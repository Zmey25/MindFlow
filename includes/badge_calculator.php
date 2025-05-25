<?php // includes/badge_calculator.php

// Конфігураційні константи для розрахунку бейджів
if (!defined('BADGES_FILE_PATH')) {
    // Шлях відносно папки, де знаходиться цей файл (тобто includes/)
    define('BADGES_FILE_PATH', __DIR__ . '/../data/badges.json');
}
if (!defined('MIN_QUESTION_SCORE')) {
    define('MIN_QUESTION_SCORE', 1);
}
if (!defined('MAX_QUESTION_SCORE')) {
    define('MAX_QUESTION_SCORE', 7);
}

/**
 * Розраховує бали для всіх бейджів користувача.
 *
 * @param string $username Ім'я користувача.
 * @return array Масив з результатом:
 *               [
 *                   'success' => bool,
 *                   'message' => string,
 *                   'badges_summary' => array // Масив об'єктів {'badgeId': string, 'score': int}
 *               ]
 */
function calculateUserBadges(string $username): array {
    // Завантаження даних користувача
    // Припускається, що функція loadUserData() визначена та доступна
    $userData = loadUserData($username);
    if (empty($userData) && !file_exists(getUserAnswersFilePath($username))) {
        return ['success' => false, 'message' => "Профіль користувача '{$username}' не знайдено для розрахунку бейджів.", 'badges_summary' => []];
    }

    // Завантаження визначень бейджів
    // Припускається, що функція readJsonFile() визначена та доступна
    $badgesDefinitionList = readJsonFile(BADGES_FILE_PATH);

    if ($badgesDefinitionList === null) { // Помилка читання файлу або невалідний JSON
        return ['success' => false, 'message' => "Файл визначень бейджів (badges.json) не знайдено або містить невалідний JSON.", 'badges_summary' => []];
    }
    if (empty($badgesDefinitionList) && is_array($badgesDefinitionList)) { // Файл існує, валідний JSON, але порожній масив []
        return ['success' => true, 'message' => "Список визначень бейджів порожній. Розрахунок не проводився.", 'badges_summary' => []];
    }
    if (!is_array($badgesDefinitionList)) { // Додаткова перевірка, якщо readJsonFile повернув щось несподіване
        return ['success' => false, 'message' => "Файл визначень бейджів (badges.json) має некоректний формат (не є масивом).", 'badges_summary' => []];
    }

    $calculatedBadgesSummary = [];

    foreach ($badgesDefinitionList as $badgeDef) {
        if (!isset($badgeDef['badgeId'], $badgeDef['questions']) || !is_array($badgeDef['questions']) || empty($badgeDef['questions'])) {
            // Можна додати логування для некоректних визначень бейджів
            // error_log("Badge definition for {$badgeDef['badgeName'] ?? $badgeDef['badgeId'] ?? 'UNKNOWN'} is invalid or has no questions.");
            continue; 
        }

        $currentBadgeRawSum = 0;
        $validQuestionsCount = 0;

        foreach ($badgeDef['questions'] as $questionInfo) {
            $questionId = $questionInfo['questionId'];
            $isReversed = $questionInfo['isReversed'] ?? false;

            $selfScore = null;
            if (isset($userData['self']['answers'][$questionId]) && is_numeric($userData['self']['answers'][$questionId])) {
                $selfScore = (float)$userData['self']['answers'][$questionId];
            }

            $otherScores = [];
            if (isset($userData['others']) && is_array($userData['others'])) {
                foreach ($userData['others'] as $assessment) {
                    if (isset($assessment['answers'][$questionId]) && is_numeric($assessment['answers'][$questionId])) {
                        $otherScores[] = (float)$assessment['answers'][$questionId];
                    }
                }
            }

            $avgOtherScore = null;
            if (!empty($otherScores)) {
                $avgOtherScore = array_sum($otherScores) / count($otherScores);
            }

            $finalQuestionScore = null;

            if ($selfScore !== null && $avgOtherScore !== null) {
                $finalQuestionScore = ($selfScore + $avgOtherScore) / 2;
            } elseif ($selfScore !== null) {
                $finalQuestionScore = $selfScore;
            } elseif ($avgOtherScore !== null) {
                $finalQuestionScore = $avgOtherScore;
            } else {
                // Питання не має оцінок, пропускаємо його
                continue;
            }
            
            // Обмеження оцінки діапазоном MIN_QUESTION_SCORE - MAX_QUESTION_SCORE
            $finalQuestionScore = max(MIN_QUESTION_SCORE, min(MAX_QUESTION_SCORE, $finalQuestionScore));

            if ($isReversed) {
                $finalQuestionScore = (MAX_QUESTION_SCORE + MIN_QUESTION_SCORE) - $finalQuestionScore;
            }

            $currentBadgeRawSum += $finalQuestionScore;
            $validQuestionsCount++;
        }

        if ($validQuestionsCount > 0) {
            $minPossibleSum = $validQuestionsCount * MIN_QUESTION_SCORE;
            $maxPossibleSum = $validQuestionsCount * MAX_QUESTION_SCORE;
            $normalizedScore = 1; // Значення за замовчуванням

            if ($maxPossibleSum == $minPossibleSum) {
                // Якщо діапазон можливих значень = 0 (наприклад, всі питання мають фіксовану оцінку, або MIN_SCORE == MAX_SCORE)
                // І користувач набрав цю єдину можливу суму.
                // Це означає "повне досягнення" в межах можливого, тому 100.
                // $currentBadgeRawSum тут має бути рівним $minPossibleSum (і $maxPossibleSum).
                if ($currentBadgeRawSum >= $maxPossibleSum) { // або >= $minPossibleSum, бо вони рівні
                     $normalizedScore = 100;
                } else { // Малоймовірно, але для повноти
                     $normalizedScore = 1;
                }
            } elseif (($maxPossibleSum - $minPossibleSum) > 0) {
                $normalizedScore = 1 + (($currentBadgeRawSum - $minPossibleSum) / ($maxPossibleSum - $minPossibleSum)) * 99;
            }
            
            // Додаткове обмеження, щоб значення точно було в діапазоні 1-100
            $normalizedScore = max(1, min(100, $normalizedScore));

            $calculatedBadgesSummary[] = [
                'badgeId' => $badgeDef['badgeId'],
                'score' => round($normalizedScore)
            ];
        } else {
            // Якщо для бейджа не було жодного валідного питання з оцінками
            $calculatedBadgesSummary[] = [
                'badgeId' => $badgeDef['badgeId'],
                'score' => 1 // Мінімальний можливий бал
            ];
        }
    }

    return [
        'success' => true,
        'message' => "Розрахунок бейджів для '{$username}' завершено.",
        'badges_summary' => $calculatedBadgesSummary
    ];
}