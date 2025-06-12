document.addEventListener('DOMContentLoaded', function() {
    // --- Logic for index.php ---
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

        // Advanced Settings Toggle
        const advancedSettingsToggleBtn = document.getElementById('advanced-settings-toggle-btn');
        const advancedSettingsContainer = document.getElementById('advanced-settings-container');
        if (advancedSettingsToggleBtn && advancedSettingsContainer) {
            advancedSettingsContainer.style.display = 'none'; // Initially hidden
            advancedSettingsToggleBtn.addEventListener('click', function() {
                const isHidden = advancedSettingsContainer.style.display === 'none';
                advancedSettingsContainer.style.display = isHidden ? 'block' : 'none';
                this.textContent = isHidden ? 'Сховати розширені налаштування' : 'Показати розширені налаштування';
            });
        }

        // Presets Logic
        const presets = {
            party: {
                settings: { reading_timer_duration: 8, max_rounds: 7, initial_skips: 2 },
                categories: {
                    "Веселі завдання": { enabled: true, weight: 50 },
                    "Розкрийся!": { enabled: true, weight: 30 },
                    "Хардкор": { enabled: false, weight: 5 },
                    "Рухайся!": { enabled: true, weight: 40 },
                    "Креатив": { enabled: false, weight: 10 },
                    "Алкогольні": { enabled: true, weight: 60 },
                    "Для компанії": { enabled: true, weight: 35 },
                    "тест": { enabled: false, weight: 0 }
                }
            },
            creative: {
                settings: { reading_timer_duration: 15, max_rounds: 4, initial_skips: 1 },
                categories: {
                    "Веселі завдання": { enabled: true, weight: 20 },
                    "Розкрийся!": { enabled: true, weight: 50 },
                    "Хардкор": { enabled: true, weight: 15 },
                    "Рухайся!": { enabled: false, weight: 5 },
                    "Креатив": { enabled: true, weight: 60 },
                    "Алкогольні": { enabled: false, weight: 5 },
                    "Для компанії": { enabled: true, weight: 30 },
                    "тест": { enabled: false, weight: 0 }
                }
            },
            default: { // To reset to defaults if needed, uses placeholder values, actual defaults are from PHP
                settings: { reading_timer_duration: 10, max_rounds: 5, initial_skips: 1 },
                categories: {} // JS will try to find original default weights from data-attributes
            }
        };

        document.querySelectorAll('.preset-btn').forEach(button => {
            button.addEventListener('click', function() {
                const presetName = this.dataset.preset;
                const preset = presets[presetName];
                if (!preset) return;

                // Apply general settings
                if (preset.settings) {
                    for (const key in preset.settings) {
                        const inputElement = document.getElementById(key);
                        if (inputElement) inputElement.value = preset.settings[key];
                    }
                }

                // Apply category settings
                const categorySettingsContainer = document.getElementById('category-settings-list');
                if (categorySettingsContainer) {
                    categorySettingsContainer.querySelectorAll('.category-setting').forEach(row => {
                        const categoryName = row.dataset.categoryName;
                        const enableCheckbox = row.querySelector('input[type="checkbox"]');
                        const weightInput = row.querySelector('input[type="number"]');

                        if (presetName === 'default') {
                             if (enableCheckbox) enableCheckbox.checked = true; // Default to enabled
                             if (weightInput) weightInput.value = weightInput.dataset.defaultWeight || 1;
                        } else if (preset.categories && preset.categories[categoryName]) {
                            const catPreset = preset.categories[categoryName];
                            if (enableCheckbox) enableCheckbox.checked = catPreset.enabled;
                            if (weightInput) weightInput.value = catPreset.weight;
                        } else { // Category not in preset, might revert to default or leave as is
                             if (enableCheckbox) enableCheckbox.checked = true; // Default to enabled if not specified in preset
                             if (weightInput) weightInput.value = weightInput.dataset.defaultWeight || 10; // Fallback
                        }
                    });
                }
                 // Ensure advanced settings are visible after applying a preset
                if (advancedSettingsContainer && advancedSettingsContainer.style.display === 'none') {
                    advancedSettingsToggleBtn.click();
                }
            });
        });
    }


    // --- Logic for game.php ---
    const gamePage = document.querySelector('.game-page');
    if (gamePage && window.GAME_DATA) {
        // --- Background setup (no changes) ---
        const iconsContainer = document.querySelector('.background-icons-container');
        if (iconsContainer) {
            document.documentElement.style.setProperty('--game-background', window.GAME_DATA.backgroundGradient);
            document.documentElement.style.setProperty('--icon-color', window.GAME_DATA.iconColor);
            document.documentElement.style.setProperty('--icon-opacity', window.GAME_DATA.iconOpacity);
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

        // --- NEW: Add sound to "Done" button ---
        const doneButton = document.querySelector('.btn-done');
        if (doneButton) {
            doneButton.addEventListener('click', function() {
                const doneSound = new Audio('sounds/ding.mp3');
                doneSound.play().catch(e => console.warn("Done sound was blocked."));
            });
        }
        
        // --- REVISED: Simplified Timer and Sound Logic ---
        const timerContainer = document.getElementById('timer-container');
        const { mainTimerDuration, initialTimerValue, initialPhase, readingTimerDuration } = window.GAME_DATA; // Added readingTimerDuration
        
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
                    // Class name is set in PHP for initial state, JS will update if phase changes
                    timerContainer.className = `timer-container timer-${currentPhase}`;
                }
            };
            
            const startTimer = () => {
                clearInterval(timerInterval);
                stopAllSounds();
                updateTimerDisplay();

                if (currentPhase === 'main' && secondsLeft <= 0 && mainTimerDuration > 0) return; // Don't restart if main timer already ended
                if (currentPhase === 'reading' && secondsLeft <= 0 && readingTimerDuration > 0) { // If reading timer already ended at page load
                     currentPhase = 'main';
                     secondsLeft = mainTimerDuration; // Start main timer directly
                     updateTimerDisplay();
                     if (mainTimerDuration > 0) {
                        tickSound.play().catch(e => console.warn("Timer sound blocked. Interact with the page to enable sound."));
                     } else { // No main timer, effectively ends here.
                        return;
                     }
                } else if (currentPhase === 'reading' && readingTimerDuration > 0) {
                    // Reading phase active, no sound yet
                } else if (currentPhase === 'main' && mainTimerDuration > 0) {
                    tickSound.play().catch(e => console.warn("Timer sound blocked. Interact with the page to enable sound."));
                }


                timerInterval = setInterval(() => {
                    secondsLeft--;
                    updateTimerDisplay();

                    if (currentPhase === 'reading' && secondsLeft <= 0 && readingTimerDuration > 0) {
                        currentPhase = 'main';
                        secondsLeft = mainTimerDuration; // Switch to main timer duration
                        updateTimerDisplay(); // Update display for new phase and time
                        if (mainTimerDuration > 0) {
                            tickSound.play().catch(e => console.warn("Timer sound blocked. Interact with the page to enable sound."));
                        } else { // If main timer is 0, stop interval
                            clearInterval(timerInterval);
                            stopAllSounds();
                            // Optionally play ding if main timer is 0 meaning "time's up for reading leads to time's up for task"
                            // dingSound.play().catch(e => console.warn("Timer sound blocked."));
                        }
                    } else if (currentPhase === 'main' && secondsLeft <= 0 && mainTimerDuration > 0) {
                        clearInterval(timerInterval);
                        stopAllSounds();
                        dingSound.play().catch(e => console.warn("Timer sound blocked."));
                    } else if ( (currentPhase === 'reading' && readingTimerDuration <=0) || (currentPhase === 'main' && mainTimerDuration <=0) ) {
                        // If either timer is disabled (duration 0 or less), clear interval
                        clearInterval(timerInterval);
                        stopAllSounds();
                    }
                }, 1000);
            };

            // Only start JS timer if there's any timer duration configured
            if (readingTimerDuration > 0 || mainTimerDuration > 0) {
                 startTimer();
            } else {
                 updateTimerDisplay(); // Show 0 if no timers
            }
        }
    }

    // --- Prevent iOS scaling (no changes) ---
    if (navigator.userAgent.match(/iPhone|iPad|iPod/i)) {
        document.documentElement.addEventListener('touchend', (e) => {
            if (e.touches.length > 1) e.preventDefault();
        }, { passive: false });
    }
});
