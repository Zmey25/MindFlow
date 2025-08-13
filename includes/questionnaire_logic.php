<?php // includes/questionnaire_logic.php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php'; // Needed for findUserById to get usernames

const QUESTIONS_FILE_PATH = __DIR__ . '/../data/questions.json';

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__); // Приклад, якщо ROOT_DIR не визначено
}
if (!defined('DATA_DIR')) {
    define('DATA_DIR', ROOT_DIR . '/data');
}
if (!defined('ANSWERS_DIR_PATH')) {
    define('ANSWERS_DIR_PATH', DATA_DIR . '/answers');
}

/**
 * Ensures the answers directory exists.
 *
 * @return bool True if directory exists or was created, false otherwise.
 */
function ensureAnswersDirectory(): bool {
    if (!is_dir(ANSWERS_DIR_PATH)) {
        if (!mkdir(ANSWERS_DIR_PATH, 0775, true)) { // Creates recursively if needed
            error_log("Failed to create answers directory: " . ANSWERS_DIR_PATH);
            return false;
        }
    }
     // Optional: Check writability, though file_put_contents will fail anyway
     // if (!is_writable(ANSWERS_DIR_PATH)) {
     //     error_log("Answers directory is not writable: " . ANSWERS_DIR_PATH);
     //     return false;
     // }
    return true;
}

/**
 * Gets the file path for a specific target user's answers.
 *
 * @param string $targetUsername The username of the user whose data file is needed.
 * @return string|null The full path to the JSON file, or null if username is empty.
 */
function getUserAnswersFilePath(string $targetUsername): ?string {
    if (empty($targetUsername)) {
         error_log("Attempted to get answers file path for empty username.");
        return null;
    }
    // Basic sanitization to prevent directory traversal, though usernames should be validated elsewhere
    $safeUsername = basename($targetUsername);
    if ($safeUsername !== $targetUsername || empty($safeUsername)) {
        error_log("Invalid username provided for file path: " . $targetUsername);
        return null;
    }
    return ANSWERS_DIR_PATH . '/' . $safeUsername . '.json';
}

/**
 * Reads and decodes the JSON data for a specific target user.
 * Creates the directory if it doesn't exist.
 * Returns default structure if file doesn't exist or is invalid.
 *
 * @param string $targetUsername The username of the user whose data to load.
 * @return array The user's data ['self' => ..., 'others' => ...].
 */
function loadUserData(string $targetUsername): array {
    $defaultStructure = ['self' => null, 'others' => []];

    if (!ensureAnswersDirectory()) {
        return $defaultStructure; // Cannot proceed if directory can't be ensured
    }

    $filePath = getUserAnswersFilePath($targetUsername);
    if ($filePath === null) {
        return $defaultStructure; // Invalid username
    }

    if (!file_exists($filePath)) {
        return $defaultStructure; // File doesn't exist yet for this user
    }

    $jsonContent = file_get_contents($filePath);
    if ($jsonContent === false) {
        error_log("Error reading user answers file: " . $filePath);
        return $defaultStructure;
    }

    $data = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error decoding JSON from user answers file: " . $filePath . " - " . json_last_error_msg());
        // Optionally back up the corrupted file here before returning default
        return $defaultStructure;
    }

    // Ensure the basic structure exists even if the file was partially valid
    if (!isset($data['self'])) {
        $data['self'] = null;
    }
    if (!isset($data['others']) || !is_array($data['others'])) {
         $data['others'] = [];
    }


    return $data;
}

/**
 * Encodes and writes data to a specific target user's JSON file.
 *
 * @param string $targetUsername The username of the user whose data to save.
 * @param array $userData The data array to save.
 * @return bool True on success, false on failure.
 */
function saveUserData(string $targetUsername, array $userData): bool {
     if (!ensureAnswersDirectory()) {
         return false; // Cannot save if directory isn't ready
     }

    $filePath = getUserAnswersFilePath($targetUsername);
     if ($filePath === null) {
         return false; // Invalid username
     }

    $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    $jsonString = json_encode($userData, $jsonOptions);

    if ($jsonString === false) {
        error_log("Failed to encode user data to JSON for user: " . $targetUsername . " - Error: " . json_last_error_msg());
        return false;
    }

    if (file_put_contents($filePath, $jsonString, LOCK_EX) === false) {
        error_log("Failed to write user answers file: " . $filePath);
        return false;
    }

    return true;
}

/**
 * Завантажує структуру питань з JSON файлу.
 *
 * @return array Масив категорій та питань або порожній масив у разі помилки.
 */
function loadQuestions(): array {
    return readJsonFile(QUESTIONS_FILE_PATH);
}

// loadAllAnswers() is removed as it's no longer needed or efficient.

/**
 * Зберігає відповіді користувача про себе.
 * Writes to the file named after the user's username.
 *
 * @param string $userId ID користувача, який відповідає і про якого йде мова.
 * @param string $username Username користувача.
 * @param array $answers Асоціативний масив відповідей [questionId => answerValue].
 * @return bool True у разі успіху, false у разі помилки.
 */
function saveSelfAnswers(string $userId, string $username, array $answers): bool {
    $userData = loadUserData($username); // Load current data for this user

    $selfAnswersData = [
        'respondentUserId' => $userId, // Technically redundant here, but keeps structure consistent
        'respondentUsername' => $username,
        'answers' => $answers,
        'timestamp' => date('c')
    ];

    // Update the 'self' part
    $userData['self'] = $selfAnswersData;
    // Ensure 'others' key exists if it didn't before
    if (!isset($userData['others'])) {
        $userData['others'] = [];
    }

    return saveUserData($username, $userData);
}

/**
 * Отримує відповіді користувача про себе.
 * Reads from the file named after the user's username.
 *
 * @param string $userId ID користувача.
 * @return array|null Масив відповідей [questionId => answerValue] або null, якщо відповіді не знайдено.
 */
function getSelfAnswers(string $userId): ?array {
    $user = findUserById($userId); // Need username to find the file
    if (!$user) {
        error_log("getSelfAnswers: User not found for ID: " . $userId);
        return null;
    }
    $username = $user['username'];
    $userData = loadUserData($username);

    return $userData['self']['answers'] ?? null;
}

/**
 * Зберігає відповіді одного користувача (respondent) про іншого (target).
 * Writes to the file named after the *target* user's username.
 *
 * @param string $targetUserId ID користувача, про якого відповідають.
 * @param string $respondentUserId ID користувача, який відповідає.
 * @param string $respondentUsername Username користувача, який відповідає.
 * @param array $answers Асоціативний масив відповідей [questionId => answerValue].
 * @return bool True у разі успіху, false у разі помилки.
 */
function saveOtherAnswers(string $targetUserId, string $respondentUserId, string $respondentUsername, array $answers): bool {
    $targetUser = findUserById($targetUserId); // Need target username to find the file
    if (!$targetUser) {
        error_log("saveOtherAnswers: Target user not found for ID: " . $targetUserId);
        return false;
    }
    $targetUsername = $targetUser['username'];

    $userData = loadUserData($targetUsername); // Load current data for the target user

    $otherAnswersData = [
        'respondentUserId' => $respondentUserId,
        'respondentUsername' => $respondentUsername,
        'answers' => $answers,
        'timestamp' => date('c')
    ];

    // Ensure 'self' and 'others' keys exist
     if (!isset($userData['self'])) {
         $userData['self'] = null;
     }
    if (!isset($userData['others'])) {
         $userData['others'] = [];
    }

    $foundIndex = -1;
    // Find if this respondent already answered about this target
    foreach ($userData['others'] as $index => $otherEntry) {
        if (isset($otherEntry['respondentUserId']) && $otherEntry['respondentUserId'] === $respondentUserId) {
            $foundIndex = $index;
            break;
        }
    }

    // Update existing entry or add a new one
    if ($foundIndex !== -1) {
        $userData['others'][$foundIndex] = $otherAnswersData;
    } else {
        $userData['others'][] = $otherAnswersData;
    }

    return saveUserData($targetUsername, $userData);
}

/**
 * Отримує відповіді, які respondentUserId дав про targetUserId.
 * Reads from the file named after the *target* user's username.
 *
 * @param string $targetUserId ID користувача, про якого питали.
 * @param string $respondentUserId ID користувача, який відповідав.
 * @return array|null Масив відповідей [questionId => answerValue] або null, якщо відповіді не знайдено.
 */
function getSpecificOtherAnswers(string $targetUserId, string $respondentUserId): ?array {
     $targetUser = findUserById($targetUserId); // Need target username to find the file
     if (!$targetUser) {
         error_log("getSpecificOtherAnswers: Target user not found for ID: " . $targetUserId);
         return null;
     }
     $targetUsername = $targetUser['username'];

    $userData = loadUserData($targetUsername);

    if (empty($userData['others'])) {
        return null; // No 'others' answers for this target user yet
    }

    // Find the specific respondent's answers
    foreach ($userData['others'] as $otherEntry) {
        if (isset($otherEntry['respondentUserId']) && $otherEntry['respondentUserId'] === $respondentUserId) {
            return $otherEntry['answers'] ?? null;
        }
    }

    return null; // This respondent hasn't answered about this target user yet
}

/**
 * Розраховує середні бали та кількість відповідей інших користувачів для кожного питання
 * стосовно цільового користувача (targetUserId).
 * Reads from the file named after the target user's username.
 *
 * @param string $targetUserId ID користувача, для якого розраховуються середні бали.
 * @param array|null $allQuestions (Необов'язково) Попередньо завантажений масив питань для оптимізації.
 * @return array Асоціативний масив, де ключ - questionId, а значення - масив ['average' => float|null, 'count' => int].
 */
// В файлі includes/questionnaire_logic.php

 function calculateAverageOtherScores(string $targetUserId, ?array $allQuestions = null): array {
    if ($allQuestions === null) {
        $allQuestions = loadQuestions();
    }
    if (empty($allQuestions)) {
         error_log("calculateAverageOtherScores: No questions loaded.");
        return [];
    }

     $targetUser = findUserById($targetUserId);
     if (!$targetUser) {
         error_log("calculateAverageOtherScores: Target user not found for ID: " . $targetUserId);
         return [];
     }
     $targetUsername = $targetUser['username'];

    $userData = loadUserData($targetUsername);
    // Важлива перевірка: чи існує 'others' і чи це масив
    $otherAssessments = (isset($userData['others']) && is_array($userData['others'])) ? $userData['others'] : [];
    $results = [];

    foreach ($allQuestions as $category) {
        // Додаткова перевірка на існування 'questions' і чи це масив
        if (empty($category['questions']) || !is_array($category['questions'])) continue;

        foreach ($category['questions'] as $question) {
            // Перевірка, чи є 'questionId' у питанні
            if (!isset($question['questionId'])) continue;
            $questionId = $question['questionId'];

            $sum = 0;
            $count = 0;

            foreach ($otherAssessments as $assessment) {
                // ---- Посилені перевірки ----
                // 1. Чи є 'answers' і чи це масив?
                if (!isset($assessment['answers']) || !is_array($assessment['answers'])) {
                    continue; // Пропустити цей запис, якщо структура неправильна
                }
                // 2. Чи є відповідь на *це* питання і чи є вона числом?
                if (isset($assessment['answers'][$questionId]) && is_numeric($assessment['answers'][$questionId])) {
                    $sum += (float) $assessment['answers'][$questionId];
                    $count++;
                }
                // ---- Кінець посилених перевірок ----
            }

            $average = ($count > 0) ? round($sum / $count, 1) : null;

            $results[$questionId] = [
                'average' => $average,
                'count' => $count
            ];
        }
    }

    return $results;
} 
    function getUkrainianNounEnding(int $number, string $form1, string $form2, string $form5): string {
        $number = abs($number) % 100;
        $num = $number % 10;
        if ($number > 10 && $number < 20) return $form5;
        if ($num > 1 && $num < 5) return $form2;
        if ($num == 1) return $form1;
        return $form5;
    }

?>
