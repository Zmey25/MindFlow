<?php
// admin.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/questionnaire_logic.php'; // Потрібно для getUserAnswersFilePath та ANSWERS_DIR_PATH у mergeUsers

// --- КОНФІГУРАЦІЯ ШЛЯХІВ ---
define('TRAITS_FILE_PATH', __DIR__ . '/data/traits.json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- АВТОРИЗАЦІЯ АДМІНІСТРАТОРА ---
if (!isUserLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUserId = $_SESSION['user_id'] ?? null;
$adminsFilePath = __DIR__ . '/data/admins.json';
$adminUserIds = [];

if ($currentUserId && file_exists($adminsFilePath)) {
    $adminData = readJsonFile($adminsFilePath);
    if (isset($adminData['admin_ids']) && is_array($adminData['admin_ids'])) {
        $adminUserIds = $adminData['admin_ids'];
    }
}

if (!$currentUserId || !in_array($currentUserId, $adminUserIds)) {
    header('Location: dashboard.php');
    exit; // Використовуємо exit замість return true
}
// --- КІНЕЦЬ АВТОРИЗАЦІЇ ---


// --- КОНФІГУРАЦІЯ ШЛЯХІВ ---
$questionsFilePath = __DIR__ . '/data/questions.json';
$usersFilePath = USERS_FILE_PATH; // Визначено в auth.php

// --- ІНІЦІАЛІЗАЦІЯ ---
$message = '';
$message_type = ''; // success, error, info
$active_section = $_GET['section'] ?? 'users'; // За замовчуванням - користувачі
$editTraitData = null; $editTraitIndex = null; 
$allTraits = []; 

// --- ЧИТАННЯ ДАНИХ (Читаємо завжди, бо POST може потребувати обидва набори) ---
$questionsData = readJsonFile($questionsFilePath);
$allUsers = readJsonFile($usersFilePath); // Завжди читаємо актуальний список

// --- ІНІЦІАЛІЗАЦІЯ (Питання) ---
$editCategoryData = null; $editQuestionData = null;
$editCategoryIndex = null; $editQuestionIndex = null;
// Нові змінні для стану UI питань
$selected_cat_index = isset($_GET['selected_cat_index']) ? (int)$_GET['selected_cat_index'] : null;
$selected_q_index = isset($_GET['selected_q_index']) ? (int)$_GET['selected_q_index'] : null;


// --- ІНІЦІАЛІЗАЦІЯ (Користувачі) ---
$editUserData = null; $userIdToEdit = null;
$searchQuery = trim($_GET['search_query'] ?? '');
$usersPerPage = 15;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Читаємо дані Трітів, вони можуть знадобитись і в GET, і в POST
$traitsFileData = readJsonFile(TRAITS_FILE_PATH);
$allTraits = $traitsFileData['traits'] ?? []; // Переконуємось, що працюємо з масивом 'traits'

// --- ОБРОБКА POST-ЗАПИТІВ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectSection = $active_section; // Запам'ятовуємо секцію для перенаправлення

    // --- Обробка дій з ПИТАННЯМИ ---
    if (isset($_POST['action'])) { // Питання/Категорії
        $action = $_POST['action'];
        $originalData = $questionsData;
        $redirectSection = 'questions'; // Дія точно стосується питань

        try {
            switch ($action) {
                case 'add_category':
                    if (!empty($_POST['categoryName']) && !empty($_POST['categoryId'])) {
                        $newCategory = ['categoryId' => trim($_POST['categoryId']), 'categoryName' => trim($_POST['categoryName']), 'questions' => []];
                        foreach ($questionsData as $category) { if ($category['categoryId'] === $newCategory['categoryId']) throw new Exception("Категорія з ID '{$newCategory['categoryId']}' вже існує."); }
                        $questionsData[] = $newCategory; $message = "Категорію '{$newCategory['categoryName']}' успішно додано.";
                    } else throw new Exception("Назва та ID категорії не можуть бути порожніми.");
                    break;
                case 'update_category':
                     if (isset($_POST['categoryIndex'], $questionsData[$_POST['categoryIndex']]) && !empty($_POST['categoryName']) && !empty($_POST['categoryId'])) {
                        $index = (int)$_POST['categoryIndex']; $newCategoryId = trim($_POST['categoryId']); $newCategoryName = trim($_POST['categoryName']); $oldCategoryId = $questionsData[$index]['categoryId'];
                        if ($newCategoryId !== $oldCategoryId) { foreach ($questionsData as $i => $category) { if ($i !== $index && $category['categoryId'] === $newCategoryId) throw new Exception("Категорія з ID '$newCategoryId' вже існує."); } }
                        $questionsData[$index]['categoryName'] = $newCategoryName; $questionsData[$index]['categoryId'] = $newCategoryId; $message = "Категорію '{$newCategoryName}' успішно оновлено.";
                    } else throw new Exception("Невірні дані для оновлення категорії.");
                    break;
                case 'delete_category':
                    if (isset($_POST['categoryIndex'], $questionsData[$_POST['categoryIndex']])) {
                        $index = (int)$_POST['categoryIndex']; $deletedCategoryName = $questionsData[$index]['categoryName']; array_splice($questionsData, $index, 1); $message = "Категорію '{$deletedCategoryName}' успішно видалено.";
                         if ($selected_cat_index === $index) { $selected_cat_index = null; $selected_q_index = null; }
                         elseif ($selected_cat_index > $index) { $selected_cat_index--; }
                    } else throw new Exception("Невірний індекс категорії для видалення.");
                    break;
                 case 'add_question':
                    $postCatIndex = isset($_POST['selected_cat_index']) ? (int)$_POST['selected_cat_index'] : null;
                    if ($postCatIndex !== null && isset($questionsData[$postCatIndex])
                        && !empty($_POST['questionId']) && !empty($_POST['q_self'])
                        && isset($_POST['q_short'], $_POST['q_other'])
                        && isset($_POST['scaleMin'], $_POST['scaleMax']) && $_POST['scaleMin'] !== '' && $_POST['scaleMax'] !== ''
                        && isset($_POST['scaleMinLabel'], $_POST['scaleMaxLabel']))
                    {
                        $catIndex = $postCatIndex;
                        $newQuestion = [
                            'questionId' => trim($_POST['questionId']),
                            'q_self'     => trim($_POST['q_self']),
                            'q_short'    => trim($_POST['q_short']),
                            'q_other'    => trim($_POST['q_other']),
                            'scale' => [
                                'min' => (int)$_POST['scaleMin'],
                                'max' => (int)$_POST['scaleMax'],
                                'minLabel' => trim($_POST['scaleMinLabel']),
                                'maxLabel' => trim($_POST['scaleMaxLabel'])
                            ]
                        ];
                        foreach ($questionsData[$catIndex]['questions'] as $question) {
                            if ($question['questionId'] === $newQuestion['questionId']) {
                                throw new Exception("Питання з ID '{$newQuestion['questionId']}' вже існує в цій категорії.");
                            }
                        }
                        if ($newQuestion['scale']['min'] >= $newQuestion['scale']['max']) {
                            throw new Exception("Мінімальне значення шкали має бути меншим за максимальне.");
                        }
                        $questionsData[$catIndex]['questions'][] = $newQuestion;
                        $message = "Питання '{$newQuestion['questionId']}' успішно додано до категорії '{$questionsData[$catIndex]['categoryName']}'.";
                        $selected_cat_index = $catIndex;
                    } else {
                        throw new Exception("Не всі поля для додавання питання заповнені коректно або категорію не вказано.");
                    }
                    break;
                case 'update_question':
                    if (isset($_POST['categoryIndex'], $_POST['questionIndex'], $questionsData[$_POST['categoryIndex']]['questions'][$_POST['questionIndex']])
                        && !empty($_POST['questionId']) && !empty($_POST['q_self'])
                        && isset($_POST['q_short'], $_POST['q_other'])
                        && isset($_POST['scaleMin'], $_POST['scaleMax']) && $_POST['scaleMin'] !== '' && $_POST['scaleMax'] !== ''
                        && isset($_POST['scaleMinLabel'], $_POST['scaleMaxLabel']))
                    {
                        $catIndex = (int)$_POST['categoryIndex']; $qIndex = (int)$_POST['questionIndex']; $newQuestionId = trim($_POST['questionId']); $oldQuestionId = $questionsData[$catIndex]['questions'][$qIndex]['questionId'];
                        if ($newQuestionId !== $oldQuestionId) {
                            foreach ($questionsData[$catIndex]['questions'] as $i => $question) {
                                if ($i !== $qIndex && $question['questionId'] === $newQuestionId) { throw new Exception("Питання з ID '$newQuestionId' вже існує в цій категорії."); }
                            }
                        }
                        if ((int)$_POST['scaleMin'] >= (int)$_POST['scaleMax']) { throw new Exception("Мінімальне значення шкали має бути меншим за максимальне."); }
                        $questionsData[$catIndex]['questions'][$qIndex]['questionId'] = $newQuestionId;
                        $questionsData[$catIndex]['questions'][$qIndex]['q_self']     = trim($_POST['q_self']);
                        $questionsData[$catIndex]['questions'][$qIndex]['q_short']    = trim($_POST['q_short']);
                        $questionsData[$catIndex]['questions'][$qIndex]['q_other']    = trim($_POST['q_other']);
                        $questionsData[$catIndex]['questions'][$qIndex]['scale'] = [
                            'min'      => (int)$_POST['scaleMin'],
                            'max'      => (int)$_POST['scaleMax'],
                            'minLabel' => trim($_POST['scaleMinLabel']),
                            'maxLabel' => trim($_POST['scaleMaxLabel'])
                        ];
                        $message = "Питання '{$newQuestionId}' успішно оновлено.";
                        $selected_cat_index = $catIndex;
                    } else { throw new Exception("Невірні дані для оновлення питання."); }
                    break;
                case 'delete_question':
                    if (isset($_POST['categoryIndex'], $_POST['questionIndex'], $questionsData[$_POST['categoryIndex']]['questions'][$_POST['questionIndex']])) {
                        $catIndex = (int)$_POST['categoryIndex']; $qIndex = (int)$_POST['questionIndex']; $deletedQuestionId = $questionsData[$catIndex]['questions'][$qIndex]['questionId'];
                         array_splice($questionsData[$catIndex]['questions'], $qIndex, 1);
                         $message = "Питання '{$deletedQuestionId}' успішно видалено.";
                         $selected_cat_index = $catIndex;
                         if ($selected_q_index === $qIndex) { $selected_q_index = null; }
                         elseif ($selected_q_index > $qIndex) { $selected_q_index--; }
                    } else throw new Exception("Невірні індекси для видалення питання.");
                    break;
                 default: throw new Exception("Невідома дія з питаннями.");
            }

            // --- Запис та редірект (питання) ---
            if ($originalData !== $questionsData) {
                if (!writeJsonFile($questionsFilePath, $questionsData)) {
                    $questionsData = $originalData; // Відновлення у разі помилки запису
                    throw new Exception("Помилка запису даних питань у файл '{$questionsFilePath}'.");
                }
                // Формуємо параметри для редіректу
                $redirectParams = [
                    'section' => $redirectSection,
                    'message' => urlencode($message),
                    'msg_type' => 'success'
                ];
                if ($selected_cat_index !== null) $redirectParams['selected_cat_index'] = $selected_cat_index;
                $anchor = ($selected_cat_index !== null) ? '#category-' . $selected_cat_index : '#questions-section';
                header("Location: admin.php?" . http_build_query($redirectParams) . $anchor);
                exit;
            } else {
                if (empty($message)) $message = "Дані питань не змінилися.";
                $message_type = 'info';
            }

        } catch (Exception $e) {
            $message = "Помилка (Питання): " . $e->getMessage();
            $message_type = 'error';
            $questionsData = $originalData; // Відновлюємо дані у разі помилки
        }

    }
    // --- Обробка дій з КОРИСТУВАЧАМИ ---
    elseif (isset($_POST['action_user'])) {
        $action_user = $_POST['action_user'];
        $originalUsers = $allUsers; // Зберігаємо стан *до* початку операції
        $redirectSection = 'users'; // Дія точно стосується користувачів
        $postSearchQuery = trim($_POST['search_query'] ?? '');
        $postPage = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
        $mergeResult = null; // Для перевірки, чи була помилка саме з mergeUsers

        try {
            switch ($action_user) {
                case 'add_user':
                    $username = trim($_POST['username'] ?? ''); $firstName = trim($_POST['first_name'] ?? ''); $lastName = trim($_POST['last_name'] ?? ''); $defaultPassword = 'mindflow2025';
                    if (empty($username) || mb_strlen($username) < USERNAME_MIN_LENGTH || mb_strlen($username) > USERNAME_MAX_LENGTH || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) throw new Exception('Некоректне ім\'я користувача (логін)...');
                    foreach ($allUsers as $user) { if (isset($user['username']) && strtolower($user['username']) === strtolower($username)) throw new Exception("Користувач з логіном '{$username}' вже існує."); }
                    $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT); if (!$passwordHash) throw new Exception("Помилка хешування пароля.");
                    $userId = generateUniqueId('user_');
                    $newUser = ['id' => $userId, 'username' => $username, 'password_hash' => $passwordHash, 'first_name' => $firstName, 'last_name' => $lastName];
                    $allUsers[] = $newUser; $message = "Користувача '{$username}' створено. Пароль: <code>{$defaultPassword}</code>. Посилання на тест: <a href='questionnaire_other.php?target_user_id={$userId}' target='_blank'>Пройти тест</a>";
                    break;
                case 'update_user':
                    $userIdToUpdate = $_POST['user_id'] ?? null; $newUsername = trim($_POST['username'] ?? ''); $newFirstName = trim($_POST['first_name'] ?? ''); $newLastName = trim($_POST['last_name'] ?? ''); $userIndex = -1;
                    if (empty($userIdToUpdate)) throw new Exception("Не вказано ID користувача для оновлення.");
                    if (empty($newUsername) || mb_strlen($newUsername) < USERNAME_MIN_LENGTH || mb_strlen($newUsername) > USERNAME_MAX_LENGTH || !preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) throw new Exception('Некоректне ім\'я користувача (логін).');
                    foreach ($allUsers as $index => $user) { if (isset($user['id']) && $user['id'] === $userIdToUpdate) { $userIndex = $index; if (strtolower($user['username']) !== strtolower($newUsername)) { foreach ($allUsers as $otherIndex => $otherUser) { if ($index !== $otherIndex && isset($otherUser['username']) && strtolower($otherUser['username']) === strtolower($newUsername)) throw new Exception("Користувач з логіном '{$newUsername}' вже існує."); } } break; } }
                    if ($userIndex === -1) throw new Exception("Користувача з ID '{$userIdToUpdate}' не знайдено.");
                    $allUsers[$userIndex]['username'] = $newUsername; $allUsers[$userIndex]['first_name'] = $newFirstName; $allUsers[$userIndex]['last_name'] = $newLastName; $message = "Дані користувача '{$newUsername}' оновлено.";
                    break;
                case 'delete_user':
                    $userIdToDelete = $_POST['user_id'] ?? null; $userIndexToDelete = -1;
                    if (empty($userIdToDelete)) throw new Exception("Не вказано ID користувача для видалення."); if ($userIdToDelete === $currentUserId) throw new Exception("Ви не можете видалити власний обліковий запис адміністратора.");
                    foreach ($allUsers as $index => $user) { if (isset($user['id']) && $user['id'] === $userIdToDelete) { $userIndexToDelete = $index; break; } }
                    if ($userIndexToDelete === -1) throw new Exception("Користувача з ID '{$userIdToDelete}' не знайдено.");
                    $deletedUsername = $allUsers[$userIndexToDelete]['username']; array_splice($allUsers, $userIndexToDelete, 1); $message = "Користувача '{$deletedUsername}' видалено.";
                    break;
                case 'merge_users':
                    $sourceUserId = $_POST['source_user_id'] ?? null;
                    $targetUserId = $_POST['target_user_id'] ?? null;
                    $priorityOption = $_POST['priority_user'] ?? 'target'; // 'target' або 'source'

                    if (empty($sourceUserId) || empty($targetUserId)) { throw new Exception("Необхідно вибрати обох користувачів для об'єднання."); }
                    if ($sourceUserId === $targetUserId) { throw new Exception("Користувач-Джерело та Цільовий користувач не можуть бути однаковими."); }
                    if ($sourceUserId === $currentUserId) { throw new Exception("Ви не можете вибрати себе як користувача-джерело для об'єднання (це видалить ваш поточний обліковий запис)."); }

                    $priorityUserId = ($priorityOption === 'source') ? $sourceUserId : $targetUserId;
                    $defaultPasswordMerge = 'mindflow2025'; // Можна винести в константу

                    // Викликаємо функцію для об'єднання
                    $mergeResult = mergeUsers($sourceUserId, $targetUserId, $priorityUserId, $defaultPasswordMerge);

                    if ($mergeResult['success']) {
                        $message = $mergeResult['message'];
                        // Перечитуємо користувачів ПІСЛЯ об'єднання для коректного відображення у списку
                        $allUsers = readJsonFile($usersFilePath);
                    } else {
                        throw new Exception($mergeResult['message']); // Кидаємо виняток з повідомленням від mergeUsers
                    }
                    break; // Кінець case 'merge_users'

                default:
                    throw new Exception("Невідома дія з користувачами.");
            } // кінець switch ($action_user)

            // --- Запис та редірект (користувачі) ---
            // Перевіряємо, чи були зміни АБО чи це була успішна операція merge
            $dataChanged = ($originalUsers !== $allUsers); // Базова перевірка змін

            // Повідомлення встановлено і НЕ було винятку? Тоді редірект.
            if (!empty($message) && !isset($e)) {
                // Записуємо файл, ТІЛЬКИ якщо дія НЕ merge (бо merge зберігає сама) І якщо дані змінились
                 if ($action_user !== 'merge_users' && $dataChanged) {
                     if (!writeJsonFile($usersFilePath, $allUsers)) {
                         $allUsers = $originalUsers; // Відновлюємо лише якщо ЗАПИС не вдався
                         throw new Exception("Помилка запису даних користувачів у файл '{$usersFilePath}'.");
                     }
                 } elseif ($action_user !== 'merge_users' && !$dataChanged) {
                     // Якщо дані не змінилися (і це не merge), не робимо редірект, просто покажемо інфо
                     if (empty($message)) $message = "Дані користувачів не змінилися.";
                     $message_type = 'info';
                     // Зберігаємо поточні параметри для відображення
                     $searchQuery = $postSearchQuery;
                     $currentPage = $postPage;
                     // Виходимо з блоку if (!empty($message) && !isset($e))
                 }

                // Якщо ми дійшли сюди (був успіх add/update/delete/merge АБО $dataChanged і успішний запис)
                if ($message_type !== 'info') { // Не редірект, якщо просто info "не змінилося"
                    // Формуємо параметри для редіректу
                    $redirectParams = [
                        'section' => $redirectSection,
                        'message' => urlencode($message),
                        'msg_type' => 'success',
                        'search_query' => $postSearchQuery,
                        'page' => ($action_user === 'delete_user' || $action_user === 'merge_users') ? 1 : $postPage // Скидаємо на 1 сторінку після видалення/об'єднання
                    ];
                    $anchor = ($action_user === 'merge_users') ? '#merge-users-section' : '#users-section'; // Якір для merge інший
                    header("Location: admin.php?" . http_build_query($redirectParams) . $anchor);
                    exit;
                }

            } else if (empty($message) && !isset($e)) {
                 // Якщо не було змін і не було повідомлення (малоймовірно, але про всяк випадок)
                 $message = "Дані користувачів не змінилися.";
                 $message_type = 'info';
                 $searchQuery = $postSearchQuery;
                 $currentPage = $postPage;
            }

        } catch (Exception $e) {
            $message = "Помилка (Користувачі): " . $e->getMessage();
            $message_type = 'error';
            // Відновлюємо стан $allUsers, ТІЛЬКИ якщо помилка НЕ була з mergeUsers
            // бо mergeUsers вже намагається відновити стан сама
            if ($mergeResult === null) { // Якщо $mergeResult не встановлено, значить помилка не з mergeUsers
                 $allUsers = $originalUsers;
            } else {
                 // Якщо помилка була з mergeUsers, краще перечитати файл, щоб бачити актуальний стан після (можливо невдалого) відкату
                 $allUsers = readJsonFile($usersFilePath);
            }
            // Зберігаємо поточні параметри пошуку/пагінації для відображення
            $searchQuery = $postSearchQuery;
            $currentPage = $postPage;
        }
    } // кінець elseif (isset($_POST['action_user']))

// --- Обробка дій з ТРІТАМИ ---
elseif (isset($_POST['action_trait'])) {
    $action_trait = $_POST['action_trait'];
    $originalTraits = $allTraits; // Зберігаємо стан до операції
    $redirectSection = 'traits'; // Секція для перенаправлення

    try {
        $traitIndex = isset($_POST['traitIndex']) ? (int)$_POST['traitIndex'] : null;

        switch ($action_trait) {
            case 'add_trait':
            case 'update_trait':
                $traitId = trim($_POST['traitId'] ?? '');
                $traitName = trim($_POST['traitName'] ?? '');
                $traitIcon = trim($_POST['traitIcon'] ?? '');
                $traitDescription = trim($_POST['traitDescription'] ?? '');
                $traitConditionsJson = trim($_POST['traitConditions'] ?? '[]'); // Отримуємо JSON рядок

                if (empty($traitId) || empty($traitName) || empty($traitConditionsJson)) {
                    throw new Exception("ID, Назва та Умови тріта не можуть бути порожніми.");
                }
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $traitId)) {
                     throw new Exception("ID тріта може містити лише латинські літери, цифри та знак підкреслення (_).");
                }

// --- Обробка та валідація умов з форми ---
$conditions = [];
$submittedConditions = $_POST['conditions'] ?? []; // Отримуємо масив умов

if (!is_array($submittedConditions)) {
     throw new Exception("Отримано некоректний формат умов.");
}
if (empty($submittedConditions)) {
    throw new Exception("Для тріта необхідно вказати хоча б одну умову.");
}

foreach ($submittedConditions as $index => $condData) {
     // Базова перевірка наявності ключів
     if (!isset($condData['type'], $condData['questionId'], $condData['operator'], $condData['value']) || $condData['value'] === '') {
          throw new Exception("Умова #".($index+1).": Відсутні обов'язкові поля (тип, питання, оператор, значення).");
     }
     // Валідація типів даних (проста)
     if (!in_array($condData['type'], ['self', 'others'])) {
         throw new Exception("Умова #".($index+1).": Некоректний тип умови '{$condData['type']}'.");
     }
     if (empty($condData['questionId'])) {
         throw new Exception("Умова #".($index+1).": Не вибрано питання.");
     }
     // TODO: Додатково перевірити, чи існує такий questionId у списку питань
     if (!in_array($condData['operator'], ['>=', '<=', '==', '>', '<'])) {
         throw new Exception("Умова #".($index+1).": Некоректний оператор '{$condData['operator']}'.");
     }
     if (!is_numeric($condData['value'])) {
          throw new Exception("Умова #".($index+1).": Значення повинно бути числом.");
     }

     $validatedCondition = [
         'type' => $condData['type'],
         'questionId' => $condData['questionId'],
         'operator' => $condData['operator'],
         'value' => (float)$condData['value'] // Зберігаємо як число
     ];

     // Додаємо агрегацію тільки для типу 'others'
     if ($condData['type'] === 'others') {
          if (!isset($condData['aggregation']) || !in_array($condData['aggregation'], ['average', 'any', 'all'])) {
             throw new Exception("Умова #".($index+1).": Для типу 'others' необхідно вказати коректну агрегацію (average, any, all).");
          }
          $validatedCondition['aggregation'] = $condData['aggregation'];
     }

     $conditions[] = $validatedCondition; // Додаємо валідовану умову до списку
}
// --- Кінець обробки умов ---

$newTraitData = [
    'id' => $traitId,
    'name' => $traitName,
    'icon' => $traitIcon,
    'description' => $traitDescription,
    'conditions' => $conditions // Використовуємо згенерований масив
];


                if ($action_trait === 'add_trait') {
                    // Перевірка унікальності ID при додаванні
                    foreach ($allTraits as $trait) {
                        if (isset($trait['id']) && $trait['id'] === $traitId) {
                            throw new Exception("Тріт з ID '{$traitId}' вже існує.");
                        }
                    }
                    $allTraits[] = $newTraitData;
                    $message = "Тріт '{$traitName}' успішно додано.";
                } else { // update_trait
                    if ($traitIndex === null || !isset($allTraits[$traitIndex])) {
                        throw new Exception("Невірний індекс тріта для оновлення.");
                    }
                     if ($allTraits[$traitIndex]['id'] !== $traitId) {
                         // Це не повинно статись, бо ID readonly при редагуванні, але перевіряємо
                         throw new Exception("Спроба змінити ID тріта під час оновлення не дозволена.");
                     }
                    $allTraits[$traitIndex] = $newTraitData;
                    $message = "Тріт '{$traitName}' успішно оновлено.";
                }
                break;

            case 'delete_trait':
                if ($traitIndex === null || !isset($allTraits[$traitIndex])) {
                    throw new Exception("Невірний індекс тріта для видалення.");
                }
                $deletedTraitName = $allTraits[$traitIndex]['name'] ?? 'N/A';
                array_splice($allTraits, $traitIndex, 1);
                $message = "Тріт '{$deletedTraitName}' успішно видалено.";
                break;

            default:
                throw new Exception("Невідома дія з трітами.");
        }

        // --- Запис та редірект (тріти) ---
        if ($originalTraits !== $allTraits) {
            // Зберігаємо весь об'єкт, включаючи ключ 'traits'
            $traitsFileDataToSave = ['traits' => $allTraits];
            if (!writeJsonFile(TRAITS_FILE_PATH, $traitsFileDataToSave)) {
                $allTraits = $originalTraits; // Відновлення у разі помилки запису
                throw new Exception("Помилка запису даних трітів у файл '" . TRAITS_FILE_PATH . "'.");
            }
             // Редірект для очищення POST і показу повідомлення
             $redirectParams = [
                'section' => $redirectSection,
                'message' => urlencode($message),
                'msg_type' => 'success'
             ];
             $anchor = ($action_trait === 'update_trait' && $traitIndex !== null) ? '#trait-' . $traitIndex : '#traits-section';
             header("Location: admin.php?" . http_build_query($redirectParams) . $anchor);
             exit;
        } else {
             $message = "Дані трітів не змінилися.";
             $message_type = 'info';
        }

    } catch (Exception $e) {
        $message = "Помилка (Тріти): " . $e->getMessage();
        $message_type = 'error';
        $allTraits = $originalTraits; // Відновлюємо дані у разі помилки
    }

} // кінець elseif (isset($_POST['action_trait']))

} // кінець if ($_SERVER['REQUEST_METHOD'] === 'POST')

// --- ОБРОБКА GET-ЗАПИТІВ ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['message'])) {
        $message = urldecode($_GET['message']);
        $message_type = ($_GET['msg_type'] ?? 'info');
    }

     // --- Підготовка до редагування (Питання/Категорії) ---
     $action_edit = $_GET['action'] ?? null;
     $is_editing_question_item = false;

    if ($action_edit === 'edit_category' && isset($_GET['categoryIndex'])) {
        $editCategoryIndex = (int)$_GET['categoryIndex'];
        if (isset($questionsData[$editCategoryIndex])) {
            $editCategoryData = $questionsData[$editCategoryIndex];
            $selected_cat_index = $editCategoryIndex;
            $selected_q_index = null;
            $is_editing_question_item = true;
        } else {
            $message = "Помилка: Категорію з індексом '{$editCategoryIndex}' для редагування не знайдено."; $message_type = 'error'; $editCategoryIndex = null;
        }
    } elseif ($action_edit === 'edit_question' && isset($_GET['selected_cat_index'], $_GET['questionIndex'])) {
        $editCategoryIndex = (int)$_GET['selected_cat_index'];
        $editQuestionIndex = (int)$_GET['questionIndex'];
        if (isset($questionsData[$editCategoryIndex]['questions'][$editQuestionIndex])) {
            $editQuestionData = $questionsData[$editCategoryIndex]['questions'][$editQuestionIndex];
            $selected_cat_index = $editCategoryIndex;
            $selected_q_index = $editQuestionIndex;
            $is_editing_question_item = true;
        } else {
            $message = "Помилка: Питання з індексами '{$editCategoryIndex}/{$editQuestionIndex}' для редагування не знайдено.";
            $message_type = 'error';
            // Не скидаємо $selected_cat_index
            $editCategoryIndex = null; $editQuestionIndex = null; $editQuestionData = null;
        }
    }

    // --- Підготовка до редагування (Користувач) ---
     $action_user_edit = $_GET['action_user'] ?? null;
    if ($action_user_edit === 'edit_user' && isset($_GET['user_id'])) {
        $userIdToEdit = $_GET['user_id'];
        $foundUser = false; // Прапорець для перевірки
        foreach ($allUsers as $user) { // Перебираємо актуальний $allUsers
            if (isset($user['id']) && $user['id'] === $userIdToEdit) {
                $editUserData = $user;
                $foundUser = true;
                break;
            }
        }
        if (!$foundUser) { // Якщо користувач не знайдений
            $message = "Помилка: Користувача з ID '{$userIdToEdit}' для редагування не знайдено.";
            $message_type = 'error';
            $userIdToEdit = null; // Скидаємо ID
            $editUserData = null; // Очищуємо дані для редагування
        } else {
             // Якщо редагуємо користувача, скидаємо стан редагування питань
             $editCategoryData = null; $editQuestionData = null;
             $editCategoryIndex = null; $editQuestionIndex = null;
             $is_editing_question_item = false;
             $selected_cat_index = null; $selected_q_index = null;
        }
    }

     // --- Перевірка валідності GET параметрів вибору питань ---
     if (!$is_editing_question_item) {
         if ($selected_cat_index !== null && !isset($questionsData[$selected_cat_index])) {
             $message = "Помилка: Вибрану категорію (індекс {$selected_cat_index}) не знайдено."; $message_type = 'error';
             $selected_cat_index = null; $selected_q_index = null;
         }
          if ($selected_q_index !== null && (!isset($questionsData[$selected_cat_index]['questions'][$selected_q_index]))) {
             // Це може бути нормальним, якщо просто вибрана категорія
             $selected_q_index = null; // Просто скидаємо вибране питання
          }
     }

// --- Підготовка до редагування (Тріти) ---
if ($action_edit === 'edit_trait' && isset($_GET['traitIndex'])) {
    $editTraitIndex = (int)$_GET['traitIndex'];
    if (isset($allTraits[$editTraitIndex])) {
        $editTraitData = $allTraits[$editTraitIndex];
        // Скидаємо стан редагування інших секцій
        $editCategoryData = null; $editQuestionData = null; $editCategoryIndex = null; $editQuestionIndex = null;
        $editUserData = null; $userIdToEdit = null;
    } else {
        $message = "Помилка: Тріт з індексом '{$editTraitIndex}' для редагування не знайдено.";
        $message_type = 'error';
        $editTraitIndex = null;
    }
}

} // кінець if ($_SERVER['REQUEST_METHOD'] === 'GET')

// --- ФІЛЬТРАЦІЯ ТА ПАГІНАЦІЯ КОРИСТУВАЧІВ ---
// Завжди використовуємо актуальний стан $allUsers
$filteredUsers = $allUsers;
if (!empty($searchQuery)) {
    $filteredUsers = array_filter($allUsers, function($user) use ($searchQuery) {
        $searchLower = mb_strtolower($searchQuery);
        $username = mb_strtolower($user['username'] ?? '');
        $firstName = mb_strtolower($user['first_name'] ?? '');
        $lastName = mb_strtolower($user['last_name'] ?? '');
        return strpos($username, $searchLower) !== false || strpos($firstName, $searchLower) !== false || strpos($lastName, $searchLower) !== false;
    });
}
$totalUsers = count($filteredUsers);
$totalPages = ($usersPerPage > 0 && $totalUsers > 0) ? ceil($totalUsers / $usersPerPage) : 1; // Обережність з діленням на 0 і порожнім масивом
$currentPage = max(1, min($currentPage, $totalPages)); // Переконуємось, що сторінка в межах
$offset = ($currentPage - 1) * $usersPerPage;
// Використовуємо array_values для переіндексації перед array_slice, щоб уникнути проблем з нечисловими ключами після filter
$paginatedUsers = ($usersPerPage > 0) ? array_slice(array_values($filteredUsers), $offset, $usersPerPage) : array_values($filteredUsers);

// --- Визначення типу повідомлення ---
if (empty($message_type) && !empty($message)) {
     $message_type = (strpos($message, 'Помилка:') === 0 || strpos($message, 'Exception:') !== false) ? 'error' : 'success'; // За замовчуванням - success, якщо не помилка
} elseif (empty($message)) {
    $message_type = '';
}

include __DIR__ . '/includes/header.php'; // Підключаємо шапку
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Адмін-панель</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="container">
        <h1>Адмін-панель</h1>

        <!-- Навігація між секціями -->
        <nav class="admin-nav">
            <a href="admin.php?section=users" class="<?php echo ($active_section === 'users') ? 'active' : ''; ?>">Управління Користувачами</a>
            <a href="admin.php?section=questions" class="<?php echo ($active_section === 'questions') ? 'active' : ''; ?>">Управління Питаннями</a>
            <a href="admin.php?section=traits" class="<?php echo ($active_section === 'traits') ? 'active' : ''; ?>">Управління Трітами</a>
             <!-- Додайте інші секції тут за потреби -->
        </nav>

        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <?php echo $message; // HTML дозволено для посилань/коду ?>
            </div>
        <?php endif; ?>

        <!-- Динамічне підключення секції -->
        <?php
        // Переконуємось, що підключаємо правильний файл секції
        $section_file = __DIR__ . '/admin_' . $active_section . '.php';

        if (file_exists($section_file)) {
            // Передаємо необхідні змінні в область видимості файлу секції
            // Змінні для users: $allUsers, $filteredUsers, $paginatedUsers, $totalUsers, $currentPage, $totalPages, $usersPerPage, $searchQuery, $editUserData, $userIdToEdit, $currentUserId
            // Змінні для questions: $questionsData, $editCategoryData, $editQuestionData, $editCategoryIndex, $editQuestionIndex, $selected_cat_index, $selected_q_index, $searchQuery, $currentPage (для збереження стану користувачів)
            include $section_file;
        } elseif ($active_section === 'users') { // Запасний варіант для користувачів, якщо файл не знайдено
             include __DIR__ . '/admin_users.php';
        } elseif ($active_section === 'questions') { // Запасний варіант для питань
             include __DIR__ . '/admin_questions.php';
        } elseif ($active_section === 'traits') {
             include __DIR__ . '/admin_traits.php';
        } else {
            echo "<p>Помилка: Файл секції '{$active_section}' не знайдено.</p>";
        }
        ?>

    </div> <!-- /.container -->

    <script>
        // Ваш JavaScript для прокрутки та очистки GET параметрів
        document.addEventListener('DOMContentLoaded', function() {
             let hash = window.location.hash;
             // Очистка GET-параметрів message/msg_type після показу
             if (window.history.replaceState && window.location.search.includes('message=')) {
                 const url = new URL(window.location);
                 url.searchParams.delete('message');
                 url.searchParams.delete('msg_type');
                 // Зберігаємо інші параметри (section, search_query, page etc.)
                 window.history.replaceState({ path: url.href }, '', url.toString()); // Використовуємо toString()
             }

             // Прокрутка і підсвітка
             if (hash) {
                 try {
                    const elementId = hash.substring(1);
                    const targetElement = document.getElementById(elementId);

                    if (targetElement) {
                        let elementToHighlight = targetElement;
                        if (targetElement.classList.contains('edit-form-section') || targetElement.classList.contains('section-form') || targetElement.closest('.edit-form-section') || targetElement.closest('.section-form')) {
                             elementToHighlight = targetElement.closest('.edit-form-section, .section-form') || targetElement;
                        } else if (targetElement.tagName === 'TR' && targetElement.closest('table')) {
                            elementToHighlight = targetElement; // Підсвічувати рядок таблиці
                        } else if (targetElement.classList.contains('category-item') || targetElement.classList.contains('question-item')) {
                             elementToHighlight = targetElement;
                        } else if (elementId === 'merge-users-section') {
                            elementToHighlight = document.getElementById('merge-users-section');
                        }

                        if(elementToHighlight) {
                            const originalBg = elementToHighlight.style.backgroundColor;
                            const originalBorder = elementToHighlight.style.borderColor;
                            elementToHighlight.style.transition = 'background-color 0.7s ease-in-out, border-color 0.7s ease-in-out';
                            elementToHighlight.style.backgroundColor = '#fff3cd'; // Світло-жовтий
                            elementToHighlight.style.borderColor = '#ffeeba';

                            setTimeout(() => {
                                elementToHighlight.style.backgroundColor = originalBg || '';
                                elementToHighlight.style.borderColor = originalBorder || '';
                                setTimeout(() => { elementToHighlight.style.transition = ''; }, 700);
                            }, 1500); // Тривалість підсвітки
                        }

                         // Плавна прокрутка
                         setTimeout(() => {
                              targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                         }, 100); // Невелика затримка

                    }
                 } catch (e) {
                     console.error("Error scrolling/highlighting:", e);
                 }
             } else if (document.querySelector('.message:not(:empty)')) {
                 // Прокрутка до повідомлення, якщо немає якоря
                 const messageElement = document.querySelector('.message');
                 // Прокручуємо, тільки якщо повідомлення не видно повністю
                 const rect = messageElement.getBoundingClientRect();
                 if (rect.top < 0 || rect.bottom > (window.innerHeight || document.documentElement.clientHeight)) {
                     messageElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                 }
             }
        });
    </script>

</body>
</html>	