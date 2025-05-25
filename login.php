<?php
// login.php
ini_set('session.gc_maxlifetime', 21600); // Час життя сесії на сервері (6 годин)
ini_set('session.cookie_lifetime', 21600); // Час життя куки сесії у браузері (6 годин)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/env-loader.php';
session_start(); // Важливо стартувати сесію ДО будь-якого виводу HTML
$pageTitle = "Вхід";

// Завантажуємо змінні середовища з .env файлу
$envPath = __DIR__ . '/../.env';
if (function_exists('loadEnv')) {
    loadEnv($envPath);
}

$message = '';
$message_type = '';

// Якщо користувач вже залогінений, перенаправляємо на дашборд
if (isUserLoggedIn()) {
    $redirectUrl = 'dashboard.php';
    if (isset($_SESSION['redirect_after_login']) && !empty($_SESSION['redirect_after_login'])) {
        $redirectUrl = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
    }
    header('Location: ' . $redirectUrl);
    exit;
}

// Обробка POST-запиту (коли форма відправлена)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = loginUser($username, $password);

    if ($result['success']) {
        // Успішний вхід - зберігаємо дані користувача в сесії
        $_SESSION['user_id'] = $result['user']['id'];
        $_SESSION['username'] = $result['user']['username'];

    $redirectUrl = 'dashboard.php'; // Шлях за замовчуванням (якщо немає збереженого)
    if (isset($_SESSION['redirect_after_login']) && !empty($_SESSION['redirect_after_login'])) {
        $redirectUrl = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
    }

    header('Location: ' . $redirectUrl);
    exit;
    } else {
        $message = $result['message'];
        $message_type = 'error';
    }
}

// Перевірка, чи прийшли з успішною реєстрацією
if (isset($_GET['reg']) && $_GET['reg'] === 'success') {
     $message = 'Реєстрація успішна! Тепер ви можете увійти.';
     $message_type = 'success';
}

// Перевірка помилок Google OAuth
if (isset($_GET['error']) && $_GET['error'] === 'google_oauth') {
    $message = $_SESSION['google_oauth_error'] ?? 'Помилка при вході через Google. Спробуйте ще раз.';
    $message_type = 'error';
    
    // Очищаємо повідомлення про помилку з сесії
    unset($_SESSION['google_oauth_error']);
}

include __DIR__ . '/includes/header.php';
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <!-- Add Google client ID for OAuth -->
    <meta name="google-signin-client_id" content="<?php echo htmlspecialchars(getenv('GOOGLE_CLIENT_ID')); ?>">
    <!-- Include Google button CSS -->
    <link rel="stylesheet" href="assets/css/google-button.css">
</head>
<body>
    <div class="auth-container">
        <h1>Вхід в MindFlow</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="username">Ім'я користувача:</label>
                <input type="text" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Увійти</button>
        </form>

        <!-- Separator -->
        <div class="separator">
            <span>або</span>
        </div>
        
        <!-- Google Sign-In Button -->
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
                <span class="btn-text">Увійти через Google</span>
            </a>
        </div>

        <p>Немає акаунту? <a href="register.php">Зареєструватися</a></p>
    </div>

    <!-- Include Google OAuth JavaScript -->
    <script src="assets/js/google-oauth.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>