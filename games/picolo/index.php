<?php
session_start();

// Якщо гра вже йде, перенаправляємо на game.php
if (isset($_SESSION['game_started']) && $_SESSION['game_started'] === true && !(isset($_GET['new_game']) && $_GET['new_game'] === 'true')) {
    header('Location: game.php');
    exit;
}

// Скидання сесії для нової гри
$_SESSION = []; // Очищення всіх даних сесії
// session_destroy(); // Можна використати, але тоді треба знову session_start()
// session_start(); // Якщо використовували session_destroy()

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $players_input = isset($_POST['players']) ? (array)$_POST['players'] : [];
    $players = [];
    foreach ($players_input as $name) {
        $trimmed_name = trim($name);
        if (!empty($trimmed_name)) {
            $players[] = htmlspecialchars($trimmed_name); // XSS protection
        }
    }

    if (count($players) < 2) {
        $error = 'Будь ласка, введіть імена щонайменше двох гравців.';
    } else {
        $_SESSION['initial_player_names'] = $players; // Зберігаємо для "Грати знов"
        
        $game_players = [];
        foreach ($players as $name) {
            $game_players[] = [
                'name' => $name,
                'skips_left' => 1,
                'active' => true,
                'deferred_effects' => [] // Для відкладених завдань/бонусів
            ];
        }
        $_SESSION['players'] = $game_players;
        $_SESSION['current_player_index'] = 0;
        $_SESSION['current_round'] = 1;
        $_SESSION['game_started'] = true;
        $_SESSION['game_over'] = false;

        // Завантаження та перемішування питань
        $all_questions = json_decode(file_get_contents('data/questions.json'), true);
        if (is_array($all_questions)) {
            $question_ids = array_map(function($q) { return $q['id']; }, $all_questions);
            shuffle($question_ids);
            $_SESSION['available_question_ids'] = $question_ids;
            $_SESSION['all_questions_data'] = array_column($all_questions, null, 'id'); // Для швидкого доступу за ID
        } else {
            // Обробка помилки завантаження питань
            $_SESSION['game_started'] = false; // Зупинити гру, якщо питання не завантажені
            $error = "Помилка завантаження файлу питань. Будь ласка, перевірте файл data/questions.json.";
        }
        
        if ($_SESSION['game_started']) {
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
            <p style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST" action="index.php">
            <p>Введіть імена гравців (мінімум 2):</p>
            <div id="player-inputs">
                <div class="player-input-group">
                    <input type="text" name="players[]" placeholder="Ім'я гравця 1" required>
                    <!-- Кнопка видалення не потрібна для перших двох -->
                </div>
                <div class="player-input-group">
                    <input type="text" name="players[]" placeholder="Ім'я гравця 2" required>
                </div>
            </div>
            <button type="button" id="add-player" class="add-player-btn">Додати гравця</button>
            <button type="submit" class="start-game-btn">Почати гру!</button>
        </form>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
