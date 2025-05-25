<?php // get_analysis.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); // Показувати ВСІ помилки, попередження та нотатки
// --- Includes & Initial Setup ---
require_once __DIR__ . '/includes/env-loader.php'; // Завантажувач змінних середовища
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/questionnaire_logic.php';

// Завантажуємо змінні оточення
loadEnv(__DIR__ . '/../.env');

// --- Gemini Configuration ---
$geminiApiKey = getenv('GEMINI_API_KEY'); // Отримуємо ключ з .env
$geminiApiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-04-17:generateContent?key=' . $geminiApiKey;
$geminiPromptTemplate = "Проведи психологічний аналіз профілю користувача на основі наданих даних.
Дані містять скорочену назву риси ('trait'), самооцінку користувача ('self') за шкалою 1-7 (деякі питання можуть мати іншу шкалу, але інтерпретуй їх відповідно до контексту), та середню оцінку від інших користувачів ('others_avg') за тією ж шкалою (null означає, що інші не оцінювали цю рису або їх було недостатньо).

Ось дані для аналізу користувача '%USERNAME%':
%ANALYSIS_DATA%

Інструкції для аналізу:
1.  Напиши загальний висновок (summary) про профіль користувача.
1.1 Напиши загальні плюси та мінуси людини простою мовою.
1.2 Проведи аналогію з відомими людьми з таким самим характером. Поясни чому. Обов'язково вкажи хоча б одну відому людину, яка має схожий характер.
2.  Зверни особливу увагу на риси, де різниця між 'self' та 'others_avg' становить 2 бали або більше. Проаналізуй можливі причини розбіжностей (завищена/занижена самооцінка, нерозуміння себе іншими тощо).
3.  Виділи риси з високими оцінками (6 або 7) як від 'self', так і від 'others_avg', і прокоментуй їх значення для профілю.
4.  Спробуй знайти можливі логічні протиріччя у відповідях (наприклад, висока 'Командна робота' і низька 'Емпатія'), але не роби на цьому головний акцент.
6.  Сформулюй 3-5 конкретних рекомендацій ('recommendations') щодо саморозвитку, покращення взаємодії з іншими або кращого саморозуміння на основі аналізу. Замотевуй людину в середині рекомндацій.
7.  Підпиши аналіз як 'analyst': 'Gemini AI Psychoanalyst'.
8.  Твоя відповідь МАЄ БУТИ ЛИШЕ у форматі JSON, без будь-якого іншого тексту до або після JSON об'єкта. Структура JSON має бути наступною:
    {
      \"summary\": \"Текст загального висновку...\",
      \"recommendations\": [
        \"Перша рекомендація...\",
        \"Друга рекомендація...\",
        \"Третя рекомендація...\"
      ],
      \"analyst\": \"Gemini AI Psychoanalyst\"
    }

Переконайся, що JSON валідний.";

// --- Path Constants ---
if (!defined('ADMINS_FILE_PATH')) {
    define('ADMINS_FILE_PATH', __DIR__ . '/data/admins.json');
}
if (!defined('QUESTIONS_FILE_PATH')) {
    define('QUESTIONS_FILE_PATH', __DIR__ . '/data/questions.json');
}
if (!defined('ANSWERS_DIR')) {
    define('ANSWERS_DIR', __DIR__ . '/data/answers/');
}

// --- Session Start ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Завантажуємо змінні оточення
loadEnv(__DIR__ . '/../.env');
$internalCallSecretFromEnv = getenv('INTERNAL_ANALYSIS_SECRET');

// --- Admin Check ---
$isInternalCallAllowed = false;
if (!empty($internalCallSecretFromEnv) && isset($_GET['internal_call_secret']) && $_GET['internal_call_secret'] === $internalCallSecretFromEnv) {
    $isInternalCallAllowed = true;
} elseif (empty($internalCallSecretFromEnv) && isset($_GET['internal_call_secret']) && $_GET['internal_call_secret'] === 'DEV_INTERNAL_SECRET_KEY_PLEASE_CHANGE') {
    // Fallback for development if .env var is not set (less secure)
    $isInternalCallAllowed = true;
    // error_log("get_analysis.php: Using fallback internal call secret. Set INTERNAL_ANALYSIS_SECRET in .env for production.");
}


$isAdmin = false;
if (isUserLoggedIn()) {
    $currentUserId = $_SESSION['user_id'];
    $adminData = readJsonFile(ADMINS_FILE_PATH);
    if ($adminData && isset($adminData['admin_ids']) && in_array($currentUserId, $adminData['admin_ids'])) {
        $isAdmin = true;
    }
}

if (!$isAdmin && !$isInternalCallAllowed) { // Access denied if not admin AND not a valid internal call
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Доступ заборонено.']);
    exit;
}

// --- Parameter Handling ---
header('Content-Type: application/json');
$username = trim($_GET['username'] ?? '');
if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Параметр username не вказано.']);
    exit;
}

// --- Check API Key ---
if (empty($geminiApiKey)) {
    echo json_encode(['success' => false, 'message' => 'Ключ API Gemini не налаштовано у файлі .env або змінних середовища.']);
    exit;
}


// --- Data Loading ---
$userData = loadUserData($username);
if ($userData === null) {
    echo json_encode(['success' => false, 'message' => "Профіль користувача '{$username}' не знайдено."]);
    exit;
}

$allQuestionsData = readJsonFile(QUESTIONS_FILE_PATH);
if ($allQuestionsData === null) {
    echo json_encode(['success' => false, 'message' => 'Не вдалося завантажити файл питань questions.json.']);
    exit;
}

// --- Check Self Answers ---
if (!isset($userData['self']['answers']) || empty($userData['self']['answers'])) {
    echo json_encode(['success' => false, 'message' => "Користувач '{$username}' ще не надав відповідей (self). Аналіз неможливий."]);
    exit;
}
$selfAnswers = $userData['self']['answers'];
$othersAnswersList = $userData['others'] ?? [];

// --- Data Preparation ---

$questionShortNames = [];
foreach ($allQuestionsData as $category) {
    if (isset($category['questions']) && is_array($category['questions'])) {
        foreach ($category['questions'] as $question) {
            if (isset($question['questionId']) && isset($question['q_short'])) {
                 $questionShortNames[$question['questionId']] = $question['q_short'];
            }
        }
    }
}

$analysisData = [];
$validOthersCount = 0;
$othersAverages = [];
$othersCounts = [];

foreach ($othersAnswersList as $otherResponse) {
    if (isset($otherResponse['answers']) && is_array($otherResponse['answers']) && !empty($otherResponse['answers'])) {
        // Перевіряємо, чи є хоча б одна відповідь на відоме питання у цього респондента
        $hasValidAnswer = false;
        foreach ($otherResponse['answers'] as $qId => $answer) {
             if (isset($questionShortNames[$qId]) && is_numeric($answer)) {
                 $hasValidAnswer = true;
                 break;
             }
        }

        if ($hasValidAnswer) {
            $validOthersCount++; // Рахуємо тільки тих, хто дав хоч одну релевантну відповідь
            foreach ($otherResponse['answers'] as $qId => $answer) {
                if (isset($questionShortNames[$qId]) && is_numeric($answer)) {
                    if (!isset($othersAverages[$qId])) {
                        $othersAverages[$qId] = 0;
                        $othersCounts[$qId] = 0;
                    }
                    $othersAverages[$qId] += (int)$answer;
                    $othersCounts[$qId]++;
                }
            }
        }
    }
}

// --- Check Minimum Others Responses ---
define('MIN_OTHERS_FOR_ANALYSIS', 3); // Мінімальна кількість відповідей від інших
if ($validOthersCount < MIN_OTHERS_FOR_ANALYSIS) {
    echo json_encode([
        'success' => false,
        'message' => "Для аналізу потрібно щонайменше " . MIN_OTHERS_FOR_ANALYSIS . " відповідей від інших користувачів. Наразі є: {$validOthersCount}."
    ]);
    exit;
}

// Calculate averages
foreach ($othersAverages as $qId => $sum) {
    if ($othersCounts[$qId] > 0) {
        $othersAverages[$qId] = round($sum / $othersCounts[$qId], 1);
    } else {
        unset($othersAverages[$qId]);
    }
}

// Format data for AI
foreach ($selfAnswers as $qId => $selfAnswer) {
    if (isset($questionShortNames[$qId]) && is_numeric($selfAnswer)) {
        $analysisData[] = [
            'trait' => $questionShortNames[$qId],
            'self' => (int)$selfAnswer,
            'others_avg' => $othersAverages[$qId] ?? null
        ];
    }
}

if (empty($analysisData)) {
     echo json_encode(['success' => false, 'message' => "Не знайдено числових відповідей для аналізу у користувача '{$username}'."]);
     exit;
}

// --- Gemini API Interaction ---

$prompt = str_replace(
    ['%USERNAME%', '%ANALYSIS_DATA%'],
    [$username, json_encode($analysisData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)],
    $geminiPromptTemplate
);

$data = [
    'contents' => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => ['responseMimeType' => 'application/json']
];

$options = [
    'http' => [
        'header' => "Content-Type: application/json\r\n",
        'method' => 'POST',
        'content' => json_encode($data),
        'ignore_errors' => true
    ]
];

$context = stream_context_create($options);
$response = @file_get_contents($geminiApiUrl, false, $context);

// --- Process Gemini Response ---

if ($response === false) {
    $error = error_get_last();
    echo json_encode(['success' => false, 'message' => 'Помилка з\'єднання з Gemini API: ' . ($error['message'] ?? 'Невідома помилка')]);
    exit;
}

$statusCode = null;
if (isset($http_response_header)) {
    preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
    if ($match) {
        $statusCode = (int)$match[1];
    }
}

if ($statusCode === null || $statusCode >= 400) {
    $errorBody = json_decode($response, true);
    $apiErrorMessage = 'Невідома помилка API';
    if (isset($errorBody['error']['message'])) {
        $apiErrorMessage = $errorBody['error']['message'];
    } elseif (is_string($response) && strlen($response) < 500) {
        $apiErrorMessage = strip_tags($response);
    }
     echo json_encode([
         'success' => false,
         'message' => "Помилка від Gemini API (Статус: " . ($statusCode ?? 'N/A') . "): " . $apiErrorMessage
     ]);
    exit;
}

// Attempt to decode JSON response directly or from nested structure
$analysisResult = null;
$decodedResponse = json_decode($response, true);

if (json_last_error() === JSON_ERROR_NONE && isset($decodedResponse['summary']) && isset($decodedResponse['recommendations']) && isset($decodedResponse['analyst'])) {
    // Direct JSON response is valid
    $analysisResult = $decodedResponse;
} elseif (isset($decodedResponse['candidates'][0]['content']['parts'][0]['text'])) {
    // Try to parse nested JSON string
    $nestedJsonString = $decodedResponse['candidates'][0]['content']['parts'][0]['text'];
    $nestedResult = json_decode($nestedJsonString, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($nestedResult['summary']) && isset($nestedResult['recommendations']) && isset($nestedResult['analyst'])) {
        $analysisResult = $nestedResult;
    }
}

// Final structure validation
if ($analysisResult === null) {
     $errorMsg = 'Gemini API повернув відповідь у неочікуваному форматі або невалідний JSON.';
     if (json_last_error() !== JSON_ERROR_NONE && isset($nestedJsonString)) {
         $errorMsg .= ' Вкладений текст: ' . substr(strip_tags($nestedJsonString), 0, 300) . '...';
     } elseif(json_last_error() !== JSON_ERROR_NONE) {
          $errorMsg .= ' Помилка JSON: ' . json_last_error_msg();
     }
     echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}

// --- Save Analysis Results ---

$userData['expertAnalysis'] = [
    'summary' => $analysisResult['summary'],
    'recommendations' => $analysisResult['recommendations'],
    'analyst' => $analysisResult['analyst'],
    'timestamp' => date('c')
];

if (!saveUserData($username, $userData)) {
    echo json_encode([
        'success' => false,
        'message' => "Помилка збереження результатів аналізу для користувача '{$username}'. Аналіз був успішним, але збереження не вдалося."
    ]);
    exit;
}

// --- Success Response ---
echo json_encode([
    'success' => true,
    'message' => "Аналіз для користувача '{$username}' успішно згенеровано та збережено.",
    'analysis' => $userData['expertAnalysis']
]);
exit;

?>