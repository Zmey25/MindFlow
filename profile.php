<?php // profile.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$pageTitle = "Профіль користувача";

$message = '';
$message_type = '';

$users = readJsonFile(USERS_FILE_PATH);
if ($users === false) {
    die("Критична помилка: не вдалося прочитати файл користувачів.");
}

$currentUser = null;
$userIndex = null;

foreach ($users as $index => $user) {
    if (isset($user['id']) && $user['id'] === $userId) {
        $currentUser = $user;
        $userIndex = $index;
        break;
    }
}

if ($currentUser === null || $userIndex === null) {
    die("Критична помилка: не вдалося знайти дані поточного користувача.");
}

// Встановлення значень за замовчуванням
$currentUser['first_name'] = $currentUser['first_name'] ?? '';
$currentUser['last_name'] = $currentUser['last_name'] ?? '';
$currentUser['email'] = $currentUser['email'] ?? '';
$currentUser['custom_question'] = $currentUser['custom_question'] ?? '';
$currentUser['hide_results'] = $currentUser['hide_results'] ?? true;
$currentUser['hide_test_link'] = $currentUser['hide_test_link'] ?? false; // Нове поле
$currentUser['participate_in_ratings'] = $currentUser['participate_in_ratings'] ?? true; // Нове поле

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $custom_question_raw = trim($_POST['custom_question'] ?? '');
        $hide_results = isset($_POST['hide_results']);
        $hide_test_link = isset($_POST['hide_test_link']); // Нове поле
        $participate_in_ratings = isset($_POST['participate_in_ratings']); // Нове поле

        if (mb_strlen($first_name) > 50) {
            $message = "Ім'я не повинно перевищувати 50 символів.";
            $message_type = 'error';
        } elseif (mb_strlen($last_name) > 50) {
            $message = "Прізвище не повинно перевищувати 50 символів.";
            $message_type = 'error';
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Будь ласка, введіть дійсну адресу електронної пошти.";
            $message_type = 'error';
        } elseif (mb_strlen($email) > 100) {
            $message = "Адреса електронної пошти не повинна перевищувати 100 символів.";
            $message_type = 'error';
        } elseif (mb_strlen($custom_question_raw) > 500) {
            $message = "Кастомне питання не повинно перевищувати 500 символів.";
            $message_type = 'error';
        } else {
            $custom_question_sanitized = strip_tags($custom_question_raw);

            $users[$userIndex]['first_name'] = $first_name;
            $users[$userIndex]['last_name'] = $last_name;
            $users[$userIndex]['email'] = $email;
            $users[$userIndex]['custom_question'] = $custom_question_sanitized;
            $users[$userIndex]['hide_results'] = $hide_results;
            $users[$userIndex]['hide_test_link'] = $hide_test_link; // Зберігаємо нове поле
            $users[$userIndex]['participate_in_ratings'] = $participate_in_ratings; // Зберігаємо нове поле

            if (writeJsonFile(USERS_FILE_PATH, $users)) {
                $message = "Профіль успішно оновлено!";
                $message_type = 'success';
                $currentUser = $users[$userIndex]; // Оновлюємо поточні дані для відображення
            } else {
                $message = "Помилка при збереженні даних профілю. Спробуйте пізніше.";
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $min_len = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8;
        $max_len = defined('PASSWORD_MAX_LENGTH') ? PASSWORD_MAX_LENGTH : 72;

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = "Всі поля для зміни паролю повинні бути заповнені.";
            $message_type = 'error';
        } elseif (strlen($new_password) < $min_len || strlen($new_password) > $max_len) {
            $message = "Новий пароль повинен містити від " . $min_len . " до " . $max_len . " символів.";
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = "Новий пароль та підтвердження не співпадають.";
            $message_type = 'error';
        } elseif (!password_verify($current_password, $users[$userIndex]['password_hash'])) {
            $message = "Поточний пароль невірний.";
            $message_type = 'error';
        } else {
            $users[$userIndex]['password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
            if (writeJsonFile(USERS_FILE_PATH, $users)) {
                $message = "Пароль успішно змінено!";
                $message_type = 'success';
            } else {
                $message = "Помилка при збереженні нового паролю. Спробуйте пізніше.";
                $message_type = 'error';
            }
        }
    }
     if ($message_type === 'success') {
        $currentUser = $users[$userIndex];
     }
}

$passwordMinLength = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8;
$passwordMaxLength = defined('PASSWORD_MAX_LENGTH') ? PASSWORD_MAX_LENGTH : 72;

include __DIR__ . '/includes/header.php';
?>

<h1>Профіль користувача</h1>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="profile-section">
    <h2>Інформація профілю та налаштування</h2>
    <form action="profile.php" method="POST" class="profile-form">
        <div class="form-group">
            <label for="username">Логін:</label>
            <input type="text" id="username" value="<?php echo htmlspecialchars($username); ?>" disabled>
            <span class="field-note">Логін не можна змінити</span>
        </div>
        <div class="form-group">
            <label for="first_name">Ім'я:</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($currentUser['first_name']); ?>" maxlength="50">
        </div>
        <div class="form-group">
            <label for="last_name">Прізвище:</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($currentUser['last_name']); ?>" maxlength="50">
        </div>
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" maxlength="100">
        </div>
        
        <h3 class="form-subtitle">Налаштування приватності</h3>
        <div class="form-group checkbox-group">
             <input type="checkbox" id="hide_results" name="hide_results" value="1" <?php echo ($currentUser['hide_results']) ? 'checked' : ''; ?>>
             <label for="hide_results">Приховати мої результати від інших користувачів</label>
             <span class="field-note">Ви завжди бачите свої результати.</span>
        </div>
        <div class="form-group checkbox-group">
             <input type="checkbox" id="hide_test_link" name="hide_test_link" value="1" <?php echo ($currentUser['hide_test_link'] ?? false) ? 'checked' : ''; ?>>
             <label for="hide_test_link">Приховати можливість проходити тест про мене</label>
             <span class="field-note">Якщо відмічено, посилання на проходження тесту про вас буде недоступним для інших.</span>
        </div>
        <div class="form-group checkbox-group">
             <input type="checkbox" id="participate_in_ratings" name="participate_in_ratings" value="1" <?php echo ($currentUser['participate_in_ratings'] ?? true) ? 'checked' : ''; ?>>
             <label for="participate_in_ratings">Брати участь у таблиці рейтингів</label>
             <span class="field-note">Якщо не відмічено, ваші показники не будуть враховуватись у публічних рейтингах.</span>
        </div>

        <h3 class="form-subtitle">Кастомне питання</h3>
        <div class="form-group">
            <label for="custom_question">Ваше кастомне питання (макс. 500 символів):</label>
            <textarea id="custom_question" name="custom_question" rows="4" maxlength="500"><?php echo htmlspecialchars($currentUser['custom_question']); ?></textarea>
            <span class="field-note">Це поле призначене для вашого персонального питання, яке запропонуєтся при проходженні теста про вас.</span>
        </div>

        <button type="submit" name="update_profile" class="btn btn-primary">Оновити профіль</button>
    </form>
</div>

<div class="profile-section">
    <h2>Зміна паролю</h2>
    <form action="profile.php" method="POST" class="password-form">
        <div class="form-group">
            <label for="current_password">Поточний пароль:</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>
        <div class="form-group">
            <label for="new_password">Новий пароль:</label>
            <input type="password" id="new_password" name="new_password" required minlength="<?php echo $passwordMinLength; ?>" maxlength="<?php echo $passwordMaxLength; ?>">
            <span class="field-note">Пароль повинен містити від <?php echo $passwordMinLength; ?> до <?php echo $passwordMaxLength; ?> символів</span>
        </div>
        <div class="form-group">
            <label for="confirm_password">Підтвердіть новий пароль:</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="<?php echo $passwordMinLength; ?>" maxlength="<?php echo $passwordMaxLength; ?>">
        </div>
        <button type="submit" name="change_password" class="btn btn-primary">Змінити пароль</button>
    </form>
</div>

<div class="back-link">
    <a href="dashboard.php">Повернутися на панель керування</a>
</div>

<style>
.profile-container { max-width: 800px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
.profile-section { background-color: #f9f9f9; border-radius: 8px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05); }
.profile-form, .password-form { max-width: 500px; margin: 0 auto; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
.form-group input[type="text"], .form-group input[type="email"], .form-group input[type="password"], .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; font-size: 1em; }
.form-group input[disabled] { background-color: #eee; cursor: not-allowed; }
.field-note { display: block; color: #777; font-size: 0.85em; margin-top: 5px; line-height: 1.3; }
.profile-container h1 { text-align: center; margin-bottom: 30px; color: #333; }
.profile-container h2 { margin-top: 0; border-bottom: 2px solid #5C67F2; padding-bottom: 10px; margin-bottom: 25px; color: #444; }
.form-subtitle { margin-top: 25px; margin-bottom: 15px; color: #555; font-size: 1.1em; font-weight: bold; border-bottom: 1px solid #e0e0e0; padding-bottom: 8px; }
.btn.btn-primary { background-color: #5C67F2; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; transition: background-color 0.3s ease; display: block; width: 100%; margin-top: 10px; }
.btn.btn-primary:hover { background-color: #4a54c4; }
.back-link { margin-top: 30px; text-align: center; }
.back-link a { color: #5C67F2; text-decoration: none; font-weight: bold; }
.back-link a:hover { text-decoration: underline; }
.message { padding: 15px; margin-bottom: 20px; border-radius: 5px; text-align: center; }
.message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.checkbox-group { display: flex; align-items: center; flex-wrap: wrap; }
.checkbox-group input[type="checkbox"] { margin-right: 10px; width: auto; flex-shrink: 0; }
.checkbox-group label { margin-bottom: 0; font-weight: normal; }
.checkbox-group .field-note { margin-top: 5px; width: 100%; padding-left: 28px; box-sizing: border-box; }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
