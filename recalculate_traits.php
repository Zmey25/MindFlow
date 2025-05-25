<?php // recalculate_traits.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/questionnaire_logic.php';
require_once __DIR__ . '/includes/trait_calculator.php';
require_once __DIR__ . '/includes/badge_calculator.php';

if (!defined('ADMINS_FILE_PATH')) {
    define('ADMINS_FILE_PATH', __DIR__ . '/data/admins.json');
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = false;
if (isUserLoggedIn()) {
    $currentUserId = $_SESSION['user_id'];
    $adminData = readJsonFile(ADMINS_FILE_PATH);
    if (isset($adminData['admin_ids']) && in_array($currentUserId, $adminData['admin_ids'])) {
        $isAdmin = true;
    }
}

if (!$isAdmin) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Доступ заборонено. Потрібні права адміністратора.']);
    exit;
}

header('Content-Type: application/json');

$username = trim($_GET['username'] ?? '');
if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Параметр username не вказано.']);
    exit;
}

// Ці $internalMessages використовуються для логіки визначення статусу оновлення
$internalMessages = []; // Для внутрішнього відстеження детальних повідомлень про зміни
$overallSuccess = true;

// --- Розрахунок Трітів (Achievements) ---
$traitsCalculationResult = calculateEarnedTraits($username);
if ($traitsCalculationResult['success']) {
    $internalMessages[] = $traitsCalculationResult['message']; // "Розрахунок трітів для '{$username}' завершено."
} else {
    $internalMessages[] = "Помилка розрахунку трітів: " . ($traitsCalculationResult['message'] ?? 'Невідома помилка');
    // $overallSuccess = false; // Вирішіть, чи є помилка трітів фатальною
}

// --- Розрахунок Бейджів ---
$badgesCalculationResult = calculateUserBadges($username);
if ($badgesCalculationResult['success']) {
    $internalMessages[] = $badgesCalculationResult['message']; // "Розрахунок бейджів для '{$username}' завершено."
} else {
    $internalMessages[] = "Помилка розрахунку бейджів: " . ($badgesCalculationResult['message'] ?? 'Невідома помилка');
    // $overallSuccess = false; // Вирішіть, чи є помилка бейджів фатальною
}

$targetUserData = loadUserData($username);

if (empty($targetUserData) && !file_exists(getUserAnswersFilePath($username))) {
     echo json_encode([
        'success' => false,
        'message' => "Профіль користувача '".htmlspecialchars($username)."' не знайдено. Перерахунок неможливий."
    ]);
     exit;
}
if (!isset($targetUserData['achievements'])) { $targetUserData['achievements'] = []; }
if (!isset($targetUserData['badges_summary'])) { $targetUserData['badges_summary'] = []; }
if (!isset($targetUserData['self'])) { $targetUserData['self'] = null; }
if (!isset($targetUserData['others'])) { $targetUserData['others'] = []; }

$changesMade = false;
$traitsUpdated = false; // Прапорець для статусу оновлення трітів
$badgesUpdated = false; // Прапорець для статусу оновлення бейджів

if ($traitsCalculationResult['success']) {
    $existingTraitIds = is_array($targetUserData['achievements'])
                       ? array_column($targetUserData['achievements'], 'id')
                       : [];
    $newTraitIds = $traitsCalculationResult['earned_ids'];
    sort($existingTraitIds);
    sort($newTraitIds);

    if ($existingTraitIds !== $newTraitIds) {
        $targetUserData['achievements'] = $traitsCalculationResult['earned_traits'];
        $internalMessages[] = "Список трітів оновлено."; // Для внутрішньої логіки
        $traitsUpdated = true;
        $changesMade = true;
    } else {
        $internalMessages[] = "Список трітів не змінився."; // Для внутрішньої логіки
    }
}

if ($badgesCalculationResult['success']) {
    $currentBadgesSummary = $targetUserData['badges_summary'] ?? [];
    $newBadgesSummary = $badgesCalculationResult['badges_summary'];

    $currentBadgesMap = [];
    if(is_array($currentBadgesSummary)) {
        foreach ($currentBadgesSummary as $badge) {
            if (isset($badge['badgeId'])) {
                $currentBadgesMap[$badge['badgeId']] = $badge['score'] ?? null;
            }
        }
    }
    $newBadgesMap = [];
    if(is_array($newBadgesSummary)) {
        foreach ($newBadgesSummary as $badge) {
            if (isset($badge['badgeId'])) {
                $newBadgesMap[$badge['badgeId']] = $badge['score'] ?? null;
            }
        }
    }
    ksort($currentBadgesMap);
    ksort($newBadgesMap);

    if ($currentBadgesMap !== $newBadgesMap) {
        $targetUserData['badges_summary'] = $newBadgesSummary;
        $internalMessages[] = "Підсумки по бейджам оновлено."; // Для внутрішньої логіки
        $badgesUpdated = true;
        $changesMade = true;
    } else {
        $internalMessages[] = "Підсумки по бейджам не змінилися."; // Для внутрішньої логіки
    }
}

$saveStatusMessage = "";
if ($changesMade) {
    if (!saveUserData($username, $targetUserData)) {
        $overallSuccess = false;
        $internalMessages[] = "ПОМИЛКА: Збереження оновлених даних для користувача '{$username}' не вдалося.";
        $saveStatusMessage = "Збереження: ПОМИЛКА запису!";
    } else {
        $internalMessages[] = "Дані користувача '{$username}' успішно збережено.";
        $saveStatusMessage = "Збереження: Дані оновлено.";
    }
} else {
    $internalMessages[] = "Змін у даних користувача '{$username}' не виявлено, збереження не потрібне.";
    $saveStatusMessage = "Збереження: Змін немає.";
}

// --- Формування фінального повідомлення для JSON ---
$usernameDisplay = htmlspecialchars($username);
$finalMessageParts = [];
$statusPrefix = "Успіх";

// Тріти
if ($traitsCalculationResult['success']) {
    $traitCount = count($traitsCalculationResult['earned_ids']);
    $traitStatusText = $traitsUpdated ? "(оновлено)" : "(без змін)";
    $finalMessageParts[] = "Тріти: {$traitCount} шт. {$traitStatusText}";
} else {
    $finalMessageParts[] = "Тріти: помилка розрахунку (" . htmlspecialchars($traitsCalculationResult['message'] ?? 'деталі невідомі') . ")";
    $statusPrefix = "Увага"; // або "Помилка", якщо це критично
    $overallSuccess = false; // Переконуємось, що overallSuccess відображає помилку
}

// Бейджі
if ($badgesCalculationResult['success']) {
    $badgeCount = count($badgesCalculationResult['badges_summary']);
    $badgeStatusText = $badgesUpdated ? "(оновлено)" : "(без змін)";
    $finalMessageParts[] = "Бейджі: {$badgeCount} шт. {$badgeStatusText}";
} else {
    $finalMessageParts[] = "Бейджі: помилка розрахунку (" . htmlspecialchars($badgesCalculationResult['message'] ?? 'деталі невідомі') . ")";
    if ($statusPrefix !== "Помилка") $statusPrefix = "Увага";
    $overallSuccess = false; // Переконуємось, що overallSuccess відображає помилку
}

// Додаємо статус збереження
if (!empty($saveStatusMessage)) {
    $finalMessageParts[] = $saveStatusMessage;
}
if (!$overallSuccess && $statusPrefix === "Успіх") { // Якщо якась з операцій не вдалась, але не була розрахунковою
    $statusPrefix = "Увага";
}
if (strpos($saveStatusMessage, "ПОМИЛКА") !== false) {
    $statusPrefix = "Помилка";
}


$finalMessage = "{$statusPrefix} для '{$usernameDisplay}': " . implode(" | ", $finalMessageParts);
if (empty($targetUserData) && !file_exists(getUserAnswersFilePath($username))) { // Цей блок вже є вище, але для безпеки
    $finalMessage = "Профіль користувача '".htmlspecialchars($username)."' не знайдено.";
}


echo json_encode([
    'success' => $overallSuccess,
    'message' => $finalMessage,
    'earned_traits_count' => $traitsCalculationResult['success'] ? count($traitsCalculationResult['earned_ids']) : 0, // Кількість ID трітів
    'earned_trait_ids' => $traitsCalculationResult['success'] ? $traitsCalculationResult['earned_ids'] : [],
    'badges_calculated_count' => $badgesCalculationResult['success'] ? count($badgesCalculationResult['badges_summary']) : 0,
    'badges_summary_preview' => $badgesCalculationResult['success'] ? $badgesCalculationResult['badges_summary'] : []
]);
exit;

?>