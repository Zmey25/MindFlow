<?php // results.php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/questionnaire_logic.php';

requireLogin(); // Перевірка, чи користувач залогінений

// --- Константи та Налаштування ---
define('MAX_QUESTION_LABEL_LENGTH', 40);
define('MAX_QUESTIONS_FOR_RADAR_LABELS', 15);
define('MIN_QUESTIONS_FOR_RADAR', 3);
// Мінімальна кількість оцінок від інших, яка потрібна для відображення результатів (як для свого профілю, так і для публічного перегляду чужого)
define('MIN_OTHER_ASSESSMENTS_CONSIDERED_ENOUGH', 3);
// Мінімальна кількість оцінок від інших для розблокування ШІ-аналізу
define('MIN_ASSESSMENTS_FOR_AI_ANALYSIS', 5);


$viewerBorderColor = 'rgb(255, 99, 132)'; // Червоний для оцінки глядача
$viewerBackgroundColor = 'rgba(255, 99, 132, 0.2)';
$otherBorderColor = 'rgb(255, 159, 64)';
$otherBackgroundColor = 'rgba(255, 159, 64, 0.2)';
$pastelBorders = [
    'rgb(92, 103, 242)', 'rgb(75, 192, 192)', 'rgb(153, 102, 255)',
    'rgb(117, 214, 117)', 'rgb(255, 205, 86)', 'rgb(201, 203, 207)'
];
$pastelBackgrounds = [
    'rgba(92, 103, 242, 0.2)', 'rgba(75, 192, 192, 0.2)', 'rgba(153, 102, 255, 0.2)',
    'rgba(117, 214, 117, 0.2)', 'rgba(255, 205, 86, 0.2)', 'rgba(201, 203, 207, 0.2)'
];


// --- Визначення цільового та поточного користувача ---
$loggedInUserId = $_SESSION['user_id'];
$loggedInUsername = $_SESSION['username'];
$isAdmin = isUserAdmin(); // Перевірка, чи поточний користувач адмін

$targetUsername = $loggedInUsername; // За замовчуванням - поточний користувач
$targetUserId = $loggedInUserId;
$targetUser = null; // Об'єкт користувача з users.json
$isViewingOther = false;
$errorMessage = null;

if (isset($_GET['user']) && !empty($_GET['user'])) {
    $requestedUsername = $_GET['user'];
    if (strtolower($requestedUsername) !== strtolower($loggedInUsername)) {
        $targetUser = findUserByUsername($requestedUsername);
        if ($targetUser) {
            $targetUsername = $targetUser['username'];
            $targetUserId = $targetUser['id'];
            $isViewingOther = true;
        } else {
            $errorMessage = "Користувача '" . htmlspecialchars($requestedUsername) . "' не знайдено.";
        }
    } else {
        $targetUser = findUserByUsername($loggedInUsername);
         if (!$targetUser) {
             die("Критична помилка: не вдалося знайти дані поточного користувача для відображення.");
         }
    }
} else {
    $targetUser = findUserByUsername($loggedInUsername);
     if (!$targetUser) {
         die("Критична помилка: не вдалося знайти дані поточного користувача.");
     }
}

// --- Дані цільового користувача та налаштування приватності ---
$pageTitle = "Результати: " . htmlspecialchars($targetUsername);
$targetProfileIsHidden = true;

if (!$errorMessage && $targetUser) {
    $targetProfileIsHidden = $targetUser['hide_results'] ?? true;
}


include __DIR__ . '/includes/header.php';

// --- Завантаження даних оцінок цільового користувача ---
$allQuestionsStructure = null;
$targetUserData = null;
$targetSelfAnswers = null;
$hasTargetSelfAnswers = false;
$targetOtherAssessments = [];
$targetNumberOfAssessors = 0;
$hasTargetOtherAnswers = false;
$hasAnyTargetData = false;
$targetAverageOtherScores = [];
$userAchievements = []; // Ініціалізуємо для використання в мотиваційних повідомленнях

if (!$errorMessage) {
    $allQuestionsStructure = loadQuestions();
    $targetUserData = loadUserData($targetUsername);

    $targetSelfAnswers = $targetUserData['self']['answers'] ?? null;
    $hasTargetSelfAnswers = !empty($targetSelfAnswers) && is_array($targetSelfAnswers);

    $targetOtherAssessments = $targetUserData['others'] ?? [];
    $targetNumberOfAssessors = count($targetOtherAssessments);
    $hasTargetOtherAnswers = $targetNumberOfAssessors > 0;

    $hasAnyTargetData = $hasTargetSelfAnswers || $hasTargetOtherAnswers;

    if ($hasTargetOtherAnswers && !empty($allQuestionsStructure)) {
        $targetAverageOtherScores = calculateAverageOtherScores($targetUserId, $allQuestionsStructure);
    }
    // Завантажуємо ачівки цільового користувача, вони можуть знадобитись для мотиваційних повідомлень
    $userAchievements = $targetUserData['achievements'] ?? [];
}

// --- Завантаження оцінок ПОТОЧНОГО користувача про ЦІЛЬОВОГО (якщо дивимось іншого) ---
$viewerAnswers = null;
$hasViewerAnswers = false;
if ($isViewingOther && !$errorMessage) {
    $viewerAnswers = getSpecificOtherAnswers($targetUserId, $loggedInUserId);
    $hasViewerAnswers = !empty($viewerAnswers) && is_array($viewerAnswers);
}

// --- Визначення, чи можна переглядати результати ---
$canViewResults = false;
$reasonCannotView = '';
$randomAchievementTeaser = ''; // Для мотивуючих повідомлень

if (!$errorMessage) {
    if (!$isViewingOther) {
        // Правило 1: Перегляд ВЛАСНИХ результатів
        if (!$hasTargetSelfAnswers) {
            $canViewResults = false;
            $reasonCannotView = 'no_self_assessment';
        } elseif ($targetNumberOfAssessors < MIN_OTHER_ASSESSMENTS_CONSIDERED_ENOUGH) {
            $canViewResults = false;
            $reasonCannotView = 'not_enough_other_assessments_self';
        } else {
            $canViewResults = true;
        }

        // Підготовка мотивуючого повідомлення з випадковою ачівкою, якщо результати не відображаються
        if (!$canViewResults) {
            if ($reasonCannotView === 'no_self_assessment') {
                if (!empty($userAchievements)) {
                    $randomAchievement = $userAchievements[array_rand($userAchievements)];
                    $achName = htmlspecialchars($randomAchievement['name'] ?? 'цікаву рису');
                    $achIcon = htmlspecialchars($randomAchievement['icon'] ?? 'fas fa-star');
                    $randomAchievementTeaser = "<br><br>Пройшовши самооцінку, ви зможете дізнатися про себе багато нового, наприклад, про такі ваші особливості як <strong><i class=\"{$achIcon}\"></i> {$achName}</strong> та інші! Зберіть також відгуки друзів для повної картини.";
                } else {
                    $randomAchievementTeaser = "<br><br>Пройдіть самооцінку, щоб почати відкривати свої унікальні риси та сильні сторони! Потім зберіть відгуки друзів для повного аналізу.";
                }
            } elseif ($reasonCannotView === 'not_enough_other_assessments_self') {
                if (!empty($userAchievements)) {
                    $randomAchievement = $userAchievements[array_rand($userAchievements)];
                    $achName = htmlspecialchars($randomAchievement['name'] ?? 'важливу рису');
                    $achIcon = htmlspecialchars($randomAchievement['icon'] ?? 'fas fa-star');
                    $randomAchievementTeaser = "<br><br>Ви вже на правильному шляху! Наприклад, у вас може бути така особливість як <strong><i class=\"{$achIcon}\"></i> {$achName}</strong>. Зберіть більше відгуків (" . (MIN_OTHER_ASSESSMENTS_CONSIDERED_ENOUGH - $targetNumberOfAssessors) . " ще), щоб розкрити повну картину та побачити, як вас сприймають інші!";
                } else {
                    $randomAchievementTeaser = "<br><br>Зберіть більше відгуків (" . (MIN_OTHER_ASSESSMENTS_CONSIDERED_ENOUGH - $targetNumberOfAssessors) . " ще), щоб розкрити повну картину ваших особливостей та побачити, як вас сприймають інші!";
                }
            }
        }

    } elseif ($isAdmin) {
        // Правило 2: Адмін дивиться чужий профіль
        $canViewResults = $hasAnyTargetData;
         if (!$canViewResults) {
            $reasonCannotView = 'admin_no_data';
        }
    } else {
        // Правило 3: Звичайний користувач дивиться ЧУЖИЙ профіль
        if ($targetProfileIsHidden) {
            $canViewResults = false;
            $reasonCannotView = 'profile_hidden';
        } else { // Профіль публічний, але перевіряємо умови
            if (!$hasTargetSelfAnswers) {
                $canViewResults = false;
                $reasonCannotView = 'other_no_self_assessment'; // Цільовий не пройшов самооцінку
            } elseif ($targetNumberOfAssessors < MIN_OTHER_ASSESSMENTS_CONSIDERED_ENOUGH) {
                $canViewResults = false;
                // Цільовий пройшов самооцінку, профіль публічний, але недостатньо відгуків від інших
                $reasonCannotView = 'other_not_enough_assessments_public';
            } else {
                // Публічний, є самооцінка, достатньо відгуків від інших
                $canViewResults = true;
            }
        }
    }
} else {
    $reasonCannotView = 'user_not_found';
}


// --- Підготовка даних для діаграм (тільки якщо $canViewResults) ---
// (цей блок залишається без змін, він виконується тільки якщо $canViewResults = true)
$chartDataByCategory = [];
$categoryScales = [];
$fullQuestionTexts = [];
$canDrawChart = [];
$categoryColorStyles = [];

if ($canViewResults && !$errorMessage && !empty($allQuestionsStructure)) {
    foreach ($allQuestionsStructure as $categoryIndex => $category) {
        $categoryId = $category['categoryId'];
        $labels = [];
        $currentFullTexts = [];
        $targetSelfScoresData = [];
        $targetOtherScoresData = [];
        $viewerScoresData = [];
        $minScale = 7; $maxScale = 1;

        if (empty($category['questions'])) {
            $canDrawChart[$categoryId] = false;
            continue;
        }

        $currentBorder = $pastelBorders[$categoryIndex % count($pastelBorders)];
        $currentBackground = $pastelBackgrounds[$categoryIndex % count($pastelBackgrounds)];
        $categoryColorStyles[$categoryId] = "border-bottom-color: {$currentBorder};";

        $questionCount = count($category['questions']);
        $canDrawChart[$categoryId] = $questionCount >= MIN_QUESTIONS_FOR_RADAR;
        $showPointLabels = $questionCount <= MAX_QUESTIONS_FOR_RADAR_LABELS;

        foreach ($category['questions'] as $question) {
            $questionId = $question['questionId'];
            $currentFullTexts[] = $question['q_other'];
            $labels[] = $question['q_short'];

             if ($hasTargetSelfAnswers) {
                 $targetSelfScoresData[] = $targetSelfAnswers[$questionId] ?? null;
             } else {
                 $targetSelfScoresData[] = null; 
             }
            $otherResult = $targetAverageOtherScores[$questionId] ?? ['average' => null, 'count' => 0];
            $targetOtherScoresData[] = $otherResult['average'];

            if ($isViewingOther && $hasViewerAnswers) {
                $viewerScoresData[] = $viewerAnswers[$questionId] ?? null;
            }
            if (isset($question['scale']['min'])) $minScale = min($minScale, $question['scale']['min']);
            if (isset($question['scale']['max'])) $maxScale = max($maxScale, $question['scale']['max']);
        }
        $fullQuestionTexts[$categoryId] = $currentFullTexts;
        if ($canDrawChart[$categoryId]) {
            $datasets = [];
             if ($hasTargetSelfAnswers) { 
                 $datasets[] = ['label' => $isViewingOther ? 'Самооцінка ('.htmlspecialchars($targetUsername).')' : 'Моя оцінка', 'data' => $targetSelfScoresData, 'fill' => true, 'backgroundColor' => $currentBackground, 'borderColor' => $currentBorder, 'pointBackgroundColor' => $currentBorder, 'pointBorderColor' => '#fff', 'pointHoverBackgroundColor' => '#fff', 'pointHoverBorderColor' => $currentBorder];
             }
            if ($hasTargetOtherAnswers) { 
                $datasets[] = ['label' => 'Оцінка інших (Ø)', 'data' => $targetOtherScoresData, 'fill' => true, 'backgroundColor' => $otherBackgroundColor, 'borderColor' => $otherBorderColor, 'pointBackgroundColor' => $otherBorderColor, 'pointBorderColor' => '#fff', 'pointHoverBackgroundColor' => '#fff', 'pointHoverBorderColor' => $otherBorderColor];
            }
            if ($isViewingOther && $hasViewerAnswers) { 
                 $datasets[] = ['label' => 'Моя оцінка про '.htmlspecialchars($targetUsername), 'data' => $viewerScoresData, 'fill' => true, 'backgroundColor' => $viewerBackgroundColor, 'borderColor' => $viewerBorderColor, 'pointBackgroundColor' => $viewerBorderColor, 'pointBorderColor' => '#fff', 'pointHoverBackgroundColor' => '#fff', 'pointHoverBorderColor' => $viewerBorderColor];
            }
            if (!empty($datasets)) {
                $chartDataByCategory[$categoryId] = ['labels' => $labels, 'showPointLabels' => $showPointLabels, 'datasets' => $datasets];
                $categoryScales[$categoryId] = ['min' => $minScale, 'max' => $maxScale];
            } else {
                 $canDrawChart[$categoryId] = false;
            }
        }
    }
}
?>

<!-- --- HTML Розмітка --- -->
<h1><?php echo $pageTitle; ?></h1>

<?php // --- Повідомлення про НЕМОЖЛИВІСТЬ перегляду --- ?>
<?php if (!$canViewResults): ?>
    <?php if ($reasonCannotView === 'user_not_found'): ?>
        <div class="message error"><?php echo $errorMessage; ?></div>
    <?php elseif ($reasonCannotView === 'no_self_assessment'): ?>
        <div class="message info">
            <img src="assets/images/cat_with_a_book.png" width="250" alt="Кицька"><br>
            Ви ще не пройшли самооцінку. Ваші результати будуть доступні після її завершення та отримання достатньої кількості відгуків від інших.
            <?php echo $randomAchievementTeaser; // Виводимо підготовлений текст з ачівкою ?>
            <br><br>
            <a href="questionnaire_self.php" class="btn btn-primary">Пройти опитування про себе</a>
        </div>
    <?php elseif ($reasonCannotView === 'not_enough_other_assessments_self'): ?>
        <div class="message info">
            <img src="assets/images/cat_with_results.png" width="250" alt="Кицька"><br>
            Ви успішно пройшли самооцінку!
            <br><br>
            Для відображення повних результатів та аналізу ваших особливостей, необхідно отримати щонайменше <strong><?php echo MIN_OTHER_ASSESSMENTS_CONSIDERED_ENOUGH; ?></strong>
            <?php echo getUkrainianNounEnding(MIN_OTHER_ASSESSMENTS_CONSIDERED_ENOUGH, 'відгук', 'відгуки', 'відгуків'); ?> від інших.
            Наразі у вас <strong><?php echo $targetNumberOfAssessors; ?></strong>
            <?php echo getUkrainianNounEnding($targetNumberOfAssessors, 'відгук', 'відгуки', 'відгуків'); ?>.
            <?php echo $randomAchievementTeaser; // Виводимо підготовлений текст з ачівкою ?>
            <br><br>
            Будь ласка, поділіться <a href="dashboard.php">посиланням для друзів</a> та попросіть їх надати зворотний зв'язок.
        </div>
    <?php elseif ($reasonCannotView === 'profile_hidden'): ?>
        <div class="message info">
            Користувач <strong><?php echo htmlspecialchars($targetUsername); ?></strong> приховав свої результати.
        </div>
    <?php elseif ($reasonCannotView === 'other_no_self_assessment'): ?>
        <div class="message info">
            Користувач <strong><?php echo htmlspecialchars($targetUsername); ?></strong> ще не пройшов самооцінку. Результати недоступні для перегляду іншими.
        </div>
    <?php elseif ($reasonCannotView === 'other_not_enough_assessments_public'): ?>
        <div class="message info">
            Хоча профіль користувача <strong><?php echo htmlspecialchars($targetUsername); ?></strong> публічний, наразі для нього зібрано недостатньо відгуків (менше <?php echo MIN_OTHER_ASSESSMENTS_CONSIDERED_ENOUGH; ?>) для відображення повних та репрезентативних результатів.
            <br>
            Результати стануть доступні, коли буде зібрано більше даних.
        </div>
     <?php elseif ($reasonCannotView === 'admin_no_data'): ?>
        <div class="message warning">
            <strong>Режим Адміністратора:</strong> Для користувача <?php echo htmlspecialchars($targetUsername); ?> ще немає жодних даних оцінок. Результати неможливо відобразити.
        </div>
    <?php else: ?>
         <div class="message error">Не вдалося відобразити результати з невідомої причини.</div>
    <?php endif; ?>
    
<?php // --- Відображення РЕЗУЛЬТАТІВ (якщо $canViewResults === true) --- ?>
<?php else: ?>

    <?php // --- Інформаційні повідомлення ПЕРЕД результатами --- ?>
    <?php if ($isViewingOther): ?>
        <div class="message info">
            Ви переглядаєте результати користувача <strong><?php echo htmlspecialchars($targetUsername); ?></strong>.
             <?php if ($isAdmin): ?>
                 (Режим адміністратора: налаштування приватності та мінімальної кількості відгуків ігноруються).
             <?php endif; ?>
            <?php if ($hasViewerAnswers): ?>
                Ваша оцінка цієї людини також відображена на графіках та в таблицях.
            <?php else: ?>
                 Ви ще не оцінювали цього користувача. <a href="questionnaire_other.php?target_user_id=<?php echo urlencode($targetUserId); ?>">Оцінити зараз?</a>
            <?php endif; ?>
        </div>
        <?php
            if ($hasTargetOtherAnswers) { // Цільовий має оцінки від інших
                echo '<div class="message info">Порівняльний аналіз самооцінки '.htmlspecialchars($targetUsername).' та середньої оцінки від <strong>'.$targetNumberOfAssessors.'</strong> '.getUkrainianNounEnding($targetNumberOfAssessors, 'оцінювача', 'оцінювачів', 'оцінювачів').'.</div>';
            } elseif ($hasTargetSelfAnswers) { // Є тільки самооцінка цільового (і ми тут, бо $canViewResults=true, значить це адмін)
                 echo '<div class="message info">Відображається лише самооцінка користувача '.htmlspecialchars($targetUsername).'. Оцінки від інших ще не надходили або їх недостатньо для повного аналізу (поточна к-сть: '.$targetNumberOfAssessors.').</div>';
            }
        ?>

    <?php else: // Якщо дивимось СВІЙ профіль ?>
         <div class="message info">Це ваші результати опитування.</div>
        <?php
            echo '<div class="message success">Порівняльний аналіз вашої самооцінки та середньої оцінки від <strong>'.$targetNumberOfAssessors.'</strong> '.getUkrainianNounEnding($targetNumberOfAssessors, 'оцінювача', 'оцінювачів', 'оцінювачів').'.</div>';

            if ($targetNumberOfAssessors < MIN_ASSESSMENTS_FOR_AI_ANALYSIS) {
                $neededForAI = MIN_ASSESSMENTS_FOR_AI_ANALYSIS - $targetNumberOfAssessors;
                echo '<div class="message info">Чудово! У вас вже є <strong>' . $targetNumberOfAssessors . '</strong> ' . getUkrainianNounEnding($targetNumberOfAssessors, 'відгук', 'відгуки', 'відгуків') . '. ';
                echo 'Зберіть ще щонайменше <strong>' . $neededForAI . '</strong> ' . getUkrainianNounEnding($neededForAI, 'відгук', 'відгуки', 'відгуків') . ' (щоб було ' . MIN_ASSESSMENTS_FOR_AI_ANALYSIS . ' або більше), аби розблокувати ШІ-аналіз вашого профілю!';
                echo '</div>';
            }
        ?>

<?php
// Посилання на хартстайлс (залишається без змін)
echo '<style>
    .sidebar-link-box { position: fixed; bottom: 20px; right: 20px; background-color: #f0f0f0; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 1000; display: flex; align-items: center; gap: 10px; font-family: sans-serif; font-size: 0.9em; }
    .sidebar-link-box a { text-decoration: none; color: #333; transition: color 0.3s ease; }
    .sidebar-link-box a:hover { color: #007bff; }
    .sidebar-link-box .close-btn { cursor: pointer; font-size: 1.2em; color: #aaa; font-weight: bold; transition: color 0.2s ease; }
    .sidebar-link-box .close-btn:hover { color: #777; }
</style>';
echo '<div id="sideLinkBox" class="sidebar-link-box">';
echo '  <a href="heartstyle.php">Дивіться також інший варіант результатів!</a>';
echo '  <span class="close-btn" onclick="document.getElementById(\'sideLinkBox\').style.display=\'none\'">×</span>';
echo '</div>';
?>
    <?php endif; ?>


    <?php // --- Контейнер для карток з результатами по категоріях --- ?>
    <div class="results-layout">
        <?php
        if (empty($allQuestionsStructure)) {
             echo "<div class='message error'>Помилка: Не вдалося завантажити структуру питань. Детальні результати неможливо відобразити.</div>";
        } else {
            foreach ($allQuestionsStructure as $category):
                $categoryId = $category['categoryId'];
                $questionsInCategory = $category['questions'] ?? [];
                if (empty($questionsInCategory)) continue;
                $canDisplayChart = $canDrawChart[$categoryId] ?? false;
                $categoryStyle = $categoryColorStyles[$categoryId] ?? '';
                $questionCount = count($questionsInCategory);
                $selfAnswers = $targetSelfAnswers; 
                $averageOtherScores = $targetAverageOtherScores; 
                $hasOtherAnswers = $hasTargetOtherAnswers;
                $viewerSpecificAnswers = ($isViewingOther && $hasViewerAnswers) ? $viewerAnswers : null;
                include __DIR__ . '/includes/results_category_card.php';
            endforeach;
        }
        ?>
    </div>

    <?php
    if (!empty($chartDataByCategory)) {
        include __DIR__ . '/includes/results_chart_renderer.php';
    } elseif ($hasAnyTargetData) { 
        $min_q_met = false;
        if (!empty($allQuestionsStructure)) {
            foreach($allQuestionsStructure as $cat) {
                if(count($cat['questions'] ?? []) >= MIN_QUESTIONS_FOR_RADAR) { $min_q_met = true; break; }
            }
        }
         if ($min_q_met) { 
             echo "<div class='message info'>Недостатньо даних або питань для побудови радіальних діаграм для деяких категорій.</div>";
         }
    }
    ?>

    <?php 
        $otherAssessmentsForInclude = $targetUserData['others'] ?? [];
        if (!empty($otherAssessmentsForInclude) && is_array($otherAssessmentsForInclude)) {
            include __DIR__ . '/includes/results_open_answers.php';
        }
    ?>

    <?php 
        // Ачівки завантажуються вище в $userAchievements
        $userBadgesSummary = $targetUserData['badges_summary'] ?? [];
        if (!defined('BADGES_FILE_PATH')) {
            define('BADGES_FILE_PATH', __DIR__ . '/data/badges.json');
        }
        $allDefinedBadges = file_exists(BADGES_FILE_PATH) ? json_decode(file_get_contents(BADGES_FILE_PATH), true) : [];
        if ($allDefinedBadges === null) $allDefinedBadges = []; 

        if (!empty($userBadgesSummary) && !empty($allDefinedBadges)):
    ?>
        <?php include __DIR__ . '/includes/results_badges.php'; ?>
    <?php endif; ?>

    <?php
        // $userAchievements вже завантажено вище
        $expertAnalysisData = $targetUserData['expertAnalysis'] ?? null;
    ?>
    <?php if (!empty($userAchievements)): // Тут ми використовуємо вже завантажені ачівки ?>
        <?php include __DIR__ . '/includes/results_achievements.php'; ?>
    <?php endif; ?>
    <?php if (!empty($expertAnalysisData)): ?>
        <?php include __DIR__ . '/includes/results_expert_analysis.php'; ?>
    <?php endif; ?>

<?php endif; ?>

<link rel="stylesheet" href="assets/css/results.css">

<?php
include __DIR__ . '/includes/footer.php';
?>
