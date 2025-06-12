<?php
session_start();

if ((isset($_GET['new_game']) && $_GET['new_game'] === 'true') || !isset($_SESSION['game_started'])) {
    $_SESSION = [];
} elseif (isset($_SESSION['game_started']) && $_SESSION['game_started'] === true && (!isset($_SESSION['game_over']) || $_SESSION['game_over'] === false)) {
    header('Location: game.php');
    exit;
} elseif (isset($_SESSION['game_started']) && $_SESSION['game_over'] === true) {
    header('Location: game_over.php');
    exit;
}

$category_styles = json_decode(file_get_contents('data/category_styles.json'), true) ?: [];

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $players_input = isset($_POST['players']) ? (array)$_POST['players'] : [];
    $players = [];
    foreach ($players_input as $name) {
        if (!empty(trim($name))) $players[] = htmlspecialchars(trim($name));
    }

    if (count($players) < 2) {
        $error = 'Будь ласка, введіть імена щонайменше двох гравців.';
    } else {
        $_SESSION['initial_player_names'] = $players;
        
        $game_settings = $_POST['settings'] ?? [];
        $_SESSION['game_settings'] = [
            'max_rounds' => (int)($game_settings['max_rounds'] ?? 5),
            'reading_timer' => (int)($game_settings['reading_timer'] ?? 10),
            'categories' => $game_settings['categories'] ?? []
        ];

        $game_players = [];
        foreach ($players as $name) {
            $game_players[] = ['name' => $name, 'skips_left' => 1, 'active' => true, 'deferred_effects' => []];
        }
        $_SESSION['players'] = $game_players;
        $_SESSION['current_player_index'] = 0;
        $_SESSION['current_round'] = 1;
        $_SESSION['game_started'] = true;
        $_SESSION['game_over'] = false;
        $_SESSION['current_question_data'] = null;
        $_SESSION['game_history'] = [];
        $_SESSION['timer_phase'] = 'reading';
        $_SESSION['timer_started_at'] = time();

        $all_questions_raw = json_decode(file_get_contents('data/questions.json'), true);
        
        if (is_array($all_questions_raw) && !empty($all_questions_raw) && is_array($category_styles) && !empty($category_styles)) {
            $_SESSION['all_questions_data'] = array_column($all_questions_raw, null, 'id');
            $_SESSION['category_styles'] = $category_styles;

            $questions_for_sorting = [];
            $enabled_categories = $_SESSION['game_settings']['categories'] ?? [];

            foreach ($all_questions_raw as $question) {
                $category_name = $question['category'];
                if (isset($enabled_categories[$category_name]) && $enabled_categories[$category_name]['enabled'] == '1') {
                    $weight = (int)($enabled_categories[$category_name]['weight'] ?? 1);
                    if ($weight > 0) {
                        $random_score = pow(mt_rand() / mt_getrandmax(), 1.0 / $weight);
                        $questions_for_sorting[] = ['id' => $question['id'], 'score' => $random_score];
                    }
                }
            }
            
            usort($questions_for_sorting, fn($a, $b) => $b['score'] <=> $a['score']);
            $_SESSION['game_question_pool'] = array_column($questions_for_sorting, 'id');

            if (empty($_SESSION['game_question_pool'])) {
                $error = "Немає доступних питань. Будь ласка, увімкніть хоча б одну категорію з вагою більше 0.";
                $_SESSION['game_started'] = false;
            }

        } else {
            $_SESSION['game_started'] = false;
            $error = "Помилка завантаження файлів гри (питання/стилі) або файли порожні.";
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
        <form method="POST" action="index.php" class="setup-form">
            <h1>Налаштування гри</h1>
            <?php if ($error): ?><p class="error-message"><?php echo $error; ?></p><?php endif; ?>
            
            <p>Введіть імена гравців (мінімум 2):</p>
            <div id="player-inputs">
                <div class="player-input-group"><input type="text" name="players[]" placeholder="Ім'я гравця 1" required></div>
                <div class="player-input-group"><input type="text" name="players[]" placeholder="Ім'я гравця 2" required></div>
            </div>
            <button type="button" id="add-player" class="add-player-btn">Додати гравця</button>

            <details id="advanced-settings">
                <summary>Розширені налаштування</summary>
                <div class="settings-container">
                    
                    <div class="general-settings">
                        <div class="setting-item">
                            <label for="max_rounds">Кількість кіл для гри:</label>
                            <input type="number" id="max_rounds" name="settings[max_rounds]" value="5" min="1" max="20">
                        </div>
                         <div class="setting-item">
                            <label for="reading_timer">Час на читання (сек):</label>
                            <input type="number" id="reading_timer" name="settings[reading_timer]" value="10" min="3" max="60">
                        </div>
                    </div>

                    <h3>Категорії та їх вага</h3>
                    <div class="preset-btn-group">
                        <button type="button" class="preset-btn" data-preset="party">Пресет: Для вечірки</button>
                        <button type="button" class="preset-btn" data-preset="creative">Пресет: Для креативу</button>
                    </div>

                    <div id="category-settings">
                        <?php foreach ($category_styles as $name => $details): ?>
                        <div class="category-setting" data-category-name="<?php echo htmlspecialchars($name); ?>">
                            <div class="category-title">
                                <input type="checkbox" name="settings[categories][<?php echo htmlspecialchars($name); ?>][enabled]" value="1" id="cat_<?php echo md5($name); ?>" checked>
                                <label for="cat_<?php echo md5($name); ?>"><?php echo htmlspecialchars($name); ?></label>
                            </div>
                            <div class="category-weight">
                                <label for="weight_<?php echo md5($name); ?>">Вага:</label>
                                <input type="number" name="settings[categories][<?php echo htmlspecialchars($name); ?>][weight]" value="<?php echo (int)($details['weight'] ?? 10); ?>" min="0" max="100" id="weight_<?php echo md5($name); ?>">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>

            <button type="submit" class="start-game-btn">Почати гру!</button>
        </form>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
