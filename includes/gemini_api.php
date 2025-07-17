<?php
// includes/gemini_api.php

if (!defined('ROOT_DIR')) {
    // Визначимо шлях до кореня проекту, якщо він ще не визначений
    define('ROOT_DIR', dirname(__DIR__));
}
// Визначимо шляхи до директорій з даними та промптами
define('DATA_DIR', ROOT_DIR . '/data');
define('PROMPTS_DIR', ROOT_DIR . '/prompts');
// Визначимо шляхи до файлів даних
define('USERS_FILE_PATH', DATA_DIR . '/users.json');
define('QUESTIONS_FILE_PATH', DATA_DIR . '/questions.json'); // Додано
define('TRAITS_FILE_PATH', DATA_DIR . '/traits.json');       // Додано
define('BADGES_FILE_PATH', DATA_DIR . '/badges.json');       // Додано
// Визначимо шлях до директорії з відповідями користувачів
define('ANSWERS_DIR_PATH', DATA_DIR . '/answers');


// Завантажуємо змінні оточення та функції, якщо вони ще не завантажені
if (!function_exists('loadEnv')) {
    require_once ROOT_DIR . '/includes/env-loader.php';
    // Припускаємо, що .env лежить на рівень вище ROOT_DIR
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
            'temperature' => 0.6, // Можна налаштувати "креативність"
            'maxOutputTokens' => 9000, // Жорстке обмеження на кількість токенів у відповіді
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
         $responseBody = is_string($response) ? $response : json_encode($response); // Логуємо тіло відповіді
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
 *
 * @param string $userQuery Оригінальний запит користувача.
 * @return array Повертає асоціативний масив з полями:
 *               - 'potential_data_sources': array, масив ідентифікаторів джерел даних.
 *               - 'target_usernames': array, масив знайдених імен користувачів (порожній, якщо не знайдено або не потрібні).
 *               - 'refined_query': string, уточнений запит для LLM2.
 *               - 'error': string, повідомлення про помилку у разі невдачі.
 */
function determineRelevantData(string $userQuery): array {
    // 1. Підготовка обмежених даних користувачів для LLM1 (username, first_name, last_name, hide_results)
    $allUsers = readJsonFile(USERS_FILE_PATH);
    $usersForLLM = [];
    if (!empty($allUsers)) {
        foreach ($allUsers as $user) {
            // Передаємо тільки необхідні для ідентифікації поля + hide_results (для інформування LLM про його існування)
            $usersForLLM[] = [
                'username' => $user['username'],
                'first_name' => $user['first_name'] ?? '',
                'last_name' => $user['last_name'] ?? '',
                'hide_results' => $user['hide_results'] ?? false
            ];
        }
    }
    // Обмежуємо кількість користувачів, щоб не перевищити ліміт контексту, якщо їх дуже багато
    $usersForLLMSubset = array_slice($usersForLLM, 0, 50); // Обмежимо, наприклад, 50 користувачами

    // 2. Підготовка обмежених даних питань для LLM1 (тільки ID, коротка назва, шкала)
    $allQuestions = readJsonFile(QUESTIONS_FILE_PATH);
    $questionsForLLM = [];
     if (!empty($allQuestions)) {
        foreach ($allQuestions as $category) {
            if (isset($category['questions']) && is_array($category['questions'])) {
                 foreach ($category['questions'] as $question) {
                     $questionsForLLM[] = [
                         'questionId' => $question['questionId'],
                         'q_short' => $question['q_short'] ?? '',
                         'scale' => $question['scale'] ?? null, // Включаємо шкалу
                         'categoryName' => $category['categoryName'] ?? '' // Можливо, назва категорії теж допоможе
                     ];
                 }
            }
        }
    }
     // Обмежуємо кількість питань, якщо їх дуже багато
    $questionsForLLMSubset = array_slice($questionsForLLM, 0, 100); // Обмежимо, наприклад, 100 питаннями


    // 3. Підготовка прикладів структур інших даних (не реальні дані, а опис формату)
    $exampleTraitStructure = "{ \"traitId\": \"t_example\", \"name\": \"Приклад риси\", \"description\": \"Опис...\" }";
    $exampleBadgeStructure = "{ \"badgeId\": \"b_example\", \"name\": \"Приклад бейджа\", \"criteria\": \"Як отримати...\" }";

    // 4. Завантаження та форматування інструкції для LLM1
    $instructionTemplate = loadInstructionFromFile('determine_data_instruction');
    if ($instructionTemplate === false) {
        return ['error' => 'Помилка завантаження інструкції для визначення даних (LLM1).'];
    }

    $systemInstruction = sprintf(
        $instructionTemplate,
        json_encode($usersForLLMSubset, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), // Передаємо обмежений список користувачів
        json_encode($questionsForLLMSubset, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), // Передаємо обмежений список питань
        $exampleTraitStructure, // Приклад структури рис
        $exampleBadgeStructure  // Приклад структури бейджів
    );

    $messages = [
        ['role' => 'user', 'parts' => [['text' => $systemInstruction . "\n\nЗапит користувача: " . $userQuery]]]
    ];

    // Логування запиту до LLM1 в окремий файл
    custom_log("Request to LLM1:\n" . json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 'gemini_route_request');

    // 5. Виклик Gemini API (LLM1)
    $geminiResponseText = callGeminiApi($messages, 'gemini-2.5-flash');

    // Логування відповіді від LLM1 в окремий файл
    custom_log("Response from LLM1:\n" . ($geminiResponseText ?? 'NULL'), 'gemini_route_response');

    if ($geminiResponseText === null) {
        return ['error' => 'Помилка отримання відповіді від ШІ маршрутизатора (LLM1 API error).'];
    }

    // 6. Парсинг та валідація відповіді LLM1
    // Видаляємо можливі блоки коду ```json ... ```
    $geminiResponseText = preg_replace('/^```(?:json)?\s*(.*?)\s*```$/s', '$1', $geminiResponseText);
    $geminiResponseText = trim($geminiResponseText);
    $geminiResponse = json_decode($geminiResponseText, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        custom_log("Помилка декодування JSON від LLM1: " . json_last_error_msg() . ". Сира відповідь: " . $geminiResponseText, 'gemini_error');
        return ['error' => 'Помилка обробки відповіді від ШІ маршрутизатора (недійсний JSON).'];
    }

    // Валідація очікуваної структури відповіді від LLM1
    if (!isset($geminiResponse['potential_data_sources']) || !is_array($geminiResponse['potential_data_sources']) ||
        !isset($geminiResponse['target_usernames']) || !is_array($geminiResponse['target_usernames']) ||
        !isset($geminiResponse['refined_query']) || !is_string($geminiResponse['refined_query']))
    {
        custom_log("Невірна структура відповіді LLM1: " . $geminiResponseText, 'gemini_error');
        return ['error' => 'Невірна структура відповіді від ШІ маршрутизатора (відсутні або некоректні поля).'];
    }

    // 7. Канонізація імен користувачів, визначених LLM1, проти ПОВНОГО списку користувачів
    $validUsernames = [];
    // Завантажуємо ПОВНИЙ список користувачів для точної перевірки та отримання канонічного username
    $allSystemUsers = readJsonFile(USERS_FILE_PATH);
    if (!empty($allSystemUsers) && in_array('user_answers', $geminiResponse['potential_data_sources']) && !empty($geminiResponse['target_usernames'])) {
        foreach ($geminiResponse['target_usernames'] as $requestedName) {
            $foundCanonicalUsername = null;
            $lowerRequestedName = mb_strtolower(trim($requestedName));

            // Шукаємо співпадіння по username, first_name, last_name у ПОВНОМУ списку
            foreach ($allSystemUsers as $systemUser) {
                 $lowerSystemUsername = mb_strtolower($systemUser['username']);
                 $lowerSystemFirstName = mb_strtolower(trim($systemUser['first_name'] ?? ''));
                 $lowerSystemLastName = mb_strtolower(trim($systemUser['last_name'] ?? ''));

                 if ($lowerSystemUsername === $lowerRequestedName ||
                     (!empty($lowerSystemFirstName) && $lowerSystemFirstName === $lowerRequestedName) ||
                     (!empty($lowerSystemLastName) && $lowerSystemLastName === $lowerRequestedName)) {
                     $foundCanonicalUsername = $systemUser['username'];
                     break; // Знайшли перше співпадіння, беремо його як канонічне
                 }
             }

            if ($foundCanonicalUsername) {
                // Додаємо тільки унікальні канонічні імена
                if (!in_array($foundCanonicalUsername, $validUsernames)) {
                    $validUsernames[] = $foundCanonicalUsername;
                }
            } else {
                // Якщо LLM1 назвав ім'я, але ми не знайшли його серед реальних користувачів,
                // просто ігноруємо це ім'я. PHP далі повідомить, якщо жодного користувача не знайдено.
                 custom_log("LLM1 suggested username '{$requestedName}' not found in full users list.", 'gemini_route_warning');
            }
        }
         $geminiResponse['target_usernames'] = $validUsernames; // Повертаємо тільки підтверджені канонічні імена
    } else {
        // Якщо тип даних не 'user_answers' або LLM1 не визначив target_usernames, очищаємо їх
        $geminiResponse['target_usernames'] = [];
    }

    // 8. Перевірка, чи LLM1 не повернув порожні potential_data_sources, якщо запит не був порожнім
     if (empty($geminiResponse['potential_data_sources']) && !empty($userQuery) && $geminiResponse['refined_query'] !== "Загальне привітання." /* простий випадок */) {
         // Якщо LLM1 не зміг визначити джерело даних, але запит не порожній,
         // можливо, варто встановити тип 'none' або повернути помилку.
         // Встановимо 'none' як дефолт і додамо загальне повідомлення.
         if (!in_array('none', $geminiResponse['potential_data_sources'])) {
             $geminiResponse['potential_data_sources'] = ['none'];
             if (empty($geminiResponse['refined_query']) || $geminiResponse['refined_query'] === $userQuery) {
                 $geminiResponse['refined_query'] = "Не вдалося точно визначити, які дані вам потрібні. Спробуйте переформулювати запит.";
             }
             custom_log("LLM1 returned empty potential_data_sources for query '{$userQuery}', defaulting to 'none'.", 'gemini_route_warning');
         }
     }


    // 9. Повертаємо результат маршрутизації
    return [
        'potential_data_sources' => $geminiResponse['potential_data_sources'],
        'target_usernames' => $geminiResponse['target_usernames'],
        'refined_query' => $geminiResponse['refined_query']
    ];
}

/**
 * Отримує остаточну відповідь від Gemini на основі уточненого запиту та даних контексту (LLM2).
 *
 * @param string $refinedQuery Уточнений запит від LLM1.
 * @param string $contextDataJson JSON-рядок з усіма завантаженими даними.
 * @return string|null Сгенерована відповідь або null у разі помилки.
 */
function getGeminiAnswer(string $refinedQuery, string $contextDataJson): ?string {
    // 1. Підготовка інструкції для LLM2
    $instructionTemplate = loadInstructionFromFile('get_answer_instruction');
    if ($instructionTemplate === false) {
        return 'Помилка завантаження інструкції для генерації відповіді (LLM2).';
    }

    // Обмежуємо розмір контексту, щоб не перевищити ліміт токенів API
    // gemini-2.5-flash має 128k контекстне вікно. 100k - безпечно.
    $maxContextLength = 100000;
    $contextDataJsonShortened = $contextDataJson;
    if (strlen($contextDataJson) > $maxContextLength) {
        $contextDataJsonShortened = substr($contextDataJson, 0, $maxContextLength) . "\n... (дані обрізано через великий розмір)";
        custom_log("Context data for LLM2 was too long (" . strlen($contextDataJson) . " bytes), shortened to {$maxContextLength}.", "gemini_warning");
    }

    // Форматування інструкції для LLM2
    // Опис даних та запит вставляються в промпт
    $systemInstruction = sprintf(
        $instructionTemplate,
        $contextDataJsonShortened, // Надаємо JSON-контекст (можливо, обрізаний)
        htmlspecialchars($refinedQuery) // Надаємо уточнений запит, екрануємо HTML
    );

    $messages = [
        ['role' => 'user', 'parts' => [['text' => $systemInstruction]]]
    ];

     // Логування запиту до LLM2 в окремий файл
    custom_log("Request to LLM2:\n" . json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 'gemini_answer_request');

    // 2. Виклик Gemini API (LLM2)
    $geminiResponseText = callGeminiApi($messages, 'gemini-2.5-flash');

    // Логування відповіді від LLM2 в окремий файл
    custom_log("Response from LLM2:\n" . ($geminiResponseText ?? 'NULL'), 'gemini_answer_response');

    if ($geminiResponseText === null) {
        return 'Вибачте, сталася помилка під час генерації відповіді від ШІ аналізатора (LLM2 API error).';
    }

    // 3. Обробка відповіді LLM2 (видалення можливих блоків коду)
    // LLM2 інструктований повертати HTML або просто текст, але може випадково обернути в ```html
    $geminiResponseText = preg_replace('/^```(?:html)?\s*(.*?)\s*```$/s', '$1', $geminiResponseText);
    $geminiResponseText = trim($geminiResponseText);

    // LLM2 може повернути пусту відповідь (що вважаємо помилкою) або відповідь, заблоковану безпекою
    // (це вже обробляється в callGeminiApi, яка поверне null або повідомлення про блок)
    if (empty($geminiResponseText)) {
         custom_log("LLM2 returned an empty string response.", 'gemini_error');
         return 'Вибачте, ШІ аналізатор не зміг сформувати відповідь на ваш запит.';
    }

    return $geminiResponseText;
}


// --- Функції loadUserData та інші, ймовірно, вже існують у includes/functions.php ---
// Ми припускаємо, що loadUserData існує і її логіка (перевірка hide_results, isAdminRequest)
// НЕ ЗМІНЮВАЛАСЬ, бо вона КРИТИЧНО важлива для безпеки та приватності на етапі завантаження даних.
// Ми додали визначення констант шляхів на початку цього файлу, щоб loadUserData могла їх використовувати.
// Якщо loadUserData також визначає ці константи, можна прибрати повторне визначення.
if (!function_exists('loadUserData')) {
     // Це заглушка, якщо loadUserData відсутня в functions.php.
     // У реальному проекті, вона має бути в functions.php і мати повну логіку.
     function loadUserData(string $username, bool $isAdminRequest = false): array {
        // Це спрощена версія для прикладу, повна має бути в functions.php
        custom_log("Викликано заглушку loadUserData для користувача '{$username}'. Переконайтесь, що функція loadUserData визначена в includes/functions.php", 'gemini_error');
        $filePath = ANSWERS_DIR_PATH . '/' . $username . '.json';
        if (file_exists($filePath)) {
             $data = readJsonFile($filePath);
             // Тут має бути логіка перевірки $isAdminRequest та $targetUser['hide_results']
             $data['username_queried'] = $username; // Додаємо ім'я для LLM2
             return ['success' => true, 'message' => "Дані завантажено (заглушка).", 'data' => $data];
        }
        return ['success' => false, 'message' => "Файл даних не знайдено (заглушка).", 'data' => []];
     }
}

// Функції readJsonFile, writeJsonFile, generateUniqueId, custom_log, mergeUsers
// мають знаходитись у includes/functions.php
// Переконайтесь, що includes/functions.php підключено перед gemini_api.php,
// якщо gemini_api.php потребує функцій з functions.php (як readJsonFile, custom_log).

?>
