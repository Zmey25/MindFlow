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

function select_question($is_reroll = false) { // Додамо флаг is_reroll
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

    // Зберігаємо стан в історію, ТІЛЬКИ якщо це не рерол в межах того самого гравця.
    // Якщо це рерол, ми хочемо, щоб "go_back" повернув до стану *перед* першим питанням цього ходу.
    // Отже, при реролі ми не додаємо новий запис, а оновлюємо останній, АБО краще -
    // при реролі видаляємо старий і додаємо новий, але з оновленим player_state.

    // Нова логіка: `select_question` більше не відповідає за історію напряму.
    // Історія буде оновлюватися в основному потоці.

    return $_SESSION['current_question_data'];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $player_idx_for_action = $_SESSION['current_player_index'];
    $question_acted_upon = $_SESSION['current_question_data']; // Question visible when action was taken

    if ($action === 'go_back') {
        if (!empty($_SESSION['game_history'])) {
            // Поточний стан (який ми бачимо) - це останній запис в історії.
            // Ми хочемо повернутися до стану ПЕРЕД ним.
            array_pop($_SESSION['game_history']); // Видаляємо поточний видимий стан з історії

            if (!empty($_SESSION['game_history'])) {
                $restored_state = end($_SESSION['game_history']); // Це стан, до якого ми повертаємося

                // Питання, яке було на екрані ПЕРЕД натисканням "go_back" ($question_acted_upon),
                // має бути повернуте в пул, оскільки ми "скасовуємо" його перегляд.
                if (isset($question_acted_upon['id'])) {
                     array_unshift($_SESSION['game_question_pool'], $question_acted_upon['id']);
                }

                $_SESSION['current_question_data'] = $restored_state['question'];
                $_SESSION['current_player_index'] = $restored_state['player_index'];
                $_SESSION['current_round'] = $restored_state['round'];
                $_SESSION['players'] = $restored_state['players_state']; // Відновлює skips_left та ефекти
                
                $question_has_main_timer = (($_SESSION['current_question_data']['timer'] ?? 0) > 0);
                if ($question_has_main_timer && $reading_timer_duration_setting > 0) {
                    $_SESSION['timer_phase'] = 'reading';
                } else {
                    $_SESSION['timer_phase'] = 'main';
                }
                $_SESSION['timer_started_at'] = time();
            } else {
                // Історія порожня після видалення поточного стану.
                // Це означає, що ми були на першому питанні гри.
                // Повертаємо питання в пул. Далі логіка завантаження сторінки має або почати гру заново,
                // або показати помилку, якщо пул порожній.
                 if (isset($question_acted_upon['id'])) {
                     array_unshift($_SESSION['game_question_pool'], $question_acted_upon['id']);
                 }
                $_SESSION['current_question_data'] = null; // Змусить select_question викликатись
                // Можливо, треба скинути гравця на 0, раунд на 1, якщо це був "вихід" з першого питання.
                // Для простоти, просто дозволимо select_question() спробувати знайти питання.
            }
        }
    } else { // completed, skip, quit
        $player_data_ref = &$_SESSION['players'][$player_idx_for_action];

        // Для 'skip', ми спочатку змінюємо стан гравця (skips_left), 
        // а потім цей змінений стан буде збережено в історію разом з новим питанням.
        // Для 'completed' та 'quit', ефекти і стан гравця змінюються, і цей новий стан
        // буде актуальним для наступного гравця.

        if ($action === 'skip') {
            if ($player_data_ref['skips_left'] > 0) {
                $player_data_ref['skips_left']--; // Зменшуємо скіпи
                // Поточне питання $question_acted_upon "використане" реролом, не повертається в пул.
                // Попередній запис в історії (для $question_acted_upon) має бути видалений,
                // бо ми його замінюємо новим питанням з оновленим станом гравця.
                if (!empty($_SESSION['game_history'])) {
                    array_pop($_SESSION['game_history']);
                }
                $_SESSION['current_question_data'] = null; // Змусить select_question() взяти нове питання
                                                       // і потім новий стан буде додано в історію.
            }
            // Якщо скіпів немає, нічого не відбувається, сторінка просто перезавантажиться.
        } else { // completed, quit
            // Обробка ефектів ТІЛЬКИ для дій, що завершують хід
            if (!empty($player_data_ref['deferred_effects'])) {
                $active_effects = [];
                foreach ($player_data_ref['deferred_effects'] as $effect) {
                    $effect['turns_left']--;
                    if ($effect['turns_left'] > 0) $active_effects[] = $effect;
                }
                $player_data_ref['deferred_effects'] = $active_effects;
            }

            if ($action === 'completed') {
                $q = $question_acted_upon;
                if ($q['bonus_skip_on_complete'] ?? false) $player_data_ref['skips_left']++;
                if (!empty($q['deferred_text_template']) && !empty($q['deferred_turns_player'])) {
                    $player_data_ref['deferred_effects'][] = ['template' => $q['deferred_text_template'], 'turns_left' => (int)$q['deferred_turns_player'], 'question_id' => $q['id']];
                }
            } elseif ($action === 'quit') {
                $player_data_ref['active'] = false;
            }
            $_SESSION['current_question_data'] = null; // Питання оброблене, готуємося до наступного гравця
        }
        
        // Перехід до наступного гравця або завершення гри (ТІЛЬКИ для 'completed' або 'quit')
        if ($action === 'completed' || $action === 'quit') {
            $active_players_count = count(get_active_players_indices());
            if ($active_players_count < 2) {
                $_SESSION['game_over'] = true;
                $_SESSION['game_over_message'] = $active_players_count === 1 ? "Залишився переможець!" : "Гравців не залишилось!";
            } else {
                $next_player_idx = get_next_active_player_index($player_idx_for_action);
                if ($next_player_idx === null) { // Малоймовірно, якщо active_players_count >= 2
                    $_SESSION['game_over'] = true; $_SESSION['game_over_message'] = "Не вдалося знайти наступного гравця.";
                } else {
                    $active_indices = get_active_players_indices();
                    if ( ($next_player_idx == ($active_indices[0] ?? null)) && ($player_idx_for_action != $next_player_idx || count($active_indices) == 1) ) {
                       if(count($active_indices) > 1 || $_SESSION['current_player_index'] != $next_player_idx ) { // Використовуємо $_SESSION['current_player_index'] для порівняння перед оновленням
                            $_SESSION['current_round']++;
                       } else if (count($active_indices) == 1 && $player_idx_for_action == $next_player_idx) {
                           $_SESSION['current_round']++;
                       }
                    }
                    $_SESSION['current_player_index'] = $next_player_idx;
                }
            }
        }
        // Якщо дія була 'skip', гравець залишається той самий, раунд той самий.
        // $_SESSION['current_question_data'] вже null, тому на наступному завантаженні буде обрано нове питання.
    } 

    if (!($_SESSION['game_over'] ?? false) && isset($_SESSION['current_round']) && $_SESSION['current_round'] > $max_rounds_setting) {
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = $max_rounds_setting . " кіл зіграно. Гра завершена!";
    }

    header('Location: game.php');
    exit;
}


// --- Page Load Logic ---

// Якщо гра завершена, перенаправляємо
if ($_SESSION['game_over'] ?? false) {
    header('Location: game_over.php');
    exit;
}

// Перевірка активності поточного гравця та наявності питання
$needs_new_question = false;
if (empty($_SESSION['current_question_data'])) {
    $needs_new_question = true;
} else {
    // Якщо поточний гравець став неактивним (наприклад, вийшов, але гра ще не завершилась для інших)
    $cp_idx = $_SESSION['current_player_index'];
    if (!isset($_SESSION['players'][$cp_idx]['active']) || !$_SESSION['players'][$cp_idx]['active']) {
        $next_active_idx = get_next_active_player_index($cp_idx -1 < 0 ? count($_SESSION['players'])-1 : $cp_idx-1); // шукаємо наступного з попереднього
        if ($next_active_idx !== null) {
            $_SESSION['current_player_index'] = $next_active_idx;
            $needs_new_question = true; 
        } else {
            $_SESSION['game_over'] = true; // Немає інших активних гравців
            $_SESSION['game_over_message'] = "Гравців не залишилось для продовження гри.";
            header('Location: game_over.php');
            exit;
        }
    }
}

if ($needs_new_question) {
    if (select_question() === null) { // select_question() ВИЛУЧАЄ питання з пулу
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Питання закінчились!";
        header('Location: game_over.php');
        exit;
    }
    // Після вибору нового питання, зберігаємо поточний стан в історію
    // Це фіксує "гравець Х отримав питання Y зі Z скіпами та активними ефектами Е"
    if (count($_SESSION['game_history']) >= 20) {
        array_shift($_SESSION['game_history']);
    }
    array_push($_SESSION['game_history'], [
        'question' => $_SESSION['current_question_data'],
        'player_index' => $_SESSION['current_player_index'],
        'round' => $_SESSION['current_round'],
        'players_state' => $_SESSION['players'] // Зберігаємо поточний стан гравців (включаючи skips_left)
    ]);
    // Якщо це був редирект після POST, то цей код виконається і новий стан буде в історії.
    // Якщо це був POST-redirect-GET, то header('Location: game.php') викличе цей блок.
}


// На цьому етапі ми точно маємо активного гравця і питання (якщо гра не завершилась)
$current_player_data = $_SESSION['players'][$_SESSION['current_player_index']];
$current_question = $_SESSION['current_question_data'];

// ... (решта коду для відображення, таймерів, HTML - без змін з попередньої версії) ...
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
        if ($p['active'] && $idx != $_SESSION['current_player_index']) $other_names[] = htmlspecialchars($p['name']);
    }
    $question_text = str_replace('{RANDOM_PLAYER_NAME}', !empty($other_names) ? $other_names[array_rand($other_names)] : '(інший гравець)', $question_text);
}

$style_info = $category_styles[$current_question['category']] ?? ($category_styles['Default'] ?? ['background' => 'linear-gradient(to right, #74ebd5, #ACB6E5)', 'icon_classes' => ['fas fa-question-circle'], 'icon_color' => 'rgba(255,255,255,0.1)', 'icon_opacity' => 0.1]);
$next_player_name_display = 'Нікого';
$next_player_idx_val = get_next_active_player_index($_SESSION['current_player_index']);
if ($next_player_idx_val !== null) $next_player_name_display = $_SESSION['players'][$next_player_idx_val]['name'];


$main_timer_from_question = (int)($current_question['timer'] ?? 0);
$js_effective_reading_duration = 0;
if ($main_timer_from_question > 0 && $reading_timer_duration_setting > 0) {
    $js_effective_reading_duration = $reading_timer_duration_setting;
}

$initial_timer_value_for_js = 0;
$current_phase_for_js = $_SESSION['timer_phase'];

if ($js_effective_reading_duration > 0 || $main_timer_from_question > 0) {
    $elapsed_time = time() - ($_SESSION['timer_started_at'] ?? time());
    if ($current_phase_for_js === 'reading') {
        $remaining_reading_time = $js_effective_reading_duration - $elapsed_time;
        if ($remaining_reading_time > 0) {
            $initial_timer_value_for_js = $remaining_reading_time;
        } else {
            $current_phase_for_js = 'main';
            $initial_timer_value_for_js = $main_timer_from_question + $remaining_reading_time;
        }
    } else {
        $initial_timer_value_for_js = $main_timer_from_question - $elapsed_time;
    }
    $initial_timer_value_for_js = max(0, $initial_timer_value_for_js);
} else {
    $current_phase_for_js = 'main';
    $initial_timer_value_for_js = 0;
}

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
            <form method="POST" action="game.php" style="width: 100%;"><button type="submit" name="action" value="go_back" class="btn-go-back action-btn" <?php echo (empty($_SESSION['game_history']) || count($_SESSION['game_history']) < 2) ? 'disabled' : ''; ?>>Попереднє питання</button></form>
            <form method="POST" action="game.php" style="width: 100%;"><button type="submit" name="action" value="quit" class="btn-quit action-btn">Вийти з гри</button></form>
        </div>
    </div>
    <script src="js/script.js"></script></body></html>
