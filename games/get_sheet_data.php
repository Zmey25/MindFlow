<?php
// get_sheet_data.php
header('Content-Type: application/json; charset=utf-8');

$data_dir = __DIR__ . '/data';

// Очікуємо параметр 'sheetName' від фронтенду
$requested_sheet_name = $_GET['sheetName'] ?? null;

if (empty($requested_sheet_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Параметр sheetName є обов\'язковим.']);
    http_response_code(400);
    exit;
}

// Створюємо безпечну назву файлу, яку ми використовували при збереженні
$safe_file_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $requested_sheet_name) . '.json';
$file_path = $data_dir . '/' . $safe_file_name;

if (file_exists($file_path)) {
    $json_content = file_get_contents($file_path);
    // Перевіряємо, чи вміст є валідним JSON (хоча cron скрипт вже повинен це забезпечити)
    json_decode($json_content);
    if (json_last_error() === JSON_ERROR_NONE) {
        // Віддаємо вміст JSON-файлу як є.
        // Він уже містить масив даних [{...}, {...}]
        echo $json_content;
    } else {
        echo json_encode(['status' => 'error', 'message' => "Файл даних для '$requested_sheet_name' пошкоджено."]);
        http_response_code(500);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => "Файл даних для '$requested_sheet_name' не знайдено. Можливо, cron-завдання ще не виконалося або виникла помилка."]);
    http_response_code(404);
}
?>