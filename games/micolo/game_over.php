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
$game_config_for_play_again = $_SESSION['game_config_at_end'] ?? null; 
$initial_players_for_play_again = $_SESSION['initial_player_names_at_end'] ?? null;

$played_questions_log_path = 'data/played_questions_log.json';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['play_again'])) {
    if ($initial_players_for_play_again && $game_config_for_play_again) {
        $_SESSION['game_config'] = $game_config_for_play_again; 
        $_SESSION['initial_player_names'] = $initial_players_for_play_again;

        $players = [];
        foreach ($initial_players_for_play_again as $name) {
            $players[] = ['name' => $name, 'skips_left' => $game_config_for_play_again['general']['initial_skips'], 'active' => true, 'deferred_effects' => []];
        }
        $_SESSION['players'] = $players;
        $_SESSION['current_player_index'] = 0;
        $_SESSION['current_round'] = 1;
        $_SESSION['game_started'] = true;
        $_SESSION['game_over'] = false;
        unset($_SESSION['game_over_message']);
        unset($_SESSION['game_config_at_end']);
        unset($_SESSION['initial_player_names_at_end']);

        $all_questions_raw = json_decode(file_get_contents('data/questions.json'), true);
        $_SESSION['all_questions_data_map'] = is_array($all_questions_raw) ? array_column($all_questions_raw, null, 'id') : [];
        $_SESSION['category_styles_from_json'] = json_decode(file_get_contents('data/category_styles.json'), true) ?: [];
        
        $played_questions_counts = json_decode(@file_get_contents($played_questions_log_path), true) ?: [];

        if (!empty($_SESSION['all_questions_data_map']) && !empty($_SESSION['category_styles_from_json'])) {
            $questions_for_sorting = [];
            $historical_k_factor = 0.3;

            foreach ($_SESSION['all_questions_data_map'] as $q_id => $question) {
                $category = $question['category'];
                $category_config_item = $game_config_for_play_again['categories'][$category] ?? null;

                if ($category_config_item && $category_config_item['enabled'] && $category_config_item['weight'] > 0) {
                    $category_weight = (float)$category_config_item['weight'];
                    $play_count = (int)($played_questions_counts[$q_id] ?? 0);
                    
                    $historical_weight_modifier = 1.0 / ( ($play_count * $historical_k_factor) + 1.0);
                    $final_weight_for_random_score = $category_weight * $historical_weight_modifier;
                    if ($final_weight_for_random_score <= 0) $final_weight_for_random_score = 0.001;

                    $random_score = pow(mt_rand() / mt_getrandmax(), 1.0 / $final_weight_for_random_score);
                    $questions_for_sorting[] = ['id' => $q_id, 'score' => $random_score];
                }
            }
            
            if (empty($questions_for_sorting)) {
                 $_SESSION['game_started'] = false;
                 $_SESSION['game_over'] = true; 
                 $_SESSION['game_over_message'] = "Не вдалося перезапустити гру: не знайдено питань для активних категорій.";
            } else {
                usort($questions_for_sorting, function ($a, $b) { return $b['score'] <=> $a['score']; });
                $_SESSION['initial_js_question_pool'] = [];
                foreach ($questions_for_sorting as $item) {
                    $_SESSION['initial_js_question_pool'][] = $_SESSION['all_questions_data_map'][$item['id']];
                }

                $first_question_for_js = $_SESSION['initial_js_question_pool'][0] ?? null;
                 if ($first_question_for_js) {
                    $q_has_main_timer = (($first_question_for_js['timer'] ?? 0) > 0);
                    $reading_timer_setting = $game_config_for_play_again['general']['reading_timer_duration'] ?? 10;
                    if ($q_has_main_timer && $reading_timer_setting > 0) {
                        $_SESSION['initial_timer_phase'] = 'reading';
                    } else {
                        $_SESSION['initial_timer_phase'] = 'main';
                    }
                    $_SESSION['initial_timer_started_at'] = time();
                } else {
                    $_SESSION['initial_timer_phase'] = 'main';
                    $_SESSION['initial_timer_started_at'] = time();
                }

                header('Location: game.php');
                exit;
            }
        } else {
            $_SESSION['game_started'] = false;
            $_SESSION['game_over'] = true;
            $_SESSION['game_over_message'] = "Помилка перезавантаження файлів гри. Спробуйте почати нову гру.";
        }
        
        if ($_SESSION['game_over'] === true && isset($_SESSION['game_over_message'])) {
             header('Location: game_over.php');
             exit;
        }

    } else {
        header('Location: index.php?new_game=true&error=session_expired_for_play_again');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_game_entirely'])) {
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
        <?php if ($initial_players_for_play_again && $game_config_for_play_again): ?>
        <form method="POST" action="game_over.php">
            <button type="submit" name="play_again" class="play-again-btn">Грати знов (ті самі гравці та налаштування)</button>
        </form>
        <?php endif; ?>
        <form method="POST" action="game_over.php">
             <button type="submit" name="new_game_entirely" class="new-game-btn">Почати нову гру (нові гравці/налаштування)</button>
        </form>
        <p style="margin-top: 20px; font-size: 1.9em;">Не забудьте зробити 5-хвилинну перерву!</p>
    </div>
</body>
</html>
