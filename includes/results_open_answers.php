<?php // includes/results_open_answers.php

/**
 * This file expects the following variables to be set before being included:
 * - $otherAssessmentsForInclude: An array of 'others' assessments for the target user.
 *                                Example: [['respondentUserId' => ..., 'answers' => [...]], ...]
 * - $targetUser: (NEWLY REQUIRED) An array containing data for the user whose results are being viewed.
 *                 Expected to have ['custom_question'] if a custom question exists.
 */

// --- Define Static Open Questions (Base set) ---
const STATIC_OPEN_QUESTIONS_DISPLAY = [
    'open_q_strength' => 'Щоб ви хотіли, щоб ця людина ПРОДОВЖУВАЛА робити?',
    'open_q_weakness' => 'Щоб ви хотіли, щоб ця людина ПЕРЕСТАЛА робити?',
    'open_q_interaction' => 'Щоб ви хотіли, щоб ця людина ПОЧАЛА робити?',
    'open_q_impression' => 'Як би ви коротко охарактеризували людину? (можно 1 словом)',
    'open_q_additional' => 'Чи є ще щось, що ви хотіли б додати про людину, чого не було в тестах?'
];

// --- Формування динамічного списку всіх відкритих питань для відображення ---
$allOpenQuestionsToDisplay = STATIC_OPEN_QUESTIONS_DISPLAY; // Починаємо зі статичних

// Перевіряємо, чи передано $targetUser і чи є у нього кастомне питання
if (isset($targetUser) && is_array($targetUser) && isset($targetUser['custom_question'])) {
    $customQuestionText = trim($targetUser['custom_question']);
    if (!empty($customQuestionText)) {
        // Ключ має співпадати з тим, що використовується в questionnaire_other.php та при збереженні
        // Наприклад, 'profile_cq_0' для першого кастомного питання
        $customQuestionKey = 'profile_cq_0'; // Поки що припускаємо одне кастомне питання
        $allOpenQuestionsToDisplay[$customQuestionKey] = $customQuestionText;
    }
}
// --- Кінець формування динамічного списку ---

$limitToShow = 5; // Show the latest 5 by default

// --- Data Processing ---
$groupedOpenAnswers = [];
foreach (array_keys($allOpenQuestionsToDisplay) as $key) {
    $groupedOpenAnswers[$key] = []; // Initialize for all possible questions
}
$hasAnyOpenAnswers = false;

if (!empty($otherAssessmentsForInclude) && is_array($otherAssessmentsForInclude)) {
    foreach (array_reverse($otherAssessmentsForInclude) as $assessment) { // Newest first
        if (isset($assessment['answers']) && is_array($assessment['answers'])) {
            // Тепер ітеруємо по $allOpenQuestionsToDisplay, щоб включити кастомні
            foreach ($allOpenQuestionsToDisplay as $key => $label) {
                if (!empty($assessment['answers'][$key]) && is_string($assessment['answers'][$key])) {
                    $answerText = trim(htmlspecialchars(htmlspecialchars_decode($assessment['answers'][$key], ENT_QUOTES), ENT_QUOTES, 'UTF-8'));
                    if (!empty($answerText)) {
                        $groupedOpenAnswers[$key][] = $answerText;
                        $hasAnyOpenAnswers = true;
                    }
                }
            }
        }
    }
}
// --- End Data Processing ---

?>

<?php if ($hasAnyOpenAnswers): ?>
<div class="results-card open-answers-section">
    <h2 class="card-header">Відкриті відповіді від інших</h2>
    <div class="card-content">
        <p class="info-text">Тут зібрані відповіді на відкриті питання, надані іншими користувачами (анонімно).</p>

        <?php $isEmptySectionOverall = true; // Флаг для перевірки, чи є хоч одна не порожня секція ?>
        <?php foreach ($allOpenQuestionsToDisplay as $key => $label): ?>
            <?php
                $answersList = $groupedOpenAnswers[$key] ?? [];
                $answerCount = count($answersList);
            ?>
            <?php if ($answerCount > 0): ?>
                <?php $isEmptySectionOverall = false; // Знайдено не порожню секцію ?>
                <div class="open-question-group">
                    <h3 class="open-question-label">
                        <?php echo htmlspecialchars($label); ?>
                        <?php if (strpos($key, 'profile_cq_') === 0): ?>
                            <em class="custom-question-source-display">(Кастомне питання)</em>
                        <?php endif; ?>
                        <span class="answer-count">(<?php echo $answerCount . ' ' . getUkrainianNounEnding($answerCount, 'відповідь', 'відповіді', 'відповідей'); ?>)</span>
                    </h3>
                    <ul class="answer-list" id="visible-answers-<?php echo htmlspecialchars($key); ?>">
                        <?php
                            $visibleAnswers = array_slice($answersList, 0, $limitToShow);
                            foreach ($visibleAnswers as $index => $answer):
                        ?>
                            <li class="answer-item"><?php echo $answer; // Already sanitized ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <?php
                        if ($answerCount > $limitToShow):
                            $hiddenAnswers = array_slice($answersList, $limitToShow);
                    ?>
                        <ul class="answer-list answer-list-hidden" id="hidden-answers-<?php echo htmlspecialchars($key); ?>" style="display: none;">
                             <?php foreach ($hiddenAnswers as $answer): ?>
                                <li class="answer-item"><?php echo $answer; // Already sanitized ?></li>
                             <?php endforeach; ?>
                        </ul>
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm toggle-answers-btn"
                            onclick="toggleOpenAnswers('<?php echo htmlspecialchars($key); ?>', <?php echo count($hiddenAnswers); ?>)"
                            id="toggle-btn-<?php echo htmlspecialchars($key); ?>"
                        >
                           Показати ще <?php echo count($hiddenAnswers); ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($isEmptySectionOverall && $hasAnyOpenAnswers): // Якщо $hasAnyOpenAnswers був true, але жодна секція не вивелась (малоймовірно) ?>
            <p>Немає відповідей на відкриті питання, доступних для відображення.</p>
        <?php elseif (!$hasAnyOpenAnswers) : // Цей блок має спрацювати, якщо $hasAnyOpenAnswers false з самого початку ?>
            <p>Немає відповідей на відкриті питання.</p>
        <?php endif; ?>

    </div>
</div>

<script>
function toggleOpenAnswers(key, hiddenCount) {
    // Ensure key is properly escaped if it can contain special characters for querySelector
    // For simple alphanumeric keys like 'open_q_strength' or 'profile_cq_0', direct use is fine.
    const hiddenList = document.getElementById('hidden-answers-' + key);
    const button = document.getElementById('toggle-btn-' + key);

    if (hiddenList && button) {
        if (hiddenList.style.display === 'none') {
            hiddenList.style.display = 'block';
            button.textContent = 'Приховати';
        } else {
            hiddenList.style.display = 'none';
            button.textContent = 'Показати ще ' + hiddenCount;
        }
    }
}
</script>

<?php /* Basic styling - ensure these styles don't conflict with existing ones */ ?>
<style>
.open-answers-section {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
    padding: 20px 25px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    margin-bottom: 25px;
      margin-top: 25px;
}
.open-answers-section .card-header {
  margin-bottom: 15px;
  font-size: 1.3em;
  color: #495057;
  border-bottom: 2px solid #e9ecef;
  padding-bottom: 8px;
}
.open-answers-section .card-content {
    padding: 20px;
}
.open-answers-section .info-text {
    font-size: 0.9em;
    color: #6c757d;
    margin-bottom: 20px;
}
.open-question-group {
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px dashed #eee;
}
.open-question-group:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}
.open-question-label {
    font-size: 1.1em;
    font-weight: 600;
    margin-bottom: 10px;
    color: #333;
}
.open-question-label .answer-count {
    font-size: 0.85em;
    font-weight: normal;
    color: #777;
}
.custom-question-source-display { /* Стиль для позначки кастомного питання */
    font-size: 0.8em;
    font-weight: normal;
    color: #555;
    margin-left: 5px;
}
.answer-list {
    list-style: none;
    padding-left: 0;
    margin-top: 0;
    margin-bottom: 10px;
}
.answer-item {
    background-color: #f9f9f9;
    padding: 10px 15px;
    margin-bottom: 8px;
    border-radius: 4px;
    border: 1px solid #eee;
    font-size: 0.95em;
    line-height: 1.5;
    word-wrap: break-word;
}
.toggle-answers-btn {
    font-size: 0.85em !important;
    padding: 5px 10px !important;
    cursor: pointer;
    margin-top: 5px;
}
.btn.btn-secondary.btn-sm {
    background-color: #6c757d;
    color: white;
    border: none;
    border-radius: 4px;
    transition: background-color 0.2s ease;
}
.btn.btn-secondary.btn-sm:hover {
    background-color: #5a6268;
    color: white;
}
</style>

<?php else: // If $hasAnyOpenAnswers is false from the start ?>
    <div class="results-card open-answers-section">
        <h2 class="card-header">Відкриті відповіді від інших</h2>
        <div class="card-content">
            <p>Поки що немає відповідей на відкриті питання для цього користувача.</p>
        </div>
    </div>
<?php endif; ?>