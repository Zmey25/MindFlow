<?php
// includes/results_badges.php
// Очікує змінні:
// $userBadgesSummary (масив об'єктів/масивів з даними бейджів, наприклад [{'badgeId': 'id', 'score': 75}, ...])
// $allDefinedBadges (масив з badges.json для отримання назв та описів)

if (!isset($userBadgesSummary) || !is_array($userBadgesSummary) || empty($userBadgesSummary) ||
    !isset($allDefinedBadges) || !is_array($allDefinedBadges) || empty($allDefinedBadges)) {
    return; // Немає даних для відображення
}

function getBadgeColorClassByScore(int $score): string {
    if ($score >= 90) return 'gold';      // 90-100 - Найкращий
    if ($score >= 80) return 'silver';    // 80-89 - Дуже добре
    if ($score >= 70) return 'purple';    // 70-79 - Добре+
    if ($score >= 60) return 'green';     // 60-69 - Добре
    if ($score >= 50) return 'teal';    // 50-59 - Середній+
    if ($score >= 40) return 'yellow';      // 40-49 - Середній
    if ($score >= 30) return 'orange';    // 30-39 - Нижче середнього
    if ($score >= 20) return 'brown';     // 20-29 - Погано
    if ($score >= 10) return 'gray';      // 10-19 - Дуже погано
    return 'red';                         // 0-9   - Найгірший
}

// Створюємо мапу для швидкого доступу до деталей бейджа по ID
$badgesDetailsMap = [];
foreach ($allDefinedBadges as $badgeDef) {
    if (isset($badgeDef['badgeId'])) {
        $badgesDetailsMap[$badgeDef['badgeId']] = [
            'name' => $badgeDef['badgeName'] ?? 'Невідомий бейдж',
            'description' => $badgeDef['badgeDescription'] ?? 'Опис відсутній.'
        ];
    }
}

?>
<div class="badges-block">
    <h2>Мої Показники</h2>
    <ul class="badges-list">
        <?php foreach ($userBadgesSummary as $badgeData): ?>
            <?php
                $badgeId = $badgeData['badgeId'];
                $score = (int)($badgeData['score'] ?? 0);

                if (!isset($badgesDetailsMap[$badgeId])) {
                    // Пропустити бейдж, якщо для нього немає визначення (назви/опису)
                    // Можна додати логування помилки тут, якщо потрібно
                    // error_log("Warning: Badge definition not found for ID: " . $badgeId);
                    continue;
                }

                $badgeInfo = $badgesDetailsMap[$badgeId];
                $name = htmlspecialchars($badgeInfo['name']);
                $description = htmlspecialchars($badgeInfo['description']);
                $colorClass = getBadgeColorClassByScore($score);
            ?>
            <li class="badge-item tooltip-container">
                <div class="badge-icon-wrapper badge-<?php echo $colorClass; ?>">
                   <i class="fas fa-medal"></i>
                    <span class="badge-score"><?php echo $score; ?></span>
                </div>
                <span class="badge-name"><?php echo $name; ?></span>
                <div class="tooltip-text">
                    <strong><?php echo $name; ?></strong>
                    <hr>
                    <?php echo $description; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>