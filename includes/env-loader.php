<?php
/**
 * Файл для завантаження змінних оточення з .env файлу
 * 
 * Цей файл містить функцію для зчитування .env файлу та встановлення 
 * змінних оточення для використання в застосунку.
 */

/**
 * Завантажує змінні оточення з .env файлу
 * 
 * @param string $path Шлях до .env файлу
 * @return bool True, якщо файл було успішно завантажено
 */
function loadEnv($path) {
    // Перевіряємо, чи існує файл
    if (!file_exists($path)) {
        return false;
    }

    // Зчитуємо файл
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return false;
    }

    // Обробляємо кожен рядок
    foreach ($lines as $line) {
        // Пропускаємо коментарі
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Розбиваємо рядок на ім'я та значення
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);

            // Видаляємо лапки, якщо вони є
            if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            }

            // Встановлюємо змінну оточення
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    return true;
}