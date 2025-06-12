document.addEventListener('DOMContentLoaded', function() {
    // --- Logic for index.php ---
    const setupPage = document.querySelector('.setup-page');
    if (setupPage) {
        // Player inputs logic (no changes)
        const playerInputsContainer = document.getElementById('player-inputs');
        const addPlayerBtn = document.getElementById('add-player');
        addPlayerBtn.addEventListener('click', function() {
            const playerCount = playerInputsContainer.querySelectorAll('.player-input-group').length + 1;
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
            removeBtn.addEventListener('click', () => {
                if (playerInputsContainer.querySelectorAll('.player-input-group').length > 2) newPlayerGroup.remove();
                else alert('Мінімум 2 гравці потрібні для гри.');
            });
            newPlayerGroup.appendChild(newInput);
            newPlayerGroup.appendChild(removeBtn);
            playerInputsContainer.appendChild(newPlayerGroup);
        });

        // Advanced settings and presets logic
        const presets = {
            party: {
                'Дія': 50, 'Розкрийся!': 20, 'Виклик': 40, 'Жестяк': 30, 'Для всіх': 25, 'тест': 0, 'Креатив': 5
            },
            creative: {
                'Дія': 15, 'Розкрийся!': 50, 'Виклик': 10, 'Жестяк': 5, 'Для всіх': 20, 'тест': 0, 'Креатив': 40
            }
        };

        document.querySelectorAll('.preset-btn').forEach(button => {
            button.addEventListener('click', function() {
                const presetName = this.dataset.preset;
                const selectedPreset = presets[presetName];

                if (selectedPreset) {
                    document.querySelectorAll('.category-setting').forEach(setting => {
                        const categoryName = setting.dataset.categoryName;
                        const weightInput = setting.querySelector('input[type="number"]');
                        const enabledCheckbox = setting.querySelector('input[type="checkbox"]');
                        
                        const weight = selectedPreset[categoryName] !== undefined ? selectedPreset[categoryName] : 0;
                        
                        weightInput.value = weight;
                        enabledCheckbox.checked = weight > 0;
                    });
                }
            });
        });
    }

    // --- Logic for game.php (no changes) ---
    const gamePage = document.querySelector('.game-page');
    if (gamePage && window.GAME_DATA) {
        // Background setup
        const iconsContainer = document.querySelector('.background-icons-container');
        if (iconsContainer) {
            document.documentElement.style.setProperty('--game-background', window.GAME_DATA.backgroundGradient);
            const iconClasses = window.GAME_DATA.iconClasses || [];
            const numIcons = Math.floor(Math.random() * 8) + 8;
            if (iconClasses.length > 0) {
                for (let i = 0; i < numIcons; i++) {
                    const icon = document.createElement('i');
                    icon.className = iconClasses[Math.floor(Math.random() * iconClasses.length)];
                    icon.style.left = `${Math.random() * 100}vw`;
                    icon.style.top = `${Math.random() * 100}vh`;
                    icon.style.fontSize = `${Math.random() * 8 + 10}vw`;
                    const duration = Math.random() * 15 + 20;
                    icon.style.animation = `floatIcon ${duration}s ${Math.random() * -duration}s infinite linear alternate`;
                    iconsContainer.appendChild(icon);
                }
            }
        }

        // "Done" button sound
        const doneButton = document.querySelector('.btn-done');
        if (doneButton) {
            doneButton.addEventListener('click', function() {
                const doneSound = new Audio('sounds/ding.mp3');
                doneSound.play().catch(e => console.warn("Done sound was blocked."));
            });
        }
        
        // Timer and Sound Logic
        const timerContainer = document.getElementById('timer-container');
        const { mainTimerDuration, initialTimerValue, initialPhase, readingTimerDuration } = window.GAME_DATA;
        
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
                    timerCircle.textContent = Math.max(0, Math.floor(secondsLeft));
                    timerContainer.className = `timer-container timer-${currentPhase}`;
                }
            };
            
            const startTimer = () => {
                clearInterval(timerInterval);
                stopAllSounds();
                updateTimerDisplay();

                if (currentPhase === 'main' && secondsLeft <= 0) return;

                timerInterval = setInterval(() => {
                    secondsLeft--;
                    updateTimerDisplay();

                    if (currentPhase === 'reading' && secondsLeft <= 0) {
                        currentPhase = 'main';
                        secondsLeft = mainTimerDuration;
                        updateTimerDisplay();
                        tickSound.play().catch(e => console.warn("Timer sound blocked."));

                    } else if (currentPhase === 'main' && secondsLeft <= 0) {
                        clearInterval(timerInterval);
                        stopAllSounds();
                        dingSound.play().catch(e => console.warn("Timer sound blocked."));
                    }
                }, 1000);
            };

            startTimer();
        }
    }

    // Prevent iOS scaling
    if (navigator.userAgent.match(/iPhone|iPad|iPod/i)) {
        document.documentElement.addEventListener('touchend', (e) => {
            if (e.touches.length > 1) e.preventDefault();
        }, { passive: false });
    }
});
