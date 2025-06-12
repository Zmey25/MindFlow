<?php
session_start();

// --- TASK 2 & 3: Load styles and usage stats for the settings form ---
$category_styles = json_decode(file_get_contents('data/category_styles.json'), true) ?? [];
$question_usage_counts = json_decode(@file_get_contents('data/question_usage.json'), true) ?? [];


// --- Handle session state ---
if (isset($_POST['new_game_entirely']) || (isset($_GET['new_game']) && $_GET['new_game'] === 'true') || !isset($_SESSION['game_started'])) {
    
    // --- TASK 2: If starting a new game after a previous one, update usage stats ---
    if (isset($_SESSION['questions_used_this_game']) && !empty($_SESSION['questions_used_this_game'])) {
        $counts_from_last_game = array_count_values($_SESSION['questions_used_this_game']);
        foreach ($counts_from_last_game as $id => $count) {
            $question_usage_counts[$id] = ($question_usage_counts[$id] ?? 0) + $count;
        }
        file_put_contents('data/question_usage.json', json_encode($question_usage_counts, JSON_PRETTY_PRINT));
    }

    $_SESSION = [];
    // Re-read usage counts for the new game
    $question_usage_counts = json_decode(@file_get_contents('data/question_usage.json'), true) ?? [];

} elseif (isset($_SESSION['game_started']) && $_SESSION['game_started'] === true && (!isset($_SESSION['game_over']) || $_SESSION['game_over'] === false)) {
    header('Location: game.php');
    exit;
} elseif (isset($_SESSION['game_started']) && $_SESSION['game_over'] === true) {
    header('Location: game_over.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_game'])) {
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
        // --- Initialize basic session data ---
        $_SESSION['initial_player_names'] = $players;
        $_SESSION['game_history'] = [];
        $_SESSION['questions_used_this_game'] = []; // TASK 2: Track used questions
        $_SESSION['current_round'] = 1;
        $_SESSION['current_player_index'] = 0;
        
        $game_players = [];
        foreach ($players as $name) {
            $game_players[] = ['name' => $name, 'skips_left' => 1, 'active' => true, 'deferred_effects' => []];
        }
        $_SESSION['players'] = $game_players;

        // --- TASK 3: Process advanced settings ---
        $game_settings = $_POST['game_settings'] ?? [];
        $_SESSION['game_settings'] = [
            'reading_timer' => (int)($game_settings['reading_timer'] ?? 10),
            'max_rounds' => (int)($game_settings['max_rounds'] ?? 5),
            'enabled_categories' => $game_settings['enabled_categories'] ?? array_keys($category_styles),
            'category_weights' => $game_settings['category_weights'] ?? array_column($category_styles, 'weight', 'name') // 'name' is not a key, but we'll use category name
        ];
        
        $all_questions_raw = json_decode(file_get_contents('data/questions.json'), true);

        if (is_array($all_questions_raw) && !empty($all_questions_raw) && is_array($category_styles) && !empty($category_styles)) {
            $_SESSION['all_questions_data'] = array_column($all_questions_raw, null, 'id');
            $_SESSION['category_styles'] = $category_styles;

            // --- TASK 2 & 3: Generate question pool based on advanced settings and usage history ---
            $questions_for_sorting = [];
            $enabled_categories = $_SESSION['game_settings']['enabled_categories'];
            $custom_weights = $_SESSION['game_settings']['category_weights'];

            foreach ($all_questions_raw as $question) {
                $category = $question['category'];
                if (!in_array($category, $enabled_categories)) {
                    continue; // Skip disabled categories
                }
                
                $weight = (float)($custom_weights[$category] ?? ($category_styles[$category]['weight'] ?? 1));
                if ($weight <= 0) continue;

                $usage_count = $question_usage_counts[$question['id']] ?? 0;
                // Weighted random score, penalized by usage count. (+1 to avoid division by zero)
                $random_score = (pow(mt_rand() / mt_getrandmax(), 1.0 / $weight)) / ($usage_count + 1);
                
                $questions_for_sorting[] = ['id' => $question['id'], 'score' => $random_score];
            }
            
            usort($questions_for_sorting, function ($a, $b) { return $b['score'] <=> $a['score']; });
            $_SESSION['game_question_pool'] = array_column($questions_for_sorting, 'id');
            
            $_SESSION['game_started'] = true;
            $_SESSION['game_over'] = false;
            $_SESSION['current_question_data'] = null;
            $_SESSION['timer_phase'] = 'reading';
            $_SESSION['timer_started_at'] = time();

            header('Location: game.php');
            exit;

        } else {
            $error = "Помилка завантаження файлів гри (питання/стилі) або файли порожні.";
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
                <div class="player-input-group"><input type="text" name="players[]" placeholder="Ім'я гравця 1" required></div>
                <div class="player-input-group"><input type="text" name="players[]" placeholder="Ім'я гравця 2" required></div>
            </div>
            <button type="button" id="add-player" class="add-player-btn">Додати гравця</button>

            <!-- TASK 3: Advanced Settings Block -->
            <div class="advanced-settings-container">
                <button type="button" id="advanced-settings-toggle">Розширені налаштування ▾</button>
                <div id="advanced-settings-content" class="hidden">
                    
                    <div class="presets-container">
                        <strong>Пресети:</strong>
                        <button type="button" class="preset-btn" data-preset="party">Для вечірки</button>
                        <button type="button" class="preset-btn" data-preset="creative">Для креативу</button>
                    </div>

                    <div class="settings-group">
                        <label for="reading-timer">Час на читання (сек):</label>
                        <input type="number" id="reading-timer" name="game_settings[reading_timer]" value="10" min="3" max="60">
                    </div>
                    <div class="settings-group">
                        <label for="max-rounds">Кількість кіл до кінця гри:</label>
                        <input type="number" id="max-rounds" name="game_settings[max_rounds]" value="5" min="1" max="50">
                    </div>
                    
                    <hr>
                    <strong>Категорії та їх вага (шанс випадіння):</strong>
                    <div id="category-settings">
                        <?php foreach ($category_styles as $name => $details): ?>
                        <div class="category-setting-item" data-category-name="<?php echo htmlspecialchars($name); ?>">
                            <input type="checkbox" name="game_settings[enabled_categories][]" value="<?php echo htmlspecialchars($name); ?>" id="cat_<?php echo md5($name); ?>" checked>
                            <label for="cat_<?php echo md5($name); ?>"><?php echo htmlspecialchars($name); ?></label>
                            <input type="number" name="game_settings[category_weights][<?php echo htmlspecialchars($name); ?>]" value="<?php echo $details['weight'] ?? 1; ?>" min="0" max="1000">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <button type="submit" name="start_game" class="start-game-btn">Почати гру!</button>
        </form>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
