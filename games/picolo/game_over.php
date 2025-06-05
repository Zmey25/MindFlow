<?php
session_start();

if (!isset($_SESSION['game_over']) || $_SESSION['game_over'] !== true) {
    // Якщо гра не закінчена, а хтось зайшов сюди, повертаємо на гру або на початок
    if (isset($_SESSION['game_started']) && $_SESSION['game_started'] === true) {
        header('Location: game.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$message = $_SESSION['game_over_message'] ?? "Гра завершена!";

// Обробка кнопки "Грати знов"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['play_again'])) {
    if (isset($_SESSION['initial_player_names'])) {
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
        unset($_SESSION['current_question_data']); // Скидаємо поточне питання

        // Перезавантаження пулу питань
        $all_questions = json_decode(file_get_contents('data/questions.json'), true);
        if (is_array($all_questions)) {
            $question_ids = array_map(function($q) { return $q['id']; }, $all_questions);
            shuffle($question_ids);
            $_SESSION['available_question_ids'] = $question_ids;
            // $_SESSION['all_questions_data'] вже має бути завантажений з index.php або game.php
        } else {
            // Обробка помилки, якщо питання не завантажились
            $_SESSION['game_started'] = false;
            // Повернення на index.php, щоб уникнути циклу, якщо тут помилка
            header('Location: index.php?error=questions_reload_failed');
            exit;
        }
        
        header('Location: game.php');
        exit;
    } else {
        // Якщо немає initial_player_names, значить щось пішло не так, краще на index
        header('Location: index.php?new_game=true');
        exit;
    }
}

// Обробка кнопки "Почати нову гру" (веде на index.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_game_entirely'])) {
    session_unset(); // Очистити всі дані сесії
    session_destroy(); // Знищити сесію
    header('Location: index.php?new_game=true'); // new_game=true для чистого старту
    exit;
}

?>
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
