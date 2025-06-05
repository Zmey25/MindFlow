<?php
session_start();

// --- Перевірки стану гри на початку ---
if (!isset($_SESSION['game_started']) || $_SESSION['game_started'] !== true) {
    header('Location: index.php?new_game=true'); // Якщо гра не почата, налаштовуємо нову
    exit;
}
if (isset($_SESSION['game_over']) && $_SESSION['game_over'] === true) {
    header('Location: game_over.php'); // Якщо гра завершена, показуємо екран завершення
    exit;
}

// Завантаження даних (припускаємо, що вони вже в сесії з index.php або game_over.php для "грати знов")
$questions_data_map = $_SESSION['all_questions_data'] ?? []; // Це має бути асоціативний масив ID => QuestionData
$category_styles = json_decode(file_get_contents('data/category_styles.json'), true);

if (empty($questions_data_map) || empty($category_styles)) {
    $_SESSION['game_over'] = true;
    $_SESSION['game_over_message'] = "Помилка: Файли гри (питання/стилі) не завантажено належним чином.";
    header('Location: game_over.php');
    exit;
}

// --- Допоміжні функції --- (залишаються переважно ті ж, але з невеликими уточненнями)
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
    return null; // Немає інших активних гравців
}


function select_question() {
    // Переконатися, що all_questions_data існує і є асоціативним масивом
    if (!isset($_SESSION['all_questions_data']) || !is_array($_SESSION['all_questions_data'])) {
        // Критична помилка - дані питань відсутні або пошкоджені
        error_log("select_question: all_questions_data is missing or not an array.");
        return null; 
    }

    // Якщо пул доступних ID порожній, оновлюємо його
    if (!isset($_SESSION['available_question_ids']) || empty($_SESSION['available_question_ids'])) {
        $all_question_ids_from_map = array_keys($_SESSION['all_questions_data']);
        if (empty($all_question_ids_from_map)) {
            // Питань взагалі немає
            error_log("select_question: No question IDs available from all_questions_data map.");
            return null;
        }
        shuffle($all_question_ids_from_map);
        $_SESSION['available_question_ids'] = $all_question_ids_from_map;
        // error_log("Question pool refreshed. New pool size: " . count($_SESSION['available_question_ids']));
    }
    
    $question_id = array_pop($_SESSION['available_question_ids']);
    // error_log("Selected question ID: " . $question_id . ". Pool size after pop: " . count($_SESSION['available_question_ids']));


    if ($question_id === null || !isset($_SESSION['all_questions_data'][$question_id])) {
        // Це може статися, якщо available_question_ids був порожній, але не оновився,
        // або якщо ID з пулу немає в all_questions_data (малоймовірно при правильній ініціалізації)
        error_log("select_question: Failed to get a valid question_id or question_data for ID: " . var_export($question_id, true));
        // Спробуємо ще раз, можливо, пул оновиться
        if (empty($_SESSION['available_question_ids'])) { // Якщо пул справді порожній після pop
            $all_question_ids_from_map = array_keys($_SESSION['all_questions_data']);
            shuffle($all_question_ids_from_map);
            $_SESSION['available_question_ids'] = $all_question_ids_from_map;
            if (empty($_SESSION['available_question_ids'])) return null; // Якщо все ще порожній
            $question_id = array_pop($_SESSION['available_question_ids']);
             if ($question_id === null || !isset($_SESSION['all_questions_data'][$question_id])) return null;
        } else {
            return null; // Якщо не вдалося отримати питання
        }
    }
    
    // Зберігаємо саме дані питання, а не тільки ID, для поточного ходу
    $_SESSION['current_question_data'] = $_SESSION['all_questions_data'][$question_id];
    return $_SESSION['current_question_data'];
}

// --- Обробка POST-запитів ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $current_player_idx_on_action = $_SESSION['current_player_index']; // Зберігаємо індекс на момент дії
    $player_data = &$_SESSION['players'][$current_player_idx_on_action];

    $made_move = false; // Прапорець, чи була здійснена дія, що передає хід

    if ($action === 'completed') {
        $current_question_processed = $_SESSION['current_question_data'] ?? null; // Використовуємо збережене питання
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
            $_SESSION['current_question_data'] = null; // Скидаємо поточне питання, щоб select_question() вибрав нове
                                                       // для ЦЬОГО Ж гравця
        }
        // Хід не передається, гравець залишається той самий. Редирект оновить сторінку.
    } elseif ($action === 'quit') {
        $player_data['active'] = false;
        $made_move = true; // Ця дія також передає хід
    }

    if ($made_move) {
        $active_players_count = count(get_active_players_indices());
        if ($active_players_count < 2) { // Потрібно >= 2 для продовження гри
            $_SESSION['game_over'] = true;
            $_SESSION['game_over_message'] = $active_players_count === 1 ? "Залишився лише один переможець!" : "Гравців не залишилось! Гра завершена.";
        } else {
            $next_player_idx = get_next_active_player_index($current_player_idx_on_action);
            if ($next_player_idx === null) { // Не повинно статися, якщо active_players_count >= 2
                $_SESSION['game_over'] = true;
                $_SESSION['game_over_message'] = "Помилка: Не вдалося визначити наступного гравця.";
            } else {
                // Перевірка на початок нового раунду
                // Раунд збільшується, якщо наступний гравець має менший індекс, ніж той, хто ходив (і це не той самий гравець)
                // Або якщо наступний гравець - перший активний, а попередній був останнім активним
                $active_indices = get_active_players_indices();
                $pos_current_in_active = array_search($current_player_idx_on_action, $active_indices);
                $pos_next_in_active = array_search($next_player_idx, $active_indices);

                if ($pos_next_in_active !== false && $pos_current_in_active !== false && $pos_next_in_active < $pos_current_in_active) {
                    // Це відбувається, коли переходимо з, наприклад, останнього активного на першого активного
                    // Або якщо гравець вибув, і наступний активний має менший індекс
                     $_SESSION['current_round']++;
                } else if (count($active_indices) > 0 && $next_player_idx == $active_indices[0] && $current_player_idx_on_action == end($active_indices) && $current_player_idx_on_action != $next_player_idx) {
                    // Явніша перевірка для переходу з останнього на першого
                    $_SESSION['current_round']++;
                }


                $_SESSION['current_player_index'] = $next_player_idx;
                $_SESSION['current_question_data'] = null; // Дуже важливо: скидаємо питання для наступного гравця
            }
        }
    }
    // Перевірка кінця гри за раундами ПІСЛЯ можливого інкременту раунду
    if (isset($_SESSION['current_round']) && $_SESSION['current_round'] > 5) {
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Гра завершена! 5 кіл зіграно. Час для відпочинку!";
    }

    // Редирект після будь-якої дії для оновлення сторінки та стану
    // Якщо game_over встановлено, редирект буде на game_over.php на початку наступного завантаження game.php
    header('Location: game.php');
    exit;
}

// --- Підготовка даних для відображення (після всіх обробок POST) ---
// Якщо питання не вибране для поточного гравця (напр., перший хід або після 'skip' або після передачі ходу)
if (!isset($_SESSION['current_question_data']) || $_SESSION['current_question_data'] === null) {
    // error_log("Current question is null, selecting new one for player: " . $_SESSION['current_player_index']);
    $_SESSION['current_question_data'] = select_question();
    if ($_SESSION['current_question_data'] === null) {
        // Якщо select_question повернув null (немає питань або помилка)
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Помилка: Не вдалося завантажити питання для гри. Можливо, питання закінчились або файл пошкоджено.";
        error_log("Critical: select_question returned null. Ending game.");
        header('Location: game_over.php'); // Негайно перенаправляємо
        exit;
    }
}

$current_player_idx = $_SESSION['current_player_index'];
// Перевірка, чи існує гравець з таким індексом і чи він активний
if (!isset($_SESSION['players'][$current_player_idx]) || !$_SESSION['players'][$current_player_idx]['active']) {
    // Спроба знайти наступного активного, якщо поточний чомусь неактивний
    $fallback_idx = get_next_active_player_index($current_player_idx -1 < 0 ? count($_SESSION['players'])-1 : $current_player_idx -1); // Починаємо пошук з попереднього
    if ($fallback_idx !== null) {
        $_SESSION['current_player_index'] = $fallback_idx;
        $_SESSION['current_question_data'] = null; // Потрібно нове питання для нового гравця
        error_log("Fallback: current player was inactive or invalid. Switched to player index: " . $fallback_idx);
        header('Location: game.php'); // Перезавантажуємо для оновлення
        exit;
    } else {
        // Якщо немає активних гравців (мало б бути оброблено в POST)
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Немає активних гравців для продовження гри.";
        error_log("Critical: No active players found, current_player_index was invalid.");
        header('Location: game_over.php');
        exit;
    }
}
$current_player_data = &$_SESSION['players'][$current_player_idx]; // & для прямої модифікації deferred_effects


// Обробка відкладених ефектів (залишається схожою, але для $current_player_data)
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

$current_question = $_SESSION['current_question_data']; // Вже має бути встановлене

// Підготовка тексту питання (залишається схожою)
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
<!-- Решта HTML game.php залишається такою ж -->
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
             data-icon-classes='<?php echo json_encode($style_info['icon_classes'] ?? [$style_info['icon_class'] ?? 'fas fa-question-circle']); ?>'
             data-icon-color="<?php echo htmlspecialchars($style_info['icon_color']); ?>"
             data-icon-opacity="<?php echo htmlspecialchars($style_info['icon_opacity'] ?? 0.1); ?>">
        </div>
        <div class="category-display">
            Категорія: <?php echo htmlspecialchars($current_question['category']); ?>
        </div>
        <div class="round-player-info">
            Раунд: <?php echo $_SESSION['current_round']; ?>/5 <br>
            <?php echo htmlspecialchars($current_player_data['name']); ?> (Пропусків: <?php echo $current_player_data['skips_left']; ?>)
        </div>
        <div class="question-container">
            <div class="current-player-name">Хід гравця: <?php echo htmlspecialchars($current_player_data['name']); ?></div>
            <div class="question-text">
                <?php echo nl2br(htmlspecialchars($question_text)); ?>
            </div>
            <?php if (!empty($deferred_messages_to_display)): ?>
                <div class="deferred-messages">
                    <strong>Активні ефекти:</strong><br>
                    <?php foreach($deferred_messages_to_display as $msg): ?>
                        <span><?php echo htmlspecialchars($msg); ?></span><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <form method="POST" action="game.php" class="action-buttons">
            <button type="submit" name="action" value="skip" class="btn-skip" <?php echo ($current_player_data['skips_left'] <= 0) ? 'disabled' : ''; ?>>
                Пропустити (<?php echo $current_player_data['skips_left']; ?>)
            </button>
            <button type="submit" name="action" value="completed" class="btn-done">
                Виконано -> <?php echo htmlspecialchars($next_player_name_for_button); ?>
            </button>
            <button type="submit" name="action" value="quit" class="btn-quit" onclick="return confirm('Ви впевнені, що хочете вийти з гри? Це не можна буде скасувати.');">
                Вийти з гри
            </button>
        </form>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
