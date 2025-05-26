      
<?php
// includes/gemini_api.php

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
}

if (!function_exists('loadEnv')) {
    require_once ROOT_DIR . '/includes/env-loader.php';
    loadEnv(ROOT_DIR . '/../.env'); // Або ROOT_DIR . '/.env'
}

if (!function_exists('custom_log')) {
    require_once ROOT_DIR . '/includes/functions.php';
}

/**
 * Здійснює HTTP-запит до Google Gemini API.
 * (Залишається без змін, але додано модель за замовчуванням з вашого коду)
 */
function callGeminiApi(array $messages, string $model = 'gemini-2.5-flash-preview-05-20'): ?string { // gemini-1.5-flash-latest або gemini-1.5-pro-latest
    $apiKey = getenv('GEMINI_API_KEY');
    if (!$apiKey) {
        custom_log('GEMINI_API_KEY не встановлено в файлі .env. Неможливо викликати Gemini API.', 'gemini_error');
        return null;
    }

    // $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    // Оновлення: Gemini 1.5 Flash та Pro використовують інший формат URL (без beta для generateContent)
    // Для моделей gemini-pro (стабільних, не preview) v1beta залишається.
    // Уточнимо, яка модель використовується. Якщо це 'gemini-1.5-flash-preview-0514', то:
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    // Якщо модель типу 'gemini-pro', то було б v1beta
    // $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";


    $payload = [
        'contents' => $messages,
        // Додаткові параметри для кращого контролю, якщо потрібно:
        // 'generationConfig' => [
        //     'temperature' => 0.7, // Креативність
        //     'topK' => 40,
        //     'topP' => 0.95,
        //     'maxOutputTokens' => 2048, // Обмеження на довжину відповіді
        // ],
        // 'safetySettings' => [ // Налаштування безпеки
        //     [ 'category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE' ],
        //     [ 'category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE' ],
        //     [ 'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE' ],
        //     [ 'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE' ],
        // ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90); // Збільшено тайм-аут для LLM

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
 *
 * @param string $userQuery Оригінальне питання від користувача.
 * @return array Асоціативний масив з 'file_type', 'target_usernames' (масив), 'follow_up_query', 'error'.
 */
function determineRelevantData(string $userQuery): array {
    $allUsers = readJsonFile(ROOT_DIR . '/data/users.json');
    // Для економії токенів, можемо передати лише обмежену кількість прикладів ID або їх структуру
    // $questionsSample = readJsonFile(ROOT_DIR . '/data/questions.json');
    // $traitsSample = readJsonFile(ROOT_DIR . '/data/traits.json');
    // $badgesSample = readJsonFile(ROOT_DIR . '/data/badges.json');

    $usersForLLM = [];
    foreach ($allUsers as $user) {
        $usersForLLM[] = [
            'username' => $user['username'], // Основне поле для ідентифікації
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? ''
            // 'id' можна прибрати, якщо ідентифікація в основному за username/name
        ];
    }
    // Обмежуємо розмір JSON, щоб уникнути лімітів токенів
    $usersForLLM = array_slice($usersForLLM, 0, 30); // Зменшено для економії

    $exampleQuestionStructure = "{ \"questionId\": \"q_example\", \"text\": \"Приклад питання...\", \"category\": \"Приклад категорії\" }";
    $exampleTraitStructure = "{ \"traitId\": \"t_example\", \"name\": \"Приклад риси\", \"description\": \"Опис...\" }";
    $exampleBadgeStructure = "{ \"badgeId\": \"b_example\", \"name\": \"Приклад бейджа\", \"criteria\": \"Як отримати...\" }";


    $systemInstruction = "Ти – інтелектуальний маршрутизатор для Telegram-бота, що аналізує дані психологічних тестів.
Твоє завдання:
1. Проаналізувати запит користувача.
2. Визначити тип необхідних даних (`file_type`).
3. Якщо запит стосується користувачів, ідентифікувати їх `username` (одного або двох для порівняння) зі списку. `target_usernames` має бути МАСИВОМ.
4. Сформулювати уточнений запит (`follow_up_query`) для наступного LLM, який отримає вже завантажені дані.

Доступні `file_type`:
- `users`: Загальна інформація про всіх користувачів (список імен, логінів).
- `questions`: Перелік питань з анкети.
- `traits`: Визначення особистісних рис.
- `badges`: Інформація про досягнення та бейджи.
- `user_answers`: Детальні відповіді та розрахунки для одного або двох користувачів. У файлах користувачів є `self_answers` (самооцінка) та `other_answers` (оцінка іншими). Це важливо.
- `none`: Для загальних запитів, що не потребують даних з файлів.

Правила для `target_usernames`:
- ЗАВЖДИ повертай масив.
- Для одного користувача: `[\"username1\"]`.
- Для порівняння двох: `[\"username1\", \"username2\"]`.
- Якщо користувачі не потрібні: `[]`.

У `follow_up_query` для `user_answers` вказуй на необхідність аналізу `self` та `other` відповідей.

Формат відповіді: ТІЛЬКИ JSON об'єкт.
Приклади:
Запит: 'результати Змея'
json
{
    \"file_type\": \"user_answers\",
    \"target_usernames\": [\"Zmey\"],
    \"follow_up_query\": \"Надай детальний аналіз результатів користувача Zmey, враховуючи його self-відповіді та other-відповіді (якщо є).\"
}

Запит: 'порівняй Zmey та Guerro'

      
{
    \"file_type\": \"user_answers\",
    \"target_usernames\": [\"Zmey\", \"Guerro\"],
    \"follow_up_query\": \"Порівняй результати тестування Zmey та Guerro. Проаналізуй їх self-відповіді та other-відповіді (якщо є) за ключовими показниками.\"
}

Запит: 'які є питання про памʼять?'

      
{
    \"file_type\": \"questions\",
    \"target_usernames\": [],
    \"follow_up_query\": \"Надай список питань, що стосуються категорії 'Памʼять' або містять ключове слово 'памʼять'.\"
}

Запит: 'що таке інтроверсія?'

      
{
    \"file_type\": \"none\",
    \"target_usernames\": [],
    \"follow_up_query\": \"Дай загальне визначення поняття 'інтроверсія'.\"
}


(Якщо запит про рису з файлу traits.json, то file_type: "traits", follow_up_query: "Опиши рису 'інтроверсія' на основі наданих даних.")

Список доступних користувачів (username, first_name, last_name):
" . json_encode($usersForLLM, JSON_UNESCAPED_UNICODE) . "

Структура даних (приклади):
Питання: " . $exampleQuestionStructure . "
Риси: " . $exampleTraitStructure . "
Бейджи: " . $exampleBadgeStructure;

      
$messages = [
    ['role' => 'user', 'parts' => [['text' => $systemInstruction . "\n\nЗапит користувача: " . $userQuery]]]
];

$geminiResponseText = callGeminiApi($messages, 'gemini-2.5-flash-preview-05-20'); // Можна вказати більш швидку модель для маршрутизації

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
            if (!in_array($foundCanonicalUsername, $validUsernames)) { // Уникаємо дублікатів
                $validUsernames[] = $foundCanonicalUsername;
            }
        } else {
            $notFoundUsernames[] = $requestedName;
        }
    }

    if (!empty($notFoundUsernames)) {
        $errorMsgPart = "Не вдалося знайти користувачів: " . implode(', ', $notFoundUsernames) . ".";
        if (empty($validUsernames)) { // Якщо ЖОДНОГО не знайдено
            $geminiResponse['file_type'] = 'none'; // Змінюємо тип, бо даних немає
            $geminiResponse['target_usernames'] = [];
            $geminiResponse['follow_up_query'] = $errorMsgPart . " Будь ласка, уточніть імена.";
        } else { // Якщо когось знайдено, а когось ні (актуально для порівняння)
            // Якщо запит був на порівняння (2 імені), а знайдено менше 2-х валідних
            if (count($geminiResponse['target_usernames']) > 1 && count($validUsernames) < 2) {
                 $geminiResponse['file_type'] = 'none';
                 $geminiResponse['target_usernames'] = [];
                 $geminiResponse['follow_up_query'] = $errorMsgPart . " Порівняння неможливе. Уточніть імена.";
            } else {
                // Якщо запит був на 1, і він знайдений, або на 2, і обидва знайдені (після фільтрації неіснуючих)
                // Попереджаємо користувача, що деякі імена не знайдені, але продовжуємо з тими, що є.
                // $geminiResponse['follow_up_query'] = $errorMsgPart . " Продовжую з " . implode(', ', $validUsernames) . ". " . $geminiResponse['follow_up_query'];
                // Або просто оновлюємо список імен на валідні
                 $geminiResponse['target_usernames'] = $validUsernames;
            }
        }
    }
    $geminiResponse['target_usernames'] = $validUsernames; // Остаточний список валідних імен
}
 // Додаткова перевірка: якщо тип user_answers, але масив імен порожній після валідації
if ($geminiResponse['file_type'] === 'user_answers' && empty($geminiResponse['target_usernames'])) {
    // Якщо follow_up_query ще не містить повідомлення про помилку
    if (strpos($geminiResponse['follow_up_query'], "Не вдалося знайти") === false && strpos($geminiResponse['follow_up_query'], "Порівняння неможливе") === false) {
        $geminiResponse['follow_up_query'] = "Було запитано дані користувача(ів), але імена не розпізнано або вказано невірно. Уточніть запит.";
    }
    // file_type міг бути змінений на 'none' вище, якщо це доречно
}

return $geminiResponse;

}

/**

    Отримує остаточну відповідь від Gemini на основі уточненого запиту та даних контексту.
    */
    function getGeminiAnswer(string $refinedQuery, string $contextDataJson): ?string {

            
    parsedContext=jsondecode(parsedContext=jsond​ecode(

          

    contextDataJson, true);
    $contextDescription = "";

    // Визначаємо тип контексту для більш точного промпту
    if (isset(

            

          

    parsedContext['user2_data'])) {

            
    user1=htmlspecialchars(user1=htmlspecialchars(

          

    parsedContext['user1_username'] ?? 'Користувач 1');

            
    user2=htmlspecialchars(user2=htmlspecialchars(

          

    parsedContext['user2_username'] ?? 'Користувач 2');

            

          

    user1} та {

            

          

    parsedContext['username']) && isset($parsedContext['answers'])) { // Припускаємо структуру файлу одного юзера

            
    username=htmlspecialchars(username=htmlspecialchars(

          

    parsedContext['username']);

            

          

    username}'. " .
    "Дані містять self_answers (самооцінка) та other_answers (оцінка іншими). " .
    "Аналізуючи, детально розглянь ОБИДВА типи відповідей, якщо вони є. \nДані:\n";
    } elseif (is_array(

            

          

    parsedContext) && isset($parsedContext[0]['username'])) {

            

          

    parsedContext) && !empty(

            

          

    parsedContext[0]['categoryId'])) {

            

          

    parsedContext) && !empty(

            

          

    parsedContext[0]['traitId'])) {

            

          

    parsedContext) && !empty(

            

          

    parsedContext[0]['badgeId'])) {
    $contextDescription = "Надано список бейджів. \nДані:\n";
    } else {
    $contextDescription = "Надані наступні дані (якщо вони не порожні): \n";
    }

    // Обрізаємо contextDataJson, якщо він занадто великий, для включення в промпт
    // Gemini 1.5 Flash має велике контекстне вікно, але все одно краще бути обережним

            
    maxContextLength=100000;//Приблизно100kсимволів,можнаналаштуватиif(strlen(maxContextLength=100000;//Приблизно100kсимволів,можнаналаштуватиif(strlen(

          

    contextDataJson) > $maxContextLength) {

            
    contextDataJsonShortened=substr(contextDataJsonShortened=substr(

          

    contextDataJson, 0,

            

          

    contextDataJson), "gemini_warning");
    } else {
    $contextDataJsonShortened = $contextDataJson;
    }

    $systemInstruction = "Ти – експерт-аналітик психологічних тестів.
    Твоя мета – надати чітку, інформативну відповідь на запит користувача, базуючись ВИКЛЮЧНО на наданому JSON-контексті.
    НЕ вигадуй інформацію. Якщо даних недостатньо, прямо вкажи це.
    Особливу увагу приділяй наявності self_answers (самооцінка) та other_answers (оцінка іншими) у даних користувачів. Надавай інформацію по обох, якщо вони є.

Форматуй відповіді для Telegram:

    Використовуй HTML-теги: <b>жирний</b>, <i>курсив</i>, <u>підкреслений</u>, <s>закреслений</s>, <code>однорядковий код</code>.

    Для багаторядкового коду або блоків даних: <pre>багаторядковий блок</pre>.

    Для списків: новий рядок, тире/зірочка: - Пункт 1\n- Пункт 2.

    Уникай Markdown.

" . $contextDescription . $contextDataJsonShortened . "

Запит користувача, який потрібно виконати, використовуючи вищенаведені дані:
" . htmlspecialchars($refinedQuery); // Екрануємо запит для безпеки

      
$messages = [
    ['role' => 'user', 'parts' => [['text' => $systemInstruction]]]
];

// Можна використати більш потужну модель для генерації кінцевої відповіді, якщо потрібно
$geminiResponseText = callGeminiApi($messages, 'gemini-2.5-flash-preview-05-20'); // або 'gemini-1.5-pro-preview-0514'

if ($geminiResponseText === null) {
    return 'Вибачте, сталася помилка під час генерації відповіді від ШІ (LLM2).';
}

$geminiResponseText = preg_replace('/^```(?:json|html)?\s*(.*?)\s*```$/s', '$1', $geminiResponseText);
$geminiResponseText = trim($geminiResponseText);

return $geminiResponseText;

}

if (!function_exists('loadUserData')) {
function loadUserData(string

        

      

/',

        

      

username}'", 'security_warning');
return [];
}
$filePath = ROOT_DIR . '/data/answers/' .

        
username.′.json′;if(fileexists(username.′.json′;if(filee​xists(

      

filePath)) {

        
data=readJsonFile(data=readJsonFile(

      

filePath);
if (empty(

        

      

username}' порожній або не вдалося прочитати: {$filePath}", 'file_error');
return [];
}
// Додаємо ім'я користувача до даних, щоб LLM2 знав, чиї це дані, якщо це один користувач
// Це особливо корисно, якщо структура файлу не містить поля 'username' всередині.
// Якщо 'username' вже є в файлі, ця операція його оновить/додасть.
$data['username_queried'] = $username; // Додаємо поле, щоб точно знати, для кого дані
return

        

      

username}' не знайдено: {$filePath}", 'file_error');
return [];
}
}
}
?>
