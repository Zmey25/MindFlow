<?php
session_start();

const READING_TIMER_DURATION = 10;

// Basic game state checks
if (!isset($_SESSION['game_started']) || $_SESSION['game_started'] !== true) {
    header('Location: index.php?new_game=true');
    exit;
}
if (isset($_SESSION['game_over']) && $_SESSION['game_over'] === true) {
    header('Location: game_over.php');
    exit;
}

$questions_data_map = $_SESSION['all_questions_data'] ?? [];
$category_styles = $_SESSION['category_styles'] ?? [];

if (empty($questions_data_map) || empty($category_styles) || !isset($_SESSION['game_question_pool'])) {
    $_SESSION['game_over'] = true;
    $_SESSION['game_over_message'] = "Помилка: Файли гри (питання/стилі) не завантажено належним чином.";
    header('Location: game_over.php');
    exit;
}

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
    global $questions_data_map;
    if (!isset($_SESSION['game_question_pool']) || empty($_SESSION['game_question_pool'])) {
        return null;
    }
    $question_id = array_shift($_SESSION['game_question_pool']);
    if ($question_id === null || !isset($questions_data_map[$question_id])) {
        return null;
    }
    $_SESSION['current_question_data'] = $questions_data_map[$question_id];
    $_SESSION['timer_phase'] = 'reading';
    $_SESSION['timer_started_at'] = time();
    return $_SESSION['current_question_data'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $current_player_idx_on_action = $_SESSION['current_player_index'];
    $player_data = &$_SESSION['players'][$current_player_idx_on_action];
    $made_move = false;

    if ($action === 'completed' || $action === 'skip' || $action === 'quit') {
        if ($action === 'skip' && $player_data['skips_left'] > 0) {
            $player_data['skips_left']--;
            $made_move = true;
        } elseif ($action === 'quit') {
            $player_data['active'] = false;
            $made_move = true;
        } elseif ($action === 'completed') {
            $current_question_processed = $_SESSION['current_question_data'] ?? null;
            if ($current_question_processed) {
                if (isset($current_question_processed['bonus_skip_on_complete']) && $current_question_processed['bonus_skip_on_complete'] === true) {
                    $player_data['skips_left']++;
                }
                if (!empty($current_question_processed['deferred_text_template']) && !empty($current_question_processed['deferred_turns_player'])) {
                    $player_data['deferred_effects'][] = ['template' => $current_question_processed['deferred_text_template'], 'turns_left' => (int)$current_question_processed['deferred_turns_player'], 'question_id' => $current_question_processed['id']];
                }
            }
            $made_move = true;
        }
    } elseif ($action === 'go_back') {
        // --- NEW GO BACK LOGIC ---
        if (!empty($_SESSION['game_history'])) {
            // 1. Put the current question back into the pool so it's not lost
            if (isset($_SESSION['current_question_data']['id'])) {
                array_unshift($_SESSION['game_question_pool'], $_SESSION['current_question_data']['id']);
            }
            
            // 2. Pop the last state from history
            $last_state = array_pop($_SESSION['game_history']);

            // 3. Restore the game state from history
            $_SESSION['current_question_data'] = $last_state['question'];
            $_SESSION['current_player_index'] = $last_state['player_index'];
            $_SESSION['current_round'] = $last_state['round'];
            // NOTE: We don't restore player specific data like skips or effects, as that would be too complex.
            // Going back only reverts the question/player turn.

            // 4. Reset the timer for the restored question
            $_SESSION['timer_phase'] = 'reading';
            $_SESSION['timer_started_at'] = time();

            header('Location: game.php');
            exit;
        }
    }

    if ($made_move) {
        // --- NEW HISTORY LOGIC: Save the completed turn to history ---
        $history_state = [
            'question' => $_SESSION['current_question_data'],
            'player_index' => $_SESSION['current_player_index'],
            'round' => $_SESSION['current_round']
        ];
        array_push($_SESSION['game_history'], $history_state);

        $_SESSION['current_question_data'] = null;
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
                 if (($next_player_idx == ($active_indices[0] ?? null)) && ($current_player_idx_on_action !== $next_player_idx || count($active_indices) === 1)) {
                     $_SESSION['current_round']++;
                 }
                $_SESSION['current_player_index'] = $next_player_idx;
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

if (!isset($_SESSION['current_question_data']) || $_SESSION['current_question_data'] === null) {
    $_SESSION['current_question_data'] = select_question();
    if ($_SESSION['current_question_data'] === null) {
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Питання закінчились! Гра завершена.";
        header('Location: game_over.php');
        exit;
    }
}

$current_player_idx = $_SESSION['current_player_index'];
if (!isset($_SESSION['players'][$current_player_idx]) || !$_SESSION['players'][$current_player_idx]['active']) {
    $fallback_idx = get_next_active_player_index($current_player_idx - 1 < 0 ? count($_SESSION['players']) - 1 : $current_player_idx - 1);
    if ($fallback_idx !== null) {
        $_SESSION['current_player_index'] = $fallback_idx;
        $_SESSION['current_question_data'] = null;
        $_SESSION['timer_phase'] = 'reading';
        $_SESSION['timer_started_at'] = time();
        header('Location: game.php');
        exit;
    } else {
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Немає активних гравців для продовження гри.";
        header('Location: game_over.php');
        exit;
    }
}
$current_player_data = &$_SESSION['players'][$current_player_idx];

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

$initial_timer_value = null;
$current_phase_for_js = $_SESSION['timer_phase'] ?? 'reading';
$main_timer_duration = $current_question['timer'] ?? null;
if ($main_timer_duration !== null) {
    $time_started = $_SESSION['timer_started_at'];
    $elapsed = time() - $time_started;
    if ($current_phase_for_js === 'reading') {
        $remaining_reading = READING_TIMER_DURATION - $elapsed;
        if ($remaining_reading > 0) {
            $initial_timer_value = $remaining_reading;
        } else {
            $current_phase_for_js = 'main';
            $time_into_main = $elapsed - READING_TIMER_DURATION;
            $initial_timer_value = $main_timer_duration - $time_into_main;
        }
    } else {
        $initial_timer_value = $main_timer_duration - $elapsed;
    }
    $initial_timer_value = max(0, $initial_timer_value);
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Гра: Хід <?php echo htmlspecialchars($current_player_data['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script>
        window.GAME_DATA = {
            backgroundGradient: <?php echo json_encode($style_info['background']); ?>,
            iconClasses: <?php echo json_encode($style_info['icon_classes'] ?? ['fas fa-question-circle']); ?>,
            iconColor: <?php echo json_encode($style_info['icon_color']); ?>,
            iconOpacity: <?php echo json_encode($style_info['icon_opacity'] ?? 0.1); ?>,
            mainTimerDuration: <?php echo json_encode($main_timer_duration); ?>,
            readingTimerDuration: <?php echo READING_TIMER_DURATION; ?>,
            initialTimerValue: <?php echo json_encode($initial_timer_value); ?>,
            initialPhase: <?php echo json_encode($current_phase_for_js); ?>
        };
    </script>
</head>
<body>
    <div class="game-page">
        <div class="background-icons-container"></div>
        <div class="category-display">Категорія: <?php echo htmlspecialchars($current_question['category']); ?> (ID: <?php echo htmlspecialchars($current_question['id']); ?>)</div>
        <div class="round-player-info">Раунд: <?php echo $_SESSION['current_round']; ?><br>Гравців: <?php echo count(get_active_players_indices()); ?></div>
        <?php if ($main_timer_duration !== null): ?>
        <div id="timer-container" class="timer-container"><div id="timer-circle" class="timer-circle"></div></div>
        <?php endif; ?>
        <div class="question-container">
            <div class="current-player-name"><?php echo htmlspecialchars($current_player_data['name']); ?></div>
            <div class="question-text"><?php echo nl2br($question_text); ?></div>
            <?php if (!empty($deferred_messages_to_display)): ?>
                <div class="deferred-messages">
                    <strong>Активні ефекти:</strong>
                    <?php foreach ($deferred_messages_to_display as $msg): ?><p><?php echo $msg; ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="action-buttons">
            <form method="POST" action="game.php" style="width: 100%;">
                <button type="submit" name="action" value="completed" class="btn-done">Виконано! (Наступний: <?php echo htmlspecialchars($next_player_name_for_button); ?>)</button>
            </form>
            <form method="POST" action="game.php" style="width: 100%;">
                <button type="submit" name="action" value="skip" class="btn-skip" <?php echo ($current_player_data['skips_left'] <= 0) ? 'disabled' : ''; ?>>Пропустити (Залишилось: <?php echo $current_player_data['skips_left']; ?>)</button>
            </form>
            <form method="POST" action="game.php" style="width: 100%;">
                <button type="submit" name="action" value="go_back" class="btn-go-back" <?php echo empty($_SESSION['game_history']) ? 'disabled' : ''; ?>>Попереднє питання</button>
            </form>
            <form method="POST" action="game.php" style="width: 100%;">
                <button type="submit" name="action" value="quit" class="btn-quit">Вийти з гри</button>
            </form>
        </div>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
