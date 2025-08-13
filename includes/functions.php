<?php // includes/functions.php

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
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__)); 
}
if (!defined('DATA_DIR')) {
    define('DATA_DIR', ROOT_DIR . '/data');
}
if (!defined('ANSWERS_DIR_PATH')) {
    define('ANSWERS_DIR_PATH', DATA_DIR . '/answers');
}

function getUserAnswersFilePath(string $username): string {
    return ANSWERS_DIR_PATH . '/' . $username . '.json';
}
function saveUserData(string $username, array $data): bool {
    $filePath = getUserAnswersFilePath($username);
    return writeJsonFile($filePath, $data);
}
function generateUniqueId(string $prefix = 'user_'): string {
    return uniqid($prefix, true);
}
/**
 * Завантажує, перевіряє права доступу та стискає дані користувача для LLM.
 *
 * @param string $username Ім'я користувача для завантаження.
 * @param bool $isAdminRequest Чи є запит від адміністратора.
 * @return array Масив з результатом: ['success' => bool, 'message' => string, 'data' => array|null]
 */
function loadAndSummarizeUserData(string $username, bool $isAdminRequest = false): array {
    custom_log("Inside loadAndSummarizeUserData for '{$username}'. Admin: " . ($isAdminRequest ? 'Yes' : 'No'), 'telegram_debug');
    
    $allUsers = readJsonFile(USERS_FILE_PATH);
    $targetUser = null;
    foreach ($allUsers as $user) {
        if (isset($user['username']) && $user['username'] === $username) {
            $targetUser = $user;
            break;
        }
    }

    if (!$targetUser) {
        return ['success' => false, 'message' => "Користувач '{$username}' не знайдений у системі.", 'data' => null];
    }

    if (isset($targetUser['hide_results']) && $targetUser['hide_results'] === true && !$isAdminRequest) {
        return ['success' => false, 'message' => "Результати користувача '{$username}' приховані.", 'data' => null];
    }

    $filePath = getUserAnswersFilePath($username);
    if (!file_exists($filePath)) {
        return ['success' => false, 'message' => "Файл з даними для '{$username}' не знайдено.", 'data' => null];
    }

    $fullUserData = readJsonFile($filePath);
    if (empty($fullUserData)) {
         return ['success' => false, 'message' => "Файл з даними для '{$username}' порожній або пошкоджений.", 'data' => null];
    }
    custom_log("User data file read successfully for '{$username}'.", 'telegram_debug');

    $summarizedData = [];
    $summarizedData['username_queried'] = $username;

    if (isset($fullUserData['self']['answers'])) {
        $summarizedData['self_answers'] = $fullUserData['self']['answers'];
    }

    if (!empty($fullUserData['others']) && is_array($fullUserData['others'])) {
        custom_log("Found " . count($fullUserData['others']) . " entries in 'others' array for '{$username}'. Starting processing.", 'telegram_debug');
        $otherAnswersSum = [];
        $otherAnswersCount = [];
        $openQuestions = [];
        $evaluators = [];

        foreach ($fullUserData['others'] as $assessment) {
            if (!is_array($assessment) || empty($assessment['answers']) || !is_array($assessment['answers'])) {
                custom_log("Skipping malformed/incomplete entry in 'others' for user {$username}. Entry: " . json_encode($assessment), 'data_warning');
                continue;
            }
            
            if (isset($assessment['respondentUserId']) && !in_array($assessment['respondentUserId'], $evaluators)) {
                $evaluators[] = $assessment['respondentUserId'];
            }

            foreach ($assessment['answers'] as $questionId => $value) {
                if (str_starts_with($questionId, 'q_') || $questionId === 'fin_literacy') {
                    if (!isset($otherAnswersSum[$questionId])) {
                        $otherAnswersSum[$questionId] = 0;
                        $otherAnswersCount[$questionId] = 0;
                    }
                    if (is_numeric($value)) {
                       $otherAnswersSum[$questionId] += (float)$value;
                       $otherAnswersCount[$questionId]++;
                    }
                } 
                elseif (str_starts_with($questionId, 'open_q_')) {
                     if (!empty(trim((string)$value))) {
                        $respondent = $assessment['respondentUsername'] ?? 'анонім';
                        $openQuestions[$questionId][] = "Від {$respondent}: " . trim((string)$value);
                    }
                }
            }
        }

        $averagedAnswers = [];
        foreach ($otherAnswersSum as $questionId => $sum) {
            if ($otherAnswersCount[$questionId] > 0) {
                $averagedAnswers[$questionId] = round($sum / $otherAnswersCount[$questionId], 2);
            }
        }
        
        if (count($evaluators) > 0) {
            $summarizedData['other_answers_summary'] = [
                'evaluators_count' => count($evaluators),
                'average_scores' => $averagedAnswers,
                'open_questions' => $openQuestions
            ];
        }
        custom_log("Finished processing 'others' array for '{$username}'.", 'telegram_debug');
    }

    if (!empty($fullUserData['achievements']) && is_array($fullUserData['achievements'])) {
        $summarizedData['achievements'] = array_map(function($ach) {
            return [
                'name' => $ach['name'] ?? 'N/A',
                'description' => $ach['description'] ?? 'N/A'
            ];
        }, $fullUserData['achievements']);
    }

    if (isset($fullUserData['badges_summary'])) {
        $summarizedData['badges_summary'] = $fullUserData['badges_summary'];
    }

    custom_log("Data summarization complete for '{$username}'.", 'telegram_debug');
    return ['success' => true, 'message' => 'Дані успішно завантажені та стиснуті.', 'data' => $summarizedData];
}

function summarizeUsersList(array $allUsersData): array {
    if (empty($allUsersData)) {
        return [];
    }
    return array_map(function($user) {
        return [
            'username' => $user['username'] ?? 'N/A',
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'hide_results' => $user['hide_results'] ?? false,
        ];
    }, $allUsersData);
}

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
            if (empty($otherUserData['others']) || !is_array($otherUserData['others'])) {
                continue;
            }
            foreach ($otherUserData['others'] as $index => $assessment) {
                if (($assessment['respondentUserId'] ?? null) === $sourceUserId) {
                    $sourceAssessmentIndex = $index;
                }
                if (($assessment['respondentUserId'] ?? null) === $targetUserId) {
                    $targetAssessmentIndex = $index;
                }
            }
            if ($sourceAssessmentIndex !== -1 && $targetAssessmentIndex !== -1) {
                if ($priorityUserId === $targetUserId) {
                    array_splice($otherUserData['others'], $sourceAssessmentIndex, 1);
                    $otherUserAnswersModified = true;
                } else {
                    $indices_to_process = [$sourceAssessmentIndex, $targetAssessmentIndex];
                    rsort($indices_to_process);
                    if ($otherUserData['others'][$indices_to_process[1]]['respondentUserId'] === $targetUserId) {
                         $targetAssessmentIndex = $indices_to_process[1];
                         $sourceAssessmentIndex = $indices_to_process[0];
                    } else {
                         $targetAssessmentIndex = $indices_to_process[0];
                         $sourceAssessmentIndex = $indices_to_process[1];
                    }
                    array_splice($otherUserData['others'], $targetAssessmentIndex, 1);
                    $otherUserData['others'][$sourceAssessmentIndex > $targetAssessmentIndex ? $sourceAssessmentIndex-1 : $sourceAssessmentIndex]['respondentUserId'] = $targetUserId;
                    $otherUserData['others'][$sourceAssessmentIndex > $targetAssessmentIndex ? $sourceAssessmentIndex-1 : $sourceAssessmentIndex]['respondentUsername'] = $targetUser['username'];
                    $otherUserAnswersModified = true;
                }
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
        if (file_exists($adminsFilePath)) {
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
if (!defined('LOG_DIR')) {
    define('LOG_DIR', dirname(__DIR__) . '/logs');
}
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
