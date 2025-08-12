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

if (!defined('LOG_DIR')) {
    define('LOG_DIR', __DIR__ . '/../logs'); // Директорія для логів на рівень вище www/includes
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
    // 0. Перевірки
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

    // --- Початок транзакції (умовно, бо файлова система) ---
    $backupAllUsers = $allUsers; // Резервна копія користувачів
    $sourceAnswersPath = getUserAnswersFilePath($sourceUser['username']);
    $targetAnswersPath = getUserAnswersFilePath($targetUser['username']);
    $sourceAnswersData = file_exists($sourceAnswersPath) ? loadUserData($sourceUser['username']) : ['self' => null, 'others' => []];
    $targetAnswersData = file_exists($targetAnswersPath) ? loadUserData($targetUser['username']) : ['self' => null, 'others' => []];
    $backupTargetAnswersData = $targetAnswersData; // Резервна копія цільових відповідей

    try {
        // 1. Визначення пріоритетних/непріоритетних даних
        $priorityUser = ($priorityUserId === $sourceUserId) ? $sourceUser : $targetUser;
        $nonPriorityUser = ($priorityUserId === $sourceUserId) ? $targetUser : $sourceUser;
        $priorityAnswers = ($priorityUserId === $sourceUserId) ? $sourceAnswersData : $targetAnswersData;
        $nonPriorityAnswers = ($priorityUserId === $sourceUserId) ? $targetAnswersData : $sourceAnswersData;

        // 2. Об'єднання базової інформації користувача (пріоритет у target, якщо не порожнє)
        $mergedUserData = $targetUser; // Починаємо з цільового
        $mergedUserData['first_name'] = !empty(trim($targetUser['first_name'] ?? '')) ? $targetUser['first_name'] : $sourceUser['first_name'] ?? '';
        $mergedUserData['last_name'] = !empty(trim($targetUser['last_name'] ?? '')) ? $targetUser['last_name'] : $sourceUser['last_name'] ?? '';

        // 3. Скидання паролю цільового користувача
        $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);
        if (!$passwordHash) {
            throw new Exception("Помилка хешування пароля для користувача '{$targetUser['username']}'.");
        }
        $mergedUserData['password_hash'] = $passwordHash;

        // 4. Об'єднання даних відповідей
        $mergedAnswersData = ['self' => null, 'others' => []];

        // 4.1. Самооцінка (self)
        if (!empty($priorityAnswers['self'])) {
            $mergedAnswersData['self'] = $priorityAnswers['self'];
        } elseif (!empty($nonPriorityAnswers['self'])) {
            $mergedAnswersData['self'] = $nonPriorityAnswers['self'];
        }

        // 4.2. Оцінки інших *про* цих користувачів (others)
        $mergedAnswersData['others'] = $targetAnswersData['others'] ?? []; // Починаємо з цільових
        $existingRespondentIds = array_column($mergedAnswersData['others'], 'respondentUserId');
        foreach ($sourceAnswersData['others'] ?? [] as $sourceOtherAssessment) {
            // Додаємо оцінку від джерела, тільки якщо оцінювач ще не оцінював ціль
            if (!in_array($sourceOtherAssessment['respondentUserId'], $existingRespondentIds)) {
                $mergedAnswersData['others'][] = $sourceOtherAssessment;
                $existingRespondentIds[] = $sourceOtherAssessment['respondentUserId']; // Оновлюємо список
            }
        }
        // 4.3. Досягнення та аналіз (беремо від пріоритетного)
        if (isset($priorityAnswers['achievements'])) {
            $mergedAnswersData['achievements'] = $priorityAnswers['achievements'];
        }
        if (isset($priorityAnswers['expertAnalysis'])) {
             $mergedAnswersData['expertAnalysis'] = $priorityAnswers['expertAnalysis'];
        }


        // 5. Перепризначення відповідей, даних *джерелом* іншим користувачам
        $allAnswerFiles = glob(ANSWERS_DIR_PATH . '/*.json');
        foreach ($allAnswerFiles as $filePath) {
            $filename = basename($filePath);
            // Пропускаємо файли джерела та цілі, їх обробили окремо
            if ($filePath === $sourceAnswersPath || $filePath === $targetAnswersPath) {
                continue;
            }

            // Отримуємо username з імені файлу
            $otherUsername = pathinfo($filename, PATHINFO_FILENAME);
            if (empty($otherUsername)) continue; // Пропускаємо, якщо ім'я файлу дивне

            $otherUserData = loadUserData($otherUsername); // Завантажуємо дані іншого користувача
            $otherUserAnswersModified = false;

            $sourceAssessmentIndex = -1;
            $targetAssessmentIndex = -1;

            // Шукаємо оцінки від джерела та цілі про цього otherUser
            foreach ($otherUserData['others'] ?? [] as $index => $assessment) {
                if ($assessment['respondentUserId'] === $sourceUserId) {
                    $sourceAssessmentIndex = $index;
                }
                if ($assessment['respondentUserId'] === $targetUserId) {
                    $targetAssessmentIndex = $index;
                }
            }

            // Логіка перепризначення/видалення
            if ($sourceAssessmentIndex !== -1 && $targetAssessmentIndex !== -1) {
                // Обидва оцінювали цього користувача
                if ($priorityUserId === $targetUserId) {
                    // Пріоритет у цілі - видаляємо оцінку джерела
                    array_splice($otherUserData['others'], $sourceAssessmentIndex, 1);
                    $otherUserAnswersModified = true;
                } else {
                    // Пріоритет у джерела - видаляємо оцінку цілі і оновлюємо джерело
                    // Важливо: видалити спочатку той, що має більший індекс, щоб не змістити інший
                    $targetIndexToDelete = $targetAssessmentIndex;
                    $sourceIndexToUpdate = $sourceAssessmentIndex;
                    if ($targetAssessmentIndex > $sourceAssessmentIndex) {
                        array_splice($otherUserData['others'], $targetIndexToDelete, 1);
                         // Індекс джерела не змінився
                    } else {
                        array_splice($otherUserData['others'], $targetIndexToDelete, 1);
                        // Індекс джерела зменшився на 1, бо видалили попередній елемент
                        $sourceIndexToUpdate--;
                    }

                    if (isset($otherUserData['others'][$sourceIndexToUpdate])) { // Додаткова перевірка
                        $otherUserData['others'][$sourceIndexToUpdate]['respondentUserId'] = $targetUserId; // Оновлюємо ID
                        $otherUserData['others'][$sourceIndexToUpdate]['respondentUsername'] = $targetUser['username']; // Оновлюємо логін
                    }
                    $otherUserAnswersModified = true;
                }
            } elseif ($sourceAssessmentIndex !== -1) {
                // Тільки джерело оцінювало - оновлюємо ID/логін на цільові
                $otherUserData['others'][$sourceAssessmentIndex]['respondentUserId'] = $targetUserId;
                $otherUserData['others'][$sourceAssessmentIndex]['respondentUsername'] = $targetUser['username'];
                $otherUserAnswersModified = true;
            }
            // Якщо тільки ціль оцінювала ($targetAssessmentIndex != -1), нічого не робимо

            // Зберігаємо зміни, якщо вони були
            if ($otherUserAnswersModified) {
                if (!saveUserData($otherUsername, $otherUserData)) {
                    throw new Exception("Не вдалося оновити файл відповідей для користувача '{$otherUsername}'.");
                }
            }
        } // кінець циклу по файлах відповідей інших користувачів


        // 6. Збереження об'єднаних даних відповідей для цільового користувача
        if (!saveUserData($targetUser['username'], $mergedAnswersData)) {
            throw new Exception("Не вдалося зберегти об'єднані відповіді для користувача '{$targetUser['username']}'.");
        }

        // 7. Оновлення списку адміністраторів (якщо потрібно)
        $adminsFilePath = __DIR__ . '/../data/admins.json';
        $adminsData = readJsonFile($adminsFilePath);
        $adminsModified = false;
        if (isset($adminsData['admin_ids']) && is_array($adminsData['admin_ids'])) {
            $isAdminSource = in_array($sourceUserId, $adminsData['admin_ids']);
            $isAdminTarget = in_array($targetUserId, $adminsData['admin_ids']);

            if ($isAdminSource && !$isAdminTarget) {
                // Додати ціль до адмінів
                $adminsData['admin_ids'][] = $targetUserId;
                $adminsModified = true;
            }
            // Видалити джерело з адмінів, якщо воно там було
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

        // 8. Оновлення основного файлу користувачів (users.json)
        $allUsers[$targetUserIndex] = $mergedUserData; // Оновлюємо дані цільового користувача
        array_splice($allUsers, $sourceUserIndex, 1); // Видаляємо користувача-джерело

        if (!writeJsonFile(USERS_FILE_PATH, $allUsers)) {
            throw new Exception("Не вдалося оновити основний файл користувачів.");
        }

        // 9. Видалення файлу відповідей джерела (робимо це останнім)
        if (file_exists($sourceAnswersPath)) {
            if (!unlink($sourceAnswersPath)) {
                // Не критична помилка, але варто повідомити
                error_log("Не вдалося видалити файл відповідей джерела: " . $sourceAnswersPath);
                // Не кидаємо виняток, бо основна частина пройшла успішно
            }
        }

        // --- Кінець транзакції (успіх) ---
        return [
            'success' => true,
            'message' => "Користувачі '{$sourceUser['username']}' та '{$targetUser['username']}' успішно об'єднані. "
                       . "Користувач '{$sourceUser['username']}' видалений. Пароль для '{$targetUser['username']}' скинуто до '{$defaultPassword}'."
        ];

    } catch (Exception $e) {
        // --- Відкат змін (спроба) ---
        writeJsonFile(USERS_FILE_PATH, $backupAllUsers); // Відновлюємо users.json
        // Відновлюємо дані відповідей цілі (якщо вдалося їх зберегти)
        if (isset($backupTargetAnswersData)) {
             saveUserData($targetUser['username'], $backupTargetAnswersData);
        }
        // Відновлення файлів інших користувачів складніше, пропускаємо наразі
        // Помилки запису адмінів теж складніше відкатити

        return ['success' => false, 'message' => 'Помилка під час об\'єднання: ' . $e->getMessage() . ' Зміни частково або повністю скасовано.'];
    }
}

?>
