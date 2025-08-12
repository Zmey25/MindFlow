<?php
// Починаємо сесію для зберігання статусу авторизації
session_start();

// --- Налаштування ---
$log_directory = 'logs';
$page_title = 'Log Viewer';
$date_format = 'Y-m-d H:i:s';
$password = '12345'; // ВАЖЛИВО: Змініть цей пароль!

// --- Логіка авторизації ---
// Вихід із системи
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Перевірка відправленого пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['password']) && $_POST['password'] === $password) {
        $_SESSION['loggedin'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $login_error = 'Неправильний пароль!';
    }
}

// Якщо користувач не авторизований, показуємо форму входу
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    ?>
    <!DOCTYPE html>
    <html lang="uk">
    <head>
        <meta charset="UTF-8">
        <title>Вхід</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f0f2f5; margin: 0; }
            .login-container { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; }
            h2 { color: #333; }
            input[type="password"] { padding: 10px; margin-top: 10px; border: 1px solid #ccc; border-radius: 4px; width: 200px; }
            input[type="submit"] { padding: 10px 20px; margin-top: 20px; border: none; background-color: #0052cc; color: white; border-radius: 4px; cursor: pointer; }
            .error { color: #d93025; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>Авторизація</h2>
            <form method="post">
                <input type="password" name="password" placeholder="Введіть пароль" required autofocus>
                <br>
                <input type="submit" value="Увійти">
            </form>
            <?php if (isset($login_error)) { echo '<p class="error">' . $login_error . '</p>'; } ?>
        </div>
    </body>
    </html>
    <?php
    exit; // Зупиняємо виконання скрипта
}

// --- Основна логіка перегляду логів (виконується тільки після авторизації) ---
$selected_file = null;
$files = [];
$log_content = '';
$error_message = '';

if (is_dir($log_directory)) {
    $scanned_files = array_diff(scandir($log_directory), ['..', '.']);
    foreach ($scanned_files as $file) {
        $file_path = $log_directory . '/' . $file;
        if (is_file($file_path)) {
            $files[$file] = filemtime($file_path);
        }
    }
    arsort($files);

    if (isset($_GET['file']) && array_key_exists($_GET['file'], $files)) {
        $selected_file = $_GET['file'];
    } elseif (!empty($files)) {
        $selected_file = key($files);
    }

    if ($selected_file) {
        $file_to_read = $log_directory . '/' . $selected_file;
        $log_content = htmlspecialchars(file_get_contents($file_to_read), ENT_QUOTES, 'UTF-8');
    }

} else {
    $error_message = "Папку з логами '{$log_directory}' не знайдено. Будь ласка, створіть її.";
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            height: 100vh;
            background-color: #f4f5f7;
            color: #172b4d;
            overflow: hidden; /* Запобігаємо прокрутці body */
        }
        .sidebar {
            width: 300px; /* Початкова ширина */
            min-width: 200px; /* Мінімальна ширина */
            max-width: 800px; /* Максимальна ширина */
            background-color: #ffffff;
            border-right: 1px solid #dfe1e6;
            display: flex;
            flex-direction: column;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            flex-shrink: 0; /* Забороняємо стискання */
        }
        .sidebar-header {
            padding: 20px;
            border-bottom: 2px solid #0052cc;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sidebar-header h2 {
            margin: 0;
            color: #0052cc;
        }
        .sidebar-header a {
            font-size: 0.9em;
            color: #42526e;
            text-decoration: none;
        }
        .sidebar-header a:hover { color: #0052cc; }
        .sidebar-list {
            list-style: none;
            padding: 0;
            margin: 0;
            overflow-y: auto;
            flex-grow: 1;
        }
        .sidebar-list li a {
            display: block;
            padding: 12px 20px;
            text-decoration: none;
            color: #42526e;
            transition: background-color 0.2s ease, color 0.2s ease;
            word-break: break-all;
            border-bottom: 1px solid #f4f5f7;
        }
        .sidebar-list li a.active, .sidebar-list li a:hover {
            background-color: #0052cc;
            color: #ffffff;
        }
        .file-date {
            font-size: 0.8em;
            color: #5e6c84;
            display: block;
            margin-top: 4px;
        }
        .sidebar-list li a.active .file-date, .sidebar-list li a:hover .file-date {
            color: #dfe1e6;
        }
        .resizer {
            width: 5px;
            cursor: col-resize;
            background: #f4f5f7;
            flex-shrink: 0;
        }
        .resizer:hover {
            background: #dfe1e6;
        }
        .content-wrapper {
            flex-grow: 1;
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .content-header {
            padding-bottom: 15px;
            border-bottom: 1px solid #dfe1e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        .content-header h1 {
            margin: 0;
            color: #172b4d;
            font-size: 1.8em;
            word-break: break-all;
        }
        .controls label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 0.9em;
        }
        .controls input { margin-right: 5px; }
        .log-content {
            flex-grow: 1;
            overflow: auto;
            background-color: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
            font-family: 'Consolas', 'Monaco', 'Lucida Console', monospace;
            font-size: 14px;
            line-height: 1.5;
            white-space: pre; /* За замовчуванням без переносу */
        }
        .log-content.wrap-lines {
            white-space: pre-wrap; /* Вмикає перенос рядків */
            word-break: break-all; /* Переносить довгі слова */
        }
        .error-message {
            background-color: #ffebe6; border: 1px solid #ff5630; color: #bf2600; padding: 15px; border-radius: 5px;
        }
    </style>
</head>
<body>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>Лог-файли</h2>
            <a href="?logout=1">Вийти</a>
        </div>
        <?php if (!empty($files)): ?>
            <ul class="sidebar-list">
                <?php foreach ($files as $file => $timestamp): ?>
                    <li>
                        <a href="?file=<?php echo urlencode($file); ?>" class="<?php echo ($file === $selected_file) ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($file); ?>
                            <span class="file-date"><?php echo date($date_format, $timestamp); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="padding: 20px;">Лог-файли не знайдено.</p>
        <?php endif; ?>
    </div>

    <div class="resizer" id="dragMe"></div>

    <div class="content-wrapper">
        <?php if ($error_message): ?>
            <p class="error-message"><?php echo $error_message; ?></p>
        <?php elseif ($selected_file): ?>
            <div class="content-header">
                <h1><?php echo htmlspecialchars($selected_file); ?></h1>
                <div class="controls">
                    <label for="wrapToggle">
                        <input type="checkbox" id="wrapToggle">
                        Переносити рядки
                    </label>
                </div>
            </div>
            <div class="log-content" id="logContent"><code><?php echo $log_content; ?></code></div>
        <?php else: ?>
            <div class="content-header"><h1>Вітаємо!</h1></div>
            <p>Оберіть файл зі списку зліва для перегляду його вмісту.</p>
        <?php endif; ?>
    </div>

<script>
    // --- Логіка для перемикача переносу рядків ---
    const wrapToggle = document.getElementById('wrapToggle');
    const logContent = document.getElementById('logContent');

    if (wrapToggle && logContent) {
        wrapToggle.addEventListener('change', function() {
            logContent.classList.toggle('wrap-lines', this.checked);
        });
    }

    // --- Логіка для масштабування бічної панелі ---
    const resizer = document.getElementById('dragMe');
    const sidebar = document.getElementById('sidebar');

    if (resizer && sidebar) {
        // Функція, що буде викликатись при русі миші
        const resize = (e) => {
            const newWidth = e.clientX - sidebar.getBoundingClientRect().left;
            sidebar.style.width = newWidth + 'px';
        };

        // Функція, що викликається при відпусканні кнопки миші
        const stopResize = () => {
            window.removeEventListener('mousemove', resize);
            window.removeEventListener('mouseup', stopResize);
            // Робимо вміст знову виділяємим
            document.body.style.userSelect = '';
            document.body.style.pointerEvents = '';
        };

        // Починаємо масштабування при натисканні на смугу
        resizer.addEventListener('mousedown', (e) => {
            e.preventDefault(); // Запобігаємо стандартній поведінці
            window.addEventListener('mousemove', resize);
            window.addEventListener('mouseup', stopResize);
            // Запобігаємо виділенню тексту під час перетягування
            document.body.style.userSelect = 'none';
            document.body.style.pointerEvents = 'none';
        });
    }
</script>

</body>
</html>
