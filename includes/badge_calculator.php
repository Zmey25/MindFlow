<?php // includes/badge_calculator.php

// Конфігураційні константи для розрахунку бейджів
if (!defined('BADGES_FILE_PATH')) {
    define('BADGES_FILE_PATH', __DIR__ . '/../data/badges.json');
}
if (!defined('MIN_QUESTION_SCORE')) {
    define('MIN_QUESTION_SCORE', 1);
}
if (!defined('MAX_QUESTION_SCORE')) {
    define('MAX_QUESTION_SCORE', 7);
}
// Список ID користувачів, чиї оцінки вважаються експертними
if (!defined('EXPERT_USER_IDS')) {
    define('EXPERT_USER_IDS', ['user_67e98ba8df12a0.39404586']);
}

/**
 * Розраховує бали для всіх бейджів користувача.
 *
 * @param string $username Ім'я користувача.
 * @return array Масив з результатом.
 */
function calculateUserBadges(string $username): array {
    $userData = loadUserData($username);
    if (empty($userData) && !file_exists(getUserAnswersFilePath($username))) {
        return ['success' => false, 'message' => "Профіль користувача '{$username}' не знайдено.", 'badges_summary' => []];
    }

    $badgesDefinitionList = readJsonFile(BADGES_FILE_PATH);
    if ($badgesDefinitionList === null) {
        return ['success' => false, 'message' => "Файл визначень бейджів (badges.json) не знайдено або невалідний.", 'badges_summary' => []];
    }
    if (empty($badgesDefinitionList) && is_array($badgesDefinitionList)) {
        return ['success' => true, 'message' => "Список визначень бейджів порожній.", 'badges_summary' => []];
    }
    if (!is_array($badgesDefinitionList)) {
        return ['success' => false, 'message' => "Файл визначень бейджів (badges.json) має некоректний формат.", 'badges_summary' => []];
    }

    $calculatedBadgesSummary = [];
    $expertIds = defined('EXPERT_USER_IDS') ? EXPERT_USER_IDS : [];

    foreach ($badgesDefinitionList as $badgeDef) {
        if (!isset($badgeDef['badgeId'], $badgeDef['questions']) || !is_array($badgeDef['questions']) || empty($badgeDef['questions'])) {
            continue;
        }

        $currentBadgeRawSum = 0;
        $validQuestionsCount = 0;

        foreach ($badgeDef['questions'] as $questionInfo) {
            $questionId = $questionInfo['questionId'];
            $isReversed = $questionInfo['isReversed'] ?? false;

            // 1. Оцінка від самого користувача (self)
            $selfScore = null;
            if (isset($userData['self']['answers'][$questionId]) && is_numeric($userData['self']['answers'][$questionId])) {
                $selfScore = (float)$userData['self']['answers'][$questionId];
            }

            // 2. Оцінки від експертів та інших
            $expertScore = null;
            $otherScores = [];
            if (isset($userData['others']) && is_array($userData['others'])) {
                foreach ($userData['others'] as $assessment) {
                    $assessorId = $assessment['respondentUserId'] ?? null;
                    if (isset($assessment['answers'][$questionId]) && is_numeric($assessment['answers'][$questionId])) {
                        $score = (float)$assessment['answers'][$questionId];
                        if ($assessorId && in_array($assessorId, $expertIds)) {
                            // Наразі беремо оцінку першого знайденого експерта
                            if ($expertScore === null) {
                               $expertScore = $score;
                            }
                        } else {
                            $otherScores[] = $score;
                        }
                    }
                }
            }

            // 3. Середня оцінка від інших (не експертів)
            $avgOtherScore = null;
            if (!empty($otherScores)) {
                $avgOtherScore = array_sum($otherScores) / count($otherScores);
            }

            // Комбінуємо наявні оцінки (self, expert, others)
            $finalScores = [];
            if ($selfScore !== null) $finalScores[] = $selfScore;
            if ($expertScore !== null) $finalScores[] = $expertScore;
            if ($avgOtherScore !== null) $finalScores[] = $avgOtherScore;

            if (empty($finalScores)) {
                continue; // Питання не має оцінок, пропускаємо
            }
            
            $finalQuestionScore = array_sum($finalScores) / count($finalScores);
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
            $normalizedScore = 1;

            if (($maxPossibleSum - $minPossibleSum) > 0) {
                $normalizedScore = 1 + (($currentBadgeRawSum - $minPossibleSum) / ($maxPossibleSum - $minPossibleSum)) * 99;
            } elseif ($currentBadgeRawSum >= $maxPossibleSum) {
                $normalizedScore = 100;
            }
            
            $calculatedBadgesSummary[] = [
                'badgeId' => $badgeDef['badgeId'],
                'score' => round(max(1, min(100, $normalizedScore)))
            ];
        } else {
            $calculatedBadgesSummary[] = ['badgeId' => $badgeDef['badgeId'], 'score' => 1];
        }
    }

    return [
        'success' => true,
        'message' => "Розрахунок бейджів для '{$username}' завершено.",
        'badges_summary' => $calculatedBadgesSummary
    ];
}
