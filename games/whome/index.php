<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Хто Я?</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <!-- Якщо хочете Font Awesome, розкоментуйте: -->
    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> -->
</head>
<body>
    <div id="game-container">
        <!-- Екран налаштувань -->
        <div id="setup-screen" class="screen active">
            <h1>🎭 Хто Я? 🕵️‍♀️</h1>
            <div class="rules">
                <h2>📜 Правила гри:</h2>
                <p>1. Виберіть кількість гравців (мін. 2) та складність 🧐.</p>
                <p>2. По черзі передавайте телефон 🤳. Коли ваша черга, хтось інший натискає "Показати роль".</p>
                <p>3. Не підглядайте! 🤫 Запам'ятайте свою роль.</p>
                <p>4. Коли всі отримають ролі, почнеться таймер ⏱️.</p>
                <p>5. Задавайте по колу питання іншим гравцям про СЕБЕ (відповідь "Так" 👍 або "Ні" 👎), щоб вгадати, хто ви.</p>
                <p>6. Вгадайте свою роль до закінчення часу! 🎉</p>
            </div>
            <div class="settings-form">
                <label for="num-players">Кількість гравців:</label>
                <input type="number" id="num-players" min="2" max="10" value="2">

                <label for="difficulty">Рівень складності:</label>
                <select id="difficulty">
                    <option value="Easy">Легкий 😊</option>
                    <option value="Medium">Середній 🤔</option>
                    <option value="Hard">Складний 🤯</option>
                </select>
                <button id="start-game-btn">🚀 Почати гру!</button>
                <p id="error-message" class="error-text"></p>
            </div>
        </div>

        <!-- Екран роздачі ролей -->
        <div id="role-assignment-screen" class="screen">
            <h2 id="player-turn-info"></h2>
            <p id="look-away-text">Інший гравець, натисніть, щоб показати роль 👇</p>
            <div id="role-display-area" class="hidden">
                <p>Твоя роль:</p>
                <h3 id="current-role"></h3>
                <button id="role-seen-btn">✅ Зрозуміло, передаю телефон</button>
            </div>
            <button id="show-role-btn">👀 Показати роль</button>
        </div>

        <!-- Екран гри (таймер) -->
        <div id="game-play-screen" class="screen">
            <h2>⏳ Гра почалася! ⏳</h2>
            <p>Задавайте питання та відгадуйте!</p>
            <div id="timer-display">10:00</div>
            <button id="end-game-early-btn">🏁 Завершити достроково</button>
        </div>

        <!-- Екран результатів -->
        <div id="results-screen" class="screen">
            <h2>🔔 Час вийшов! / Гру завершено! 🔔</h2>
            <p>Хто ким був:</p>
            <ul id="roles-reveal-list"></ul>
            <button id="play-again-btn">🔄 Грати ще раз</button>
        </div>
    </div>

    <audio id="alarm-sound" src="sounds/alarm.mp3" preload="auto"></audio>
    <!-- Створіть папку sounds та покладіть туди файл alarm.mp3 -->
    <!-- Можете знайти безкоштовний звук будильника онлайн -->

    <script src="script.js"></script>
</body>
</html>