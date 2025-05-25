<?php
// includes/results_category_card.php

// --- Очікувані Змінні ---
// Необхідні:
// $category (array): Дані категорії (name, etc.)
// $categoryId (string): ID категорії
// $questionsInCategory (array): Питання в цій категорії
// $canDisplayChart (bool): Чи можна малювати діаграму
// $categoryStyle (string): Inline стиль для H2
// MIN_QUESTIONS_FOR_RADAR (constant): Мінімальна к-сть питань для діаграми

// Опціональні (можуть бути null або порожніми):
// $selfAnswers (array|null): Самооцінка ЦІЛЬОВОГО користувача (questionId => score)
// $averageOtherScores (array): Середні оцінки інших про ЦІЛЬОВОГО (questionId => ['average' => score, 'count' => num]) (може бути [])
// $hasOtherAnswers (bool): Чи є ВЗАГАЛІ оцінки інших про ЦІЛЬОВОГО користувача
// $viewerSpecificAnswers (array|null): Оцінки ПОТОЧНОГО користувача про ЦІЛЬОВОГО (questionId => score)
// $isViewingOther (bool): Чи поточний користувач дивиться ЧУЖИЙ профіль?
// $targetUsername (string): Ім'я користувача, чиї результати переглядаються

// --- Перевірка Базових Даних ---
if (!isset($category, $categoryId, $questionsInCategory, $canDisplayChart, $categoryStyle)) {
    echo "<div class='message error'>Помилка: Недостатньо базових даних для відображення картки категорії. ID: " . htmlspecialchars($categoryId ?? 'unknown') . "</div>";
    // Можна додати логування помилки для розробника
    // error_log("Missing essential data for category card: categoryId=" . ($categoryId ?? 'unknown'));
    return; // Зупиняємо виконання цього include
}

// Визначаємо, чи потрібно показувати колонку з оцінкою поточного глядача
// Потрібно, якщо: 1) дивимось чужий профіль, 2) є дані оцінок глядача (масив не null і не порожній)
$showViewerColumn = $isViewingOther && is_array($viewerSpecificAnswers) && !empty($viewerSpecificAnswers);

// Визначаємо, чи є хоч якісь дані самооцінки (перевіряємо чи $selfAnswers - це масив)
$hasSelfAnswersData = is_array($selfAnswers);

?>
<!-- Картка категорії -->
<div class="result-category-card">
    <h2 style="<?php echo $categoryStyle; /* Застосовуємо стиль кольору */ ?>">
        <?php echo htmlspecialchars($category['categoryName']); ?>
    </h2>

    <?php if ($canDisplayChart && (!empty($selfAnswers) || !empty($averageOtherScores) || !empty($viewerSpecificAnswers) )): // Показуємо контейнер діаграми, якщо її можна малювати І є хоч якісь дані для неї ?>
        <div class="chart-container">
            <?php // Тут буде відмальована діаграма за допомогою JavaScript ?>
            <canvas id="chart-<?php echo $categoryId; ?>"></canvas>
        </div>
    <?php elseif (!empty($questionsInCategory)): // Якщо питань достатньо, але даних нема, або питань замало ?>
        <div class="message info">
            <?php if ($questionCount < MIN_QUESTIONS_FOR_RADAR): ?>
                 Для побудови діаграми потрібно щонайменше <?php echo MIN_QUESTIONS_FOR_RADAR; ?> питання в цій категорії.
            <?php else: ?>
                 Недостатньо даних для побудови діаграми в цій категорії.
            <?php endif; ?>
             Детальні результати доступні в таблиці нижче.
        </div>
    <?php else: // Якщо в категорії взагалі немає питань ?>
        <div class="message info">В цій категорії немає визначених питань.</div>
    <?php endif; ?>


    <?php // Показуємо таблицю тільки якщо в категорії є питання ?>
    <?php if (!empty($questionsInCategory)): ?>
        <details class="table-details" <?php echo !$canDisplayChart ? 'open' : ''; /* Відкрита за замовчуванням, якщо немає діаграми */ ?>>
            <summary>Показати/Сховати детальну таблицю</summary>
            <div class="table-container">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Текст питання</th>

                            <?php // --- Заголовок Колонки Самооцінки --- ?>
                            <?php if ($hasSelfAnswersData || !$isViewingOther || $isAdmin): // Показуємо колонку самооцінки, якщо є дані, або якщо це власний профіль, або якщо адмін ?>
                            <th><?php echo $isViewingOther ? 'С. (' . htmlspecialchars($targetUsername) . ')' : 'Моя оцінка'; ?></th>
                            <?php endif; ?>

                            <?php // --- Заголовки Колонок Оцінок Інших --- ?>
                            <?php if ($hasOtherAnswers): ?>
                            <th>Ø інших</th>
                            <?php if ($hasSelfAnswersData): // Різницю показуємо тільки якщо є самооцінка для порівняння ?>
                            <th>Різниця</th>
                            <?php endif; ?>
                            <th>К-сть оцінок</th>
                            <?php endif; ?>

                            <?php // --- Заголовок Колонки Оцінки Глядача --- ?>
                            <?php if ($showViewerColumn): ?>
                            <th>Моя про <?php echo htmlspecialchars($targetUsername); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questionsInCategory as $qIndex => $question):
                            $questionId = $question['questionId'];
                            $questionText = $question['q_other']; // Використовуємо повний текст для title і potentially для cell

                            // --- Отримання Даних для Поточного Питання ---

                            // 1. Самооцінка цільового користувача
                            $selfScore = ($hasSelfAnswersData && isset($selfAnswers[$questionId]))
                                         ? $selfAnswers[$questionId]
                                         : null;

                            // 2. Середня оцінка інших про цільового
                            $otherAvgScore = null;
                            $otherCount = 0;
                            // Перевіряємо чи є оцінки інших ВЗАГАЛІ і чи є дані для ЦЬОГО питання
                            if ($hasOtherAnswers && is_array($averageOtherScores) && isset($averageOtherScores[$questionId])) {
                                // averageOtherScores[$questionId] вже містить ['average' => ?, 'count' => ?]
                                // calculateAverageOtherScores має повертати null для average якщо count = 0
                                $otherAvgScore = $averageOtherScores[$questionId]['average'];
                                $otherCount = $averageOtherScores[$questionId]['count'];
                            }

                            // 3. Різниця (якщо є і самооцінка, і середня оцінка інших)
                            $difference = null;
                            $diffClass = '';
                            if ($hasSelfAnswersData && $hasOtherAnswers && $selfScore !== null && $otherAvgScore !== null) {
                                $difference = round($selfScore - $otherAvgScore, 1);
                                 if ($difference > 0.5) $diffClass = 'positive';
                                 elseif ($difference < -0.5) $diffClass = 'negative';
                            }

                            // 4. Оцінка поточного глядача про цільового
                            $viewerScore = null;
                            if ($showViewerColumn && isset($viewerSpecificAnswers[$questionId])) { // $showViewerColumn вже перевіряє is_array
                                $viewerScore = $viewerSpecificAnswers[$questionId];
                            }
                        ?>
                            <tr>
                                <td class="question-col" title="<?php echo htmlspecialchars($questionText); ?>">
                                    <?php // Можна скоротити текст, якщо він задовгий, або залишити повний
                                      echo htmlspecialchars(mb_strlen($questionText) > 60 ? mb_substr($questionText, 0, 57).'...' : $questionText);
                                    ?>
                                </td>

                                <?php // --- Колонка Самооцінки --- ?>
                                <?php if ($hasSelfAnswersData || !$isViewingOther || $isAdmin): ?>
                                <td class="score-col <?php echo ($selfScore === null && $hasSelfAnswersData) ? 'no-data-expected' : ($selfScore === null ? 'no-data' : ''); ?>">
                                    <?php echo ($selfScore !== null) ? htmlspecialchars($selfScore) : 'N/A'; ?>
                                </td>
                                <?php endif; ?>

                                <?php // --- Колонки Оцінок Інших --- ?>
                                <?php if ($hasOtherAnswers): ?>
                                <td class="score-col <?php echo ($otherAvgScore === null) ? 'no-data' : ''; ?>">
                                    <?php echo ($otherAvgScore !== null) ? number_format($otherAvgScore, 1) : 'N/A'; ?>
                                </td>
                                <?php if ($hasSelfAnswersData): // Показуємо різницю ?>
                                <td class="score-col difference <?php echo $diffClass; ?> <?php echo ($difference === null) ? 'no-data' : ''; ?>">
                                    <?php echo ($difference !== null) ? (($difference > 0 ? '+' : '') . number_format($difference, 1)) : 'N/A'; ?>
                                </td>
                                <?php endif; ?>
                                <td class="score-col <?php echo ($otherCount === 0) ? 'no-data' : ''; ?>">
                                    <?php echo htmlspecialchars($otherCount); ?>
                                </td>
                                <?php endif; ?>

                                <?php // --- Колонка Оцінки Глядача --- ?>
                                <?php if ($showViewerColumn): ?>
                                <td class="score-col <?php echo ($viewerScore === null) ? 'no-data' : ''; ?>">
                                    <?php echo ($viewerScore !== null) ? htmlspecialchars($viewerScore) : 'N/A'; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; // Кінець циклу по питаннях ?>
                    </tbody>
                </table>
            </div>
        </details>
    <?php endif; // Кінець перевірки if (!empty($questionsInCategory)) ?>
</div> <!-- Кінець result-category-card -->