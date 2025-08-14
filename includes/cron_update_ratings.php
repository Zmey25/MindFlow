<?php
// Скрипт для запуску через cron для оновлення даних рейтингів

// Встановлюємо робочу директорію на корінь проєкту
chdir(dirname(__DIR__));

// Підключаємо файл з функціями
require_once 'includes/functions.php';

// Виконуємо функцію генерації даних
$result = generateRatingsData();

// Виводимо результат для логування в cron
echo date('Y-m-d H:i:s') . ' - ' . $result['message'] . PHP_EOL;

// Записуємо результат в лог-файл проєкту
custom_log($result['message'], 'cron_ratings');

exit($result['success'] ? 0 : 1);
