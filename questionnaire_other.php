<?php // questionnaire_other.php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/questionnaire_logic.php';
require_once __DIR__ . '/includes/trait_calculator.php';
require_once __DIR__ . '/includes/badge_calculator.php';
require_once __DIR__ . '/includes/env-loader.php'; // Для .env
require_once __DIR__ . '/includes/functions.php';
loadEnv(__DIR__ . '/../.env');                    // Завантажуємо змінні середовища

requireLogin();

define('ANALYSIS_TRIGGER_FREQUENCY', 5); // Кожні N унікальних оцінок запускати аналіз

$respondentUserId = $_SESSION['user_id'];
$respondentUsername = $_SESSION['username'];

$targetUserId = $_GET['target_user_id'] ?? null;
$targetUser = null;
$targetUserDisplayName = '';
$targetUsername = '';
$error_message_page = '';

if ($targetUserId === null) {
    $error_message_page = "Не вказано ID користувача, про якого потрібно пройти опитування.";
} elseif ($targetUserId === $respondentUserId) {
    $error_message_page = "Ви не можете відповідати на питання про себе за цим посиланням. Скористайтеся своєю панеллю керування.";
} else {
    $targetUser = findUserById($targetUserId);
    if ($targetUser === null) {
        $error_message_page = "Користувача з ID '".htmlspecialchars($targetUserId)."' не знайдено.";
        $targetUserId = null;
    } else {
        $targetUsername = $targetUser['username'];
        if (!empty($targetUser['first_name']) && !empty($targetUser['last_name'])) {
            $targetUserDisplayName = trim(htmlspecialchars($targetUser['first_name']) . ' ' . htmlspecialchars($targetUser['last_name']));
        } else {
            $targetUserDisplayName = htmlspecialchars($targetUser['username']);
        }
    }
}

if ($error_message_page) {
    $pageTitle = "Помилка опитування";
    include __DIR__ . '/includes/header.php';
    echo "<h1>{$pageTitle}</h1>";
    echo "<div class='message error'>" . htmlspecialchars($error_message_page) . "</div>";
    echo "<p><a href='dashboard.php'>Повернутися на панель керування</a></p>";
    include __DIR__ . '/includes/footer.php';
    exit;
}

$sessionAnswersKey = 'page_other_answers_for_' . $targetUserId;

if (isset($_GET['action']) && $_GET['action'] === 'clear_session') {
    unset($_SESSION[$sessionAnswersKey]);
    header('Location: questionnaire_other.php?target_user_id=' . urlencode($targetUserId));
    exit;
}

const STATIC_OPEN_QUESTIONS = [
    'open_q_strength' => 'Щоб ви хотіли, щоб ця людина ПРОДОВЖУВАЛА робити?',
    'open_q_weakness' => 'Щоб ви хотіли, щоб ця людина ПЕРЕСТАЛА робити?',
    'open_q_interaction' => 'Щоб ви хотіли, щоб ця людина ПОЧАЛА робити?',
    'open_q_impression' => 'Як би ви коротко охарактеризували людину? (можно 1 словом)',
    'open_q_additional' => 'Чи є ще щось, що ви хотіли б додати про людину, чого не було в тестах?'
];

$allOpenQuestionsWithKeys = STATIC_OPEN_QUESTIONS;
if ($targetUser && isset($targetUser['custom_question'])) {
    $customQuestionText = trim($targetUser['custom_question']);
    if (!empty($customQuestionText)) {
        $allOpenQuestionsWithKeys['profile_cq_0'] = $customQuestionText;
    }
}

$pageTitle = "Опитування про " . $targetUserDisplayName;
$message = '';
$message_type = '';

$allSliderCategories = loadQuestions();
if (empty($allSliderCategories)) {
    error_log("Warning: Could not load standard slider questions for target user ID {$targetUserId}. Open questions might still be available.");
}

$totalSliderCategories = count($allSliderCategories);
$openQuestionsCategoryIndex = $totalSliderCategories;

$currentCategoryIndex = isset($_GET['category_index']) ? (int)$_GET['category_index'] : 0;

if ($currentCategoryIndex < 0 || $currentCategoryIndex > $openQuestionsCategoryIndex) {
    header('Location: questionnaire_other.php?target_user_id=' . urlencode($targetUserId) . '&category_index=0');
    exit;
}

$existingDbAnswers = getSpecificOtherAnswers($targetUserId, $respondentUserId);
$hasExistingDbAnswers = !empty($existingDbAnswers);

if (!isset($_SESSION[$sessionAnswersKey])) {
    $_SESSION[$sessionAnswersKey] = $existingDbAnswers ?: [];
}
$formAnswers = $_SESSION[$sessionAnswersKey];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCategoryIndex = isset($_POST['current_category_index']) ? (int)$_POST['current_category_index'] : $currentCategoryIndex;
    $isValid = true;
    $validationErrorMessage = '';

    if ($submittedCategoryIndex < $totalSliderCategories && !empty($allSliderCategories[$submittedCategoryIndex])) {
        $currentSliderCategoryData = $allSliderCategories[$submittedCategoryIndex];
        $submittedSliderAnswersForCategory = [];
        foreach ($currentSliderCategoryData['questions'] as $question) {
            $questionId = $question['questionId'];
            if (isset($_POST['answers'][$questionId])) {
                $answerValue = filter_var(
                    $_POST['answers'][$questionId],
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => $question['scale']['min'], 'max_range' => $question['scale']['max']]]
                );
                if ($answerValue === false) {
                    $isValid = false;
                    $validationErrorMessage = "Будь ласка, дайте коректну відповідь на всі питання в поточній категорії.";
                    $formAnswers[$questionId] = $_POST['answers'][$questionId];
                } else {
                    $submittedSliderAnswersForCategory[$questionId] = $answerValue;
                }
            } else {
                $isValid = false;
                $validationErrorMessage = "Будь ласка, дайте відповідь на всі питання в поточній категорії.";
            }
        }
        if ($isValid) {
            $_SESSION[$sessionAnswersKey] = array_merge($_SESSION[$sessionAnswersKey] ?? [], $submittedSliderAnswersForCategory);
            $formAnswers = $_SESSION[$sessionAnswersKey];
        }
    }

    if ($isValid) {
        if (isset($_POST['next_category'])) {
            $nextIdx = $submittedCategoryIndex + 1;
            if ($nextIdx <= $openQuestionsCategoryIndex) {
                header('Location: questionnaire_other.php?target_user_id=' . urlencode($targetUserId) . '&category_index=' . $nextIdx);
                exit;
            }
        } elseif (isset($_POST['save_all'])) {
            $allSliderAnswersToSave = [];
            if (!empty($allSliderCategories)) {
                foreach ($allSliderCategories as $cat) {
                    foreach ($cat['questions'] as $q) {
                        if (isset($_SESSION[$sessionAnswersKey][$q['questionId']])) {
                            $allSliderAnswersToSave[$q['questionId']] = $_SESSION[$sessionAnswersKey][$q['questionId']];
                        }
                    }
                }
            }

            $submittedOpenAnswersRaw = $_POST['open_answers'] ?? [];
            $validatedOpenAnswers = [];
            $openQuestionsValid = true;

            foreach ($allOpenQuestionsWithKeys as $key => $label) {
                $rawAnswer = $submittedOpenAnswersRaw[$key] ?? '';
                if (mb_strlen($rawAnswer, 'UTF-8') > 250) {
                    $openQuestionsValid = false;
                    $questionTextForError = (mb_strlen($label) > 50) ? htmlspecialchars(mb_substr($label, 0, 47)) . '...' : htmlspecialchars($label);
                    $validationErrorMessage = "Відповідь на питання \"{$questionTextForError}\" занадто довга (максимум 250 символів).";
                    $formAnswers[$key] = $rawAnswer;
                    break;
                }
                $validatedOpenAnswers[$key] = htmlspecialchars(trim($rawAnswer), ENT_QUOTES, 'UTF-8');
            }

            if ($openQuestionsValid) {
                $allAnswersToSave = array_merge($allSliderAnswersToSave, $validatedOpenAnswers);
                if (empty($targetUsername)) {
                    $message = "Помилка: Ім'я цільового користувача не визначено. Неможливо зберегти дані.";
                    $message_type = 'error';
                    $currentCategoryIndex = $openQuestionsCategoryIndex;
                } elseif (saveOtherAnswers($targetUserId, $respondentUserId, $respondentUsername, $allAnswersToSave)) {
                    $targetUserData = loadUserData($targetUsername);
                    $changesMade = false;

                    if (!$targetUserData) {
                        error_log("questionnaire_other.php: Не вдалося завантажити дані користувача '{$targetUsername}' для оновлення трітів та бейджів.");
                    } else {
                        if (!isset($targetUserData['achievements'])) $targetUserData['achievements'] = [];
                        if (!isset($targetUserData['badges_summary'])) $targetUserData['badges_summary'] = [];
                        if (!isset($targetUserData['self'])) $targetUserData['self'] = null;
                        if (!isset($targetUserData['others'])) $targetUserData['others'] = [];

                        // 1. Розрахунок Трітів
                        $traitsRecalculationResult = calculateEarnedTraits($targetUsername);
                        if ($traitsRecalculationResult['success']) {
                            $existingTraitIds = is_array($targetUserData['achievements']) ? array_column($targetUserData['achievements'], 'id') : [];
                            $newTraitIds = $traitsRecalculationResult['earned_ids'];
                            sort($existingTraitIds); sort($newTraitIds);
                            if ($existingTraitIds !== $newTraitIds) {
                                $targetUserData['achievements'] = $traitsRecalculationResult['earned_traits'];
                                $changesMade = true;
                            }
                        } else {
                            error_log("questionnaire_other.php: Помилка розрахунку трітів для '{$targetUsername}'. " . ($traitsRecalculationResult['message'] ?? 'Невідома помилка'));
                        }

                        // 2. Розрахунок Бейджів
                        $badgesRecalculationResult = calculateUserBadges($targetUsername);
                        if ($badgesRecalculationResult['success']) {
                            $currentBadgesSummary = $targetUserData['badges_summary'] ?? [];
                            $newBadgesSummary = $badgesRecalculationResult['badges_summary'];
                            
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
                                $targetUserData['badges_summary'] = $newBadgesSummary;
                                $changesMade = true;
                            }
                        } else {
                            error_log("questionnaire_other.php: Помилка розрахунку бейджів для '{$targetUsername}'. " . ($badgesRecalculationResult['message'] ?? 'Невідома помилка'));
                        }

                        if ($changesMade) {
                            if (!saveUserData($targetUsername, $targetUserData)) {
                                error_log("questionnaire_other.php: Помилка збереження оновлених даних (тріти/бейджі) для '{$targetUsername}'.");
                            }
                        }
                    }

 		  // --- Початок запуску Gemini аналізу (fsockopen "fire and forget") ---
                    $targetUserDataForAnalysisCount = loadUserData($targetUsername);
                    if ($targetUserDataForAnalysisCount && isset($targetUserDataForAnalysisCount['others']) && is_array($targetUserDataForAnalysisCount['others'])) {
                        $numOthersResponses = count($targetUserDataForAnalysisCount['others']);
                        custom_log("User '{$targetUsername}': Total 'others' responses = {$numOthersResponses}. Analysis frequency = " . ANALYSIS_TRIGGER_FREQUENCY, 'analysis_trigger');

                        if ($numOthersResponses > 0 && $numOthersResponses % ANALYSIS_TRIGGER_FREQUENCY === 0) {
                            custom_log("User '{$targetUsername}': Condition MET for triggering analysis.", 'analysis_trigger');
                            
                            $internalCallSecret = getenv('INTERNAL_ANALYSIS_SECRET');
                            if (empty($internalCallSecret)) {
                                $internalCallSecret = 'DEV_INTERNAL_SECRET_KEY_PLEASE_CHANGE';
                                custom_log("User '{$targetUsername}': Using fallback internal call secret for get_analysis.php. Set INTERNAL_ANALYSIS_SECRET in .env for production.", 'analysis_trigger');
                            } else {
                                custom_log("User '{$targetUsername}': Using internal call secret from .env.", 'analysis_trigger');
                            }

                            $scriptPath = $_SERVER['PHP_SELF']; 
                            $baseScriptPath = dirname($scriptPath); 
                            if ($baseScriptPath === '/' || $baseScriptPath === '\\') {
                                $baseScriptPath = ''; 
                            }
                            $analysisScriptRelativePath = $baseScriptPath . "/get_analysis.php";
                            custom_log("User '{$targetUsername}': Determined analysis script relative path: {$analysisScriptRelativePath}", 'analysis_trigger');


                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            custom_log("User '{$targetUsername}': Protocol: {$protocol}, Host: {$host}", 'analysis_trigger');
                            
                            $queryString = "?username=" . urlencode($targetUsername) . "&internal_call_secret=" . urlencode($internalCallSecret);
                            $fullAnalysisUrlForLog = $protocol . $host . $analysisScriptRelativePath . $queryString;
                            custom_log("User '{$targetUsername}': Full analysis URL for fsockopen: {$fullAnalysisUrlForLog}", 'analysis_trigger');

                            $urlParts = parse_url($protocol . $host);
                            if (!$urlParts || !isset($urlParts['host'])) {
                                custom_log("User '{$targetUsername}': CRITICAL - Could not parse protocol/host: {$protocol}{$host}", 'analysis_trigger');
                                // Тут можна було б зупинитись або спробувати альтернативу, але для fsockopen це критично
                            } else {
                                $portToConnect = $urlParts['scheme'] === 'https' ? 443 : ($urlParts['port'] ?? 80);
                                $fpHost = $urlParts['scheme'] === 'https' ? 'ssl://' . $urlParts['host'] : $urlParts['host'];
                                custom_log("User '{$targetUsername}': Attempting fsockopen to Host: {$fpHost}, Port: {$portToConnect}", 'analysis_trigger');
                                
                                $fp_timeout = 2; // Збільшимо трохи таймаут для fsockopen
                                $fp = @fsockopen($fpHost, $portToConnect, $errno, $errstr, $fp_timeout); 

                                if ($fp) {
                                    custom_log("User '{$targetUsername}': fsockopen SUCCESSFUL. Preparing to send GET request.", 'analysis_trigger');
                                    $out = "GET " . $analysisScriptRelativePath . $queryString . " HTTP/1.1\r\n";
                                    $out .= "Host: " . $urlParts['host'] . "\r\n";
                                    $out .= "Connection: Close\r\n\r\n"; 
                                    custom_log("User '{$targetUsername}': HTTP Request to send:\n{$out}", 'analysis_trigger');
                                    
                                    $bytesWritten = @fwrite($fp, $out);
                                    if ($bytesWritten === false || $bytesWritten < strlen($out)) {
                                        custom_log("User '{$targetUsername}': fwrite FAILED or incomplete. Bytes written: " . ($bytesWritten === false ? 'false' : $bytesWritten) . " of " . strlen($out), 'analysis_trigger');
                                    } else {
                                        custom_log("User '{$targetUsername}': fwrite SUCCESSFUL. Bytes written: {$bytesWritten}. Closing connection.", 'analysis_trigger');
                                    }
                                    @fclose($fp); 
                                } else {
                                    custom_log("User '{$targetUsername}': fsockopen FAILED. Error number: {$errno}, Error string: {$errstr}. URL: {$fullAnalysisUrlForLog}", 'analysis_trigger');
                                }
                            }
                        } else {
                             custom_log("User '{$targetUsername}': Condition NOT MET for triggering analysis (numResponses: {$numOthersResponses}, frequency: " . ANALYSIS_TRIGGER_FREQUENCY . ")", 'analysis_trigger');
                        }
                    } else {
                        custom_log("User '{$targetUsername}': Could not load target user data or 'others' array is missing/not an array for analysis count.", 'analysis_trigger');
                    }
                    // --- Кінець запуску Gemini аналізу ---

                    $_SESSION['flash_message'] = 'Ваші відповіді про ' . $targetUserDisplayName . ' успішно збережено!';
                    $_SESSION['flash_message_type'] = 'success';
                    unset($_SESSION[$sessionAnswersKey]);
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $message = "Сталася помилка під час збереження ваших відповідей. Спробуйте ще раз.";
                    $message_type = 'error';
                    $formAnswers = array_merge($allSliderAnswersToSave, $submittedOpenAnswersRaw);
                    $currentCategoryIndex = $openQuestionsCategoryIndex;
                }
            } else { // $openQuestionsValid is false
                $message = $validationErrorMessage;
                $message_type = 'error';
                $formAnswers = array_merge($allSliderAnswersToSave, $submittedOpenAnswersRaw); // Зберігаємо введені дані для відображення
                $currentCategoryIndex = $openQuestionsCategoryIndex; // Залишаємось на сторінці відкритих питань
            }
        }
    } else { // $isValid is false (помилка валідації слайдерів)
        $message = $validationErrorMessage;
        $message_type = 'error';
        // $formAnswers вже оновлено з некоректними значеннями слайдерів
        $currentCategoryIndex = $submittedCategoryIndex; // Залишаємось на поточній категорії слайдерів
    }
}

$categoryToDisplay = null;
$showOpenQuestions = false;

if ($currentCategoryIndex < $totalSliderCategories && !empty($allSliderCategories[$currentCategoryIndex])) {
    $categoryToDisplay = $allSliderCategories[$currentCategoryIndex];
    $pageTitle = "Опитування про " . $targetUserDisplayName . " (Категорія " . ($currentCategoryIndex + 1) . " з " . $totalSliderCategories . ")";
} elseif ($currentCategoryIndex == $openQuestionsCategoryIndex && !empty($allOpenQuestionsWithKeys)) {
    $showOpenQuestions = true;
    $pageTitle = "Опитування про " . $targetUserDisplayName . " (Відкриті питання)";
} elseif ($totalSliderCategories == 0 && $currentCategoryIndex == 0 && !empty($allOpenQuestionsWithKeys)) {
    $showOpenQuestions = true;
    $currentCategoryIndex = $openQuestionsCategoryIndex;
    $pageTitle = "Опитування про " . $targetUserDisplayName . " (Відкриті питання)";
}

include __DIR__ . '/includes/header.php';
?>

<h1><?php echo htmlspecialchars($pageTitle); ?></h1>

<?php if (!$categoryToDisplay && !$showOpenQuestions && $totalSliderCategories > 0): ?>
    <div class="message info">Схоже, для цієї категорії немає питань, або ви обрали невірний індекс.</div>
<?php elseif ($totalSliderCategories == 0 && empty($allOpenQuestionsWithKeys)): ?>
    <div class="message info">На жаль, для цього опитування зараз немає доступних питань.</div>
<?php endif; ?>


<?php if ($categoryToDisplay || $showOpenQuestions): ?>
    <p>Будь ласка, оцініть користувача <strong><?php echo $targetUserDisplayName; ?></strong>.
    Ваші відповіді допоможуть <?php echo $targetUserDisplayName; ?> краще зрозуміти себе.
    Пам'ятайте, що ваші відповіді залишаться анонімними для <?php echo $targetUserDisplayName; ?>, тому не соромтесь та відповідайте "як думаєте".</p>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="questionnaire_other.php?target_user_id=<?php echo urlencode($targetUserId); ?>&category_index=<?php echo $currentCategoryIndex; ?>" method="POST" class="questionnaire-form">
        <input type="hidden" name="current_category_index" value="<?php echo $currentCategoryIndex; ?>">

        <?php if ($categoryToDisplay): ?>
            <fieldset class="category-fieldset">
                <legend><?php echo htmlspecialchars($categoryToDisplay['categoryName']); ?></legend>
                <?php foreach ($categoryToDisplay['questions'] as $question):
                    $questionId = $question['questionId'];
                    $currentValue = $formAnswers[$questionId] ?? round(($question['scale']['min'] + $question['scale']['max']) / 2);
                ?>
                    <div class="question-block">
                        <label for="q_<?php echo $questionId; ?>" class="question-text">
                            <?php echo htmlspecialchars($question['q_other']); ?>
                        </label>
                        <div class="range-slider">
                            <span class="range-label min"><?php echo htmlspecialchars($question['scale']['minLabel']); ?> (<?php echo $question['scale']['min']; ?>)</span>
                            <input
                                type="range" id="q_<?php echo $questionId; ?>"
                                name="answers[<?php echo $questionId; ?>]"
                                min="<?php echo $question['scale']['min']; ?>" max="<?php echo $question['scale']['max']; ?>"
                                value="<?php echo htmlspecialchars($currentValue); ?>" step="1"
                                oninput="updateRangeValue(this)" required>
                            <span class="range-label max">(<?php echo $question['scale']['max']; ?>) <?php echo htmlspecialchars($question['scale']['maxLabel']); ?></span>
                            <span class="range-value" id="val_<?php echo $questionId; ?>"><?php echo htmlspecialchars($currentValue); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </fieldset>
        <?php endif; ?>

        <?php if ($showOpenQuestions && !empty($allOpenQuestionsWithKeys)): ?>
            <fieldset class="category-fieldset open-questions">
                 <legend>Відкриті питання (необов'язково, максимум 250 символів для кожного)</legend>
                 <?php foreach ($allOpenQuestionsWithKeys as $key => $label):
                     $currentOpenValue = $formAnswers[$key] ?? '';
                 ?>
                    <div class="question-block open-question-block">
                         <label for="<?php echo $key; ?>" class="question-text">
                            <?php echo htmlspecialchars($label); ?>
                            <?php if (strpos($key, 'profile_cq_') === 0): ?>
                                <em class="custom-question-source">(Кастомне питання від <?php echo $targetUserDisplayName; ?>)</em>
                            <?php endif; ?>
                         </label>
                         <textarea id="<?php echo $key; ?>" name="open_answers[<?php echo $key; ?>]"
                                   rows="3" maxlength="250" class="open-answer-textarea"
                                   placeholder="Ваша відповідь..."><?php echo htmlspecialchars($currentOpenValue); ?></textarea>
                         <small class="char-counter" id="counter_<?php echo $key; ?>"></small>
                    </div>
                 <?php endforeach; ?>
            </fieldset>
        <?php endif; ?>

        <div class="form-navigation-buttons" style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <?php if ($currentCategoryIndex > 0): ?>
                    <a href="questionnaire_other.php?target_user_id=<?php echo urlencode($targetUserId); ?>&category_index=<?php echo $currentCategoryIndex - 1; ?>" class="btn btn-primary">Попередня категорія</a>
                <?php endif; ?>

                <?php if ($currentCategoryIndex < $openQuestionsCategoryIndex && $currentCategoryIndex < $totalSliderCategories): ?>
                    <button type="submit" name="next_category" class="btn btn-primary">
                        <?php echo ($currentCategoryIndex == $totalSliderCategories - 1 && !empty($allOpenQuestionsWithKeys)) ? "Перейти до відкритих питань" : "Наступна категорія"; ?>
                    </button>
                <?php elseif ($showOpenQuestions || ($currentCategoryIndex == $totalSliderCategories && $totalSliderCategories == 0 && !empty($allOpenQuestionsWithKeys)) ): ?>
                    <button type="submit" name="save_all" class="btn btn-primary">
                        <?php echo $hasExistingDbAnswers ? "Оновити всі відповіді" : "Надіслати всі відповіді"; ?>
                    </button>
                <?php endif; ?>
            </div>
            
            <div>
                 <a href="questionnaire_other.php?target_user_id=<?php echo urlencode($targetUserId); ?>&action=clear_session" class="btn btn-secondary" onclick="return confirm('Це очистить всі ваші поточні відповіді для цього користувача і почне опитування заново. Продовжити?');">Почати заново</a>
                 <a href="dashboard.php" class="btn btn-secondary" onclick="return confirm('Якщо ви скасуєте, незбережені зміни для поточної категорії/блоку питань буде втрачено, але відповіді з попередніх категорій залишаться в сесії. Продовжити?');">Скасувати</a>
            </div>
        </div>
    </form>
<?php endif; ?>

<style>
    .custom-question-source { display: block; font-size: 0.85em; color: #555; margin-top: 3px; }
</style>

<script>
    function updateRangeValue(element) {
        const valueSpan = document.getElementById('val_' + element.id.substring(2));
        if (valueSpan) { valueSpan.textContent = element.value; }
    }
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.questionnaire-form input[type="range"]').forEach(input => { updateRangeValue(input); });

        document.querySelectorAll('.open-answer-textarea').forEach(textarea => {
            const counter = document.getElementById('counter_' + textarea.id);
            if (counter) {
                const updateCounter = () => {
                    const currentLength = textarea.value.length;
                    const maxLength = textarea.maxLength;
                    counter.textContent = `${currentLength} / ${maxLength}`;
                    counter.style.color = currentLength > maxLength ? 'red' : (currentLength > maxLength - 20 ? 'orange' : '');
                };
                textarea.addEventListener('input', updateCounter);
                updateCounter();
            }
        });
    });
</script>

<?php
include __DIR__ . '/includes/footer.php';
?>