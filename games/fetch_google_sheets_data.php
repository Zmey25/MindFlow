<?php
// fetch_google_sheets_data.php

// Ваш URL Google Apps Script
$google_script_url = 'https://script.google.com/macros/s/AKfycbxsKUea21G-9afyRq43V81f98prYKBvULOAwOFiEryR9Rj1tXijH9g9BApk0gjI5PGB/exec';

// Папка для збереження JSON файлів (створіть її в тій же директорії, де лежить цей скрипт)
$data_dir = __DIR__ . '/data';
$excluded_sheet_name_lower = 'tech'; // Назва листа, який потрібно ігнорувати, в нижньому регістрі

// Перевірка та створення папки data
if (!is_dir($data_dir)) {
    if (!mkdir($data_dir, 0775, true)) { // 0775 - типові права
        log_message("ERROR: Не вдалося створити папку: $data_dir");
        exit(1);
    }
    log_message("INFO: Папку $data_dir створено.");
}

// Функція для логування
function log_message($message) {
    echo date('[Y-m-d H:i:s] ') . $message . PHP_EOL;
}

// Функція для виконання POST-запиту до Google Apps Script
function make_google_api_request($url, $payload) {
    $options = [
        'http' => [
            'header' => "Content-Type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($payload),
            'ignore_errors' => true, // Щоб отримати тіло відповіді навіть при HTTP помилці
            'timeout' => 30, // Таймаут для запиту в секундах
        ],
        'ssl' => [ // Може знадобитися для обходу проблем з SSL на деяких серверах
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ];
    $context = stream_context_create($options);
    $response_body = file_get_contents($url, false, $context);

    if ($response_body === false) {
        log_message("ERROR: Не вдалося виконати запит до $url. Payload: " . json_encode($payload));
        return null;
    }

    $response_data = json_decode($response_body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message("ERROR: Некоректний JSON у відповіді від $url. Payload: " . json_encode($payload) . ". Response body: " . substr($response_body, 0, 500));
        return null;
    }
    
    if (!isset($response_data['status'])) {
        log_message("ERROR: Відсутній 'status' у відповіді від $url. Payload: " . json_encode($payload) . ". Response: " . json_encode($response_data));
        return null;
    }
    
    return $response_data;
}

log_message("INFO: Початок оновлення даних з Google Sheets.");

// 1. Отримати список назв усіх листів
log_message("INFO: Запит списку назв листів...");
$sheet_names_payload = ['action' => 'get_all_sheet_names'];
$sheet_names_response = make_google_api_request($google_script_url, $sheet_names_payload);

if (!$sheet_names_response || $sheet_names_response['status'] !== 'success' || !isset($sheet_names_response['data']) || !is_array($sheet_names_response['data'])) {
    log_message("ERROR: Помилка отримання списку назв листів. Відповідь: " . json_encode($sheet_names_response));
    exit(1);
}

$sheet_names = $sheet_names_response['data'];
log_message("INFO: Отримано назви листів: " . implode(', ', $sheet_names));

// 2. Для кожного листа (крім 'tech') отримати його дані та зберегти в JSON
foreach ($sheet_names as $sheet_name) {
    if (strtolower($sheet_name) === $excluded_sheet_name_lower) {
        log_message("INFO: Пропуск листа '$sheet_name' (виключений).");
        continue;
    }

    log_message("INFO: Запит даних для листа '$sheet_name'...");
    $sheet_data_payload = ['action' => 'get', 'sheetName' => $sheet_name];
    $sheet_data_response = make_google_api_request($google_script_url, $sheet_data_payload);

    if (!$sheet_data_response || $sheet_data_response['status'] !== 'success' || !isset($sheet_data_response['data'])) {
        log_message("ERROR: Помилка отримання даних для листа '$sheet_name'. Відповідь: " . json_encode($sheet_data_response));
        continue; // Продовжуємо з наступним листом
    }

    // Створюємо безпечну назву файлу
    $safe_file_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $sheet_name) . '.json';
    $file_path = $data_dir . '/' . $safe_file_name;

    // Зберігаємо лише масив 'data' з відповіді
    $data_to_save = $sheet_data_response['data'];

    if (file_put_contents($file_path, json_encode($data_to_save, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        log_message("INFO: Дані для листа '$sheet_name' успішно збережено в '$file_path'.");
    } else {
        log_message("ERROR: Не вдалося зберегти дані для листа '$sheet_name' в '$file_path'.");
    }
}

log_message("INFO: Оновлення даних завершено.");
?>