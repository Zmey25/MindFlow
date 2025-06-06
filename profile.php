<?php // profile.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php'; // У вас тут USERS_FILE_PATH та функції читання/запису

// Require user to be logged in
requireLogin();

// Get user data from session
$userId = $_SESSION['user_id'];
$username = $_SESSION['username']; // Username (login) is from session and assumed not changeable here
$pageTitle = "Профіль користувача";

// Define variables for messages
$message = '';
$message_type = '';

// Get all users data
$users = readJsonFile(USERS_FILE_PATH);
if ($users === false) {
    // Handle error reading users file, e.g., show an error message or redirect
    die("Критична помилка: не вдалося прочитати файл користувачів.");
}

$currentUser = null;
$userIndex = null; // Зберігаємо індекс для легкого оновлення

// Find the current user in the users array
foreach ($users as $index => $user) {
    if (isset($user['id']) && $user['id'] === $userId) {
        $currentUser = $user;
        $userIndex = $index;
        break;
    }
}

// Якщо користувача з якоїсь причини не знайдено в файлі, це помилка
if ($currentUser === null || $userIndex === null) {
    // Можливо, перенаправити на вихід або показати фатальну помилку
    die("Критична помилка: не вдалося знайти дані поточного користувача.");
}

// Встановлюємо значення за замовчуванням для полів, якщо вони відсутні
$currentUser['first_name'] = $currentUser['first_name'] ?? '';
$currentUser['last_name'] = $currentUser['last_name'] ?? '';
$currentUser['email'] = $currentUser['email'] ?? '';
$currentUser['custom_question'] = $currentUser['custom_question'] ?? '';
$currentUser['hide_results'] = $currentUser['hide_results'] ?? true; // За замовчуванням приховано

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine which form was submitted
    if (isset($_POST['update_profile'])) {
        // Profile update form submitted
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $custom_question_raw = trim($_POST['custom_question'] ?? '');
        // Отримуємо значення чекбокса: true якщо він був відправлений (checked), false якщо ні
        $hide_results = isset($_POST['hide_results']);

        // Basic validation
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
            // Sanitize custom question for storage (remove HTML tags)
            $custom_question_sanitized = strip_tags($custom_question_raw);

            // Update user data in the array using the found index
            $users[$userIndex]['first_name'] = $first_name;
            $users[$userIndex]['last_name'] = $last_name;
            $users[$userIndex]['email'] = $email;
            $users[$userIndex]['custom_question'] = $custom_question_sanitized;
            $users[$userIndex]['hide_results'] = $hide_results; // Зберігаємо булеве значення

            // Save updated users data
            if (writeJsonFile(USERS_FILE_PATH, $users)) {
                $message = "Профіль успішно оновлено!";
                $message_type = 'success';

                // Update current user data for display AFTER saving
                $currentUser['first_name'] = $first_name;
                $currentUser['last_name'] = $last_name;
                $currentUser['email'] = $email;
                $currentUser['custom_question'] = $custom_question_sanitized;
                $currentUser['hide_results'] = $hide_results;
            } else {
                $message = "Помилка при збереженні даних профілю. Спробуйте пізніше.";
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Password change form submitted
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = "Всі поля для зміни паролю повинні бути заповнені.";
            $message_type = 'error';
        } elseif (strlen($new_password) < (defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8) || strlen($new_password) > (defined('PASSWORD_MAX_LENGTH') ? PASSWORD_MAX_LENGTH : 72)) {
            $min_len = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8;
            $max_len = defined('PASSWORD_MAX_LENGTH') ? PASSWORD_MAX_LENGTH : 72;
            $message = "Новий пароль повинен містити від " . $min_len . " до " . $max_len . " символів.";
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = "Новий пароль та підтвердження не співпадають.";
            $message_type = 'error';
        } else {
            if (!password_verify($current_password, $users[$userIndex]['password_hash'])) {
                $message = "Поточний пароль невірний.";
                $message_type = 'error';
            } else {
                // Hash new password
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                // Update password in the array
                $users[$userIndex]['password_hash'] = $new_password_hash;

                // Save updated users data
                if (writeJsonFile(USERS_FILE_PATH, $users)) {
                    $message = "Пароль успішно змінено!";
                    $message_type = 'success';
                } else {
                    $message = "Помилка при збереженні нового паролю. Спробуйте пізніше.";
                    $message_type = 'error';
                }
            }
        }
    }
     // Оновлюємо $currentUser після будь-якої зміни, щоб відображати актуальні дані
     if ($message_type === 'success' && isset($users[$userIndex])) { // Ensure userIndex is still valid
        $currentUser = $users[$userIndex];
        // Перезабезпечимо значення за замовчуванням, якщо вони раптом пропали (малоймовірно, але безпечно)
        $currentUser['first_name'] = $currentUser['first_name'] ?? '';
        $currentUser['last_name'] = $currentUser['last_name'] ?? '';
        $currentUser['email'] = $currentUser['email'] ?? '';
        $currentUser['custom_question'] = $currentUser['custom_question'] ?? '';
        $currentUser['hide_results'] = $currentUser['hide_results'] ?? true;
     }
}

// Provide default values for password length constants if not defined
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
            <div class="form-group checkbox-group">
                 <input type="checkbox" id="hide_results" name="hide_results" value="1" <?php echo ($currentUser['hide_results']) ? 'checked' : ''; ?>>
                 <label for="hide_results">Приховати мої результати від інших користувачів</label>
                 <span class="field-note">Ви завжди бачите свої результати.</span>
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
                <input type="password" id="new_password" name="new_password" required
                       minlength="<?php echo $passwordMinLength; ?>"
                       maxlength="<?php echo $passwordMaxLength; ?>">
                <span class="field-note">Пароль повинен містити від <?php echo $passwordMinLength; ?> до <?php echo $passwordMaxLength; ?> символів</span>
            </div>
            <div class="form-group">
                <label for="confirm_password">Підтвердіть новий пароль:</label>
                <input type="password" id="confirm_password" name="confirm_password" required
                       minlength="<?php echo $passwordMinLength; ?>"
                       maxlength="<?php echo $passwordMaxLength; ?>">
            </div>
            <button type="submit" name="change_password" class="btn btn-primary">Змінити пароль</button>
        </form>
    </div>

    <div class="back-link">
        <a href="dashboard.php">Повернутися на панель керування</a>
    </div>

<!-- Add some additional CSS styles for the profile page -->
<style>
.profile-container { max-width: 800px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
.profile-section { background-color: #f9f9f9; border-radius: 8px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05); }
.profile-form, .password-form { max-width: 500px; margin: 0 auto; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
.form-group input[type="text"],
.form-group input[type="email"], /* Added email type */
.form-group input[type="password"],
.form-group textarea { /* Added textarea */
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box; /* Important for width 100% */
    font-family: inherit; /* Inherit font from body/container */
    font-size: 1em; /* Match other inputs */
}
.form-group input[disabled] { background-color: #eee; cursor: not-allowed; }
.field-note { display: block; color: #777; font-size: 0.85em; margin-top: 5px; line-height: 1.3; }
.profile-container h1 { text-align: center; margin-bottom: 30px; color: #333; }
.profile-container h2 { margin-top: 0; border-bottom: 2px solid #5C67F2; padding-bottom: 10px; margin-bottom: 25px; color: #444; }
.profile-form .form-subtitle { /* Style for the "Кастомне питання" sub-heading */
    margin-top: 25px;
    margin-bottom: 15px;
    color: #555;
    font-size: 1.1em; /* Slightly smaller than h2 */
    font-weight: bold;
    border-bottom: 1px solid #e0e0e0;
    padding-bottom: 8px;
}
.btn.btn-primary { background-color: #5C67F2; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; transition: background-color 0.3s ease; display: block; width: 100%; margin-top: 10px; }
.btn.btn-primary:hover { background-color: #4a54c4; }
.back-link { margin-top: 30px; text-align: center; }
.back-link a { color: #5C67F2; text-decoration: none; font-weight: bold; }
.back-link a:hover { text-decoration: underline; }
.message { padding: 15px; margin-bottom: 20px; border-radius: 5px; text-align: center; }
.message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.message.info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
.message.warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }

/* Стилі для чекбокса */
.checkbox-group { display: flex; align-items: center; flex-wrap: wrap; }
.checkbox-group input[type="checkbox"] { margin-right: 10px; width: auto; }
.checkbox-group label { margin-bottom: 0; font-weight: normal; /* Забираємо жирність у лейбла чекбокса */ }
.checkbox-group .field-note { margin-top: 5px; width: 100%; /* Примітка під чекбоксом */ }

</style>

<?php include __DIR__ . '/includes/footer.php'; ?>