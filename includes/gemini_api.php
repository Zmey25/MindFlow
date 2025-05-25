<?php
// includes/gemini_api.php

// Перевіряємо, чи визначено ROOT_DIR. Якщо ні, визначаємо його.
// Це важливо для правильного формування шляхів до файлів даних.
if (!defined('ROOT_DIR')) {
    // Припускаємо, що цей файл знаходиться в піддиректорії 'includes' відносно кореня проекту.
    define('ROOT_DIR', dirname(__DIR__));
}

// Завантажуємо змінні оточення, якщо вони ще не завантажені.
// Це гарантує доступність GEMINI_API_KEY.
if (!function_exists('loadEnv')) {
    require_once ROOT_DIR . '/includes/env-loader.php';
    loadEnv(ROOT_DIR . '/../.env');
}

// Завантажуємо загальні функції, такі як custom_log та readJsonFile.
if (!function_exists('custom_log')) {
    require_once ROOT_DIR . '/includes/functions.php';
}

/**
 * Здійснює HTTP-запит до Google Gemini API.
 *
 * @param array $messages Масив повідомлень для моделі (role, parts).
 * @param string $model Модель Gemini для використання (за замовчуванням 'gemini-pro').
 * @return string|null Згенерований текстовий вміст від Gemini, або null у разі помилки.
 */
function callGeminiApi(array $messages, string $model = 'gemini-2.5-flash-preview-05-20'): ?string {
    $apiKey = getenv('GEMINI_API_KEY');
    if (!$apiKey) {
        custom_log('GEMINI_API_KEY не встановлено в файлі .env. Неможливо викликати Gemini API.', 'gemini_error');
        return null;
    }

    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $payload = [
        'contents' => $messages
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Встановлюємо тайм-аут на 60 секунд

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        custom_log("cURL помилка при виклику Gemini API: " . $curlError, 'gemini_error');
        return null;
    }

    $responseData = json_decode($response, true);

    if ($httpCode !== 200) {
        $errorDetails = $responseData['error']['message'] ?? 'Невідома помилка';
        custom_log("Gemini API повернув HTTP {$httpCode}: " . $errorDetails . " Відповідь: " . $response, 'gemini_error');
        return null;
    }

    // Видобуваємо текст з відповіді
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        return $responseData['candidates'][0]['content']['parts'][0]['text'];
    } elseif (isset($responseData['promptFeedback']['blockReason'])) {
        $reason = $responseData['promptFeedback']['blockReason'];
        custom_log("Gemini API заблокував відповідь через: " . $reason . " Запит: " . json_encode($messages), 'gemini_safety_block');
        return "Вибачте, ваш запит був заблокований через порушення правил безпеки або конфіденційності. Будь ласка, спробуйте переформулювати його.";
    } else {
        custom_log("Неочікувана структура відповіді Gemini API: " . $response, 'gemini_error');
        return null;
    }
}

/**
 * Визначає, яке джерело даних є релевантним для запиту користувача, та уточнює запит.
 * Це перший виклик LLM (маршрутизатор/вибір файлу).
 *
 * @param string $userQuery Оригінальне питання від користувача.
 * @return array Асоціативний масив з 'file_type', 'target_username', 'follow_up_query', 'error'.
 */
function determineRelevantData(string $userQuery): array {
    // Завантажуємо лише необхідні дані для підказки LLM
    $allUsers = readJsonFile(ROOT_DIR . '/data/users.json');
    $questionsSample = readJsonFile(ROOT_DIR . '/data/questions.json');
    $traitsSample = readJsonFile(ROOT_DIR . '/data/traits.json');
    $badgesSample = readJsonFile(ROOT_DIR . '/data/badges.json');

    // Готуємо стислий список користувачів для LLM
    $usersForLLM = [];
    foreach ($allUsers as $user) {
        $usersForLLM[] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? ''
        ];
    }
    // Обмежуємо розмір JSON, щоб уникнути лімітів токенів
    $usersForLLM = array_slice($usersForLLM, 0, 50); // Приклад: обмежуємо до 50 користувачів

    // Готуємо ID питань, рис, бейджів для LLM
    $questionIds = [];
    foreach ($questionsSample as $category) {
        foreach ($category['questions'] as $q) {
            $questionIds[] = $q['questionId'];
        }
    }

    $traitIds = [];
    foreach ($traitsSample as $trait) {
        $traitIds[] = $trait['traitId'];
    }

    $badgeIds = [];
    foreach ($badgesSample as $badge) {
        $badgeIds[] = $badge['badgeId'];
    }


    $systemInstruction = "Ти є інтелектуальним асистентом для веб-додатку, який надає аналіз особистісних характеристик. Твоє завдання – визначити, який тип даних найбільш релевантний для відповіді на запитання користувача та сформулювати уточнений запит для подальшої обробки.

Доступні типи даних:
- `users`: Інформація про користувачів (ім'я, прізвище, логін, ID).
- `questions`: Перелік питань, які використовуються в анкеті (ID питань, короткі описи, категорії).
- `traits`: Визначення особистісних рис.
- `badges`: Інформація про досягнення та бейджи.
- `about`: Загальна інформація про проект.
- `dashboard_warning`: Попередження, що відображається на панелі інструментів.
- `user_answers`: Детальні відповіді та розрахунки характеристик для конкретного користувача.

Важливо: Якщо запит стосується конкретного користувача (наприклад, 'покажи результати Змея', 'які відповіді у користувача l_guerro'), ти повинен визначити його `username` з наданого списку користувачів. Ти повинен бути дуже уважним до можливих неточностей у запитах користувачів, які можуть стосуватися імен або прізвищ, а не лише логінів.

Формат відповіді: JSON об'єкт.
Приклад:
```json
{
    \"file_type\": \"user_answers\",
    \"target_username\": \"Zmey\",
    \"follow_up_query\": \"Які показники пам'яті у Zmey?\"
}

Якщо запит загальний і не стосується конкретного файлу або користувача, але може бути відповідь за допомогою загальних знань:

{
    \"file_type\": \"none\",
    \"target_username\": null,
    \"follow_up_query\": \"Що таке інтелект?\"
}

Якщо запит стосується загальної інформації про проект (наприклад, 'що це за проект?', 'розкажи про додаток'):

{
    \"file_type\": \"about\",
    \"target_username\": null,
    \"follow_up_query\": \"Розкажи загалом про проект.\"
}

Якщо запит стосується питань:

{
    \"file_type\": \"questions\",
    \"target_username\": null,
    \"follow_up_query\": \"Які питання є в категорії 'Інтелект'?\"
}

Якщо запит стосується конкретного питання за ID (наприклад, 'що таке q_memory?'):

{
    \"file_type\": \"questions\",
    \"target_username\": null,
    \"follow_up_query\": \"Опиши питання q_memory.\"
}

Якщо запит стосується конкретного імені користувача або прізвища, спробуй зіставити його з наданим списком користувачів і повернути його username.

Будь максимально точним у визначенні file_type та target_username.

Доступні користувачі (username, first_name, last_name, id):
" . json_encode($usersForLLM, JSON_UNESCAPED_UNICODE) . "

Приклад ID питань: " . json_encode(array_slice($questionIds, 0, 10)) . " (лише перші 10 для прикладу)
Приклад ID рис: " . json_encode(array_slice($traitIds, 0, 10)) . "
Приклад ID бейджів: " . json_encode(array_slice($badgeIds, 0, 10)) . "
";

$messages = [
    ['role' => 'user', 'parts' => ['text' => $systemInstruction . "\n\nЗапит користувача: " . $userQuery]]
];

$geminiResponseText = callGeminiApi($messages);

if ($geminiResponseText === null) {
    return ['error' => 'Помилка визначення релевантних даних.'];
}

// --- НОВЕ: Очищуємо відповідь від Markdown-блоків ---
// Це регулярний вираз, який шукає початок (```json або просто ```) і кінець (```) блоку коду,
// а потім витягує вміст всередині. Модифікатор 's' дозволяє '.' відповідати новим рядкам.
$geminiResponseText = preg_replace('/^```(?:json)?\s*(.*?)\s*```$/s', '$1', $geminiResponseText);
$geminiResponseText = trim($geminiResponseText); // Видаляємо зайві пробіли/нові рядки

$geminiResponse = json_decode($geminiResponseText, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    custom_log("Помилка декодування LLM1 JSON відповіді: " . json_last_error_msg() . " Сира відповідь: " . $geminiResponseText, 'gemini_error');
    return ['error' => 'Помилка обробки відповіді від ШІ.'];
}

// Валідація структури відповіді
if (!isset($geminiResponse['file_type']) || !isset($geminiResponse['follow_up_query'])) {
    custom_log("Невірна структура LLM1 відповіді: " . $geminiResponseText, 'gemini_error');
    return ['error' => 'Невірна структура відповіді від ШІ.'];
}

// Якщо `target_username` надано, перевіряємо, чи він відповідає існуючому користувачу
if ($geminiResponse['file_type'] === 'user_answers' && isset($geminiResponse['target_username'])) {
    $foundUser = null;
    foreach ($allUsers as $user) {
        // Перевіряємо за логіном, ID, ім'ям або прізвищем (без урахування регістру)
        if (mb_strtolower($user['username']) === mb_strtolower($geminiResponse['target_username']) ||
            mb_strtolower($user['id']) === mb_strtolower($geminiResponse['target_username']) ||
            (isset($user['first_name']) && mb_strtolower($user['first_name']) === mb_strtolower($geminiResponse['target_username'])) ||
            (isset($user['last_name']) && mb_strtolower($user['last_name']) === mb_strtolower($geminiResponse['target_username']))) {
            $foundUser = $user;
            break;
        }
    }
    if (!$foundUser) {
        custom_log("LLM1 запросив user_answers для неіснуючого користувача: " . $geminiResponse['target_username'], 'gemini_warning');
        // Якщо користувача не знайдено, переходимо до типу 'none' і даємо повідомлення про помилку
        $geminiResponse['file_type'] = 'none';
        $geminiResponse['target_username'] = null;
        $geminiResponse['follow_up_query'] = "Користувача з іменем або логіном '{$geminiResponse['target_username']}' не знайдено. Будь ласка, уточніть запит або оберіть зі списку існуючих користувачів.";
    } else {
         $geminiResponse['target_username'] = $foundUser['username']; // Використовуємо правильний логін для подальшого завантаження файлу
    }
}


return $geminiResponse;

}

/**

    Отримує остаточну відповідь від Gemini на основі уточненого запиту та даних контексту.
    Це другий виклик LLM (обробник даних).
    @param string $refinedQuery Запит, уточнений першим LLM.
    @param string $contextDataJson JSON-рядок вмісту релевантного файлу.
    @return string|null Остаточна відповідь від Gemini, або null у разі помилки.
    */
    function getGeminiAnswer(string $refinedQuery, string $contextDataJson): ?string {
    $systemInstruction = "Ти є інтелектуальним асистентом, який відповідає на запитання, використовуючи надану інформацію.
    Надані дані є у форматі JSON. Твоя відповідь повинна бути чіткою, лаконічною та ґрунтуватися виключно на наданому контексті.
    Якщо інформації для відповіді немає, вкажи це прямо. Не вигадуй інформацію.
    Форматуй відповіді для Telegram, використовуючи HTML, наприклад, для жирного тексту або списків.

Ось дані:

" . $contextDataJson . "

Запит: " . $refinedQuery;

$messages = [
    ['role' => 'user', 'parts' => ['text' => $systemInstruction]]
];

$geminiResponseText = callGeminiApi($messages);

if ($geminiResponseText === null) {
    return 'Виникла помилка під час отримання відповіді від ШІ.';
}

// --- НОВЕ: Очищуємо відповідь від Markdown-блоків для другого LLM також ---
$geminiResponseText = preg_replace('/^```(?:json)?\s*(.*?)\s*```$/s', '$1', $geminiResponseText);
$geminiResponseText = trim($geminiResponseText);

return $geminiResponseText;

}

// Перевизначаємо або забезпечуємо доступність loadUserData,
// оскільки вона використовується у determineRelevantData та telegram_handler.
// Якщо functions.php вже завантажено, ця функція не буде перестворена завдяки if (!function_exists).
if (!function_exists('loadUserData')) {
function loadUserData(string $username): array {
$filePath = ROOT_DIR . '/data/answers/' . $username . '.json';
return readJsonFile($filePath);
}
}
