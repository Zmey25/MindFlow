<?php
session_start();

const READING_TIMER_DURATION = 10;

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
    $_SESSION['game_over_message'] = "Помилка: Файли гри не завантажено.";
    header('Location: game_over.php');
    exit;
}

function get_active_players_indices() {
    $active_indices = [];
    if (isset($_SESSION['players']) && is_array($_SESSION['players'])) {
        foreach ($_SESSION['players'] as $index => $player) {
            if ($player['active'] ?? false) {
                $active_indices[] = $index;
            }
        }
    }
    return $active_indices;
}

function get_next_active_player_index($current_index) {
    $all_players = $_SESSION['players'] ?? [];
    if (empty($all_players)) return null;
    $num_players = count($all_players);
    $next_idx = ($current_index + 1) % $num_players;
    for ($i = 0; $i < $num_players; $i++) {
        if ($all_players[$next_idx]['active'] ?? false) {
            return $next_idx;
        }
        $next_idx = ($next_idx + 1) % $num_players;
    }
    return null;
}

function select_question() {
    global $questions_data_map;
    if (empty($_SESSION['game_question_pool'])) return null;
    $question_id = array_shift($_SESSION['game_question_pool']);
    if ($question_id === null || !isset($questions_data_map[$question_id])) return null;
    $_SESSION['current_question_data'] = $questions_data_map[$question_id];
    $_SESSION['timer_phase'] = 'reading';
    $_SESSION['timer_started_at'] = time();
    return $_SESSION['current_question_data'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'go_back') {
        if (!empty($_SESSION['game_history'])) {
            if (isset($_SESSION['current_question_data']['id'])) {
                array_unshift($_SESSION['game_question_pool'], $_SESSION['current_question_data']['id']);
            }
            $last_state = array_pop($_SESSION['game_history']);

            $_SESSION['current_question_data'] = $last_state['question'];
            $_SESSION['current_player_index'] = $last_state['player_index'];
            $_SESSION['current_round'] = $last_state['round'];
            $_SESSION['players'] = $last_state['players_state'];

            $_SESSION['timer_phase'] = 'reading';
            $_SESSION['timer_started_at'] = time();
        }
    } else {
        $current_player_idx = $_SESSION['current_player_index'];
        
        // Save state BEFORE any modifications for this turn
        array_push($_SESSION['game_history'], [
            'question' => $_SESSION['current_question_data'],
            'player_index' => $current_player_idx,
            'round' => $_SESSION['current_round'],
            'players_state' => $_SESSION['players']
        ]);

        // --- NEW: Modify state explicitly here ---
        $player_data = &$_SESSION['players'][$current_player_idx];
        
        // 1. Decrement existing effects for the current player
        if (!empty($player_data['deferred_effects'])) {
            $active_effects = [];
            foreach ($player_data['deferred_effects'] as $effect) {
                $effect['turns_left']--;
                if ($effect['turns_left'] > 0) {
                    $active_effects[] = $effect;
                }
            }
            $player_data['deferred_effects'] = $active_effects;
        }

        // 2. Apply action consequences
        if ($action === 'completed') {
            $q = $_SESSION['current_question_data'];
            if ($q['bonus_skip_on_complete'] ?? false) $player_data['skips_left']++;
            if (!empty($q['deferred_text_template']) && !empty($q['deferred_turns_player'])) {
                $player_data['deferred_effects'][] = ['template' => $q['deferred_text_template'], 'turns_left' => (int)$q['deferred_turns_player'], 'question_id' => $q['id']];
            }
        } elseif ($action === 'skip' && $player_data['skips_left'] > 0) {
            $player_data['skips_left']--;
        } elseif ($action === 'quit') {
            $player_data['active'] = false;
        }
        
        // 3. Move to next player
        $_SESSION['current_question_data'] = null;
        $active_players_count = count(get_active_players_indices());

        if ($active_players_count < 2) {
            $_SESSION['game_over'] = true;
            $_SESSION['game_over_message'] = $active_players_count === 1 ? "Залишився переможець!" : "Гравців не залишилось!";
        } else {
            $next_player_idx = get_next_active_player_index($current_player_idx);
            if ($next_player_idx === null) {
                $_SESSION['game_over'] = true;
                $_SESSION['game_over_message'] = "Не вдалося знайти наступного гравця.";
            } else {
                $active_indices = get_active_players_indices();
                if (($next_player_idx == ($active_indices[0] ?? null)) && ($current_player_idx !== $next_player_idx || count($active_indices) === 1)) {
                    $_SESSION['current_round']++;
                }
                $_SESSION['current_player_index'] = $next_player_idx;
            }
        }
    }

    if (isset($_SESSION['current_round']) && $_SESSION['current_round'] > 5) {
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "5 кіл зіграно. Гра завершена!";
    }

    header('Location: game.php');
    exit;
}


if (empty($_SESSION['current_question_data'])) {
    if (select_question() === null) {
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Питання закінчились!";
        header('Location: game_over.php');
        exit;
    }
}

$current_player_idx = $_SESSION['current_player_index'];
if (!($_SESSION['players'][$current_player_idx]['active'] ?? false)) {
    $fallback_idx = get_next_active_player_index($current_player_idx - 1);
    if ($fallback_idx !== null) {
        $_SESSION['current_player_index'] = $fallback_idx;
        $_SESSION['current_question_data'] = null;
        header('Location: game.php');
        exit;
    } else {
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Немає активних гравців.";
        header('Location: game_over.php');
        exit;
    }
}

$current_player_data = $_SESSION['players'][$current_player_idx];
$current_question = $_SESSION['current_question_data'];

$deferred_messages_to_display = [];
if (!empty($current_player_data['deferred_effects'])) {
    foreach ($current_player_data['deferred_effects'] as $effect) {
        $text = str_replace(['{TURNS_LEFT}', '{PLAYER_NAME}'], [$effect['turns_left'], htmlspecialchars($current_player_data['name'])], $effect['template']);
        $deferred_messages_to_display[] = $text;
    }
}

$question_text = str_replace('{PLAYER_NAME}', htmlspecialchars($current_player_data['name']), $current_question['text']);
if (strpos($question_text, '{RANDOM_PLAYER_NAME}') !== false) {
    $other_names = [];
    foreach ($_SESSION['players'] as $idx => $p) {
        if ($p['active'] && $idx != $current_player_idx) $other_names[] = htmlspecialchars($p['name']);
    }
    $question_text = str_replace('{RANDOM_PLAYER_NAME}', !empty($other_names) ? $other_names[array_rand($other_names)] : '(інший гравець)', $question_text);
}

$style_info = $category_styles[$current_question['category']] ?? $category_styles['Default'];
$next_player_name = 'Нікого';
$next_player_idx = get_next_active_player_index($current_player_idx);
if ($next_player_idx !== null) $next_player_name = $_SESSION['players'][$next_player_idx]['name'];

$initial_timer_value = null;
$current_phase_for_js = $_SESSION['timer_phase'] ?? 'reading';
$main_timer_duration = $current_question['timer'] ?? null;
if ($main_timer_duration !== null) {
    $elapsed = time() - $_SESSION['timer_started_at'];
    if ($current_phase_for_js === 'reading') {
        $remaining = READING_TIMER_DURATION - $elapsed;
        $initial_timer_value = ($remaining > 0) ? $remaining : $main_timer_duration + $remaining;
        if ($remaining <= 0) $current_phase_for_js = 'main';
    } else {
        $initial_timer_value = $main_timer_duration - $elapsed;
    }
    $initial_timer_value = max(0, $initial_timer_value);
}
?>
<!DOCTYPE html>
<html lang="uk"><head>
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
            initialTimerValue: <?php echo json_encode($initial_timer_value); ?>,
            initialPhase: <?php echo json_encode($current_phase_for_js); ?>
        };
    </script>
</head><body>
    <div class="game-page">
        <div class="background-icons-container"></div>
        <div class="category-display">Категорія: <?php echo htmlspecialchars($current_question['category']); ?></div>
        <div class="round-player-info">Раунд: <?php echo $_SESSION['current_round']; ?><br>Гравців: <?php echo count(get_active_players_indices()); ?></div>
        <?php if ($main_timer_duration !== null): ?>
        <div id="timer-container" class="timer-container"><div id="timer-circle" class="timer-circle"></div></div>
        <?php endif; ?>
        <div class="question-container">
            <div class="current-player-name"><?php echo htmlspecialchars($current_player_data['name']); ?></div>
            <div class="question-text"><?php echo nl2br($question_text); ?></div>
            <?php if (!empty($deferred_messages_to_display)): ?>
                <div class="deferred-messages"><strong>Активні ефекти:</strong><?php foreach ($deferred_messages_to_display as $msg): ?><p><?php echo $msg; ?></p><?php endforeach; ?></div>
            <?php endif; ?>
        </div>
        <div class="action-buttons">
            <form method="POST" action="game.php" style="width: 100%;"><button type="submit" name="action" value="completed" class="btn-done action-btn">Виконано! (Наст: <?php echo htmlspecialchars($next_player_name); ?>)</button></form>
            <form method="POST" action="game.php" style="width: 100%;"><button type="submit" name="action" value="skip" class="btn-skip action-btn" <?php echo ($current_player_data['skips_left'] <= 0) ? 'disabled' : ''; ?>>Пропустити (Залишилось: <?php echo $current_player_data['skips_left']; ?>)</button></form>
            <form method="POST" action="game.php" style="width: 100%;"><button type="submit" name="action" value="go_back" class="btn-go-back action-btn" <?php echo empty($_SESSION['game_history']) ? 'disabled' : ''; ?>>Попереднє питання</button></form>
            <form method="POST" action="game.php" style="width: 100%;"><button type="submit" name="action" value="quit" class="btn-quit action-btn">Вийти з гри</button></form>
        </div>
    </div>
    <script src="js/script.js"></script>
</body></html>
