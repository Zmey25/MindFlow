<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function generateTestCaptcha(int $length = 5): string {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    $captchaCode = '';
    $max = strlen($characters) - 1;
    for ($i = 0; $i < $length; $i++) {
        $captchaCode .= $characters[random_int(0, $max)];
    }
    $_SESSION['test_captcha_code'] = strtoupper($captchaCode);
    error_log("Generated test CAPTCHA: " . $_SESSION['test_captcha_code']); // Логування
    return $_SESSION['test_captcha_code'];
}

$message = '';
$session_captcha_on_load = $_SESSION['test_captcha_code'] ?? null; // Запам'ятовуємо, що було при завантаженні

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_captcha = strtoupper(trim($_POST['captcha_input'] ?? ''));
    $captcha_in_session_at_post_check = $_SESSION['test_captcha_code'] ?? null;

    echo "<div style='background: #e0e0ff; border: 1px solid blue; padding: 10px; margin: 10px;'>";
    echo "<strong>POST DEBUG (Test Page):</strong><br>";
    echo "CAPTCHA shown on page load (from session): " . htmlspecialchars($session_captcha_on_load ?? 'N/A') . "<br>";
    echo "CAPTCHA in SESSION at POST check time: <strong>" . htmlspecialchars($captcha_in_session_at_post_check ?? 'NOT SET') . "</strong><br>";
    echo "CAPTCHA input from USER: <strong>" . htmlspecialchars($input_captcha) . "</strong><br>";

    if ($captcha_in_session_at_post_check && $input_captcha === $captcha_in_session_at_post_check) {
        $message = "CAPTCHA Correct!";
        echo "Comparison: <strong style='color:green;'>MATCHED</strong><br>";
        unset($_SESSION['test_captcha_code']); // Очистити для наступного тесту
    } else {
        $message = "CAPTCHA Incorrect!";
        echo "Comparison: <strong style='color:red;'>MISMATCHED</strong><br>";
    }
    echo "</div><br><br><br>";
    // Після POST завжди генеруємо нову для наступного показу, незалежно від результату
    generateTestCaptcha();
} else {
    // GET request
    generateTestCaptcha();
}

$captcha_to_display_now = $_SESSION['test_captcha_code'] ?? 'Error generating';
include __DIR__ . '/includes/header.php';
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Тест CAPTCHA</title>
    <style> body { font-family: sans-serif; } .captcha-display { font-size: 2em; font-weight: bold; letter-spacing: 3px; padding: 10px; background-color: #f0f0f0; border: 1px solid #ccc; display: inline-block; margin-bottom:10px; user-select: none;} </style>
</head>
<body>
    <h1>Тест CAPTCHA</h1>
    <?php if ($message): ?>
        <p style="color: <?php echo (strpos($message, 'Correct') !== false) ? 'green' : 'red'; ?>;">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <form action="test_captcha.php" method="POST">
        <div>Поточна CAPTCHA для введення:</div>
        <div class="captcha-display"><?php echo htmlspecialchars($captcha_to_display_now); ?></div>
        <div>
            <label for="captcha_input">Введіть CAPTCHA:</label>
            <input type="text" id="captcha_input" name="captcha_input" maxlength="5" required autocomplete="off" style="text-transform: uppercase;">
        </div>
        <button type="submit">Перевірити</button>
    </form>
     <p><a href="test_captcha.php">Оновити сторінку (новий GET)</a></p>
</body>
</html>