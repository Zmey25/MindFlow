<?php
// register.php
session_start(); // Потрібно для роботи з сесіями
$pageTitle = "Реєстрація";
require_once __DIR__ . '/includes/auth.php'; // Тут функція registerUser
require_once __DIR__ . '/includes/env-loader.php'; // Для GOOGLE_CLIENT_ID

// Завантажуємо змінні середовища з .env файлу
$envPath = __DIR__ . '/../.env';
if (function_exists('loadEnv')) {
    loadEnv($envPath);
}

$message = '';
$message_type = ''; // 'success' або 'error'

// Якщо користувач вже залогінений, перенаправляємо
if (isUserLoggedIn()) {
    $redirectUrl = 'dashboard.php';
    if (isset($_SESSION['redirect_after_login']) && !empty($_SESSION['redirect_after_login'])) {
        $redirectUrl = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
    }
    header('Location: ' . $redirectUrl);
    exit;
}

// Ініціалізуємо змінні для форми, щоб уникнути помилок undefined variable
// і для збереження значень при помилці валідації
$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? ''; // Нове поле email
$first_name = $_POST['first_name'] ?? '';
$last_name = $_POST['last_name'] ?? '';


// --- Обробка POST-запиту ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $captcha_input = $_POST['captcha'] ?? '';

    // Очищаємо змінні від зайвих пробілів (для полів, які йдуть в registerUser)
    $username = trim($username);
    $email = trim($email);
    $first_name = trim($first_name);
    $last_name = trim($last_name);

    // 1. Перевірка CAPTCHA
    $valid_captcha = false;
    if (isset($_SESSION['captcha_code']) && !empty($_SESSION['captcha_code'])) {
        if (strtoupper($captcha_input) === $_SESSION['captcha_code']) {
            $valid_captcha = true;
        }
    }

    if (!$valid_captcha) {
        $message = 'Неправильний код CAPTCHA.';
        $message_type = 'error';
        generateCaptchaCode(); // Регенеруємо
    } elseif (empty($email)) { // Додаємо перевірку на порожній email перед іншими
        $message = 'Адреса електронної пошти є обов\'язковою.';
        $message_type = 'error';
        generateCaptchaCode(); // Регенеруємо
    } elseif ($password !== $password_confirm) {
        $message = 'Паролі не співпадають.';
        $message_type = 'error';
        generateCaptchaCode(); // Регенеруємо
    } else {
        // Якщо капча, email і паролі вірні, пробуємо зареєструвати
        $result = registerUser($username, $password, $email, $first_name, $last_name);
        if ($result['success']) {
            $message = $result['message'] . ' Тепер ви можете увійти.';
            $message_type = 'success';
            unset($_SESSION['captcha_code']); // Успіх - можна очистити капчу
            
            // Очищаємо поля форми після успішної реєстрації
            $username = '';
            $email = '';
            $first_name = '';
            $last_name = '';
            // Не скидаємо $_POST, щоб повідомлення про успіх відобразилося коректно
            // header('Location: login.php?reg=success'); // Розкоментуйте, якщо хочете перенаправляти
            // exit;
        } else {
            $message = $result['message']; // Повідомлення про помилку від registerUser
            $message_type = 'error';
            generateCaptchaCode(); // Регенеруємо
        }
    }
} else {
    // Якщо це GET запит (перше завантаження сторінки), генеруємо CAPTCHA
    generateCaptchaCode();
    // Ініціалізуємо змінні, щоб форма була порожньою при першому завантаженні
    $username = '';
    $email = '';
    $first_name = '';
    $last_name = '';
}
// --- Кінець обробки POST ---


$pageTitle = "Реєстрація";
include __DIR__ . '/includes/header.php';

?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <!-- Add Google client ID for OAuth -->
    <meta name="google-signin-client_id" content="<?php echo htmlspecialchars(getenv('GOOGLE_CLIENT_ID') ?: ''); ?>">
    <!-- Include Google button CSS -->
    <link rel="stylesheet" href="assets/css/google-button.css">
    <style>
        /* ... (існуючі стилі для captcha залишаються) ... */
        .captcha-block {
            display: flex;
            align-items: center;
            flex-wrap: wrap; /* Дозволити перенос на малих екранах */
            gap: 10px;
            margin-bottom: 15px; /* Додамо відступ знизу */
        }
        .captcha-block label {
            flex-basis: 100%; /* Лейбл на всю ширину */
            margin-bottom: 5px; /* Невеликий відступ для лейбла */
        }
        .captcha-code {
            padding: 8px 15px;
            background-color: #eee;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1.2em;
            font-weight: bold;
            letter-spacing: 2px; /* Розрідження для читабельності */
            user-select: none; /* Щоб не можна було виділити текст */
            font-family: 'Courier New', Courier, monospace; /* Моноширинний шрифт */
            margin-right: 10px; /* Відступ праворуч від коду */
        }
        .captcha-block input[type="text"] {
           flex-grow: 1; /* Поле займає решту місця */
           width: auto; /* Перевизначаємо стандартну ширину */
           max-width: 150px; /* Обмежимо ширину */
        }
        /* Позначка обов'язкових полів */
        .form-group label:after { /* Застосовуємо до всіх лейблів в .form-group */
            content: " *";
            color: red;
            display: inline;
        }
        /* Прибираємо зірочку для необов'язкових полів */
        .form-group label[for="first_name"]:after,
        .form-group label[for="last_name"]:after {
            content: ""; /* Порожній контент */
        }
    </style>
</head>
<body>
<div class="auth-container">
    <h1>Реєстрація в MindFlow</h1>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="register.php" method="POST">
        <div class="form-group">
            <label for="username">Ім'я користувача (<?php echo USERNAME_MIN_LENGTH . '-' . USERNAME_MAX_LENGTH; ?> симв., a-z, A-Z, 0-9, _):</label>
            <input type="text" id="username" name="username" required
                   minlength="<?php echo USERNAME_MIN_LENGTH; ?>"
                   maxlength="<?php echo USERNAME_MAX_LENGTH; ?>"
                   pattern="^[a-zA-Z0-9_]+$"
                   title="Дозволені лише латинські літери, цифри та знак підкреслення"
                   value="<?php echo htmlspecialchars($username); ?>">
        </div>

        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required maxlength="100"
                   value="<?php echo htmlspecialchars($email); ?>">
        </div>

        <div class="form-group">
            <label for="first_name">Ім'я:</label>
            <input type="text" id="first_name" name="first_name" maxlength="50"
                   value="<?php echo htmlspecialchars($first_name); ?>">
        </div>
        <div class="form-group">
            <label for="last_name">Прізвище:</label>
            <input type="text" id="last_name" name="last_name" maxlength="50"
                   value="<?php echo htmlspecialchars($last_name); ?>">
        </div>

        <div class="form-group">
            <label for="password">Пароль (<?php echo PASSWORD_MIN_LENGTH . '-' . PASSWORD_MAX_LENGTH; ?> симв.):</label>
            <input type="password" id="password" name="password" required
                   minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                   maxlength="<?php echo PASSWORD_MAX_LENGTH; ?>">
        </div>
         <div class="form-group">
            <label for="password_confirm">Підтвердіть пароль:</label>
            <input type="password" id="password_confirm" name="password_confirm" required
                   minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                   maxlength="<?php echo PASSWORD_MAX_LENGTH; ?>">
        </div>

        <div class="form-group captcha-block">
             <label for="captcha">Введіть код:</label>
             <div class="captcha-code">
                 <?php if(isset($_SESSION['captcha_code'])): ?>
                     <strong><?php echo htmlspecialchars($_SESSION['captcha_code']); ?></strong>
                 <?php else: ?>
                      <span>Помилка CAPTCHA</span>
                 <?php endif; ?>
             </div>
            <input type="text" id="captcha" name="captcha" required autocomplete="off" maxlength="5" style="text-transform: uppercase;">
        </div>

        <button type="submit" class="btn">Зареєструватися</button>
    </form>

    <div class="separator">
        <span>або</span>
    </div>
    
    <div class="google-btn-container">
        <a href="javascript:void(0);" onclick="startGoogleLogin()" class="google-btn">
            <div class="google-icon-wrapper">
                <svg class="google-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                    <path fill="none" d="M0 0h48v48H0z"/>
                </svg>
            </div>
            <span class="btn-text">Зареєструватися через Google</span>
        </a>
    </div>

    <p>Вже маєте акаунт? <a href="login.php">Увійти</a></p>
</div>

<script src="assets/js/google-oauth.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>