<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Рейтинги користувачів";
include __DIR__ . '/includes/header.php'; // Тут перевіряється $isLoggedIn та $isAdmin

// Завантажуємо дані, які будуть потрібні на сторінці
$allRatingsData = readJsonFile(DATA_DIR . '/ratings_summary.json');
$badgeDefinitions = readJsonFile(DATA_DIR . '/badges.json');

// Готуємо дані для передачі в JavaScript
// 1. Повний список користувачів для пошуку в режимі порівняння
$usersForSearch = [];
if (!empty($allRatingsData)) {
    $usersForSearch = array_map(function($user) {
        return [
            'userId' => $user['userId'],
            'username' => $user['username'],
            'firstName' => $user['firstName'],
            'lastName' => $user['lastName'],
        ];
    }, $allRatingsData);
}

// 2. Інформація про бейджі для заголовків таблиці
$badgeHeaders = [];
if (!empty($badgeDefinitions)) {
    foreach ($badgeDefinitions as $badge) {
        $badgeHeaders[] = [
            'id' => $badge['badgeId'],
            'name' => $badge['badgeName'],
            'description' => $badge['badgeDescription']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background-color: #f4f7f6; color: #333; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; background-color: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 8px; }
        h1 { color: #2c3e50; margin-bottom: 20px; text-align: center; }
        
        .controls-container { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; padding: 15px; background-color: #f9f9f9; border-radius: 6px; }
        .view-toggle { display: flex; gap: 5px; }
        .comparison-controls { display: flex; gap: 10px; align-items: center; flex-grow: 1; }
        .comparison-controls input[type="search"] { padding: 9px; border: 1px solid #ddd; border-radius: 4px; flex-grow: 1; min-width: 200px; }

        .btn { padding: 9px 13px; border: 1px solid transparent; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9em; transition: all 0.2s ease; display: inline-block; text-align: center; white-space: nowrap; }
        .btn-primary { background-color: #3498db; color: white; border-color: #3498db; }
        .btn-primary:hover { background-color: #2980b9; border-color: #2980b9; }
        .btn-secondary { background-color: #f0f2f5; color: #333; border-color: #ddd; }
        .btn-secondary.active, .btn-secondary:hover { background-color: #e0e0e0; border-color: #ccc; }
        .btn-danger { background-color: #e74c3c; color: white; border-color: #e74c3c; }
        .btn-danger:hover { background-color: #c0392b; border-color: #c0392b; }

        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #e0e0e0; padding: 10px; text-align: left; vertical-align: middle; white-space: nowrap; }
        th { background-color: #f0f2f5; font-weight: bold; color: #555; position: sticky; top: 0; z-index: 1; }
        th.sortable { cursor: pointer; user-select: none; }
        th.sortable:hover { background-color: #e4e7eb; }
        th .sort-icon { margin-left: 5px; color: #999; display: inline-block; width: 1em; }
        th .sort-icon.asc { content: '▲'; }
        th .sort-icon.desc { content: '▼'; }
        th.user-col { min-width: 150px; }
        
        tbody tr:nth-child(even) { background-color: #f9f9f9; }
        tbody tr:hover { background-color: #f1f1f1; }
        
        .hidden-score { color: #95a5a6; font-style: italic; }
        .pagination { text-align: center; margin: 25px 0; }
        .pagination button { background-color: white; border: 1px solid #ddd; color: #3498db; }
        .pagination button:hover { background-color: #f0f2f5; }
        .pagination button.current-page { background-color: #3498db; color: white; font-weight: bold; border-color: #3498db; }
        .pagination button:disabled { color: #aaa; cursor: not-allowed; }
        
        .info-text { text-align: center; font-size: 0.9em; color: #6c757d; margin-top: 10px; }
        #no-data-message { text-align: center; padding: 20px; font-size: 1.1em; color: #777; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Рейтинги користувачів</h1>

        <div class="controls-container">
            <div class="view-toggle">
                <button id="default-view-btn" class="btn btn-secondary active">Загальний рейтинг</button>
                <button id="comparison-view-btn" class="btn btn-secondary">Порівняння</button>
            </div>
            <div id="comparison-controls-container" class="comparison-controls" style="display: none;">
                <input type="search" id="user-search-input" placeholder="Почніть вводити логін, ім'я...">
                <button id="add-user-btn" class="btn btn-primary">Додати</button>
                <button id="clear-list-btn" class="btn btn-danger">Скинути список</button>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead id="ratings-table-head">
                    <tr>
                        <th class="sortable user-col" data-sort-key="username" title="Сортувати за логіном">Користувач <span class="sort-icon"></span></th>
                        <?php foreach ($badgeHeaders as $badge): ?>
                            <th class="sortable" data-sort-key="<?php echo htmlspecialchars($badge['id']); ?>" title="<?php echo htmlspecialchars($badge['description']); ?>">
                                <?php echo htmlspecialchars($badge['name']); ?>
                                <span class="sort-icon"></span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody id="ratings-table-body">
                    <!-- Рядки будуть вставлені за допомогою JavaScript -->
                </tbody>
            </table>
        </div>
        <div id="no-data-message" style="display: none;">Немає даних для відображення.</div>
        <div id="pagination-container" class="pagination"></div>
        <p id="info-text-container" class="info-text"></p>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // --- DATA FROM PHP ---
        const allRatingsData = <?php echo json_encode($allRatingsData ?? []); ?>;
        const badgeHeaders = <?php echo json_encode($badgeHeaders ?? []); ?>;
        const usersForSearch = <?php echo json_encode($usersForSearch ?? []); ?>;
        const isAdmin = <?php echo json_encode($isAdmin ?? false); ?>;

        // --- STATE ---
        let isComparisonMode = false;
        let comparisonList = JSON.parse(sessionStorage.getItem('comparisonList')) || [];
        let currentData = [];
        let currentPage = 1;
        const usersPerPage = 50;
        let sortState = { key: 'username', direction: 'asc' };

        // --- UI ELEMENTS ---
        const defaultViewBtn = document.getElementById('default-view-btn');
        const comparisonViewBtn = document.getElementById('comparison-view-btn');
        const comparisonControls = document.getElementById('comparison-controls-container');
        const userSearchInput = document.getElementById('user-search-input');
        const addUserBtn = document.getElementById('add-user-btn');
        const clearListBtn = document.getElementById('clear-list-btn');
        const tableHead = document.getElementById('ratings-table-head');
        const tableBody = document.getElementById('ratings-table-body');
        const paginationContainer = document.getElementById('pagination-container');
        const noDataMessage = document.getElementById('no-data-message');
        const infoTextContainer = document.getElementById('info-text-container');
        
        // --- INITIALIZATION ---
        function init() {
            // Attach event listeners
            defaultViewBtn.addEventListener('click', switchToDefaultMode);
            comparisonViewBtn.addEventListener('click', switchToComparisonMode);
            addUserBtn.addEventListener('click', addUserToComparison);
            userSearchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') addUserToComparison();
            });
            clearListBtn.addEventListener('click', clearComparisonList);
            tableHead.addEventListener('click', handleSort);

            // Initial view setup
            if (comparisonList.length > 0) {
                switchToComparisonMode();
            } else {
                switchToDefaultMode();
            }
        }

        // --- VIEW SWITCHING ---
        function switchToDefaultMode() {
            isComparisonMode = false;
            defaultViewBtn.classList.add('active');
            comparisonViewBtn.classList.remove('active');
            comparisonControls.style.display = 'none';
            currentPage = 1;
            render();
        }

        function switchToComparisonMode() {
            isComparisonMode = true;
            comparisonViewBtn.classList.add('active');
            defaultViewBtn.classList.remove('active');
            comparisonControls.style.display = 'flex';
            currentPage = 1;
            render();
        }

        // --- DATA HANDLING & RENDERING ---
        function prepareData() {
            if (isComparisonMode) {
                currentData = allRatingsData.filter(user => comparisonList.includes(user.userId));
            } else {
                currentData = allRatingsData.filter(user => user.participateInRatings);
            }
        }

        function sortData() {
            currentData.sort((a, b) => {
                let valA = a[sortState.key];
                let valB = b[sortState.key];
                
                // For user names, use case-insensitive string comparison
                if (sortState.key === 'username') {
                    valA = (a.firstName || a.lastName) ? `${a.firstName} ${a.lastName}`.trim() : a.username;
                    valB = (b.firstName || b.lastName) ? `${b.firstName} ${b.lastName}`.trim() : b.username;
                    return sortState.direction === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                }

                // For numeric badge scores
                const scoreA = Number(valA) || 0;
                const scoreB = Number(valB) || 0;
                
                return sortState.direction === 'asc' ? scoreA - scoreB : scoreB - scoreA;
            });
        }

        function render() {
            prepareData();
            sortData();
            
            tableBody.innerHTML = '';
            noDataMessage.style.display = 'none';

            if (currentData.length === 0) {
                noDataMessage.style.display = 'block';
                paginationContainer.innerHTML = '';
                infoTextContainer.textContent = '';
                return;
            }

            const totalUsers = currentData.length;
            const totalPages = Math.ceil(totalUsers / usersPerPage);
            currentPage = Math.min(currentPage, totalPages);
            const offset = (currentPage - 1) * usersPerPage;
            const paginatedData = currentData.slice(offset, offset + usersPerPage);

            paginatedData.forEach(user => {
                const row = document.createElement('tr');
                const displayName = (user.firstName || user.lastName) ? `${user.firstName} ${user.lastName} (${user.username})` : user.username;
                
                let userCellHTML = `<td>${escapeHtml(displayName)}</td>`;
                
                let badgesCellHTML = badgeHeaders.map(badge => {
                    const score = user[badge.id];
                    const showHidden = !user.participateInRatings && !isAdmin && isComparisonMode;
                    const cellContent = showHidden ? `<span class="hidden-score">Приховано</span>` : (score !== undefined ? score : '0');
                    return `<td>${cellContent}</td>`;
                }).join('');

                row.innerHTML = userCellHTML + badgesCellHTML;
                tableBody.appendChild(row);
            });

            renderPagination(totalPages, totalUsers, paginatedData.length);
            updateSortIcons();
        }

        function renderPagination(totalPages, totalUsers, displayedCount) {
            paginationContainer.innerHTML = '';
            if (totalPages <= 1) {
                infoTextContainer.textContent = totalUsers > 0 ? `Показано ${displayedCount} з ${totalUsers} користувачів.` : '';
                return;
            }

            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = i;
                pageBtn.className = "btn";
                if (i === currentPage) {
                    pageBtn.classList.add('current-page');
                    pageBtn.disabled = true;
                }
                pageBtn.addEventListener('click', () => {
                    currentPage = i;
                    render();
                });
                paginationContainer.appendChild(pageBtn);
            }
            infoTextContainer.textContent = `Показано ${displayedCount} з ${totalUsers} користувачів. Сторінка ${currentPage} з ${totalPages}.`;
        }

        // --- EVENT HANDLERS ---
        function handleSort(event) {
            const header = event.target.closest('th.sortable');
            if (!header) return;

            const sortKey = header.dataset.sortKey;
            if (sortState.key === sortKey) {
                sortState.direction = sortState.direction === 'asc' ? 'desc' : 'asc';
            } else {
                sortState.key = sortKey;
                sortState.direction = 'desc'; // Default to descending for scores, asc for names
                if (sortKey === 'username') sortState.direction = 'asc';
            }
            render();
        }

        function addUserToComparison() {
            const query = userSearchInput.value.trim().toLowerCase();
            if (!query) return;

            const userFound = allRatingsData.find(u => 
                u.username.toLowerCase() === query ||
                `${u.firstName} ${u.lastName}`.toLowerCase().includes(query)
            );

            if (userFound) {
                if (!comparisonList.includes(userFound.userId)) {
                    comparisonList.push(userFound.userId);
                    sessionStorage.setItem('comparisonList', JSON.stringify(comparisonList));
                    userSearchInput.value = '';
                    render();
                } else {
                    alert('Цей користувач вже у списку.');
                }
            } else {
                alert('Користувача не знайдено.');
            }
        }

        function clearComparisonList() {
            comparisonList = [];
            sessionStorage.removeItem('comparisonList');
            render();
        }

        // --- UTILITIES ---
        function updateSortIcons() {
            document.querySelectorAll('th.sortable .sort-icon').forEach(icon => {
                icon.textContent = '';
            });
            const activeHeader = document.querySelector(`th[data-sort-key="${sortState.key}"] .sort-icon`);
            if (activeHeader) {
                activeHeader.textContent = sortState.direction === 'asc' ? '▲' : '▼';
            }
        }
        
        function escapeHtml(unsafe) {
            return unsafe
                 .replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;");
        }

        // --- START THE APP ---
        init();
    });
    </script>
</body>
</html>
