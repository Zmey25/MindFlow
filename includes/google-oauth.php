<?php
/**
 * Модуль для обробки Google OAuth
 * 
 * Цей файл містить функції для взаємодії з API аутентифікації Google
 */

// Стартуємо сесію для зберігання стану OAuth
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
require_once __DIR__ . '/env-loader.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// Завантажуємо змінні оточення
loadEnv(__DIR__ . '/../../.env');

// Перевіряємо, чи існує користувач з таким username
function usernameExists($username, $users) {
    foreach ($users as $user) {
        if (strtolower($user['username']) === strtolower($username)) {
            return true;
        }
    }
    return false;
}

/**
 * Формує URL для авторизації в Google
 * 
 * @return array Масив з URL авторизації або помилкою
 */
function getGoogleAuthUrl() {
    // Отримуємо необхідні дані з змінних оточення
    $clientId = getenv('GOOGLE_CLIENT_ID');
    $redirectUri = getenv('GOOGLE_REDIRECT_URI');
    
    // Перевіряємо наявність необхідних даних
    if (!$clientId || !$redirectUri) {
        return [
            'success' => false,
            'error' => 'Google OAuth налаштування не знайдено'
        ];
    }
    
    // Створюємо стан для захисту від CSRF
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;
    
    // Створюємо URL для авторизації
    $authUrl = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email',
        'state' => $state,
        'prompt' => 'select_account'
    ]);
    
    return [
        'success' => true,
        'auth_url' => $authUrl
    ];
}

/**
 * Отримує токен доступу від Google
 *
 * @param string $code Авторизаційний код
 * @return array Масив з токеном або помилкою
 */
function getGoogleAccessToken($code) {
    // Отримуємо необхідні дані з змінних оточення
    $clientId = getenv('GOOGLE_CLIENT_ID');
    $clientSecret = getenv('GOOGLE_CLIENT_SECRET');
    $redirectUri = getenv('GOOGLE_REDIRECT_URI');
    
    // Перевіряємо наявність необхідних даних
    if (!$clientId || !$clientSecret || !$redirectUri) {
        return [
            'success' => false,
            'error' => 'Google OAuth налаштування не знайдено'
        ];
    }
    
    // Відправляємо запит на отримання токена
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    // Отримуємо відповідь
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Перевіряємо відповідь
    if ($httpCode !== 200 || !$response) {
        return [
            'success' => false,
            'error' => 'Не вдалося отримати токен доступу'
        ];
    }
    
    // Декодуємо відповідь
    $data = json_decode($response, true);
    if (!isset($data['access_token'])) {
        return [
            'success' => false,
            'error' => 'Неправильний формат відповіді від Google'
        ];
    }
    
    return [
        'success' => true,
        'access_token' => $data['access_token']
    ];
}

/**
 * Отримує інформацію про користувача Google
 *
 * @param string $accessToken Токен доступу
 * @return array Масив з даними користувача або помилкою
 */
function getGoogleUserInfo($accessToken) {
    // Відправляємо запит на отримання даних користувача
    $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    
    // Отримуємо відповідь
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Перевіряємо відповідь
    if ($httpCode !== 200 || !$response) {
        return [
            'success' => false,
            'error' => 'Не вдалося отримати дані користувача'
        ];
    }
    
    // Декодуємо відповідь
    $data = json_decode($response, true);
    if (!isset($data['email'])) {
        return [
            'success' => false,
            'error' => 'Неправильний формат відповіді від Google'
        ];
    }
    
    return [
        'success' => true,
        'user' => $data
    ];
}

// Функція для логування OAuth процесу
function logOAuthEvent($message, $data = null) {
    $logFile = __DIR__ . '/../google_oauth_log.txt';
    $logMessage = date('Y-m-d H:i:s') . " - " . $message;
    
    if ($data !== null) {
        $logMessage .= " - " . (is_array($data) || is_object($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data);
    }
    
    file_put_contents($logFile, $logMessage . "\n", FILE_APPEND);
}

/**
 * Обробляє авторизацію через Google
 *
 * @param string $code Авторизаційний код
 * @param string $state Стан для захисту від CSRF
 * @return array Результат авторизації
 */
function processGoogleLogin($code, $state) {
    // Початок логування
    logOAuthEvent("Початок processGoogleLogin", ['code' => substr($code, 0, 10) . '...', 'state' => $state]);
    
    // Перевіряємо стан
    if (!isset($_SESSION['google_oauth_state'])) {
        logOAuthEvent("Помилка: google_oauth_state не знайдено в сесії");
        return [
            'success' => false,
            'error' => 'Помилка перевірки стану: стан не знайдено'
        ];
    }
    
    if ($state !== $_SESSION['google_oauth_state']) {
        logOAuthEvent("Помилка: стан не співпадає", ['session_state' => $_SESSION['google_oauth_state'], 'request_state' => $state]);
        return [
            'success' => false,
            'error' => 'Помилка перевірки стану: стан не співпадає'
        ];
    }
    
    // Очищаємо стан
    $session_state = $_SESSION['google_oauth_state'];
    unset($_SESSION['google_oauth_state']);
    logOAuthEvent("Стан перевірено успішно і очищено", ['session_state' => $session_state]);
    
    // Отримуємо токен доступу
    logOAuthEvent("Запит токену доступу");
    $tokenResult = getGoogleAccessToken($code);
    
    if (!$tokenResult['success']) {
        logOAuthEvent("Помилка отримання токену", $tokenResult);
        return $tokenResult;
    }
    
    logOAuthEvent("Токен доступу отримано успішно");
    
    // Отримуємо дані користувача
    logOAuthEvent("Запит інформації про користувача");
    $userInfoResult = getGoogleUserInfo($tokenResult['access_token']);
    
    if (!$userInfoResult['success']) {
        logOAuthEvent("Помилка отримання інформації про користувача", $userInfoResult);
        return $userInfoResult;
    }
    
    logOAuthEvent("Інформація про користувача отримана успішно");
    
    // Отримуємо дані користувача
    $googleUser = $userInfoResult['user'];
    
    // Перевіряємо наявність email
    if (!isset($googleUser['email'])) {
        return [
            'success' => false,
            'error' => 'Email не знайдено в даних Google'
        ];
    }
    
    // Шукаємо користувача за email
    $usersFile = __DIR__ . '/../data/users.json';
    if (!file_exists($usersFile)) {
        return [
            'success' => false,
            'error' => 'Файл користувачів не знайдено'
        ];
    }
    
    $users = json_decode(file_get_contents($usersFile), true);
    
    // Шукаємо користувача за email
    $user = null;
    foreach ($users as &$existingUser) {
        if (isset($existingUser['email']) && strtolower($existingUser['email']) === strtolower($googleUser['email'])) {
            $user = &$existingUser;
            break;
        }
    }
    
    // Якщо користувач не знайдений, реєструємо нового
    if ($user === null) {
        // Створюємо унікальний username на основі email
        $baseUsername = explode('@', $googleUser['email'])[0];
        $username = $baseUsername;
        $counter = 1;
        
        while (usernameExists($username, $users)) {
            $username = $baseUsername . $counter;
            $counter++;
        }
        
        // Визначаємо ім'я та прізвище
        $firstName = isset($googleUser['given_name']) ? $googleUser['given_name'] : '';
        $lastName = isset($googleUser['family_name']) ? $googleUser['family_name'] : '';
        
        // Створюємо запис нового користувача
        $newUser = [
            'id' => generateUniqueId(),
            'username' => $username,
            'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), // Випадковий пароль
            'email' => $googleUser['email'],
            'first_name' => $firstName,
            'last_name' => $lastName,
            'google_id' => $googleUser['sub'],
            'registration_date' => date('Y-m-d H:i:s'),
            'is_google_user' => true
        ];
        
        // Додаємо нового користувача
        $users[] = $newUser;
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Встановлюємо сесію
        $_SESSION['user_id'] = $newUser['id'];
        $_SESSION['username'] = $newUser['username'];
        
        return [
            'success' => true,
            'action' => 'register',
            'user' => $newUser
        ];
    }
    
    // Оновлюємо дані користувача
    $user['google_id'] = $googleUser['sub'];
    $user['is_google_user'] = true;
    
    // Оновлюємо ім'я та прізвище, якщо вони порожні
    if (empty($user['first_name']) && isset($googleUser['given_name'])) {
        $user['first_name'] = $googleUser['given_name'];
    }
    
    if (empty($user['last_name']) && isset($googleUser['family_name'])) {
        $user['last_name'] = $googleUser['family_name'];
    }
    
    // Оновлюємо файл користувачів
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Встановлюємо сесію
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    
    return [
        'success' => true,
        'action' => 'login',
        'user' => $user
    ];
}

// Обробляємо AJAX запит для отримання URL авторизації
if (isset($_GET['action']) && $_GET['action'] === 'get_auth_url') {
    header('Content-Type: application/json');
    $result = getGoogleAuthUrl();
    
    // Логуємо подію
    logOAuthEvent("Згенеровано URL авторизації", ['success' => $result['success']]);
    
    echo json_encode($result['success'] ? ['auth_url' => $result['auth_url']] : ['error' => $result['error']]);
    exit;
}