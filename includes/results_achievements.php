<?php
// includes/results_achievements.php
// Очікує змінну $userAchievements (масив об'єктів/масивів з даними ачівок)

if (!isset($userAchievements) || !is_array($userAchievements) || empty($userAchievements)) {
    // Можна нічого не виводити або показати повідомлення
    // echo "<p>У вас поки немає досягнень.</p>";
    return;
}

// Приклад структури однієї ачівки в масиві $userAchievements:
// [
//   "id" => "some_unique_id",
//   "name" => "Назва Ачівки",
//   "icon" => "fas fa-star", // Клас іконки (напр., Font Awesome) або шлях до зображення
//   "description" => "Короткий опис, чому отримано ачівку (може бути в title)"
// ]

?>
<div class="achievements-block">
    <h2>Мої особливості</h2>
    <ul class="achievements-list">
        <?php foreach ($userAchievements as $achievement): ?>
            <?php
                $name = htmlspecialchars($achievement['name'] ?? 'Невідома ачівка');
                $iconClass = htmlspecialchars($achievement['icon'] ?? 'fas fa-question-circle'); 
                $description = htmlspecialchars($achievement['description'] ?? ''); 
            ?>
            <li class="achievement-item achievement-item-details">
                 <details>
                    <summary>
                        <i class="<?php echo $iconClass; ?>"></i>
                        <span><?php echo $name; ?></span>
                    </summary>
                    <div class="achievement-description"> 
                        <?php echo $description; ?>
                    </div>
                </details>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<style>
/* Додайте цей CSS до вашого файлу стилів */

/* Стилізація для приховування стандартної стрілочки details */
.achievement-item-details details summary {
    list-style: none; /* Приховує стандартний маркер */
    cursor: pointer;  /* Показує, що елемент клікабельний */
    padding: 0;       /* Скидаємо стандартний padding, якщо він є */
    display: flex;    /* Дозволяє вирівнювати іконку та текст */
    align-items: center;
    /* Додайте інші стилі, щоб summary виглядав як початковий li контент */
}

.achievement-item-details details summary::-webkit-details-marker {
    display: none; /* Приховує маркер у Webkit браузерах (Chrome, Safari) */
}

.achievement-item-details details summary::marker {
    display: none; /* Приховує маркер у стандартних браузерах */
}


/* Стилізація для прихованого опису, який розкривається */
.achievement-item-details .achievement-description {
    margin-top: 8px;    /* Відступ зверху */
    padding: 8px 4px;  /* Внутрішній відступ */
    background-color: #eee; /* Легкий фон для опису */
    border-radius: 4px;
    font-size: 12px;
    color: #555;
    /* Можна додати анімацію появи, якщо потрібно */
}

/* Стилі для елементів всередині summary */
.achievement-item-details summary i {
    margin-right: 10px; /* Відступ між іконкою та текстом */
}

.achievement-item-details summary span {
    flex-grow: 1; /* Дозволяє тексту займати доступний простір */
}

/* Стилі для самого li, якщо потрібно */
.achievement-item-details {
     /* Додайте border, padding, margin, display як у ваших оригінальних li */
     display: block; /* details/summary поводиться як блок */
     padding: 10px; /* Приклад */
     border-bottom: 1px solid #ccc; /* Приклад */
     margin-bottom: 5px; /* Приклад */
}
</style>