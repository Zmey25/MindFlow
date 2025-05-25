<?php // includes/trait_calculator.php

// Переконайтесь, що ці файли вже підключені там, де буде викликатись ця функція,
// або додайте require_once тут, якщо потрібно.
// require_once __DIR__ . '/functions.php';
// require_once __DIR__ . '/questionnaire_logic.php'; // Потрібно для loadUserData, saveUserData, getUserAnswersFilePath

/**
 * Допоміжна функція для порівняння значень (скопійовано з recalculate_traits.php)
 */
if (!function_exists('compareTraitValues')) { // Запобігання повторному визначенню
    function compareTraitValues($value1, $operator, $value2): bool {
        if (in_array($operator, ['>=', '<=', '>', '<'])) {
            if (!is_numeric($value1) || !is_numeric($value2)) return false;
            $v1 = (float)$value1; $v2 = (float)$value2;
            switch ($operator) {
                case '>=': return $v1 >= $v2;
                case '<=': return $v1 <= $v2;
                case '>':  return $v1 >  $v2;
                case '<':  return $v1 <  $v2;
            }
        } elseif ($operator === '==') { return $value1 == $value2; }
        elseif ($operator === '!=') { return $value1 != $value2; }
        return false;
    }
}


/**
 * Розраховує, які тріти має отримати користувач на основі його даних.
 * НЕ зберігає результат, лише повертає список отриманих трітів.
 *
 * @param string $username Ім'я користувача для перерахунку.
 * @return array Масив з результатом:
 *               [
 *                   'success' => bool,
 *                   'message' => string,
 *                   'earned_traits' => array // Масив повних об'єктів отриманих трітів
 *                   'earned_ids' => array   // Масив тільки ID отриманих трітів
 *               ]
 */
function calculateEarnedTraits(string $username): array {
    // Переконаємось, що константу визначено. Якщо ні, використовуємо типовий шлях.
    if (!defined('TRAITS_FILE_PATH')) {
       define('TRAITS_FILE_PATH', __DIR__ . '/../data/traits.json');
    }

    $userData = loadUserData($username);
    // Перевірка чи користувач взагалі існує (чи є його файл даних)
    if (empty($userData) && !file_exists(getUserAnswersFilePath($username))) {
        return ['success' => false, 'message' => "Профіль користувача '{$username}' не знайдено.", 'earned_traits' => [], 'earned_ids' => []];
    }

    $traitsFileData = readJsonFile(TRAITS_FILE_PATH);
    $allDefinedTraits = $traitsFileData['traits'] ?? [];

    if (empty($allDefinedTraits)) {
        return ['success' => true, 'message' => "Список трітів порожній.", 'earned_traits' => [], 'earned_ids' => []];
    }

    $earnedTraitsResult = [];
    $earnedTraitIdsResult = [];

    foreach ($allDefinedTraits as $trait) {
        if (!isset($trait['id'], $trait['conditions']) || !is_array($trait['conditions'])) {
            continue; // Пропускаємо некоректно визначений тріт
        }

        $allConditionsMet = true;

        foreach ($trait['conditions'] as $condition) {
            $type = $condition['type'] ?? null;
            $questionId = $condition['questionId'] ?? null;
            $operator = $condition['operator'] ?? null;
            $value = $condition['value'] ?? null;
            $aggregation = $condition['aggregation'] ?? null;

            if (!$type || !$questionId || !$operator || $value === null) {
                $allConditionsMet = false; break;
            }

            $conditionMet = false;

            if ($type === 'self') {
                $selfAnswer = $userData['self']['answers'][$questionId] ?? null;
                if ($selfAnswer !== null) {
                    $conditionMet = compareTraitValues($selfAnswer, $operator, $value);
                }
            } elseif ($type === 'others') {
                $otherAnswers = [];
                 if (isset($userData['others']) && is_array($userData['others'])) {
                    foreach ($userData['others'] as $assessment) {
                        if (isset($assessment['answers'][$questionId]) && is_numeric($assessment['answers'][$questionId])) {
                            $otherAnswers[] = (float) $assessment['answers'][$questionId];
                        }
                    }
                 }

                 if (empty($otherAnswers)) {
                     $conditionMet = false;
                 } else {
                     if ($aggregation === 'average') {
                         $average = count($otherAnswers) > 0 ? array_sum($otherAnswers) / count($otherAnswers) : null;
                         if ($average !== null) {
                             $conditionMet = compareTraitValues($average, $operator, $value);
                         }
                     } elseif ($aggregation === 'any') {
                         foreach ($otherAnswers as $answer) {
                             if (compareTraitValues($answer, $operator, $value)) {
                                 $conditionMet = true; break;
                             }
                         }
                     } elseif ($aggregation === 'all') {
                         $conditionMet = true;
                         foreach ($otherAnswers as $answer) {
                             if (!compareTraitValues($answer, $operator, $value)) {
                                 $conditionMet = false; break;
                             }
                         }
                     } else { $conditionMet = false; }
                 }
            } else { $allConditionsMet = false; break; }

            if (!$conditionMet) {
                $allConditionsMet = false; break;
            }
        }

        if ($allConditionsMet) {
             $earnedTraitsResult[] = [
                'id' => $trait['id'],
                'name' => $trait['name'] ?? '',
                'icon' => $trait['icon'] ?? '',
                'description' => $trait['description'] ?? ''
            ];
            $earnedTraitIdsResult[] = $trait['id'];
        }
    }

    return [
        'success' => true,
        'message' => "Розрахунок трітів для '{$username}' завершено.",
        'earned_traits' => $earnedTraitsResult,
        'earned_ids' => $earnedTraitIdsResult
    ];
}
?>