<?php
// includes/gemini_api.php

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
}
// Визначимо шлях до директорії з промптами
define('PROMPTS_DIR', ROOT_DIR . '/prompts');


if (!function_exists('loadEnv')) {
    require_once ROOT_DIR . '/includes/env-loader.php';
    loadEnv(ROOT_DIR . '/../.env');
}

if (!function_exists('custom_log')) {
    require_once ROOT_DIR . '/includes/functions.php';
}

/**
 * Завантажує шаблон інструкції з файлу.
 *
 * @param string $instructionFileName Назва файлу інструкції (наприклад, 'determine_data_instruction').
 * @return string|false Вміст файлу або false у разі помилки.
 */
function loadInstructionFromFile(string $instructionFileName): string|false {
    $filePath = PROMPTS_DIR . '/' . $instructionFileName . '.txt';
    if (!file_exists($filePath)) {
        custom_log("Файл інструкції не знайдено: {$filePath}", 'gemini_error');
        return false;
    }
    $instruction = file_get_contents($filePath);
    if ($instruction === false) {
        custom_log("Не вдалося прочитати файл інструкції: {$filePath}", 'gemini_error');
        return false;
    }
    return $instruction;
}

/**
 * Здійснює HTTP-запит до Google Gemini API.
 * (Залишається без змін)
 */
function callGeminiApi(array $messages, string $model = 'gemini-2.5-flash-preview-05-20'): ?string {
    $apiKey = getenv('GEMINI_API_KEY');
    if (!$apiKey) {
        custom_log('GEMINI_API_KEY не встановлено в файлі .env. Неможливо викликати Gemini API.', 'gemini_error');
        return null;
    }

    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $payload = [
        'contents' => $messages,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        custom_log("cURL помилка при виклику Gemini API ({$model}): " . $curlError, 'gemini_error');
        return null;
    }

    $responseData = json_decode($response, true);

    if ($httpCode !== 200) {
        $errorDetails = $responseData['error']['message'] ?? 'Невідома помилка від API';
        custom_log("Gemini API ({$model}) повернув HTTP {$httpCode}: " . $errorDetails . " Відповідь: " . $response, 'gemini_error');
        return null;
    }

    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        return $responseData['candidates'][0]['content']['parts'][0]['text'];
    } elseif (isset($responseData['promptFeedback']['blockReason'])) {
        $reason = $responseData['promptFeedback']['blockReason'];
        $safetyRatings = $responseData['promptFeedback']['safetyRatings'] ?? [];
        custom_log("Gemini API ({$model}) заблокував відповідь через: " . $reason . ". Ratings: ".json_encode($safetyRatings). " Запит: " . json_encode($messages), 'gemini_safety_block');
        return "Вибачте, ваш запит не може бути оброблений через обмеження безпеки. Спробуйте переформулювати його.";
    } else {
        custom_log("Неочікувана структура відповіді Gemini API ({$model}): " . $response, 'gemini_error');
        return null;
    }
}

/**
 * Визначає, яке джерело даних є релевантним для запиту користувача, та уточнює запит.
 */
function determineRelevantData(string $userQuery): array {
    $allUsers = readJsonFile(ROOT_DIR . '/data/users.json');
    $usersForLLM = [];
    foreach ($allUsers as $user) {
        // --- NEW: Filter out hidden users for LLM if not admin, or just include all and let loadUserData handle it.
        // For determineRelevantData, we can still provide a list of existing users,
        // but loadUserData will apply the 'hide_results' logic.
        $usersForLLM[] = [
            'username' => $user['username'],
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? ''
        ];
    }
    $usersForLLM = array_slice($usersForLLM, 0, 30);

    $exampleQuestionStructure = "{ \"questionId\": \"q_example\", \"text\": \"Приклад питання...\", \"category\": \"Приклад категорії\" }";
    $exampleTraitStructure = "{ \"traitId\": \"t_example\", \"name\": \"Приклад риси\", \"description\": \"Опис...\" }";
    $exampleBadgeStructure = "{ \"badgeId\": \"b_example\", \"name\": \"Приклад бейджа\", \"criteria\": \"Як отримати...\" }";

    $instructionTemplate = loadInstructionFromFile('determine_data_instruction');
    if ($instructionTemplate === false) {
        return ['error' => 'Помилка завантаження інструкції для визначення даних.'];
    }

    $systemInstruction = sprintf(
        $instructionTemplate,
        json_encode($usersForLLM, JSON_UNESCAPED_UNICODE),
        $exampleQuestionStructure,
        $exampleTraitStructure,
        $exampleBadgeStructure
    );

    $messages = [
        ['role' => 'user', 'parts' => [['text' => $systemInstruction . "\n\nЗапит користувача: " . $userQuery]]]
    ];

    $geminiResponseText = callGeminiApi($messages, 'gemini-2.5-flash-preview-05-20');

    if ($geminiResponseText === null) {
        return ['error' => 'Помилка визначення релевантних даних (LLM1).'];
    }

    $geminiResponseText = preg_replace('/^```(?:json)?\s*(.*?)\s*```$/s', '$1', $geminiResponseText);
    $geminiResponseText = trim($geminiResponseText);
    $geminiResponse = json_decode($geminiResponseText, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        custom_log("Помилка декодування LLM1 JSON: " . json_last_error_msg() . ". Сира відповідь: " . $geminiResponseText, 'gemini_error');
        return ['error' => 'Помилка обробки відповіді від ШІ маршрутизатора (невірний JSON).'];
    }

    if (!isset($geminiResponse['file_type']) || !isset($geminiResponse['follow_up_query']) || !isset($geminiResponse['target_usernames']) || !is_array($geminiResponse['target_usernames'])) {
        custom_log("Невірна структура LLM1 відповіді: " . $geminiResponseText, 'gemini_error');
        return ['error' => 'Невірна структура відповіді від ШІ маршрутизатора (відсутні/некоректні поля).'];
    }

    // Валідація `target_usernames` та приведення до канонічних імен
    $validUsernames = [];
    $notFoundUsernames = [];
    if ($geminiResponse['file_type'] === 'user_answers' && !empty($geminiResponse['target_usernames'])) {
        foreach ($geminiResponse['target_usernames'] as $requestedName) {
            $foundCanonicalUsername = null;
            foreach ($allUsers as $systemUser) {
                if (mb_strtolower($systemUser['username']) === mb_strtolower($requestedName) ||
                    (isset($systemUser['first_name']) && !empty($systemUser['first_name']) && mb_strtolower($systemUser['first_name']) === mb_strtolower($requestedName)) ||
                    (isset($systemUser['last_name']) && !empty($systemUser['last_name']) && mb_strtolower($systemUser['last_name']) === mb_strtolower($requestedName)) ) {
                    $foundCanonicalUsername = $systemUser['username'];
                    break;
                }
            }
            if ($foundCanonicalUsername) {
                if (!in_array($foundCanonicalUsername, $validUsernames)) {
                    $validUsernames[] = $foundCanonicalUsername;
                }
            } else {
                $notFoundUsernames[] = $requestedName;
            }
        }

        if (!empty($notFoundUsernames)) {
            $errorMsgPart = "Не вдалося знайти користувачів: " . implode(', ', $notFoundUsernames) . ".";
            if (empty($validUsernames)) {
                $geminiResponse['file_type'] = 'none';
                $geminiResponse['target_usernames'] = [];
                $geminiResponse['follow_up_query'] = $errorMsgPart . " Будь ласка, уточніть імена.";
            } else {
                if (count($geminiResponse['target_usernames']) > 1 && count($validUsernames) < 2) {
                     $geminiResponse['file_type'] = 'none';
                     $geminiResponse['target_usernames'] = [];
                     $geminiResponse['follow_up_query'] = $errorMsgPart . " Порівняння неможливе. Уточніть імена.";
                } else {
                     $geminiResponse['target_usernames'] = $validUsernames;
                }
            }
        }
        $geminiResponse['target_usernames'] = $validUsernames;
    }

    if ($geminiResponse['file_type'] === 'user_answers' && empty($geminiResponse['target_usernames'])) {
        if (strpos($geminiResponse['follow_up_query'], "Не вдалося знайти") === false && strpos($geminiResponse['follow_up_query'], "Порівняння неможливе") === false) {
            $geminiResponse['follow_up_query'] = "Було запитано дані користувача(ів), але імена не розпізнано або вказано невірно. Уточніть запит.";
        }
    }

    return $geminiResponse;
}

/**
 * Отримує остаточну відповідь від Gemini на основі уточненого запиту та даних контексту.
 */
function getGeminiAnswer(string $refinedQuery, string $contextDataJson): ?string {
    $parsedContext = json_decode($contextDataJson, true);
    $contextDescription = "";

    if (isset($parsedContext['user1_data']) && isset($parsedContext['user2_data'])) {
        $user1 = htmlspecialchars($parsedContext['user1_username'] ?? 'Користувач 1');
        $user2 = htmlspecialchars($parsedContext['user2_username'] ?? 'Користувач 2');
        $contextDescription = "Надано дані для ПОРІВНЯННЯ двох користувачів: {$user1} та {$user2}. " .
                              "У кожного користувача можуть бути `self_answers` (самооцінка) та `other_answers` (оцінка іншими). " .
                              "При порівнянні звертай увагу на ОБИДВА типи відповідей для кожного, якщо вони присутні. \nДані:\n";
    } elseif (isset($parsedContext['username_queried']) && (isset($parsedContext['answers']) || isset($parsedContext['self_answers']) || isset($parsedContext['other_answers'])) ) { // username_queried додається в loadUserData
        $username = htmlspecialchars($parsedContext['username_queried']);
        $contextDescription = "Надано дані користувача '{$username}'. " .
                              "Дані містять `self_answers` (самооцінка) та `other_answers` (оцінка іншими). " .
                              "Аналізуючи, детально розглянь ОБИДВА типи відповідей, якщо вони є. \nДані:\n";
    } elseif (is_array($parsedContext) && !empty($parsedContext) && isset($parsedContext[0]['username'])) {
        $contextDescription = "Надано список користувачів системи. \nДані:\n";
    } elseif (is_array($parsedContext) && !empty($parsedContext) && isset($parsedContext[0]['categoryId'])) {
        $contextDescription = "Надано структуру питань тестування. \nДані:\n";
    } elseif (is_array($parsedContext) && !empty($parsedContext) && isset($parsedContext[0]['traitId'])) {
        $contextDescription = "Надано список особистісних рис. \nДані:\n";
    } elseif (is_array($parsedContext) && !empty($parsedContext) && isset($parsedContext[0]['badgeId'])) {
        $contextDescription = "Надано список бейджів. \nДані:\n";
    } else {
        $contextDescription = "Надані наступні дані (якщо вони не порожні): \n";
    }

    $maxContextLength = 100000;
    if (strlen($contextDataJson) > $maxContextLength) {
        $contextDataJsonShortened = substr($contextDataJson, 0, $maxContextLength) . "\n... (дані обрізано через великий розмір)";
        custom_log("Context data for LLM2 was too long, shortened. Original length: " . strlen($contextDataJson), "gemini_warning");
    } else {
        $contextDataJsonShortened = $contextDataJson;
    }

    $instructionTemplate = loadInstructionFromFile('get_answer_instruction');
    if ($instructionTemplate === false) {
        return 'Помилка завантаження інструкції для генерації відповіді.';
    }

    $systemInstruction = sprintf(
        $instructionTemplate,
        $contextDescription,
        $contextDataJsonShortened,
        htmlspecialchars($refinedQuery) // Екрануємо запит для безпеки при вставці
    );

    $messages = [
        ['role' => 'user', 'parts' => [['text' => $systemInstruction]]]
    ];

    $geminiResponseText = callGeminiApi($messages, 'gemini-2.5-flash-preview-05-20');

    if ($geminiResponseText === null) {
        return 'Вибачте, сталася помилка під час генерації відповіді від ШІ (LLM2).';
    }

    $geminiResponseText = preg_replace('/^```(?:json|html)?\s*(.*?)\s*```$/s', '$1', $geminiResponseText);
    $geminiResponseText = trim($geminiResponseText);

    return $geminiResponseText;
}

if (!function_exists('loadUserData')) {
    /**
     * Завантажує дані відповідей користувача.
     *
     * @param string $username Ім'я користувача, чиї дані потрібно завантажити.
     * @param bool $isAdminRequest Прапорець, що вказує, чи запит надходить від адміністратора.
     * @return array Повертає асоціативний масив:
     *               - 'success': bool, true якщо дані завантажено успішно.
     *               - 'data': array, завантажені дані користувача або порожній масив у разі невдачі.
     *               - 'message': string, повідомлення про результат (успіх або помилка).
     */
    function loadUserData(string $username, bool $isAdminRequest = false): array {
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            custom_log("Спроба завантажити дані для некоректного імені користувача: '{$username}'", 'security_warning');
            return ['success' => false, 'message' => "Некоректне ім'я користувача.", 'data' => []];
        }

        // --- NEW: Read users.json to check 'hide_results' ---
        $allUsers = readJsonFile(ROOT_DIR . '/data/users.json');
        $targetUser = null;
        foreach ($allUsers as $user) {
            if ($user['username'] === $username) {
                $targetUser = $user;
                break;
            }
        }

        if (!$targetUser) {
            custom_log("Користувача '{$username}' не знайдено в users.json.", 'user_error');
            return ['success' => false, 'message' => "Користувача '{$username}' не знайдено.", 'data' => []];
        }

        // Check for hidden results if not an admin request
        if (isset($targetUser['hide_results']) && $targetUser['hide_results'] === true && !$isAdminRequest) {
            custom_log("Спроба доступу до прихованих результатів користувача '{$username}' не-адміном.", 'security_warning');
            return ['success' => false, 'message' => "Результати користувача '{$username}' приховані.", 'data' => []];
        }

        $filePath = ROOT_DIR . '/data/answers/' . $username . '.json';
        if (file_exists($filePath)) {
            $data = readJsonFile($filePath);
            if (empty($data)) {
                 custom_log("Файл даних для користувача '{$username}' порожній або не вдалося прочитати: {$filePath}", 'file_error');
                 return ['success' => false, 'message' => "Файл даних для користувача '{$username}' порожній або пошкоджений.", 'data' => []];
            }
            $data['username_queried'] = $username;
            return ['success' => true, 'message' => 'Дані завантажено успішно.', 'data' => $data];
        } else {
            custom_log("Файл даних для користувача '{$username}' не знайдено: {$filePath}", 'file_error');
            return ['success' => false, 'message' => "Дані для користувача '{$username}' не знайдено. Можливо, він не проходив тест.", 'data' => []];
        }
    }
}
?>
