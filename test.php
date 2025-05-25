<?php
// Увімкніть показ всіх помилок для тестування (не на продакшені!)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Допоміжна функція для українських закінчень ---
if (!function_exists('getUkrainianNounEnding')) {
    function getUkrainianNounEnding(int $number, string $form1, string $form2, string $form5): string {
        $number = abs($number) % 100;
        $num = $number % 10;
        if ($number > 10 && $number < 20) return $form5;
        if ($num > 1 && $num < 5) return $form2;
        if ($num == 1) return $form1;
        return $form5;
    }
}

echo "Тестування функції...<br>";
$testNumber = 1;
$result = getUkrainianNounEnding($testNumber, 'людини', 'людей', 'людей');
echo "Результат для $testNumber: " . $result;
echo "<br>Тестування завершено.";
?>