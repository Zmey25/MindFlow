<?php // questionnaire_self.php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/questionnaire_logic.php';
require_once __DIR__ . '/includes/trait_calculator.php'; // Додано для перерахунку
require_once __DIR__ . '/includes/badge_calculator.php'; // Додано для перерахунку
require_once __DIR__ . '/includes/env-loader.php';      // Додано для .env
loadEnv(__DIR__ . '/../.env');                         // Завантажуємо змінні середовища

requireLogin(); // Доступ тільки для залогінених

// Обробка дії "Почати заново" - очищення сесії
if (isset($_GET['action']) && $_GET['action'] === 'clear_session') {
    unset($_SESSION['page_self_answers']);
    header('Location: questionnaire_self.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? null; // Важливо отримати username для збереження та перерахунку
$pageTitle = "Опитування про себе";
$message = '';
$message_type = ''; // 'success' or 'error'

// Завантажуємо структуру питань
$allCategories = loadQuestions();
if (empty($allCategories)) {
    $message = "Помилка: Не вдалося завантажити питання. Спробуйте пізніше.";
    $message_type = 'error';
}

// --- Категорії та навігація ---
$currentCategoryIndex = isset($_GET['category_index']) ? (int)$_GET['category_index'] : 0;
$totalCategories = !empty($allCategories) ? count($allCategories) : 0;

if (!empty($allCategories) && ($currentCategoryIndex < 0 || $currentCategoryIndex >= $totalCategories)) {
    if ($totalCategories > 0) {
        header('Location: questionnaire_self.php?category_index=0');
        exit;
    } else {
        $currentCategoryIndex = 0;
    }
}

// --- Робота з відповідями у сесії ---
if (!isset($_SESSION['page_self_answers'])) {
    $dbAnswers = getSelfAnswers($userId);
    $_SESSION['page_self_answers'] = $dbAnswers ?: [];
}
$isEditing = !empty(getSelfAnswers($userId));
$formAnswers = $_SESSION['page_self_answers'];

// Обробка POST-запиту
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($allCategories)) {
    $submittedCategoryIndex = isset($_POST['current_category_index']) ? (int)$_POST['current_category_index'] : 0;
    
    if ($submittedCategoryIndex < 0 || $submittedCategoryIndex >= $totalCategories) {
        header('Location: questionnaire_self.php?category_index=0');
        exit;
    }

    $currentCategoryData = $allCategories[$submittedCategoryIndex];
    $submittedAnswersForCategory = [];
    $isValid = true;

    foreach ($currentCategoryData['questions'] as $question) {
        $questionId = $question['questionId'];
        if (isset($_POST['answers'][$questionId])) {
            $answerValue = filter_var(
                $_POST['answers'][$questionId],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => $question['scale']['min'], 'max_range' => $question['scale']['max']]]
            );
            if ($answerValue === false) {
                $isValid = false;
                $message = "Будь ласка, дайте відповідь на всі питання в поточній категорії, використовуючи шкалу.";
                $message_type = 'error';
                $formAnswers[$questionId] = $_POST['answers'][$questionId]; 
            } else {
                $submittedAnswersForCategory[$questionId] = $answerValue;
            }
        } else {
            $isValid = false;
            $message = "Будь ласка, дайте відповідь на всі питання в поточній категорії.";
            $message_type = 'error';
        }
    }

    if ($isValid) {
        $_SESSION['page_self_answers'] = array_merge($_SESSION['page_self_answers'] ?? [], $submittedAnswersForCategory);
        $formAnswers = $_SESSION['page_self_answers'];

        if (isset($_POST['next_category'])) {
            $nextCategoryIndex = $submittedCategoryIndex + 1;
            if ($nextCategoryIndex < $totalCategories) {
                header('Location: questionnaire_self.php?category_index=' . $nextCategoryIndex);
                exit;
            } else {
                 header('Location: questionnaire_self.php?category_index=' . $submittedCategoryIndex);
                 exit;
            }
        } elseif (isset($_POST['save_all'])) {
            if (empty($username)) { // Перевірка чи є username, потрібен для saveSelfAnswers та recalculations
                 $message = "Помилка: Ім'я користувача не визначено. Неможливо зберегти дані.";
                 $message_type = 'error';
                 $currentCategoryIndex = $submittedCategoryIndex;
            } elseif (saveSelfAnswers($userId, $username, $_SESSION['page_self_answers'])) {
                // --- Початок перерахунку трітів та бейджів ---
                $userDataForRecalc = loadUserData($username);
                if ($userDataForRecalc) {
                    $changesMade = false;
                    if (!isset($userDataForRecalc['achievements'])) $userDataForRecalc['achievements'] = [];
                    if (!isset($userDataForRecalc['badges_summary'])) $userDataForRecalc['badges_summary'] = [];
                    if (!isset($userDataForRecalc['self'])) $userDataForRecalc['self'] = null;
                    if (!isset($userDataForRecalc['others'])) $userDataForRecalc['others'] = [];


                    // 1. Розрахунок Трітів
                    $traitsRecalcResult = calculateEarnedTraits($username);
                    if ($traitsRecalcResult['success']) {
                        $existingTraitIds = is_array($userDataForRecalc['achievements']) ? array_column($userDataForRecalc['achievements'], 'id') : [];
                        $newTraitIds = $traitsRecalcResult['earned_ids'];
                        sort($existingTraitIds); sort($newTraitIds);
                        if ($existingTraitIds !== $newTraitIds) {
                            $userDataForRecalc['achievements'] = $traitsRecalcResult['earned_traits'];
                            $changesMade = true;
                        }
                    } else {
                        error_log("questionnaire_self.php: Помилка розрахунку трітів для '{$username}'. " . ($traitsRecalcResult['message'] ?? 'Невідома помилка'));
                    }

                    // 2. Розрахунок Бейджів
                    $badgesRecalcResult = calculateUserBadges($username);
                    if ($badgesRecalcResult['success']) {
                        $currentBadgesSummary = $userDataForRecalc['badges_summary'] ?? [];
                        $newBadgesSummary = $badgesRecalcResult['badges_summary'];

                        $currentBadgesMap = [];
                        if (is_array($currentBadgesSummary)) {
                            foreach ($currentBadgesSummary as $badge) {
                                if (isset($badge['badgeId'])) $currentBadgesMap[$badge['badgeId']] = $badge['score'] ?? null;
                            }
                        }
                        $newBadgesMap = [];
                        if (is_array($newBadgesSummary)) {
                            foreach ($newBadgesSummary as $badge) {
                                if (isset($badge['badgeId'])) $newBadgesMap[$badge['badgeId']] = $badge['score'] ?? null;
                            }
                        }
                        ksort($currentBadgesMap); ksort($newBadgesMap);

                        if ($currentBadgesMap !== $newBadgesMap) {
                            $userDataForRecalc['badges_summary'] = $newBadgesSummary;
                            $changesMade = true;
                        }
                    } else {
                        error_log("questionnaire_self.php: Помилка розрахунку бейджів для '{$username}'. " . ($badgesRecalcResult['message'] ?? 'Невідома помилка'));
                    }

                    if ($changesMade) {
                        if (!saveUserData($username, $userDataForRecalc)) {
                            error_log("questionnaire_self.php: Помилка збереження оновлених даних (тріти/бейджі) для '{$username}'.");
                        }
                    }
                } else {
                    error_log("questionnaire_self.php: Не вдалося завантажити дані користувача '{$username}' для оновлення трітів та бейджів.");
                }
                // --- Кінець перерахунку трітів та бейджів ---

                $_SESSION['flash_message'] = 'Ваші відповіді успішно збережено!';
                $_SESSION['flash_message_type'] = 'success';
                unset($_SESSION['page_self_answers']); 
                header('Location: dashboard.php');
                exit;
            } else {
                $message = "Сталася помилка під час збереження ваших відповідей. Спробуйте ще раз.";
                $message_type = 'error';
                $currentCategoryIndex = $submittedCategoryIndex;
            }
        }
    } else {
        $currentCategoryIndex = $submittedCategoryIndex;
    }
}

$categoryToDisplay = null;
if (!empty($allCategories) && $totalCategories > 0 && isset($allCategories[$currentCategoryIndex])) {
    $categoryToDisplay = $allCategories[$currentCategoryIndex];
} else if (empty($allCategories) && $message_type !== 'error') {
    $message = $message ?: "Немає доступних питань для відображення.";
    $message_type = $message_type ?: 'info';
}

include __DIR__ . '/includes/header.php';
?>

<h1><?php echo $isEditing ? "Редагування відповідей про себе" : "Опитування про себе"; ?></h1>

<?php
$minScaleText = "1"; $maxScaleText = "7";
if (!empty($allCategories) && isset($allCategories[0]['questions'][0]['scale'])) {
    $minScaleText = $allCategories[0]['questions'][0]['scale']['min'] ?? "1";
    $maxScaleText = $allCategories[0]['questions'][0]['scale']['max'] ?? "7";
}
?>

<?php if ($totalCategories > 0 && $categoryToDisplay): ?>
<p>Категорія <?php echo $currentCategoryIndex + 1; ?> з <?php echo $totalCategories; ?>.
Будь ласка, оцініть себе за наступними критеріями, використовуючи шкалу (в більшості випадків) від <?php echo $minScaleText; ?> до <?php echo $maxScaleText; ?>.
Зауважте, що відповіді для поточної категорії зберігаються тимчасово при переході до наступної. Для фінального збереження всіх відповідей натисніть відповідну кнопку на останній категорії.</p>
<?php elseif (empty($allCategories) && $message_type !== 'error' && !$message): ?>
<p>Будь ласка, оцініть себе за наступними критеріями. Натисніть кнопку нижче, щоб зберегти відповіді.</p>
<?php endif; ?>

<div id="intro-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: white; padding: 25px; border: 1px solid #ccc; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 1000; width: 90%; max-width: 550px; text-align: center;">
  <h3 style="margin-top: 0; color: #333;">Важливе нагадування</h3>
  <p id="intro-modal-text" style="text-align: justify; margin-bottom: 15px; color: #555; line-height: 1.6;">
    Пам'ятайте про різницю між "<span style="color: #007bff; font-weight: bold;">Людина, якою я хочу бути</span>" та "<span style="color: #dc3545; font-weight: bold;">Людина, яка я є насправді</span>".
    <br><br>
    Це опитування допоможе вам краще зрозуміти <span style="color: #28a745; font-weight: bold;">себе справжнього</span>. Будь ласка, відповідайте <span style="color: #6f42c1; font-weight: bold;">чесно</span> та <span style="color: #6f42c1; font-weight: bold;">відверто</span>. Тут немає "правильних" чи "неправильних" відповідей.
  </p>
  <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
  <p style="text-align: justify; margin-bottom: 15px; color: #555; line-height: 1.6;">
    Біля кожної шкали можемо ввімкнути кнопки <i class="fas fa-thumbs-down" style="color: #dc3545;"></i> "Хочу мати менше" та <i class="fas fa-thumbs-up" style="color: #198754;"></i> "Хочу мати більше". Вони <span style="font-weight: bold;">не впливають на результат</span> і <span style="font-weight: bold;">не зберігаються</span>. Відповідно, вони не обов'язкові.
    Це лише <span style="font-weight: bold;">візуальна підказка для вас</span>: вона допоможе замислитись, чи поточний стан вас влаштовує, чи ви хотіли б щось змінити. Це може допомогти бути більш чесним у самооцінці.
  </p>
  <p style="font-weight: bold; margin-bottom: 20px; color: #333;">Бажаєте використовувати ці кнопки-підказки?</p>
  <div class="modal-button-group">
    <button id="modal-enable-wish-buttons" class="btn btn-primary" style="padding: 10px 20px; font-size: 16px;">Так, увімкнути</button>
    <button id="modal-disable-wish-buttons" class="btn btn-secondary" style="padding: 10px 20px; font-size: 16px;">Ні, сховати</button>
  </div>
</div>

<?php if ($message): ?>
    <div class="message <?php echo htmlspecialchars($message_type); ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if ($categoryToDisplay): ?>
<form action="questionnaire_self.php?category_index=<?php echo $currentCategoryIndex; ?>" method="POST" class="questionnaire-form">
    <input type="hidden" name="current_category_index" value="<?php echo $currentCategoryIndex; ?>">
    <fieldset class="category-fieldset">
        <legend><?php echo htmlspecialchars($categoryToDisplay['categoryName']); ?></legend>

        <?php foreach ($categoryToDisplay['questions'] as $question):
             $questionId = $question['questionId'];
             $currentValue = $formAnswers[$questionId] ?? round(($question['scale']['min'] + $question['scale']['max']) / 2);
        ?>
            <div class="question-block">
                <label for="q_<?php echo $questionId; ?>" class="question-text">
                    <?php echo htmlspecialchars($question['q_self']); ?>
                </label>
                <div class="range-slider">
                    <button type="button" class="btn-wish btn-wish-less" title="Хочу менше" data-wish="less">
                        <i class="fas fa-thumbs-down"></i> <span class="wish-text">Хочу менше</span>
                    </button>
                    <span class="range-label min"><?php echo htmlspecialchars($question['scale']['minLabel']); ?> (<?php echo $question['scale']['min']; ?>)</span>
                    <input
                        type="range"
                        id="q_<?php echo $questionId; ?>"
                        name="answers[<?php echo $questionId; ?>]"
                        min="<?php echo $question['scale']['min']; ?>"
                        max="<?php echo $question['scale']['max']; ?>"
                        value="<?php echo htmlspecialchars($currentValue); ?>"
                        step="1"
                        oninput="updateRangeValue(this)"
                        required
                    >
                    <span class="range-label max">(<?php echo $question['scale']['max']; ?>) <?php echo htmlspecialchars($question['scale']['maxLabel']); ?></span>
                    <button type="button" class="btn-wish btn-wish-more" title="Хочу більше" data-wish="more">
                        <i class="fas fa-thumbs-up"></i> <span class="wish-text">Хочу більше</span>
                    </button>
                    <span class="range-value" id="val_<?php echo $questionId; ?>"><?php echo htmlspecialchars($currentValue); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </fieldset>

    <div class="form-navigation-buttons" style="margin-top: 20px; display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div> <!-- Group for Prev/Next/Save -->
            <?php if ($currentCategoryIndex > 0): ?>
                <a href="questionnaire_self.php?category_index=<?php echo $currentCategoryIndex - 1; ?>" class="btn btn-primary">Попередня категорія</a>
            <?php endif; ?>

            <?php if ($currentCategoryIndex < $totalCategories - 1): ?>
                <button type="submit" name="next_category" class="btn btn-primary">Наступна категорія</button>
            <?php else: ?>
                <button type="submit" name="save_all" class="btn btn-primary"><?php echo $isEditing ? "Оновити всі відповіді" : "Зберегти всі відповіді"; ?></button>
            <?php endif; ?>
        </div>
        
        <div> <!-- Group for Cancel/Start Over -->
            <a href="questionnaire_self.php?action=clear_session" class="btn btn-secondary" onclick="return confirm('Це очистить всі ваші поточні відповіді в цьому опитуванні і почне його заново. Продовжити?');">Почати заново</a>
            <a href="dashboard.php" class="btn btn-secondary" onclick="return confirm('Якщо ви скасуєте, незбережені зміни для поточної категорії буде втрачено, але відповіді з попередніх категорій залишаться в сесії. Продовжити?');">Скасувати</a>
        </div>
    </div>
</form>

<?php elseif (empty($allCategories) && $message_type === 'error'): ?>
    <?php // Повідомлення про помилку завантаження питань вже виведено вище, якщо $message встановлено ?>
<?php elseif (empty($allCategories) && !$message): ?>
    <p>На жаль, зараз немає доступних питань.</p>
<?php endif; ?>

<script>
  function updateRangeValue(element) {
      const valueSpan = document.getElementById('val_' + element.id.substring(2));
      if (valueSpan) {
          valueSpan.textContent = element.value;
      }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('intro-modal');
    const enableButton = document.getElementById('modal-enable-wish-buttons');
    const disableButton = document.getElementById('modal-disable-wish-buttons');

    document.body.classList.add('wish-buttons-hidden'); 

    const urlParams = new URLSearchParams(window.location.search);
    const categoryIndexParam = urlParams.get('category_index');

    if (modal && (categoryIndexParam === null || categoryIndexParam === '0')) {
      if (!localStorage.getItem('introModalDismissedSelf')) {
          modal.style.display = 'block';
      }
    }

    function dismissModalAndStorePreference() {
        if (modal) modal.style.display = 'none';
        localStorage.setItem('introModalDismissedSelf', 'true');
    }

    if (enableButton) {
      enableButton.onclick = function() {
        document.body.classList.remove('wish-buttons-hidden');
        dismissModalAndStorePreference();
      }
    }

    if (disableButton) {
      disableButton.onclick = function() {
        dismissModalAndStorePreference();
      }
    }

    document.querySelectorAll('.questionnaire-form input[type="range"]').forEach(input => {
         updateRangeValue(input);
    });

    document.querySelectorAll('.btn-wish').forEach(button => {
        button.addEventListener('click', function() {
            if (document.body.classList.contains('wish-buttons-hidden')) {
                return;
            }
            const sliderDiv = this.closest('.range-slider');
            if (!sliderDiv) return;

            const isAlreadyActive = this.classList.contains('active');
            const lessBtn = sliderDiv.querySelector('.btn-wish-less');
            const moreBtn = sliderDiv.querySelector('.btn-wish-more');

            if (lessBtn) lessBtn.classList.remove('active');
            if (moreBtn) moreBtn.classList.remove('active');

            if (!isAlreadyActive) {
                this.classList.add('active');
            }
        });
    });
  });
</script>

<?php
include __DIR__ . '/includes/footer.php';
?>