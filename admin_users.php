        <!-- ========================================= -->
        <!--        СЕКЦІЯ УПРАВЛІННЯ КОРИСТУВАЧАМИ        -->
        <!-- ========================================= -->
        <h2>Управління Користувачами</h2>
        
        <form action="admin.php#users-section" method="GET" class="search-form">
             <label for="search_query">Пошук:</label>
             <input type="search" id="search_query" name="search_query" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Логін, ім'я або прізвище...">
             <input type="hidden" name="page" value="1">
             <button type="submit" class="btn btn-primary">Знайти</button>
             <?php if (!empty($searchQuery)): ?>
                <a href="admin.php?page=<?php echo $currentPage; ?>#users-section" class="cancel-btn" style="margin-left: 10px;">Скинути пошук</a>
             <?php endif; ?>
        </form>
        
         <?php if ($editUserData): ?>
            <div id="edit-user-form" class="edit-form-section">
                <h3>Редагування користувача: <?php echo htmlspecialchars($editUserData['username']); ?></h3>
                <form action="admin.php#users-section" method="POST">
                    <input type="hidden" name="action_user" value="update_user">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($editUserData['id']); ?>">
                    <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
        
                    <div class="form-group">
                        <label for="edit_username">Логін:</label>
                        <input type="text" id="edit_username" name="username" value="<?php echo htmlspecialchars($editUserData['username']); ?>" required pattern="^[a-zA-Z0-9_.]+$" title="Тільки латинські літери, цифри та _">
                    </div>
                     <div class="form-group">
                        <label for="edit_first_name">Ім'я:</label>
                        <input type="text" id="edit_first_name" name="first_name" value="<?php echo htmlspecialchars($editUserData['first_name'] ?? ''); ?>">
                    </div>
                     <div class="form-group">
                        <label for="edit_last_name">Прізвище:</label>
                        <input type="text" id="edit_last_name" name="last_name" value="<?php echo htmlspecialchars($editUserData['last_name'] ?? ''); ?>">
                    </div>
        
                    <h4 style="margin-top:20px; margin-bottom:10px;">Налаштування приватності</h4>
                    <div class="form-group checkbox-group">
                         <input type="checkbox" id="edit_hide_results" name="hide_results" value="1" <?php echo ($editUserData['hide_results'] ?? true) ? 'checked' : ''; ?>>
                         <label for="edit_hide_results">Приховати результати від інших</label>
                    </div>
                    <div class="form-group checkbox-group">
                         <input type="checkbox" id="edit_hide_test_link" name="hide_test_link" value="1" <?php echo ($editUserData['hide_test_link'] ?? false) ? 'checked' : ''; ?>>
                         <label for="edit_hide_test_link">Приховати посилання на тест про користувача</label>
                    </div>
                    <div class="form-group checkbox-group">
                         <input type="checkbox" id="edit_participate_in_ratings" name="participate_in_ratings" value="1" <?php echo ($editUserData['participate_in_ratings'] ?? true) ? 'checked' : ''; ?>>
                         <label for="edit_participate_in_ratings">Бере участь у рейтингах</label>
                    </div>
        
                    <p class="form-note">Пароль не змінюється через адмін-панель.</p>
                    <button type="submit" class="btn btn-primary">Оновити користувача</button>
                    <a href="admin.php?search_query=<?php echo urlencode($searchQuery); ?>&page=<?php echo $currentPage; ?>#users-section" class="cancel-btn">Скасувати</a>
                </form>
            </div>
        
         <?php else: ?>
            <div id="add-user-form" class="section-form">
                <h3>Додати нового користувача</h3>
                <form action="admin.php#users-section" method="POST">
                     <input type="hidden" name="action_user" value="add_user">
                     <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($searchQuery); ?>">
                     <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
                    <div class="form-group">
                        <label for="add_username">Логін:</label>
                        <input type="text" id="add_username" name="username" required pattern="^[a-zA-Z0-9_]+$" title="Тільки латинські літери, цифри та _">
                    </div>
                     <div class="form-group">
                        <label for="add_first_name">Ім'я:</label>
                        <input type="text" id="add_first_name" name="first_name">
                    </div>
                     <div class="form-group">
                        <label for="add_last_name">Прізвище:</label>
                        <input type="text" id="add_last_name" name="last_name">
                    </div>
                    <p class="form-note">Пароль буде встановлено за замовчуванням: <code>mindflow2025</code></p>
                    <button type="submit" class="btn add-btn">Додати користувача</button>
                </form>
            </div>
        <?php endif; ?>

            <!-- Таблиця користувачів -->
            <h3>Список користувачів</h3>
            <?php if ($totalUsers > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Логін</th>
                            <th>Ім'я</th>
                            <th>Прізвище</th>
                            <th class="actions-cell">Дії</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginatedUsers as $user): ?>
                        <tr>
                            <td><small><?php echo htmlspecialchars($user['id']); ?></small></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['first_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['last_name'] ?? '-'); ?></td>
                            <td class="actions-cell" style="min-width: 220px;"> <!-- Додано стиль для ширини -->
                                <div style="display: flex; align-items: center;">
                                    <select class="action-select" style="flex-grow: 1; margin-right: 5px;"
                                            data-user-id="<?php echo htmlspecialchars($user['id']); ?>"
                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                            data-escaped-username="<?php echo htmlspecialchars(addslashes($user['username']), ENT_QUOTES); ?>">
                                        <option value="">-- Обрати дію --</option>
                                        <option value="test" data-url="questionnaire_other.php?target_user_id=<?php echo htmlspecialchars($user['id']); ?>">Пройти тест про</option>
                                        <option value="edit" data-url="admin.php?action_user=edit_user&user_id=<?php echo htmlspecialchars($user['id']); ?>&search_query=<?php echo urlencode($searchQuery); ?>&page=<?php echo $currentPage; ?>#edit-user-form">Редагувати</option>
                                        <option value="recalculate" data-url="recalculate_traits.php?username=<?php echo urlencode($user['username']); ?>">Перерахувати тріти</option>
                                        <option value="analyze" data-url="get_analysis.php?username=<?php echo urlencode($user['username']); ?>">Зробити аналіз</option>
                                        <option value="results" data-url="results.php?user=<?php echo urlencode($user['username']); ?>">Подивитись результат</option>
                                        <?php if ($user['id'] !== $currentUserId): // Не показувати опцію видалення для себе ?>
                                            <option value="delete">Видалити</option>
                                        <?php else: ?>
                                            <option value="delete" disabled>Видалити (себе)</option>
                                        <?php endif; ?>
                                    </select>
                                    <button type="button" class="btn btn-sm action-execute-btn">Ok</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Пагінація -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                     <?php
                        $queryParams = ['search_query' => $searchQuery]; // Параметри для URL
                        // Попередня сторінка
                        if ($currentPage > 1) {
                            echo '<a href="?' . http_build_query(array_merge($queryParams, ['page' => $currentPage - 1])) . '#users-section">« Попередня</a>';
                        } else {
                            echo '<span class="disabled">« Попередня</span>';
                        }

                        // Номери сторінок
                        for ($i = 1; $i <= $totalPages; $i++) {
                            if ($i == $currentPage) {
                                echo '<span class="current-page">' . $i . '</span>';
                            } else {
                                echo '<a href="?' . http_build_query(array_merge($queryParams, ['page' => $i])) . '#users-section">' . $i . '</a>';
                            }
                        }

                        // Наступна сторінка
                        if ($currentPage < $totalPages) {
                            echo '<a href="?' . http_build_query(array_merge($queryParams, ['page' => $currentPage + 1])) . '#users-section">Наступна »</a>';
                        } else {
                             echo '<span class="disabled">Наступна »</span>';
                        }
                     ?>
                </div>
                 <p style="text-align: center; font-size: 0.9em; color: #6c757d;">Показано <?php echo count($paginatedUsers); ?> з <?php echo $totalUsers; ?> користувачів.</p>
                <?php endif; ?>

<!-- ========================================= -->
<!--        СЕКЦІЯ ОБ'ЄДНАННЯ КОРИСТУВАЧІВ       -->
<!-- ========================================= -->
<?php if ($totalUsers >= 2): // Показувати тільки якщо є кого об'єднувати ?>
<hr>
<div id="merge-users-section" class="section-form">
    <h3>Об'єднати користувачів</h3>
    <p class="form-note">Об'єднання перенесе відповіді користувача-джерела до цільового користувача та видалить джерело. Дані цільового користувача мають пріоритет за замовчуванням, якщо не вказано інше.</p>
    <form action="admin.php#merge-users-section" method="POST" onsubmit="return confirmMerge(this);">
        <input type="hidden" name="action_user" value="merge_users">
        <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($searchQuery); ?>">
        <input type="hidden" name="page" value="<?php echo $currentPage; ?>">

        <div class="form-group">
            <label for="source_user_id">Користувач-Джерело (буде видалено):</label>
            <select id="source_user_id" name="source_user_id" required>
                <option value="">-- Виберіть --</option>
                <?php foreach ($allUsers as $u): ?>
                    <option value="<?php echo htmlspecialchars($u['id']); ?>" data-username="<?php echo htmlspecialchars($u['username']); ?>">
                        <?php echo htmlspecialchars($u['username'] . (!empty($u['first_name']) || !empty($u['last_name']) ? ' (' . trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) . ')' : '')); ?> (ID: <?php echo htmlspecialchars($u['id']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="target_user_id">Цільовий користувач (залишиться):</label>
            <select id="target_user_id" name="target_user_id" required>
                 <option value="">-- Виберіть --</option>
                 <?php foreach ($allUsers as $u): ?>
                    <option value="<?php echo htmlspecialchars($u['id']); ?>" data-username="<?php echo htmlspecialchars($u['username']); ?>">
                         <?php echo htmlspecialchars($u['username'] . (!empty($u['first_name']) || !empty($u['last_name']) ? ' (' . trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) . ')' : '')); ?> (ID: <?php echo htmlspecialchars($u['id']); ?>)
                    </option>
                 <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Пріоритетний користувач (чиї дані перезапишуть конфлікти):</label>
            <div class="radio-group">
                <label>
                    <input type="radio" name="priority_user" value="target" checked>
                    <span id="priority_target_label">Цільовий (за замовчуванням)</span>
                </label>
                <label>
                    <input type="radio" name="priority_user" value="source">
                     <span id="priority_source_label">Джерело</span>
                </label>
            </div>
             <p class="form-note" id="priority-note" style="margin-top: 5px;">Дані цільового користувача (якщо є) будуть збережені при конфліктах (напр., якщо обидва пройшли самооцінку).</p>
        </div>

        <p class="form-note" style="color: red; font-weight: bold;">Увага! Пароль цільового користувача буде скинуто до <code>mindflow2025</code>.</p>

        <button type="submit" class="btn merge-btn">Об'єднати Користувачів</button>
    </form>
</div>

<script>
    // --- Скрипт для об'єднання користувачів (без змін) ---
    const sourceSelect = document.getElementById('source_user_id');
    const targetSelect = document.getElementById('target_user_id');
    const priorityTargetLabel = document.getElementById('priority_target_label');
    const prioritySourceLabel = document.getElementById('priority_source_label');
    const priorityNote = document.getElementById('priority-note');
    const priorityRadios = document.querySelectorAll('input[name="priority_user"]');

    function updatePriorityLabels() {
        const sourceOpt = sourceSelect.options[sourceSelect.selectedIndex];
        const targetOpt = targetSelect.options[targetSelect.selectedIndex];
        const sourceUsername = sourceOpt ? sourceOpt.getAttribute('data-username') : 'Джерело';
        const targetUsername = targetOpt ? targetOpt.getAttribute('data-username') : 'Цільовий';

        priorityTargetLabel.textContent = `Цільовий (${targetUsername || 'не вибрано'})`;
        prioritySourceLabel.textContent = `Джерело (${sourceUsername || 'не вибрано'})`;

        updatePriorityNote();
    }

    function updatePriorityNote() {
         const selectedPriority = document.querySelector('input[name="priority_user"]:checked').value;
         const sourceOpt = sourceSelect.options[sourceSelect.selectedIndex];
         const targetOpt = targetSelect.options[targetSelect.selectedIndex];
         const sourceUsername = sourceOpt ? sourceOpt.getAttribute('data-username') : 'Джерела';
         const targetUsername = targetOpt ? targetOpt.getAttribute('data-username') : 'Цільового';

         if (selectedPriority === 'target') {
             priorityNote.textContent = `Дані користувача '${targetUsername || 'Цільового'}' (якщо є) будуть збережені при конфліктах.`;
         } else {
             priorityNote.textContent = `Дані користувача '${sourceUsername || 'Джерела'}' (якщо є) будуть збережені при конфліктах.`;
         }
    }

    function validateSelection() {
        if (sourceSelect.value && targetSelect.value && sourceSelect.value === targetSelect.value) {
            alert('Користувач-Джерело та Цільовий користувач не можуть бути однаковими.');
            // Скидаємо вибір, який змінили останнім
            if (this === sourceSelect) targetSelect.value = '';
            else sourceSelect.value = '';
        }
        updatePriorityLabels(); // Оновлюємо мітки при зміні вибору
    }

    sourceSelect.addEventListener('change', validateSelection);
    targetSelect.addEventListener('change', validateSelection);
    priorityRadios.forEach(radio => radio.addEventListener('change', updatePriorityNote));
    updatePriorityLabels(); // Ініціалізація міток при завантаженні

    function confirmMerge(form) {
        const sourceOpt = form.source_user_id.options[form.source_user_id.selectedIndex];
        const targetOpt = form.target_user_id.options[form.target_user_id.selectedIndex];
        if (!sourceOpt || !targetOpt || sourceOpt.value === '' || targetOpt.value === '') {
            alert('Будь ласка, виберіть обох користувачів.');
            return false;
        }
        if (sourceOpt.value === targetOpt.value) {
             alert('Користувач-Джерело та Цільовий користувач не можуть бути однаковими.');
             return false;
        }
        const sourceUsername = sourceOpt.getAttribute('data-username');
        const targetUsername = targetOpt.getAttribute('data-username');
        const priorityValue = form.querySelector('input[name="priority_user"]:checked').value;
        const priorityUsername = (priorityValue === 'source') ? sourceUsername : targetUsername;

        return confirm(`Ви впевнені, що хочете об'єднати '${sourceUsername}' (буде видалено) з '${targetUsername}'?\n\n`
            + `Дані '${priorityUsername}' матимуть пріоритет при конфліктах.\n`
            + `Пароль для '${targetUsername}' буде скинуто до 'mindflow2025'.\n\n`
            + `Цю дію НЕ МОЖНА скасувати!`);
    }

    // --- Новий скрипт для кнопок дій у таблиці ---
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('action-execute-btn')) {
            const button = event.target;
            const select = button.closest('td').querySelector('.action-select'); // Знайти select у тій же комірці
            const selectedOption = select.options[select.selectedIndex];
            const action = selectedOption.value;
            const userId = select.dataset.userId;
            const username = select.dataset.username; // Оригінальний логін
            const escapedUsername = select.dataset.escapedUsername; // Екранований логін для confirm

            // Отримати поточні параметри пошуку та сторінки (важливо для збереження стану після дії)
            const currentSearchQuery = "<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>";
            const currentPage = "<?php echo $currentPage; ?>";

            if (!action) {
                alert('Будь ласка, оберіть дію.');
                return;
            }

            button.disabled = true; // Блокуємо кнопку на час виконання
            select.disabled = true; // Блокуємо селект

            switch (action) {
                case 'test':
                    const testUrl = selectedOption.dataset.url;
                    window.open(testUrl, '_blank');
                    // Розблокувати одразу, бо відкриття нового вікна не вимагає очікування
                    button.disabled = false;
                    select.disabled = false;
                    select.value = ''; // Скинути вибір
                    break;

                case 'edit':
                    const editUrl = selectedOption.dataset.url;
                    window.location.href = editUrl;
                    // Перехід на іншу сторінку, розблокування не потрібне тут
                    break;

                case 'recalculate':
                    const recalcUrl = selectedOption.dataset.url;
                    const originalButtonText = button.textContent;
                    button.textContent = 'Обробка...';
                    button.disabled = true; // Блокуємо кнопку під час запиту
                    select.disabled = true; // і селект

                    fetch(recalcUrl)
                        .then(response => response.json())
                        .then(data => {
                            alert(data.message || (data.success ? 'Дію успішно виконано!' : 'Сталася невідома помилка.'));

                            if (data.success) {
                                button.textContent = 'Готово ✅';
                            } else {
                                button.textContent = 'Помилка ❌';
                            }
                            // Повертаємо вигляд кнопки через деякий час
                            setTimeout(() => {
                                button.textContent = originalButtonText;
                                button.disabled = false;
                                select.disabled = false;
                                select.value = ''; // Скинути вибір
                            }, 2500);
                        })
                        .catch(error => {
                            console.error('Fetch Error:', error);
                            alert('Сталася помилка мережі або відповідь не є JSON.');
                            button.textContent = 'Помилка ❌';
                            setTimeout(() => {
                                button.textContent = originalButtonText;
                                button.disabled = false;
                                select.disabled = false;
                                select.value = ''; // Скинути вибір
                            }, 2500);
                        });
                    break;
                case 'analyze':
                    const analyzeUrl = selectedOption.dataset.url;
                    const originalButtonTextAnalyze = button.textContent;
                    button.textContent = 'Аналіз...';

                    fetch(analyzeUrl)
                        .then(response => response.json()) // Припускаємо, що get_analysis.php повертає JSON
                        .then(data => {
                            if (data.success) {
                                alert(`Аналіз успішно виконано: ${data.message || 'OK'}`);
                                button.textContent = 'Аналіз ✅';
                            } else {
                                alert(`Помилка аналізу: ${data.message || 'Невідома помилка.'}`);
                                button.textContent = 'Аналіз ❌';
                            }
                            setTimeout(() => {
                                button.textContent = originalButtonTextAnalyze;
                                button.disabled = false;
                                select.disabled = false;
                                select.value = '';
                            }, 2500);
                        })
                        .catch(error => {
                            console.error('Fetch Error:', error);
                            alert('Сталася помилка мережі або відповідь не є JSON під час аналізу.');
                            button.textContent = 'Аналіз ❌';
                            setTimeout(() => {
                                button.textContent = originalButtonTextAnalyze;
                                button.disabled = false;
                                select.disabled = false;
                                select.value = '';
                            }, 2500);
                        });
                    break;
                case 'results':
                     const resultsUrl = selectedOption.dataset.url;
                     if (resultsUrl) {
                         window.open(resultsUrl, '_blank'); // Відкрити результат в новій вкладці
                     } else {
                         alert('Не вдалося отримати URL для результатів.');
                     }
                     // Як і для "test", розблоковуємо одразу
                     button.disabled = false;
                     select.disabled = false;
                     select.value = ''; // Скинути вибір
                     break;
                case 'delete':
                    if (selectedOption.disabled) { // Перевірка, чи опція не заблокована (для себе)
                         button.disabled = false;
                         select.disabled = false;
                         select.value = '';
                         return;
                    }
                    if (confirm(`Ви впевнені, що хочете видалити користувача '${escapedUsername}'?`)) {
                        // Створюємо приховану форму для POST запиту
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'admin.php#users-section'; // Перенаправлення на секцію користувачів

                        const hiddenFields = {
                            action_user: 'delete_user',
                            user_id: userId,
                            search_query: currentSearchQuery, // Передаємо пошуковий запит
                            page: currentPage             // Передаємо поточну сторінку
                        };

                        for (const key in hiddenFields) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key;
                            input.value = hiddenFields[key];
                            form.appendChild(input);
                        }

                        document.body.appendChild(form);
                        form.submit();
                        // Перехід на іншу сторінку через submit, розблокування не потрібне тут
                    } else {
                        // Користувач скасував видалення
                        button.disabled = false;
                        select.disabled = false;
                        select.value = ''; // Скинути вибір
                    }
                    break;

                default:
                    // Невідома дія або не вибрано
                    button.disabled = false;
                    select.disabled = false;
                    break;
            }
        }
    });

    // function recalculateTraits(linkElement) { ... } // Можна видалити або закоментувати стару функцію

</script>
<?php endif; ?>

            <?php else: ?>
                <p><?php echo !empty($searchQuery) ? 'Користувачів за вашим запитом не знайдено.' : 'Немає зареєстрованих користувачів.'; ?></p>
            <?php endif; ?>

        <hr>
