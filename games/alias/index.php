<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Аліас 🎲 Гра Слів</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <!-- Екран налаштувань -->
        <div id="settings-screen" class="screen active">
            <h1>Аліас 🥳</h1>
            <div class="rules">
                <h2>📜 Правила гри:</h2>
                <p>1. Розділіться на команди.</p>
                <p>2. Один гравець пояснює слова своїй команді, не використовуючи однокореневі.</p>
                <p>3. За кожне вгадане слово команда отримує 1 бал.</p>
                <p>4. Перемагає команда, яка першою набере задану кількість балів!</p>
            </div>

            <h2>⚙️ Налаштування:</h2>
            <div class="form-group">
                <label for="word-set">📚 Набір слів:</label>
                <select id="word-set">
                    <option value="alias">alias</option>
                    <!-- Додайте сюди інші набори, якщо є -->
                </select>
            </div>

            <div class="form-group">
                <label>🤔 Складність (оберіть хоча б одну):</label>
                <div>
                    <input type="checkbox" id="diff-easy" value="Easy" checked> <label for="diff-easy">Легкі 😎</label>
                </div>
                <div>
                    <input type="checkbox" id="diff-medium" value="Medium"> <label for="diff-medium">Середні 🤔</label>
                </div>
                <div>
                    <input type="checkbox" id="diff-hard" value="Hard"> <label for="diff-hard">Складні 🤯</label>
                </div>
            </div>

            <div class="form-group">
                <label for="num-teams">👨‍👩‍👧‍👦 Кількість команд (2-4):</label>
                <input type="number" id="num-teams" value="2" min="2" max="4">
            </div>

            <div id="team-names-container">
                <!-- Сюди JS додасть поля для назв команд -->
            </div>

            <div class="form-group">
                <label for="round-time">⏱️ Час на раунд (секунд):</label>
                <select id="round-time">
                    <option value="30">30 сек</option>
                    <option value="60" selected>60 сек</option>
                    <option value="90">90 сек</option>
                    <option value="120">120 сек</option>
                </select>
            </div>

            <div class="form-group">
                <label for="win-score">🏆 Очки для перемоги:</label>
                <input type="number" id="win-score" value="30" min="5">
            </div>

            <button id="start-game-btn" class="btn btn-primary">🚀 Почати Гру!</button>
        </div>

        <!-- Екран передачі ходу -->
        <div id="turn-start-screen" class="screen">
            <h2 id="current-team-turn-label">Хід команди: <span id="turn-start-team-name"></span></h2>
            <p>Приготуйтесь! <span id="explainer-call"></span>, ти пояснюєш! 🧐</p>
            <button id="begin-round-btn" class="btn btn-primary">▶️ Розпочати Раунд</button>
            <button id="show-scores-interim-btn" class="btn btn-secondary">📊 Показати Рахунок</button>
        </div>

        <!-- Ігровий екран -->
        <div id="game-screen" class="screen">
            <div class="game-header">
                <div id="timer">⏳ 00:00</div>
                <div id="current-round-score">Бали за раунд: 0</div>
            </div>
            <div id="word-display-container">
                <p id="word-to-explain">Слово</p>
            </div>
            <div class="game-controls">
                <button id="guessed-btn" class="btn btn-success">Вгадали 👍</button>
                <button id="skip-btn" class="btn btn-warning">Пропустити 👎</button>
            </div>
        </div>

        <!-- Екран результатів раунду -->
        <div id="round-end-screen" class="screen">
            <h2>🏁 Раунд Завершено!</h2>
            <p>Команда <strong id="round-end-team-name"></strong> заробила <strong id="round-end-score"></strong> балів.</p>
            <h3>Вгадані слова:</h3>
            <ul id="guessed-words-list"></ul>
            <h3>Пропущені слова:</h3>
            <ul id="skipped-words-list"></ul>
            <button id="next-turn-btn" class="btn btn-primary">Далі 👉</button>
            <button id="show-scores-final-btn" class="btn btn-secondary">📊 Показати Рахунок</button>
        </div>
        
        <!-- Екран таблиці результатів -->
        <div id="scoreboard-screen" class="screen">
            <h2>🏆 Таблиця Лідерів 🏆</h2>
            <div id="scores-display">
                <!-- Сюди JS додасть результати команд -->
            </div>
            <button id="continue-game-btn" class="btn btn-primary">Продовжити Гру ▶️</button>
            <button id="main-menu-btn" class="btn btn-secondary">🏠 Головне Меню</button>
        </div>

        <!-- Екран завершення гри -->
        <div id="game-over-screen" class="screen">
            <h2 id="winner-announcement">🎉 Перемогла Команда <span id="winning-team-name"></span>! 🎉</h2>
            <h3>Підсумковий Рахунок:</h3>
            <div id="final-scores-display">
                <!-- Сюди JS додасть фінальні результати -->
            </div>
            <button id="play-again-btn" class="btn btn-primary">🔄 Грати Ще Раз</button>
        </div>

        <div id="loading-indicator" style="display:none; text-align:center; font-size: 1.5em; margin-top: 20px;">
            ⏳ Завантаження слів...
        </div>
        <div id="error-message" style="display:none; color: red; text-align:center; margin-top: 20px;"></div>

    </div>
    <script src="script.js"></script>
</body>
</html>