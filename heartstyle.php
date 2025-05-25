<?php // heartstyle.php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/questionnaire_logic.php';

requireLogin();

$heartstylesBehaviorToQuestionMap = [
    // Effective - Blue Quadrant (Purpose / Цільолеспрямованість)
    'authentic'        => [
        ['id' => 'q_self_esteem',      'inverse' => false], // Висока самооцінка
        ['id' => 'q_self_reflection',  'inverse' => false], // Висока саморефлексія
        ['id' => 'q_reservedness',     'inverse' => false]  // Висока відкритість (maxLabel: "Дуже відкрита/ий, легко ділиться")
    ],
    'self_improving'   => [
        ['id' => 'q_learning_ability', 'inverse' => false], // Легко навчається
        ['id' => 'q_critical_thinking','inverse' => false], // Глибоко аналізує
        ['id' => 'q_self_reflection',  'inverse' => false]  // Часто і глибоко аналізує себе
    ],
    'reliable'         => [
        ['id' => 'q_responsibility',   'inverse' => false], // Завжди бере відповідальність
        ['id' => 'q_punctuality',      'inverse' => false], // Все робить вчасно
        ['id' => 'q_trust',            'inverse' => false]  // Висока довіра (q_self: "легко іншим довіряти вам", q_other: "можете ви довіряти")
    ],
    'achieving'        => [
        ['id' => 'q_problem_solving',  'inverse' => false], // Вирішує проблеми
        ['id' => 'q_decisiveness',     'inverse' => false], // Дуже рішуча/ий (виправлено ID)
        ['id' => 'q_work_engagement',  'inverse' => false]  // Дуже залучена/ий в роботу
    ],

    // Effective - Red Quadrant (Love / Любов)
    'relating'         => [
        ['id' => 'q_teamwork',         'inverse' => false], // Комфортно в команді
        ['id' => 'q_trust',            'inverse' => false], // Висока довіра
        ['id' => 'q_empathy',          'inverse' => false]  // Висока емпатія
    ],
    'inspiring'        => [
        ['id' => 'q_leading',          'inverse' => false], // Чудово керує
        ['id' => 'q_team_influence',   'inverse' => false], // Ключовий авторитет
        ['id' => 'q_optimism',         'inverse' => false]  // Бачить позитив (оптиміст)
    ],
    'developing'       => [
        ['id' => 'q_feedbacking',      'inverse' => false], // Часто дає фідбек
        ['id' => 'q_empathy',          'inverse' => false], // Висока емпатія (для розуміння потреб у розвитку)
        ['id' => 'q_rescuer',          'inverse' => false]  // Часто допомагає іншим (схильність до ролі Рятівника, що може бути спрямована на розвиток)
    ],
    'understanding'    => [
        ['id' => 'q_empathy',          'inverse' => false], // Висока емпатія
        ['id' => 'q_causality',        'inverse' => false], // Легко розуміє причини та наслідки
        ['id' => 'q_critical_thinking','inverse' => false]  // Глибоко аналізує для розуміння
    ],

    // Ineffective - Green Quadrant (Pride / Гординя)
    'arrogant_hs'      => [
        ['id' => 'q_modesty',          'inverse' => true],  // Низька скромність = пихатість
        ['id' => 'q_pride',            'inverse' => false], // Висока гординя/непоступливість
        ['id' => 'q_self_esteem',      'inverse' => false]  // Дуже висока самооцінка (може бути ознакою)
    ],
    'competitive_hs'   => [
        ['id' => 'q_assertiveness',    'inverse' => false], // Дуже наполеглива/ий
        ['id' => 'q_persecutor',       'inverse' => false], // Схильність до ролі Агресора (в конкуренції)
        ['id' => 'q_social_comparison','inverse' => false]  // Часто порівнює, відчуває незадоволення (заздрісність)
    ],
    'controlling_hs'   => [
        ['id' => 'q_leading',          'inverse' => false], // Якщо "чудово керує" інтерпретується як авторитарний контроль
        ['id' => 'q_perfectionism',    'inverse' => false], // "Все має бути ідеально" може вести до контролю
        ['id' => 'q_uncertainty_tolerance', 'inverse' => true] // Низька толерантність до невизначеності (потребує визначеності -> контролю)
    ],
    'perfectionist_hs' => [
        ['id' => 'q_perfectionism',    'inverse' => false], // "Все має бути ідеально"
        ['id' => 'q_attention',        'inverse' => false], // Може фокусуватись дуже довго (для досягнення ідеалу)
        ['id' => 'q_decision_quality', 'inverse' => true]   // "Постійно шкодує / Доводиться змінювати" (низький бал = постійне незадоволення результатом)
    ],

    // Ineffective - Orange Quadrant (Fear / Страх)
    'approval_seeking' => [
        ['id' => 'q_inclusivity',      'inverse' => false], // Висока конформність ("Підлаштовуюсь під оточуючих")
        ['id' => 'q_self_esteem',      'inverse' => true],  // Низька самооцінка
        ['id' => 'q_nonconforming',    'inverse' => true]   // Низький нонконформізм ("Хочу бути як усі")
    ],
    'defensive_hs'     => [
        ['id' => 'q_offensiveness',    'inverse' => false], // Дуже легко образити
        ['id' => 'q_stress_resistance','inverse' => true],  // Низька стресостійкість (легко втрачає рівновагу)
        ['id' => 'q_pride',            'inverse' => false]  // Висока гординя (важко визнає помилки, що веде до захисту)
    ],
    'dependent_hs'     => [
        ['id' => 'q_responsibility',   'inverse' => true],  // Уникає відповідальності
        ['id' => 'q_apathy',           'inverse' => false], // Висока байдужість (до стороннього, може вести до бездіяльності та залежності)
        ['id' => 'q_self_esteem',      'inverse' => true]   // Низька самооцінка
    ],
    'avoiding_hs'      => [
        ['id' => 'q_work_engagement',  'inverse' => true],  // Низька залученість в роботу
        ['id' => 'q_responsibility',   'inverse' => true],  // Уникає відповідальності
        ['id' => 'q_conflict_proneness','inverse' => true] // Низька схильність до конфліктів (уникає їх)
    ]
];

$heartstylesBehaviorLabels = [
    'authentic'        => 'Автентичний',
    'self_improving'   => 'Само-вдоскон.',
    'reliable'         => 'Надійний',
    'achieving'        => 'Досягаючий',
    'relating'         => 'Буд. стосунки',
    'inspiring'        => 'Надихаючий',
    'developing'       => 'Розвиваючий',
    'understanding'    => 'Розуміючий',
    'arrogant_hs'      => 'Пихатий',
    'competitive_hs'   => 'Конкуруючий',
    'controlling_hs'   => 'Контролюючий',
    'perfectionist_hs' => 'Перфекціоніст',
    'approval_seeking' => 'Шук. схвалення',
    'defensive_hs'     => 'Образливий',
    'dependent_hs'     => 'Залежний',
    'avoiding_hs'      => 'Уникаючий'
];

// Quadrant Definitions
$heartstylesQuadrants = [
    'purpose' => [
        'main_title' => 'Цілеспрямованість', 'sub_title' => 'Особистісний ріст',
        'color' => '#00AEEF', 'text_color' => '#FFFFFF', 'bar_color' => 'rgba(0, 174, 239, 0.7)',
        'behaviors' => ['authentic', 'self_improving', 'reliable', 'achieving'], 'direction' => 'up', 'position' => 'top-left'
    ],
    'love' => [
        'main_title' => 'Любов', 'sub_title' => 'Розвиток інших',
        'color' => '#ED1C24', 'text_color' => '#FFFFFF', 'bar_color' => 'rgba(237, 28, 36, 0.7)',
        'behaviors' => ['relating', 'inspiring', 'developing', 'understanding'], 'direction' => 'up', 'position' => 'top-right'
    ],
    'pride' => [
        'main_title' => 'Гординя', 'sub_title' => 'Самореклама',
        'color' => '#8DC63F', 'text_color' => '#FFFFFF', 'bar_color' => 'rgba(141, 198, 63, 0.7)',
        'behaviors' => ['arrogant_hs', 'competitive_hs', 'controlling_hs', 'perfectionist_hs'], 'direction' => 'down', 'position' => 'bottom-left'
    ],
    'fear' => [
        'main_title' => 'Страх', 'sub_title' => 'Самозахист',
        'color' => '#F7941D', 'text_color' => '#FFFFFF', 'bar_color' => 'rgba(247, 148, 29, 0.7)',
        'behaviors' => ['approval_seeking', 'defensive_hs', 'dependent_hs', 'avoiding_hs'], 'direction' => 'down', 'position' => 'bottom-right'
    ]
];
$minScaleOverall = 1;
$maxScaleOverall = 7;
$normZoneMin = 1; 
$normZoneMax = 4;
$ineffectivenormZoneMin = 4;
$ineffectivenormZoneMax = 7;


// --- Standard User and Data Loading ---
$loggedInUserId = $_SESSION['user_id'];
$loggedInUsername = $_SESSION['username'];
$isAdmin = isUserAdmin();

$minOtherAssessorsRequired = 3; // NEW: Minimum number of other assessors required for non-admins
$notEnoughOtherAssessorsMessage = null; // NEW: Message if not enough other assessors

$targetUsername = $loggedInUsername;
$targetUserId = $loggedInUserId;
$targetUser = null;
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
         if (!$targetUser) { die("Критична помилка: не вдалося знайти дані поточного користувача."); }
    }
} else {
    $targetUser = findUserByUsername($loggedInUsername);
     if (!$targetUser) { die("Критична помилка: не вдалося знайти дані поточного користувача."); }
}

$pageTitle = "Heartstyles: " . htmlspecialchars($targetUsername);
$targetProfileIsHidden = true;

if (!$errorMessage && $targetUser) {
    $targetProfileIsHidden = $targetUser['hide_results'] ?? true;
}

// $allQuestionsStructure and data loading moved before header.php to allow any logic based on them
$allQuestionsStructure = null;
$targetUserData = null;
$targetSelfAnswers = null;
$hasTargetSelfAnswers = false;
$targetAverageOtherScores = [];
$hasTargetOtherAnswers = false;
$hasAnyTargetData = false;

if (!$errorMessage && $targetUser) { // Ensure $targetUser is set before using it
    $allQuestionsStructure = loadQuestions();
    $targetUserData = loadUserData($targetUsername); // $targetUserData contains ['self'] and ['others']
    
    $targetSelfAnswers = $targetUserData['self']['answers'] ?? null;
    $hasTargetSelfAnswers = !empty($targetSelfAnswers) && is_array($targetSelfAnswers);

    // NEW/MODIFIED logic for $hasTargetOtherAnswers
    $numberOfOtherAssessors = count($targetUserData['others'] ?? []);
    if ($numberOfOtherAssessors >= 0 && !empty($allQuestionsStructure)) {
        if ($isAdmin || $numberOfOtherAssessors >= $minOtherAssessorsRequired) {
            $tempAverageOtherScores = calculateAverageOtherScores($targetUserId, $allQuestionsStructure);
            if (!empty($tempAverageOtherScores)) {
                $targetAverageOtherScores = $tempAverageOtherScores;
                $hasTargetOtherAnswers = true;
            }
        } else {
            // Not admin and not enough assessors for 'others' graph
            $hasTargetOtherAnswers = false; // Ensure it's false
            $notEnoughOtherAssessorsMessage = "Для відображення \"Середньої Оцінки Інших Heartstyles\" необхідно щонайменше {$minOtherAssessorsRequired} оцінки від інших.\n";
            $notEnoughOtherAssessorsMessage .= "Наразі " . ($targetUser['username'] == $loggedInUsername ? "Ви отримали" : "користувач ".htmlspecialchars($targetUser['username'])." отримав") . ": {$numberOfOtherAssessors}.\n";
            $notEnoughOtherAssessorsMessage .= "Тому дані від інших не відображаються на графіку.";
        }
    }
    // else: 0 other assessors, $hasTargetOtherAnswers remains false.

    $hasAnyTargetData = $hasTargetSelfAnswers || $hasTargetOtherAnswers; // Recalculate based on potentially modified $hasTargetOtherAnswers
}


$canViewResults = false;
$reasonCannotView = '';

if (!$errorMessage) {
    if (!$isViewingOther) { // Viewing own profile
        $canViewResults = $hasTargetSelfAnswers;
        if (!$canViewResults) $reasonCannotView = 'no_self_assessment';
    } elseif ($isAdmin) { // Admin viewing other
        $canViewResults = $hasAnyTargetData; // Uses updated $hasAnyTargetData
        if (!$canViewResults) $reasonCannotView = 'admin_no_data';
    } else { // Non-admin viewing other
        if ($targetProfileIsHidden) {
            $canViewResults = false;
            $reasonCannotView = 'profile_hidden';
        } else {
            $canViewResults = $hasTargetSelfAnswers; 
            if (!$canViewResults) $reasonCannotView = 'other_no_self_assessment';
        }
    }
} else {
    $reasonCannotView = 'user_not_found';
}

// --- Prepare Mapped Data for Heartstyles ---
$mappedSelfScores = [];
$mappedAvgOtherScores = [];

if ($canViewResults && !$errorMessage && !empty($allQuestionsStructure)) {
    $questionDetailsCache = []; // Кеш для деталей питань (шкал)

    foreach ($heartstylesBehaviorToQuestionMap as $hsBehavior => $questionMappings) {
        $selfScoresForBehavior = [];
        $avgOtherScoresForBehavior = [];

        // $questionMappings тепер є масивом
        foreach ($questionMappings as $mapInfo) {
            $questionId = $mapInfo['id'];
            $isInverse = $mapInfo['inverse'];

            if (!isset($questionDetailsCache[$questionId])) {
                // Завантаження деталей питання (шкали) один раз
                $foundQuestion = false;
                foreach ($allQuestionsStructure as $category) {
                    foreach ($category['questions'] as $q) {
                        if ($q['questionId'] === $questionId) {
                            $questionDetailsCache[$questionId] = $q['scale'] ?? ['min' => $minScaleOverall, 'max' => $maxScaleOverall];
                            $foundQuestion = true;
                            break 2;
                        }
                    }
                }
                if (!$foundQuestion) {
                     // Якщо питання не знайдено у структурі, використовуємо загальну шкалу
                     $questionDetailsCache[$questionId] = ['min' => $minScaleOverall, 'max' => $maxScaleOverall];
                }
            }
            $qScale = $questionDetailsCache[$questionId];
            $qMin = $qScale['min'];
            $qMax = $qScale['max'];

            // Обробка самооцінки
            if ($hasTargetSelfAnswers && isset($targetSelfAnswers[$questionId]) && is_numeric($targetSelfAnswers[$questionId])) {
                $score = floatval($targetSelfAnswers[$questionId]);
                $processedScore = $isInverse ? ($qMax + $qMin) - $score : $score;
                $selfScoresForBehavior[] = $processedScore;
            } elseif ($hasTargetSelfAnswers && !isset($targetSelfAnswers[$questionId])) {
                 // Якщо відповіді на це конкретне питання немає, але самооцінка загалом є
                 // можна додати null, щоб потім його проігнорувати при усередненні,
                 // або нічого не додавати, залежно від бажаної логіки
            }


            // Обробка середньої оцінки інших
            if ($hasTargetOtherAnswers && isset($targetAverageOtherScores[$questionId]) && isset($targetAverageOtherScores[$questionId]['average']) && is_numeric($targetAverageOtherScores[$questionId]['average'])) {
                $score = floatval($targetAverageOtherScores[$questionId]['average']);
                $processedScore = $isInverse ? ($qMax + $qMin) - $score : $score;
                $avgOtherScoresForBehavior[] = $processedScore;
            } elseif ($hasTargetOtherAnswers && !isset($targetAverageOtherScores[$questionId])) {
                // Аналогічно для оцінок інших
            }
        }

        // Усереднення зібраних оцінок для поточної поведінки Heartstyles
        if (!empty($selfScoresForBehavior)) {
            $mappedSelfScores[$hsBehavior] = array_sum($selfScoresForBehavior) / count($selfScoresForBehavior);
        } else {
            $mappedSelfScores[$hsBehavior] = null; // Якщо немає жодної валідної оцінки для цієї поведінки
        }

        if (!empty($avgOtherScoresForBehavior)) {
            $mappedAvgOtherScores[$hsBehavior] = array_sum($avgOtherScoresForBehavior) / count($avgOtherScoresForBehavior);
        } else {
            $mappedAvgOtherScores[$hsBehavior] = null;
        }
    }
}

include __DIR__ . '/includes/header.php'; // Header included after all logic
?>

<h1><?php echo $pageTitle; ?> - Аналіз Heartstyles</h1>

<?php if (!$canViewResults): ?>
    <?php if ($reasonCannotView === 'user_not_found'): ?>
        <div class="message error"><?php echo $errorMessage; ?></div>
    <?php elseif ($reasonCannotView === 'no_self_assessment'): ?>
        <div class="message info">
            Для перегляду аналізу Heartstyles, спочатку пройдіть основне опитування про себе.
            <br><br>
            <a href="questionnaire_self.php" class="btn btn-primary">Пройти опитування</a>
        </div>
    <?php elseif ($reasonCannotView === 'profile_hidden'): ?>
        <div class="message info">Користувач <strong><?php echo htmlspecialchars($targetUsername); ?></strong> приховав свої результати.</div>
    <?php elseif ($reasonCannotView === 'other_no_self_assessment'): ?>
        <div class="message info">Користувач <strong><?php echo htmlspecialchars($targetUsername); ?></strong> ще не пройшов самооцінку. Результати Heartstyles недоступні.</div>
    <?php elseif ($reasonCannotView === 'admin_no_data'): ?>
        <div class="message warning"><strong>Режим Адміністратора:</strong> Для користувача <?php echo htmlspecialchars($targetUsername); ?> немає даних для аналізу Heartstyles (ані самооцінки, ані оцінок інших).</div>
    <?php else: ?>
         <div class="message error">Не вдалося відобразити результати Heartstyles.</div>
    <?php endif; ?>
<?php else: ?>
    <div class="message info">
        <strong>Важливо:</strong> Цей аналіз Heartstyles базується на відповідях з основного опитувальника, а не на спеціалізованому опитувальнику Heartstyles. Результати є інтерпретацією та показують відносні прояви поведінки на шкалі від <?php echo $minScaleOverall; ?> до <?php echo $maxScaleOverall; ?>. Сіра зона (заштрихована на діаграмах) відповідає значенням від <?php echo $normZoneMin; ?> до <?php echo $normZoneMax; ?> для ефективних стилів (бажано вищі значення) та від <?php echo $ineffectivenormZoneMin; ?> до <?php echo $ineffectivenormZoneMax; ?> для неефективних стилів (бажано нижчі значення).
    </div>

    <?php // FIX 2: Display message about insufficient other assessors if applicable ?>
    <?php if ($notEnoughOtherAssessorsMessage && $hasTargetSelfAnswers && !$hasTargetOtherAnswers && !$isAdmin): ?>
        <div class="message warning"><?php echo nl2br(htmlspecialchars($notEnoughOtherAssessorsMessage)); ?></div>
    <?php endif; ?>

    <div class="heartstyles-explanation">
        <h2>Розуміння Індикатора Heartstyles</h2>
        <p>Індикатор Heartstyles — це інструмент розвитку, а не оцінки. Він допомагає усвідомити свою поведінку, базуючись на власному сприйнятті та сприйнятті інших. Немає «хороших» чи «поганих» показників, однак є позитивні або негативні наслідки як для високих, так і для низьких результатів.</p>

        <h3>Чотири Квадрати Серця:</h3>
        <div class="quadrants-container">
            <div class="quadrant purpose"><strong>Цілеспрямованість (Голубий квадрат)</strong><br>Ефективна поведінка, фокус на собі. Розвиває характер, сприяє досягненню результатів та цілей через відкритість.</div>
            <div class="quadrant love"><strong>Любов (Червоний квадрат)</strong><br>Ефективна поведінка, фокус на інших. Описує пристрасть, турботу, вірність, повагу. Включає розвиток інших.</div>
            <div class="quadrant pride"><strong>Гординя (Зелений квадрат)</strong><br>Неефективна поведінка, фокус на собі. Самоспрямованість, заважає розкриттю потенціалу, залежить від самопрезентації.</div>
            <div class="quadrant fear"><strong>Страх (Оранжевий квадрат)</strong><br>Неефективна поведінка, фокус на інших. Страх критики, невдачі; утримує від розвитку, заважає жити повним життям.</div>
        </div>

        <h3>Інтерпретація Діаграм:</h3>
        <p>На діаграмах нижче представлені 16 моделей поведінки. Чим більший стовпчик (або далі точка від центру на радар-діаграмі), тим більш виражена дана поведінка.</p>
        <ul>
            <li><strong>Ефективні моделі поведінки (Голубий та Червоний квадрати):</strong> В ідеалі, ці показники мають бути вищими. Якщо показник низький (наприклад, в зоні <?php echo $normZoneMin; ?>-<?php echo $normZoneMax; ?> або нижче на шкалі <?php echo $minScaleOverall; ?>-<?php echo $maxScaleOverall; ?>), це вказує на можливість для розвитку.</li>
            <li><strong>Неефективні моделі поведінки (Зелений та Оранжевий квадрати):</strong> В ідеалі, ці показники мають бути нижчими. Якщо показник високий (наприклад, в зоні <?php echo $ineffectivenormZoneMin; ?>-<?php echo $ineffectivenormZoneMax; ?> або вище), це також вказує на можливість для розвитку та корекції.</li>
        </ul>
        <p>Усі 16 моделей поведінки взаємодіють. Висока оцінка за однією з неефективних моделей може нівелювати позитивний вплив високої оцінки за ефективною моделлю.</p>
         <p><em>Пам'ятайте: Оскільки ці діаграми базуються на загальному опитувальнику, "високі" та "низькі" показники тут слід інтерпретувати відносно середнього значення шкали (<?php echo ($minScaleOverall+$maxScaleOverall)/2; ?> для шкали <?php echo $minScaleOverall; ?>-<?php echo $maxScaleOverall; ?>) та у порівнянні з іншими вашими показниками.</em></p>
    </div>

    <?php
    function render_quadrant_charts($title, $dataSource, $quadrantDefs, $behaviorLabels, $minVal, $maxVal, $normMin, $normMax, $ineffectiveNormMin, $ineffectiveNormMax, $chartIdPrefix) { // Added ineffective norm params
        if (empty($dataSource)) {
            // If the dataSource is empty for "Середня Оцінка Інших Heartstyles" and $notEnoughOtherAssessorsMessage is NOT set (e.g. admin view with 0 others, or non-admin with 0 others)
            // then this generic message is fine.
            // If $notEnoughOtherAssessorsMessage WAS set, it would have been displayed already for non-admins.
            echo "<div class='message info'>Немає даних для аналізу '$title'.</div>";
            return;
        }
        echo "<h2>$title</h2>";
        echo "<div class='quadrant-grid-container'>";
        echo "<div class='effective-label'>ЕФЕКТИВНИЙ</div>";
        echo "<div class='focus-self-label'>ФОКУС НА СЕБЕ</div>";
        echo "<div class='focus-other-label'>ФОКУС НА ДРУГИХ</div>";
        echo "<div class='quadrant-chart-grid'>";

        foreach ($quadrantDefs as $qKey => $qData) {
            echo "<div class='quadrant-cell quadrant-{$qData['position']}'>";
            echo "<div class='quadrant-header' style='background-color: {$qData['color']}; color: {$qData['text_color']};'>";
            echo "<strong>{$qData['main_title']}</strong><br><small>{$qData['sub_title']}</small>";
            echo "</div>";
            echo "<div class='quadrant-canvas-container'><canvas id='{$chartIdPrefix}-{$qKey}'></canvas></div>";
            echo "</div>";
        }
        echo "</div>"; // .quadrant-chart-grid
        echo "<div class='ineffective-label'>НЕЕФЕКТИВНИЙ</div>";
        echo "</div>"; // .quadrant-grid-container
    }
    ?>

    <?php if ($hasTargetSelfAnswers) render_quadrant_charts('Самооцінка Heartstyles', $mappedSelfScores, $heartstylesQuadrants, $heartstylesBehaviorLabels, $minScaleOverall, $maxScaleOverall, $normZoneMin, $normZoneMax, $ineffectivenormZoneMin, $ineffectivenormZoneMax, 'self'); ?>
    <?php if ($hasTargetOtherAnswers) render_quadrant_charts('Середня Оцінка Інших Heartstyles', $mappedAvgOtherScores, $heartstylesQuadrants, $heartstylesBehaviorLabels, $minScaleOverall, $maxScaleOverall, $normZoneMin, $normZoneMax, $ineffectivenormZoneMin, $ineffectivenormZoneMax, 'avgOther'); ?>

    <div class="heartstyles-explanation"> 
        <?php 
        // Assuming heartstyles_interpretation_text.php provides general interpretation
        // You might want to make its content conditional or more nuanced if $hasTargetOtherAnswers is false due to count limit
        if (file_exists(__DIR__ . '/includes/heartstyles_interpretation_text.php')) {
            include __DIR__ . '/includes/heartstyles_interpretation_text.php';
        } else {
            echo "<p>Детальна інтерпретація буде доступна незабаром.</p>";
        }
        ?>
    </div>

    <?php
    $jsSelfScores = ($hasTargetSelfAnswers && !empty($mappedSelfScores)) ? $mappedSelfScores : null;
    $jsAvgOtherScores = ($hasTargetOtherAnswers && !empty($mappedAvgOtherScores)) ? $mappedAvgOtherScores : null;
    ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-annotation/2.2.1/chartjs-plugin-annotation.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const heartstylesQuadrantsJS = <?php echo json_encode($heartstylesQuadrants); ?>;
        const behaviorLabelsJS = <?php echo json_encode($heartstylesBehaviorLabels); ?>;

        const selfScoresJS = <?php echo json_encode($jsSelfScores); ?>;
        const avgOtherScoresJS = <?php echo json_encode($jsAvgOtherScores); ?>;

        const minVal = <?php echo $minScaleOverall; ?>;
        const maxVal = <?php echo $maxScaleOverall; ?>;
        const normMinPHP = <?php echo $normZoneMin; ?>; // Renamed to avoid conflict with function param
        const normMaxPHP = <?php echo $normZoneMax; ?>;
        const ineffectiveNormMinPHP = <?php echo $ineffectivenormZoneMin; ?>;
        const ineffectiveNormMaxPHP = <?php echo $ineffectivenormZoneMax; ?>;

        function createQuadrantBarChart(canvasId, quadrantKey, scoresForBehavior, chartTypeTitle) {
            const canvasElement = document.getElementById(canvasId);
            if (!canvasElement) {
                return;
            }
            if (!scoresForBehavior) { // This condition handles cases where $jsSelfScores or $jsAvgOtherScores is null
                const ctx = canvasElement.getContext('2d');
                ctx.clearRect(0, 0, canvasElement.width, canvasElement.height);
                ctx.font = "12px Arial";
                ctx.fillStyle = "#888";
                ctx.textAlign = "center";
                ctx.fillText("Немає даних для діаграми", canvasElement.width / 2, canvasElement.height / 2);
                return;
            }

            const qDetails = heartstylesQuadrantsJS[quadrantKey];
            if (!qDetails) {
                return;
            }

            const labels = qDetails.behaviors.map(b => behaviorLabelsJS[b] || b);
            const dataScores = qDetails.behaviors.map(b => {
                const score = scoresForBehavior[b];
                // Ensure nulls from PHP are treated as null in JS for Chart.js to skip them
                return (score !== undefined && score !== null) ? parseFloat(score.toFixed(1)) : null;
            });

            const hasNumericData = dataScores.some(score => typeof score === 'number' && !isNaN(score));
            if (!hasNumericData) {
                const ctx = canvasElement.getContext('2d');
                ctx.clearRect(0, 0, canvasElement.width, canvasElement.height);
                ctx.font = "12px Arial";
                ctx.fillStyle = "#888";
                ctx.textAlign = "center";
                ctx.fillText("Відсутні числові дані", canvasElement.width / 2, canvasElement.height / 2);
                return;
            }

            const isBottomQuadrant = qDetails.direction === 'down';

            // Determine which norm zone values to use for annotation based on quadrant type
            const currentNormMin = (qDetails.direction === 'up') ? normMinPHP : ineffectiveNormMinPHP;
            const currentNormMax = (qDetails.direction === 'up') ? normMaxPHP : ineffectiveNormMaxPHP;


            new Chart(canvasElement, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        data: dataScores,
                        backgroundColor: qDetails.bar_color,
                        borderColor: qDetails.color,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'x',
                    scales: {
                        x: { ticks: { font: { size: 9 } } },
                        y: {
                            beginAtZero: false, // Should be false if minVal can be > 0
                            min: minVal,
                            max: maxVal,
                            reverse: isBottomQuadrant,
                            ticks: { stepSize: 1 }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                             callbacks: {
                                label: function(context) {
                                    // Display N/A for null scores, otherwise formatted number
                                    return context.parsed.y !== null ? context.parsed.y.toFixed(1) : 'N/A';
                                }
                            }
                        },
                        annotation: {
                            annotations: {
                                normBox: {
                                    type: 'box',
                                    // Logic for yMin/yMax for annotation based on effective/ineffective
                                    // For effective (up), norm is normMinPHP-normMaxPHP (e.g. 1-4)
                                    // For ineffective (down, reversed axis), norm is ineffectiveNormMinPHP-ineffectiveNormMaxPHP (e.g. 4-7)
                                    // This highlights "low" for effective and "high" for ineffective as areas for attention.
                                    yMin: isBottomQuadrant ? ineffectiveNormMinPHP : normMinPHP,
                                    yMax: isBottomQuadrant ? ineffectiveNormMaxPHP : normMaxPHP,
                                    backgroundColor: 'rgba(200, 200, 200, 0.3)',
                                    borderColor: 'rgba(200, 200, 200, 0.5)',
                                    borderWidth: 1
                                }
                            }
                        }
                    }
                }
            });
        }

        if (selfScoresJS) {
            for (const qKey in heartstylesQuadrantsJS) {
                createQuadrantBarChart('self-' + qKey, qKey, selfScoresJS, 'Самооцінка');
            }
        } else {
             // Handle cases where selfScoresJS might be null even if $hasTargetSelfAnswers was true (e.g. all answers were null)
            for (const qKey in heartstylesQuadrantsJS) {
                const canvasId = 'self-' + qKey;
                const canvasElement = document.getElementById(canvasId);
                if(canvasElement) {
                    const ctx = canvasElement.getContext('2d');
                    ctx.clearRect(0, 0, canvasElement.width, canvasElement.height);
                    ctx.font = "12px Arial";
                    ctx.fillStyle = "#888";
                    ctx.textAlign = "center";
                    ctx.fillText("Немає даних самооцінки", canvasElement.width / 2, canvasElement.height / 2);
                }
            }
        }

        if (avgOtherScoresJS) {
            for (const qKey in heartstylesQuadrantsJS) {
                createQuadrantBarChart('avgOther-' + qKey, qKey, avgOtherScoresJS, 'Середня оцінка інших');
            }
        } else {
            // This part will be reached if $hasTargetOtherAnswers was false.
            // The PHP function render_quadrant_charts already handles empty $mappedAvgOtherScores
            // So, this JS fallback for avgOther might not be strictly necessary if PHP side handles it.
            // However, good for robustness if $jsAvgOtherScores is null for any reason.
            for (const qKey in heartstylesQuadrantsJS) {
                const canvasId = 'avgOther-' + qKey;
                const canvasElement = document.getElementById(canvasId);
                 if(canvasElement) {
                    const ctx = canvasElement.getContext('2d');
                    ctx.clearRect(0, 0, canvasElement.width, canvasElement.height);
                    ctx.font = "12px Arial";
                    ctx.fillStyle = "#888";
                    ctx.textAlign = "center";
                    // Message depends on why avgOtherScoresJS is null.
                    // If due to $notEnoughOtherAssessorsMessage, that's already shown in PHP.
                    // Otherwise, "Немає оцінок інших" is a general fallback.
                    ctx.fillText("Немає оцінок інших для діаграми", canvasElement.width / 2, canvasElement.height / 2);
                }
            }
        }
    });
    </script>
<?php endif; ?>

<link rel="stylesheet" href="assets/css/results.css">
<style>
    .heartstyles-explanation { background-color: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .quadrants-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin: 20px 0; }
    .quadrant { padding: 15px; border-radius: 6px; color: #fff; }
    .quadrant.purpose { background-color: #00AEEF; }
    .quadrant.love { background-color: #ED1C24; }
    .quadrant.pride { background-color: #8DC63F; }
    .quadrant.fear { background-color: #F7941D; }
    .quadrant strong { display: block; font-size: 1.1em; margin-bottom: 5px; }

    .message { padding: 15px 20px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
    .message.info { background-color: #e7f3fe; border-color: #d0e3f0; color: #31708f; }
    .message.error { background-color: #f2dede; border-color: #ebccd1; color: #a94442; }
    .message.warning { background-color: #fcf8e3; border-color: #faebcc; color: #8a6d3b; }
    .btn.btn-primary { background-color: #007bff; border-color: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; display: inline-block;}
    .btn.btn-primary:hover { background-color: #0056b3; border-color: #0056b3; }


    .quadrant-grid-container {
        display: grid;
        grid-template-columns: auto 1fr 1fr auto; 
        grid-template-rows: auto 1fr 1fr auto; 
        gap: 5px; 
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 8px;
        margin-bottom: 30px;
        position: relative; 
        background-color: #f8f8f8;
    }

    .quadrant-chart-grid {
        grid-column: 2 / 4; 
        grid-row: 2 / 4;    
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-rows: 1fr 1fr;
        gap: 10px; 
    }

    .quadrant-cell {
        display: flex;
        flex-direction: column;
        border: 1px solid #ddd;
        border-radius: 6px;
        overflow: hidden; 
    }
    .quadrant-header {
        padding: 8px;
        text-align: center;
        font-size: 0.9em;
    }
    .quadrant-header strong { font-size: 1.1em; display: block; }
    .quadrant-header small { font-size: 0.85em; }

    .quadrant-canvas-container {
        flex-grow: 1;
        position: relative; 
        padding: 10px;
        background-color: #fff; 
        min-height: 220px; 
    }
    
    .effective-label, .ineffective-label, .focus-self-label, .focus-other-label {
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: #555;
        font-size: 0.9em;
        text-transform: uppercase;
        background-color: #e9e9e9;
        padding: 5px;
        border-radius: 4px;
    }
    .effective-label { grid-column: 2 / 4; grid-row: 1 / 2; }
    .ineffective-label { grid-column: 2 / 4; grid-row: 4 / 5; }
    .focus-self-label {
        grid-column: 1 / 2; grid-row: 2 / 4;
        writing-mode: vertical-rl; text-orientation: mixed; transform: rotate(180deg);
    }
    .focus-other-label {
        grid-column: 4 / 5; grid-row: 2 / 4;
        writing-mode: vertical-rl; text-orientation: mixed;
    }
.heartstyles-detailed-interpretation {
    background-color: #fdfdfd;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    font-size: 0.98em;
    line-height: 1.65;
}
.heartstyles-detailed-interpretation h2 {
    text-align: center;
    color: #333;
    margin-bottom: 20px;
}
.heartstyles-detailed-interpretation h3 {
    color: #444;
    margin-top: 25px;
    margin-bottom: 10px;
    border-bottom: 1px solid #eee;
    padding-bottom: 5px;
}
.heartstyles-detailed-interpretation h4 {
    color: #555;
    margin-top: 15px;
    margin-bottom: 8px;
}
.heartstyles-detailed-interpretation ul, .heartstyles-detailed-interpretation ol {
    padding-left: 25px;
    margin-bottom: 15px;
}
.heartstyles-detailed-interpretation li {
    margin-bottom: 8px;
}
.heartstyles-detailed-interpretation strong {
    color: #2c3e50;
}
.heartstyles-detailed-interpretation em {
    color: #7f8c8d;
    font-style: italic;
}
</style>

<?php
include __DIR__ . '/includes/footer.php';
?>