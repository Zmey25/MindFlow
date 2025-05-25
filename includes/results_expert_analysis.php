<?php
// includes/results_expert_analysis.php
// Очікує змінну $expertAnalysisData (може бути рядком або структурованим масивом)

if (!isset($expertAnalysisData) || empty($expertAnalysisData)) {
    return; // Нічого не виводимо, якщо даних немає
}

// Демонструємо обробку різних форматів $expertAnalysisData
$analysisHtml = '';
$analystName = '';

if (is_string($expertAnalysisData)) {
    // Якщо це просто рядок
    $analysisHtml = '<p>' . nl2br(htmlspecialchars($expertAnalysisData)) . '</p>';
} elseif (is_array($expertAnalysisData)) {
    // Якщо це структурований масив (приклад)
    if (!empty($expertAnalysisData['summary'])) {
        $analysisHtml .= '<h3>Загальний висновок</h3><p>' . nl2br(htmlspecialchars($expertAnalysisData['summary'])) . '</p>';
    }
    if (!empty($expertAnalysisData['recommendations']) && is_array($expertAnalysisData['recommendations'])) {
        $analysisHtml .= '<h3>Рекомендації</h3><ul>';
        foreach ($expertAnalysisData['recommendations'] as $rec) {
            $analysisHtml .= '<li>' . htmlspecialchars($rec) . '</li>';
        }
        $analysisHtml .= '</ul>';
    }
    // Можна додати обробку інших полів, наприклад, "strengths", "weaknesses"
    if (!empty($expertAnalysisData['analyst'])) {
        $analystName = htmlspecialchars($expertAnalysisData['analyst']);
    }
} else {
    // Невідомий формат даних
     $analysisHtml = '<p class="message error">Не вдалося відобразити дані аналізу.</p>';
}

?>
<div class="expert-analysis-block">
    <h2>Аналіз від експерта</h2>
    <div class="expert-analysis-content">
        <?php echo $analysisHtml; // Виводимо згенерований HTML ?>
    </div>
    <?php if (!empty($analystName)): ?>
        <p class="expert-analyst">Аналіз підготував: <?php echo $analystName; ?></p>
    <?php endif; ?>
</div>