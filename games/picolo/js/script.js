document.addEventListener('DOMContentLoaded', function() {
    const setupPage = document.querySelector('.setup-page');
    if (setupPage) {
        const playerInputsContainer = document.getElementById('player-inputs');
        if (playerInputsContainer) {
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
        }

        const settingsToggle = document.getElementById('advanced-settings-toggle');
        const settingsContent = document.getElementById('advanced-settings-content');
        if (settingsToggle && settingsContent) {
            settingsToggle.addEventListener('click', () => {
                settingsContent.classList.toggle('hidden');
                settingsToggle.innerHTML = settingsContent.classList.contains('hidden') 
                    ? 'Розширені налаштування ▾' 
                    : 'Розширені налаштування ▴';
            });
        }

        const presets = {
            party: { "Правда чи дія": { enabled: true, weight: 40 }, "Я ніколи не...": { enabled: true, weight: 30 }, "Розкрийся!": { enabled: true, weight: 10 }, "Хардкор": { enabled: true, weight: 50 }, "Ігролад": { enabled: true, weight: 20 }, "тест": { enabled: false, weight: 0 } },
            creative: { "Правда чи дія": { enabled: true, weight: 15 }, "Я ніколи не...": { enabled: true, weight: 10 }, "Розкрийся!": { enabled: true, weight: 50 }, "Хардкор": { enabled: false, weight: 0 }, "Ігролад": { enabled: true, weight: 30 }, "тест": { enabled: false, weight: 0 } }
        };

        document.querySelectorAll('.preset-btn').forEach(button => {
            button.addEventListener('click', function() {
                const presetName = this.dataset.preset;
                if (!presets[presetName]) return;
                const presetData = presets[presetName];
                document.querySelectorAll('.category-setting-item').forEach(item => {
                    const categoryName = item.dataset.categoryName;
                    const checkbox = item.querySelector('input[type="checkbox"]');
                    const weightInput = item.querySelector('input[type="number"]');
                    if (presetData[categoryName]) {
                        checkbox.checked = presetData[categoryName].enabled;
                        weightInput.value = presetData[categoryName].weight;
                    } else {
                        checkbox.checked = true;
                        weightInput.value = 1;
                    }
                });
                alert(`Пресет "${this.textContent}" застосовано!`);
            });
        });
    }

    const gamePage = document.querySelector('.game-page');
    if (gamePage && window.GAME_DATA) {
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

        const doneButton = document.querySelector('.btn-done');
        if (doneButton) {
            doneButton.addEventListener('click', function() {
                const doneSound = new Audio('sounds/ding.mp3');
                doneSound.play().catch(e => console.warn("Done sound was blocked."));
            });
        }
        
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

    if (navigator.userAgent.match(/iPhone|iPad|iPod/i)) {
        document.documentElement.addEventListener('touchend', (e) => {
            if (e.touches.length > 1) e.preventDefault();
        }, { passive: false });
    }
});
