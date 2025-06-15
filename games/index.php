<?php
// games/index.php

?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ігри</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="games.css">
</head>
<body>
    <div class="container">
        <h1>🎲 Ігри</h1>

        <p>Ласкаво просимо до розділу ігор! Оберіть гру:</p>

        <div class="game-list">
            <a href="alias/" class="game-card">
                <h2>🔠 Аліас</h2>
                <p>Поясни слово, не використовуючи однокорінні!</p>
            </a>

            <a href="whome/" class="game-card">
                <h2>🎭 Хто Я?</h2>
                <p>Вгадай свою роль, ставлячи питання іншим!</p>
            </a>

            <a href="micolo/" class="game-card">
                <h2>🃏 Micolo</h2>
                <p>Алкогра! Грай з іншими відповідаючи на питання та роблючи різні завдання!</p>
            </a>

            <!-- Add other games here as you create them -->
        </div>
    </div>

    <?php
    ?>
</body>
</html>
