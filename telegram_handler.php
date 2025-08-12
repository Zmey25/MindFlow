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
    custom_log('TELEGRAM_TOKEN не встановлено.', 'telegram_error');
    http_response_code(500);
    die('Помилка конфігурації.');
}

$input = file_get_contents('php://input');
$update = json_decode($input, true);

custom_log('Отримано оновлення Telegram: ' . $input, 'telegram_webhook');

if (json_last_error() !== JSON_ERROR_NONE) {
    custom_log('Не вдалося декодувати JSON з вебхука: ' . json_last_error_msg(), 'telegram_error');
    http_response_code(400);
    die('Недійсний JSON ввід.');
}

function sendTelegramMessage(int $chatId, string $text, string $telegramToken): void {
    $apiUrl = "https://api.telegram.org/bot{$telegramToken}/sendMessage";
    $postFields = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        custom_log("Telegram API повернув HTTP {$httpCode} для Chat ID {$chatId}: " . $response, 'telegram_error');
    }
}

if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = trim($message['text'] ?? '');
    $lowerText = mb_strtolower($text);

    $is_admin_request = ($chatId == ADMIN_CHAT_ID);
    $responseText = '';

    if (strpos($text, '/start') === 0) {
        $responseText = "Вітаю! Я ваш персональний бот для аналізу особистості. Запитуйте мене про користувачів, питання, риси чи бейджи.";
    } elseif (preg_match('/(хто ти\??|про проект|що це за бот\??)/ui', $lowerText)) {
        $responseText = "Я Маскот проєкту психологічних тестів MindFlow! Я кіт, ваш персональний помічник. Моя робота - швидко знаходити та надавати вам інформацію з результатів тестів.";
    }
    elseif (!empty($text)) {
        sendTelegramMessage($chatId, "🤖 Аналізую ваш запит, хвилинку...", $telegramToken);

        $geminiRouteResult = determineRelevantData($text);

        if (isset($geminiRouteResult['error'])) {
            $responseText = "Вибачте, сталася помилка під час аналізу запиту (LLM1): " . $geminiRouteResult['error'];
        } else {
            $potentialDataSources = $geminiRouteResult['potential_data_sources'];
            $targetUsernames = $geminiRouteResult['target_usernames'];
            $refinedQuery = $geminiRouteResult['refined_query'];

            custom_log("LLM1 Route: sources=" . json_encode($potentialDataSources) . ", users=" . json_encode($targetUsernames), 'telegram_webhook');

            $contextData = [];
            $dataLoadingError = null;
            $partialDataWarnings = [];

            $userAnswersRequested = in_array('user_answers', $potentialDataSources);

            if ($userAnswersRequested) {
                 if (empty($targetUsernames)) {
                     $dataLoadingError = "Запит, схоже, стосується даних користувача, але не вдалося визначити, якого саме. Будь ласка, вкажіть ім'я точніше.";
                 } else {
                    $loadedUserDataSets = [];
                    foreach ($targetUsernames as $username) {
                         $loadResult = loadAndSummarizeUserData($username, $is_admin_request);
                         if ($loadResult['success']) {
                             $loadedUserDataSets[$username] = $loadResult['data'];
                         } else {
                             $partialDataWarnings[] = $loadResult['message'];
                         }
                    }

                    if (empty($loadedUserDataSets)) {
                        $dataLoadingError = "Не вдалося завантажити дані для запитаних користувачів. Причини: " . implode("; ", $partialDataWarnings);
                    } else {
                        if (count($loadedUserDataSets) === 1) {
                            $contextData['user_data'] = reset($loadedUserDataSets);
                        } elseif (count($loadedUserDataSets) >= 2) {
                             $keys = array_keys($loadedUserDataSets);
                             $contextData['comparison_data'] = [
                                'user1_data' => $loadedUserDataSets[$keys[0]],
                                'user2_data' => $loadedUserDataSets[$keys[1]],
                                'user1_username' => $keys[0],
                                'user2_username' => $keys[1]
                             ];
                        }
                    }
                 }
            }

            if (!$dataLoadingError) {
                foreach ($potentialDataSources as $sourceType) {
                    if ($sourceType === 'user_answers' || $sourceType === 'none') continue;
                    switch ($sourceType) {
                        case 'users':
                            $contextData['all_users_list'] = summarizeUsersList(readJsonFile(USERS_FILE_PATH));
                            break;
                        case 'questions':
                            $contextData['all_questions'] = readJsonFile(QUESTIONS_FILE_PATH);
                            break;
                        case 'traits':
                            $contextData['all_traits'] = readJsonFile(TRAITS_FILE_PATH);
                            break;
                        case 'badges':
                            $contextData['all_badges'] = readJsonFile(BADGES_FILE_PATH);
                            break;
                    }
                }
            }

            if ($dataLoadingError) {
                $responseText = "Виникла проблема з доступом до даних: " . $dataLoadingError;
            } elseif (empty($contextData) && !in_array('none', $potentialDataSources)) {
                $responseText = "Не вдалося завантажити необхідні дані для відповіді. Можливо, вони відсутні.";
            } else {
                // Створюємо компактний JSON для API
                $contextDataJsonForApi = json_encode($contextData, JSON_UNESCAPED_UNICODE);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                     $responseText = "Внутрішня помилка: не вдалося підготувати дані для аналізу.";
                } else {
                    // Викликаємо LLM2 з компактним JSON
                    $finalAnswer = getGeminiAnswer($refinedQuery, $contextDataJsonForApi);
                    
                    if ($finalAnswer) {
                        $responseText = $finalAnswer;
                        if (!empty($partialDataWarnings)) {
                            $warningText = "<i><b>Примітка:</b> " . implode("; ", $partialDataWarnings) . "</i>";
                            $responseText = $warningText . "\n\n" . $responseText;
                        }
                    } else {
                        $responseText = "Вибачте, ШІ-аналізатор не зміг сформувати відповідь.";
                    }
                }
            }
        }
    }

    if (!empty($responseText)) {
        $allowed_tags = '<b><i><u><s><a><code><pre>';
        $sanitizedResponse = strip_tags($responseText, $allowed_tags);
        $limit = 4000;
        
        if (mb_strlen($sanitizedResponse, 'UTF-8') > $limit) {
            $messages = preg_split('/(\r\n|\n|\r)/', $sanitizedResponse);
            $currentMessage = '';
            foreach($messages as $line){
                if(mb_strlen($currentMessage . $line . "\n", 'UTF-8') > $limit){
                    sendTelegramMessage($chatId, $currentMessage, $telegramToken);
                    $currentMessage = $line . "\n";
                    usleep(500000); 
                } else {
                    $currentMessage .= $line . "\n";
                }
            }
            if(!empty($currentMessage)){
                sendTelegramMessage($chatId, $currentMessage, $telegramToken);
            }
        } else {
            sendTelegramMessage($chatId, $sanitizedResponse, $telegramToken);
        }
    }
    http_response_code(200);

} else {
    custom_log('Отримано не-повідомлення: ' . $input, 'telegram_webhook');
    http_response_code(200);
}
?>
