<?php
session_start();

$default_game_config = [
    'general' => [
        'reading_timer_duration' => 10,
        'max_rounds' => 5,
        'initial_skips' => 1,
    ],
    'categories' => []
];

$raw_category_styles_data = json_decode(file_get_contents('data/category_styles.json'), true) ?: [];
foreach ($raw_category_styles_data as $cat_name => $style) {
    $default_game_config['categories'][$cat_name] = [
        'enabled' => true,
        'weight' => $style['weight'] ?? 10
    ];
}

if ((isset($_GET['new_game']) && $_GET['new_game'] === 'true') || !isset($_SESSION['game_started'])) {
    $_SESSION = [];
    $_SESSION['game_config'] = $default_game_config;
    // Store base styles for index page display and game page visuals
    $_SESSION['category_styles_from_json'] = $raw_category_styles_data;
} elseif (isset($_SESSION['game_started']) && $_SESSION['game_started'] === true && (!isset($_SESSION['game_over']) || $_SESSION['game_over'] === false)) {
    header('Location: game.php');
    exit;
} elseif (isset($_SESSION['game_started']) && $_SESSION['game_over'] === true) {
    header('Location: game_over.php');
    exit;
}

// Ensure game_config and category_styles_from_json are always set for the page
$_SESSION['game_config'] = $_SESSION['game_config'] ?? $default_game_config;
$_SESSION['category_styles_from_json'] = $_SESSION['category_styles_from_json'] ?? $raw_category_styles_data;

// If categories in config don't match json (e.g. json updated), try to merge safely
foreach ($raw_category_styles_data as $cat_name => $style) {
    if (!isset($_SESSION['game_config']['categories'][$cat_name])) {
        $_SESSION['game_config']['categories'][$cat_name] = [
            'enabled' => true,
            'weight' => $style['weight'] ?? 10
        ];
    }
}
// Remove categories from config if they are no longer in json
foreach (array_keys($_SESSION['game_config']['categories']) as $conf_cat_name) {
    if (!isset($raw_category_styles_data[$conf_cat_name])) {
        unset($_SESSION['game_config']['categories'][$conf_cat_name]);
    }
}


$current_game_config = $_SESSION['game_config'];
$display_category_styles = $_SESSION['category_styles_from_json'];

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $players_input = isset($_POST['players']) ? (array)$_POST['players'] : [];
    $player_names = [];
    foreach ($players_input as $name) {
        $trimmed_name = trim($name);
        if (!empty($trimmed_name)) {
            $player_names[] = htmlspecialchars($trimmed_name);
        }
    }

    if (count($player_names) < 2) {
        $error = 'Будь ласка, введіть імена щонайменше двох гравців.';
    } else {
        $_SESSION['initial_player_names'] = $player_names; // Used for "Play Again"

        // Process advanced settings
        $current_game_config['general']['reading_timer_duration'] = max(0, (int)($_POST['reading_timer_duration'] ?? $default_game_config['general']['reading_timer_duration']));
        $current_game_config['general']['max_rounds'] = max(1, (int)($_POST['max_rounds'] ?? $default_game_config['general']['max_rounds']));
        $current_game_config['general']['initial_skips'] = max(0, (int)($_POST['initial_skips'] ?? $default_game_config['general']['initial_skips']));

        $posted_categories_enabled = $_POST['categories_enabled'] ?? [];
        $posted_category_weights = $_POST['category_weights'] ?? [];

        foreach (array_keys($current_game_config['categories']) as $cat_name) {
            $current_game_config['categories'][$cat_name]['enabled'] = isset($posted_categories_enabled[$cat_name]);
            if (isset($posted_category_weights[$cat_name])) {
                $current_game_config['categories'][$cat_name]['weight'] = max(0, (int)$posted_category_weights[$cat_name]);
            }
        }
        $_SESSION['game_config'] = $current_game_config;


        $game_players = [];
        foreach ($player_names as $name) {
            $game_players[] = [
                'name' => $name,
                'skips_left' => $current_game_config['general']['initial_skips'],
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
        $_SESSION['game_history'] = [];
        $_SESSION['timer_phase'] = 'reading'; // Will be 'main' if reading_timer_duration is 0
        $_SESSION['timer_started_at'] = time();


        $all_questions_raw = json_decode(file_get_contents('data/questions.json'), true);
        // $_SESSION['category_styles_from_json'] is already loaded and up-to-date

        if (is_array($all_questions_raw) && !empty($all_questions_raw) && !empty($_SESSION['category_styles_from_json'])) {
            $_SESSION['all_questions_data'] = array_column($all_questions_raw, null, 'id');
            
            $questions_for_sorting = [];
            foreach ($all_questions_raw as $question) {
                $category = $question['category'];
                
                // Use configured enabled status and weight
                $category_config = $current_game_config['categories'][$category] ?? null;

                if ($category_config && $category_config['enabled'] && $category_config['weight'] > 0) {
                    $weight = $category_config['weight'];
                    $random_score = pow(mt_rand() / mt_getrandmax(), 1.0 / $weight);
                    $questions_for_sorting[] = [
                        'id' => $question['id'],
                        'score' => $random_score
                    ];
                }
            }
            
            if (empty($questions_for_sorting)) {
                 $_SESSION['game_started'] = false;
                 $error = "Не знайдено жодного питання для обраних категорій. Увімкніть більше категорій або перевірте їх вагу.";
            } else {
                usort($questions_for_sorting, function ($a, $b) {
                    return $b['score'] <=> $a['score'];
                });
                $_SESSION['game_question_pool'] = array_column($questions_for_sorting, 'id');
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
        <h1>Налаштування гри</h1>
        <?php if ($error): ?>
            <p style="color: red; padding: 10px; background-color: rgba(255,0,0,0.1); border-radius: 5px;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST" action="index.php">
            <p>Введіть імена гравців (мінімум 2):</p>
            <div id="player-inputs">
                <div class="player-input-group">
                    <input type="text" name="players[]" placeholder="Ім'я гравця 1" required value="<?php echo htmlspecialchars($_SESSION['initial_player_names'][0] ?? ''); ?>">
                </div>
                <div class="player-input-group">
                    <input type="text" name="players[]" placeholder="Ім'я гравця 2" required value="<?php echo htmlspecialchars($_SESSION['initial_player_names'][1] ?? ''); ?>">
                </div>
                <?php if (isset($_SESSION['initial_player_names']) && count($_SESSION['initial_player_names']) > 2): ?>
                    <?php for ($i = 2; $i < count($_SESSION['initial_player_names']); $i++): ?>
                    <div class="player-input-group">
                        <input type="text" name="players[]" placeholder="Ім'я гравця <?php echo $i + 1; ?>" required value="<?php echo htmlspecialchars($_SESSION['initial_player_names'][$i]); ?>">
                        <button type="button" class="remove-player-btn">X</button>
                    </div>
                    <?php endfor; ?>
                <?php endif; ?>
            </div>
            <button type="button" id="add-player" class="add-player-btn">Додати гравця</button>

            <button type="button" id="advanced-settings-toggle-btn" class="advanced-settings-toggle-btn">Показати розширені налаштування</button>
            
            <div id="advanced-settings-container" style="display: none;">
                <h3>Розширені налаштування</h3>

                <div class="settings-group">
                    <h4>Загальні налаштування</h4>
                    <label for="reading_timer_duration">Тривалість таймера для читання (сек, 0 - вимкнено):</label>
                    <input type="number" id="reading_timer_duration" name="reading_timer_duration" min="0" value="<?php echo htmlspecialchars($current_game_config['general']['reading_timer_duration']); ?>">
                    
                    <label for="max_rounds">Кількість кіл для гри:</label>
                    <input type="number" id="max_rounds" name="max_rounds" min="1" value="<?php echo htmlspecialchars($current_game_config['general']['max_rounds']); ?>">

                    <label for="initial_skips">Початкова кількість пропусків на гравця:</label>
                    <input type="number" id="initial_skips" name="initial_skips" min="0" value="<?php echo htmlspecialchars($current_game_config['general']['initial_skips']); ?>">
                </div>

                <div class="settings-group">
                    <h4>Налаштування категорій</h4>
                    <div class="preset-buttons">
                        <button type="button" class="preset-btn" data-preset="party">Пресет: Вечірка</button>
                        <button type="button" class="preset-btn" data-preset="deepTalk">Пресет: Розкрийся!</button>
                        <button type="button" class="preset-btn" data-preset="creative">Пресет: Креатив</button>
                        <button type="button" class="preset-btn" data-preset="gameNight">Пресет: Ігровий Вибух</button>
                        <button type="button" class="preset-btn" data-preset="adultsOnly">Пресет: Для Дорослих 18+</button>
                        <button type="button" class="preset-btn" data-preset="default">Скинути до замовчувань</button>
                    </div>
                    <div id="category-settings-list">
                    <?php if (!empty($display_category_styles)): ?>
                        <?php foreach ($display_category_styles as $cat_name => $style): ?>
                            <?php
                                $cat_config = $current_game_config['categories'][$cat_name] ?? ['enabled' => true, 'weight' => $style['weight'] ?? 10];
                                $is_enabled = $cat_config['enabled'];
                                $current_weight = $cat_config['weight'];
                                $default_weight = $style['weight'] ?? 10;
                            ?>
                            <div class="category-setting" data-category-name="<?php echo htmlspecialchars($cat_name); ?>">
                                <span class="category-name" title="<?php echo htmlspecialchars($style['description'] ?? $cat_name); ?>"><?php echo htmlspecialchars($cat_name); ?></span>
                                <span class="category-enable">
                                    <input type="checkbox" name="categories_enabled[<?php echo htmlspecialchars($cat_name); ?>]" <?php echo $is_enabled ? 'checked' : ''; ?>>
                                </span>
                                <span class="category-weight">
                                    <input type="number" name="category_weights[<?php echo htmlspecialchars($cat_name); ?>]" min="0" value="<?php echo htmlspecialchars($current_weight); ?>" data-default-weight="<?php echo htmlspecialchars($default_weight); ?>">
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Не вдалося завантажити категорії. Перевірте файл data/category_styles.json.</p>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="start-game-btn">Почати гру!</button>
        </form>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
