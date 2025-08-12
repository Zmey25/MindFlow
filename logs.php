<?php
// --- Налаштування ---
$log_directory = 'logs';
$page_title = 'Log Viewer';
$date_format = 'Y-m-d H:i:s'; // Формат дати

// --- Логіка скрипта ---
$selected_file = null;
$files = [];
$log_content = '';
$error_message = '';

// Перевірка існування папки з логами
if (is_dir($log_directory)) {
    // Скануємо папку та отримуємо файли
    $scanned_files = array_diff(scandir($log_directory), ['..', '.']);

    // Створюємо масив з інформацією про файли
    foreach ($scanned_files as $file) {
        $file_path = $log_directory . '/' . $file;
        if (is_file($file_path)) {
            $files[$file] = filemtime($file_path);
        }
    }

    // Сортуємо файли за датою модифікації (новіші вгорі)
    arsort($files);

    // Визначаємо, який файл потрібно відобразити
    if (isset($_GET['file']) && array_key_exists($_GET['file'], $files)) {
        $selected_file = $_GET['file'];
    } elseif (!empty($files)) {
        // Якщо файл не вибрано, беремо найновіший
        $selected_file = key($files);
    }

    // Читаємо вміст вибраного файлу
    if ($selected_file) {
        $file_to_read = $log_directory . '/' . $selected_file;
        // Використовуємо htmlspecialchars для безпечного виводу
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
        }
        .sidebar {
            width: 300px;
            background-color: #ffffff;
            border-right: 1px solid #dfe1e6;
            padding: 20px;
            overflow-y: auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        .sidebar h2 {
            margin-top: 0;
            border-bottom: 2px solid #0052cc;
            padding-bottom: 10px;
            color: #0052cc;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        .sidebar li a {
            display: block;
            padding: 12px 15px;
            text-decoration: none;
            color: #42526e;
            border-radius: 3px;
            transition: background-color 0.2s ease, color 0.2s ease;
            word-break: break-all;
        }
        .sidebar li a.active, .sidebar li a:hover {
            background-color: #0052cc;
            color: #ffffff;
        }
        .sidebar .file-date {
            font-size: 0.8em;
            color: #5e6c84;
            display: block;
            margin-top: 4px;
        }
        .sidebar li a.active .file-date {
            color: #ffffff;
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
        }
        .content-header h1 {
            margin: 0;
            color: #172b4d;
            font-size: 1.8em;
            word-break: break-all;
        }
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
            white-space: pre;
        }
        .error-message {
            background-color: #ffebe6;
            border: 1px solid #ff5630;
            color: #bf2600;
            padding: 15px;
            border-radius: 5px;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>Лог-файли</h2>
        <?php if (!empty($files)): ?>
            <ul>
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
            <p>Лог-файли не знайдено.</p>
        <?php endif; ?>
    </div>

    <div class="content-wrapper">
        <?php if ($error_message): ?>
            <p class="error-message"><?php echo $error_message; ?></p>
        <?php elseif ($selected_file): ?>
            <div class="content-header">
                <h1><?php echo htmlspecialchars($selected_file); ?></h1>
            </div>
            <div class="log-content"><code><?php echo $log_content; ?></code></div>
        <?php else: ?>
            <div class="content-header">
                <h1>Вітаємо!</h1>
            </div>
            <p>Оберіть файл зі списку зліва для перегляду його вмісту.</p>
        <?php endif; ?>
    </div>

</body>
</html>
