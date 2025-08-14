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
        // Виправлено: використовуємо 'badgeName'
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
        .container { max-width: 95%; margin: 20px auto; padding: 20px; background-color: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 8px; }
        h1 { color: #2c3e50; margin-bottom: 20px; text-align: center; }
        
        .controls-container { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; padding: 15px; background-color: #f9f9f9; border-radius: 6px; }
        .view-toggle { display: flex; gap: 5px; }
        .comparison-controls { display: flex; gap: 10px; align-items: center; flex-grow: 1; position: relative; }
        .comparison-controls input[type="search"] { padding: 9px; border: 1px solid #ddd; border-radius: 4px; flex-grow: 1; min-width: 250px; }
        #search-suggestions { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ccc; border-top: none; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: none; }
        .suggestion-item { padding: 10px; cursor: pointer; }
        .suggestion-item:hover { background-color: #f0f2f5; }
        .suggestion-item strong { color: #3498db; }

        .btn { padding: 9px 13px; border: 1px solid transparent; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9em; transition: all 0.2s ease; display: inline-block; text-align: center; white-space: nowrap; }
        .btn-primary { background-color: #3498db; color: white; border-color: #3498db; }
        .btn-primary:hover { background-color: #2980b9; border-color: #2980b9; }
        .btn-secondary { background-color: #f0f2f5; color: #333; border-color: #ddd; }
        .btn-secondary.active, .btn-secondary:hover { background-color: #e0e0e0; border-color: #ccc; }
        .btn-danger { background-color: #e74c3c; color: white; border-color: #e74c3c; }
        .btn-danger:hover { background-color: #c0392b; border-color: #c0392b; }

        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; table-layout: fixed; }
        th, td { border: 1px solid #e0e0e0; padding: 10px; text-align: left; vertical-align: middle; }
        th { background-color: #f0f2f5; font-weight: bold; color: #555; position: sticky; top: 0; z-index: 1; }
        th.sortable { cursor: pointer; user-select: none; }
        th.sortable:hover { background-color: #e4e7eb; }
        th .sort-icon { margin-left: 5px; color: #999; display: inline-block; width: 1em; }
        th.user-col { min-width: 250px; width: 30%; }
        td.user-col-data { max-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; } /* Ellipsis for long names */
        th.badge-col { height: 180px; text-align: center; vertical-align: bottom; padding: 10px 4px; min-width: 45px; }
        th.badge-col > div { writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; }
        td.badge-data { text-align: center; }
        
        tbody tr:nth-child(even) { background-color: #f9f9f9; }
        tbody tr:hover { background-color: #f1f1f1; }
        
        .hidden-score { font-style: normal; cursor: help; } /* Style for hidden icon */
        .pagination { text-align: center; margin: 25px 0; }
        .pagination button { background-color: white; border: 1px solid #ddd; color: #3498db; }
        .pagination button:hover { background-color: #f0f2f5; }
        .pagination button.current-page { background-color: #3498db; color: white; font-weight: bold; border-color: #3498db; }
        .pagination button:disabled { color: #aaa; cursor: not-allowed; }
        
        .info-text { text-align: center; font-size: 0.9em; color: #6c757d; margin-top: 10px; }
        #no-data-message { text-align: center; padding: 20px; font-size: 1.1em; color: #777; }
        #participation-note { font-size: 0.85em; text-align: center; color: #777; margin-top: 20px; }
        #participation-note a { color: #3498db; text-decoration: none; }
        #participation-note a:hover { text-decoration: underline; }

        /* === НОВИЙ КОД: ЦЕНТРУВАННЯ КИЦЬКИ === */
        .cat-container {
            text-align: center;
            margin-top: 25px;
        }
        .cat-container img {
            max-width: 100%; /* Забезпечує адаптивність зображення */
            height: auto;
        }

        /* === НОВИЙ КОД: АДАПТИВНІСТЬ ДЛЯ МОБІЛЬНИХ ПРИСТРОЇВ === */
        @media (max-width: 768px) {
            .container {
                max-width: 100%;
                padding: 10px;
                margin: 10px 0;
            }
            h1 {
                font-size: 1.6em;
            }

            /* Адаптуємо блок керування */
            .controls-container {
                flex-direction: column; /* Елементи один під одним */
                align-items: stretch; /* Елементи на всю ширину */
                gap: 10px;
            }
            .comparison-controls {
                flex-wrap: wrap; /* Дозволяємо перенос, якщо потрібно */
            }
            .comparison-controls input[type="search"] {
                min-width: 150px; /* Зменшуємо мінімальну ширину пошуку */
            }

            /* Адаптуємо таблицю */
            th, td {
                padding: 8px 5px; /* Зменшуємо відступи в комірках */
                font-size: 0.9em; /* Трохи зменшуємо шрифт для компактності */
            }
            th.user-col {
                min-width: 150px; /* Зменшуємо мінімальну ширину колонки користувача */
            }
            th.badge-col {
                height: 150px; /* Трохи зменшуємо висоту заголовків */
                min-width: 40px;
            }
        }
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
                <div style="flex-grow:1; position:relative;">
                    <input type="search" id="user-search-input" placeholder="Введіть 3+ символи для пошуку...">
                    <div id="search-suggestions"></div>
                </div>
                <button id="clear-list-btn" class="btn btn-danger">Скинути список</button>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead id="ratings-table-head">
                    <tr>
                        <th class="sortable user-col" data-sort-key="username" title="Сортувати за логіном">Користувач <span class="sort-icon">▲</span></th>
                        <?php foreach ($badgeHeaders as $badge): ?>
                            <th class="sortable badge-col" data-sort-key="<?php echo htmlspecialchars($badge['id']); ?>" title="<?php echo htmlspecialchars($badge['name']) . '&#10;---&#10;' . htmlspecialchars($badge['description']); ?>">
                                <div><?php echo htmlspecialchars($badge['name']); ?></div>
                                <span class="sort-icon"></span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody id="ratings-table-body">
                </tbody>
            </table>
        </div>
        <div id="no-data-message" style="display: none;">Немає даних для відображення.</div>
        <div id="pagination-container" class="pagination"></div>
        <p id="info-text-container" class="info-text"></p>
        <p id="participation-note" style="display: none;">Щоб потрапити до загального рейтингу, <a href="profile.php">встановіть відповідну позначку</a> у своєму профілі.</p>
        
        <!-- === ЗМІНЕНО: Додано контейнер для кицьки === -->
        <div class="cat-container">
            <img src="assets/images/cat_ratings.png" width="250" alt="Кицька">
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // --- DATA FROM PHP ---
        const allRatingsData = <?php echo json_encode($allRatingsData ?? []); ?>;
        const badgeHeaders = <?php echo json_encode($badgeHeaders ?? []); ?>;
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
        const searchSuggestions = document.getElementById('search-suggestions');
        const clearListBtn = document.getElementById('clear-list-btn');
        const tableHead = document.getElementById('ratings-table-head');
        const tableBody = document.getElementById('ratings-table-body');
        const paginationContainer = document.getElementById('pagination-container');
        const noDataMessage = document.getElementById('no-data-message');
        const infoTextContainer = document.getElementById('info-text-container');
        const participationNote = document.getElementById('participation-note');
        
        // --- INITIALIZATION ---
        function init() {
            defaultViewBtn.addEventListener('click', switchToDefaultMode);
            comparisonViewBtn.addEventListener('click', switchToComparisonMode);
            userSearchInput.addEventListener('input', handleSearchInput);
            userSearchInput.addEventListener('blur', () => setTimeout(() => searchSuggestions.style.display = 'none', 150));
            searchSuggestions.addEventListener('click', handleSuggestionClick);
            clearListBtn.addEventListener('click', clearComparisonList);
            tableHead.addEventListener('click', handleSort);

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
            participationNote.style.display = 'block';
            currentPage = 1;
            render();
        }

        function switchToComparisonMode() {
            isComparisonMode = true;
            comparisonViewBtn.classList.add('active');
            defaultViewBtn.classList.remove('active');
            comparisonControls.style.display = 'flex';
            participationNote.style.display = 'none';
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
                
                if (sortState.key === 'username') {
                    valA = `${a.firstName} ${a.lastName} ${a.username}`.trim();
                    valB = `${b.firstName} ${b.lastName} ${b.username}`.trim();
                    return sortState.direction === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                }

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
                
                let userCellHTML = `<td class="user-col-data" title="${escapeHtml(displayName)}">${escapeHtml(displayName)}</td>`;
                
                let badgesCellHTML = badgeHeaders.map(badge => {
                    const score = user[badge.id];
                    const showHidden = !user.participateInRatings && !isAdmin && isComparisonMode;
                    const cellContent = showHidden 
                        ? `<span class="hidden-score" title="Приховано">👁️‍🗨️</span>` 
                        : (score !== undefined ? score : '0');
                    return `<td class="badge-data">${cellContent}</td>`;
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
        function handleSearchInput() {
            const query = userSearchInput.value.toLowerCase().trim();
            if (query.length < 3) {
                searchSuggestions.style.display = 'none';
                return;
            }
            
            const matches = allRatingsData.filter(user => {
                const fullName = `${user.firstName} ${user.lastName} ${user.username}`.toLowerCase();
                return fullName.includes(query);
            }).slice(0, 3);
            
            if (matches.length > 0) {
                searchSuggestions.innerHTML = matches.map(user => {
                    const displayName = (user.firstName || user.lastName) ? `${user.firstName} ${user.lastName} (<strong>${user.username}</strong>)` : `<strong>${user.username}</strong>`;
                    return `<div class="suggestion-item" data-userid="${user.userId}">${displayName}</div>`;
                }).join('');
                searchSuggestions.style.display = 'block';
            } else {
                searchSuggestions.style.display = 'none';
            }
        }

        function handleSuggestionClick(event) {
            const item = event.target.closest('.suggestion-item');
            if (!item) return;
            
            const userId = item.dataset.userid;
            addUserToComparison(userId);
            
            userSearchInput.value = '';
            searchSuggestions.style.display = 'none';
        }
        
        function addUserToComparison(userId) {
            if (!comparisonList.includes(userId)) {
                comparisonList.push(userId);
                sessionStorage.setItem('comparisonList', JSON.stringify(comparisonList));
                render();
            } else {
                alert('Цей користувач вже у списку.');
            }
        }

        function handleSort(event) {
            const header = event.target.closest('th.sortable');
            if (!header) return;

            const sortKey = header.dataset.sortKey;
            if (sortState.key === sortKey) {
                sortState.direction = sortState.direction === 'asc' ? 'desc' : 'asc';
            } else {
                sortState.key = sortKey;
                sortState.direction = (sortKey === 'username') ? 'asc' : 'desc';
            }
            currentPage = 1;
            render();
        }

        function clearComparisonList() {
            comparisonList = [];
            sessionStorage.removeItem('comparisonList');
            render();
        }

        // --- UTILITIES ---
        function updateSortIcons() {
            document.querySelectorAll('th.sortable .sort-icon').forEach(icon => { icon.textContent = ''; });
            const activeHeader = document.querySelector(`th[data-sort-key="${sortState.key}"] .sort-icon`);
            if (activeHeader) {
                activeHeader.textContent = sortState.direction === 'asc' ? '▲' : '▼';
            }
        }
        
        function escapeHtml(unsafe) {
            return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        // --- START THE APP ---
        init();
    });
    </script>
</body>
</html>
