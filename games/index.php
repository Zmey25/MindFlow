<?php
// games/index.php
// Simple landing page for the games directory

// You might want to include header/footer from the main site if available
// include_once('../includes/header.php');
// include_once('../includes/footer.php');

?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ігри</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="games.css">
    <!-- Consider reusing main site styles if applicable -->
    <!-- <link rel="stylesheet" href="../assets/css/style.css"> -->
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

            <!-- Add other games here as you create them -->
        </div>
    </div>

    <?php
    // You might include a footer here
    // include_once('../includes/footer.php');
    ?>
</body>
</html>
