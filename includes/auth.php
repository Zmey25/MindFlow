<?php // includes/auth.php

require_once __DIR__ . '/functions.php'; // Підключаємо наші функції

const USERS_FILE_PATH = __DIR__ . '/../data/users.json'; // Шлях до файлу користувачів

define('USERNAME_MIN_LENGTH', 3);
define('USERNAME_MAX_LENGTH', 25);
define('PASSWORD_MIN_LENGTH', 6);
define('PASSWORD_MAX_LENGTH', 72); // bcrypt має обмеження ~72 символи


/**
 * Реєструє нового користувача.
 *
 * @param string $username Ім'я користувача.
 * @param string $password Пароль.
 * @param string $email Email користувача.
 * @param string $first_name Ім'я (необов'язково).
 * @param string $last_name Прізвище (необов'язково).
 * @return array Масив з результатом: ['success' => bool, 'message' => string, 'userId' => string|null]
 */
function registerUser(string $username, string $password, string $email, string $first_name = '', string $last_name = ''): array {
    $users = readJsonFile(USERS_FILE_PATH);
    if ($users === false) {
        error_log("Не вдалося прочитати файл користувачів: " . USERS_FILE_PATH);
        return ['success' => false, 'message' => 'Сталася системна помилка. Спробуйте пізніше.', 'userId' => null];
    }


    // 1. Валідація
    $username = trim($username);
    $email = trim($email); // Обрізаємо пробіли і для email
    // $first_name та $last_name вже обрізані при отриманні в register.php

    if (empty($username) || empty($password) || empty($email)) {
        return ['success' => false, 'message' => 'Ім\'я користувача, пароль та email є обов\'язковими.', 'userId' => null];
    }
    if (mb_strlen($username) < USERNAME_MIN_LENGTH || mb_strlen($username) > USERNAME_MAX_LENGTH) {
         return ['success' => false, 'message' => 'Ім\'я користувача повинно містити від ' . USERNAME_MIN_LENGTH . ' до ' . USERNAME_MAX_LENGTH . ' символів.', 'userId' => null];
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
         return ['success' => false, 'message' => 'Ім\'я користувача може містити лише латинські літери, цифри та знак підкреслення (_).', 'userId' => null];
    }
    if (strlen($password) < PASSWORD_MIN_LENGTH || strlen($password) > PASSWORD_MAX_LENGTH) {
        return ['success' => false, 'message' => 'Пароль має містити від ' . PASSWORD_MIN_LENGTH . ' до ' . PASSWORD_MAX_LENGTH . ' символів.', 'userId' => null];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Будь ласка, введіть дійсну адресу електронної пошти.', 'userId' => null];
    }
    if (mb_strlen($email) > 100) { // Обмеження довжини email
        return ['success' => false, 'message' => 'Адреса електронної пошти не повинна перевищувати 100 символів.', 'userId' => null];
    }

    if (!empty($first_name) && mb_strlen($first_name) > 50) {
         return ['success' => false, 'message' => 'Ім\'я не повинно перевищувати 50 символів.', 'userId' => null];
    }
    if (!empty($last_name) && mb_strlen($last_name) > 50) {
         return ['success' => false, 'message' => 'Прізвище не повинно перевищувати 50 символів.', 'userId' => null];
    }

    // 2. Перевірка на унікальність (реєстронезалежно для username та email)
    $lowerUsername = strtolower($username);
    $lowerEmail = strtolower($email);
    foreach ($users as $user) {
        if (isset($user['username']) && strtolower($user['username']) === $lowerUsername) {
            return ['success' => false, 'message' => 'Користувач з таким іменем вже існує.', 'userId' => null];
        }
        if (isset($user['email']) && strtolower($user['email']) === $lowerEmail) {
            return ['success' => false, 'message' => 'Користувач з такою адресою електронної пошти вже існує.', 'userId' => null];
        }
    }

    // 3. Хешування пароля
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        error_log("Помилка хешування пароля для користувача: " . $username);
        return ['success' => false, 'message' => 'Сталася помилка під час реєстрації. Спробуйте пізніше.', 'userId' => null];
    }

    // 4. Створення нового користувача
    $userId = generateUniqueId();
    $newUser = [
        'id' => $userId,
        'username' => $username,
        'password_hash' => $passwordHash,
        'email' => $email, // Зберігаємо email
        'first_name' => $first_name,
        'last_name' => $last_name,
        'registration_date' => date('Y-m-d H:i:s'), // Дата реєстрації
        'is_google_user' => false, // Для звичайної реєстрації
        'hide_results' => true, // Значення за замовчуванням (як у profile.php)
        'custom_question' => '' // Порожнє значення за замовчуванням
    ];

    // 5. Додавання користувача до масиву та збереження
    $users[] = $newUser;

    if (writeJsonFile(USERS_FILE_PATH, $users)) {
        return ['success' => true, 'message' => 'Реєстрація успішна!', 'userId' => $userId];
    } else {
        error_log("Не вдалося записати нового користувача у файл: " . USERS_FILE_PATH);
        return ['success' => false, 'message' => 'Сталася помилка під час збереження даних. Спробуйте пізніше.', 'userId' => null];
    }
}

/**
 * Перевіряє логін та пароль користувача.
 *
 * @param string $username Ім'я користувача.
 * @param string $password Пароль.
 * @return array Масив з результатом: ['success' => bool, 'message' => string, 'user' => array|null]
 */
function loginUser(string $username, string $password): array {
    $users = readJsonFile(USERS_FILE_PATH);
    if ($users === false) {
        error_log("Не вдалося прочитати файл користувачів для входу: " . USERS_FILE_PATH);
        return ['success' => false, 'message' => 'Помилка системи. Спробуйте пізніше.', 'user' => null];
    }

     if (empty(trim($username)) || empty(trim($password))) {
        return ['success' => false, 'message' => 'Введіть ім\'я користувача та пароль.', 'user' => null];
    }

    foreach ($users as $user) {
        if (isset($user['username']) && strtolower($user['username']) === strtolower(trim($username))) {
            if (isset($user['password_hash']) && password_verify($password, $user['password_hash'])) {
                unset($user['password_hash']);
                return ['success' => true, 'message' => 'Вхід успішний!', 'user' => $user];
            } else {
                return ['success' => false, 'message' => 'Невірне ім\'я користувача або пароль.', 'user' => null];
            }
        }
    }
    return ['success' => false, 'message' => 'Невірне ім\'я користувача або пароль.', 'user' => null];
}

/**
* Перевіряє, чи користувач авторизований.
* @return bool True, якщо користувач авторизований, інакше false.
*/
function isUserLoggedIn(): bool {
   if (session_status() == PHP_SESSION_NONE) {
       session_start(); // Захисний виклик, якщо сесія ще не стартувала
   }
   return isset($_SESSION['user_id']);
}


/**
* Вимагає, щоб користувач був авторизований.
* Якщо не авторизований, перенаправляє на сторінку логіну.
* @param string $redirectUrl URL для перенаправлення, за замовчуванням 'login.php')
*/
function requireLogin(string $redirectUrl = 'login.php'): void {
    if (!isUserLoggedIn()) { // isUserLoggedIn забезпечить старт сесії, якщо потрібно
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header("Location: " . $redirectUrl);
            exit;
    }
}

/**
 * Знаходить користувача за його ID.
 *
 * @param string $userId ID користувача для пошуку.
 * @return array|null Дані користувача (без хешу пароля) або null, якщо не знайдено.
 */
function findUserById(string $userId): ?array {
    $users = readJsonFile(USERS_FILE_PATH);
    if ($users === false) return null;
    foreach ($users as $user) {
        if (isset($user['id']) && $user['id'] === $userId) {
            unset($user['password_hash']);
            return $user;
        }
    }
    return null;
}

/**
 * Перевіряє, чи існує користувач з заданим ID.
 *
 * @param string $userId ID користувача для перевірки.
 * @return bool True, якщо користувач існує, інакше false.
 */
function isUserValid(string $userId): bool {
    return findUserById($userId) !== null;
}

/**
 * Генерує простий CAPTCHA код і зберігає його в сесії.
 * @param int $length Довжина коду.
 * @return string Згенерований код.
 */
function generateCaptchaCode(int $length = 5): string {
    if (session_status() == PHP_SESSION_NONE) {
        session_start(); // Захисний виклик, якщо сесія ще не стартувала
    }
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    $captchaCode = '';
    $max = strlen($characters) - 1;
    for ($i = 0; $i < $length; $i++) {
        $captchaCode .= $characters[random_int(0, $max)];
    }
    $_SESSION['captcha_code'] = strtoupper($captchaCode); // Зберігаємо у верхньому регістрі
    return $captchaCode;
}

/**
 * Знаходить користувача за його іменем (реєстронезалежно).
 *
 * @param string $username Ім'я користувача для пошуку.
 * @return array|null Дані користувача (без хешу пароля) або null, якщо не знайдено.
 */
function findUserByUsername(string $username): ?array {
    $users = readJsonFile(USERS_FILE_PATH);
    if ($users === false) return null;
    $lowerUsername = strtolower(trim($username));
    foreach ($users as $user) {
        if (isset($user['username']) && strtolower($user['username']) === $lowerUsername) {
            unset($user['password_hash']);
            return $user;
        }
    }
    return null;
}

/**
 * Перевіряє, чи є поточний авторизований користувач адміністратором.
 * @return bool True, якщо користувач є адміністратором, інакше false.
 */
function isUserAdmin(): bool {
    if (!isUserLoggedIn()) { // isUserLoggedIn забезпечить старт сесії, якщо потрібно
        return false;
    }
    $currentUserId = $_SESSION['user_id'] ?? null;
    if ($currentUserId === null) {
         return false;
    }
    $adminsFilePath = __DIR__ . '/../data/admins.json';
    if (!file_exists($adminsFilePath)) {
        return false;
    }
    $adminData = readJsonFile($adminsFilePath);
    if (empty($adminData) || !isset($adminData['admin_ids']) || !is_array($adminData['admin_ids'])) {
         return false;
    }
    $adminUserIds = $adminData['admin_ids'];
    return in_array($currentUserId, $adminUserIds);
}
?>