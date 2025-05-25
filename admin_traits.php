<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// admin_traits.php
// Включається в admin.php, тому змінні $message, $message_type, etc. доступні

// --- ЧИТАННЯ ДАНИХ ---
// $allTraits зазвичай читається в admin.php
// Якщо ні: $traitsFileData = readJsonFile(TRAITS_FILE_PATH); $allTraits = $traitsFileData['traits'] ?? [];

// --- ІНІЦІАЛІЗАЦІЯ ---
// $editTraitData та $editTraitIndex визначаються в admin.php
$isEditingTrait = isset($editTraitData) && $editTraitIndex !== null;
$formAction = $isEditingTrait ? 'update_trait' : 'add_trait';
$formButtonText = $isEditingTrait ? 'Оновити Тріт' : 'Додати Тріт';

// --- ОТРИМАННЯ ДОСТУПНИХ ID ПИТАНЬ ---
$allQuestionIds = [];
$questionsFilePath = defined('QUESTIONS_FILE_PATH') ? QUESTIONS_FILE_PATH : __DIR__ . '/data/questions.json'; // Переконайся, що шлях вірний
$questionsDataForIds = readJsonFile($questionsFilePath);
foreach ($questionsDataForIds as $category) {
    if (!empty($category['questions']) && is_array($category['questions'])) {
        foreach ($category['questions'] as $question) {
            if (!empty($question['questionId'])) {
                $allQuestionIds[$question['questionId']] = $question['q_short'] ?? $question['questionId']; // Використовуємо q_short для зрозумілості
            }
        }
    }
}
ksort($allQuestionIds); // Сортуємо за ID

// Доступні оператори
$operators = ['>=' => '>= (більше або рівно)', '<=' => '<= (менше або рівно)', '==' => '== (рівно)', '>' => '> (більше)', '<' => '< (менше)'];
// Доступні агрегації
$aggregations = ['average' => 'Середнє (average)', 'any' => 'Хоча б один (any)', 'all' => 'Всі (all)'];

// Поточні умови для редагування (для передачі в JS)
$currentConditionsJson = '[]';
if ($isEditingTrait && isset($editTraitData['conditions']) && is_array($editTraitData['conditions'])) {
    $currentConditionsJson = json_encode($editTraitData['conditions']);
}

?>
<style>
    /* Стилі для кращого вигляду форми умов */
    .condition-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 15px;
        margin-bottom: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        background-color: #f9f9f9;
        align-items: center; /* Вирівнювання елементів по вертикалі */
    }
    .condition-row label {
        font-weight: bold;
        margin-right: 5px;
        margin-bottom: 0; /* Забираємо відступ знизу у label */
        white-space: nowrap;
    }
    .condition-row select,
    .condition-row input[type="number"] {
        padding: 5px;
        border: 1px solid #ccc;
        border-radius: 3px;
        flex-grow: 1; /* Дозволяємо елементам розтягуватись */
        min-width: 100px; /* Мінімальна ширина */
    }
     .condition-row .form-group {
         margin-bottom: 0; /* Забираємо стандартний відступ форми */
         display: flex;
         align-items: center;
         flex-basis: auto; /* Базова ширина */
     }
     .condition-row .form-group.aggregation-group {
         /* Стилі для групи агрегації, спочатку прихована */
         display: none;
     }
    .condition-row button.remove-condition {
        background-color: #f44336;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 3px;
        cursor: pointer;
        margin-left: auto; /* Притискаємо кнопку видалення праворуч */
        flex-shrink: 0; /* Не стискати кнопку */
    }
    .condition-row button.remove-condition:hover {
        background-color: #d32f2f;
    }
    #add-condition-btn {
        margin-top: 15px;
        padding: 8px 15px;
    }
</style>

<section id="traits-section" class="admin-section">
    <h2>Управління Трітами (Achievements/Traits)</h2>

    <!-- Форма для додавання/редагування Тріта -->
    <div class="section-form <?php echo $isEditingTrait ? 'edit-form-section' : ''; ?>" id="trait-form-section">
        <h3><?php echo $isEditingTrait ? 'Редагувати Тріт: ' . htmlspecialchars($editTraitData['name'] ?? '') : 'Додати Новий Тріт'; ?></h3>
        <form action="admin.php?section=traits" method="post">
            <input type="hidden" name="action_trait" value="<?php echo $formAction; ?>">
            <?php if ($isEditingTrait): ?>
                <input type="hidden" name="traitIndex" value="<?php echo htmlspecialchars($editTraitIndex); ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="traitId">ID Тріта:</label>
                <input type="text" id="traitId" name="traitId" value="<?php echo htmlspecialchars($editTraitData['id'] ?? ''); ?>" required <?php echo $isEditingTrait ? 'readonly' : ''; ?> placeholder="напр. high_self_rating">
                <?php if ($isEditingTrait): ?><small>ID не можна змінити.</small><?php else: ?><small>Унікальний ідентифікатор (латиниця, цифри, _).</small><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="traitName">Назва:</label>
                <input type="text" id="traitName" name="traitName" value="<?php echo htmlspecialchars($editTraitData['name'] ?? ''); ?>" required placeholder="напр. Впевнений у собі">
            </div>
            <div class="form-group">
                <label for="traitIcon">Іконка:</label>
                <input type="text" id="traitIcon" name="traitIcon" value="<?php echo htmlspecialchars($editTraitData['icon'] ?? ''); ?>" placeholder="напр. fas fa-user-check">
                 <small>Клас Font Awesome. <a href="https://fontawesome.com/search?m=free" target="_blank">Знайти іконку</a>.</small>
            </div>
            <div class="form-group">
                <label for="traitDescription">Опис:</label>
                <textarea id="traitDescription" name="traitDescription" rows="2" placeholder="Короткий опис тріта, який побачить користувач."><?php echo htmlspecialchars($editTraitData['description'] ?? ''); ?></textarea>
            </div>

            <h4>Умови для отримання тріта:</h4>
            <div id="conditions-container">
                <!-- Сюди будуть додаватися рядки умов -->
            </div>
            <button type="button" id="add-condition-btn" class="button">Додати Умову</button>
            <small>Всі додані умови повинні бути виконані одночасно (логіка AND).</small>

            <hr>
            <button type="submit"><?php echo $formButtonText; ?></button>
            <?php if ($isEditingTrait): ?>
                <a href="admin.php?section=traits" class="cancel-button">Скасувати редагування</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Шаблон для нового рядка умови (прихований) -->
    <template id="condition-template">
        <div class="condition-row">
            <div class="form-group">
                <label>Тип:</label>
                <select name="conditions[INDEX][type]" class="condition-type" required>
                    <option value="self">Самооцінка (self)</option>
                    <option value="others">Оцінки інших (others)</option>
                </select>
            </div>
            <div class="form-group">
                 <label>Питання:</label>
                 <select name="conditions[INDEX][questionId]" required>
                     <option value="">-- Виберіть питання --</option>
                     <?php foreach ($allQuestionIds as $qId => $qLabel): ?>
                         <option value="<?php echo htmlspecialchars($qId); ?>"><?php echo htmlspecialchars($qLabel); ?></option>
                     <?php endforeach; ?>
                 </select>
             </div>
            <div class="form-group">
                 <label>Оператор:</label>
                 <select name="conditions[INDEX][operator]" required>
                     <?php foreach ($operators as $opVal => $opLabel): ?>
                         <option value="<?php echo htmlspecialchars($opVal); ?>"><?php echo htmlspecialchars($opLabel); ?></option>
                     <?php endforeach; ?>
                 </select>
            </div>
             <div class="form-group">
                 <label>Значення:</label>
                 <input type="number" name="conditions[INDEX][value]" step="any" required placeholder="Порівняти з...">
            </div>
             <div class="form-group aggregation-group">
                 <label>Агрегація:</label>
                 <select name="conditions[INDEX][aggregation]">
                     <?php foreach ($aggregations as $aggVal => $aggLabel): ?>
                         <option value="<?php echo htmlspecialchars($aggVal); ?>"><?php echo htmlspecialchars($aggLabel); ?></option>
                     <?php endforeach; ?>
                 </select>
             </div>
            <button type="button" class="remove-condition">Видалити</button>
        </div>
    </template>

    <!-- Список існуючих Трітів (Залишаємо без змін) -->
    <h3>Існуючі Тріти</h3>
    <?php if (empty($allTraits)): ?>
        <p>Ще немає жодного тріта.</p>
    <?php else: ?>
        <table class="admin-table">
             <thead>
                 <tr>
                     <th>ID</th>
                     <th>Іконка</th>
                     <th>Назва</th>
                     <th>Опис</th>
                     <th>Умови (кількість)</th>
                     <th>Дії</th>
                 </tr>
             </thead>
             <tbody>
                 <?php foreach ($allTraits as $index => $trait): ?>
                     <tr id="trait-<?php echo $index; ?>">
                         <td><?php echo htmlspecialchars($trait['id'] ?? 'N/A'); ?></td>
                         <td><i class="<?php echo htmlspecialchars($trait['icon'] ?? ''); ?>"></i></td>
                         <td><?php echo htmlspecialchars($trait['name'] ?? 'N/A'); ?></td>
                         <td><?php echo htmlspecialchars($trait['description'] ?? ''); ?></td>
                         <td><?php echo isset($trait['conditions']) && is_array($trait['conditions']) ? count($trait['conditions']) : 0; ?></td>
                         <td>
                             <a href="admin.php?section=traits&action=edit_trait&traitIndex=<?php echo $index; ?>#trait-form-section" class="action-link edit-link">Редагувати</a>
                             <form action="admin.php?section=traits" method="post" style="display: inline;" onsubmit="return confirm('Ви впевнені, що хочете видалити тріт \'<?php echo htmlspecialchars(addslashes($trait['name'] ?? '')); ?>\'?');">
                                 <input type="hidden" name="action_trait" value="delete_trait">
                                 <input type="hidden" name="traitIndex" value="<?php echo $index; ?>">
                                 <button type="submit" class="action-link delete-link">Видалити</button>
                             </form>
                         </td>
                     </tr>
                 <?php endforeach; ?>
             </tbody>
        </table>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const conditionsContainer = document.getElementById('conditions-container');
    const addConditionBtn = document.getElementById('add-condition-btn');
    const conditionTemplate = document.getElementById('condition-template');
    let conditionIndex = 0; // Лічильник для унікальних імен полів

    // Функція для додавання нового рядка умови
    function addConditionRow(conditionData = null) {
        const templateContent = conditionTemplate.content.cloneNode(true);
        const newRow = templateContent.querySelector('.condition-row');

        // Оновлюємо індекси в атрибутах name
        newRow.innerHTML = newRow.innerHTML.replace(/\[INDEX\]/g, `[${conditionIndex}]`);

        const typeSelect = newRow.querySelector('.condition-type');
        const aggregationGroup = newRow.querySelector('.aggregation-group');

        // Функція для показу/приховування агрегації
        function toggleAggregation(selectedType) {
            if (selectedType === 'others') {
                aggregationGroup.style.display = 'flex'; // Показуємо як flex елемент
                aggregationGroup.querySelector('select').required = true; // Робимо обов'язковим
            } else {
                aggregationGroup.style.display = 'none'; // Приховуємо
                 aggregationGroup.querySelector('select').required = false; // Робимо необов'язковим
                 aggregationGroup.querySelector('select').value = 'average'; // Скидаємо значення за замовчуванням
            }
        }

        // Встановлюємо обробник подій для зміни типу
        typeSelect.addEventListener('change', function() {
            toggleAggregation(this.value);
        });

        // Якщо передані дані (редагування), заповнюємо поля
        if (conditionData) {
            newRow.querySelector(`select[name="conditions[${conditionIndex}][type]"]`).value = conditionData.type || 'self';
            newRow.querySelector(`select[name="conditions[${conditionIndex}][questionId]"]`).value = conditionData.questionId || '';
            newRow.querySelector(`select[name="conditions[${conditionIndex}][operator]"]`).value = conditionData.operator || '>=';
            newRow.querySelector(`input[name="conditions[${conditionIndex}][value]"]`).value = conditionData.value !== undefined ? conditionData.value : '';
            if(conditionData.type === 'others') {
                 newRow.querySelector(`select[name="conditions[${conditionIndex}][aggregation]"]`).value = conditionData.aggregation || 'average';
            }
        }

        // Викликаємо функцію для встановлення початкового стану агрегації
        toggleAggregation(typeSelect.value);

        // Додаємо обробник для кнопки видалення
        const removeBtn = newRow.querySelector('.remove-condition');
        removeBtn.addEventListener('click', function() {
            newRow.remove();
            // Можливо, тут треба буде переіндексувати інші рядки, але для PHP це не обов'язково
        });

        conditionsContainer.appendChild(newRow);
        conditionIndex++; // Збільшуємо індекс для наступного рядка
    }

    // Обробник для кнопки "Додати Умову"
    addConditionBtn.addEventListener('click', function() {
        addConditionRow();
    });

    // --- Заповнення умов при редагуванні ---
    const existingConditions = <?php echo $currentConditionsJson; ?>;
    if (Array.isArray(existingConditions) && existingConditions.length > 0) {
        existingConditions.forEach(condition => {
            addConditionRow(condition);
        });
    } else if (!<?php echo $isEditingTrait ? 'true' : 'false'; ?>) {
         // Якщо це форма додавання (не редагування) і немає умов, додаємо один порожній рядок
         addConditionRow();
    }

});
</script>