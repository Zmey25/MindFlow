<?php // dashboard.php

// Найперше - перевіряємо, чи користувач залогінений
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/questionnaire_logic.php';
requireLogin(); // Якщо не залогінений, функція перенаправить на login.php

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// === Реалізація перевірки проходження опитування ===
$selfAnswers = getSelfAnswers($userId); // Отримуємо відповіді
$hasCompletedSelfSurvey = !empty($selfAnswers); // true, якщо масив відповідей не порожній
// === Кінець реалізації ===

// Генеруємо посилання для друзів
$inviteLinkBase = "https://mindflow.ovh/questionnaire_other.php"; // Базова частина URL
$inviteLinkParams = "?target_user_id=" . urlencode($userId);
$inviteLink = $inviteLinkBase . $inviteLinkParams;

// Текст для поширення (можна налаштувати)
$shareText = "Привіт! Оціни мене на платформі MindFlow, щоб я міг краще зрозуміти себе. (Не спам, не скам, не треба голосувать за маміну племінницю чи скидати 3000 до 10 ранку, але може бути чуток реклами) ";
$encodedShareText = urlencode($shareText);
$encodedInviteLink = urlencode($inviteLink);


$pageTitle = "Панель керування";
include __DIR__ . '/includes/header.php'; // Підключаємо шапку

?>

<!-- CSS для нового дизайну дашборду -->
<style>
/* Основні стилі сторінки */
.dashboard-container {
    color: #333;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.2;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.dashboard-header {
    text-align: center;
    margin-bottom: 30px; /* Зменшено відступ */
}

.dashboard-header h1 {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: #333;
}

.dashboard-header p {
    font-size: 1.2rem;
    color: #555;
    max-width: 800px;
    margin: 0 auto;
}

/* Структура гріда для розміщення блоків - зменшено вертикальний розмір */
.flow-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: auto auto auto;
    gap: 10px;
    max-width: 1000px;
    margin: 0 auto 30px;
    line-height: 0.8;
}

/* Позиціонування блоків за вашою схемою */
.grid-block-1 {
    grid-column: 1;
    grid-row: 1;
}

.grid-block-2 {
    grid-column: 2;
    grid-row: 1;
}

.grid-block-3 {
    grid-column: 1;
    grid-row: 2;
}

.grid-block-4 {
    grid-column: 2;
    grid-row: 2;
}

.grid-block-5 {
    grid-column: 1 / span 2;
    grid-row: 3;
}

/* Стилі для блоків */
.flow-block {
    background: linear-gradient(135deg, #f5f7fa 0%, #e9e9e9 100%);
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    transition: transform 0.3s, box-shadow 0.3s;
    height: 90%;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.flow-block:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
}

.content-block {
    border-left: 5px solid #5C67F2;
}

/* Стрілка - прозорий блок без стилів блоку */
.arrow-block {
    background: transparent;
    border-radius: 0;
    box-shadow: none;
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
}

.arrow-block:hover {
    transform: none;
    box-shadow: none;
}

.result-block {
    border-left: 5px solid #7986CB;
}

/* Стилі для заголовків і тексту */
.flow-block h3 {
    color: #333;
    font-size: 1.5rem;
    margin-bottom: 15px;
}

.flow-block p {
    color: #666;
    margin-bottom: 15px;
}

/* Стилі для зображень стрілок */
.arrow-image {
    width: 60%;
    height: auto;
    position: relative;
    animation: morph 4s ease-in-out infinite;
}

.arrow-down-right {
    transform: rotate(85deg) translateY(35%);
}

.arrow-down-left {
    transform: rotate(75deg) scaleY(-1) translateX(5%);
}

/* Анімація морфінгу для стрілок */
@keyframes morph {

}

/* Стилі для кнопок */
.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary {
    background: linear-gradient(135deg, #5C67F2 0%, #6f7ef2 100%);
    color: white;
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #4a54d6 0%, #5d6ce0 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(92, 103, 242, 0.3);
}

.btn-secondary {
    background: transparent;
    border: 2px solid #5C67F2;
    color: #5C67F2;
}

.btn-secondary:hover {
    background-color: rgba(92, 103, 242, 0.1);
}

.btn-info {
    background: linear-gradient(135deg, #6F7EF2 0%, #7986CB 100%);
    color: white;
    border: none;
}

.btn-info:hover {
    background: linear-gradient(135deg, #5d6ce0 0%, #6875c4 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(111, 126, 242, 0.3);
}

/* Стилі для секції поширення */
.share-section {
    background: linear-gradient(135deg, rgba(92, 103, 242, 0.1) 0%, rgba(111, 126, 242, 0.1) 100%);
    border-radius: 8px;
    padding: 15px;
    margin-top: 15px;
}

.share-section h3 {
    font-size: 1.2rem;
    margin-bottom: 10px;
}

.invite-link-container {
    display: flex;
    margin-bottom: 15px;
}

.invite-link-input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px 0 0 4px;
    font-size: 0.9rem;
}

.invite-link-container .btn {
    border-radius: 0 4px 4px 0;
    padding: 8px 12px;
}

.share-buttons {
    margin-top: 15px;
}

.share-buttons h4 {
    font-size: 1rem;
    margin-bottom: 10px;
}

.social-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.share-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px;
    margin: 2px;
    border-radius: 50%;
    width: 35px;
    height: 35px;
    color: white;
    text-decoration: none;
    font-size: 16px;
    transition: all 0.3s;
}

.share-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.facebook {
    background-color: #3b5998;
}

.telegram {
    background-color: #0088cc;
}

.twitter {
    background-color: #1da1f2;
}

.viber {
    background-color: #7360f2;
}

.whatsapp {
    background-color: #25d366;
}

/* Адаптивний дизайн */
@media (max-width: 992px) {
    .flow-grid {
        grid-template-columns: 1fr;
    }
    
    .grid-block-1 {
        grid-column: 1;
        grid-row: 1;
    }
    
    .grid-block-2 {
        display: none; /* Ховаємо стрілки на мобільних */
    }
    
    .grid-block-3 {
        display: none; /* Ховаємо стрілки на мобільних */
    }
    
    .grid-block-4 {
        grid-column: 1;
        grid-row: 2;
    }
    
    .grid-block-5 {
        grid-column: 1;
        grid-row: 3;
    }
}
</style>

<div class="dashboard-container">
    <div class="dashboard-header">
<?php if (trim(@file_get_contents('data/dashboard_warning.txt'))) echo "<div class='message warning'>" . nl2br(file_get_contents('data/dashboard_warning.txt')) . "</div>"; ?>
        <!-- <h1>Ласкаво просимо, <?php echo htmlspecialchars($username); ?>!</h1> -->
        <p>MindFlow допоможе вам краще зрозуміти, як ви сприймаєте себе та як вас бачать інші.</p>
    </div>

    <!-- Новий макет з 5 блоками точно за схемою -->
    <div class="flow-grid">
        <!-- Блок 1: Пройти тест (ліворуч, перший рядок) -->
        <div class="flow-block content-block grid-block-1">
            <h3>Ваш тест</h3>
            <p>Дайте відповіді на запитання про ваші якості та особливості.</p>
            
            <?php if (!$hasCompletedSelfSurvey): ?>
                <a href="questionnaire_self.php" class="btn btn-primary">Пройти опитування про себе</a>
            <?php else: ?>
                <a href="questionnaire_self.php" class="btn btn-secondary">Редагувати свої відповіді</a>
                <p><small>Ви вже пройшли опитування про себе.</small></p>
            <?php endif; ?>
        </div>
        
        <!-- Блок 2: Стрілка верхня (праворуч, перший рядок) -->
        <div class="arrow-block grid-block-2">
            <img src="assets/images/arrow.png" alt="Стрілка" class="arrow-image arrow-down-right">
        </div>
        
        <!-- Блок 3: Стрілка нижня (ліворуч, другий рядок) -->
        <div class="arrow-block grid-block-3">
            <img src="assets/images/arrow.png" alt="Стрілка" class="arrow-image arrow-down-left">
        </div>
        
        <!-- Блок 4: Дати пройти тест іншим (праворуч, другий рядок) -->
        <div class="flow-block content-block grid-block-4">
            <h3>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Бачення інших</h3>
            <p>Поділіться посиланням з друзями та отримайте їх оцінку.</p>
            
            <div class="share-section">
                <h3>Поділіться посиланням:</h3>
                <div class="invite-link-container">
                    <input type="text" value="<?php echo htmlspecialchars($inviteLink); ?>" readonly onclick="this.select();" class="invite-link-input">
                    <button onclick="copyLinkToClipboard('<?php echo htmlspecialchars($inviteLink); ?>')" class="btn btn-primary">Копіювати</button>
                </div>
                <p id="copy-status" style="display: none; color: green; margin: 5px 0;">Посилання скопійовано!</p>

                <!-- Кнопки соціальних мереж -->
                <div class="share-buttons">
                    <h4>Поділитися через:</h4>
                    <div class="social-buttons">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $encodedInviteLink; ?>" target="_blank" class="share-btn facebook" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://t.me/share/url?url=<?php echo $encodedInviteLink; ?>&text=<?php echo $encodedShareText; ?>" target="_blank" class="share-btn telegram" title="Telegram">
                            <i class="fab fa-telegram-plane"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo $encodedInviteLink; ?>&text=<?php echo $encodedShareText; ?>" target="_blank" class="share-btn twitter" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="viber://forward?text=<?php echo $encodedShareText; ?>%20<?php echo $encodedInviteLink; ?>" target="_blank" class="share-btn viber" title="Viber">
                            <i class="fab fa-viber"></i>
                        </a>
                        <a href="whatsapp://send?text=<?php echo $encodedShareText; ?>%20<?php echo $encodedInviteLink; ?>" target="_blank" class="share-btn whatsapp" title="WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <button id="shareButton" class="share-btn twitter" title="Поділитися">
                            <i class="fas fa-share-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Блок 5: Результат (на всю ширину, третій рядок) -->
        <div class="flow-block result-block grid-block-5">
            <h3>Результат</h3>
            <p>Порівняйте своє бачення з оцінками інших та зрозумійте, як вас сприймають оточуючі.</p>
            
            <?php if ($hasCompletedSelfSurvey): ?>
                <a href="results.php" class="btn btn-info">Переглянути мої результати</a>
            <?php else: ?>
                <span class="text-muted">Перегляд результатів буде доступний після проходження опитування про себе.</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function copyLinkToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        console.log('Async: Copying to clipboard was successful!');
        const status = document.getElementById('copy-status');
        status.style.display = 'inline';
        setTimeout(() => { status.style.display = 'none'; }, 2000);
    }, function(err) {
        console.error('Async: Could not copy text: ', err);
        try {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-9999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            const status = document.getElementById('copy-status');
            status.style.display = 'inline';
            setTimeout(() => { status.style.display = 'none'; }, 2000);
        } catch (e) {
            console.error('Fallback: Oops, unable to copy', e);
            alert('Не вдалося скопіювати посилання автоматично. Будь ласка, виділіть та скопіюйте його вручну.');
        }
    });
}

// JavaScript для Web Share API
const shareButton = document.getElementById('shareButton');

// Дані для поширення
const shareData = {
  title: "Портал MindFlow",
  text: <?php echo json_encode($shareText); ?>,
  url: <?php echo json_encode($inviteLink); ?>
};

// Додаємо обробник кліку на кнопку
if (shareButton) {
  shareButton.addEventListener('click', () => {
    if (navigator.share) {
      navigator.share(shareData)
        .then(() => console.log('Контент успішно поширено!'))
        .catch((error) => console.error('Помилка поширення:', error));
    } else {
      console.log('Web Share API не підтримується у цьому браузері.');
      alert("Функція 'Поділитися' не підтримується вашим браузером.");
    }
  });
}
</script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<?php
include __DIR__ . '/includes/footer.php'; // Підключаємо підвал
?>