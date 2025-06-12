<?php
session_start();

if (!isset($_SESSION['game_over']) || $_SESSION['game_over'] !== true) {
    if (isset($_SESSION['game_started']) && $_SESSION['game_started'] === true) {
        header('Location: game.php');
    } else {
        header('Location: index.php?new_game=true');
    }
    exit;
}

$message = $_SESSION['game_over_message'] ?? "Гра завершена!";
$game_config = $_SESSION['game_config'] ?? null; // For "Play Again"

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['play_again'])) {
    if (isset($_SESSION['initial_player_names']) && $game_config) {
        $players = [];
        foreach ($_SESSION['initial_player_names'] as $name) {
            $players[] = ['name' => $name, 'skips_left' => $game_config['general']['initial_skips'], 'active' => true, 'deferred_effects' => []];
        }
        $_SESSION['players'] = $players;
        $_SESSION['current_player_index'] = 0;
        $_SESSION['current_round'] = 1;
        $_SESSION['game_started'] = true;
        $_SESSION['game_over'] = false;
        unset($_SESSION['game_over_message']);
        $_SESSION['current_question_data'] = null;
        $_SESSION['game_history'] = [];
        
        if (($game_config['general']['reading_timer_duration'] ?? 10) > 0) {
            $_SESSION['timer_phase'] = 'reading';
        } else {
            $_SESSION['timer_phase'] = 'main';
        }
        $_SESSION['timer_started_at'] = time();

        $all_questions_raw = json_decode(file_get_contents('data/questions.json'), true);
        // category_styles_from_json should still be in session from initial setup
        // $_SESSION['category_styles_from_json'] = json_decode(file_get_contents('data/category_styles.json'), true);


        if (is_array($all_questions_raw) && !empty($all_questions_raw) && isset($_SESSION['category_styles_from_json']) && !empty($_SESSION['category_styles_from_json'])) {
            $_SESSION['all_questions_data'] = array_column($all_questions_raw, null, 'id');
            // Use existing game_config for categories for question pool
            
            $questions_for_sorting = [];
            foreach ($all_questions_raw as $question) {
                $category = $question['category'];
                $category_config_item = $game_config['categories'][$category] ?? null;

                if ($category_config_item && $category_config_item['enabled'] && $category_config_item['weight'] > 0) {
                    $weight = $category_config_item['weight'];
                    $random_score = pow(mt_rand() / mt_getrandmax(), 1.0 / $weight);
                    $questions_for_sorting[] = ['id' => $question['id'], 'score' => $random_score];
                }
            }
            
            if (empty($questions_for_sorting)) {
                 $_SESSION['game_started'] = false; // Prevent starting if no questions
                 $_SESSION['game_over'] = true; // Stay on game_over page
                 $_SESSION['game_over_message'] = "Не вдалося перезапустити гру: не знайдено питань для активних категорій.";
                 // No redirect, stay on page to show message
            } else {
                usort($questions_for_sorting, function ($a, $b) { return $b['score'] <=> $a['score']; });
                $_SESSION['game_question_pool'] = array_column($questions_for_sorting, 'id');
                header('Location: game.php'); // Only redirect if successful
                exit;
            }

        } else {
            $_SESSION['game_started'] = false;
            $_SESSION['game_over'] = true;
            $_SESSION['game_over_message'] = "Помилка перезавантаження файлів гри. Спробуйте почати нову гру.";
            // No redirect
        }
        // If we reach here due to an error in "Play Again", we refresh game_over.php to show the new message
        if ($_SESSION['game_over'] === true && isset($_SESSION['game_over_message'])) {
             header('Location: game_over.php');
             exit;
        }


    } else {
        // Fallback if initial names or config missing
        header('Location: index.php?new_game=true&error=session_expired_for_play_again');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_game_entirely'])) {
    // Session will be cleared by index.php due to new_game=true
    header('Location: index.php?new_game=true');
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
        <?php if (isset($_SESSION['initial_player_names']) && isset($_SESSION['game_config'])): ?>
        <form method="POST" action="game_over.php">
            <button type="submit" name="play_again" class="play-again-btn">Грати знов (ті самі гравці та налаштування)</button>
        </form>
        <?php endif; ?>
        <form method="POST" action="game_over.php">
             <button type="submit" name="new_game_entirely" class="new-game-btn">Почати нову гру (нові гравці/налаштування)</button>
        </form>
        <p style="margin-top: 20px; font-size: 0.9em;">Не забудьте зробити 5-хвилинну перерву!</p>
    </div>
</body>
</html>
