<?php // includes/functions.php

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
}
if (!defined('DATA_DIR')) {
    define('DATA_DIR', ROOT_DIR . '/data');
}
if (!defined('LOG_DIR')) {
    define('LOG_DIR', ROOT_DIR . '/logs');
}
if (!defined('USERS_FILE_PATH')) {
    define('USERS_FILE_PATH', DATA_DIR . '/users.json');
}
if (!defined('ANSWERS_DIR_PATH')) {
     define('ANSWERS_DIR_PATH', DATA_DIR . '/answers');
}


/**
 * Читає дані з JSON файлу.
 */
function readJsonFile(string $filePath): array {
    if (!file_exists($filePath)) {
        return [];
    }
    $jsonContent = file_get_contents($filePath);
    if ($jsonContent === false) {
        error_log("Помилка читання файлу: " . $filePath);
        return [];
    }
    $data = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Помилка декодування JSON з файлу: " . $filePath . " - " . json_last_error_msg());
        return [];
    }
    return is_array($data) ? $data : [];
}

/**
 * Записує дані у JSON файл.
 */
function writeJsonFile(string $filePath, array $data): bool {
    $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($jsonContent === false) {
        error_log("Помилка кодування JSON для файлу: " . $filePath . " - " . json_last_error_msg());
        return false;
    }
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true)) {
             error_log("Не вдалося створити директорію: " . $dir);
             return false;
        }
    }

    if (file_put_contents($filePath, $jsonContent, LOCK_EX) === false) {
        error_log("Помилка запису у файл: " . $filePath);
        return false;
    }
    return true;
}

/**
 * Завантажує та перевіряє доступ до даних користувача.
 *
 * @param string $username Ім'я користувача для завантаження.
 * @param bool $isAdminRequest Чи є запит від адміністратора.
 * @return array ['success' => bool, 'message' => string, 'data' => array]
 */
function loadUserData(string $username, bool $isAdminRequest = false): array {
    // 1. Знайти користувача в загальному списку для перевірки налаштувань приватності
    $allUsers = readJsonFile(USERS_FILE_PATH);
    $targetUser = null;
    foreach ($allUsers as $user) {
        if (isset($user['username']) && $user['username'] === $username) {
            $targetUser = $user;
            break;
        }
    }

    if (!$targetUser) {
        return ['success' => false, 'message' => 'Користувач не знайдений.', 'data' => []];
    }

    // 2. Перевірка приватності
    if (isset($targetUser['hide_results']) && $targetUser['hide_results'] === true && !$isAdminRequest) {
        return ['success' => false, 'message' => 'Результати цього користувача приватні.', 'data' => []];
    }

    // 3. Завантаження файлу відповідей
    $filePath = ANSWERS_DIR_PATH . '/' . $username . '.json';
    if (!file_exists($filePath)) {
        return ['success' => false, 'message' => 'Файл з відповідями користувача не знайдено.', 'data' => []];
    }

    $userData = readJsonFile($filePath);
    if (empty($userData)) {
        return ['success' => false, 'message' => 'Файл з відповідями порожній або пошкоджений.', 'data' => []];
    }

    // Додаємо ім'я користувача, якого запитували, для подальшого використання
    $userData['username_queried'] = $username;

    return ['success' => true, 'message' => 'Дані успішно завантажено.', 'data' => $userData];
}

/**
 * Створює стислий виклад даних користувача для передачі в LLM.
 *
 * @param array $fullUserData Повний масив даних з файлу відповідей користувача.
 * @return array Стислий масив даних.
 */
function summarizeUserData(array $fullUserData): array {
    $summary = [];

    // 1. Self-відповіді залишаємо без змін
    if (isset($fullUserData['self'])) {
        $summary['self'] = $fullUserData['self'];
    }

    // 2. Агрегація "other" відповідей
    if (!empty($fullUserData['others']) && is_array($fullUserData['others'])) {
        $aggregatedScores = [];
        $openQuestions = [];
        $respondentUserIds = [];

        foreach ($fullUserData['others'] as $assessment) {
            if (!isset($assessment['answers']) || !is_array($assessment['answers'])) {
                continue;
            }
            // Збираємо унікальних оцінювачів
            if (isset($assessment['respondentUserId']) && !in_array($assessment['respondentUserId'], $respondentUserIds)) {
                $respondentUserIds[] = $assessment['respondentUserId'];
            }

            foreach ($assessment['answers'] as $key => $value) {
                // Збираємо числові відповіді для агрегації
                if (str_starts_with($key, 'q_') || $key === 'fin_literacy') {
                     if (is_numeric($value)) {
                        $aggregatedScores[$key][] = (float)$value;
                    }
                }
                // Збираємо відповіді на відкриті питання
                elseif (str_starts_with($key, 'open_q_') && !empty($value)) {
                    $openQuestions[$key][] = $value;
                }
            }
        }

        // Обчислюємо середні значення
        $averageScores = [];
        foreach ($aggregatedScores as $key => $scores) {
            $averageScores[$key] = round(array_sum($scores) / count($scores), 2);
        }

        $summary['other_answers_summary'] = [
            'evaluator_count' => count($respondentUserIds),
            'average_scores' => $averageScores,
            'open_questions' => $openQuestions
        ];
    }

    // 3. Досягнення (тільки name та description)
    if (!empty($fullUserData['achievements']) && is_array($fullUserData['achievements'])) {
        $summary['achievements'] = [];
        foreach ($fullUserData['achievements'] as $achievement) {
            if (isset($achievement['name']) && isset($achievement['description'])) {
                $summary['achievements'][] = [
                    'name' => $achievement['name'],
                    'description' => $achievement['description']
                ];
            }
        }
    }

    // 4. expertAnalysis не додаємо
    
    // 5. badges_summary залишаємо без змін
    if (isset($fullUserData['badges_summary'])) {
        $summary['badges_summary'] = $fullUserData['badges_summary'];
    }
    
    // Додаємо username для ідентифікації
    if(isset($fullUserData['username_queried'])) {
        $summary['username_queried'] = $fullUserData['username_queried'];
    }

    return $summary;
}


/**
 * Створює стислий виклад списку всіх користувачів.
 *
 * @param array $allUsers Повний масив користувачів з users.json.
 * @return array Стислий масив користувачів.
 */
function summarizeUsersList(array $allUsers): array {
    $summaryList = [];
    foreach ($allUsers as $user) {
        $summaryList[] = [
            'username' => $user['username'] ?? 'N/A',
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'hide_results' => $user['hide_results'] ?? false
        ];
    }
    return $summaryList;
}


/**
 * Записує повідомлення у спеціальний лог-файл.
 */
function custom_log(string $message, string $logFile = 'app_debug'): void {
    if (!is_dir(LOG_DIR)) {
        @mkdir(LOG_DIR, 0775, true);
    }
    if (!is_dir(LOG_DIR) || !is_writable(LOG_DIR)) {
        error_log("Custom Log Error: Log directory " . LOG_DIR . " is not writable or does not exist.");
        error_log("Original Message for {$logFile}.log: " . $message);
        return;
    }

    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}" . PHP_EOL;
    
    @file_put_contents(LOG_DIR . '/' . $logFile . '.log', $logEntry, FILE_APPEND | LOCK_EX);
}

// Інші функції (generateUniqueId, mergeUsers, writeJsonFile тощо) залишаються тут без змін
// ...
