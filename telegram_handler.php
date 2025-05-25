<?php
// telegram_handler.php
// This file acts as a webhook endpoint for a Telegram bot.

// Set up error reporting for debugging. In a production environment, you might
// want to log errors without displaying them directly to the user.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define the path to the project root. Since this file is assumed to be
// in the root, __DIR__ will be the project's main directory.
define('ROOT_DIR', __DIR__);

// Load environment variables from the .env file.
// The .env file is expected to be in the project root.
require_once ROOT_DIR . '/includes/env-loader.php';
loadEnv(ROOT_DIR . '/.env');

// Load general utility functions, including `custom_log`.
require_once ROOT_DIR . '/includes/functions.php';

// Retrieve the Telegram Bot Token from environment variables.
// getenv() is generally preferred over $_ENV for environment variables,
// especially if loaded by a web server, but $_ENV also works if loadEnv sets it.
$telegramToken = getenv('TELEGRAM_TOKEN');

if (!$telegramToken) {
    // Log an error if the token is not found.
    custom_log('TELEGRAM_TOKEN is not set in the .env file. Cannot process Telegram webhook.', 'telegram_error');
    // Respond with a server error status code to indicate a configuration issue.
    http_response_code(500);
    die('Configuration error: Telegram token missing.');
}

// Get the raw JSON data sent by Telegram via the webhook.
$input = file_get_contents('php://input');
$update = json_decode($input, true);

// Log the received update for debugging purposes. You can check logs/telegram_webhook.log
custom_log('Received Telegram Webhook Update: ' . $input, 'telegram_webhook');

// Check for JSON decoding errors.
if (json_last_error() !== JSON_ERROR_NONE) {
    custom_log('Failed to decode JSON from Telegram webhook: ' . json_last_error_msg(), 'telegram_error');
    http_response_code(400); // Bad Request
    die('Invalid JSON input received.');
}

// Process only 'message' updates for now. Other update types (e.g., edited_message, callback_query)
// would require additional logic.
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? ''; // The text of the message, if it's a text message.

    custom_log("Processing message from Chat ID: {$chatId}. Text: '{$text}'", 'telegram_webhook');

    $responseText = '';

    // Simple command handling based on the message text.
    // Commands are usually prefixed with a slash (e.g., /start, /help).
    if (strpos($text, '/start') === 0) {
        $responseText = "Вітаю! Я ваш персональний бот. Я тут, щоб допомогти вам з інформацією про проект.";
    } elseif (strpos($text, '/help') === 0) {
        $responseText = "Я розумію кілька простих команд, наприклад /start. Спробуйте їх!";
    } elseif (strpos($text, '/test_log') === 0) {
        // Example command to test if custom_log is working correctly.
        custom_log("User {$chatId} issued /test_log command.", 'telegram_test');
        $responseText = "Перевіряю лог. Якщо все працює, ви побачите запис в `logs/telegram_test.log`.";
    } elseif (!empty($text)) {
        // Default response for any other non-empty text message.
        $responseText = "Ви сказали: \"" . htmlspecialchars($text) . "\"\nЯ поки що не розумію складніші запити, але вчуся!";
    } else {
        // Response for non-text messages (e.g., stickers, photos, voice messages).
        $responseText = "Я отримав ваше повідомлення, але воно не містить тексту. Будь ласка, надсилайте текстові повідомлення.";
    }

    // Prepare data for sending a message back to Telegram using the sendMessage method.
    $apiUrl = "https://api.telegram.org/bot{$telegramToken}/sendMessage";
    $postFields = [
        'chat_id' => $chatId,
        'text' => $responseText,
        'parse_mode' => 'HTML' // Use 'HTML' or 'MarkdownV2' for text formatting, or remove for plain text.
    ];

    // Use cURL to send the POST request to the Telegram Bot API.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the transfer as a string of the return value

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        custom_log("cURL Error sending message to Chat ID {$chatId}: " . $curlError, 'telegram_error');
        // Do not die() here, as Telegram might retry the webhook if no 200 OK is received.
    } elseif ($httpCode !== 200) {
        custom_log("Telegram API returned HTTP {$httpCode} for Chat ID {$chatId}: " . $response, 'telegram_error');
    } else {
        custom_log("Successfully sent message to Chat ID {$chatId}: '{$responseText}'", 'telegram_webhook');
    }

    // Always respond with 200 OK to Telegram to acknowledge successful receipt and avoid re-sends.
    http_response_code(200);

} else {
    // If it's not a 'message' update (e.g., an 'edited_message', 'channel_post', 'callback_query', etc.),
    // just log it and return 200 OK to Telegram. This prevents Telegram from re-sending the same update.
    custom_log('Received non-message or unsupported Telegram update type. Update content: ' . $input, 'telegram_webhook');
    http_response_code(200);
}
?>
