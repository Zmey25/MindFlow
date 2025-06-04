document.addEventListener('DOMContentLoaded', () => {
    const LOCAL_DATA_PROVIDER_URL = '../get_sheet_data.php';
    const GAME_ROLES_SHEET_NAME = 'whome';
    const ROLE_REVEAL_DURATION = 10; // seconds the role is shown
    const COUNTDOWN_DURATION = 5; // seconds countdown before reveal
    const MAIN_GAME_DURATION = 10 * 60; // 10 minutes in seconds

    // Елементи DOM
    const screens = {
        setup: document.getElementById('setup-screen'),
        roleAssignment: document.getElementById('role-assignment-screen'),
        gamePlay: document.getElementById('game-play-screen'),
        results: document.getElementById('results-screen'),
    };

    const numPlayersInput = document.getElementById('num-players');
    const difficultySelect = document.getElementById('difficulty');
    const startGameBtn = document.getElementById('start-game-btn');
    const errorMessage = document.getElementById('error-message');

    const playerTurnInfo = document.getElementById('player-turn-info');
    // Removed: lookAwayText, showRoleBtn, roleSeenBtn
    const readyForRoleBtn = document.getElementById('ready-for-role-btn'); // New
    const roleCountdown = document.getElementById('role-countdown'); // New
    const roleDisplayArea = document.getElementById('role-display-area');
    const currentRoleText = document.getElementById('current-role');
    const seenPromptArea = document.getElementById('seen-prompt-area'); // New
    const showAgainBtn = document.getElementById('show-again-btn'); // New
    const nextPlayerBtn = document.getElementById('next-player-btn'); // New

    const timerDisplay = document.getElementById('timer-display');
    const endGameEarlyBtn = document.getElementById('end-game-early-btn');
    // const wakeLockStatus = document.getElementById('wake-lock-status'); // Optional status text

    const rolesRevealList = document.getElementById('roles-reveal-list');
    const playAgainBtn = document.getElementById('play-again-btn');
    const alarmSound = document.getElementById('alarm-sound');
    // const revealSound = document.getElementById('reveal-sound'); // Optional sound

    // Стан гри
    let numPlayers = 0;
    let difficulty = '';
    let allRoles = []; // All roles fetched from data source
    let availableRoles = []; // Roles available to be assigned for this game
    let assignedRoles = []; // [{ playerIndex: 0, role: "Роль1" }, ...]
    let currentPlayerIndexForRole = 0;

    let roleRevealTimerInterval;
    let roleRevealTimeLeft = ROLE_REVEAL_DURATION;

    let countdownTimerInterval; // New timer for the 5s countdown
    let countdownTimeLeft = COUNTDOWN_DURATION;

    let gameTimerInterval; // Renamed from timerInterval
    let gameTimeLeft = MAIN_GAME_DURATION;

    let wakeLock = null; // For Screen Wake Lock API

    // --- Utility Functions ---

    function switchScreen(activeScreen) {
        for (const screenKey in screens) {
            screens[screenKey].classList.remove('active');
        }
        screens[activeScreen].classList.add('active');
    }

    function shuffleArray(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
    }

     // Function to request wake lock
     async function requestWakeLock() {
        if ('wakeLock' in navigator && !wakeLock) {
            try {
                wakeLock = await navigator.wakeLock.request('screen');
                // if (wakeLockStatus) wakeLockStatus.textContent = 'Screen wake lock active.';
                 console.log('Screen wake lock active.');
                wakeLock.addEventListener('release', () => {
                    // if (wakeLockStatus) wakeLockStatus.textContent = 'Screen wake lock released.';
                     console.log('Screen wake lock released.');
                });
            } catch (err) {
                // if (wakeLockStatus) wakeLockStatus.textContent = `Wake lock error: ${err.name}, ${err.message}`;
                console.error(`Wake lock error: ${err.name}, ${err.message}`);
            }
        }
    }

    // Function to release wake lock
    function releaseWakeLock() {
        if (wakeLock) {
            wakeLock.release();
            wakeLock = null;
             // if (wakeLockStatus) wakeLockStatus.textContent = '';
             console.log('Screen wake lock released explicitly.');
        }
    }

     // Handle visibility change - attempt to re-acquire wake lock if document becomes visible
     document.addEventListener('visibilitychange', async () => {
        if (wakeLock !== null && document.visibilityState === 'visible') {
            // Check if we are still in the gameplay screen before re-requesting
            if (screens.gamePlay.classList.contains('active')) {
                console.log('Visibility changed, attempting to re-acquire wake lock...');
                 await requestWakeLock();
            }
        }
     });


    // --- Game Flow Functions ---

    async function fetchRoles() {
        errorMessage.textContent = 'Завантаження ролей... ⏳';
        startGameBtn.disabled = true; // Disable button while fetching
        try {
            // Запит до нашого локального PHP-скрипта
            const response = await fetch(`${LOCAL_DATA_PROVIDER_URL}?sheetName=${encodeURIComponent(GAME_ROLES_SHEET_NAME)}`);

            if (!response.ok) {
                let errorMsg = `Помилка ${response.status}: ${response.statusText}`;
                try {
                    const errorData = await response.json();
                    if (errorData && errorData.message) {
                        errorMsg = errorData.message;
                    }
                } catch (e) { /* ігноруємо, якщо відповідь не JSON */ }
                throw new Error(errorMsg);
            }

            const rolesDataFromSheet = await response.json(); // Це вже масив об'єктів [{...}, ...]

            if (!Array.isArray(rolesDataFromSheet)) {
                 throw new Error('Отримано некоректний формат даних для ролей.');
            }

            // Фільтруємо ролі за обраною складністю та видаляємо порожні
            const difficultyKey = difficulty; // "Easy", "Medium", "Hard"
            allRoles = rolesDataFromSheet
                .map(item => item[difficultyKey]) // Отримуємо значення для поточного рівня складності
                .filter(role => role && typeof role === 'string' && role.trim() !== ''); // Видаляємо порожні або не рядкові значення

            if (allRoles.length === 0) {
                 const baseMsg = rolesDataFromSheet.length > 0
                    ? `😥 На листі "${GAME_ROLES_SHEET_NAME}" немає ролей для рівня складності "${difficulty}". Перевірте колонку "${difficultyKey}".`
                    : `😥 Лист "${GAME_ROLES_SHEET_NAME}" порожній або не містить даних.`;
                errorMessage.textContent = baseMsg;
                return false;
            }

            if (allRoles.length < numPlayers) {
                errorMessage.textContent = `😥 Недостатньо унікальних ролей (${allRoles.length}) на листі "${GAME_ROLES_SHEET_NAME}" для ${numPlayers} гравців на рівні "${difficulty}".`;
                return false;
            }

            // Use a copy for assigning, so we don't modify the original `allRoles`
            availableRoles = [...allRoles];
            shuffleArray(availableRoles); // Перемішуємо доступні ролі
            errorMessage.textContent = '';
            return true;

        } catch (error) {
            console.error('Помилка завантаження ролей:', error);
            errorMessage.textContent = `🚨 Помилка завантаження ролей: ${error.message}. Переконайтеся, що cron-завдання працює і шлях до get_sheet_data.php вірний.`;
            return false;
        } finally {
             startGameBtn.disabled = false; // Re-enable button
        }
    }

    function startRoleAssignment() {
        currentPlayerIndexForRole = 0;
        assignedRoles = [];
        // Assign roles now before starting the player-by-player reveal
        for (let i = 0; i < numPlayers; i++) {
             // Take roles from the shuffled available list
             assignedRoles.push({ playerIndex: i, role: availableRoles[i] });
        }
        console.log("Assigned roles:", assignedRoles); // For debugging

        // Prepare for the first player
        prepareNextPlayerForRole();
    }

    function prepareNextPlayerForRole() {
        // Hide everything first
        readyForRoleBtn.classList.add('hidden');
        roleCountdown.classList.add('hidden');
        roleDisplayArea.classList.add('hidden');
        seenPromptArea.classList.add('hidden');
        currentRoleText.textContent = ''; // Clear previous role

        if (currentPlayerIndexForRole < numPlayers) {
            playerTurnInfo.textContent = `👉 Гравець ${currentPlayerIndexForRole + 1}, візьми телефон.`;
            readyForRoleBtn.classList.remove('hidden'); // Show the "Ready" button
            switchScreen('roleAssignment');
        } else {
            // All roles assigned
            startGamePlay();
        }
    }

    function startCountdown() {
        countdownTimeLeft = COUNTDOWN_DURATION;
        roleCountdown.textContent = countdownTimeLeft;
        roleCountdown.classList.remove('hidden'); // Show countdown
        readyForRoleBtn.classList.add('hidden'); // Hide ready button
        playerTurnInfo.textContent = 'Приготуйся...'; // Change prompt

        countdownTimerInterval = setInterval(() => {
            countdownTimeLeft--;
            roleCountdown.textContent = countdownTimeLeft;

            if (countdownTimeLeft <= 0) {
                clearInterval(countdownTimerInterval);
                roleCountdown.classList.add('hidden'); // Hide countdown
                showAndTimerRole(); // Proceed to show the role
            }
        }, 1000);
    }

    function showAndTimerRole() {
        const role = assignedRoles[currentPlayerIndexForRole].role;
        currentRoleText.textContent = role;
        roleDisplayArea.classList.remove('hidden'); // Show role area
        playerTurnInfo.textContent = `Гравець ${currentPlayerIndexForRole + 1}:`; // Change prompt
        roleRevealTimeLeft = ROLE_REVEAL_DURATION; // Reset reveal timer
        // Optional: Play reveal sound here
        // if (revealSound) revealSound.play().catch(e => console.warn("Reveal sound play error:", e));

        roleRevealTimerInterval = setInterval(() => {
            roleRevealTimeLeft--;
            // You could optionally display this timer somewhere
            console.log(`Role reveal timer: ${roleRevealTimeLeft}`);

            if (roleRevealTimeLeft <= 0) {
                clearInterval(roleRevealTimerInterval);
                hideRoleAndAsk(); // Role reveal time is up
            }
        }, 1000);
    }

    function hideRoleAndAsk() {
        roleDisplayArea.classList.add('hidden'); // Hide role area
        playerTurnInfo.textContent = `Гравець ${currentPlayerIndexForRole + 1}:`; // Keep prompt, or change?
        seenPromptArea.classList.remove('hidden'); // Show "Everyone Seen?" prompt
    }


    function startGamePlay() {
        switchScreen('gamePlay');
        gameTimeLeft = MAIN_GAME_DURATION; // Reset game timer
        updateGameTimerDisplay();
        // Start main game timer
        gameTimerInterval = setInterval(() => {
            gameTimeLeft--;
            updateGameTimerDisplay();
            if (gameTimeLeft <= 0) {
                endGame(false); // false - not early
            }
        }, 1000);

        // Request wake lock when game timer starts
        requestWakeLock();
    }

    function updateGameTimerDisplay() {
        const minutes = Math.floor(gameTimeLeft / 60);
        const seconds = gameTimeLeft % 60;
        timerDisplay.textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
    }

    function endGame(early = false) {
        clearInterval(gameTimerInterval);
        // Release wake lock when game ends
        releaseWakeLock();

        if (!early && alarmSound.src) { // Відтворюємо звук, тільки якщо таймер закінчився сам
            alarmSound.play().catch(e => console.warn("Не вдалося відтворити звук будильника:", e));
        }

        rolesRevealList.innerHTML = ''; // Очищаємо список
        assignedRoles.forEach(item => {
            const li = document.createElement('li');
            // Use player index + 1 for display numbers (1-based)
            li.innerHTML = `<strong>Гравець ${item.playerIndex + 1}:</strong> ${item.role}`;
            rolesRevealList.appendChild(li);
        });
        switchScreen('results');
    }

    // --- Event Listeners ---

    startGameBtn.addEventListener('click', async () => {
        numPlayers = parseInt(numPlayersInput.value);
        difficulty = difficultySelect.value;

        if (numPlayers < 2) {
            errorMessage.textContent = 'Мінімум 2 гравці!';
            return;
        }
        errorMessage.textContent = '';

        // Fetch roles first, then start assignment if successful
        const rolesFetched = await fetchRoles();
        if (rolesFetched) {
             startRoleAssignment();
        }
    });

    // New: Handler for the "I'm Ready" button
    readyForRoleBtn.addEventListener('click', startCountdown);

    // New: Handler for "Show Again" button after role reveal
    showAgainBtn.addEventListener('click', () => {
         seenPromptArea.classList.add('hidden'); // Hide prompt area
         showAndTimerRole(); // Restart the reveal process for the same player
    });

    // New: Handler for "Next Player" button after role reveal
    nextPlayerBtn.addEventListener('click', () => {
        currentPlayerIndexForRole++; // Move to the next player
        prepareNextPlayerForRole(); // Prepare the screen for the next player
    });


    endGameEarlyBtn.addEventListener('click', () => {
        if (confirm("Завершити гру достроково?")) {
            endGame(true); // true - early
        }
    });

    playAgainBtn.addEventListener('click', () => {
        // Скидання стану для нової гри
        allRoles = [];
        availableRoles = [];
        assignedRoles = [];
        currentPlayerIndexForRole = 0;

        // Clear all potential timers
        clearInterval(roleRevealTimerInterval);
        clearInterval(countdownTimerInterval);
        clearInterval(gameTimerInterval);
        releaseWakeLock(); // Ensure wake lock is off

        timeLeft = MAIN_GAME_DURATION; // Reset timer value display starts clean
        updateGameTimerDisplay(); // Update display

        // Reset form inputs
        numPlayersInput.value = "2";
        difficultySelect.value = "Easy";
        errorMessage.textContent = ''; // Clear any old error messages

        // Hide all dynamic elements from role assignment/game
        readyForRoleBtn.classList.add('hidden');
        roleCountdown.classList.add('hidden');
        roleDisplayArea.classList.add('hidden');
        seenPromptArea.classList.add('hidden');
        currentRoleText.textContent = '';

        switchScreen('setup'); // Go back to setup
    });

    // --- Initialization ---
    switchScreen('setup'); // Show the initial setup screen
    updateGameTimerDisplay(); // Display initial timer value (10:00)
});
