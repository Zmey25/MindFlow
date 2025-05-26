<?php
// mail_helper.php
require_once __DIR__ . '/functions.php';
/**
 * Відправляє електронний лист від імені MindFlow.ovh з фіксованим HTML-підписом.
 *
 * @param string $to_email         Адреса отримувача (наприклад, "otrymuyach@example.com").
 * @param string $subject          Тема листа.
 * @param string $message_content  Текст листа. Може бути як звичайний текст, так і HTML-код.
 *                                 Увага: Якщо $message_content походить від користувача,
 *                                 його слід очистити від потенційно шкідливого HTML/скриптів перед передачею.
 * @return bool                    Повертає true у разі успішної відправки, false у разі помилки.
 */
function sendMindFlowEmail(string $to_email, string $subject, string $message_content): bool
{
    // --- Статичні налаштування відправника ---
    $sender_email = "info@mindflow.ovh";
    $sender_name = "MindFlow Cat"; 

    // --- HTML-підпис від кішки Лапки ---
    $html_signature = '
<hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
<p style="font-family: Arial, sans-serif; font-size: 14px; color: #555; line-height: 1.5;">
    З мур-мур-повагою,<br>
    <span style="color: #888;">Ваш пухнастий секретар та помічник у <a href="https://mindflow.ovh" style="color: #007bff; text-decoration: none;">MindFlow</a></span>
</p>
<p style="font-family: Arial, sans-serif; font-size: 12px; line-height: 1.5;">
    Email: <a href="mailto:info@mindflow.ovh" style="color: #007bff; text-decoration: none;">info@mindflow.ovh</a>
</p>
<p style="font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5;">
    <em>Няв!</em> 🐾
</p>
';

    // --- Формування тіла листа ---
    // Якщо $message_content є звичайним текстом, nl2br перетворить переноси рядків на <br/>.
    // Якщо $message_content вже містить HTML, він буде доданий "як є".
    // !!! ВАЖЛИВО: Якщо $message_content походить від користувача, переконайтеся, що ви очистили його
    // від потенційно шкідливого HTML або скриптів перед передачею в цю функцію.
    $full_message_body = nl2br($message_content) . $html_signature;

    // --- Формування заголовків ---
    $headers = "From: " . $sender_name . " <" . $sender_email . ">\r\n";
    $headers .= "Reply-To: " . $sender_email . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n"; // Завжди HTML

    // --- Валідація email-адреси отримувача ---
    if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Email Error: Invalid recipient email address provided: " . $to_email);
        custom_log("Email Error: Invalid recipient email address provided: " . $to_email, 'mail');
        return false;
    }

    // --- Відправка листа ---
    if (mail($to_email, $subject, $full_message_body, $headers)) {
      custom_log("Email sent to: " . $to_email . " with subject: " . $subject, 'mail');
        return true; // Успішно відправлено
    } else {
        // Логуємо помилки для налагодження
        error_log("Email Error: Failed to send mail to " . $to_email . " with subject: " . $subject);
        custom_log("Email Error: Failed to send mail to " . $to_email . " with subject: " . $subject, 'mail');
        return false; // Помилка відправки
    }
}
