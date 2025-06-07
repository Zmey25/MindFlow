<?php
session_start();

// --- Перевірки стану гри на початку ---
if (!isset($_SESSION['game_started']) || $_SESSION['game_started'] !== true) {
    header('Location: index.php?new_game=true');
    exit;
}
if (isset($_SESSION['game_over']) && $_SESSION['game_over'] === true) {
    header('Location: game_over.php');
    exit;
}

// Load data from session (should be populated by index.php or game_over.php)
$questions_data_map = $_SESSION['all_questions_data'] ?? []; // ID => QuestionData
$category_styles = $_SESSION['category_styles'] ?? []; // CategoryName => StyleData
$questions_by_category = $_SESSION['questions_by_category'] ?? []; // CategoryName => [ID, ID, ...]
$available_question_ids_by_category = &$_SESSION['available_question_ids_by_category']; // & for direct modification

if (empty($questions_data_map) || empty($category_styles) || empty($questions_by_category) || empty($available_question_ids_by_category)) {
    $_SESSION['game_over'] = true;
    $_SESSION['game_over_message'] = "Помилка: Файли гри (питання/стилі/категорії) не завантажено належним чином.";
    header('Location: game_over.php');
    exit;
}

// --- Допоміжні функції ---
function get_active_players_indices() {
    $active_indices = [];
    if (isset($_SESSION['players']) && is_array($_SESSION['players'])) {
        foreach ($_SESSION['players'] as $index => $player) {
            if (isset($player['active']) && $player['active']) {
                $active_indices[] = $index;
            }
        }
    }
    return $active_indices;
}

function get_next_active_player_index($current_index_in_session) {
    $all_players = $_SESSION['players'] ?? [];
    if (empty($all_players)) return null;

    $num_players = count($all_players);
    $next_idx = ($current_index_in_session + 1) % $num_players;
    $checked_count = 0;

    while ($checked_count < $num_players) {
        if (isset($all_players[$next_idx]['active']) && $all_players[$next_idx]['active']) {
            return $next_idx;
        }
        $next_idx = ($next_idx + 1) % $num_players;
        $checked_count++;
    }
    return null;
}

function select_question() {
    global $questions_data_map, $category_styles, $questions_by_category, $available_question_ids_by_category;

    // Step 1: Select a category based on weights
    $weighted_categories = [];
    $total_weight = 0;
    foreach ($category_styles as $category_name => $data) {
        if (isset($data['weight']) && $data['weight'] > 0 && isset($questions_by_category[$category_name]) && !empty($questions_by_category[$category_name])) {
            $weighted_categories[$category_name] = (int)$data['weight'];
            $total_weight += (int)$data['weight'];
        }
    }

    if ($total_weight === 0) {
        error_log("select_question: No active categories with weights or no questions in them.");
        return null;
    }

    $rand = mt_rand(1, $total_weight);
    $selected_category = null;
    foreach ($weighted_categories as $category => $weight) {
        $rand -= $weight;
        if ($rand <= 0) {
            $selected_category = $category;
            break;
        }
    }

    if ($selected_category === null) {
        error_log("select_question: Failed to select a category. This should not happen if total_weight > 0.");
        return null; // Fallback
    }

    // Step 2: Select a question from the chosen category
    if (!isset($available_question_ids_by_category[$selected_category]) || empty($available_question_ids_by_category[$selected_category])) {
        // If the pool for this category is empty, refill it
        if (!isset($questions_by_category[$selected_category]) || empty($questions_by_category[$selected_category])) {
            error_log("select_question: No questions found for selected category: " . $selected_category);
            return null;
        }
        $all_ids_in_category = $questions_by_category[$selected_category];
        shuffle($all_ids_in_category);
        $available_question_ids_by_category[$selected_category] = $all_ids_in_category;
    }

    $question_id = array_pop($available_question_ids_by_category[$selected_category]);

    if ($question_id === null || !isset($questions_data_map[$question_id])) {
        error_log("select_question: Failed to get a valid question_id or question_data for ID: " . var_export($question_id, true) . " from category: " . $selected_category);
        return null;
    }

    $_SESSION['current_question_data'] = $questions_data_map[$question_id];
    return $_SESSION['current_question_data'];
}


// --- Обробка POST-запитів ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $current_player_idx_on_action = $_SESSION['current_player_index'];
    $player_data = &$_SESSION['players'][$current_player_idx_on_action];

    $made_move = false;

    if ($action === 'completed') {
        $current_question_processed = $_SESSION['current_question_data'] ?? null;
        if ($current_question_processed) {
            if (isset($current_question_processed['bonus_skip_on_complete']) && $current_question_processed['bonus_skip_on_complete'] === true) {
                $player_data['skips_left']++;
            }
            if (!empty($current_question_processed['deferred_text_template']) && !empty($current_question_processed['deferred_turns_player']) && $current_question_processed['deferred_turns_player'] > 0) {
                $player_data['deferred_effects'][] = [
                    'template' => $current_question_processed['deferred_text_template'],
                    'turns_left' => (int)$current_question_processed['deferred_turns_player'],
                    'question_id' => $current_question_processed['id']
                ];
            }
        }
        $made_move = true;
    } elseif ($action === 'skip') {
        if ($player_data['skips_left'] > 0) {
            $player_data['skips_left']--;
            $_SESSION['current_question_data'] = null; // Forces new question for THIS player
        }
    } elseif ($action === 'quit') {
        $player_data['active'] = false;
        $made_move = true;
    }

    if ($made_move) {
        $active_players_count = count(get_active_players_indices());
        if ($active_players_count < 2) {
            $_SESSION['game_over'] = true;
            $_SESSION['game_over_message'] = $active_players_count === 1 ? "Залишився лише один переможець!" : "Гравців не залишилось! Гра завершена.";
        } else {
            $next_player_idx = get_next_active_player_index($current_player_idx_on_action);
            if ($next_player_idx === null) {
                $_SESSION['game_over'] = true;
                $_SESSION['game_over_message'] = "Помилка: Не вдалося визначити наступного гравця.";
            } else {
                $active_indices = get_active_players_indices();
                $pos_current_in_active = array_search($current_player_idx_on_action, $active_indices);
                $pos_next_in_active = array_search($next_player_idx, $active_indices);

                if ($pos_next_in_active !== false && $pos_current_in_active !== false && $pos_next_in_active < $pos_current_in_active) {
                     $_SESSION['current_round']++;
                } else if (count($active_indices) > 0 && $next_player_idx == $active_indices[0] && $current_player_idx_on_action == end($active_indices) && $current_player_idx_on_action != $next_player_idx) {
                    $_SESSION['current_round']++;
                }

                $_SESSION['current_player_index'] = $next_player_idx;
                $_SESSION['current_question_data'] = null;
            }
        }
    }
    if (isset($_SESSION['current_round']) && $_SESSION['current_round'] > 5) {
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Гра завершена! 5 кіл зіграно. Час для відпочинку!";
    }

    header('Location: game.php');
    exit;
}

// --- Підготовка даних для відображення (після всіх обробок POST) ---
if (!isset($_SESSION['current_question_data']) || $_SESSION['current_question_data'] === null) {
    $_SESSION['current_question_data'] = select_question();
    if ($_SESSION['current_question_data'] === null) {
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Помилка: Не вдалося завантажити питання для гри. Можливо, питання закінчились або файл пошкоджено.";
        error_log("Critical: select_question returned null. Ending game.");
        header('Location: game_over.php');
        exit;
    }
}

$current_player_idx = $_SESSION['current_player_index'];
if (!isset($_SESSION['players'][$current_player_idx]) || !$_SESSION['players'][$current_player_idx]['active']) {
    $fallback_idx = get_next_active_player_index($current_player_idx -1 < 0 ? count($_SESSION['players'])-1 : $current_player_idx -1);
    if ($fallback_idx !== null) {
        $_SESSION['current_player_index'] = $fallback_idx;
        $_SESSION['current_question_data'] = null;
        error_log("Fallback: current player was inactive or invalid. Switched to player index: " . $fallback_idx);
        header('Location: game.php');
        exit;
    } else {
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Немає активних гравців для продовження гри.";
        error_log("Critical: No active players found, current_player_index was invalid.");
        header('Location: game_over.php');
        exit;
    }
}
$current_player_data = &$_SESSION['players'][$current_player_idx];


// Process deferred effects
$deferred_messages_to_display = [];
if (!empty($current_player_data['deferred_effects'])) {
    $active_effects = [];
    foreach ($current_player_data['deferred_effects'] as $effect) {
        if (isset($effect['turns_left']) && $effect['turns_left'] > 0) {
            $text = str_replace('{TURNS_LEFT}', $effect['turns_left'], $effect['template']);
            $text = str_replace('{PLAYER_NAME}', htmlspecialchars($current_player_data['name']), $text);
            $deferred_messages_to_display[] = $text;

            $effect['turns_left']--;
            if ($effect['turns_left'] > 0) {
                $active_effects[] = $effect;
            }
        }
    }
    $current_player_data['deferred_effects'] = $active_effects;
}

$current_question = $_SESSION['current_question_data'];

// Prepare question text
$question_text = $current_question['text'];
$question_text = str_replace('{PLAYER_NAME}', htmlspecialchars($current_player_data['name']), $question_text);
if (strpos($question_text, '{RANDOM_PLAYER_NAME}') !== false) {
    $other_active_players_names = [];
    foreach ($_SESSION['players'] as $idx => $p_data) {
        if ($p_data['active'] && $idx != $current_player_idx) {
            $other_active_players_names[] = htmlspecialchars($p_data['name']);
        }
    }
    if (!empty($other_active_players_names)) {
        $random_name = $other_active_players_names[array_rand($other_active_players_names)];
        $question_text = str_replace('{RANDOM_PLAYER_NAME}', $random_name, $question_text);
    } else {
        $question_text = str_replace('{RANDOM_PLAYER_NAME}', '(інший гравець)', $question_text);
    }
}

$style_info = $category_styles[$current_question['category']] ?? $category_styles['Default'];

$next_player_for_button_idx = get_next_active_player_index($current_player_idx);
$next_player_name_for_button = ($next_player_for_button_idx !== null && isset($_SESSION['players'][$next_player_for_button_idx])) ? $_SESSION['players'][$next_player_for_button_idx]['name'] : 'Нікого';

?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Гра: Хід <?php echo htmlspecialchars($current_player_data['name']); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .game-page {
            background: <?php echo $style_info['background']; ?>;
        }
    </style>
</head>
<body>
    <div class="game-page">
        <div class="background-icons-container"></div>
        <div id="game-data-container"
             data-icon-classes='<?php echo json_encode($style_info['icon_classes'] ?? ['fas fa-question-circle']); ?>'
             data-icon-color="<?php echo htmlspecialchars($style_info['icon_color']); ?>"
             data-icon-opacity="<?php echo htmlspecialchars($style_info['icon_opacity'] ?? 0.1); ?>">
        </div>
        <div class="category-display">
            Категорія: <?php echo htmlspecialchars($current_question['category']); ?>
        </div>
        <div class="round-player-info">
            Раунд: <?php echo $_SESSION['current_round']; ?><br>
            Гравців: <?php echo count(get_active_players_indices()); ?>
        </div>
        <div class="question-container">
            <div class="current-player-name"><?php echo htmlspecialchars($current_player_data['name']); ?></div>
            <div class="question-text"><?php echo nl2br($question_text); ?></div>
            <?php if (!empty($deferred_messages_to_display)): ?>
                <div class="deferred-messages">
                    <strong>Активні ефекти:</strong>
                    <?php foreach ($deferred_messages_to_display as $msg): ?>
                        <p><?php echo $msg; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="action-buttons">
            <form method="POST" action="game.php" style="width: 100%;">
                <button type="submit" name="action" value="done" class="btn-done">
                    Виконано! (Наступний: <?php echo $next_player_name_for_button; ?>)
                </button>
            </form>
            <form method="POST" action="game.php" style="width: 100%;">
                <button type="submit" name="action" value="skip" class="btn-skip" <?php echo ($current_player_data['skips_left'] <= 0) ? 'disabled' : ''; ?>>
                    Пропустити (Залишилось: <?php echo $current_player_data['skips_left']; ?>)
                </button>
            </form>
            <form method="POST" action="game.php" style="width: 100%;">
                <button type="submit" name="action" value="quit" class="btn-quit">
                    Вийти з гри
                </button>
            </form>
        </div>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
