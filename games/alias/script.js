document.addEventListener('DOMContentLoaded', () => {
    // Елементи DOM
    const screens = {
        settings: document.getElementById('settings-screen'),
        turnStart: document.getElementById('turn-start-screen'),
        game: document.getElementById('game-screen'),
        roundEnd: document.getElementById('round-end-screen'),
        scoreboard: document.getElementById('scoreboard-screen'),
        gameOver: document.getElementById('game-over-screen'),
    };

    const settingsForm = {
        wordSet: document.getElementById('word-set'),
        diffEasy: document.getElementById('diff-easy'),
        diffMedium: document.getElementById('diff-medium'),
        diffHard: document.getElementById('diff-hard'),
        numTeams: document.getElementById('num-teams'),
        teamNamesContainer: document.getElementById('team-names-container'),
        roundTime: document.getElementById('round-time'),
        winScore: document.getElementById('win-score'),
        startGameBtn: document.getElementById('start-game-btn')
    };

    const turnStartElements = {
        teamNameLabel: document.getElementById('turn-start-team-name'),
        explainerCall: document.getElementById('explainer-call'),
        beginRoundBtn: document.getElementById('begin-round-btn'),
        showScoresInterimBtn: document.getElementById('show-scores-interim-btn'),
        currentTeamTurnLabel: document.getElementById('current-team-turn-label')
    };

    const gameElements = {
        timerDisplay: document.getElementById('timer'),
        currentRoundScoreDisplay: document.getElementById('current-round-score'),
        wordDisplay: document.getElementById('word-to-explain'),
        guessedBtn: document.getElementById('guessed-btn'),
        skipBtn: document.getElementById('skip-btn')
    };

    const roundEndElements = {
        teamName: document.getElementById('round-end-team-name'),
        score: document.getElementById('round-end-score'),
        guessedWordsList: document.getElementById('guessed-words-list'),
        skippedWordsList: document.getElementById('skipped-words-list'),
        nextTurnBtn: document.getElementById('next-turn-btn'),
        showScoresFinalBtn: document.getElementById('show-scores-final-btn')
    };
    
    const scoreboardElements = {
        scoresDisplay: document.getElementById('scores-display'),
        continueGameBtn: document.getElementById('continue-game-btn'),
        mainMenuBtn: document.getElementById('main-menu-btn')
    };

    const gameOverElements = {
        winnerAnnouncement: document.getElementById('winner-announcement'),
        winningTeamName: document.getElementById('winning-team-name'),
        finalScoresDisplay: document.getElementById('final-scores-display'),
        playAgainBtn: document.getElementById('play-again-btn')
    };
    
    const loadingIndicator = document.getElementById('loading-indicator');
    const errorMessage = document.getElementById('error-message');

    // Стан гри
    let allWords = [];
    let currentWordsPool = [];
    let currentWordIndex = 0;
    let gameSettings = {};
    let teamData = []; // { name: 'Команда 1', score: 0, playerIndex: 0 }
    let currentTeamIdx = 0;
    let timerInterval;
    let timeLeft = 0;
    let currentRoundScore = 0;
    let roundGuessedWords = [];
    let roundSkippedWords = [];
    const teamColors = ['#FF6347', '#4682B4', '#3CB371', '#FFD700']; // Томатний, Сталево-синій, Морська зелень, Золотий


    // Функції
    function showScreen(screenId) {
        Object.values(screens).forEach(screen => screen.classList.remove('active'));
        screens[screenId].classList.add('active');
    }
    
    function displayError(message) {
        errorMessage.textContent = message;
        errorMessage.style.display = 'block';
        loadingIndicator.style.display = 'none';
        setTimeout(() => errorMessage.style.display = 'none', 5000);
    }

    function updateTeamNameInputs() {
        const num = parseInt(settingsForm.numTeams.value);
        settingsForm.teamNamesContainer.innerHTML = '';
        for (let i = 0; i < num; i++) {
            const div = document.createElement('div');
            div.classList.add('form-group');
            const label = document.createElement('label');
            label.setAttribute('for', `team-name-${i}`);
            label.textContent = `Назва команди ${i + 1} 🚩:`;
            const input = document.createElement('input');
            input.type = 'text';
            input.id = `team-name-${i}`;
            input.value = `Команда ${i + 1}`;
            input.required = true;
            div.appendChild(label);
            div.appendChild(input);
            settingsForm.teamNamesContainer.appendChild(div);
        }
    }
    settingsForm.numTeams.addEventListener('change', updateTeamNameInputs);
    updateTeamNameInputs(); // Ініціалізація при завантаженні

    async function fetchWords() {
        const sheetName = settingsForm.wordSet.value;
        loadingIndicator.style.display = 'block';
        errorMessage.style.display = 'none';
        try {
            const response = await fetch(`../get_sheet_data.php?sheetName=${encodeURIComponent(sheetName)}`);
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || `Помилка HTTP: ${response.status}`);
            }
            allWords = await response.json();
            if (!Array.isArray(allWords) || allWords.length === 0) {
                 throw new Error("Формат даних невірний або файл слів порожній.");
            }
            loadingIndicator.style.display = 'none';
            return true;
        } catch (error) {
            console.error('Помилка завантаження слів:', error);
            displayError(`Не вдалося завантажити слова: ${error.message}. Перевірте назву файлу та його вміст.`);
            return false;
        }
    }

    function shuffleArray(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
    }

    function prepareWordPool() {
        const selectedDifficulties = [];
        if (settingsForm.diffEasy.checked) selectedDifficulties.push("Easy");
        if (settingsForm.diffMedium.checked) selectedDifficulties.push("Medium");
        if (settingsForm.diffHard.checked) selectedDifficulties.push("Hard");

        if(selectedDifficulties.length === 0){
            displayError("Будь ласка, оберіть хоча б один рівень складності.");
            return false; // Повертаємо false, щоб зупинити гру
        }

        currentWordsPool = allWords.reduce((acc, wordObj) => {
            selectedDifficulties.forEach(diff => {
                if (wordObj[diff]) {
                    acc.push(wordObj[diff]);
                }
            });
            return acc;
        }, []);

        if (currentWordsPool.length === 0) {
            displayError("Не знайдено слів для обраних рівнів складності. Спробуйте інші налаштування.");
            return false; // Повертаємо false, якщо немає слів
        }
        shuffleArray(currentWordsPool);
        currentWordIndex = 0;
        return true; // Все добре
    }

    async function startGame() {
        gameSettings = {
            wordSet: settingsForm.wordSet.value,
            difficulties: {
                easy: settingsForm.diffEasy.checked,
                medium: settingsForm.diffMedium.checked,
                hard: settingsForm.diffHard.checked
            },
            numTeams: parseInt(settingsForm.numTeams.value),
            roundTime: parseInt(settingsForm.roundTime.value),
            winScore: parseInt(settingsForm.winScore.value)
        };
        
        if (!gameSettings.difficulties.easy && !gameSettings.difficulties.medium && !gameSettings.difficulties.hard) {
            displayError("Будь ласка, оберіть хоча б один рівень складності.");
            return;
        }

        const wordsLoaded = await fetchWords();
        if (!wordsLoaded) return; // Зупинка, якщо слова не завантажено

        if (!prepareWordPool()) return; // Зупинка, якщо немає слів для обраної складності

        teamData = [];
        for (let i = 0; i < gameSettings.numTeams; i++) {
            const teamNameInput = document.getElementById(`team-name-${i}`);
            teamData.push({ 
                name: teamNameInput ? teamNameInput.value : `Команда ${i + 1}`, 
                score: 0,
                playerIndex: 0, // Для відстеження, хто пояснює наступним (якщо реалізовувати)
                color: teamColors[i % teamColors.length]
            });
        }
        currentTeamIdx = 0;
        showTurnStartScreen();
    }

    function showTurnStartScreen() {
        const currentTeam = teamData[currentTeamIdx];
        turnStartElements.teamNameLabel.textContent = currentTeam.name;
        turnStartElements.teamNameLabel.style.color = currentTeam.color;
        turnStartElements.explainerCall.textContent = currentTeam.name; // Можна додати "Гравець X з команди Y"
        turnStartElements.currentTeamTurnLabel.style.color = currentTeam.color;
        showScreen('turnStart');
    }

    function beginRound() {
        if (!prepareWordPool()) { // Перепідготовка слів на випадок, якщо попередній пул закінчився
            showScreen('settings'); // Або інше повідомлення про помилку
            return;
        }
        currentRoundScore = 0;
        roundGuessedWords = [];
        roundSkippedWords = [];
        gameElements.currentRoundScoreDisplay.textContent = `Бали за раунд: ${currentRoundScore}`;
        timeLeft = gameSettings.roundTime;
        updateTimerDisplay();
        displayNextWord();
        showScreen('game');
        startTimer();
    }

    function startTimer() {
        clearInterval(timerInterval);
        timerInterval = setInterval(() => {
            timeLeft--;
            updateTimerDisplay();
            if (timeLeft <= 0) {
                endRound();
            }
        }, 1000);
    }

    function stopTimer() {
        clearInterval(timerInterval);
    }

    function updateTimerDisplay() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        gameElements.timerDisplay.textContent = `⏳ ${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        if (timeLeft <= 10 && timeLeft > 0) { // Червоний колір для останніх 10 секунд
            gameElements.timerDisplay.style.color = 'white';
            gameElements.timerDisplay.style.backgroundColor = '#D32F2F'; // Темно-червоний
        } else {
            gameElements.timerDisplay.style.color = 'white';
            gameElements.timerDisplay.style.backgroundColor = '#FF6347'; // Томатний
        }
    }

    function displayNextWord() {
        if (currentWordIndex < currentWordsPool.length) {
            gameElements.wordDisplay.textContent = currentWordsPool[currentWordIndex];
        } else {
            gameElements.wordDisplay.textContent = "Слова скінчились! 😮";
            // Можна автоматично завершити раунд або заблокувати кнопки
            gameElements.guessedBtn.disabled = true;
            gameElements.skipBtn.disabled = true;
        }
    }

    function handleGuessed() {
        if (currentWordIndex < currentWordsPool.length) {
            currentRoundScore++;
            teamData[currentTeamIdx].score++;
            roundGuessedWords.push(currentWordsPool[currentWordIndex]);
            gameElements.currentRoundScoreDisplay.textContent = `Бали за раунд: ${currentRoundScore}`;
            currentWordIndex++;
            displayNextWord();
            checkWinCondition();
        }
    }

    function handleSkip() {
        if (currentWordIndex < currentWordsPool.length) {
            // Опціонально: teamData[currentTeamIdx].score--; // Штраф за пропуск
            roundSkippedWords.push(currentWordsPool[currentWordIndex]);
            currentWordIndex++;
            displayNextWord();
        }
    }
    
    function renderList(listElement, wordsArray) {
        listElement.innerHTML = '';
        if (wordsArray.length === 0) {
            const li = document.createElement('li');
            li.textContent = "Немає";
            listElement.appendChild(li);
        } else {
            wordsArray.forEach(word => {
                const li = document.createElement('li');
                li.textContent = word;
                listElement.appendChild(li);
            });
        }
    }


    function endRound() {
        stopTimer();
        gameElements.guessedBtn.disabled = false; // Повертаємо кнопки в активний стан
        gameElements.skipBtn.disabled = false;

        roundEndElements.teamName.textContent = teamData[currentTeamIdx].name;
        roundEndElements.teamName.style.color = teamData[currentTeamIdx].color;
        roundEndElements.score.textContent = currentRoundScore;
        
        renderList(roundEndElements.guessedWordsList, roundGuessedWords);
        renderList(roundEndElements.skippedWordsList, roundSkippedWords);

        showScreen('roundEnd');
    }

    function checkWinCondition() {
        const winner = teamData.find(team => team.score >= gameSettings.winScore);
        if (winner) {
            showGameOverScreen(winner);
            return true;
        }
        return false;
    }

    function nextTurn() {
        if (checkWinCondition()) return;

        currentTeamIdx = (currentTeamIdx + 1) % gameSettings.numTeams;
        showTurnStartScreen();
    }
    
    function updateScoresDisplay(container) {
        container.innerHTML = '';
        const sortedTeams = [...teamData].sort((a, b) => b.score - a.score);
        sortedTeams.forEach(team => {
            const teamDiv = document.createElement('div');
            teamDiv.classList.add('team-score-item');
            
            const nameSpan = document.createElement('span');
            nameSpan.classList.add('team-name');
            nameSpan.textContent = `${team.name}: `;
            nameSpan.style.color = team.color;
            
            const scoreSpan = document.createElement('span');
            scoreSpan.classList.add('score-value');
            scoreSpan.textContent = team.score;
            
            teamDiv.appendChild(nameSpan);
            teamDiv.appendChild(scoreSpan);
            container.appendChild(teamDiv);
        });
    }

    function showScoreboard() {
        updateScoresDisplay(scoreboardElements.scoresDisplay);
        showScreen('scoreboard');
    }


    function showGameOverScreen(winner) {
        gameOverElements.winningTeamName.textContent = winner.name;
        gameOverElements.winningTeamName.style.color = winner.color;
        gameOverElements.winnerAnnouncement.style.borderColor = winner.color; // Якщо є рамка
        updateScoresDisplay(gameOverElements.finalScoresDisplay);
        showScreen('gameOver');
    }

    function resetGame() {
        allWords = [];
        currentWordsPool = [];
        currentWordIndex = 0;
        gameSettings = {};
        teamData = [];
        currentTeamIdx = 0;
        stopTimer();
        timeLeft = 0;
        currentRoundScore = 0;
        // Скинути поля вводу налаштувань до початкових значень, якщо потрібно,
        // або просто показати екран налаштувань, щоб користувач ввів їх знову.
        // settingsForm.numTeams.value = 2; // Приклад
        // updateTeamNameInputs();
        showScreen('settings');
    }

    // Обробники подій
    settingsForm.startGameBtn.addEventListener('click', startGame);
    turnStartElements.beginRoundBtn.addEventListener('click', beginRound);
    turnStartElements.showScoresInterimBtn.addEventListener('click', showScoreboard);

    gameElements.guessedBtn.addEventListener('click', handleGuessed);
    gameElements.skipBtn.addEventListener('click', handleSkip);

    roundEndElements.nextTurnBtn.addEventListener('click', nextTurn);
    roundEndElements.showScoresFinalBtn.addEventListener('click', showScoreboard);
    
    scoreboardElements.continueGameBtn.addEventListener('click', () => {
        // Якщо гру завершено, кнопка не має нічого робити або має бути прихована
        if (teamData.some(team => team.score >= gameSettings.winScore)) {
            // Можна показати екран Game Over, якщо він ще не був показаний
            const winner = teamData.find(team => team.score >= gameSettings.winScore);
            if (winner) showGameOverScreen(winner);
        } else {
            showTurnStartScreen(); // Повернення до екрану передачі ходу
        }
    });
    scoreboardElements.mainMenuBtn.addEventListener('click', resetGame);


    gameOverElements.playAgainBtn.addEventListener('click', resetGame);

    // Ініціалізація
    showScreen('settings');
});