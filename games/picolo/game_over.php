<?php
session_start();

// ... (перевірка, чи гра дійсно завершена, залишається) ...
if (!isset($_SESSION['game_over']) || $_SESSION['game_over'] !== true) {
    if (isset($_SESSION['game_started']) && $_SESSION['game_started'] === true) {
        header('Location: game.php');
    } else {
        header('Location: index.php?new_game=true');
    }
    exit;
}

$message = $_SESSION['game_over_message'] ?? "Гра завершена!";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['play_again'])) {
    if (isset($_SESSION['initial_player_names'])) {
        // ... (відновлення гравців) ...
        $players = [];
        foreach ($_SESSION['initial_player_names'] as $name) {
            $players[] = [
                'name' => $name,
                'skips_left' => 1,
                'active' => true,
                'deferred_effects' => []
            ];
        }
        $_SESSION['players'] = $players;
        $_SESSION['current_player_index'] = 0;
        $_SESSION['current_round'] = 1;
        $_SESSION['game_started'] = true;
        $_SESSION['game_over'] = false;
        unset($_SESSION['game_over_message']);
        $_SESSION['current_question_data'] = null; // Важливо для першого ходу нової гри

        // Перезавантаження та перемішування пулу питань
        $all_questions_raw = json_decode(file_get_contents('data/questions.json'), true);
        if (is_array($all_questions_raw) && !empty($all_questions_raw)) {
            $_SESSION['all_questions_data'] = array_column($all_questions_raw, null, 'id');
            $question_ids = array_keys($_SESSION['all_questions_data']);
            shuffle($question_ids);
            $_SESSION['available_question_ids'] = $question_ids;
        } else {
            // Якщо тут помилка, гра не зможе початися, перенаправляємо на index з помилкою
            $_SESSION['game_started'] = false; // Явно
            header('Location: index.php?error=questions_reload_failed_game_over');
            exit;
        }
        
        header('Location: game.php');
        exit;
    } else {
        header('Location: index.php?new_game=true');
        exit;
    }
}
// ... (код для new_game_entirely залишається) ...
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_game_entirely'])) {
    $_SESSION = [];
    session_destroy(); 
    // Потрібно знову стартувати сесію, щоб index.php міг її використовувати
    // або просто перенаправити, а index.php сам її почне.
    // Але для чистоти краще так:
    session_start(); 
    header('Location: index.php?new_game=true'); 
    exit;
}
?>
<!-- HTML для game_over.php залишається тим самим -->
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Гру завершено</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="gameover-page">
        <h1>Гру завершено!</h1>
        <p><?php echo htmlspecialchars($message); ?></p>
        <?php if (isset($_SESSION['initial_player_names'])): ?>
        <form method="POST" action="game_over.php">
            <button type="submit" name="play_again" class="play-again-btn">Грати знов (ті самі гравці)</button>
        </form>
        <?php endif; ?>
        <form method="POST" action="game_over.php">
             <button type="submit" name="new_game_entirely" class="new-game-btn">Почати нову гру (інші гравці)</button>
        </form>
        <p style="margin-top: 20px; font-size: 0.9em;">Не забудьте зробити 5-хвилинну перерву!</p>
    </div>
</body>
</html>
