<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mail_helper.php';

$pageTitle = "Доступ до тесту";
$target_username = trim($_GET['user'] ?? '');
$message = '';
$message_type = 'info';

if (empty($target_username)) {
    include __DIR__ . '/includes/header.php';
    echo "<div class='container'><p class='message error'>Помилка: не вказано логін користувача.</p></div>";
    include __DIR__ . '/includes/footer.php';
    exit;
}

$allUsers = readJsonFile(USERS_FILE_PATH);
$targetUser = null;
$targetUserIndex = null;

if ($allUsers) {
    foreach ($allUsers as $index => $user) {
        if (isset($user['username']) && strcasecmp($user['username'], $target_username) === 0) {
            $targetUser = $user;
            $targetUserIndex = $index;
            break;
        }
    }
}

if ($targetUser === null) {
    include __DIR__ . '/includes/header.php';
    echo "<div class='container'><p class='message error'>Користувача з логіном '" . htmlspecialchars($target_username) . "' не знайдено.</p></div>";
    include __DIR__ . '/includes/footer.php';
    exit;
}

$hideTestLink = $targetUser['hide_test_link'] ?? true;

if (!$hideTestLink) {
    $inviteLink = "https://mindflow.ovh/questionnaire_other.php?target_user_id=" . urlencode($targetUser['id']);
    header("Location: " . $inviteLink);
    exit;
}

$canRequest = true;
$daysSinceLastRequest = null;
if (isset($targetUser['last_test_request_date'])) {
    try {
        $lastDate = new DateTime($targetUser['last_test_request_date']);
        $today = new DateTime();
        $interval = $today->diff($lastDate);
        $daysSinceLastRequest = $interval->days;
        if ($daysSinceLastRequest < 3) {
            $canRequest = false;
        }
    } catch (Exception $e) { /* Ігноруємо невірний формат дати */ }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_access'])) {
    if ($canRequest) {
        $allUsers[$targetUserIndex]['last_test_request_date'] = date('Y-m-d');
        if (writeJsonFile(USERS_FILE_PATH, $allUsers)) {
            $emailSent = false;
            if (!empty($targetUser['email'])) {
                $subject = "Запит на доступ до вашого тесту на MindFlow";
                $requesterInfo = isUserLoggedIn() ? "Користувач '" . $_SESSION['username'] . "'" : "Анонімний користувач";
                $content = "<p>Привіт, " . htmlspecialchars($targetUser['username']) . "!</p>";
                $content .= "<p>" . $requesterInfo . " хоче пройти тест про вас на сайті MindFlow.ovh.</p>";
                $content .= "<p>Наразі публічний доступ до вашого тесту обмежено в налаштуваннях приватності. Якщо ви хочете надати доступ, будь ласка, передайте користувачу посилання на тест або змініть налаштування приватності у вашому профілі.</p>";
                $content .= '<p><a href="https://mindflow.ovh/profile.php" style="padding:10px 15px; background-color:#3498db; color:white; text-decoration:none; border-radius:4px;">Перейти до профілю</a></p>';
                if(sendMindFlowEmail($targetUser['email'], $subject, $content)) {
                   $emailSent = true;
                }
            }
            $message = "Запит надіслано користувачу. ";
            if (!$emailSent && !empty($targetUser['email'])) {
                $message .= "Однак, сталася помилка при відправці листа.";
                $message_type = 'warning';
            } elseif (empty($targetUser['email'])) {
                $message .= "У користувача не вказана пошта, тому сповіщення не було надіслано.";
                $message_type = 'info';
            } else {
                 $message_type = 'success';
            }
            $canRequest = false; 
            $daysSinceLastRequest = 0;
        } else {
            $message = 'Не вдалося зберегти дані запиту. Спробуйте пізніше.';
            $message_type = 'error';
        }
    } else {
        $message = 'Ви вже відправляли запит нещодавно.';
        $message_type = 'error';
    }
}
include __DIR__ . '/includes/header.php';
?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <style>
        .container { max-width: 600px; margin: 40px auto; padding: 30px; background-color: #fff; box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-radius: 8px; text-align: center; }
        h1 { color: #2c3e50; margin-bottom: 15px; }
        p { color: #555; margin-bottom: 25px; }
        .btn { display: inline-block; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-size: 1em; font-weight: bold; transition: background-color 0.2s, transform 0.2s; background-color: #e67e22; color: white; }
        .btn:hover { background-color: #d35400; transform: translateY(-2px); }
        .btn[disabled] { background-color: #bdc3c7; color: #7f8c8d; cursor: not-allowed; transform: none; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 5px; text-align: center; border: 1px solid transparent; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .message.info { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
        .message.warning { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
        .cooldown-note { font-size: 0.9em; color: #7f8c8d; margin-top: 10px; }
    </style>
    
    <div class="container">
        <h1>Доступ обмежено</h1>
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <p>Користувач <strong><?php echo htmlspecialchars($target_username); ?></strong> обмежив доступ до тесту про себе у налаштуваннях приватності.</p>
        <p>Ви можете натиснути кнопку нижче, щоб попросити його надати доступ. Користувачу буде надіслано сповіщення.</p>
        
        <form action="get_test.php?user=<?php echo urlencode($target_username); ?>" method="POST">
            <button type="submit" name="request_access" class="btn" <?php echo !$canRequest ? 'disabled' : ''; ?>>Наполягти та попросити доступ</button>
        </form>
        
        <?php if (!$canRequest && $daysSinceLastRequest !== null): ?>
            <p class="cooldown-note">
                Наступний запит можна буде відправити через <?php echo 3 - $daysSinceLastRequest; ?> дні(в).
            </p>
        <?php endif; ?>
    </div>

<?php include __DIR__ . '/includes/footer.php'; ?>
