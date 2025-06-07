document.addEventListener('DOMContentLoaded', function() {
    // --- Логіка для index.php (додавання/видалення гравців) ---
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
            // newInput.required = true; // 'required' краще залишити тільки для перших двох

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.classList.add('remove-player-btn');
            removeBtn.textContent = 'X';
            removeBtn.title = 'Видалити гравця';
            removeBtn.addEventListener('click', function() {
                // Не даємо видалити, якщо гравців менше 3 (залишається 2)
                if (playerInputsContainer.querySelectorAll('.player-input-group').length > 2) {
                    newPlayerGroup.remove();
                    // Оновлюємо playerCount та плейсхолдери, якщо це важливо, але простіше так
                } else {
                    alert('Мінімум 2 гравці потрібні для гри.');
                }
            });

            newPlayerGroup.appendChild(newInput);
            newPlayerGroup.appendChild(removeBtn);
            playerInputsContainer.appendChild(newPlayerGroup);
        });

        // Додаємо обробники для кнопок видалення, які вже є (якщо вони є)
        // Це не потрібно, якщо перші два поля не мають кнопки видалення
    }

    // --- Логіка для game.php (анімація фонових іконок) ---
    const gamePage = document.querySelector('.game-page');
    const iconsContainer = document.querySelector('.background-icons-container');
    const gameDataElement = document.getElementById('game-data-container');

    if (gamePage && iconsContainer && gameDataElement) {
        try {
            const iconClassesJSON = gameDataElement.dataset.iconClasses;
            const iconClasses = JSON.parse(iconClassesJSON); // ['fas fa-gift', 'fas fa-star']
            const iconColor = gameDataElement.dataset.iconColor || 'rgba(255, 255, 255, 0.1)';
            const iconOpacity = parseFloat(gameDataElement.dataset.iconOpacity) || 0.1;

            // Змінено: Рандомна кількість іконок від 8 до 20
            const numIcons = Math.floor(Math.random() * 13) + 8; // 8-20 іконок

            if (iconClasses && iconClasses.length > 0 && iconClasses[0] !== "none") {
                for (let i = 0; i < numIcons; i++) {
                    const iconElement = document.createElement('i');
                    // Вибираємо випадкову іконку з масиву
                    const randomIconClass = iconClasses[Math.floor(Math.random() * iconClasses.length)];
                    iconElement.className = randomIconClass; // Наприклад, "fas fa-gift"
                    
                    iconElement.style.setProperty('--icon-color', iconColor); // Встановлюємо CSS змінну
                    iconElement.style.setProperty('--icon-opacity', iconOpacity);

                    // Оновлено: Забезпечуємо, щоб іконки спочатку були в межах вьюпорту
                    iconElement.style.left = (Math.random() * 100) + 'vw'; // 0vw to 100vw
                    iconElement.style.top = (Math.random() * 100) + 'vh';  // 0vh to 100vh
                    iconElement.style.fontSize = (Math.random() * 8 + 10) + 'vw'; // 10vw to 18vw

                    // Випадкова тривалість та затримка анімації (тривалість залишається повільною)
                    const duration = Math.random() * 10 + 25; // 25-35s (трохи повільніше)
                    const delay = Math.random() * -duration; // Негативна затримка, щоб анімації починались з різних фаз
                    
                    iconElement.style.animationDuration = duration + 's';
                    iconElement.style.animationDelay = delay + 's';
                    
                    iconsContainer.appendChild(iconElement);
                }
            }
        } catch (e) {
            console.error("Error parsing icon classes or setting up background icons:", e);
            console.error("Dataset value was:", gameDataElement.dataset.iconClasses);
        }
    }

    // Запобігання масштабуванню подвійним тапом на iOS
    if (navigator.userAgent.match(/iPhone|iPad|iPod/i)) {
        let lastTouchEnd = 0;
        document.documentElement.addEventListener('touchend', function (event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, { passive: false });
    }
     // Запобігання масштабуванню pinch-to-zoom
    document.documentElement.addEventListener('touchstart', function (event) {
        if (event.touches.length > 1) {
            event.preventDefault();
        }
    }, { passive: false });

});
