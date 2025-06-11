<?php
session_start();

// Constant for the reading timer duration
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

// Ensure necessary game data is loaded
$questions_data_map = $_SESSION['all_questions_data'] ?? [];
$category_styles = $_SESSION['category_styles'] ?? [];

if (empty($questions_data_map) || empty($category_styles) || !isset($_SESSION['game_question_pool'])) {
    $_SESSION['game_over'] = true;
    $_SESSION['game_over_message'] = "Помилка: Файли гри (питання/стилі) не завантажено належним чином.";
    header('Location: game_over.php');
    exit;
}

/**
 * Returns an array of indices of currently active players.
 * @return array
 */
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

/**
 * Determines the index of the next active player in the sequence.
 * @param int $current_index_in_session The session index of the current player.
 * @return int|null The index of the next active player, or null if none found.
 */
function get_next_active_player_index($current_index_in_session) {
    $all_players = $_SESSION['players'] ?? [];
    if (empty($all_players)) return null;

    $num_players = count($all_players);
    $next_idx = ($current_index_in_session + 1) % $num_players;
    $checked_count = 0; // To prevent infinite loop in case no active players

    while ($checked_count < $num_players) {
        if (isset($all_players[$next_idx]['active']) && $all_players[$next_idx]['active']) {
            return $next_idx;
        }
        $next_idx = ($next_idx + 1) % $num_players;
        $checked_count++;
    }
    return null; // No active players found
}

/**
 * Selects the next question from the pool and stores it in session.
 * Stores current question and player as "last" before selecting new.
 * @return array|null The selected question data, or null if no questions left.
 */
function select_question() {
    global $questions_data_map;

    if (!isset($_SESSION['game_question_pool']) || empty($_SESSION['game_question_pool'])) {
        return null;
    }
    
    // Store current question data and player index before fetching a new one
    // This allows for a "go back" feature
    $_SESSION['last_displayed_question_data'] = $_SESSION['current_question_data'] ?? null;
    $_SESSION['last_player_index'] = $_SESSION['current_player_index'] ?? null;

    $question_id = array_shift($_SESSION['game_question_pool']);
    if ($question_id === null || !isset($questions_data_map[$question_id])) {
        return null;
    }

    $_SESSION['current_question_data'] = $questions_data_map[$question_id];
    
    // Reset timer for the new question
    $_SESSION['timer_phase'] = 'reading';
    $_SESSION['timer_started_at'] = time();

    return $_SESSION['current_question_data'];
}


// Handle POST requests for player actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $current_player_idx_on_action = $_SESSION['current_player_index'];
    $player_data = &$_SESSION['players'][$current_player_idx_on_action];

    $made_move = false; // Flag to determine if player action implies a turn change

    if ($action === 'completed') {
        $current_question_processed = $_SESSION['current_question_data'] ?? null;
        if ($current_question_processed) {
            // Apply bonus skip if question specifies it
            if (isset($current_question_processed['bonus_skip_on_complete']) && $current_question_processed['bonus_skip_on_complete'] === true) {
                $player_data['skips_left']++;
            }
            // Add deferred effects if question specifies it
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
        // Allow skip only if skips are available
        if ($player_data['skips_left'] > 0) {
            $player_data['skips_left']--;
            $made_move = true;
        }
    } elseif ($action === 'quit') {
        // Mark player as inactive and proceed
        $player_data['active'] = false;
        $made_move = true;
    } elseif ($action === 'go_back') {
        // Allow going back only once per turn
        if ($_SESSION['can_go_back'] && $_SESSION['last_displayed_question_data'] !== null && $_SESSION['last_player_index'] !== null) {
            $_SESSION['current_question_data'] = $_SESSION['last_displayed_question_data'];
            $_SESSION['current_player_index'] = $_SESSION['last_player_index'];
            $_SESSION['can_go_back'] = false; // Disable go back after use
            
            // Reset timer for the restored question
            $_SESSION['timer_phase'] = 'reading';
            $_SESSION['timer_started_at'] = time();

            header('Location: game.php'); // Redirect to show previous question
            exit;
        }
    }

    // Logic to proceed to next turn if a player action was made
    if ($made_move) {
        $_SESSION['current_question_data'] = null; // Clear current question to force selection of new one
        $_SESSION['can_go_back'] = true; // Enable go back for the next turn

        $active_players_count = count(get_active_players_indices());

        if ($active_players_count < 2) {
            $_SESSION['game_over'] = true;
            $_SESSION['game_over_message'] = $active_players_count === 1 ? "Залишився лише один переможець!" : "Гравців не залишилось! Гра завершена.";
        } else {
            $next_player_idx = get_next_active_player_index($current_player_idx_on_action);
            if ($next_player_idx === null) {
                // This should ideally not happen if active_players_count is > 0
                $_SESSION['game_over'] = true;
                $_SESSION['game_over_message'] = "Помилка: Не вдалося визначити наступного гравця.";
            } else {
                 // Check for round completion
                 $active_indices = get_active_players_indices();
                
                 // Check if the next player is the first active player AND
                 // if the current player was the last active player, or if the index wraps around.
                 // This handles scenarios where players quit, making the active indices non-sequential.
                 $current_is_last_active = ($current_player_idx_on_action == end($active_indices));
                 $next_is_first_active = ($next_player_idx == $active_indices[0]);
                 
                 // If the next player is the first active player, and we weren't already at the start,
                 // increment round. This prevents incrementing round if there's only one player left
                 // and they are the first and last active.
                 if ($next_is_first_active && ($current_player_idx_on_action !== $next_player_idx || count($active_indices) === 1)) {
                     $_SESSION['current_round']++;
                 }

                $_SESSION['current_player_index'] = $next_player_idx;
            }
        }
    }

    // Check for max rounds (e.g., 5 rounds)
    if (isset($_SESSION['current_round']) && $_SESSION['current_round'] > 5) {
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Гра завершена! 5 кіл зіграно. Час для відпочинку!";
    }

    header('Location: game.php'); // Redirect to prevent re-submission on refresh
    exit;
}

// If no current question data or it's null, select a new one
if (!isset($_SESSION['current_question_data']) || $_SESSION['current_question_data'] === null) {
    $_SESSION['current_question_data'] = select_question();
    if ($_SESSION['current_question_data'] === null) {
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Питання закінчились! Гра завершена.";
        header('Location: game_over.php');
        exit;
    }
}

// Ensure current player is active. If not, find next active.
$current_player_idx = $_SESSION['current_player_index'];
if (!isset($_SESSION['players'][$current_player_idx]) || !$_SESSION['players'][$current_player_idx]['active']) {
    // If current player is inactive or doesn't exist, try to find the next active player starting from previous
    // This handles cases where the current player quits and we need to immediately switch.
    $fallback_idx = get_next_active_player_index($current_player_idx - 1 < 0 ? count($_SESSION['players']) - 1 : $current_player_idx - 1);
    if ($fallback_idx !== null) {
        $_SESSION['current_player_index'] = $fallback_idx;
        $_SESSION['current_question_data'] = null; // Force new question for the new player
        
        // Reset timer for the newly selected player/question
        $_SESSION['timer_phase'] = 'reading';
        $_SESSION['timer_started_at'] = time();

        header('Location: game.php');
        exit;
    } else {
        // No active players left
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Немає активних гравців для продовження гри.";
        header('Location: game_over.php');
        exit;
    }
}
$current_player_data = &$_SESSION['players'][$current_player_idx];


// Process deferred effects for the current player
$deferred_messages_to_display = [];
if (!empty($current_player_data['deferred_effects'])) {
    $active_effects = [];
    foreach ($current_player_data['deferred_effects'] as $effect) {
        if (isset($effect['turns_left']) && $effect['turns_left'] > 0) {
            $text = str_replace('{TURNS_LEFT}', $effect['turns_left'], $effect['template']);
            $text = str_replace('{PLAYER_NAME}', htmlspecialchars($current_player_data['name']), $text);
            $deferred_messages_to_display[] = $text;

            $effect['turns_left']--; // Decrement turns left
            if ($effect['turns_left'] > 0) {
                $active_effects[] = $effect; // Keep effect if still active
            }
        }
    }
    $current_player_data['deferred_effects'] = $active_effects; // Update deferred effects
}

// Prepare question text with dynamic placeholders
$current_question = $_SESSION['current_question_data'];
$question_text = $current_question['text'];
$question_text = str_replace('{PLAYER_NAME}', htmlspecialchars($current_player_data['name']), $question_text);

// Handle {RANDOM_PLAYER_NAME} placeholder
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
        // Fallback if no other active players
        $question_text = str_replace('{RANDOM_PLAYER_NAME}', '(інший гравець)', $question_text);
    }
}

// Get style info for the current question's category
$style_info = $category_styles[$current_question['category']] ?? $category_styles['Default'];

// Prepare next player name for button text
$next_player_for_button_idx = get_next_active_player_index($current_player_idx);
$next_player_name_for_button = ($next_player_for_button_idx !== null && isset($_SESSION['players'][$next_player_for_button_idx])) ? $_SESSION['players'][$next_player_for_button_idx]['name'] : 'Нікого';

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
        // Pass game data to JavaScript for dynamic styling and timer
        window.GAME_DATA = {
            backgroundGradient: <?php echo json_encode($style_info['background']); ?>,
            iconClasses: <?php echo json_encode($style_info['icon_classes'] ?? ['fas fa-question-circle']); ?>,
            iconColor: <?php echo json_encode($style_info['icon_color']); ?>,
            iconOpacity: <?php echo json_encode($style_info['icon_opacity'] ?? 0.1); ?>,
            currentQuestionTimer: <?php echo json_encode($current_question['timer'] ?? null); ?>, // Pass timer value if exists
            readingTimerDuration: <?php echo READING_TIMER_DURATION; ?>, // Pass reading timer duration
            timerPhase: <?php echo json_encode($_SESSION['timer_phase'] ?? 'reading'); ?>, // Current timer phase
            timerStartedAt: <?php echo json_encode($_SESSION['timer_started_at'] ?? time()); ?> // Timestamp when timer started
        };
    </script>
</head>
<body>
    <div class="game-page">
        <div class="background-icons-container"></div>
        
        <div class="category-display">
            Категорія: <?php echo htmlspecialchars($current_question['category']); ?> (ID: <?php echo htmlspecialchars($current_question['id']); ?>)
        </div>
        <div class="round-player-info">
            Раунд: <?php echo $_SESSION['current_round']; ?><br>
            Гравців: <?php echo count(get_active_players_indices()); ?>
        </div>

        <?php if (isset($current_question['timer']) && $current_question['timer'] !== null): ?>
        <div id="timer-container" class="timer-container">
            <div id="timer-circle" class="timer-circle"></div>
        </div>
        <?php endif; ?>

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
                <button type="submit" name="action" value="completed" class="btn-done">
                    Виконано! (Наступний: <?php echo $next_player_name_for_button; ?>)
                </button>
            </form>
            <form method="POST" action="game.php" style="width: 100%;">
                <button type="submit" name="action" value="skip" class="btn-skip" <?php echo ($current_player_data['skips_left'] <= 0) ? 'disabled' : ''; ?>>
                    Пропустити (Залишилось: <?php echo $current_player_data['skips_left']; ?>)
                </button>
            </form>
            <form method="POST" action="game.php" style="width: 100%;">
                <button type="submit" name="action" value="go_back" class="btn-go-back" <?php echo ($_SESSION['can_go_back'] ?? false) ? '' : 'disabled'; ?>>
                    Попереднє питання (1 раз)
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
