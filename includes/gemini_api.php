<?php
// includes/gemini_api.php

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
}
define('DATA_DIR', ROOT_DIR . '/data');
define('PROMPTS_DIR', ROOT_DIR . '/prompts');
define('USERS_FILE_PATH', DATA_DIR . '/users.json');
define('QUESTIONS_FILE_PATH', DATA_DIR . '/questions.json');
define('TRAITS_FILE_PATH', DATA_DIR . '/traits.json');
define('BADGES_FILE_PATH', DATA_DIR . '/badges.json');
define('ANSWERS_DIR_PATH', DATA_DIR . '/answers');


if (!function_exists('loadEnv')) {
    require_once ROOT_DIR . '/includes/env-loader.php';
    loadEnv(ROOT_DIR . '/../.env');
}

if (!function_exists('custom_log')) {
    require_once ROOT_DIR . '/includes/functions.php';
}

/**
 * Завантажує шаблон інструкції з файлу.
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
 */
function callGeminiApi(array $messages, string $model = 'gemini-2.5-flash'): ?string {
    $apiKey = getenv('GEMINI_API_KEY');
    if (!$apiKey) {
        custom_log('GEMINI_API_KEY не встановлено в файлі .env. Неможливо викликати Gemini API.', 'gemini_error');
        return null;
    }

    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $payload = [
        'contents' => $messages,
        'generationConfig' => [
            'temperature' => 0.6,
            'maxOutputTokens' => 9000,
        ]
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
         $responseBody = is_string($response) ? $response : json_encode($response);
        custom_log("Gemini API ({$model}) повернув HTTP {$httpCode}: " . $errorDetails . " Відповідь: " . $responseBody, 'gemini_error');
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
 * Визначає, які джерела даних є релевантними для запиту користувача, та уточнює запит (LLM1).
 */
function determineRelevantData(string $userQuery): array {
    $allUsers = readJsonFile(USERS_FILE_PATH);
    $usersForLLM = [];
    if (!empty($allUsers)) {
        foreach ($allUsers as $user) {
            $usersForLLM[] = [
                'username' => $user['username'],
                'first_name' => $user['first_name'] ?? '',
                'last_name' => $user['last_name'] ?? '',
                'hide_results' => $user['hide_results'] ?? false
            ];
        }
    }
    $usersForLLMSubset = array_slice($usersForLLM, 0, 50);

    $allQuestions = readJsonFile(QUESTIONS_FILE_PATH);
    $questionsForLLM = [];
     if (!empty($allQuestions)) {
        foreach ($allQuestions as $category) {
            if (isset($category['questions']) && is_array($category['questions'])) {
                 foreach ($category['questions'] as $question) {
                     $questionsForLLM[] = [
                         'questionId' => $question['questionId'],
                         'q_short' => $question['q_short'] ?? '',
                         'scale' => $question['scale'] ?? null,
                         'categoryName' => $category['categoryName'] ?? ''
                     ];
                 }
            }
        }
    }
    $questionsForLLMSubset = array_slice($questionsForLLM, 0, 100);

    $exampleTraitStructure = "{ \"traitId\": \"t_example\", \"name\": \"Приклад риси\", \"description\": \"Опис...\" }";
    $exampleBadgeStructure = "{ \"badgeId\": \"b_example\", \"name\": \"Приклад бейджа\", \"criteria\": \"Як отримати...\" }";

    $instructionTemplate = loadInstructionFromFile('determine_data_instruction');
    if ($instructionTemplate === false) {
        return ['error' => 'Помилка завантаження інструкції для визначення даних (LLM1).'];
    }

    $systemInstruction = sprintf(
        $instructionTemplate,
        json_encode($usersForLLMSubset, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        json_encode($questionsForLLMSubset, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        $exampleTraitStructure,
        $exampleBadgeStructure
    );

    $messages = [
        ['role' => 'user', 'parts' => [['text' => $systemInstruction . "\n\nЗапит користувача: " . $userQuery]]]
    ];

    custom_log("Request to LLM1:\n" . json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 'gemini_route_request');

    $geminiResponseText = callGeminiApi($messages, 'gemini-2.5-flash');

    custom_log("Response from LLM1:\n" . ($geminiResponseText ?? 'NULL'), 'gemini_route_response');

    if ($geminiResponseText === null) {
        return ['error' => 'Помилка отримання відповіді від ШІ маршрутизатора (LLM1 API error).'];
    }

    $geminiResponseText = preg_replace('/^```(?:json)?\s*(.*?)\s*```$/s', '$1', $geminiResponseText);
    $geminiResponseText = trim($geminiResponseText);
    $geminiResponse = json_decode($geminiResponseText, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        custom_log("Помилка декодування JSON від LLM1: " . json_last_error_msg() . ". Сира відповідь: " . $geminiResponseText, 'gemini_error');
        return ['error' => 'Помилка обробки відповіді від ШІ маршрутизатора (недійсний JSON).'];
    }

    if (!isset($geminiResponse['potential_data_sources']) || !is_array($geminiResponse['potential_data_sources']) ||
        !isset($geminiResponse['target_usernames']) || !is_array($geminiResponse['target_usernames']) ||
        !isset($geminiResponse['refined_query']) || !is_string($geminiResponse['refined_query']))
    {
        custom_log("Невірна структура відповіді LLM1: " . $geminiResponseText, 'gemini_error');
        return ['error' => 'Невірна структура відповіді від ШІ маршрутизатора (відсутні або некоректні поля).'];
    }

    $validUsernames = [];
    $allSystemUsers = readJsonFile(USERS_FILE_PATH);
    if (!empty($allSystemUsers) && in_array('user_answers', $geminiResponse['potential_data_sources']) && !empty($geminiResponse['target_usernames'])) {
        foreach ($geminiResponse['target_usernames'] as $requestedName) {
            $foundCanonicalUsername = null;
            $lowerRequestedName = mb_strtolower(trim($requestedName));

            foreach ($allSystemUsers as $systemUser) {
                 $lowerSystemUsername = mb_strtolower($systemUser['username']);
                 $lowerSystemFirstName = mb_strtolower(trim($systemUser['first_name'] ?? ''));
                 $lowerSystemLastName = mb_strtolower(trim($systemUser['last_name'] ?? ''));

                 if ($lowerSystemUsername === $lowerRequestedName ||
                     (!empty($lowerSystemFirstName) && $lowerSystemFirstName === $lowerRequestedName) ||
                     (!empty($lowerSystemLastName) && $lowerSystemLastName === $lowerRequestedName)) {
                     $foundCanonicalUsername = $systemUser['username'];
                     break;
                 }
             }

            if ($foundCanonicalUsername) {
                if (!in_array($foundCanonicalUsername, $validUsernames)) {
                    $validUsernames[] = $foundCanonicalUsername;
                }
            } else {
                 custom_log("LLM1 suggested username '{$requestedName}' not found in full users list.", 'gemini_route_warning');
            }
        }
         $geminiResponse['target_usernames'] = $validUsernames;
    } else {
        $geminiResponse['target_usernames'] = [];
    }

     if (empty($geminiResponse['potential_data_sources']) && !empty($userQuery) && $geminiResponse['refined_query'] !== "Загальне привітання.") {
         if (!in_array('none', $geminiResponse['potential_data_sources'])) {
             $geminiResponse['potential_data_sources'] = ['none'];
             if (empty($geminiResponse['refined_query']) || $geminiResponse['refined_query'] === $userQuery) {
                 $geminiResponse['refined_query'] = "Не вдалося точно визначити, які дані вам потрібні. Спробуйте переформулювати запит.";
             }
             custom_log("LLM1 returned empty potential_data_sources for query '{$userQuery}', defaulting to 'none'.", 'gemini_route_warning');
         }
     }

    return [
        'potential_data_sources' => $geminiResponse['potential_data_sources'],
        'target_usernames' => $geminiResponse['target_usernames'],
        'refined_query' => $geminiResponse['refined_query']
    ];
}

/**
 * Отримує остаточну відповідь від Gemini на основі уточненого запиту та даних контексту (LLM2).
 */
function getGeminiAnswer(string $refinedQuery, string $contextDataJson): ?string {
    $instructionTemplate = loadInstructionFromFile('get_answer_instruction');
    if ($instructionTemplate === false) {
        return 'Помилка завантаження інструкції для генерації відповіді (LLM2).';
    }

    $maxContextLength = 100000;
    $contextDataJsonShortened = $contextDataJson;
    if (strlen($contextDataJson) > $maxContextLength) {
        $contextDataJsonShortened = substr($contextDataJson, 0, $maxContextLength) . "\n... (дані обрізано через великий розмір)";
        custom_log("Context data for LLM2 was too long (" . strlen($contextDataJson) . " bytes), shortened to {$maxContextLength}.", "gemini_warning");
    }

    $systemInstruction = sprintf(
        $instructionTemplate,
        $contextDataJsonShortened,
        htmlspecialchars($refinedQuery)
    );

    $messages = [
        ['role' => 'user', 'parts' => [['text' => $systemInstruction]]]
    ];

    custom_log("Request to LLM2:\n" . json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 'gemini_answer_request');

    $geminiResponseText = callGeminiApi($messages, 'gemini-2.5-flash');

    custom_log("Response from LLM2:\n" . ($geminiResponseText ?? 'NULL'), 'gemini_answer_response');

    if ($geminiResponseText === null) {
        return 'Вибачте, сталася помилка під час генерації відповіді від ШІ аналізатора (LLM2 API error).';
    }

    $geminiResponseText = preg_replace('/^```(?:html)?\s*(.*?)\s*```$/s', '$1', $geminiResponseText);
    $geminiResponseText = trim($geminiResponseText);

    if (empty($geminiResponseText)) {
         custom_log("LLM2 returned an empty string response.", 'gemini_error');
         return 'Вибачте, ШІ аналізатор не зміг сформувати відповідь на ваш запит.';
    }

    return $geminiResponseText;
}

?>
