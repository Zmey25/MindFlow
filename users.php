<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php'; // Для USERS_FILE_PATH
require_once __DIR__ . '/includes/functions.php'; // Для readJsonFile, htmlspecialchars
$pageTitle = "Список користувачів ";
// Ініціалізація
$allUsers = readJsonFile(USERS_FILE_PATH);
if ($allUsers === null) {
    $allUsers = []; // Якщо файл не існує або помилка читання
}

$searchQuery = trim($_GET['search_query'] ?? '');
$usersPerPage = 15; // Кількість користувачів на сторінці
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
// Фільтрація користувачів
$filteredUsers = $allUsers;
if (!empty($searchQuery)) {
    $filteredUsers = array_filter($allUsers, function($user) use ($searchQuery) {
        $searchLower = mb_strtolower($searchQuery);
        $username = mb_strtolower($user['username'] ?? '');
        $firstName = mb_strtolower($user['first_name'] ?? '');
        $lastName = mb_strtolower($user['last_name'] ?? '');
        return strpos($username, $searchLower) !== false ||
               strpos($firstName, $searchLower) !== false ||
               strpos($lastName, $searchLower) !== false;
    });
}

if (!empty($filteredUsers)) {
    shuffle($filteredUsers);
}

$totalUsers = count($filteredUsers);
$totalPages = ($usersPerPage > 0 && $totalUsers > 0) ? ceil($totalUsers / $usersPerPage) : 1;
$currentPage = max(1, min($currentPage, $totalPages));
$offset = ($currentPage - 1) * $usersPerPage;
// Важливо: array_values потрібен тут, щоб скинути ключі після array_filter і shuffle
// для коректної роботи array_slice
$paginatedUsers = ($usersPerPage > 0) ? array_slice(array_values($filteredUsers), $offset, $usersPerPage) : array_values($filteredUsers);
include __DIR__ . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список користувачів</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f7f6;
            color: #333;
        }
        .container {
            max-width: 960px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }

        /* Search Form */
        .search-form {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            gap: 8px; /* Трохи зменшимо проміжок */
            flex-wrap: wrap; /* Дозволяє переносити, якщо не вміщується */
        }
        .search-form label {
            font-weight: bold;
            margin-right: 4px; /* Трохи зменшимо */
            white-space: nowrap; /* Щоб "Пошук:" не переносилось */
        }
        .search-form input[type="search"] {
            flex-grow: 1;
            padding: 9px; /* Трохи зменшимо */
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95em; /* Трохи зменшимо */
            min-width: 140px; /* Зменшено мінімальну ширину */
        }

        /* Buttons */
        .btn {
            padding: 9px 13px; /* Трохи зменшимо */
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em; /* Трохи зменшимо */
            transition: background-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            display: inline-block;
            text-align: center;
            white-space: nowrap; /* Щоб текст кнопки не переносився */
        }
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .cancel-btn {
            background-color: #e0e0e0;
            color: #333;
        }
        .cancel-btn:hover {
            background-color: #cccccc;
        }
        /* Кнопка "Дивитись" в таблиці */
        td .btn {
            background-color: #5cb85c;
            color: white;
            padding: 6px 10px; /* Зберігаємо менший розмір для кнопок в таблиці */
            font-size: 0.85em;
        }
        td .btn:hover {
            background-color: #4cae4c;
        }


        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            table-layout: fixed; /* Допомагає з управлінням шириною колонок та переносом */
        }
        th, td {
            border: 1px solid #e0e0e0;
            padding: 10px; /* Трохи зменшимо */
            text-align: left;
            vertical-align: middle;
            word-wrap: break-word; /* Для переносу довгих слів */
            overflow-wrap: break-word; /* Сучасний аналог word-wrap */
        }
        th {
            background-color: #f0f2f5;
            font-weight: bold;
            color: #555;
            text-align: center; /* Заголовки по центру */
        }
        /* Специфічна ширина для колонки дій, щоб інші могли розтягуватися */
        th.actions-cell, td.actions-cell {
             width: 100px; /* Фіксована ширина для колонки з кнопкою "Дивитись" */
             text-align: center;
        }
        /* Для інших колонок можна задати приблизні відсотки, якщо потрібно, але table-layout: fixed; і word-wrap мають допомогти */
        /* th:nth-child(1), td:nth-child(1) { width: 35%; } /* Логін */
        /* th:nth-child(2), td:nth-child(2) { width: 30%; } /* Ім'я */
        /* th:nth-child(3), td:nth-child(3) { width: 35%; } /* Прізвище */


        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tbody tr:hover {
            background-color: #f1f1f1;
        }


        /* Pagination */
        .pagination {
            text-align: center;
            margin: 25px 0;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 3px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #3498db;
            transition: background-color 0.2s, color 0.2s;
        }
        .pagination a:hover {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }
        .pagination .current-page {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
            font-weight: bold;
        }
        .pagination .disabled {
            color: #aaa;
            border-color: #eee;
            cursor: not-allowed;
        }
        .pagination .disabled:hover {
            background-color: transparent;
            color: #aaa;
        }

        /* Informational Text */
        .info-text {
            text-align: center;
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 10px;
        }
        p {
            margin-bottom: 1em;
        }

        /* Responsive adjustments for very small screens */
        @media (max-width: 480px) {
            .search-form {
                flex-direction: column; /* Складаємо елементи пошуку в стовпчик */
                align-items: stretch; /* Розтягуємо елементи на всю ширину */
            }
            .search-form input[type="search"],
            .search-form .btn {
                width: 100%; /* Займають всю ширину в стовпчику */
                box-sizing: border-box; /* Щоб padding не збільшував загальну ширину */
                margin-bottom: 8px; /* Додаємо відступ між елементами в стовпчику */
            }
            .search-form .cancel-btn {
                margin-left: 0; /* Скидаємо відступ для кнопки скасування */
            }
            .search-form label {
                margin-bottom: 5px; /* Відступ для лейбла */
                text-align: left;
            }

            /* Зменшуємо padding в таблиці для економії місця */
            th, td {
                padding: 8px;
                font-size: 0.9em;
            }
            td .btn {
                padding: 5px 8px;
                font-size: 0.8em;
            }
             .pagination a, .pagination span {
                padding: 6px 9px;
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Список користувачів</h1>

        <!-- Пошук -->
        <form action="users.php" method="GET" class="search-form">
             <label for="search_query">Пошук:</label>
             <input type="search" id="search_query" name="search_query" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Логін, ім'я або прізвище...">
             <button type="submit" class="btn btn-primary">Знайти</button>
             <?php if (!empty($searchQuery)): ?>
                <a href="users.php" class="btn cancel-btn">Скинути</a>
             <?php endif; ?>
        </form>

        <?php if ($totalUsers > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Логін</th>
                        <th>Ім'я</th>
                        <th>Прізвище</th>
                        <th class="actions-cell">Результат</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginatedUsers as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['first_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($user['last_name'] ?? '-'); ?></td>
                        <td class="actions-cell">
                            <a href="results.php?user=<?php echo urlencode($user['username']); ?>" class="btn" target="_blank">Дивитись</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Пагінація -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                 <?php
                    $queryParams = ['search_query' => $searchQuery];
                    if ((int)$currentPage > 1) {
                        echo '<a href="users.php?' . http_build_query(array_merge($queryParams, ['page' => (int)$currentPage - 1])) . '">« Попередня</a>';
                    } else {
                        echo '<span class="disabled">« Попередня</span>';
                    }

                    for ($i = 1; $i <= $totalPages; $i++) {
                        if ($i == $currentPage) {
                            echo '<span class="current-page">' . $i . '</span>';
                        } else {
                            echo '<a href="users.php?' . http_build_query(array_merge($queryParams, ['page' => $i])) . '">' . $i . '</a>';
                        }
                    }

                    if ((int)$currentPage < $totalPages) {
                        echo '<a href="users.php?' . http_build_query(array_merge($queryParams, ['page' => (int)$currentPage + 1])) . '">Наступна »</a>';
                    } else {
                         echo '<span class="disabled">Наступна »</span>';
                    }
                 ?>
            </div>
            <p class="info-text">Показано <?php echo count($paginatedUsers); ?> з <?php echo $totalUsers; ?> користувачів.</p>
            <?php endif; ?>

        <?php else: ?>
            <p><?php echo !empty($searchQuery) ? 'Користувачів за вашим запитом не знайдено.' : 'Немає зареєстрованих користувачів. Список тимчасово вимкнений.'; ?></p>
        <?php endif; ?>

    </div> <!-- /.container -->

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>