<?php
// telegram_handler.php
// Цей файл слугує точкою входу для вебхука Telegram бота.

// Налаштовуємо виведення помилок для налагодження. У продакшн-середовищі краще лише логувати помилки.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Визначаємо шлях до кореня проекту.
// Оскільки цей файл передбачається в корені, __DIR__ буде головною директорією проекту.
define('ROOT_DIR', __DIR__);

// Завантажуємо змінні оточення з файлу .env.
require_once ROOT_DIR . '/includes/env-loader.php';
loadEnv(ROOT_DIR . '/../.env');

// Завантажуємо загальні службові функції, включаючи `custom_log` та `readJsonFile`.
require_once ROOT_DIR . '/includes/functions.php';

// Завантажуємо функції взаємодії з Gemini API.
require_once ROOT_DIR . '/includes/gemini_api.php'; // <--- НОВЕ: Підключаємо файл з функціями Gemini

// Отримуємо Telegram Bot Token зі змінних оточення.
$telegramToken = getenv('TELEGRAM_TOKEN');

if (!$telegramToken) {
    custom_log('TELEGRAM_TOKEN не встановлено в файлі .env. Неможливо обробити вебхук Telegram.', 'telegram_error');
    http_response_code(500);
    die('Помилка конфігурації: відсутній токен Telegram.');
}

// Отримуємо сирі JSON дані, надіслані Telegram через вебхук.
$input = file_get_contents('php://input');
$update = json_decode($input, true);

custom_log('Отримано оновлення Telegram Webhook: ' . $input, 'telegram_webhook');

if (json_last_error() !== JSON_ERROR_NONE) {
    custom_log('Не вдалося декодувати JSON з вебхука Telegram: ' . json_last_error_msg(), 'telegram_error');
    http_response_code(400); // Поганий запит
    die('Отримано недійсний JSON ввід.');
}

/**
 * Функція для надсилання повідомлення назад до Telegram.
 *
 * @param int $chatId ID чату, куди надсилати повідомлення.
 * @param string $text Текст повідомлення.
 * @param string $telegramToken Токен Telegram бота.
 * @return void
 */
function sendTelegramMessage(int $chatId, string $text, string $telegramToken): void {
    $apiUrl = "https://api.telegram.org/bot{$telegramToken}/sendMessage";
    $postFields = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML' // Використовуємо 'HTML' або 'MarkdownV2' для форматування тексту
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        custom_log("Помилка cURL при надсиланні повідомлення до Chat ID {$chatId}: " . $curlError, 'telegram_error');
    } elseif ($httpCode !== 200) {
        custom_log("Telegram API повернув HTTP {$httpCode} для Chat ID {$chatId}: " . $response, 'telegram_error');
    } else {
        custom_log("Успішно надіслано повідомлення до Chat ID {$chatId}", 'telegram_webhook');
    }
}


if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = trim($message['text'] ?? '');

    custom_log("Обробка повідомлення з Chat ID: {$chatId}. Текст: '{$text}'", 'telegram_webhook');

    $responseText = '';

    if (strpos($text, '/start') === 0) {
        $responseText = "Вітаю! Я ваш персональний бот для аналізу особистості. Ви можете запитати мене про користувачів, питання, риси чи бейджи. Спробуйте '/ask [ваше питання]'";
    } elseif (strpos($text, '/help') === 0) {
        $responseText = "Я розумію кілька команд: \n/start - почати діалог.\n/help - отримати допомогу.\n/ask [питання] - задати питання про дані проекту (користувачі, питання, риси, бейджи, тощо).";
    } elseif (strpos($text, '/test_log') === 0) {
        custom_log("Користувач {$chatId} використав команду /test_log.", 'telegram_test');
        $responseText = "Перевіряю лог. Якщо все працює, ви побачите запис в `logs/telegram_test.log`.";
    } elseif (!empty($text)) {
            // Надсилаємо негайний відгук користувачу, оскільки LLM-виклики можуть зайняти час
            sendTelegramMessage($chatId, "Обробляю ваш запит, зачекайте...", $telegramToken);

            // --- Логіка взаємодії з LLM ---
            $geminiRoute = determineRelevantData($userQuestion);

            if (isset($geminiRoute['error'])) {
                $responseText = "Вибачте, виникла помилка під час обробки вашого запиту: " . $geminiRoute['error'];
            } else {
                $fileType = $geminiRoute['file_type'];
                $targetUsername = $geminiRoute['target_username'];
                $followUpQuery = $geminiRoute['follow_up_query'];
                $contextData = [];
                $contextDataJson = '';

                // Завантажуємо дані контексту на основі рішення першого LLM
                switch ($fileType) {
                    case 'users':
                        $contextData = readJsonFile(ROOT_DIR . '/data/users.json');
                        // Видаляємо чутливі дані (хеші паролів) з контексту для LLM
                        foreach ($contextData as &$user) {
                            unset($user['password_hash'], $user['password']);
                        }
                        $contextDataJson = json_encode($contextData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        break;
                    case 'questions':
                        $contextData = readJsonFile(ROOT_DIR . '/data/questions.json');
                        $contextDataJson = json_encode($contextData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        break;
                    case 'traits':
                        $contextData = readJsonFile(ROOT_DIR . '/data/traits.json');
                        $contextDataJson = json_encode($contextData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        break;
                    case 'badges':
                        $contextData = readJsonFile(ROOT_DIR . '/data/badges.json');
                        $contextDataJson = json_encode($contextData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        break;
                    case 'about':
                        $contextData = file_get_contents(ROOT_DIR . '/data/about.txt');
                        $contextDataJson = json_encode(['about_text' => $contextData], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        break;
                    case 'dashboard_warning':
                        $contextData = file_get_contents(ROOT_DIR . '/data/dashboard_warning.txt');
                        $contextDataJson = json_encode(['warning_text' => $contextData], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        break;
                    case 'user_answers':
                        if ($targetUsername) {
                            $contextData = loadUserData($targetUsername); // Ця функція використовує readJsonFile всередині
                            $contextDataJson = json_encode($contextData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        } else {
                            $responseText = "Не вдалося визначити ім'я користувача для запиту про відповіді. Будь ласка, уточніть ім'я користувача.";
                        }
                        break;
                    case 'none':
                    default:
                        // Немає потреби в конкретному файлі, запит може бути загальним або повертатися до LLM для відкритих питань
                        $contextDataJson = json_encode(['info' => 'Для цього типу запиту не завантажено конкретний файл контексту.']);
                        break;
                }

                // Викликаємо другий LLM з контекстом
                if (!empty($followUpQuery)) {
                    $finalAnswer = getGeminiAnswer($followUpQuery, $contextDataJson);
                    if ($finalAnswer) {
                        $responseText = $finalAnswer;
                    } else {
                        $responseText = "Вибачте, не вдалося отримати відповідь від ШІ. Можливо, питання занадто складне або не має достатнього контексту.";
                    }
                } else {
                    $responseText = "Запит не був уточнений для отримання кінцевої відповіді.";
                }
            }
        
        // Стандартна відповідь для не-командних текстових повідомлень
        // $responseText = "Ви сказали: \"" . htmlspecialchars($text) . "\"\nЯ поки що не розумію складніші запити, але вчуся! Спробуйте команду /ask [ваше питання].";
    } else {
        // Відповідь для нетекстових повідомлень (наприклад, стікерів, фото)
        $responseText = "Я отримав ваше повідомлення, але воно не містить тексту. Будь ласка, надсилайте текстові повідомлення.";
    }

    sendTelegramMessage($chatId, $responseText, $telegramToken);
    http_response_code(200);

} else {
    // Якщо це не оновлення типу 'message' (наприклад, 'edited_message', 'channel_post', 'callback_query' тощо),
    // просто логуємо це і повертаємо 200 OK до Telegram.
    custom_log('Отримано не-повідомлення або непідтримуваний тип оновлення Telegram. Вміст оновлення: ' . $input, 'telegram_webhook');
    http_response_code(200);
}
?>
