<?php
session_start();

// Redirect if game is not actually over
if (!isset($_SESSION['game_over']) || $_SESSION['game_over'] !== true) {
    if (isset($_SESSION['game_started']) && $_SESSION['game_started'] === true) {
        header('Location: game.php'); // Game is still ongoing
    } else {
        header('Location: index.php?new_game=true'); // Not started yet
    }
    exit;
}

$message = $_SESSION['game_over_message'] ?? "Гра завершена!";

// Logic for "Play Again" with same players
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['play_again'])) {
    if (isset($_SESSION['initial_player_names'])) {
        $players = [];
        foreach ($_SESSION['initial_player_names'] as $name) {
            $players[] = [
                'name' => $name,
                'skips_left' => 1, // Reset skips
                'active' => true,  // All players active again
                'deferred_effects' => [] // Clear deferred effects
            ];
        }
        $_SESSION['players'] = $players;
        $_SESSION['current_player_index'] = 0; // Start with first player
        $_SESSION['current_round'] = 1;      // Reset round
        $_SESSION['game_started'] = true;
        $_SESSION['game_over'] = false;
        unset($_SESSION['game_over_message']); // Clear game over message
        $_SESSION['current_question_data'] = null; // Clear current question
        $_SESSION['can_go_back'] = false; // Reset "undo" capability
        $_SESSION['last_displayed_question_data'] = null; // Reset last question
        $_SESSION['last_player_index'] = null; // Reset last player index
        $_SESSION['timer_phase'] = 'reading'; // Reset timer phase
        $_SESSION['timer_started_at'] = time(); // Reset timer start time

        // Reload questions and styles for a fresh game
        $all_questions_raw = json_decode(file_get_contents('data/questions.json'), true);
        $category_styles = json_decode(file_get_contents('data/category_styles.json'), true);

        if (is_array($all_questions_raw) && !empty($all_questions_raw) && is_array($category_styles) && !empty($category_styles)) {
            $_SESSION['all_questions_data'] = array_column($all_questions_raw, null, 'id');
            $_SESSION['category_styles'] = $category_styles;

            // UPDATED WEIGHTED SHUFFLE LOGIC (same as index.php)
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
            // Fallback if question files cannot be reloaded
            $_SESSION['game_started'] = false;
            header('Location: index.php?error=questions_reload_failed_game_over');
            exit;
        }

        header('Location: game.php');
        exit;
    } else {
        // If initial player names are not set, go back to setup page
        header('Location: index.php?new_game=true');
        exit;
    }
}

// Logic for "Start New Game (different players)"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_game_entirely'])) {
    $_SESSION = []; // Clear entire session
    session_destroy(); // Destroy session
    session_start(); // Start new session
    header('Location: index.php?new_game=true'); // Redirect to setup page
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
        <?php if (isset($_SESSION['initial_player_names'])): // Only show "Play again" if initial players are known ?>
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
