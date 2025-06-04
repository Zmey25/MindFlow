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
    const readyForRoleBtn = document.getElementById('ready-for-role-btn');
    const roleCountdown = document.getElementById('role-countdown');
    const roleDisplayArea = document.getElementById('role-display-area');
    const currentRoleText = document.getElementById('current-role');
    const seenPromptArea = document.getElementById('seen-prompt-area');
    const showAgainBtn = document.getElementById('show-again-btn');
    const nextPlayerBtn = document.getElementById('next-player-btn');

    const timerDisplay = document.getElementById('timer-display');
    const endGameEarlyBtn = document.getElementById('end-game-early-btn');
    // const wakeLockStatus = document.getElementById('wake-lock-status'); // Optional status text

    const rolesRevealList = document.getElementById('roles-reveal-list');
    const playAgainBtn = document.getElementById('play-again-btn');

    // Audio Elements
    const alarmSound = document.getElementById('alarm-sound'); // Existing
    const tickTockSound = document.getElementById('tick-tock-sound'); // New
    const dingSound = document.getElementById('ding-sound');       // New

    // Стан гри
    let numPlayers = 0;
    let difficulty = '';
    let allRoles = []; // All roles fetched from data source, filtered by difficulty
    let assignedRoles = []; // [{ playerIndex: 0, role: "Роль1" }, ...]
    let currentPlayerIndexForRole = 0;

    let roleRevealTimerInterval;
    let roleRevealTimeLeft = ROLE_REVEAL_DURATION;

    let countdownTimerInterval; // Timer for the 5s countdown
    let countdownTimeLeft = COUNTDOWN_DURATION;

    let gameTimerInterval; // Main game timer
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
        // Check if we are currently on the gamePlay screen before requesting
        if ('wakeLock' in navigator && !wakeLock && screens.gamePlay.classList.contains('active')) {
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

     // Function to stop looping audio
     function stopLoopingAudio(audioElement) {
        if (audioElement) {
            audioElement.pause();
            audioElement.currentTime = 0; // Rewind to start
        }
     }

    // --- Game Flow Functions ---

    async function fetchRoles() {
        errorMessage.textContent = 'Завантаження ролей... ⏳';
        startGameBtn.disabled = true; // Disable button while fetching
        assignedRoles = []; // Ensure this is empty before fetching/assigning

        try {
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

            const rolesDataFromSheet = await response.json();

            if (!Array.isArray(rolesDataFromSheet)) {
                 throw new Error('Отримано некоректний формат даних для ролей.');
            }

            const difficultyKey = difficulty;
            allRoles = rolesDataFromSheet
                .map(item => item[difficultyKey])
                .filter(role => role && typeof role === 'string' && role.trim() !== '');

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

            shuffleArray(allRoles);

            // Assign the first 'numPlayers' roles from the shuffled list
            for (let i = 0; i < numPlayers; i++) {
                 assignedRoles.push({ playerIndex: i, role: allRoles[i] });
            }

            console.log("Assigned roles:", assignedRoles); // For debugging
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
        prepareNextPlayerForRole();
    }

    function prepareNextPlayerForRole() {
        // Clear previous timers before starting a new sequence
        clearInterval(roleRevealTimerInterval);
        clearInterval(countdownTimerInterval);
        stopLoopingAudio(tickTockSound); // Ensure tick-tock is off

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
        clearInterval(countdownTimerInterval); // Clear any existing countdown timer
        countdownTimeLeft = COUNTDOWN_DURATION;
        roleCountdown.textContent = countdownTimeLeft;
        roleCountdown.classList.remove('hidden'); // Show countdown
        readyForRoleBtn.classList.add('hidden'); // Hide ready button
        seenPromptArea.classList.add('hidden'); // Hide prompt area if triggered from 'Show Again'
        roleDisplayArea.classList.add('hidden'); // Hide role if triggered from 'Show Again'
        playerTurnInfo.textContent = 'Розверни телефон для гравців поки не почуеш звук закінчення таймеру. Не підглядай...'; // Change prompt

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
        clearInterval(roleRevealTimerInterval); // Clear any existing reveal timer
        stopLoopingAudio(tickTockSound); // Ensure tick-tock is off before starting new one

        const role = assignedRoles[currentPlayerIndexForRole].role;
        currentRoleText.textContent = role;
        roleDisplayArea.classList.remove('hidden'); // Show role area
        playerTurnInfo.textContent = `Гравець ${currentPlayerIndexForRole + 1}:`; // Change prompt
        roleRevealTimeLeft = ROLE_REVEAL_DURATION; // Reset reveal timer

        // Start tick-tock sound
        if (tickTockSound) {
             tickTockSound.currentTime = 0; // Rewind to start
             tickTockSound.play().catch(e => console.warn("Не вдалося відтворити звук tick-tock:", e));
        }

        roleRevealTimerInterval = setInterval(() => {
            roleRevealTimeLeft--;
            // Optional: Display this timer somewhere if needed, currently only console log is active
            // console.log(`Role reveal timer: ${roleRevealTimeLeft}`);

            if (roleRevealTimeLeft <= 0) {
                clearInterval(roleRevealTimerInterval);
                stopLoopingAudio(tickTockSound); // Stop tick-tock when time is up
                hideRoleAndAsk(); // Role reveal time is up
            }
        }, 1000);
    }

    function hideRoleAndAsk() {
        roleDisplayArea.classList.add('hidden'); // Hide role area
        playerTurnInfo.textContent = `Гравець ${currentPlayerIndexForRole + 1}:`; // Keep prompt
        seenPromptArea.classList.remove('hidden'); // Show "Everyone Seen?" prompt

        // Play ding sound once
        if (dingSound) {
            dingSound.currentTime = 0; // Rewind to start
            dingSound.play().catch(e => console.warn("Не вдалося відтворити звук ding:", e));
        }
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
        stopLoopingAudio(tickTockSound); // Ensure tick-tock is stopped if game ended mid-reveal

        // Play alarm sound if timer ended naturally
        if (!early && alarmSound.src) {
            alarmSound.play().catch(e => console.warn("Не вдалося відтворити звук будильника:", e));
        }

        // Play ding sound three times when game ends (either early or naturally)
        if (dingSound) {
            // Use timeouts to play the ding sound multiple times
            const playDing = () => {
                dingSound.currentTime = 0; // Rewind to start
                dingSound.play().catch(e => console.warn("Не вдалося відтворити звук ding:", e));
            };
            playDing(); // Play first ding immediately
            setTimeout(playDing, 500); // Play second ding after 0.5s
            setTimeout(playDing, 1000); // Play third ding after 1s
        }


        rolesRevealList.innerHTML = ''; // Очищаємо список
        if (assignedRoles.length > 0) {
             assignedRoles.forEach(item => {
                 const li = document.createElement('li');
                 // Use player index + 1 for display numbers (1-based)
                 li.innerHTML = `<strong>Гравець ${item.playerIndex + 1}:</strong> ${item.role}`;
                 rolesRevealList.appendChild(li);
             });
        } else {
             const li = document.createElement('li');
             li.textContent = "Інформація про ролі недоступна.";
             rolesRevealList.appendChild(li);
        }

        switchScreen('results');
    }

    // --- Event Listeners ---

    startGameBtn.addEventListener('click', async () => {
        numPlayers = parseInt(numPlayersInput.value);
        difficulty = difficultySelect.value;

        if (isNaN(numPlayers) || numPlayers < 2) {
            errorMessage.textContent = 'Будь ласка, введіть коректну кількість гравців (мінімум 2).';
            return;
        }
        errorMessage.textContent = '';

        // Fetch roles first, then start assignment if successful
        const rolesFetched = await fetchRoles();
        if (rolesFetched) {
             startRoleAssignment();
        }
    });

    // Handler for the "I'm Ready" button
    readyForRoleBtn.addEventListener('click', startCountdown);

    // Handler for "Show Again" button after role reveal
    showAgainBtn.addEventListener('click', () => {
         startCountdown(); // Restart the countdown before showing again
    });

    // Handler for "Next Player" button after role reveal
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
        assignedRoles = [];
        currentPlayerIndexForRole = 0;

        // Clear all potential timers
        clearInterval(roleRevealTimerInterval);
        clearInterval(countdownTimerInterval);
        clearInterval(gameTimerInterval);
        releaseWakeLock(); // Ensure wake lock is off

        // Stop any sounds that might still be playing
        stopLoopingAudio(tickTockSound);
        if (alarmSound) alarmSound.pause(); // Pause alarm if it was playing
        if (dingSound) dingSound.pause(); // Pause ding if it was playing


        gameTimeLeft = MAIN_GAME_DURATION; // Reset timer value display starts clean
        updateGameTimerDisplay(); // Update display (will show initial 10:00)

        // Reset form inputs
        numPlayersInput.value = "2";
        difficultySelect.value = "Easy";
        errorMessage.textContent = ''; // Clear any old error messages

        // Hide all dynamic elements from role assignment/game (ensure clean state)
        readyForRoleBtn.classList.add('hidden');
        roleCountdown.classList.add('hidden');
        roleDisplayArea.classList.add('hidden');
        seenPromptArea.classList.add('hidden');

        // Switch back to the setup screen
        switchScreen('setup');
    });

    // Initial setup display on load
    switchScreen('setup');
    updateGameTimerDisplay(); // Ensure timer display is correct initially
});
