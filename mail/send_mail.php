<?php

// Фіксована адреса відправника
$sender_email = "info@mindflow.ovh";
$sender_name = "MindFlow Support"; // Можна змінити на будь-яке ім'я

// Змінні для повідомлень користувачу
$status_message = "";

// Обробка форми, якщо вона була відправлена
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Отримуємо дані з форми
    $to = $_POST['to'] ?? ''; // Кому
    $subject = $_POST['subject'] ?? ''; // Тема
    $message_body = $_POST['message'] ?? ''; // Текст листа

    // Перевірка, чи не порожні поля
    if (!empty($to) && !empty($subject) && !empty($message_body)) {
        // Формуємо заголовки листа
        $headers = "From: " . $sender_name . " <" . $sender_email . ">\r\n";
        $headers .= "Reply-To: " . $sender_email . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/plain; charset=UTF-8\r\n"; // Важливо для коректного відображення кирилиці

        // Відправляємо лист
        if (mail($to, $subject, $message_body, $headers)) {
            $status_message = "<p style='color: green;'>Лист успішно відправлено на " . htmlspecialchars($to) . "!</p>";
        } else {
            $status_message = "<p style='color: red;'>Помилка при відправці листа. Перевірте налаштування сервера.</p>";
        }
    } else {
        $status_message = "<p style='color: orange;'>Будь ласка, заповніть всі поля форми.</p>";
    }
}

?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Відправка пошти</title>
</head>
<body>
    <h1>Відправка листа</h1>

    <?php echo $status_message; // Виводимо статус повідомлення ?>

    <form method="post" action="">
        <label for="to">Кому (email):</label><br>
        <input type="email" id="to" name="to" required size="50"><br><br>

        <label for="subject">Заголовок:</label><br>
        <input type="text" id="subject" name="subject" required size="50"><br><br>

        <label for="message">Текст листа:</label><br>
        <textarea id="message" name="message" rows="10" cols="50" required></textarea><br><br>

        <input type="submit" value="Відправити лист">
    </form>

    <p>Відправник: `<?php echo htmlspecialchars($sender_email); ?>`</p>

</body>
</html>
