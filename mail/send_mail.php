<?php

// Фіксована адреса відправника
$sender_email = "info@mindflow.ovh";
$sender_name = "MindFlow Support"; // Можна змінити на будь-яке ім'я

// Змінні для повідомлень користувачу
$status_message = "";

// Підпис (футер) від кішки Лапки
// Ми додаємо мінімальні інлайн-стилі, щоб воно виглядало пристойно у більшості поштових клієнтів.
$cat_signature = '
<hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
<p style="font-family: Arial, sans-serif; font-size: 14px; color: #555; line-height: 1.5;">
    З мур-мур-повагою,<br>
    <strong>Лапка</strong><br>
    <span style="color: #888;">Ваш пухнастий секретар та помічник у MindFlow.ovh</span>
</p>
<p style="font-family: Arial, sans-serif; font-size: 12px; color: #777; line-height: 1.5;">
    <em>Ми допомагаємо знайти гармонію у вашій свідомості.</em>
</p>
<p style="font-family: Arial, sans-serif; font-size: 12px; line-height: 1.5;">
    Email: <a href="mailto:info@mindflow.ovh" style="color: #007bff; text-decoration: none;">info@mindflow.ovh</a><br>
    Сайт: <a href="https://mindflow.ovh" style="color: #007bff; text-decoration: none;">MindFlow.ovh</a>
</p>
<p style="font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5;">
    <em>Мяу!</em> 🐾
</p>
';


// Обробка форми, якщо вона була відправлена
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Отримуємо дані з форми
    $to = $_POST['to'] ?? ''; // Кому
    $subject = $_POST['subject'] ?? ''; // Тема
    $message_body = $_POST['message'] ?? ''; // Текст листа

    // Перевірка, чи не порожні поля
    if (!empty($to) && !empty($subject) && !empty($message_body)) {

        // Додаємо підпис до основного тексту листа
        // Використовуємо nl2br() для основного тексту, щоб зберегти переноси рядків з textarea
        $full_message = nl2br(htmlspecialchars($message_body)) . $cat_signature;

        // Формуємо заголовки листа
        $headers = "From: " . $sender_name . " <" . $sender_email . ">\r\n";
        $headers .= "Reply-To: " . $sender_email . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        // *** Змінюємо Content-type на text/html ***
        $headers .= "Content-type: text/html; charset=UTF-8\r\n"; // Важливо для коректного відображення кирилиці та HTML

        // Відправляємо лист
        if (mail($to, $subject, $full_message, $headers)) {
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
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; color: #333; margin: 20px; }
        h1 { color: #333; }
        form { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 600px; margin: 20px auto; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="email"],
        input[type="text"],
        textarea { width: calc(100% - 20px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; }
        textarea { resize: vertical; min-height: 150px; }
        input[type="submit"] { background-color: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        input[type="submit"]:hover { background-color: #0056b3; }
        p { margin-top: 10px; }
    </style>
</head>
<body>
    <h1>Відправка листа</h1>

    <?php echo $status_message; // Виводимо статус повідомлення ?>

    <form method="post" action="">
        <label for="to">Кому (email):</label>
        <input type="email" id="to" name="to" required size="50"><br>

        <label for="subject">Заголовок:</label>
        <input type="text" id="subject" name="subject" required size="50"><br>

        <label for="message">Текст листа:</label>
        <textarea id="message" name="message" rows="10" cols="50" required></textarea><br>

        <input type="submit" value="Відправити лист">
    </form>

    <p>Відправник: `<?php echo htmlspecialchars($sender_email); ?>`</p>

</body>
</html>
