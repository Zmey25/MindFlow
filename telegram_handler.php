<?php
// telegram_handler.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('ROOT_DIR', __DIR__);
define('DATA_DIR', ROOT_DIR . '/data');
define('ANSWERS_DIR_PATH', DATA_DIR . '/answers');
define('USERS_FILE_PATH', DATA_DIR . '/users.json');
define('QUESTIONS_FILE_PATH', DATA_DIR . '/questions.json');
define('TRAITS_FILE_PATH', DATA_DIR . '/traits.json');
define('BADGES_FILE_PATH', DATA_DIR . '/badges.json');

require_once ROOT_DIR . '/includes/env-loader.php';
loadEnv(ROOT_DIR . '/../.env');

require_once ROOT_DIR . '/includes/functions.php';
require_once ROOT_DIR . '/includes/gemini_api.php';

$telegramToken = getenv('TELEGRAM_TOKEN');

define('ADMIN_CHAT_ID', 1282207313);

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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        custom_log("Помилка cURL при надсиланні повідомлення до Chat ID {$chatId}: " . $curlError, 'telegram_error');
    } elseif ($httpCode !== 200) {
         $responseBody = is_string($response) ? $response : json_encode($response);
        custom_log("Telegram API повернув HTTP {$httpCode} для Chat ID {$chatId}: " . $responseBody, 'telegram_error');
    } else {
        custom_log("Успішно надіслано повідомлення до Chat ID {$chatId}", 'telegram_webhook');
    }
}


if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = trim($message['text'] ?? '');
    $lowerText = mb_strtolower($text);

    $is_admin_request = ($chatId == ADMIN_CHAT_ID);

    custom_log("Обробка повідомлення з Chat ID: {$chatId}. Текст: '{$text}' (Admin: " . ($is_admin_request ? 'Так' : 'Ні') . ")", 'telegram_webhook');

    $responseText = '';

    if (strpos($text, '/start') === 0) {
        $responseText = "Вітаю! Я ваш персональний бот для аналізу особистості. Ви можете запитати мене про користувачів, питання, риси чи бейджи. Спробуйте '/ask [ваше питання]' або просто поставте питання.";
    } elseif (strpos($text, '/help') === 0) {
        $responseText = "Я розумію кілька команд: \n/start - почати діалог.\n/help - отримати допомогу.\n/ask [питання] або просто ваше питання - задати питання про дані проекту.\n\nТакож ви можете запитати:\n- 'хто ти?' або 'про проект' - для інформації про мене.\n- 'порівняй [користувач1] та [користувач2]' - для порівняння результатів.\n- 'які результати у [користувач]?' - для інформації по конкретному користувачу.";
    } elseif (strpos($text, '/test_log') === 0) {
        custom_log("Користувач {$chatId} використав команду /test_log.", 'telegram_test');
        $responseText = "Перевіряю лог. Якщо все працює, ви побачите запис в `logs/telegram_test.log`.";
    }
    elseif (preg_match('/(хто ти\??|про проект|що це за бот\??|про mindflow)/ui', $lowerText)) {
        $responseText = "Я Маскот проєкту психологічних тестів MindFlow! Я кіт (або кішка, як вам більше подобається 😉), ваш персональний секретар і помічник. Моя робота - швидко знаходити та надавати вам інформацію з результатів тестів користувачів. Запитуйте!";
    }
    elseif (!empty($text)) {
        sendTelegramMessage($chatId, "🤖 Аналізую ваш запит і готую дані, хвилинку...", $telegramToken);

        custom_log("Calling determineRelevantData with query: '{$text}'", 'telegram_webhook');
        $geminiRouteResult = determineRelevantData($text);

        if (isset($geminiRouteResult['error'])) {
            $responseText = "Вибачте, виникла помилка під час аналізу вашого запиту (LLM1): " . $geminiRouteResult['error'];
            custom_log("LLM1 routing error: " . $responseText, 'telegram_error');
        } else {
            $potentialDataSources = $geminiRouteResult['potential_data_sources'];
            $targetUsernames = $geminiRouteResult['target_usernames'];
            $refinedQuery = $geminiRouteResult['refined_query'];

            custom_log("LLM1 Route Result: potential_data_sources=" . json_encode($potentialDataSources) . ", target_usernames=" . json_encode($targetUsernames) . ", refined_query='{$refinedQuery}'", 'telegram_webhook');

            $contextData = [];
            $dataLoadingError = null;

            custom_log("Starting data loading based on potential_data_sources: " . json_encode($potentialDataSources), 'telegram_webhook');

            $loadedUserDataSets = [];
            $failedUsernames = [];
            $userAnswersRequested = in_array('user_answers', $potentialDataSources);

            if ($userAnswersRequested) {
                 if (empty($targetUsernames)) {
                     $dataLoadingError = "Запит стосується даних користувача(ів), але ШІ не визначив жодного імені користувача.";
                     custom_log($dataLoadingError, 'telegram_error');
                 } else {
                    foreach ($targetUsernames as $username) {
                         $loadResult = loadUserData($username, $is_admin_request);
                         if ($loadResult['success']) {
                             // Створюємо стислий виклад даних перед додаванням до контексту
                             $loadedUserDataSets[$username] = summarizeUserData($loadResult['data']);
                         } else {
                             custom_log("Failed to load data for user '{$username}': " . $loadResult['message'], 'telegram_error');
                             $failedUsernames[] = $username . " (" . $loadResult['message'] . ")";
                         }
                    }

                    if (!empty($failedUsernames)) {
                         $dataLoadingError = "Не вдалося завантажити дані для наступних користувачів: " . implode(", ", $failedUsernames) . ".";
                         if (empty($loadedUserDataSets)) {
                             custom_log("No target user data loaded. Setting dataLoadingError: " . $dataLoadingError, 'telegram_error');
                         } else {
                             custom_log("Some target user data failed to load, but others succeeded. Message: " . $dataLoadingError, 'telegram_warning');
                         }
                    }

                     if (!empty($loadedUserDataSets)) {
                        if (count($loadedUserDataSets) === 1) {
                            $contextData['user_data'] = reset($loadedUserDataSets);
                            custom_log("Added 1 summarized user data set to context.", 'telegram_webhook');
                        } elseif (count($loadedUserDataSets) === 2) {
                            $usernamesLoaded = array_keys($loadedUserDataSets);
                             $contextData['comparison_data'] = [
                                'user1_data' => $loadedUserDataSets[$usernamesLoaded[0]],
                                'user2_data' => $loadedUserDataSets[$usernamesLoaded[1]],
                                'user1_username' => $usernamesLoaded[0],
                                'user2_username' => $usernamesLoaded[1]
                             ];
                            custom_log("Added 2 summarized user data sets for comparison to context.", 'telegram_webhook');
                        } else {
                             $dataLoadingError = "Завантажено невірну кількість наборів даних користувачів для обробки (" . count($loadedUserDataSets) . "). Очікувалось 1 або 2.";
                             custom_log($dataLoadingError, 'telegram_error');
                        }
                     }
                 }
            }

            foreach ($potentialDataSources as $sourceType) {
                 if ($sourceType === 'user_answers' || $sourceType === 'none') {
                     continue;
                 }

                switch ($sourceType) {
                    case 'users':
                        $allUsersData = readJsonFile(USERS_FILE_PATH);
                        // Створюємо стислий виклад списку користувачів
                        $contextData['all_users_list'] = summarizeUsersList($allUsersData);
                        custom_log("Loaded and summarized 'users' list data.", 'telegram_webhook');
                        break;
                    case 'questions':
                        $contextData['all_questions'] = readJsonFile(QUESTIONS_FILE_PATH);
                         custom_log("Loaded 'questions' data.", 'telegram_webhook');
                        break;
                    case 'traits':
                        $contextData['all_traits'] = readJsonFile(TRAITS_FILE_PATH);
                         custom_log("Loaded 'traits' data.", 'telegram_webhook');
                        break;
                    case 'badges':
                        $contextData['all_badges'] = readJsonFile(BADGES_FILE_PATH);
                         custom_log("Loaded 'badges' data.", 'telegram_webhook');
                        break;
                    default:
                        custom_log("Невідомий тип джерела даних від LLM1 під час завантаження: {$sourceType}", 'telegram_warning');
                        break;
                }
            }


            custom_log("Proceeding to LLM2. Data loading error: " . ($dataLoadingError ?? 'None'), 'telegram_webhook');

            if (($userAnswersRequested && empty($loadedUserDataSets)) ||
                 (empty($contextData) && !in_array('none', $potentialDataSources)) ||
                 empty($refinedQuery) )
            {
                if ($dataLoadingError === null) {
                    $dataLoadingError = "Не вдалося завантажити необхідні дані для відповіді на запит. Можливо, файли даних порожні або пошкоджені.";
                    custom_log("General data loading failure or empty context for non-'none' request.", 'telegram_error');
                }
                $responseText = "Виникла проблема під час підготовки даних: " . $dataLoadingError . " " .
                                "Спробуйте переформулювати запит.";

            } else {
                $contextDataJson = json_encode($contextData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                if (json_last_error() !== JSON_ERROR_NONE) {
                     custom_log("JSON encode error for contextData for LLM2: " . json_last_error_msg(), 'telegram_error');
                     $responseText = "Внутрішня помилка: не вдалося підготувати дані для ШІ аналізу.";
                } else {
                    custom_log("Calling getGeminiAnswer with refinedQuery='{$refinedQuery}' and summarized context...", 'telegram_webhook');
                    $finalAnswer = getGeminiAnswer($refinedQuery, $contextDataJson);

                    if ($finalAnswer) {
                        if ($dataLoadingError !== null) {
                            $responseText = "⚠️ Частина даних недоступна: " . $dataLoadingError . "\n\n" . $finalAnswer;
                             custom_log("Added partial data error to LLM2 response.", 'telegram_warning');
                        } else {
                             $responseText = $finalAnswer;
                        }

                    } else {
                        $responseText = "Вибачте, не вдалося отримати відповідь від ШІ аналізатора (LLM2). Можливо, питання занадто складне або сталася внутрішня помилка ШІ.";
                         custom_log("LLM2 returned empty response.", 'telegram_error');
                    }
                }
            }
        }
    } elseif (empty($text) && isset($update['message']['message_id'])) {
        $responseText = "Я отримав ваше повідомлення, але воно не містить тексту. Будь ласка, надсилайте текстові повідомлення.";
        custom_log("Received non-text message.", 'telegram_webhook');
    }

function splitHtmlMessageFlexible(string $html, int $limit, string $allowed_tags): array
{
    $tag_list = trim(str_replace(['<', '>'], ['', '|'], $allowed_tags), '|');
    if (empty($tag_list)) {
        return explode("\n", wordwrap($html, $limit, "\n", true));
    }

    $regex = '/<(' . $tag_list . ')( [^>]*)?>$/i';
    
    $chunks = explode("\n", wordwrap($html, $limit, "\n", true));
    $chunkCount = count($chunks);

    for ($i = 0; $i < $chunkCount - 1; $i++) {
        if (preg_match($regex, $chunks[$i], $matches)) {
            $openingTag = $matches[0];
            $tagName = $matches[1];
            $closingTag = "</$tagName>";

            if (str_starts_with(ltrim($chunks[$i + 1]), $closingTag)) {
                $chunks[$i] = substr($chunks[$i], 0, -strlen($openingTag));
                $chunks[$i + 1] = ltrim(substr_replace($chunks[$i + 1], '', 0, strlen($closingTag)));
            }
        }
    }
    return array_filter($chunks);
}

    if (!empty($responseText)) {
        $allowed_tags = '<b><i><u><s><a><code><pre>';
        $sanitizedResponse = strip_tags($responseText, $allowed_tags);
        $limit = 4000;
        
        if (mb_strlen($sanitizedResponse, 'UTF-8') > $limit) {
            $messages = splitHtmlMessageFlexible($sanitizedResponse, $limit, $allowed_tags);
            foreach ($messages as $messagePart) {
                sendTelegramMessage($chatId, $messagePart, $telegramToken);
                usleep(700000);
            }
        } else {
            sendTelegramMessage($chatId, $sanitizedResponse, $telegramToken);
        }
    } else {
        custom_log("No response generated for update (Chat ID: {$chatId}, Text: '{$text}'). Update: " . $input, 'telegram_webhook');
    }
    http_response_code(200);

} else {
    custom_log('Отримано не-повідомлення або непідтримуваний тип оновлення Telegram. Вміст оновлення: ' . $input, 'telegram_webhook');
    http_response_code(200);
}
?>
