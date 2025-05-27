<?php
// telegram_handler.php
// Цей файл слугує точкою входу для вебхука Telegram бота.

// Налаштовуємо виведення помилок для налагодження. У продакшн-середовищі краще лише логувати помилки.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Визначаємо шлях до кореня проекту.
define('ROOT_DIR', __DIR__);

// Завантажуємо змінні оточення з файлу .env.
require_once ROOT_DIR . '/includes/env-loader.php';
// Припускаємо, що .env лежить на рівень вище ROOT_DIR (наприклад, поза public_html)
// Якщо .env лежить в ROOT_DIR, змініть на ROOT_DIR . '/.env'
loadEnv(ROOT_DIR . '/../.env');


// Завантажуємо загальні службові функції, включаючи `custom_log` та `readJsonFile`.
require_once ROOT_DIR . '/includes/functions.php';

// Завантажуємо функції взаємодії з Gemini API.
require_once ROOT_DIR . '/includes/gemini_api.php';

// Отримуємо Telegram Bot Token зі змінних оточення.
$telegramToken = getenv('TELEGRAM_TOKEN');

// --- NEW: Define Admin Chat ID ---
define('ADMIN_CHAT_ID', 1282207313); // Ваш Telegram Chat ID

if (!$telegramToken) {
    custom_log('TELEGRAM_TOKEN не встановлено в файлі .env. Неможливо обробити вебхук Telegram.', 'telegram_error');
    http_response_code(500);
    die('Помилка конфігурації: відсутній токен Telegram.');
}

$input = file_get_contents('php://input');
$update = json_decode($input, true);

custom_log('Отримано оновлення Telegram Webhook: ' . $input, 'telegram_webhook');

if (json_last_error() !== JSON_ERROR_NONE) {
    custom_log('Не вдалося декодувати JSON з вебхука Telegram: ' . json_last_error_msg(), 'telegram_error');
    http_response_code(400);
    die('Отримано недійсний JSON ввід.');
}

/**
 * Функція для надсилання повідомлення назад до Telegram.
 * (Залишається без змін)
 */
function sendTelegramMessage(int $chatId, string $text, string $telegramToken): void {
    $apiUrl = "https://api.telegram.org/bot{$telegramToken}/sendMessage";
    $postFields = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Тайм-аут для надсилання повідомлення

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
    $lowerText = mb_strtolower($text); // Для перевірки ключових слів без урахування регістру

    // --- NEW: Determine if the request is from an admin ---
    $is_admin_request = ($chatId == ADMIN_CHAT_ID);

    custom_log("Обробка повідомлення з Chat ID: {$chatId}. Текст: '{$text}' (Admin: " . ($is_admin_request ? 'Так' : 'Ні') . ")", 'telegram_webhook');

    $responseText = '';

    // Обробка статичних/простих команд ПЕРЕД викликом LLM
    if (strpos($text, '/start') === 0) {
        $responseText = "Вітаю! Я ваш персональний бот для аналізу особистості. Ви можете запитати мене про користувачів, питання, риси чи бейджи. Спробуйте '/ask [ваше питання]' або просто поставте питання.";
    } elseif (strpos($text, '/help') === 0) {
        $responseText = "Я розумію кілька команд: \n/start - почати діалог.\n/help - отримати допомогу.\n/ask [питання] або просто ваше питання - задати питання про дані проекту.\n\nТакож ви можете запитати:\n- 'хто ти?' або 'про проект' - для інформації про мене.\n- 'порівняй [користувач1] та [користувач2]' - для порівняння результатів.\n- 'які результати у [користувач]?' - для інформації по конкретному користувачу.";
    } elseif (strpos($text, '/test_log') === 0) {
        custom_log("Користувач {$chatId} використав команду /test_log.", 'telegram_test');
        $responseText = "Перевіряю лог. Якщо все працює, ви побачите запис в `logs/telegram_test.log`.";
    }
    // Перевірка на запити про бота/проект - статична відповідь
    elseif (preg_match('/(хто ти\??|про проект|що це за бот\??|про mindflow)/ui', $lowerText)) {
        $responseText = "Я Маскот проєкту психологічних тестів MindFlow! Я кіт (або кішка, як вам більше подобається 😉), ваш персональний секретар і помічник. Моя робота - швидко знаходити та надавати вам інформацію з результатів тестів користувачів. Запитуйте!";
    }
    // Основна логіка обробки, якщо це не проста команда або запит "про проект"
    elseif (!empty($text)) {
        sendTelegramMessage($chatId, "Аналізую ваш запит, хвилинку... 🤖", $telegramToken);

        $geminiRoute = determineRelevantData($text);

        if (isset($geminiRoute['error'])) {
            $responseText = "Вибачте, виникла помилка під час аналізу вашого запиту: " . $geminiRoute['error'];
        } else {
            $fileType = $geminiRoute['file_type'];
            $targetUsernames = $geminiRoute['target_usernames'] ?? []; // Тепер це масив
            $followUpQuery = $geminiRoute['follow_up_query'];
            $contextData = null; // Використовуємо null для перевірки чи були дані завантажені
            $contextDataJson = '';
            $dataLoadedSuccessfully = true; // Прапорець успішного завантаження даних

            custom_log("LLM1 Route: file_type='{$fileType}', target_usernames=" . json_encode($targetUsernames) . ", query='{$followUpQuery}'", 'gemini_route');

            switch ($fileType) {
                case 'users':
                    $allUsersData = readJsonFile(ROOT_DIR . '/data/users.json');
                    $contextData = [];
                    foreach ($allUsersData as $user) {
                        unset($user['password_hash'], $user['password']);
                        $contextData[] = $user;
                    }
                    break;
                case 'questions':
                    $contextData = readJsonFile(ROOT_DIR . '/data/questions.json');
                    break;
                case 'traits':
                    $contextData = readJsonFile(ROOT_DIR . '/data/traits.json');
                    break;
                case 'badges':
                    $contextData = readJsonFile(ROOT_DIR . '/data/badges.json');
                    break;
                case 'user_answers': // Обробляє одного або двох користувачів
                    if (empty($targetUsernames)) {
                        $responseText = "Для запиту типу 'user_answers' не було визначено користувачів.";
                        $dataLoadedSuccessfully = false;
                        break;
                    }

                    $loadedUsersData = [];
                    foreach ($targetUsernames as $username) {
                        // --- UPDATED: Pass is_admin_request to loadUserData ---
                        $loadResult = loadUserData($username, $is_admin_request);
                        if ($loadResult['success']) {
                            $loadedUsersData[$username] = $loadResult['data'];
                        } else {
                            // If any user data fails to load (e.g., hidden results), set responseText and stop.
                            $responseText = $loadResult['message'];
                            $dataLoadedSuccessfully = false;
                            break 2; // Break out of both the foreach and the switch
                        }
                    }

                    if ($dataLoadedSuccessfully) { // Only proceed if all necessary users loaded successfully
                        if (count($targetUsernames) === 1) {
                            $contextData = reset($loadedUsersData); // Get the first (and only) user's data
                        } elseif (count($targetUsernames) === 2) {
                            // Ensure both users actually have data, even if loadUserData succeeded for one
                            $user1Data = $loadedUsersData[$targetUsernames[0]] ?? null;
                            $user2Data = $loadedUsersData[$targetUsernames[1]] ?? null;

                            if (empty($user1Data) || empty($user2Data)) {
                                $responseText = "Не вдалося завантажити дані для порівняння, або один з користувачів недоступний.";
                                $dataLoadedSuccessfully = false;
                                break;
                            }

                            $contextData = [
                                'user1_data' => $user1Data,
                                'user2_data' => $user2Data,
                                'user1_username' => $targetUsernames[0],
                                'user2_username' => $targetUsernames[1]
                            ];
                        } else {
                             $responseText = "Отримано невірну кількість імен користувачів для обробки: " . count($targetUsernames) . ". Очікувалось 1 або 2.";
                             $dataLoadedSuccessfully = false;
                        }
                    }
                    break;
                case 'none':
                default:
                    // Для 'none' контекст не потрібен, або він вже включений у followUpQuery
                    $contextData = ['info' => 'Для цього типу запиту не завантажено специфічний файл контексту.'];
                    break;
            }

            // Якщо дані не були завантажені успішно (крім типу 'none', де це нормально)
            if (!$dataLoadedSuccessfully) {
                // $responseText вже встановлено з повідомленням про помилку від loadUserData
                custom_log("Data loading failed. Response: " . $responseText, 'telegram_webhook');
            } elseif (!empty($followUpQuery)) {
                // Переконуємось, що $contextData не null і не порожній перед json_encode, особливо для file_type 'none'
                if ($contextData !== null && !empty($contextData) && !(count($contextData) === 1 && isset($contextData['info']))) { // Check if it's just the default 'info' context
                    $contextDataJson = json_encode($contextData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        custom_log("JSON encode error for contextData: " . json_last_error_msg(), 'telegram_error');
                        $responseText = "Внутрішня помилка: не вдалося підготувати дані для ШІ.";
                        $dataLoadedSuccessfully = false; // Щоб не викликати getGeminiAnswer
                    }
                } else { // If contextData is null, empty, or only contains 'info'
                    if (empty($responseText)) { // Only set responseText if not already set by loadUserData or other logic
                        $responseText = "Немає даних для аналізу. Можливо, дані приховані або користувач не існує.";
                    }
                    custom_log("No valid context data for LLM2. Context: " . json_encode($contextData) . " Response: " . $responseText, 'telegram_webhook');
                    $dataLoadedSuccessfully = false; // Prevent LLM2 call
                }

                if ($dataLoadedSuccessfully) { // Продовжуємо, якщо все ще успішно
                    custom_log("Sending to LLM2: Query='{$followUpQuery}', Context (first 200 chars)='" . substr($contextDataJson, 0, 200) . "...'", 'gemini_request');
                    $finalAnswer = getGeminiAnswer($followUpQuery, $contextDataJson);
                    if ($finalAnswer) {
                        $responseText = $finalAnswer;
                    } else {
                        $responseText = "Вибачте, не вдалося отримати відповідь від ШІ. Можливо, питання занадто складне або сталася внутрішня помилка.";
                    }
                }
            } else {
                 // Якщо $responseText ще не встановлено (наприклад, помилкою завантаження даних)
                if (empty($responseText)) {
                    $responseText = "Запит не був достатньо уточнений для отримання кінцевої відповіді, або сталася помилка на етапі визначення маршруту.";
                }
            }
        }
    } elseif (empty($text) && isset($message['message_id'])) { // Якщо це не текстове повідомлення, але є $message
        $responseText = "Я отримав ваше повідомлення, але воно не містить тексту. Будь ласка, надсилайте текстові повідомлення.";
    }

    if (!empty($responseText)) {
        sendTelegramMessage($chatId, $responseText, $telegramToken);
    } else {
        // Якщо відповідь порожня, логуємо, але не надсилаємо нічого користувачу
        custom_log("No response generated for update (Chat ID: {$chatId}, Text: '{$text}'). Update: " . $input, 'telegram_webhook');
    }
    http_response_code(200);

} else {
    custom_log('Отримано не-повідомлення або непідтримуваний тип оновлення Telegram. Вміст оновлення: ' . $input, 'telegram_webhook');
    http_response_code(200); // Telegram очікує 200 OK, навіть якщо ми не обробляємо цей тип
}
?>
