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
            newInput.required = true; // Ensure new inputs are also required
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.classList.add('remove-player-btn');
            removeBtn.textContent = 'X';
            removeBtn.title = 'Видалити гравця';
            removeBtn.addEventListener('click', function() {
                // Allow removal only if there are more than 2 players
                if (playerInputsContainer.querySelectorAll('.player-input-group').length > 2) {
                    newPlayerGroup.remove();
                    // Optional: Re-index placeholders if needed, though not strictly necessary
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
    const iconsContainer = document.querySelector('.background-icons-container');
    const timerContainer = document.getElementById('timer-container');
    const timerCircle = document.getElementById('timer-circle');

    // Check if our GAME_DATA object exists (it's populated in game.php)
    if (gamePage && iconsContainer && window.GAME_DATA) {
        // Set background via CSS variable
        if (window.GAME_DATA.backgroundGradient) {
            document.documentElement.style.setProperty('--game-background', window.GAME_DATA.backgroundGradient);
        }

        const iconClasses = window.GAME_DATA.iconClasses || [];
        const iconColor = window.GAME_DATA.iconColor || 'rgba(255, 255, 255, 0.1)';
        const iconOpacity = window.GAME_DATA.iconOpacity || 0.1;

        const numIcons = Math.floor(Math.random() * 8) + 8; // 8-15 icons

        if (iconClasses.length > 0) {
            for (let i = 0; i < numIcons; i++) {
                const iconElement = document.createElement('i');
                const randomIconClass = iconClasses[Math.floor(Math.random() * iconClasses.length)];
                iconElement.className = randomIconClass;
                
                iconElement.style.setProperty('--icon-color', iconColor);
                iconElement.style.setProperty('--icon-opacity', iconOpacity);
                // Randomize starting position for smoother animation
                iconElement.style.left = (Math.random() * 100) + 'vw';
                iconElement.style.top = (Math.random() * 100) + 'vh';
                iconElement.style.fontSize = (Math.random() * 8 + 10) + 'vw'; // Random icon size
                const duration = Math.random() * 15 + 20; // Random animation duration
                const delay = Math.random() * -duration; // Random negative delay to start at different points
                iconElement.style.animationDuration = duration + 's';
                iconElement.style.animationDelay = delay + 's';
                
                iconsContainer.appendChild(iconElement);
            }
        }

        // --- Timer Logic (UPDATED) ---
        let currentQuestionTimer = window.GAME_DATA.currentQuestionTimer;
        const readingTimerDuration = window.GAME_DATA.readingTimerDuration;
        let timerPhase = window.GAME_DATA.timerPhase;
        let timerStartedAt = window.GAME_DATA.timerStartedAt;
        let timerInterval;

        const updateTimerDisplay = (seconds, phase) => {
            if (timerCircle) {
                timerCircle.textContent = seconds;
                timerContainer.classList.remove('timer-reading', 'timer-main');
                timerContainer.classList.add(`timer-${phase}`);
            }
        };

        const startCountdown = () => {
            clearInterval(timerInterval); // Clear any existing timer

            timerInterval = setInterval(() => {
                const now = Math.floor(Date.now() / 1000); // Current time in seconds
                const elapsedTime = now - timerStartedAt;

                let remainingSeconds;

                if (timerPhase === 'reading') {
                    remainingSeconds = readingTimerDuration - elapsedTime;
                    if (remainingSeconds <= 0) {
                        // Transition to main timer phase
                        timerPhase = 'main';
                        timerStartedAt = now; // Reset start time for main timer
                        remainingSeconds = currentQuestionTimer; // Initial remaining for main timer
                        updateTimerDisplay(remainingSeconds, timerPhase);
                    } else {
                        updateTimerDisplay(remainingSeconds, timerPhase);
                    }
                }
                
                if (timerPhase === 'main') {
                    remainingSeconds = currentQuestionTimer - (now - timerStartedAt);
                    if (remainingSeconds <= 0) {
                        clearInterval(timerInterval);
                        updateTimerDisplay(0, timerPhase);
                        // Automatically "skip" the question when time runs out
                        const skipButton = document.querySelector('.btn-skip');
                        if (skipButton && !skipButton.disabled) {
                            skipButton.click(); // Programmatically click the skip button
                        } else {
                            // If skip button is disabled (e.g., no skips left), just refresh to next player
                            // This scenario implies a force-move to next player if no skips.
                            // For simplicity, we just reload the page which the PHP will handle.
                            window.location.reload(); 
                        }
                    } else {
                        updateTimerDisplay(remainingSeconds, timerPhase);
                    }
                }
            }, 1000); // Update every second
        };

        if (currentQuestionTimer !== null && timerContainer && timerCircle) {
            // Initial check to see if timer has already expired or phase has changed
            const now = Math.floor(Date.now() / 1000);
            let elapsedTime = now - timerStartedAt;

            if (timerPhase === 'reading') {
                if (elapsedTime >= readingTimerDuration) {
                    // Reading phase already passed, switch to main timer
                    timerPhase = 'main';
                    timerStartedAt = now - (elapsedTime - readingTimerDuration); // Adjust start time for main timer
                                                                               // So it's effectively 'started' at the moment reading timer ended
                    elapsedTime = now - timerStartedAt; // Recalculate elapsed for main phase
                }
            }

            if (timerPhase === 'main') {
                if (elapsedTime >= currentQuestionTimer) {
                    // Main timer already expired before page load/script execution
                    updateTimerDisplay(0, 'main');
                    // Immediately trigger skip action
                    const skipButton = document.querySelector('.btn-skip');
                    if (skipButton && !skipButton.disabled) {
                        skipButton.click();
                    } else {
                         window.location.reload();
                    }
                } else {
                    startCountdown(); // Start the countdown
                }
            } else { // If still in reading phase
                startCountdown();
            }
        }
    }

    // --- Prevent iOS scaling (no changes) ---
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
