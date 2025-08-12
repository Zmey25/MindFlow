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

if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = trim($message['text'] ?? '');
    $is_admin_request = ($chatId == ADMIN_CHAT_ID);
    
    // Обгортаємо всю логіку в try-catch, щоб зловити будь-яку фатальну помилку
    try {
        custom_log("Processing message from Chat ID: {$chatId}, Admin: " . ($is_admin_request ? 'Yes' : 'No') . ", Text: {$text}", 'telegram_debug');

        if (empty($text)) {
            http_response_code(200); // Відповідаємо ОК, але нічого не робимо
            exit();
        }

        $lowerText = mb_strtolower($text);
        $responseText = '';

        if (strpos($text, '/start') === 0) {
            $responseText = "Вітаю! Я ваш персональний бот для аналізу особистості. Запитуйте мене про користувачів, питання, риси чи бейджи.";
        } elseif (preg_match('/(хто ти\??|про проект|що це за бот\??)/ui', $lowerText)) {
            $responseText = "Я Маскот проєкту психологічних тестів MindFlow! Я кіт, ваш персональний помічник. Моя робота - швидко знаходити та надавати вам інформацію з результатів тестів.";
        } else {
            sendTelegramMessage($chatId, "🤖 Аналізую ваш запит, хвилинку...", $telegramToken);
            
            custom_log("Step 1: Calling determineRelevantData...", 'telegram_debug');
            $geminiRouteResult = determineRelevantData($text);
            custom_log("Step 1 finished. Route result: " . json_encode($geminiRouteResult, JSON_UNESCAPED_UNICODE), 'telegram_debug');


            if (isset($geminiRouteResult['error'])) {
                $responseText = "Вибачте, сталася помилка під час аналізу запиту (LLM1): " . $geminiRouteResult['error'];
            } else {
                $potentialDataSources = $geminiRouteResult['potential_data_sources'];
                $targetUsernames = $geminiRouteResult['target_usernames'];
                $refinedQuery = $geminiRouteResult['refined_query'];

                $contextData = [];
                $dataLoadingError = null;
                $partialDataWarnings = [];
                
                custom_log("Step 2: Starting data loading. Sources: " . json_encode($potentialDataSources), 'telegram_debug');
                
                $userAnswersRequested = in_array('user_answers', $potentialDataSources);

                if ($userAnswersRequested) {
                     if (empty($targetUsernames)) {
                         $dataLoadingError = "Запит, схоже, стосується даних користувача, але не вдалося визначити, якого саме. Будь ласка, вкажіть ім'я точніше.";
                     } else {
                        custom_log("Loading user data for: " . json_encode($targetUsernames), 'telegram_debug');
                        $loadedUserDataSets = [];
                        foreach ($targetUsernames as $username) {
                             custom_log("Calling loadAndSummarizeUserData for '{$username}'...", 'telegram_debug');
                             $loadResult = loadAndSummarizeUserData($username, $is_admin_request);
                             if ($loadResult['success']) {
                                 $loadedUserDataSets[$username] = $loadResult['data'];
                                 custom_log("Successfully loaded and summarized data for '{$username}'.", 'telegram_debug');
                             } else {
                                 $partialDataWarnings[] = $loadResult['message'];
                                 custom_log("Failed to load data for '{$username}': " . $loadResult['message'], 'telegram_debug');
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
                    custom_log("Loading other data types...", 'telegram_debug');
                    foreach ($potentialDataSources as $sourceType) {
                        if ($sourceType === 'user_answers' || $sourceType === 'none') continue;
                        custom_log("Loading '{$sourceType}'...", 'telegram_debug');
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
                
                custom_log("Step 2 finished. Data loading error: " . ($dataLoadingError ?? 'None'), 'telegram_debug');
                custom_log("Context keys prepared for LLM2: " . json_encode(array_keys($contextData)), 'telegram_debug');

                if ($dataLoadingError) {
                    $responseText = "Виникла проблема з доступом до даних: " . $dataLoadingError;
                } elseif (empty($contextData) && !in_array('none', $potentialDataSources)) {
                    $responseText = "Не вдалося завантажити необхідні дані для відповіді. Можливо, вони відсутні.";
                } else {
                    custom_log("Step 3: Preparing to call LLM2.", 'telegram_debug');
                    $contextDataJsonForApi = json_encode($contextData, JSON_UNESCAPED_UNICODE);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                         $responseText = "Внутрішня помилка: не вдалося підготувати дані для аналізу.";
                    } else {
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
            sendTelegramMessage($chatId, $responseText, $telegramToken);
        }

    } catch (Throwable $e) {
        // КЛЮЧОВА ЗМІНА: перехоплюємо будь-яку помилку
        $error_message = sprintf(
            "FATAL ERROR caught in telegram_handler.php: \nType: %s\nMessage: %s\nFile: %s\nLine: %d\nTrace: %s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        custom_log($error_message, 'telegram_error');
        
        // Відповідаємо користувачу, що сталася помилка
        sendTelegramMessage($chatId, "Вибачте, сталася внутрішня помилка сервера. Адміністратора вже сповіщено.", $telegramToken);
    }
} else {
    custom_log('Отримано не-повідомлення або непідтримуваний тип: ' . $input, 'telegram_webhook');
}

http_response_code(200); // Завжди відповідаємо Telegram 200 OK


function sendTelegramMessage(int $chatId, string $text, string $telegramToken): void {
    $allowed_tags = '<b><i><u><s><a><code><pre>';
    $sanitizedResponse = strip_tags($text, $allowed_tags);
    $limit = 4000;
    
    if (mb_strlen($sanitizedResponse, 'UTF-8') > $limit) {
        $messages = preg_split('/(\r\n|\n|\r)/', $sanitizedResponse);
        $currentMessage = '';
        foreach($messages as $line){
            if(mb_strlen($currentMessage . $line . "\n", 'UTF-8') > $limit){
                sendTelegramMessagePart($chatId, $currentMessage, $telegramToken);
                $currentMessage = $line . "\n";
                usleep(500000); 
            } else {
                $currentMessage .= $line . "\n";
            }
        }
        if(!empty(trim($currentMessage))){
            sendTelegramMessagePart($chatId, $currentMessage, $telegramToken);
        }
    } else {
        sendTelegramMessagePart($chatId, $sanitizedResponse, $telegramToken);
    }
}

function sendTelegramMessagePart(int $chatId, string $text, string $telegramToken): void {
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
        custom_log("Telegram API returned HTTP {$httpCode} for Chat ID {$chatId}: " . $response, 'telegram_error');
    }
}
?>
