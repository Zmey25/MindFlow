document.addEventListener('DOMContentLoaded', () => {
    const LOCAL_DATA_PROVIDER_URL = '../get_sheet_data.php';
    const GAME_ROLES_SHEET_NAME = 'whome';

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
    const lookAwayText = document.getElementById('look-away-text');
    const showRoleBtn = document.getElementById('show-role-btn');
    const roleDisplayArea = document.getElementById('role-display-area');
    const currentRoleText = document.getElementById('current-role');
    const roleSeenBtn = document.getElementById('role-seen-btn');

    const timerDisplay = document.getElementById('timer-display');
    const endGameEarlyBtn = document.getElementById('end-game-early-btn');

    const rolesRevealList = document.getElementById('roles-reveal-list');
    const playAgainBtn = document.getElementById('play-again-btn');
    const alarmSound = document.getElementById('alarm-sound');

    // Стан гри
    let numPlayers = 0;
    let difficulty = '';
    let allRoles = [];
    let assignedRoles = []; // [{ player: 1, role: "Роль1" }, ...]
    let currentPlayerIndexForRole = 0;
    let timerInterval;
    let timeLeft = 10 * 60; // 10 хвилин в секундах

    // Функції
    function switchScreen(activeScreen) {
        for (const screenKey in screens) {
            screens[screenKey].classList.remove('active');
        }
        screens[activeScreen].classList.add('active');
    }

    async function fetchRoles() {
        errorMessage.textContent = 'Завантаження ролей... ⏳';
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

            // Фільтруємо ролі за обраною складністю.
            // Припускаємо, що `difficulty` ("Easy", "Medium", "Hard") - це ключ в об'єктах rolesDataFromSheet
            const difficultyKey = difficulty; // "Easy", "Medium", "Hard"
            allRoles = rolesDataFromSheet
                .map(item => item[difficultyKey]) // Отримуємо значення для поточного рівня складності
                .filter(role => role && typeof role === 'string' && role.trim() !== ''); // Видаляємо порожні або не рядкові значення

            if (allRoles.length === 0) {
                if (rolesDataFromSheet.length > 0) {
                     errorMessage.textContent = `😥 На листі "${GAME_ROLES_SHEET_NAME}" немає ролей для рівня складності "${difficulty}". Перевірте колонку "${difficultyKey}".`;
                } else {
                     errorMessage.textContent = `😥 Лист "${GAME_ROLES_SHEET_NAME}" порожній або не містить даних.`;
                }
                return false;
            }
            
            if (allRoles.length < numPlayers) {
                errorMessage.textContent = `😥 Недостатньо унікальних ролей (${allRoles.length}) на листі "${GAME_ROLES_SHEET_NAME}" для ${numPlayers} гравців на рівні "${difficulty}".`;
                return false;
            }

            shuffleArray(allRoles); // Перемішуємо відфільтровані ролі
            errorMessage.textContent = '';
            return true;

        } catch (error) {
            console.error('Помилка завантаження ролей:', error);
            errorMessage.textContent = `🚨 Помилка завантаження ролей: ${error.message}. Переконайтеся, що cron-завдання працює.`;
            return false;
        }
    }

    function shuffleArray(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
    }

    function startRoleAssignment() {
        currentPlayerIndexForRole = 0;
        assignedRoles = [];
        roleDisplayArea.classList.add('hidden');
        showRoleBtn.classList.remove('hidden');
        lookAwayText.classList.remove('hidden');
        assignRoleToNextPlayer();
        switchScreen('roleAssignment');
    }

    function assignRoleToNextPlayer() {
        if (currentPlayerIndexForRole < numPlayers) {
            playerTurnInfo.textContent = `🤫 Гравець ${currentPlayerIndexForRole + 1}, не підглядай!`;
            currentRoleText.textContent = ''; // Очищаємо попередню роль
            roleDisplayArea.classList.add('hidden');
            showRoleBtn.classList.remove('hidden');
            lookAwayText.classList.remove('hidden');
        } else {
            // Всі ролі роздані, починаємо гру
            startGamePlay();
        }
    }

    showRoleBtn.addEventListener('click', () => {
        const role = allRoles[currentPlayerIndexForRole]; // Беремо роль без видалення, якщо гравців більше ніж унікальних ролей
        currentRoleText.textContent = role;
        assignedRoles.push({ player: currentPlayerIndexForRole + 1, role: role });

        roleDisplayArea.classList.remove('hidden');
        showRoleBtn.classList.add('hidden');
        lookAwayText.classList.add('hidden');
    });

    roleSeenBtn.addEventListener('click', () => {
        currentPlayerIndexForRole++;
        assignRoleToNextPlayer();
    });

    function startGamePlay() {
        switchScreen('gamePlay');
        timeLeft = 10 * 60; // Скидаємо таймер
        updateTimerDisplay();
        timerInterval = setInterval(() => {
            timeLeft--;
            updateTimerDisplay();
            if (timeLeft <= 0) {
                endGame(false); // false - не достроково
            }
        }, 1000);
    }

    function updateTimerDisplay() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        timerDisplay.textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
    }

    function endGame(early = false) {
        clearInterval(timerInterval);
        if (!early && alarmSound.src) { // Відтворюємо звук, тільки якщо таймер закінчився сам
            alarmSound.play().catch(e => console.warn("Не вдалося відтворити звук:", e));
        }
        
        rolesRevealList.innerHTML = ''; // Очищаємо список
        assignedRoles.forEach(item => {
            const li = document.createElement('li');
            li.innerHTML = `<strong>Гравець ${item.player}:</strong> ${item.role}`;
            rolesRevealList.appendChild(li);
        });
        switchScreen('results');
    }

    // Обробники подій
    startGameBtn.addEventListener('click', async () => {
        numPlayers = parseInt(numPlayersInput.value);
        difficulty = difficultySelect.value;

        if (numPlayers < 2) {
            errorMessage.textContent = 'Мінімум 2 гравці!';
            return;
        }
        errorMessage.textContent = '';

        const rolesFetched = await fetchRoles();
        if (rolesFetched) {
            startRoleAssignment();
        }
    });

    endGameEarlyBtn.addEventListener('click', () => {
        // Можна додати підтвердження
        if (confirm("Завершити гру достроково?")) {
            endGame(true); // true - достроково
        }
    });

    playAgainBtn.addEventListener('click', () => {
        // Скидання стану для нової гри
        allRoles = [];
        assignedRoles = [];
        currentPlayerIndexForRole = 0;
        clearInterval(timerInterval);
        timeLeft = 10 * 60;
        numPlayersInput.value = "2"; // Скидання налаштувань
        difficultySelect.value = "Easy";

        switchScreen('setup');
    });

    // Ініціалізація: показати перший екран
    switchScreen('setup');
});