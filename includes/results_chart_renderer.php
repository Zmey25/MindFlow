<?php
// includes/results_chart_renderer.php
// Цей файл очікує, що наступні змінні вже визначені:
// $chartDataByCategory (масив даних для всіх діаграм)
// $categoryScales (масив шкал для всіх діаграм)
// $fullQuestionTexts (масив повних текстів питань для тултіпів)

// Перевірка наявності необхідних змінних (необов'язково, але корисно для відладки)
if (!isset($chartDataByCategory, $categoryScales, $fullQuestionTexts)) {
     echo "<script>console.error('Помилка: Недостатньо даних для рендерингу діаграм.');</script>";
     return;
}
?>
<!-- Підключення Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- --- JavaScript для малювання діаграм --- -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Отримуємо дані, підготовлені в PHP та передані сюди
    const chartDataByCategory = <?php echo json_encode($chartDataByCategory); ?>;
    const categoryScales = <?php echo json_encode($categoryScales); ?>;
    const fullQuestionTexts = <?php echo json_encode($fullQuestionTexts); ?>;

    // Проходимо по кожній категорії, для якої є дані діаграми
    for (const categoryId in chartDataByCategory) {
        const canvas = document.getElementById(`chart-${categoryId}`);
        if (canvas) { // Перевіряємо, чи існує елемент canvas
            const ctx = canvas.getContext('2d');
            const categoryData = chartDataByCategory[categoryId];
            if (!categoryData || !categoryData.datasets || categoryData.datasets.length === 0) {
                 console.warn(`No valid datasets found for category ${categoryId}`);
                 continue; // Пропускаємо, якщо немає даних або наборів даних
            }

            const scale = categoryScales[categoryId] || { min: 1, max: 7 }; // Використовуємо шкалу категорії або дефолтну
            const currentFullTexts = fullQuestionTexts[categoryId] || []; // Повні тексти питань для тултіпів

            // --- Конфігурація діаграми Chart.js ---
            const config = {
                type: 'radar',
                data: {
                    labels: categoryData.labels, // Мітки питань (короткі)
                    datasets: categoryData.datasets // Набори даних (Моя оцінка, Оцінка інших)
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    elements: {
                        line: { borderWidth: 2 },
                        point: { radius: 4, hoverRadius: 7, hitRadius: 15 }
                    },
                    scales: {
                        r: {
                            min: Math.max(0, Math.floor(scale.min - 0.5)),
                            max: scale.max,
                            ticks: {
                                stepSize: 1,
                                backdropColor: 'rgba(255, 255, 255, 0.75)',
                                color: '#666'
                            },
                            angleLines: { color: 'rgba(0, 0, 0, 0.1)' },
                            grid: { color: 'rgba(0, 0, 0, 0.1)' },
                            pointLabels: {
                                display: categoryData.showPointLabels,
                                font: { size: 11 },
                                color: '#757575',

                                callback: function(label, index) {
                                    const maxLengthPerLine = 12; // Наприклад, 12 символів на рядок
                                    if (typeof label === 'string' && label.length > maxLengthPerLine) {
                                        const words = label.split(' ');
                                        const lines = [];
                                        let currentLine = '';
                                        words.forEach(word => {
                                            if ((currentLine + ' ' + word).trim().length > maxLengthPerLine && currentLine.length > 0) {
                                                lines.push(currentLine.trim());
                                                currentLine = word;
                                            } else {
                                                currentLine += (currentLine.length === 0 ? '' : ' ') + word;
                                            }
                                        });
                                        if (currentLine.length > 0) {
                                            lines.push(currentLine.trim());
                                        }
                                        return lines; // Chart.js v4 обробить масив рядків
                                    }
                                    return label;
                                }

                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { boxWidth: 20, padding: 15 }
                        },
                        tooltip: {
                             bodyFont: { size: 13 },
                             padding: 10,
                            callbacks: {
                                title: function(tooltipItems) { return ''; },
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) { label += ': '; }
                                    if (context.parsed.r !== null && typeof context.parsed.r !== 'undefined') {
                                        label += `${context.parsed.r}`;
                                    } else {
                                        label += 'N/A';
                                    }
                                    return label;
                                },
                                afterLabel: function(context) {
                                     const questionIndex = context.dataIndex;
                                     const fullText = currentFullTexts[questionIndex] || '';
                                     const maxLineLength = 45;
                                     const words = fullText.split(' ');
                                     const lines = [];
                                     let currentLine = '';
                                     words.forEach(word => {
                                         if ((currentLine + word).length > maxLineLength && currentLine.length > 0) {
                                             lines.push(currentLine.trim());
                                             currentLine = word;
                                         } else {
                                             currentLine += (currentLine.length === 0 ? '' : ' ') + word;
                                         }
                                     });
                                      if (currentLine.length > 0) { lines.push(currentLine.trim()); }
                                     return lines.length > 0 ? lines : fullText;
                                }
                            }
                        }
                    }
                }
            };
            // Створюємо нову діаграму
            new Chart(ctx, config);
        } else {
             console.warn(`Canvas element not found for chart ID: chart-${categoryId}`);
        }
    }

});
</script>