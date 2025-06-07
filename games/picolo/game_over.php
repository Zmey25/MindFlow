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
        $_SESSION['current_question_data'] = null;

        $all_questions_raw = json_decode(file_get_contents('data/questions.json'), true);
        $category_styles = json_decode(file_get_contents('data/category_styles.json'), true);

        if (is_array($all_questions_raw) && !empty($all_questions_raw) && is_array($category_styles) && !empty($category_styles)) {
            $_SESSION['all_questions_data'] = array_column($all_questions_raw, null, 'id');
            $_SESSION['category_styles'] = $category_styles;

            // ОНОВЛЕНА ЛОГІКА ЗВАЖЕНОГО ПЕРЕМІШУВАННЯ
            $questions_for_sorting = [];
            foreach ($all_questions_raw as $question) {
                $category = $question['category'];
                $weight = $category_styles[$category]['weight'] ?? 1;
                if ($weight > 0) {
                    $random_score = pow(mt_rand() / mt_getrandmax(), 1.0 / $weight);
                    $questions_for_sorting[] = [
                        'id' => $question['id'],
                        'score' => $random_score
                    ];
                }
            }
            
            usort($questions_for_sorting, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            $_SESSION['game_question_pool'] = array_column($questions_for_sorting, 'id');

        } else {
            $_SESSION['game_started'] = false;
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_game_entirely'])) {
    $_SESSION = [];
    session_destroy();
    session_start();
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
