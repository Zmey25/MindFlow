document.addEventListener('DOMContentLoaded', function() {
    // --- Логіка для index.php (без змін) ---
    const playerInputsContainer = document.getElementById('player-inputs');
    const addPlayerBtn = document.getElementById('add-player');
    
    if (playerInputsContainer && addPlayerBtn) {
        let playerCount = playerInputsContainer.querySelectorAll('.player-input-group').length;
        addPlayerBtn.addEventListener('click', function() {
            playerCount++;
            const newPlayerGroup = document.createElement('div');
            newPlayerGroup.classList.add('player-input-group');
            const newInput = document.createElement('input');
            newInput.type = 'text';
            newInput.name = 'players[]';
            newInput.placeholder = 'Ім\'я гравця ' + playerCount;
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.classList.add('remove-player-btn');
            removeBtn.textContent = 'X';
            removeBtn.title = 'Видалити гравця';
            removeBtn.addEventListener('click', function() {
                if (playerInputsContainer.querySelectorAll('.player-input-group').length > 2) {
                    newPlayerGroup.remove();
                } else {
                    alert('Мінімум 2 гравці потрібні для гри.');
                }
            });
            newPlayerGroup.appendChild(newInput);
            newPlayerGroup.appendChild(removeBtn);
            playerInputsContainer.appendChild(newPlayerGroup);
        });
    }

    // --- Логіка для game.php (UPDATED) ---
    const gamePage = document.querySelector('.game-page');
    const iconsContainer = document.querySelector('.background-icons-container');

    // Перевіряємо, чи існує наш об'єкт з даними
    if (gamePage && iconsContainer && window.GAME_DATA) {
        // Встановлюємо фон через CSS-змінну
        if (window.GAME_DATA.backgroundGradient) {
            document.documentElement.style.setProperty('--game-background', window.GAME_DATA.backgroundGradient);
        }

        const iconClasses = window.GAME_DATA.iconClasses || [];
        const iconColor = window.GAME_DATA.iconColor || 'rgba(255, 255, 255, 0.1)';
        const iconOpacity = window.GAME_DATA.iconOpacity || 0.1;

        const numIcons = Math.floor(Math.random() * 8) + 8; // 8-15 іконок

        if (iconClasses.length > 0) {
            for (let i = 0; i < numIcons; i++) {
                const iconElement = document.createElement('i');
                const randomIconClass = iconClasses[Math.floor(Math.random() * iconClasses.length)];
                iconElement.className = randomIconClass;
                
                iconElement.style.setProperty('--icon-color', iconColor);
                iconElement.style.setProperty('--icon-opacity', iconOpacity);
                iconElement.style.setProperty('--randX', Math.random());
                iconElement.style.setProperty('--randY', Math.random());
                iconElement.style.left = (Math.random() * 100) + 'vw';
                iconElement.style.top = (Math.random() * 100) + 'vh';
                iconElement.style.fontSize = (Math.random() * 8 + 10) + 'vw';
                const duration = Math.random() * 15 + 20;
                const delay = Math.random() * -duration;
                iconElement.style.animationDuration = duration + 's';
                iconElement.style.animationDelay = delay + 's';
                
                iconsContainer.appendChild(iconElement);
            }
        }
    }

    // Запобігання масштабуванню (без змін)
    if (navigator.userAgent.match(/iPhone|iPad|iPod/i)) {
        let lastTouchEnd = 0;
        document.documentElement.addEventListener('touchend', function (event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) { event.preventDefault(); }
            lastTouchEnd = now;
        }, { passive: false });
    }
    document.documentElement.addEventListener('touchstart', function (event) {
        if (event.touches.length > 1) { event.preventDefault(); }
    }, { passive: false });
});
