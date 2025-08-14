<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = "Список користувачів";

$allUsers = readJsonFile(USERS_FILE_PATH);
if ($allUsers === null) {
    $allUsers = [];
}

$searchQuery = trim($_GET['search_query'] ?? '');
$usersPerPage = 15;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

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
    usort($filteredUsers, function($a, $b) {
        return strcasecmp($a['username'], $b['username']);
    });
}

$totalUsers = count($filteredUsers);
$totalPages = ($usersPerPage > 0 && $totalUsers > 0) ? ceil($totalUsers / $usersPerPage) : 1;
$currentPage = max(1, min($currentPage, $totalPages));
$offset = ($currentPage - 1) * $usersPerPage;

$paginatedUsers = ($usersPerPage > 0) ? array_slice($filteredUsers, $offset, $usersPerPage) : $filteredUsers;
include __DIR__ . '/includes/header.php';
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список користувачів</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background-color: #f4f7f6; color: #333; }
        .container { max-width: 960px; margin: 20px auto; padding: 20px; background-color: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 8px; }
        h1 { color: #2c3e50; margin-bottom: 20px; text-align: center; }
        .ratings-link { text-align: center; margin-bottom: 25px; padding: 12px; background-color: #eaf2f8; border-radius: 5px; }
        .ratings-link a { font-weight: bold; text-decoration: none; color: #2980b9; }
        .ratings-link a:hover { text-decoration: underline; }
        .search-form { display: flex; align-items: center; margin-bottom: 25px; gap: 8px; flex-wrap: wrap; }
        .search-form label { font-weight: bold; margin-right: 4px; white-space: nowrap; }
        .search-form input[type="search"] { flex-grow: 1; padding: 9px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.95em; min-width: 140px; }
        .btn { padding: 9px 13px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9em; transition: background-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out; display: inline-block; text-align: center; white-space: nowrap; }
        .btn-primary { background-color: #3498db; color: white; }
        .btn-primary:hover { background-color: #2980b9; }
        .cancel-btn { background-color: #e0e0e0; color: #333; }
        .cancel-btn:hover { background-color: #cccccc; }
        td .btn { padding: 6px 10px; font-size: 0.85em; }
        td .btn-results { background-color: #5cb85c; color: white; margin: 5px; }
        td .btn-results:hover { background-color: #4cae4c; }
        td .btn-test { background-color: #f0ad4e; color: white; margin: 5px; }
        td .btn-test:hover { background-color: #ec971f; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; table-layout: fixed; }
        th, td { border: 1px solid #e0e0e0; padding: 10px; text-align: left; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; }
        th { background-color: #f0f2f5; font-weight: bold; color: #555; text-align: center; }
        th.actions-cell, td.actions-cell { width: 120px; text-align: center; }
        tbody tr:nth-child(even) { background-color: #f9f9f9; }
        tbody tr:hover { background-color: #f1f1f1; }
        .pagination { text-align: center; margin: 25px 0; }
        .pagination a, .pagination span { display: inline-block; padding: 8px 12px; margin: 0 3px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #3498db; }
        .pagination a:hover { background-color: #3498db; color: white; border-color: #3498db; }
        .pagination .current-page { background-color: #3498db; color: white; border-color: #3498db; font-weight: bold; }
        .pagination .disabled { color: #aaa; border-color: #eee; cursor: not-allowed; }
        .info-text { text-align: center; font-size: 0.9em; color: #6c757d; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Список користувачів</h1>

        <div class="ratings-link">
            Переглянути <a href="ratings.php">рейтинг користувачів</a> за результатами тестів.
        </div>

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
                        <th class="actions-cell">Дії</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginatedUsers as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['first_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($user['last_name'] ?? '-'); ?></td>
                        <td class="actions-cell">
                            <a href="results.php?user=<?php echo urlencode($user['username']); ?>" class="btn btn-results" title="Дивитись результат">Результат</a>
                            <a href="get_test.php?user=<?php echo urlencode($user['username']); ?>" class="btn btn-test" title="Пройти тест">Тест</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                 <?php
                    $queryParams = ['search_query' => $searchQuery];
                    if ((int)$currentPage > 1) {
                        echo '<a href="users.php?' . http_build_query(array_merge($queryParams, ['page' => (int)$currentPage - 1])) . '">«</a>';
                    }
                    for ($i = 1; $i <= $totalPages; $i++) {
                        if ($i == $currentPage) {
                            echo '<span class="current-page">' . $i . '</span>';
                        } else {
                            echo '<a href="users.php?' . http_build_query(array_merge($queryParams, ['page' => $i])) . '">' . $i . '</a>';
                        }
                    }
                    if ((int)$currentPage < $totalPages) {
                        echo '<a href="users.php?' . http_build_query(array_merge($queryParams, ['page' => (int)$currentPage + 1])) . '">»</a>';
                    }
                 ?>
            </div>
            <p class="info-text">Показано <?php echo count($paginatedUsers); ?> з <?php echo $totalUsers; ?> користувачів.</p>
            <?php endif; ?>
        <?php else: ?>
            <p><?php echo !empty($searchQuery) ? 'Користувачів за вашим запитом не знайдено.' : 'Немає зареєстрованих користувачів.'; ?></p>
        <?php endif; ?>
    </div>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
