<?php
// logout.php
session_start(); // Потрібно для доступу до сесії

// Видаляємо всі змінні сесії
$_SESSION = array();

// Якщо використовується cookie для сесії, видаляємо її
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Знищуємо сесію
session_destroy();

// Перенаправляємо на сторінку входу
header("Location: login.php?logout=success"); // Додамо параметр для можливого повідомлення
exit;
?>