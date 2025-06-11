document.addEventListener('DOMContentLoaded', function() {
    // --- Logic for index.php ---
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
            newInput.required = true;
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

    // --- Logic for game.php ---
    const gamePage = document.querySelector('.game-page');
    if (gamePage && window.GAME_DATA) {
        // Background and Icons setup
        const iconsContainer = document.querySelector('.background-icons-container');
        if (iconsContainer) {
            document.documentElement.style.setProperty('--game-background', window.GAME_DATA.backgroundGradient);
            const iconClasses = window.GAME_DATA.iconClasses || [];
            const numIcons = Math.floor(Math.random() * 8) + 8;
            if (iconClasses.length > 0) {
                for (let i = 0; i < numIcons; i++) {
                    const iconElement = document.createElement('i');
                    iconElement.className = iconClasses[Math.floor(Math.random() * iconClasses.length)];
                    iconElement.style.setProperty('--icon-color', window.GAME_DATA.iconColor);
                    iconElement.style.setProperty('--icon-opacity', window.GAME_DATA.iconOpacity);
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
        
        // --- REWRITTEN TIMER LOGIC ---
        const timerContainer = document.getElementById('timer-container');
        const { mainTimerDuration, initialTimerValue, initialPhase } = window.GAME_DATA;

        if (timerContainer && mainTimerDuration !== null) {
            const timerCircle = document.getElementById('timer-circle');
            let secondsLeft = initialTimerValue;
            let currentPhase = initialPhase;
            let timerInterval;

            const tickSound = new Audio('sounds/tick-tock.wav');
            tickSound.loop = true;
            const dingSound = new Audio('sounds/ding.mp3');

            const stopAllSounds = () => {
                tickSound.pause();
                tickSound.currentTime = 0;
            };

            const updateTimerDisplay = () => {
                if (timerCircle) {
                    timerCircle.textContent = Math.max(0, secondsLeft);
                    timerContainer.classList.remove('timer-reading', 'timer-main');
                    timerContainer.classList.add(`timer-${currentPhase}`);
                }
            };
            
            const startTimer = () => {
                clearInterval(timerInterval);
                stopAllSounds();
                updateTimerDisplay(); // Initial display

                if (secondsLeft <= 0 && currentPhase === 'main') {
                    return; // Timer already finished
                }
                
                if (currentPhase === 'main') {
                    tickSound.play().catch(e => console.error("Audio play failed:", e));
                }

                timerInterval = setInterval(() => {
                    secondsLeft--;
                    updateTimerDisplay();

                    if (currentPhase === 'reading' && secondsLeft <= 0) {
                        // Switch to main phase
                        currentPhase = 'main';
                        secondsLeft = mainTimerDuration;
                        updateTimerDisplay();
                        tickSound.play().catch(e => console.error("Audio play failed:", e));
                    } else if (currentPhase === 'main' && secondsLeft <= 0) {
                        // Timer ends
                        clearInterval(timerInterval);
                        stopAllSounds();
                        dingSound.play().catch(e => console.error("Audio play failed:", e));
                    }
                }, 1000);
            };

            startTimer();
        }
    }

    // --- Prevent iOS scaling ---
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
