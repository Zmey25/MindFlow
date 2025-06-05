<?php
session_start();

// Скидання сесії для нової гри, якщо це вказано або гра ще не починалась
if ((isset($_GET['new_game']) && $_GET['new_game'] === 'true') || !isset($_SESSION['game_started'])) {
    $_SESSION = []; 
    // session_destroy(); // Якщо використовуєте, то потрібен session_start() одразу після
    // session_start(); 
} elseif (isset($_SESSION['game_started']) && $_SESSION['game_started'] === true && $_SESSION['game_over'] === false) {
    // Якщо гра вже йде і не завершена, перенаправляємо на game.php
    header('Location: game.php');
    exit;
} elseif (isset($_SESSION['game_started']) && $_SESSION['game_over'] === true) {
    // Якщо гра завершена, але користувач зайшов на index.php, перенаправимо на game_over.php
    header('Location: game_over.php');
    exit;
}


$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (код валідації гравців залишається тим самим) ...
    $players_input = isset($_POST['players']) ? (array)$_POST['players'] : [];
    $players = [];
    foreach ($players_input as $name) {
        $trimmed_name = trim($name);
        if (!empty($trimmed_name)) {
            $players[] = htmlspecialchars($trimmed_name);
        }
    }

    if (count($players) < 2) {
        $error = 'Будь ласка, введіть імена щонайменше двох гравців.';
    } else {
        $_SESSION['initial_player_names'] = $players; 
        
        $game_players = [];
        foreach ($players as $name) {
            $game_players[] = [
                'name' => $name,
                'skips_left' => 1,
                'active' => true,
                'deferred_effects' => []
            ];
        }
        $_SESSION['players'] = $game_players;
        $_SESSION['current_player_index'] = 0;
        $_SESSION['current_round'] = 1;
        $_SESSION['game_started'] = true;
        $_SESSION['game_over'] = false; // Явно встановлюємо
        $_SESSION['current_question_data'] = null; // Переконатися, що питання не вибране до першого ходу

        // Завантаження та перемішування питань
        $all_questions_raw = json_decode(file_get_contents('data/questions.json'), true);
        if (is_array($all_questions_raw) && !empty($all_questions_raw)) {
            // Створюємо асоціативний масив питань за ID для легкого доступу
            $_SESSION['all_questions_data'] = array_column($all_questions_raw, null, 'id');
            
            // Створюємо масив ID для перемішування
            $question_ids = array_keys($_SESSION['all_questions_data']);
            shuffle($question_ids);
            $_SESSION['available_question_ids'] = $question_ids; // Пул доступних ID
            
        } else {
            $_SESSION['game_started'] = false;
            $error = "Помилка завантаження файлу питань або файл порожній. Перевірте data/questions.json.";
        }
        
        if ($_SESSION['game_started'] && empty($error)) { // Перевіряємо $error
            header('Location: game.php');
            exit;
        }
    }
}
?>
<!-- Решта HTML index.php залишається такою ж -->
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
