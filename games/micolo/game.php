<?php
session_start();

if (!isset($_SESSION['game_started']) || $_SESSION['game_started'] !== true) {
    header('Location: index.php?new_game=true');
    exit;
}
if (isset($_SESSION['game_over']) && $_SESSION['game_over'] === true) {
    // This should ideally not be hit if JS handles game end properly,
    // but as a fallback.
    header('Location: game_over.php');
    exit;
}

// Check for essential data prepared by index.php or game_over.php (for "Play Again")
if (
    !isset($_SESSION['game_config']) || 
    !isset($_SESSION['players']) || 
    !isset($_SESSION['initial_js_question_pool']) || 
    empty($_SESSION['initial_js_question_pool']) ||
    !isset($_SESSION['category_styles_from_json']) ||
    !isset($_SESSION['all_questions_data_map'])
) {
    // Critical data missing, force new game setup
    $_SESSION['game_over'] = true; // Mark as over to allow redirection
    $_SESSION['game_over_message'] = "Помилка: Необхідні дані гри відсутні. Почніть нову гру.";
    // Clean up potentially corrupted session state for game start
    unset($_SESSION['game_started']);
    header('Location: game_over.php'); // Redirect to game_over to display message and offer new game
    exit;
}

$game_config = $_SESSION['game_config'];
$players_initial_state = $_SESSION['players'];
$current_player_index_initial = $_SESSION['current_player_index'] ?? 0;
$current_round_initial = $_SESSION['current_round'] ?? 1;
$initial_js_question_pool = $_SESSION['initial_js_question_pool']; // Full question objects, sorted
$category_styles = $_SESSION['category_styles_from_json'];
$all_questions_data_map_for_js = $_SESSION['all_questions_data_map']; // For reference

// Initial question and timer state for the very first display
$first_question_to_display = $initial_js_question_pool[0]; // JS will also use this as its first
$current_player_data_for_first_display = $players_initial_state[$current_player_index_initial];

$initial_timer_phase_for_js = $_SESSION['initial_timer_phase'] ?? 'main';
$initial_timer_started_at_for_js = $_SESSION['initial_timer_started_at'] ?? time(); // Server time

// Calculate effective reading duration and initial timer value for JS for the first question
$main_timer_from_first_question = (int)($first_question_to_display['timer'] ?? 0);
$reading_timer_duration_setting = $game_config['general']['reading_timer_duration'] ?? 10;
$js_effective_reading_duration_first_q = 0;
if ($main_timer_from_first_question > 0 && $reading_timer_duration_setting > 0) {
    $js_effective_reading_duration_first_q = $reading_timer_duration_setting;
}

$initial_timer_value_for_js_first_q = 0;
$current_phase_for_js_first_q = $initial_timer_phase_for_js;

if ($js_effective_reading_duration_first_q > 0 || $main_timer_from_first_question > 0) {
    $elapsed_time = time() - $initial_timer_started_at_for_js;
    if ($current_phase_for_js_first_q === 'reading') {
        $remaining_reading_time = $js_effective_reading_duration_first_q - $elapsed_time;
        if ($remaining_reading_time > 0) {
            $initial_timer_value_for_js_first_q = $remaining_reading_time;
        } else {
            $current_phase_for_js_first_q = 'main'; // Auto-transition if reading time elapsed server-side
            $initial_timer_value_for_js_first_q = $main_timer_from_first_question + $remaining_reading_time; // remaining_reading_time is negative here
        }
    } else { // 'main'
        $initial_timer_value_for_js_first_q = $main_timer_from_first_question - $elapsed_time;
    }
    $initial_timer_value_for_js_first_q = max(0, $initial_timer_value_for_js_first_q);
}


$question_text_first_display = str_replace('{PLAYER_NAME}', htmlspecialchars($current_player_data_for_first_display['name']), $first_question_to_display['text']);
if (strpos($question_text_first_display, '{RANDOM_PLAYER_NAME}') !== false) {
    $other_names_first_display = [];
    foreach ($players_initial_state as $idx => $p) {
        if ($p['active'] && $idx != $current_player_index_initial) $other_names_first_display[] = htmlspecialchars($p['name']);
    }
    $question_text_first_display = str_replace('{RANDOM_PLAYER_NAME}', !empty($other_names_first_display) ? $other_names_first_display[array_rand($other_names_first_display)] : '(інший гравець)', $question_text_first_display);
}

$style_info_first_display = $category_styles[$first_question_to_display['category']] ?? ($category_styles['Default'] ?? ['background' => 'linear-gradient(to right, #74ebd5, #ACB6E5)', 'icon_classes' => ['fas fa-question-circle'], 'icon_color' => 'rgba(255,255,255,0.1)', 'icon_opacity' => 0.1]);

// Determine next player name for initial display only
$active_indices_initial = array_filter(array_keys($players_initial_state), function($idx) use ($players_initial_state) {
    return $players_initial_state[$idx]['active'];
});
$next_player_name_display_initial = 'Нікого';
if (!empty($active_indices_initial)) {
    $current_pos_in_active = array_search($current_player_index_initial, $active_indices_initial);
    if ($current_pos_in_active !== false) {
        $next_pos_in_active = ($current_pos_in_active + 1) % count($active_indices_initial);
        $next_player_idx_val_initial = $active_indices_initial[$next_pos_in_active];
        $next_player_name_display_initial = htmlspecialchars($players_initial_state[$next_player_idx_val_initial]['name']);
    }
}
$max_rounds_setting = $game_config['general']['max_rounds'] ?? 5;

// These session variables are for JS to pick up. They won't be used by PHP game logic anymore.
unset($_SESSION['initial_timer_phase']);
unset($_SESSION['initial_timer_started_at']);
// initial_js_question_pool, all_questions_data_map, players, current_player_index etc. will be passed to JS
?>
<!DOCTYPE html>
<html lang="uk"><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Гра: Хід <?php echo htmlspecialchars($current_player_data_for_first_display['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script>
        window.INITIAL_GAME_STATE = {
            questionPool: <?php echo json_encode($initial_js_question_pool); ?>,
            allQuestionsDataMap: <?php echo json_encode($all_questions_data_map_for_js); ?>,
            players: <?php echo json_encode($players_initial_state); ?>,
            currentPlayerIndex: <?php echo json_encode($current_player_index_initial); ?>,
            currentRound: <?php echo json_encode($current_round_initial); ?>,
            gameConfig: <?php echo json_encode($game_config); ?>,
            categoryStyles: <?php echo json_encode($category_styles); ?>,
            
            // Timer related data for the *first* question, calculated by PHP
            initialEffectiveReadingDuration: <?php echo json_encode($js_effective_reading_duration_first_q); ?>,
            initialMainTimerDuration: <?php echo json_encode($main_timer_from_first_question); ?>,
            initialTimerValue: <?php echo json_encode($initial_timer_value_for_js_first_q); ?>,
            initialPhase: <?php echo json_encode($current_phase_for_js_first_q); ?>,
            serverTimeAtStart: <?php echo json_encode(time()); ?> // For JS to sync its timer if needed
        };
    </script>
</head><body>
    <div class="game-page">
        <div class="background-icons-container"></div>
        <div class="category-display">
            ID: <span id="q-id"><?php echo htmlspecialchars($first_question_to_display['id']); ?></span> | Категорія: <span id="q-category"><?php echo htmlspecialchars($first_question_to_display['category']); ?></span>
        </div>
        <div class="round-player-info">Раунд: <span id="round-num"><?php echo $current_round_initial; ?></span>/<?php echo $max_rounds_setting; ?><br>Гравців: <span id="active-players-count"><?php echo count(array_filter($players_initial_state, function($p){ return $p['active']; })); ?></span></div>
        
        <?php if ($js_effective_reading_duration_first_q > 0 || $main_timer_from_first_question > 0): ?>
        <div id="timer-container" class="timer-container timer-<?php echo $current_phase_for_js_first_q; ?>"><div id="timer-circle" class="timer-circle"></div></div>
        <?php else: ?>
        <div id="timer-container" class="timer-container" style="display:none;"><div id="timer-circle" class="timer-circle">0</div></div>
        <?php endif; ?>

        <div class="question-container">
            <div class="current-player-name" id="current-player-name-display"><?php echo htmlspecialchars($current_player_data_for_first_display['name']); ?></div>
            <div class="question-text" id="question-text-display"><?php echo nl2br($question_text_first_display); ?></div>
            <div class="deferred-messages" id="deferred-messages-display" style="display:none;"><strong>Активні ефекти:</strong><div id="deferred-messages-content"></div></div>
        </div>
        <div class="action-buttons">
            <button type="button" id="btn-completed" class="btn-done action-btn">Виконано! (Наст: <span id="next-player-btn-info"><?php echo $next_player_name_display_initial; ?></span>)</button>
            <button type="button" id="btn-skip" class="btn-skip action-btn">Пропустити (Залишилось: <span id="skips-left-display"><?php echo $current_player_data_for_first_display['skips_left']; ?></span>)</button>
            <button type="button" id="btn-go-back" class="btn-go-back action-btn" disabled>Попереднє питання</button>
            <button type="button" id="btn-quit" class="btn-quit action-btn">Вийти з гри</button>
        </div>
    </div>
    <script src="js/script.js"></script></body></html>