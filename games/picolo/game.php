<?php
session_start();

if (!isset($_SESSION['game_started']) || $_SESSION['game_started'] !== true || $_SESSION['game_over'] === true) {
    header('Location: index.php');
    exit;
}

$questions_data = $_SESSION['all_questions_data'] ?? [];
$category_styles = json_decode(file_get_contents('data/category_styles.json'), true);

if (empty($questions_data) || empty($category_styles)) {
    // Критична помилка, питання або стилі не завантажені
    $_SESSION['game_over'] = true;
    $_SESSION['game_over_message'] = "Помилка: Файли гри (питання/стилі) не знайдено або пошкоджено.";
    header('Location: game_over.php');
    exit;
}

// Допоміжні функції
function get_active_players_indices() {
    $active_indices = [];
    foreach ($_SESSION['players'] as $index => $player) {
        if ($player['active']) {
            $active_indices[] = $index;
        }
    }
    return $active_indices;
}

function get_next_active_player_index($current_index) {
    $active_indices = get_active_players_indices();
    if (empty($active_indices)) return null;

    $current_pos_in_active = array_search($current_index, $active_indices);
    if ($current_pos_in_active === false) { // Якщо поточний гравець став неактивним
        // Шукаємо наступного активного від поточного індекса
        $next_idx = ($current_index + 1) % count($_SESSION['players']);
        while(!$_SESSION['players'][$next_idx]['active'] && $next_idx != $current_index) {
            $next_idx = ($next_idx + 1) % count($_SESSION['players']);
        }
        return $_SESSION['players'][$next_idx]['active'] ? $next_idx : null; // Якщо всі неактивні крім одного (що не має бути)
    }
    
    $next_active_pos = ($current_pos_in_active + 1) % count($active_indices);
    return $active_indices[$next_active_pos];
}

function select_question() {
    if (empty($_SESSION['available_question_ids'])) {
        // Якщо пул закінчився, перемішуємо всі ID знову
        $all_q_ids = array_keys($_SESSION['all_questions_data']);
        shuffle($all_q_ids);
        $_SESSION['available_question_ids'] = $all_q_ids;
    }
    // Беремо останній ID з перемішаного масиву (або перший, якщо array_shift)
    $question_id = array_pop($_SESSION['available_question_ids']);
    $_SESSION['current_question_id'] = $question_id;
    return $_SESSION['all_questions_data'][$question_id] ?? null;
}

// --- Обробка дій гравця ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $current_player_idx = $_SESSION['current_player_index'];
    $player_data = &$_SESSION['players'][$current_player_idx]; // Reference for direct modification

    if ($action === 'completed') {
        $current_question = $_SESSION['all_questions_data'][$_SESSION['current_question_id']] ?? null;
        if ($current_question) {
            if (isset($current_question['bonus_skip_on_complete']) && $current_question['bonus_skip_on_complete'] === true) {
                $player_data['skips_left']++;
            }
            if (!empty($current_question['deferred_text_template']) && !empty($current_question['deferred_turns_player']) && $current_question['deferred_turns_player'] > 0) {
                $player_data['deferred_effects'][] = [
                    'template' => $current_question['deferred_text_template'],
                    'turns_left' => (int)$current_question['deferred_turns_player'],
                    'question_id' => $current_question['id'] 
                ];
            }
        }
        // Перехід ходу
        $next_player_idx = get_next_active_player_index($current_player_idx);
        if ($next_player_idx === null) { // Немає активних гравців
            $_SESSION['game_over'] = true;
            $_SESSION['game_over_message'] = "Залишився лише один гравець або гравців не залишилось!";
        } else {
            if ($next_player_idx < $_SESSION['current_player_index'] && count(get_active_players_indices()) > 1) { // Перейшли на початок кола
                 $_SESSION['current_round']++;
            }
            $_SESSION['current_player_index'] = $next_player_idx;
        }

    } elseif ($action === 'skip') {
        if ($player_data['skips_left'] > 0) {
            $player_data['skips_left']--;
            // Нове питання для того ж гравця, хід не передається
            $_SESSION['current_question_data'] = select_question();
        }
        // Якщо немає пропусків, нічого не відбувається, гравець має обрати іншу дію
    } elseif ($action === 'quit') {
        $player_data['active'] = false;
        $active_players_count = count(get_active_players_indices());
        if ($active_players_count < 2) {
            $_SESSION['game_over'] = true;
            $_SESSION['game_over_message'] = $active_players_count === 1 ? "Залишився лише один гравець!" : "Гравців не залишилось!";
        } else {
            // Перехід ходу, якщо гра не закінчена
            $next_player_idx = get_next_active_player_index($current_player_idx); // отримаємо наступного від того, хто вийшов
             if ($next_player_idx === null) {
                 $_SESSION['game_over'] = true; // Малоймовірно, але перевірка
                 $_SESSION['game_over_message'] = "Не вдалося знайти наступного гравця.";
             } else {
                // Якщо той, хто вийшов, був останнім у списку і наступний - перший, це може інкрементувати раунд
                // Логіка інкрементації раунду вже є в 'completed', але тут також потрібно
                // Оскільки той, хто вийшов, вже неактивний, get_next_active_player_index знайде першого активного
                // Якщо новий current_player_index менший за старий (індекс гравця, що вийшов), і активних гравців > 1, тоді коло завершене
                // Важливо: тут $current_player_idx - це індекс того, хто ВИЙШОВ.
                // Наступний гравець вже визначений. Якщо $next_player_idx < $current_player_idx (і це не перший хід гри) - це нове коло.
                // Однак, коректніше інкрементувати раунд, коли *поточний* (новий) індекс менший за *попередній* (старий активний) індекс.
                // Цю логіку вже має get_next_active_player_index + блок "completed"
                $_SESSION['current_player_index'] = $next_player_idx;
             }
        }
    }
    // Після будь-якої дії, що змінює стан, перевіряємо, чи не час завершувати гру
    if ($_SESSION['current_round'] > 5) {
        $_SESSION['game_over'] = true;
        $_SESSION['game_over_message'] = "Гра завершена! 5 кіл зіграно. Час для відпочинку!";
    }
    if ($_SESSION['game_over']) {
        header('Location: game_over.php');
        exit;
    }
    // Якщо дія була 'skip', сторінка просто перезавантажиться з новим питанням для того ж гравця.
    // Для 'completed' та 'quit' (якщо гра не закінчилась), сторінка перезавантажиться для наступного гравця.
    header('Location: game.php'); // Перезавантаження для оновлення стану
    exit;
}

// --- Підготовка даних для відображення ---
$current_player_idx = $_SESSION['current_player_index'];
$current_player_data = &$_SESSION['players'][$current_player_idx];

// Обробка відкладених ефектів для поточного гравця
$deferred_messages_to_display = [];
if (!empty($current_player_data['deferred_effects'])) {
    $active_effects = [];
    foreach ($current_player_data['deferred_effects'] as $effect) {
        if ($effect['turns_left'] > 0) {
            $text = str_replace('{TURNS_LEFT}', $effect['turns_left'], $effect['template']);
            $text = str_replace('{PLAYER_NAME}', htmlspecialchars($current_player_data['name']), $text); // Якщо в шаблоні є ім'я
            $deferred_messages_to_display[] = $text;
            
            $effect['turns_left']--; // Зменшуємо лічильник тільки для активних ефектів поточного гравця
            if ($effect['turns_left'] > 0) { // Якщо ефект ще активний, зберігаємо його
                $active_effects[] = $effect;
            }
        }
    }
    $current_player_data['deferred_effects'] = $active_effects; // Оновлюємо список активних ефектів
}


// Вибір питання, якщо воно ще не встановлено для поточного ходу
if (!isset($_SESSION['current_question_data']) || $_SESSION['current_question_data'] === null) {
    $_SESSION['current_question_data'] = select_question();
}
$current_question = $_SESSION['current_question_data'];

if (!$current_question) { // Якщо питання не вдалося вибрати (напр. порожній JSON)
    $_SESSION['game_over'] = true;
    $_SESSION['game_over_message'] = "Помилка: Не вдалося завантажити питання для гри.";
    header('Location: game_over.php');
    exit;
}

// Підготовка тексту питання
$question_text = $current_question['text'];
$question_text = str_replace('{PLAYER_NAME}', htmlspecialchars($current_player_data['name']), $question_text);

// Обробка {RANDOM_PLAYER_NAME}
if (strpos($question_text, '{RANDOM_PLAYER_NAME}') !== false) {
    $other_active_players = [];
    foreach ($_SESSION['players'] as $idx => $p_data) {
        if ($p_data['active'] && $idx != $current_player_idx) {
            $other_active_players[] = htmlspecialchars($p_data['name']);
        }
    }
    if (!empty($other_active_players)) {
        $random_other_player_name = $other_active_players[array_rand($other_active_players)];
        $question_text = str_replace('{RANDOM_PLAYER_NAME}', $random_other_player_name, $question_text);
    } else {
        // Якщо немає інших активних гравців, замінюємо на щось нейтральне або прибираємо частину
        $question_text = str_replace('{RANDOM_PLAYER_NAME}', '(інший гравець)', $question_text);
    }
}

$style_info = $category_styles[$current_question['category']] ?? $category_styles['Default'];

$next_player_real_idx = get_next_active_player_index($current_player_idx);
$next_player_name = ($next_player_real_idx !== null) ? $_SESSION['players'][$next_player_real_idx]['name'] : 'Нікого';

// Важливо: після POST-запиту, який не був skip, питання має бути вибрано заново для НАСТУПНОГО гравця.
// Якщо це не POST або був skip, то $_SESSION['current_question_data'] вже містить питання для поточного.
// Ця логіка вже врахована вище: select_question() викликається, якщо $_SESSION['current_question_data'] порожнє.
// Після 'completed' або 'quit', ми робимо редирект, і на наступному завантаженні, якщо
// $_SESSION['current_question_data'] не було очищено (а воно не очищається), то буде те саме питання.
// Отже, після успішного 'completed' чи 'quit' (якщо гра триває), ТРЕБА скидати поточне питання.
// Це робиться так: перед редиректом після 'completed' або 'quit', якщо хід передається:
// $_SESSION['current_question_data'] = null; (Це було пропущено, додаю в обробку POST)

// Коригування: Скидання поточного питання ПІСЛЯ успішного виконання чи виходу
// Це вже зроблено неявно, бо select_question() викликається при кожному завантаженні game.php
// якщо $_SESSION['current_question_data'] не існує.
// Краще скидати його явно, щоб новий гравець отримав нове питання.
// Додано в POST-обробку: якщо хід передається, $_SESSION['current_question_data'] = null;
// Насправді, краще не скидати, а дозволити select_question() зробити свою роботу на наступному завантаженні.
// Якщо був 'skip', то select_question() вже оновив $_SESSION['current_question_data'].
// Якщо був 'completed' або 'quit', то після редиректу, на початку скрипта game.php,
// якщо `$_SESSION['current_question_data']` не скинуте, то гравець, що отримав хід, побачить те саме питання.
// Тому, після `completed` або `quit` (що веде до зміни гравця), `$_SESSION['current_question_data'] = null;` має бути перед редиректом.
// Однак, простіше, якщо select_question() викликається завжди, якщо це не skip.
// Давайте зробимо так: якщо дія НЕ 'skip', то $_SESSION['current_question_data'] = null; перед редиректом
// Це вже реалізовано тим, що після POST-запиту з 'completed' або 'quit' відбувається редирект,
// а при наступному завантаженні `game.php` блок `if (!isset($_SESSION['current_question_data']))`
// знову викличе `select_question()`. Так що поточна логіка має працювати.

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
        <div class="background-icons-container">
            <!-- Іконки будуть додані JS -->
        </div>
        
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
                Виконано -> <?php echo htmlspecialchars($next_player_name); ?>
            </button>
            <button type="submit" name="action" value="quit" class="btn-quit" onclick="return confirm('Ви впевнені, що хочете вийти з гри? Це не можна буде скасувати.');">
                Вийти з гри
            </button>
        </form>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
