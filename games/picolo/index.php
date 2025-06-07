<?php
session_start();

if ((isset($_GET['new_game']) && $_GET['new_game'] === 'true') || !isset($_SESSION['game_started'])) {
    $_SESSION = [];
    // session_destroy(); // If used, session_start() must follow immediately.
    // session_start();
} elseif (isset($_SESSION['game_started']) && $_SESSION['game_started'] === true && $_SESSION['game_over'] === false) {
    header('Location: game.php');
    exit;
} elseif (isset($_SESSION['game_started']) && $_SESSION['game_over'] === true) {
    header('Location: game_over.php');
    exit;
}


$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $players_input = isset($_POST['players']) ? (array)$_POST['players'] : [];
    $players = [];
    foreach ($players_input as $name) {
        $trimmed_name = trim($name);
        if (!empty($trimmed_name)) {
            $players[] = htmlspecialchars($trimmed_name);
        }
    }

    if (count($players) < 2) {
        $error = 'Будь ласка, введіть імена щонайменше двох гравців.';
    } else {
        $_SESSION['initial_player_names'] = $players;

        $game_players = [];
        foreach ($players as $name) {
            $game_players[] = [
                'name' => $name,
                'skips_left' => 1,
                'active' => true,
                'deferred_effects' => []
            ];
        }
        $_SESSION['players'] = $game_players;
        $_SESSION['current_player_index'] = 0;
        $_SESSION['current_round'] = 1;
        $_SESSION['game_started'] = true;
        $_SESSION['game_over'] = false;
        $_SESSION['current_question_data'] = null;

        // Load all questions and category styles
        $all_questions_raw = json_decode(file_get_contents('data/questions.json'), true);
        $category_styles = json_decode(file_get_contents('data/category_styles.json'), true);

        if (is_array($all_questions_raw) && !empty($all_questions_raw) && is_array($category_styles) && !empty($category_styles)) {
            $_SESSION['all_questions_data'] = array_column($all_questions_raw, null, 'id'); // Master map of ID => QuestionData
            $_SESSION['category_styles'] = $category_styles; // Store category styles in session

            // Prepare questions grouped by category and their shuffled IDs
            $questions_by_category = [];
            foreach ($all_questions_raw as $question) {
                $category = $question['category'];
                if (!isset($questions_by_category[$category])) {
                    $questions_by_category[$category] = [];
                }
                $questions_by_category[$category][] = $question['id'];
            }
            $_SESSION['questions_by_category'] = $questions_by_category;

            // Initialize available question IDs for each category (shuffled)
            $_SESSION['available_question_ids_by_category'] = [];
            foreach ($questions_by_category as $category => $ids) {
                shuffle($ids);
                $_SESSION['available_question_ids_by_category'][$category] = $ids;
            }

        } else {
            $_SESSION['game_started'] = false;
            $error = "Помилка завантаження файлів гри (питання/стилі) або файли порожні. Перевірте data/questions.json та data/category_styles.json.";
        }

        if ($_SESSION['game_started'] && empty($error)) {
            header('Location: game.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Налаштування гри</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="setup-page">
        <h1>Налаштування гри</h1>
        <?php if ($error): ?>
            <p style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST" action="index.php">
            <p>Введіть імена гравців (мінімум 2):</p>
            <div id="player-inputs">
                <div class="player-input-group">
                    <input type="text" name="players[]" placeholder="Ім'я гравця 1" required>
                </div>
                <div class="player-input-group">
                    <input type="text" name="players[]" placeholder="Ім'я гравця 2" required>
                </div>
            </div>
            <button type="button" id="add-player" class="add-player-btn">Додати гравця</button>
            <button type="submit" class="start-game-btn">Почати гру!</button>
        </form>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
