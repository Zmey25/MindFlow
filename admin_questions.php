<?php
// admin_questions.php - Включається в admin.php

// Перевірка, чи змінні існують (для безпеки)
$searchQuery = $searchQuery ?? '';
$currentPage = $currentPage ?? 1;
$questionsData = $questionsData ?? [];
$editCategoryData = $editCategoryData ?? null;
$editQuestionData = $editQuestionData ?? null;
$editCategoryIndex = $editCategoryIndex ?? null;
$editQuestionIndex = $editQuestionIndex ?? null;
$selected_cat_index = $selected_cat_index ?? null;
// $selected_q_index - не використовується активно в цьому файлі, але передається

// Допоміжні змінні для збереження стану користувачів у посиланнях/формах
$userStateParams = ['section' => 'questions'];
if (!empty($searchQuery)) $userStateParams['search_query'] = $searchQuery;
if ($currentPage > 1) $userStateParams['page'] = $currentPage;

?>

<div id="questions-section">
    <h2>Управління Питаннями</h2>

    <!-- ФОРМА РЕДАГУВАННЯ КАТЕГОРІЇ (без змін) -->
    <?php if ($editCategoryData !== null && $editCategoryIndex !== null): ?>
        <div id="edit-category-form" class="edit-form-section">
            <h3>Редагування Категорії: <?php echo htmlspecialchars($editCategoryData['categoryName']); ?></h3>
            <form action="admin.php#edit-category-form" method="post">
                <input type="hidden" name="action" value="update_category">
                <input type="hidden" name="categoryIndex" value="<?php echo $editCategoryIndex; ?>">
                <input type="hidden" name="section" value="questions">
                <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($searchQuery); ?>">
                <input type="hidden" name="page" value="<?php echo $currentPage; ?>">

                <div class="form-group">
                    <label for="editCategoryName">Назва категорії:</label>
                    <input type="text" id="editCategoryName" name="categoryName" value="<?php echo htmlspecialchars($editCategoryData['categoryName']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="editCategoryId">ID категорії:</label>
                    <input type="text" id="editCategoryId" name="categoryId" value="<?php echo htmlspecialchars($editCategoryData['categoryId']); ?>" required pattern="^[a-zA-Z0-9_]+$" title="Тільки літери, цифри та _">
                </div>
                <button type="submit" class="btn btn-primary">Оновити категорію</button>
                <a href="admin.php?<?php echo http_build_query(array_merge($userStateParams)); ?>#questions-section" class="cancel-btn">Скасувати</a>
            </form>
        </div>
        <hr>
    <?php endif; ?>


     <!-- СПИСОК КАТЕГОРІЙ ТА ФОРМА ДОДАВАННЯ КАТЕГОРІЇ (без змін) -->
     <?php if ($editCategoryData === null): ?>
        <h3>Категорії Питань</h3>
        <div class="category-list">
            <?php if (empty($questionsData)): ?>
                <p>Ще немає жодної категорії.</p>
            <?php else: ?>
                <?php foreach ($questionsData as $catIndex => $category): ?>
                    <div class="category-item <?php echo ($selected_cat_index === $catIndex) ? 'selected' : ''; ?>" id="category-<?php echo $catIndex; ?>">
                        <div class="category-name">
                            <a href="admin.php?<?php echo http_build_query(array_merge($userStateParams, ['selected_cat_index' => $catIndex])); ?>#category-<?php echo $catIndex; ?>">
                                <?php echo htmlspecialchars($category['categoryName']); ?>
                                <small>(ID: <?php echo htmlspecialchars($category['categoryId']); ?>)</small>
                            </a>
                        </div>
                        <div class="actions">
                            <a href="admin.php?<?php echo http_build_query(array_merge($userStateParams, ['action' => 'edit_category', 'categoryIndex' => $catIndex])); ?>#edit-category-form" class="edit-btn btn-sm">Редагувати</a>
                            <form action="admin.php#questions-section" method="post" onsubmit="return confirm('Видалити категорію \'<?php echo htmlspecialchars(addslashes($category['categoryName']), ENT_QUOTES); ?>\' та всі її питання?');">
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="categoryIndex" value="<?php echo $catIndex; ?>">
                                <input type="hidden" name="section" value="questions">
                                <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($searchQuery); ?>">
                                <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
                                <button type="submit" class="delete-btn btn-sm">Видалити</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Форма Додавання Нової Категорії (без змін) -->
        <?php if ($editQuestionData === null): // Не показувати, якщо редагуємо питання ?>
            <div id="add-category-form" class="section-form">
                 <h4>Додати Нову Категорію</h4>
                <form action="admin.php#add-category-form" method="post">
                    <input type="hidden" name="action" value="add_category">
                    <input type="hidden" name="section" value="questions">
                    <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
                    <div class="form-group">
                        <label for="addCategoryName">Назва нової категорії:</label>
                        <input type="text" id="addCategoryName" name="categoryName" required>
                    </div>
                    <div class="form-group">
                        <label for="addCategoryId">ID нової категорії:</label>
                        <input type="text" id="addCategoryId" name="categoryId" required pattern="^[a-zA-Z0-9_]+$" title="Тільки літери, цифри та _">
                    </div>
                    <button type="submit" class="add-btn">Додати категорію</button>
                </form>
            </div>
         <?php endif; ?>
         <hr>
     <?php endif; // кінець блоку "якщо не редагуємо категорію" ?>


    <!-- БЛОК ВИБРАНОЇ КАТЕГОРІЇ (ПИТАННЯ ТА ФОРМА ДОДАВАННЯ ПИТАННЯ) -->
    <?php if ($selected_cat_index !== null && isset($questionsData[$selected_cat_index]) && $editCategoryData === null): ?>
        <?php $selectedCategory = $questionsData[$selected_cat_index]; ?>
        <div id="selected-category-section">
            <h3>Питання в категорії: "<?php echo htmlspecialchars($selectedCategory['categoryName']); ?>"</h3>

             <!-- Форма Додавання Нового Питання (тільки якщо НЕ редагуємо питання) -->
             <?php if ($editQuestionData === null): ?>
                <div id="add-question-form" class="section-form">
                    <h4>Додати нове питання</h4>
                    <form action="admin.php#category-<?php echo $selected_cat_index; ?>" method="post">
                        <input type="hidden" name="action" value="add_question">
                        <input type="hidden" name="section" value="questions">
                        <input type="hidden" name="selected_cat_index" value="<?php echo $selected_cat_index; ?>">
                        <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($searchQuery); ?>">
                        <input type="hidden" name="page" value="<?php echo $currentPage; ?>">

                        <div class="form-group">
                            <label for="addQuestionId">ID питання:</label>
                            <input type="text" id="addQuestionId" name="questionId" required pattern="^[a-zA-Z0-9_]+$" title="Тільки літери, цифри та _">
                        </div>
                        <div class="form-group">
                            <label for="addQSelf">Питання про себе:</label>
                            <textarea id="addQSelf" name="q_self" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="addQShort">Скорочено про що питання:</label>
                            <input type="text" id="addQShort" name="q_short">
                             <small class="form-note">Короткий опис для звітів, заголовків.</small>
                        </div>
                         <div class="form-group">
                            <label for="addQOther">Питання для інших:</label>
                            <textarea id="addQOther" name="q_other" required></textarea>
                             <small class="form-note">Як це питання звучатиме, коли оцінюють іншого.</small>
                        </div>
                        <fieldset>
                            <legend>Шкала оцінювання</legend>
                            <div class="form-inline">
                                <label for="addScaleMin">Min:</label>
                                <input type="number" id="addScaleMin" name="scaleMin" value="1" required>
                                <label for="addScaleMinLabel" class="label-text">Мітка Min:</label>
                                <input type="text" id="addScaleMinLabel" name="scaleMinLabel" required>
                            </div>
                             <div class="form-inline">
                                <label for="addScaleMax">Max:</label>
                                <input type="number" id="addScaleMax" name="scaleMax" value="7" required>
                                <label for="addScaleMaxLabel" class="label-text">Мітка Max:</label>
                                <input type="text" id="addScaleMaxLabel" name="scaleMaxLabel" required>
                             </div>
                        </fieldset>
                        <button type="submit" class="add-btn">Додати питання</button>
                    </form>
                </div>
            <?php endif; ?>


            <!-- Список існуючих питань -->
            <h4>Існуючі питання:</h4>
             <div class="question-list">
                <?php if (empty($selectedCategory['questions'])): ?>
                    <p>У цій категорії ще немає питань.</p>
                <?php else: ?>
                     <?php foreach ($selectedCategory['questions'] as $qIndex => $question): ?>
                        <div class="question-item <?php echo ($editQuestionData !== null && $editQuestionIndex === $qIndex) ? 'selected' : ''; ?>" id="question-<?php echo $qIndex; ?>">
                            <!-- ***** ЗМІНИ ТУТ: Відображення питань ***** -->
                            <div class="question-text-block">
                                <?php // Посилання для ВИБОРУ/РЕДАГУВАННЯ питання ?>
                                <a href="admin.php?<?php echo http_build_query(array_merge($userStateParams, ['selected_cat_index' => $selected_cat_index, 'action' => 'edit_question', 'questionIndex' => $qIndex])); ?>#edit-question-form">
                                     <?php echo nl2br(htmlspecialchars($question['q_self'] ?? '')); // Використовуємо q_self ?>
                                    <small>(ID: <?php echo htmlspecialchars($question['questionId']); ?>)</small>
                                </a>
                                <?php if (!empty($question['q_short'])): ?>
                                    <div class="question-details question-short">
                                        <strong>Коротко:</strong> <?php echo htmlspecialchars($question['q_short']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($question['q_other'])): ?>
                                    <div class="question-details">
                                        <strong>Для інших:</strong> <?php echo nl2br(htmlspecialchars($question['q_other'])); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="question-details">
                                    <strong>Шкала:</strong> <?php echo htmlspecialchars($question['scale']['min'] ?? ''); ?> (<?php echo htmlspecialchars($question['scale']['minLabel'] ?? ''); ?>) - <?php echo htmlspecialchars($question['scale']['max'] ?? ''); ?> (<?php echo htmlspecialchars($question['scale']['maxLabel'] ?? ''); ?>)
                                </div>
                            </div>
                            <!-- ***** КІНЕЦЬ ЗМІН ***** -->
                            <div class="actions">
                                <?php // Кнопка Редагувати (дублює посилання вище) ?>
                                <a href="admin.php?<?php echo http_build_query(array_merge($userStateParams, ['selected_cat_index' => $selected_cat_index, 'action' => 'edit_question', 'questionIndex' => $qIndex])); ?>#edit-question-form" class="edit-btn btn-sm">Редагувати</a>
                                <form action="admin.php#category-<?php echo $selected_cat_index; ?>" method="post" onsubmit="return confirm('Видалити питання \'<?php echo htmlspecialchars(addslashes($question['questionId']), ENT_QUOTES); ?>\'?');">
                                    <input type="hidden" name="action" value="delete_question">
                                    <input type="hidden" name="categoryIndex" value="<?php echo $selected_cat_index; ?>">
                                    <input type="hidden" name="questionIndex" value="<?php echo $qIndex; ?>">
                                    <input type="hidden" name="section" value="questions">
                                    <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($searchQuery); ?>">
                                    <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
                                    <button type="submit" class="delete-btn btn-sm">Видалити</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
             </div>
        </div><!-- /#selected-category-section -->
    <?php endif; ?>


     <!-- ФОРМА РЕДАГУВАННЯ ПИТАННЯ (якщо активна) -->
     <?php if ($editQuestionData !== null && $editCategoryIndex !== null && $editQuestionIndex !== null): ?>
         <hr>
        <div id="edit-question-form" class="edit-form-section">
            <h3>Редагування Питання <small>(з категорії "<?php echo htmlspecialchars($questionsData[$editCategoryIndex]['categoryName']); ?>")</small></h3>
            <form action="admin.php#category-<?php echo $editCategoryIndex; ?>" method="post">
                <input type="hidden" name="action" value="update_question">
                <input type="hidden" name="categoryIndex" value="<?php echo $editCategoryIndex; ?>">
                <input type="hidden" name="questionIndex" value="<?php echo $editQuestionIndex; ?>">
                <input type="hidden" name="section" value="questions">
                <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($searchQuery); ?>">
                <input type="hidden" name="page" value="<?php echo $currentPage; ?>">

                <div class="form-group">
                    <label for="editQuestionId">ID питання:</label>
                    <input type="text" id="editQuestionId" name="questionId" value="<?php echo htmlspecialchars($editQuestionData['questionId']); ?>" required pattern="^[a-zA-Z0-9_]+$" title="Тільки літери, цифри та _">
                </div>
                <div class="form-group">
                    <label for="editQSelf">Питання про себе:</label>
                    <textarea id="editQSelf" name="q_self" required><?php echo htmlspecialchars($editQuestionData['q_self'] ?? ''); // Використовуємо q_self ?></textarea>
                </div>
                <div class="form-group">
                    <label for="editQShort">Скорочено про що питання:</label>
                    <input type="text" id="editQShort" name="q_short" value="<?php echo htmlspecialchars($editQuestionData['q_short'] ?? ''); ?>">
                    <small class="form-note">Короткий опис для звітів, заголовків.</small>
                </div>
                 <div class="form-group">
                    <label for="editQOther">Питання для інших:</label>
                    <textarea id="editQOther" name="q_other"><?php echo htmlspecialchars($editQuestionData['q_other'] ?? ''); ?></textarea>
                    <small class="form-note">Як це питання звучатиме, коли оцінюють іншого.</small>
                </div>
                <fieldset>
                    <legend>Шкала оцінювання</legend>
                     <div class="form-inline">
                        <label for="editScaleMin">Min:</label>
                        <input type="number" id="editScaleMin" name="scaleMin" value="<?php echo htmlspecialchars($editQuestionData['scale']['min'] ?? ''); ?>" required>
                        <label for="editScaleMinLabel" class="label-text">Мітка Min:</label>
                        <input type="text" id="editScaleMinLabel" name="scaleMinLabel" value="<?php echo htmlspecialchars($editQuestionData['scale']['minLabel'] ?? ''); ?>" required>
                    </div>
                     <div class="form-inline">
                        <label for="editScaleMax">Max:</label>
                        <input type="number" id="editScaleMax" name="scaleMax" value="<?php echo htmlspecialchars($editQuestionData['scale']['max'] ?? ''); ?>" required>
                        <label for="editScaleMaxLabel" class="label-text">Мітка Max:</label>
                        <input type="text" id="editScaleMaxLabel" name="scaleMaxLabel" value="<?php echo htmlspecialchars($editQuestionData['scale']['maxLabel'] ?? ''); ?>" required>
                     </div>
                </fieldset>
                <button type="submit" class="btn btn-primary">Оновити питання</button>
                <?php // Посилання "Скасувати" повертає до списку питань вибраної категорії ?>
                <a href="admin.php?<?php echo http_build_query(array_merge($userStateParams, ['selected_cat_index' => $editCategoryIndex])); ?>#category-<?php echo $editCategoryIndex; ?>" class="cancel-btn">Скасувати</a>
            </form>
        </div>
     <?php endif; ?>

</div><!-- /#questions-section -->