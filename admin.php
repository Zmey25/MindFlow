<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/questionnaire_logic.php';

define('TRAITS_FILE_PATH', __DIR__ . '/data/traits.json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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
    exit;
}

$questionsFilePath = __DIR__ . '/data/questions.json';
$usersFilePath = USERS_FILE_PATH;

$message = '';
$message_type = '';
$active_section = $_GET['section'] ?? 'users';
$editTraitData = null; $editTraitIndex = null;
$allTraits = [];

$questionsData = readJsonFile($questionsFilePath);
$allUsers = readJsonFile($usersFilePath);

$editCategoryData = null; $editQuestionData = null;
$editCategoryIndex = null; $editQuestionIndex = null;
$selected_cat_index = isset($_GET['selected_cat_index']) ? (int)$_GET['selected_cat_index'] : null;
$selected_q_index = isset($_GET['selected_q_index']) ? (int)$_GET['selected_q_index'] : null;

$editUserData = null; $userIdToEdit = null;
$searchQuery = trim($_GET['search_query'] ?? '');
$usersPerPage = 15;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$traitsFileData = readJsonFile(TRAITS_FILE_PATH);
$allTraits = $traitsFileData['traits'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectSection = $active_section;

    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $originalData = $questionsData;
        $redirectSection = 'questions';

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
                    if ($postCatIndex !== null && isset($questionsData[$postCatIndex]) && !empty($_POST['questionId']) && !empty($_POST['q_self']) && isset($_POST['q_short'], $_POST['q_other']) && isset($_POST['scaleMin'], $_POST['scaleMax']) && $_POST['scaleMin'] !== '' && $_POST['scaleMax'] !== '' && isset($_POST['scaleMinLabel'], $_POST['scaleMaxLabel'])) {
                        $catIndex = $postCatIndex;
                        $newQuestion = ['questionId' => trim($_POST['questionId']), 'q_self' => trim($_POST['q_self']), 'q_short' => trim($_POST['q_short']), 'q_other' => trim($_POST['q_other']), 'scale' => ['min' => (int)$_POST['scaleMin'], 'max' => (int)$_POST['scaleMax'], 'minLabel' => trim($_POST['scaleMinLabel']), 'maxLabel' => trim($_POST['scaleMaxLabel'])]];
                        foreach ($questionsData[$catIndex]['questions'] as $question) { if ($question['questionId'] === $newQuestion['questionId']) { throw new Exception("Питання з ID '{$newQuestion['questionId']}' вже існує в цій категорії."); } }
                        if ($newQuestion['scale']['min'] >= $newQuestion['scale']['max']) { throw new Exception("Мінімальне значення шкали має бути меншим за максимальне."); }
                        $questionsData[$catIndex]['questions'][] = $newQuestion; $message = "Питання '{$newQuestion['questionId']}' успішно додано до категорії '{$questionsData[$catIndex]['categoryName']}'."; $selected_cat_index = $catIndex;
                    } else { throw new Exception("Не всі поля для додавання питання заповнені коректно або категорію не вказано."); }
                    break;
                case 'update_question':
                    if (isset($_POST['categoryIndex'], $_POST['questionIndex'], $questionsData[$_POST['categoryIndex']]['questions'][$_POST['questionIndex']]) && !empty($_POST['questionId']) && !empty($_POST['q_self']) && isset($_POST['q_short'], $_POST['q_other']) && isset($_POST['scaleMin'], $_POST['scaleMax']) && $_POST['scaleMin'] !== '' && $_POST['scaleMax'] !== '' && isset($_POST['scaleMinLabel'], $_POST['scaleMaxLabel'])) {
                        $catIndex = (int)$_POST['categoryIndex']; $qIndex = (int)$_POST['questionIndex']; $newQuestionId = trim($_POST['questionId']); $oldQuestionId = $questionsData[$catIndex]['questions'][$qIndex]['questionId'];
                        if ($newQuestionId !== $oldQuestionId) { foreach ($questionsData[$catIndex]['questions'] as $i => $question) { if ($i !== $qIndex && $question['questionId'] === $newQuestionId) { throw new Exception("Питання з ID '$newQuestionId' вже існує в цій категорії."); } } }
                        if ((int)$_POST['scaleMin'] >= (int)$_POST['scaleMax']) { throw new Exception("Мінімальне значення шкали має бути меншим за максимальне."); }
                        $questionsData[$catIndex]['questions'][$qIndex] = ['questionId' => $newQuestionId, 'q_self' => trim($_POST['q_self']), 'q_short' => trim($_POST['q_short']), 'q_other' => trim($_POST['q_other']), 'scale' => ['min' => (int)$_POST['scaleMin'], 'max' => (int)$_POST['scaleMax'], 'minLabel' => trim($_POST['scaleMinLabel']), 'maxLabel' => trim($_POST['scaleMaxLabel'])]];
                        $message = "Питання '{$newQuestionId}' успішно оновлено."; $selected_cat_index = $catIndex;
                    } else { throw new Exception("Невірні дані для оновлення питання."); }
                    break;
                case 'delete_question':
                    if (isset($_POST['categoryIndex'], $_POST['questionIndex'], $questionsData[$_POST['categoryIndex']]['questions'][$_POST['questionIndex']])) {
                        $catIndex = (int)$_POST['categoryIndex']; $qIndex = (int)$_POST['questionIndex']; $deletedQuestionId = $questionsData[$catIndex]['questions'][$qIndex]['questionId'];
                         array_splice($questionsData[$catIndex]['questions'], $qIndex, 1);
                         $message = "Питання '{$deletedQuestionId}' успішно видалено."; $selected_cat_index = $catIndex;
                         if ($selected_q_index === $qIndex) { $selected_q_index = null; }
                         elseif ($selected_q_index > $qIndex) { $selected_q_index--; }
                    } else throw new Exception("Невірні індекси для видалення питання.");
                    break;
                 default: throw new Exception("Невідома дія з питаннями.");
            }
            if ($originalData !== $questionsData) {
                if (!writeJsonFile($questionsFilePath, $questionsData)) { $questionsData = $originalData; throw new Exception("Помилка запису даних питань у файл '{$questionsFilePath}'."); }
                $redirectParams = ['section' => $redirectSection, 'message' => urlencode($message), 'msg_type' => 'success'];
                if ($selected_cat_index !== null) $redirectParams['selected_cat_index'] = $selected_cat_index;
                $anchor = ($selected_cat_index !== null) ? '#category-' . $selected_cat_index : '#questions-section';
                header("Location: admin.php?" . http_build_query($redirectParams) . $anchor);
                exit;
            } else { if (empty($message)) $message = "Дані питань не змінилися."; $message_type = 'info'; }
        } catch (Exception $e) { $message = "Помилка (Питання): " . $e->getMessage(); $message_type = 'error'; $questionsData = $originalData; }
    }
    elseif (isset($_POST['action_user'])) {
        $action_user = $_POST['action_user'];
        $originalUsers = $allUsers;
        $redirectSection = 'users';
        $postSearchQuery = trim($_POST['search_query'] ?? '');
        $postPage = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
        $mergeResult = null;

        try {
            switch ($action_user) {
                case 'add_user':
                    $username = trim($_POST['username'] ?? ''); $firstName = trim($_POST['first_name'] ?? ''); $lastName = trim($_POST['last_name'] ?? ''); $defaultPassword = 'mindflow2025';
                    if (empty($username) || mb_strlen($username) < USERNAME_MIN_LENGTH || mb_strlen($username) > USERNAME_MAX_LENGTH || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) throw new Exception('Некоректне ім\'я користувача (логін)...');
                    foreach ($allUsers as $user) { if (isset($user['username']) && strtolower($user['username']) === strtolower($username)) throw new Exception("Користувач з логіном '{$username}' вже існує."); }
                    $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT); if (!$passwordHash) throw new Exception("Помилка хешування пароля.");
                    $userId = generateUniqueId('user_');
                    $newUser = [
                        'id' => $userId, 
                        'username' => $username, 
                        'password_hash' => $passwordHash, 
                        'first_name' => $firstName, 
                        'last_name' => $lastName,
                        'hide_results' => true,
                        'hide_test_link' => false,
                        'participate_in_ratings' => true
                    ];
                    $allUsers[] = $newUser; $message = "Користувача '{$username}' створено. Пароль: <code>{$defaultPassword}</code>. Посилання на тест: <a href='questionnaire_other.php?target_user_id={$userId}' target='_blank'>Пройти тест</a>";
                    break;
                case 'update_user':
                    $userIdToUpdate = $_POST['user_id'] ?? null; $newUsername = trim($_POST['username'] ?? ''); $newFirstName = trim($_POST['first_name'] ?? ''); $newLastName = trim($_POST['last_name'] ?? ''); $userIndex = -1;
                    if (empty($userIdToUpdate)) throw new Exception("Не вказано ID користувача для оновлення.");
                    if (empty($newUsername) || mb_strlen($newUsername) < USERNAME_MIN_LENGTH || mb_strlen($newUsername) > USERNAME_MAX_LENGTH || !preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) throw new Exception('Некоректне ім\'я користувача (логін).');
                    foreach ($allUsers as $index => $user) { if (isset($user['id']) && $user['id'] === $userIdToUpdate) { $userIndex = $index; if (strtolower($user['username']) !== strtolower($newUsername)) { foreach ($allUsers as $otherIndex => $otherUser) { if ($index !== $otherIndex && isset($otherUser['username']) && strtolower($otherUser['username']) === strtolower($newUsername)) throw new Exception("Користувач з логіном '{$newUsername}' вже існує."); } } break; } }
                    if ($userIndex === -1) throw new Exception("Користувача з ID '{$userIdToUpdate}' не знайдено.");
                    $allUsers[$userIndex]['username'] = $newUsername; 
                    $allUsers[$userIndex]['first_name'] = $newFirstName; 
                    $allUsers[$userIndex]['last_name'] = $newLastName;
                    $allUsers[$userIndex]['hide_results'] = isset($_POST['hide_results']);
                    $allUsers[$userIndex]['hide_test_link'] = isset($_POST['hide_test_link']);
                    $allUsers[$userIndex]['participate_in_ratings'] = isset($_POST['participate_in_ratings']);
                    $message = "Дані користувача '{$newUsername}' оновлено.";
                    break;
                case 'delete_user':
                    $userIdToDelete = $_POST['user_id'] ?? null; $userIndexToDelete = -1;
                    if (empty($userIdToDelete)) throw new Exception("Не вказано ID користувача для видалення."); if ($userIdToDelete === $currentUserId) throw new Exception("Ви не можете видалити власний обліковий запис адміністратора.");
                    foreach ($allUsers as $index => $user) { if (isset($user['id']) && $user['id'] === $userIdToDelete) { $userIndexToDelete = $index; break; } }
                    if ($userIndexToDelete === -1) throw new Exception("Користувача з ID '{$userIdToDelete}' не знайдено.");
                    $deletedUsername = $allUsers[$userIndexToDelete]['username']; array_splice($allUsers, $userIndexToDelete, 1); $message = "Користувача '{$deletedUsername}' видалено.";
                    break;
                case 'merge_users':
                    $sourceUserId = $_POST['source_user_id'] ?? null; $targetUserId = $_POST['target_user_id'] ?? null; $priorityOption = $_POST['priority_user'] ?? 'target';
                    if (empty($sourceUserId) || empty($targetUserId)) { throw new Exception("Необхідно вибрати обох користувачів для об'єднання."); }
                    if ($sourceUserId === $targetUserId) { throw new Exception("Користувач-Джерело та Цільовий користувач не можуть бути однаковими."); }
                    if ($sourceUserId === $currentUserId) { throw new Exception("Ви не можете вибрати себе як користувача-джерело для об'єднання (це видалить ваш поточний обліковий запис)."); }
                    $priorityUserId = ($priorityOption === 'source') ? $sourceUserId : $targetUserId;
                    $defaultPasswordMerge = 'mindflow2025';
                    $mergeResult = mergeUsers($sourceUserId, $targetUserId, $priorityUserId, $defaultPasswordMerge);
                    if ($mergeResult['success']) { $message = $mergeResult['message']; $allUsers = readJsonFile($usersFilePath); } 
                    else { throw new Exception($mergeResult['message']); }
                    break;
                default:
                    throw new Exception("Невідома дія з користувачами.");
            }

            $dataChanged = ($originalUsers !== $allUsers);
            if (!empty($message) && !isset($e)) {
                 if ($action_user !== 'merge_users' && $dataChanged) {
                     if (!writeJsonFile($usersFilePath, $allUsers)) { $allUsers = $originalUsers; throw new Exception("Помилка запису даних користувачів у файл '{$usersFilePath}'."); }
                 } elseif ($action_user !== 'merge_users' && !$dataChanged) {
                     if (empty($message)) $message = "Дані користувачів не змінилися.";
                     $message_type = 'info'; $searchQuery = $postSearchQuery; $currentPage = $postPage;
                 }
                if ($message_type !== 'info') {
                    $redirectParams = ['section' => $redirectSection, 'message' => urlencode($message), 'msg_type' => 'success', 'search_query' => $postSearchQuery, 'page' => ($action_user === 'delete_user' || $action_user === 'merge_users') ? 1 : $postPage];
                    $anchor = ($action_user === 'merge_users') ? '#merge-users-section' : '#users-section';
                    header("Location: admin.php?" . http_build_query($redirectParams) . $anchor);
                    exit;
                }
            } else if (empty($message) && !isset($e)) { $message = "Дані користувачів не змінилися."; $message_type = 'info'; $searchQuery = $postSearchQuery; $currentPage = $postPage; }
        } catch (Exception $e) {
            $message = "Помилка (Користувачі): " . $e->getMessage();
            $message_type = 'error';
            if ($mergeResult === null) { $allUsers = $originalUsers; } 
            else { $allUsers = readJsonFile($usersFilePath); }
            $searchQuery = $postSearchQuery; $currentPage = $postPage;
        }
    }
    elseif (isset($_POST['action_trait'])) {
        $action_trait = $_POST['action_trait'];
        $originalTraits = $allTraits;
        $redirectSection = 'traits';
        try {
            $traitIndex = isset($_POST['traitIndex']) ? (int)$_POST['traitIndex'] : null;
            switch ($action_trait) {
                case 'add_trait':
                case 'update_trait':
                    $traitId = trim($_POST['traitId'] ?? ''); $traitName = trim($_POST['traitName'] ?? ''); $traitIcon = trim($_POST['traitIcon'] ?? ''); $traitDescription = trim($_POST['traitDescription'] ?? ''); $traitConditionsJson = trim($_POST['traitConditions'] ?? '[]');
                    if (empty($traitId) || empty($traitName) || empty($traitConditionsJson)) { throw new Exception("ID, Назва та Умови тріта не можуть бути порожніми."); }
                    if (!preg_match('/^[a-zA-Z0-9_]+$/', $traitId)) { throw new Exception("ID тріта може містити лише латинські літери, цифри та знак підкреслення (_)."); }
                    $conditions = []; $submittedConditions = $_POST['conditions'] ?? [];
                    if (!is_array($submittedConditions)) { throw new Exception("Отримано некоректний формат умов."); }
                    if (empty($submittedConditions)) { throw new Exception("Для тріта необхідно вказати хоча б одну умову."); }
                    foreach ($submittedConditions as $index => $condData) {
                         if (!isset($condData['type'], $condData['questionId'], $condData['operator'], $condData['value']) || $condData['value'] === '') { throw new Exception("Умова #".($index+1).": Відсутні обов'язкові поля (тип, питання, оператор, значення)."); }
                         if (!in_array($condData['type'], ['self', 'others'])) { throw new Exception("Умова #".($index+1).": Некоректний тип умови '{$condData['type']}'."); }
                         if (empty($condData['questionId'])) { throw new Exception("Умова #".($index+1).": Не вибрано питання."); }
                         if (!in_array($condData['operator'], ['>=', '<=', '==', '>', '<'])) { throw new Exception("Умова #".($index+1).": Некоректний оператор '{$condData['operator']}'."); }
                         if (!is_numeric($condData['value'])) { throw new Exception("Умова #".($index+1).": Значення повинно бути числом."); }
                         $validatedCondition = ['type' => $condData['type'], 'questionId' => $condData['questionId'], 'operator' => $condData['operator'], 'value' => (float)$condData['value']];
                         if ($condData['type'] === 'others') {
                              if (!isset($condData['aggregation']) || !in_array($condData['aggregation'], ['average', 'any', 'all'])) { throw new Exception("Умова #".($index+1).": Для типу 'others' необхідно вказати коректну агрегацію (average, any, all)."); }
                              $validatedCondition['aggregation'] = $condData['aggregation'];
                         }
                         $conditions[] = $validatedCondition;
                    }
                    $newTraitData = ['id' => $traitId, 'name' => $traitName, 'icon' => $traitIcon, 'description' => $traitDescription, 'conditions' => $conditions];
                    if ($action_trait === 'add_trait') {
                        foreach ($allTraits as $trait) { if (isset($trait['id']) && $trait['id'] === $traitId) { throw new Exception("Тріт з ID '{$traitId}' вже існує."); } }
                        $allTraits[] = $newTraitData; $message = "Тріт '{$traitName}' успішно додано.";
                    } else {
                        if ($traitIndex === null || !isset($allTraits[$traitIndex])) { throw new Exception("Невірний індекс тріта для оновлення."); }
                         if ($allTraits[$traitIndex]['id'] !== $traitId) { throw new Exception("Спроба змінити ID тріта під час оновлення не дозволена."); }
                        $allTraits[$traitIndex] = $newTraitData; $message = "Тріт '{$traitName}' успішно оновлено.";
                    }
                    break;
                case 'delete_trait':
                    if ($traitIndex === null || !isset($allTraits[$traitIndex])) { throw new Exception("Невірний індекс тріта для видалення."); }
                    $deletedTraitName = $allTraits[$traitIndex]['name'] ?? 'N/A';
                    array_splice($allTraits, $traitIndex, 1);
                    $message = "Тріт '{$deletedTraitName}' успішно видалено.";
                    break;
                default:
                    throw new Exception("Невідома дія з трітами.");
            }
            if ($originalTraits !== $allTraits) {
                $traitsFileDataToSave = ['traits' => $allTraits];
                if (!writeJsonFile(TRAITS_FILE_PATH, $traitsFileDataToSave)) { $allTraits = $originalTraits; throw new Exception("Помилка запису даних трітів у файл '" . TRAITS_FILE_PATH . "'."); }
                 $redirectParams = ['section' => $redirectSection, 'message' => urlencode($message), 'msg_type' => 'success'];
                 $anchor = ($action_trait === 'update_trait' && $traitIndex !== null) ? '#trait-' . $traitIndex : '#traits-section';
                 header("Location: admin.php?" . http_build_query($redirectParams) . $anchor);
                 exit;
            } else { $message = "Дані трітів не змінилися."; $message_type = 'info'; }
        } catch (Exception $e) { $message = "Помилка (Тріти): " . $e->getMessage(); $message_type = 'error'; $allTraits = $originalTraits; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['message'])) { $message = urldecode($_GET['message']); $message_type = ($_GET['msg_type'] ?? 'info'); }
     $action_edit = $_GET['action'] ?? null;
     $is_editing_question_item = false;
    if ($action_edit === 'edit_category' && isset($_GET['categoryIndex'])) {
        $editCategoryIndex = (int)$_GET['categoryIndex'];
        if (isset($questionsData[$editCategoryIndex])) { $editCategoryData = $questionsData[$editCategoryIndex]; $selected_cat_index = $editCategoryIndex; $selected_q_index = null; $is_editing_question_item = true; } 
        else { $message = "Помилка: Категорію з індексом '{$editCategoryIndex}' для редагування не знайдено."; $message_type = 'error'; $editCategoryIndex = null; }
    } elseif ($action_edit === 'edit_question' && isset($_GET['selected_cat_index'], $_GET['questionIndex'])) {
        $editCategoryIndex = (int)$_GET['selected_cat_index']; $editQuestionIndex = (int)$_GET['questionIndex'];
        if (isset($questionsData[$editCategoryIndex]['questions'][$editQuestionIndex])) { $editQuestionData = $questionsData[$editCategoryIndex]['questions'][$editQuestionIndex]; $selected_cat_index = $editCategoryIndex; $selected_q_index = $editQuestionIndex; $is_editing_question_item = true; } 
        else { $message = "Помилка: Питання з індексами '{$editCategoryIndex}/{$editQuestionIndex}' для редагування не знайдено."; $message_type = 'error'; $editCategoryIndex = null; $editQuestionIndex = null; $editQuestionData = null; }
    }
     $action_user_edit = $_GET['action_user'] ?? null;
    if ($action_user_edit === 'edit_user' && isset($_GET['user_id'])) {
        $userIdToEdit = $_GET['user_id']; $foundUser = false;
        foreach ($allUsers as $user) { if (isset($user['id']) && $user['id'] === $userIdToEdit) { $editUserData = $user; $foundUser = true; break; } }
        if (!$foundUser) { $message = "Помилка: Користувача з ID '{$userIdToEdit}' для редагування не знайдено."; $message_type = 'error'; $userIdToEdit = null; $editUserData = null; } 
        else { $editCategoryData = null; $editQuestionData = null; $editCategoryIndex = null; $editQuestionIndex = null; $is_editing_question_item = false; $selected_cat_index = null; $selected_q_index = null; }
    }
     if (!$is_editing_question_item) {
         if ($selected_cat_index !== null && !isset($questionsData[$selected_cat_index])) { $message = "Помилка: Вибрану категорію (індекс {$selected_cat_index}) не знайдено."; $message_type = 'error'; $selected_cat_index = null; $selected_q_index = null; }
          if ($selected_q_index !== null && (!isset($questionsData[$selected_cat_index]['questions'][$selected_q_index]))) { $selected_q_index = null; }
     }
    if ($action_edit === 'edit_trait' && isset($_GET['traitIndex'])) {
        $editTraitIndex = (int)$_GET['traitIndex'];
        if (isset($allTraits[$editTraitIndex])) { $editTraitData = $allTraits[$editTraitIndex]; $editCategoryData = null; $editQuestionData = null; $editCategoryIndex = null; $editQuestionIndex = null; $editUserData = null; $userIdToEdit = null; } 
        else { $message = "Помилка: Тріт з індексом '{$editTraitIndex}' для редагування не знайдено."; $message_type = 'error'; $editTraitIndex = null; }
    }
}

$filteredUsers = $allUsers;
if (!empty($searchQuery)) {
    $filteredUsers = array_filter($allUsers, function($user) use ($searchQuery) {
        $searchLower = mb_strtolower($searchQuery); $username = mb_strtolower($user['username'] ?? ''); $firstName = mb_strtolower($user['first_name'] ?? ''); $lastName = mb_strtolower($user['last_name'] ?? '');
        return strpos($username, $searchLower) !== false || strpos($firstName, $searchLower) !== false || strpos($lastName, $searchLower) !== false;
    });
}
$totalUsers = count($filteredUsers);
$totalPages = ($usersPerPage > 0 && $totalUsers > 0) ? ceil($totalUsers / $usersPerPage) : 1;
$currentPage = max(1, min($currentPage, $totalPages));
$offset = ($currentPage - 1) * $usersPerPage;
$paginatedUsers = ($usersPerPage > 0) ? array_slice(array_values($filteredUsers), $offset, $usersPerPage) : array_values($filteredUsers);

if (empty($message_type) && !empty($message)) { $message_type = (strpos($message, 'Помилка:') === 0 || strpos($message, 'Exception:') !== false) ? 'error' : 'success'; } 
elseif (empty($message)) { $message_type = ''; }

include __DIR__ . '/includes/header.php';
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
        <nav class="admin-nav">
            <a href="admin.php?section=users" class="<?php echo ($active_section === 'users') ? 'active' : ''; ?>">Управління Користувачами</a>
            <a href="admin.php?section=questions" class="<?php echo ($active_section === 'questions') ? 'active' : ''; ?>">Управління Питаннями</a>
            <a href="admin.php?section=traits" class="<?php echo ($active_section === 'traits') ? 'active' : ''; ?>">Управління Трітами</a>
        </nav>
        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <?php
        $section_file = __DIR__ . '/admin_' . $active_section . '.php';
        if (file_exists($section_file)) { include $section_file; } 
        elseif ($active_section === 'users') { include __DIR__ . '/admin_users.php'; } 
        elseif ($active_section === 'questions') { include __DIR__ . '/admin_questions.php'; } 
        elseif ($active_section === 'traits') { include __DIR__ . '/admin_traits.php'; } 
        else { echo "<p>Помилка: Файл секції '{$active_section}' не знайдено.</p>"; }
        ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
             let hash = window.location.hash;
             if (window.history.replaceState && window.location.search.includes('message=')) {
                 const url = new URL(window.location);
                 url.searchParams.delete('message');
                 url.searchParams.delete('msg_type');
                 window.history.replaceState({ path: url.href }, '', url.toString());
             }
             if (hash) {
                 try {
                    const elementId = hash.substring(1);
                    const targetElement = document.getElementById(elementId);
                    if (targetElement) {
                        let elementToHighlight = targetElement;
                        if (targetElement.classList.contains('edit-form-section') || targetElement.classList.contains('section-form') || targetElement.closest('.edit-form-section') || targetElement.closest('.section-form')) {
                             elementToHighlight = targetElement.closest('.edit-form-section, .section-form') || targetElement;
                        } else if (targetElement.tagName === 'TR' && targetElement.closest('table')) {
                            elementToHighlight = targetElement;
                        } else if (targetElement.classList.contains('category-item') || targetElement.classList.contains('question-item')) {
                             elementToHighlight = targetElement;
                        } else if (elementId === 'merge-users-section') {
                            elementToHighlight = document.getElementById('merge-users-section');
                        }
                        if(elementToHighlight) {
                            const originalBg = elementToHighlight.style.backgroundColor;
                            const originalBorder = elementToHighlight.style.borderColor;
                            elementToHighlight.style.transition = 'background-color 0.7s ease-in-out, border-color 0.7s ease-in-out';
                            elementToHighlight.style.backgroundColor = '#fff3cd';
                            elementToHighlight.style.borderColor = '#ffeeba';
                            setTimeout(() => {
                                elementToHighlight.style.backgroundColor = originalBg || '';
                                elementToHighlight.style.borderColor = originalBorder || '';
                                setTimeout(() => { elementToHighlight.style.transition = ''; }, 700);
                            }, 1500);
                        }
                         setTimeout(() => {
                              targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                         }, 100);
                    }
                 } catch (e) { console.error("Error scrolling/highlighting:", e); }
             } else if (document.querySelector('.message:not(:empty)')) {
                 const messageElement = document.querySelector('.message');
                 const rect = messageElement.getBoundingClientRect();
                 if (rect.top < 0 || rect.bottom > (window.innerHeight || document.documentElement.clientHeight)) {
                     messageElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                 }
             }
        });
    </script>
</body>
</html>
