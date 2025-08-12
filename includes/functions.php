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
 *
 * @param string $filePath Шлях до файлу.
 * @return array Повертає масив даних або порожній масив у разі помилки.
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
    $data = json_decode($jsonContent, true); // true для асоціативного масиву
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Помилка декодування JSON з файлу: " . $filePath . " - " . json_last_error_msg());
        return []; // Повертаємо порожній масив у разі помилки
    }
    // Переконуємося, що повертаємо масив
    return is_array($data) ? $data : [];
}

/**
 * Записує дані у JSON файл.
 *
 * @param string $filePath Шлях до файлу.
 * @param array $data Дані для запису.
 * @return bool Повертає true у разі успіху, false у разі помилки.
 */
function writeJsonFile(string $filePath, array $data): bool {
    $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($jsonContent === false) {
        error_log("Помилка кодування JSON для файлу: " . $filePath . " - " . json_last_error_msg());
        return false;
    }
    // Переконуємося, що директорія існує
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true)) {
             error_log("Не вдалося створити директорію: " . $dir);
             return false;
        }
    }

    if (file_put_contents($filePath, $jsonContent, LOCK_EX) === false) { // LOCK_EX для запобігання конфліктам запису
        error_log("Помилка запису у файл: " . $filePath);
        return false;
    }
    return true;
}

/**
 * Генерує унікальний ID.
 *
 * @param string $prefix Префікс для ID (необов'язково).
 * @return string Унікальний ID.
 */
function generateUniqueId(string $prefix = 'user_'): string {
    return uniqid($prefix, true); // Використовуємо more_entropy для кращої унікальності
}

/**
 * Отримує шлях до файлу відповідей користувача.
 *
 * @param string $username
 * @return string
 */
function getUserAnswersFilePath(string $username): string {
    return ANSWERS_DIR_PATH . '/' . $username . '.json';
}

/**
 * Зберігає дані користувача у файл.
 *
 * @param string $username
 * @param array $data
 * @return bool
 */
function saveUserData(string $username, array $data): bool {
    $filePath = getUserAnswersFilePath($username);
    return writeJsonFile($filePath, $data);
}


/**
 * Об'єднує дані двох користувачів, переносячи дані sourceUser до targetUser.
 *
 * @param string $sourceUserId ID користувача-джерела (буде видалено).
 * @param string $targetUserId ID цільового користувача (залишиться).
 * @param string $priorityUserId ID користувача, чиї дані мають пріоритет при конфліктах.
 * @param string $defaultPassword Пароль за замовчуванням для цільового користувача.
 * @return array Масив з результатом: ['success' => bool, 'message' => string]
 */
function mergeUsers(string $sourceUserId, string $targetUserId, string $priorityUserId, string $defaultPassword = 'qwerty'): array
{
    if ($sourceUserId === $targetUserId) {
        return ['success' => false, 'message' => 'Користувач-Джерело та Цільовий користувач не можуть бути однаковими.'];
    }
    $allUsers = readJsonFile(USERS_FILE_PATH);
    $sourceUser = null; $targetUser = null;
    $sourceUserIndex = -1; $targetUserIndex = -1;

    foreach ($allUsers as $index => $user) {
        if ($user['id'] === $sourceUserId) {
            $sourceUser = $user;
            $sourceUserIndex = $index;
        }
        if ($user['id'] === $targetUserId) {
            $targetUser = $user;
            $targetUserIndex = $index;
        }
    }

    if (!$sourceUser || !$targetUser) {
        return ['success' => false, 'message' => 'Один або обидва користувачі не знайдені.'];
    }
    if ($priorityUserId !== $sourceUserId && $priorityUserId !== $targetUserId) {
        return ['success' => false, 'message' => 'Невірний ID пріоритетного користувача.'];
    }

    $backupAllUsers = $allUsers;
    $sourceAnswersPath = getUserAnswersFilePath($sourceUser['username']);
    $targetAnswersPath = getUserAnswersFilePath($targetUser['username']);
    $sourceAnswersData = file_exists($sourceAnswersPath) ? readJsonFile($sourceAnswersPath) : ['self' => null, 'others' => []];
    $targetAnswersData = file_exists($targetAnswersPath) ? readJsonFile($targetAnswersPath) : ['self' => null, 'others' => []];
    $backupTargetAnswersData = $targetAnswersData;

    try {
        $priorityUser = ($priorityUserId === $sourceUserId) ? $sourceUser : $targetUser;
        $nonPriorityUser = ($priorityUserId === $sourceUserId) ? $targetUser : $sourceUser;
        $priorityAnswers = ($priorityUserId === $sourceUserId) ? $sourceAnswersData : $targetAnswersData;
        $nonPriorityAnswers = ($priorityUserId === $sourceUserId) ? $targetAnswersData : $sourceAnswersData;

        $mergedUserData = $targetUser;
        $mergedUserData['first_name'] = !empty(trim($targetUser['first_name'] ?? '')) ? $targetUser['first_name'] : $sourceUser['first_name'] ?? '';
        $mergedUserData['last_name'] = !empty(trim($targetUser['last_name'] ?? '')) ? $targetUser['last_name'] : $sourceUser['last_name'] ?? '';

        $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);
        if (!$passwordHash) {
            throw new Exception("Помилка хешування пароля для користувача '{$targetUser['username']}'.");
        }
        $mergedUserData['password_hash'] = $passwordHash;

        $mergedAnswersData = ['self' => null, 'others' => []];

        if (!empty($priorityAnswers['self'])) {
            $mergedAnswersData['self'] = $priorityAnswers['self'];
        } elseif (!empty($nonPriorityAnswers['self'])) {
            $mergedAnswersData['self'] = $nonPriorityAnswers['self'];
        }

        $mergedAnswersData['others'] = $targetAnswersData['others'] ?? [];
        $existingRespondentIds = array_column($mergedAnswersData['others'], 'respondentUserId');
        foreach ($sourceAnswersData['others'] ?? [] as $sourceOtherAssessment) {
            if (!in_array($sourceOtherAssessment['respondentUserId'], $existingRespondentIds)) {
                $mergedAnswersData['others'][] = $sourceOtherAssessment;
                $existingRespondentIds[] = $sourceOtherAssessment['respondentUserId'];
            }
        }
        if (isset($priorityAnswers['achievements'])) {
            $mergedAnswersData['achievements'] = $priorityAnswers['achievements'];
        }
        if (isset($priorityAnswers['expertAnalysis'])) {
             $mergedAnswersData['expertAnalysis'] = $priorityAnswers['expertAnalysis'];
        }

        $allAnswerFiles = glob(ANSWERS_DIR_PATH . '/*.json');
        foreach ($allAnswerFiles as $filePath) {
            if ($filePath === $sourceAnswersPath || $filePath === $targetAnswersPath) {
                continue;
            }
            $otherUsername = pathinfo(basename($filePath), PATHINFO_FILENAME);
            if (empty($otherUsername)) continue;

            $otherUserData = readJsonFile($filePath);
            $otherUserAnswersModified = false;
            $sourceAssessmentIndex = -1;
            $targetAssessmentIndex = -1;

            foreach ($otherUserData['others'] ?? [] as $index => $assessment) {
                if ($assessment['respondentUserId'] === $sourceUserId) $sourceAssessmentIndex = $index;
                if ($assessment['respondentUserId'] === $targetUserId) $targetAssessmentIndex = $index;
            }

            if ($sourceAssessmentIndex !== -1 && $targetAssessmentIndex !== -1) {
                if ($priorityUserId === $targetUserId) {
                    array_splice($otherUserData['others'], $sourceAssessmentIndex, 1);
                } else {
                    $targetIndexToDelete = $targetAssessmentIndex > $sourceAssessmentIndex ? $targetAssessmentIndex : $sourceAssessmentIndex;
                    $sourceIndexToUpdate = $targetAssessmentIndex < $sourceAssessmentIndex ? $targetAssessmentIndex : $sourceAssessmentIndex;
                    if ($targetAssessmentIndex > $sourceAssessmentIndex) {
                        array_splice($otherUserData['others'], $targetIndexToDelete, 1);
                    } else {
                        array_splice($otherUserData['others'], $targetIndexToDelete, 1);
                        $sourceIndexToUpdate--;
                    }
                    if (isset($otherUserData['others'][$sourceIndexToUpdate])) {
                        $otherUserData['others'][$sourceIndexToUpdate]['respondentUserId'] = $targetUserId;
                        $otherUserData['others'][$sourceIndexToUpdate]['respondentUsername'] = $targetUser['username'];
                    }
                }
                $otherUserAnswersModified = true;
            } elseif ($sourceAssessmentIndex !== -1) {
                $otherUserData['others'][$sourceAssessmentIndex]['respondentUserId'] = $targetUserId;
                $otherUserData['others'][$sourceAssessmentIndex]['respondentUsername'] = $targetUser['username'];
                $otherUserAnswersModified = true;
            }

            if ($otherUserAnswersModified) {
                if (!saveUserData($otherUsername, $otherUserData)) {
                    throw new Exception("Не вдалося оновити файл відповідей для користувача '{$otherUsername}'.");
                }
            }
        }

        if (!saveUserData($targetUser['username'], $mergedAnswersData)) {
            throw new Exception("Не вдалося зберегти об'єднані відповіді для користувача '{$targetUser['username']}'.");
        }

        $adminsFilePath = DATA_DIR . '/admins.json';
        $adminsData = readJsonFile($adminsFilePath);
        $adminsModified = false;
        if (isset($adminsData['admin_ids']) && is_array($adminsData['admin_ids'])) {
            $isAdminSource = in_array($sourceUserId, $adminsData['admin_ids']);
            $isAdminTarget = in_array($targetUserId, $adminsData['admin_ids']);
            if ($isAdminSource && !$isAdminTarget) {
                $adminsData['admin_ids'][] = $targetUserId;
                $adminsModified = true;
            }
            $sourceAdminKey = array_search($sourceUserId, $adminsData['admin_ids']);
            if ($sourceAdminKey !== false) {
                array_splice($adminsData['admin_ids'], $sourceAdminKey, 1);
                $adminsModified = true;
            }
        }
        if ($adminsModified) {
            if (!writeJsonFile($adminsFilePath, $adminsData)) {
                 throw new Exception("Не вдалося оновити список адміністраторів.");
            }
        }

        $allUsers[$targetUserIndex] = $mergedUserData;
        array_splice($allUsers, $sourceUserIndex, 1);
        if (!writeJsonFile(USERS_FILE_PATH, $allUsers)) {
            throw new Exception("Не вдалося оновити основний файл користувачів.");
        }

        if (file_exists($sourceAnswersPath)) {
            if (!unlink($sourceAnswersPath)) {
                error_log("Не вдалося видалити файл відповідей джерела: " . $sourceAnswersPath);
            }
        }

        return [
            'success' => true,
            'message' => "Користувачі '{$sourceUser['username']}' та '{$targetUser['username']}' успішно об'єднані. "
                       . "Користувач '{$sourceUser['username']}' видалений. Пароль для '{$targetUser['username']}' скинуто до '{$defaultPassword}'."
        ];

    } catch (Exception $e) {
        writeJsonFile(USERS_FILE_PATH, $backupAllUsers);
        if (isset($backupTargetAnswersData)) {
             saveUserData($targetUser['username'], $backupTargetAnswersData);
        }
        return ['success' => false, 'message' => 'Помилка під час об\'єднання: ' . $e->getMessage() . ' Зміни частково або повністю скасовано.'];
    }
}


/**
 * Завантажує та перевіряє доступ до даних користувача.
 *
 * @param string $username Ім'я користувача для завантаження.
 * @param bool $isAdminRequest Чи є запит від адміністратора.
 * @return array ['success' => bool, 'message' => string, 'data' => array]
 */
function loadUserData(string $username, bool $isAdminRequest = false): array {
    $allUsers = readJsonFile(USERS_FILE_PATH);
    $targetUser = null;
    foreach ($allUsers as $user) {
        if (isset($user['username']) && strcasecmp($user['username'], $username) === 0) {
            $targetUser = $user;
            break;
        }
    }

    if (!$targetUser) {
        return ['success' => false, 'message' => 'Користувач не знайдений.', 'data' => []];
    }

    if (isset($targetUser['hide_results']) && $targetUser['hide_results'] === true && !$isAdminRequest) {
        return ['success' => false, 'message' => 'Результати цього користувача приватні.', 'data' => []];
    }

    $filePath = getUserAnswersFilePath($username);
    if (!file_exists($filePath)) {
        return ['success' => false, 'message' => 'Файл з відповідями користувача не знайдено.', 'data' => []];
    }

    $userData = readJsonFile($filePath);
    if (empty($userData)) {
        return ['success' => false, 'message' => 'Файл з відповідями порожній або пошкоджений.', 'data' => []];
    }

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

    if (isset($fullUserData['self'])) {
        $summary['self'] = $fullUserData['self'];
    }

    if (!empty($fullUserData['others']) && is_array($fullUserData['others'])) {
        $aggregatedScores = [];
        $openQuestions = [];
        $respondentUserIds = [];

        foreach ($fullUserData['others'] as $assessment) {
            if (!isset($assessment['answers']) || !is_array($assessment['answers'])) {
                continue;
            }
            if (isset($assessment['respondentUserId']) && !in_array($assessment['respondentUserId'], $respondentUserIds)) {
                $respondentUserIds[] = $assessment['respondentUserId'];
            }

            foreach ($assessment['answers'] as $key => $value) {
                if (str_starts_with($key, 'q_') || $key === 'fin_literacy') {
                     if (is_numeric($value)) {
                        $aggregatedScores[$key][] = (float)$value;
                    }
                }
                elseif (str_starts_with($key, 'open_q_') && !empty(trim((string)$value))) {
                    $openQuestions[$key][] = (string)$value;
                }
            }
        }

        $averageScores = [];
        foreach ($aggregatedScores as $key => $scores) {
            if(count($scores) > 0) {
                 $averageScores[$key] = round(array_sum($scores) / count($scores), 2);
            }
        }

        $summary['other_answers_summary'] = [
            'evaluator_count' => count($respondentUserIds),
            'average_scores' => $averageScores,
            'open_questions' => $openQuestions
        ];
    }

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
    
    if (isset($fullUserData['badges_summary'])) {
        $summary['badges_summary'] = $fullUserData['badges_summary'];
    }
    
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
 *
 * @param string $message Повідомлення для запису.
 * @param string $logFile Назва лог-файлу (без розширення).
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

?>
