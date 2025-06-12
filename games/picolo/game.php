<?php
session_start();

if (!isset($_SESSION['game_started']) || $_SESSION['game_started'] !== true) {
    header('Location: index.php?new_game=true');
    exit;
}
if (isset($_SESSION['game_over']) && $_SESSION['game_over'] === true) {
    header('Location: game_over.php');
    exit;
}

if (!isset($_SESSION['game_config']) || !isset($_SESSION['all_questions_data']) || !isset($_SESSION['category_styles_from_json'])) {
    $_SESSION['game_over'] = true;
    $_SESSION['game_over_message'] = "Помилка: Конфігурація гри не завантажена. Почніть нову гру.";
    header('Location: game_over.php');
    exit;
}

$game_config = $_SESSION['game_config'];
$reading_timer_duration_setting = $game_config['general']['reading_timer_duration'] ?? 10;
$max_rounds_setting = $game_config['general']['max_rounds'] ?? 5;

$questions_data_map = $_SESSION['all_questions_data'];
$category_styles = $_SESSION['category_styles_from_json'];

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
            if ($player['active'] ?? false) $active_indices[] = $index;
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
        if ($all_players[$next_idx]['active'] ?? false) return $next_idx;
        $next_idx = ($next_idx + 1) % $num_players;
    }
    return null;
}

function select_question() {
    global $questions_data_map, $reading_timer_duration_setting;
    if (empty($_SESSION['game_question_pool'])) return null;
    
    $question_id = array_shift($_SESSION['game_question_pool']);
    if ($question_id === null || !isset($questions_data_map[$question_id])) return null;
    
    $_SESSION['current_question_data'] = $questions_data_map[$question_id];
    
    $question_has_main_timer = (($_SESSION['current_question_data']['timer'] ?? 0) > 0);
    if ($question_has_main_timer && $reading_timer_duration_setting > 0) {
        $_SESSION['timer_phase'] = 'reading';
    } else {
        $_SESSION['timer_phase'] = 'main';
    }
    $_SESSION['timer_started_at'] = time();
    return $_SESSION['current_question_data'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $current_player_idx = $_SESSION['current_player_index'];
    $current_question_for_action = $_SESSION['current_question_data']; 

    if ($action === 'go_back') {
        if (!empty($_SESSION['game_history'])) {
            // If there's a current question, put its ID back into the pool
            if (isset($current_question_for_action['id'])) {
                array_unshift($_SESSION['game_question_pool'], $current_question_for_action['id']);
            }
            $last_state = array_pop($_SESSION['game_history']);

            $_SESSION['current_question_data'] = $last_state['question'];
            $_SESSION['current_player_index'] = $last_state['player_index'];
            $_SESSION['current_round'] = $last_state['round'];
            $_SESSION['players'] = $last_state['players_state']; // This restores deferred_effects as they were
            
            $question_has_main_timer = (($_SESSION['current_question_data']['timer'] ?? 0) > 0);
            if ($question_has_main_timer && $reading_timer_duration_setting > 0) {
                $_SESSION['timer_phase'] = 'reading';
            } else {
                $_SESSION['timer_phase'] = 'main';
            }
            $_SESSION['timer_started_at'] = time();
        }
    } else {
        // --- State modification block for non 'go_back' actions ---
        // We capture the player data by reference to modify it directly in the session
        $player_data_ref = &$_SESSION['players'][$current_player_idx];

        // Actions 'completed' and 'quit' are turn-ending.
        // Action 'skip' (reroll) is NOT turn-ending.
        // Deferred effects are processed only on turn-ending actions.
        if ($action === 'completed' || $action === 'quit') {
            // Store state BEFORE this turn's modifications for 'go_back'
            // This includes player state BEFORE deferred effects decrement for THIS turn
             if (count($_SESSION['game_history']) >= 20) array_shift($_SESSION['game_history']);
             array_push($_SESSION['game_history'], [
                 'question' => $current_question_for_action, // The question that was just acted upon
                 'player_index' => $current_player_idx,
                 'round' => $_SESSION['current_round'],
                 'players_state' => $_SESSION['players'] // Current state of all players (including their effects)
             ]);

            // Decrement existing deferred effects for the current player
            if (!empty($player_data_ref['deferred_effects'])) {
                $active_effects = [];
                foreach ($player_data_ref['deferred_effects'] as $effect) {
                    $effect['turns_left']--;
                    if ($effect['turns_left'] > 0) $active_effects[] = $effect;
                }
                $player_data_ref['deferred_effects'] = $active_effects;
            }
        }
        // If action is 'skip', history is NOT pushed here, because 'go_back' should revert to the state *before* the first question of this reroll sequence.

        // Apply action consequences
        if ($action === 'completed') {
            $q = $current_question_for_action;
            if ($q['bonus_skip_on_complete'] ?? false) $player_data_ref['skips_left']++;
            if (!empty($q['deferred_text_template']) && !empty($q['deferred_turns_player'])) {
                $player_data_ref['deferred_effects'][] = ['template' => $q['deferred_text_template'], 'turns_left' => (int)$q['deferred_turns_player'], 'question_id' => $q['id']];
            }
            $_SESSION['current_question_data'] = null; // Mark question as processed, next player's turn
        } elseif ($action === 'skip') {
            // No free reroll anymore
            if ($player_data_ref['skips_left'] > 0) {
                $player_data_ref['skips_left']--;
                // The current question (the one being rerolled) is not put back into the pool.
                // It's considered "used" by the reroll.
                $_SESSION['current_question_data'] = null; // Nullify to trigger select_question()
                if (select_question() === null) { // Try to get a new question
                    $_SESSION['game_over'] = true;
                    $_SESSION['game_over_message'] = "Питання закінчились після спроби реролу!";
                }
                // Player index and round remain the same. Timer phase/start time reset by select_question().
            }
            // If no skips left, page just reloads with the same question (button should be disabled by UI).
        } elseif ($action === 'quit') {
            $player_data_ref['active'] = false;
            $_SESSION['current_question_data'] = null; // Mark question as processed, next player's turn
        }
        
        // --- Move to next player or end game (only if action was 'completed' or 'quit') ---
        if ($action === 'completed' || $action === 'quit') {
            $active_players_count = count(get_active_players_indices());
            if ($active_players_count < 2) {
                $_SESSION['game_over'] = true;
                $_SESSION['game_over_message'] = $active_players_count === 1 ? "Залишився переможець!" : "Гравців не залишилось!";
            } else {
                $next_player_idx = get_next_active_player_index($current_player_idx);
                if ($next_player_idx === null) {
                    $_SESSION['game_over'] = true; $_SESSION['game_over_message'] = "Не вдалося знайти наступного гравця.";
                } else {
                    $active_indices = get_active_players_indices();
                    // Increment round if the next player is the first active player in the list
                    // AND it's not the same player continuing (unless they are the only one left and it's their turn again).
                    if ( ($next_player_idx == ($active_indices[0] ?? null)) && ($current_player_idx != $next_player_idx || count($active_indices) == 1) ) {
                       // Only increment round if it's genuinely a new round for the group,
                       // or if a single player is cycling through turns.
                       if(count($active_indices) > 1 || $_SESSION['current_player_index'] != $next_player_idx ) {
                            $_SESSION['current_round']++;
                       } else if (count($active_indices) == 1 && $current_player_idx == $next_player_idx) {
                           // If only one player is left, round should still increment when their turn comes up again
                           // This condition might be redundant if the game ends when only 1 player is left
                           // but kept for robustness if single-player mode could exist.
                           // Actually, for a single player, their index will always be active_indices[0].
                           // So the round will increment on each of their turns.
                           $_SESSION['current_round']++;
                       }
                    }
                    $_SESSION['current_player_index'] = $next_player_idx;
                }
            }
        }
    } // End of non-'go_back' action block

    // Check for max rounds after all actions
    if (!($_SESSION['game_over'] ?? false) && isset($_SESSION['current_round']) && $_SESSION['current_round'] > $max_rounds_setting) {
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = $max_rounds_setting . " кіл зіграно. Гра завершена!";
    }

    header('Location: game.php');
    exit;
}


// --- Page Load Logic (if not a POST request or if POST led here) ---

// If no current question (e.g., after a turn-ending action, or at game start for first player)
if (empty($_SESSION['current_question_data'])) {
    // Before selecting a new question for the current player, push the state if it's a new turn for this player
    // This is crucial for 'go_back' from the very first question of a player's turn.
    // However, this needs to be nuanced: only push if it's truly a *new* turn state, not after a reroll.
    // The current logic for 'go_back' already handles putting the *previous* question back.
    // The history for the *start* of a player's turn (before they see their first question or after they complete one)
    // is effectively captured when 'completed' or 'quit' is processed for the *previous* player.
    // So, we might not need an explicit history push here.

    if (select_question() === null) { // This selects a question and sets up timer phase
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Питання закінчились!";
        header('Location: game_over.php');
        exit;
    }
}

$current_player_idx = $_SESSION['current_player_index'];
// Ensure current player is active; if not, find next active or end game
if (!($_SESSION['players'][$current_player_idx]['active'] ?? false)) {
    $original_player_idx_before_skip = $current_player_idx;
    $fallback_idx = get_next_active_player_index( $current_player_idx -1 < 0 ? count($_SESSION['players']) -1 : $current_player_idx -1 );

    if ($fallback_idx !== null) {
        // If the inactive player was supposed to start a new round, and we skipped them to the next player
        // who is also the start of the round, the round might have been incremented already by the previous player's turn end.
        // We need to be careful not to double-increment.
        // The round increment logic is tied to the *end* of the *previous* player's turn.
        // If an inactive player's turn is skipped, the round should reflect the state as if that player played.
        $_SESSION['current_player_index'] = $fallback_idx;
        $_SESSION['current_question_data'] = null; // Force new question selection for this new player
        
        // Check if skipping the inactive player crossed a round boundary
        // This is complex, as the round increments when the *previous* player finishes and points to the *new* first player.
        // For simplicity, we rely on the round increment logic at the end of a completed turn.
        // If an inactive player is skipped, the next player starts their turn in the *current* round.

        header('Location: game.php'); // Reload to select question for this new player
        exit;
    } else { 
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Немає активних гравців.";
        header('Location: game_over.php');
        exit;
    }
}

$current_player_data = $_SESSION['players'][$current_player_idx];
$current_question = $_SESSION['current_question_data']; // This is now guaranteed to be set

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

$style_info = $category_styles[$current_question['category']] ?? ($category_styles['Default'] ?? ['background' => 'linear-gradient(to right, #74ebd5, #ACB6E5)', 'icon_classes' => ['fas fa-question-circle'], 'icon_color' => 'rgba(255,255,255,0.1)', 'icon_opacity' => 0.1]);
$next_player_name_display = 'Нікого';
$next_player_idx_val = get_next_active_player_index($current_player_idx);
if ($next_player_idx_val !== null) $next_player_name_display = $_SESSION['players'][$next_player_idx_val]['name'];


// --- Timer data for JS ---
$main_timer_from_question = (int)($current_question['timer'] ?? 0);
$js_effective_reading_duration = 0; // This will be the actual reading duration for JS timer
if ($main_timer_from_question > 0 && $reading_timer_duration_setting > 0) {
    $js_effective_reading_duration = $reading_timer_duration_setting;
}

$initial_timer_value_for_js = 0;
$current_phase_for_js = $_SESSION['timer_phase']; // Phase already determined by select_question or go_back

if ($js_effective_reading_duration > 0 || $main_timer_from_question > 0) { // If any timer is active
    $elapsed_time = time() - ($_SESSION['timer_started_at'] ?? time());

    if ($current_phase_for_js === 'reading') { // This implies $js_effective_reading_duration > 0
        $remaining_reading_time = $js_effective_reading_duration - $elapsed_time;
        if ($remaining_reading_time > 0) {
            $initial_timer_value_for_js = $remaining_reading_time;
        } else { // Reading time expired
            $current_phase_for_js = 'main'; // Switch to main phase for JS
            $initial_timer_value_for_js = $main_timer_from_question + $remaining_reading_time; // remaining_reading_time is negative or 0
        }
    } else { // Current phase is 'main' (either by initial selection or because reading expired)
        $initial_timer_value_for_js = $main_timer_from_question - $elapsed_time;
    }
    $initial_timer_value_for_js = max(0, $initial_timer_value_for_js);
} else { // No timers for this question (neither reading nor main)
    $current_phase_for_js = 'main'; // Default to main, though timer won't run/display
    $initial_timer_value_for_js = 0;
}
// --- End Timer data for JS ---

$disable_skip_button = ($current_player_data['skips_left'] <= 0);

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
            iconColor: <?php echo json_encode($style_info['icon_color'] ?? 'rgba(255,255,255,0.1)'); ?>,
            iconOpacity: <?php echo json_encode($style_info['icon_opacity'] ?? 0.1); ?>,
            readingTimerDuration: <?php echo json_encode($js_effective_reading_duration); ?>,
            mainTimerDuration: <?php echo json_encode($main_timer_from_question); ?>,
            initialTimerValue: <?php echo json_encode($initial_timer_value_for_js); ?>,
            initialPhase: <?php echo json_encode($current_phase_for_js); ?>
        };
    </script>
</head><body>
    <div class="game-page">
        <div class="background-icons-container"></div>
        <div class="category-display">
            ID: <?php echo htmlspecialchars($current_question['id']); ?> | Категорія: <?php echo htmlspecialchars($current_question['category']); ?>
        </div>
        <div class="round-player-info">Раунд: <?php echo $_SESSION['current_round']; ?>/<?php echo $max_rounds_setting; ?><br>Гравців: <?php echo count(get_active_players_indices()); ?></div>
        
        <?php if ($js_effective_reading_duration > 0 || $main_timer_from_question > 0): ?>
        <div id="timer-container" class="timer-container timer-<?php echo $current_phase_for_js; ?>"><div id="timer-circle" class="timer-circle"></div></div>
        <?php endif; ?>

        <div class="question-container">
            <div class="current-player-name"><?php echo htmlspecialchars($current_player_data['name']); ?></div>
            <div class="question-text"><?php echo nl2br($question_text); ?></div>
            <?php if (!empty($deferred_messages_to_display)): ?>
                <div class="deferred-messages"><strong>Активні ефекти:</strong><?php foreach ($deferred_messages_to_display as $msg): ?><p><?php echo $msg; ?></p><?php endforeach; ?></div>
            <?php endif; ?>
        </div>
        <div class="action-buttons">
            <form method="POST" action="game.php" style="width: 100%;"><button type="submit" name="action" value="completed" class="btn-done action-btn">Виконано! (Наст: <?php echo htmlspecialchars($next_player_name_display); ?>)</button></form>
            <form method="POST" action="game.php" style="width: 100%;"><button type="submit" name="action" value="skip" class="btn-skip action-btn" <?php echo $disable_skip_button ? 'disabled' : ''; ?>>Пропустити (Залишилось: <?php echo $current_player_data['skips_left']; ?>)</button></form>
            <form method="POST" action="game.php" style="width: 100%;"><button type="submit" name="action" value="go_back" class="btn-go-back action-btn" <?php echo empty($_SESSION['game_history']) ? 'disabled' : ''; ?>>Попереднє питання</button></form>
            <form method="POST" action="game.php" style="width: 100%;"><button type="submit" name="action" value="quit" class="btn-quit action-btn">Вийти з гри</button></form>
        </div>
    </div>
    <script src="js/script.js"></script></body></html>
