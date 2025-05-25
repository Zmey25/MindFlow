<?php
/**
 * Файл обробки зворотнього виклику Google OAuth
 * 
 * Цей файл обробляє відповідь від Google після авторизації користувача
 */

// Стартуємо сесію для збереження даних авторизації з тими самими параметрами
if (session_status() == PHP_SESSION_NONE) {
    // Покращуємо безпеку сесії
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.gc_maxlifetime', 21600); // 6 годин
    ini_set('session.cookie_lifetime', 21600);
    
    session_start();
}

// Підключаємо необхідні файли
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/google-oauth.php';

// Перевіряємо наявність коду авторизації
if (!isset($_GET['code'])) {
    // Якщо є помилка від Google
    if (isset($_GET['error'])) {
        $_SESSION['google_oauth_error'] = $_GET['error'];
        header('Location: login.php?error=google_oauth');
        exit;
    }
    
    // Якщо немає ні коду, ні помилки
    header('Location: login.php?error=no_code');
    exit;
}

// Отримуємо код та стан
$code = $_GET['code'];
$state = $_GET['state'] ?? '';

// Створюємо лог файл для діагностики
$logFile = __DIR__ . '/google_oauth_log.txt';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Отримано код: " . $code . "\n", FILE_APPEND);
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Отримано стан: " . $state . "\n", FILE_APPEND);

// Обробляємо авторизацію через Google
try {
    $result = processGoogleLogin($code, $state);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Результат: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
} catch (Exception $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Виняток: " . $e->getMessage() . "\n", FILE_APPEND);
    $_SESSION['google_oauth_error'] = "Виняток: " . $e->getMessage();
    header('Location: login.php?error=google_oauth');
    exit;
}

// Перевіряємо результат авторизації
if (!$result['success']) {
    // Зберігаємо помилку в сесію
    $_SESSION['google_oauth_error'] = $result['error'];
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Помилка: " . $result['error'] . "\n", FILE_APPEND);
    header('Location: login.php?error=google_oauth');
    exit;
}

// Успішна авторизація - перенаправляємо на дашборд
header('Location: dashboard.php');
exit;