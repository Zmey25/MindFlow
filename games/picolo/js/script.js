document.addEventListener('DOMContentLoaded', function() {
    // --- Logic for index.php (no changes from previous advanced settings implementation) ---
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

        const advancedSettingsToggleBtn = document.getElementById('advanced-settings-toggle-btn');
        const advancedSettingsContainer = document.getElementById('advanced-settings-container');
        if (advancedSettingsToggleBtn && advancedSettingsContainer) {
            // advancedSettingsContainer.style.display = 'none'; // Initial state handled by PHP or default CSS
            advancedSettingsToggleBtn.addEventListener('click', function() {
                const isHidden = advancedSettingsContainer.style.display === 'none';
                advancedSettingsContainer.style.display = isHidden ? 'block' : 'none';
                this.textContent = isHidden ? 'Сховати розширені налаштування' : 'Показати розширені налаштування';
            });
        }

const presets = {
    // Пресет для класичної вечірки: сміх, алкоголь, завдання та трохи особистого.
    party: {
        settings: { reading_timer_duration: 8, max_rounds: 10, initial_skips: 2 },
        categories: {
            "Розкрийся!": { enabled: true, weight: 20 },
            "Штраф": { enabled: true, weight: 15 },
            "Подарунок": { enabled: true, weight: 15 },
            "Виклик": { enabled: true, weight: 35 },
            "Машина Часу": { enabled: true, weight: 15 },
            "Погляд у Майбутнє": { enabled: true, weight: 15 },
            "А що, якби...?": { enabled: true, weight: 25 },
            "Ти і Я": { enabled: true, weight: 30 },
            "Сміхопанорама": { enabled: true, weight: 40 },
            "Етична Дилема": { enabled: true, weight: 10 },
            "Світ Кіно та Музики": { enabled: true, weight: 25 },
            "Таємна Скринька": { enabled: true, weight: 15 },
            "Креативний Куточок": { enabled: true, weight: 15 },
            "Бліц!": { enabled: true, weight: 20 },
            "Хто перший?": { enabled: true, weight: 30 },
            "Ланцюгова Реакція": { enabled: true, weight: 35 },
            "Ігровий Вірус": { enabled: true, weight: 25 },
            "Дуель": { enabled: true, weight: 25 },
            "Спільний Розум": { enabled: true, weight: 30 },
            "Кривий Художник": { enabled: true, weight: 15 },
            "Перекличка": { enabled: true, weight: 35 },
            "Таємно": { enabled: true, weight: 25 },
            "Обирайте": { enabled: true, weight: 30 },
            "тест": { enabled: false, weight: 0 },
            "Default": { enabled: true, weight: 0 }
        }
    },
    // Пресет для глибоких розмов та кращого знайомства. Мінімум активностей.
    deepTalk: {
        settings: { reading_timer_duration: 15, max_rounds: 8, initial_skips: 3 },
        categories: {
            "Розкрийся!": { enabled: true, weight: 50 },
            "Штраф": { enabled: false, weight: 10 },
            "Подарунок": { enabled: false, weight: 10 },
            "Виклик": { enabled: true, weight: 10 },
            "Машина Часу": { enabled: true, weight: 45 },
            "Погляд у Майбутнє": { enabled: true, weight: 45 },
            "А що, якби...?": { enabled: true, weight: 35 },
            "Ти і Я": { enabled: true, weight: 30 },
            "Сміхопанорама": { enabled: true, weight: 20 },
            "Етична Дилема": { enabled: true, weight: 40 },
            "Світ Кіно та Музики": { enabled: true, weight: 25 },
            "Таємна Скринька": { enabled: true, weight: 20 },
            "Креативний Куточок": { enabled: true, weight: 15 },
            "Бліц!": { enabled: false, weight: 10 },
            "Хто перший?": { enabled: false, weight: 10 },
            "Ланцюгова Реакція": { enabled: true, weight: 10 },
            "Ігровий Вірус": { enabled: false, weight: 10 },
            "Дуель": { enabled: false, weight: 10 },
            "Спільний Розум": { enabled: true, weight: 15 },
            "Кривий Художник": { enabled: true, weight: 10 },
            "Перекличка": { enabled: true, weight: 10 },
            "Таємно": { enabled: true, weight: 15 },
            "Обирайте": { enabled: true, weight: 20 },
            "тест": { enabled: false, weight: 0 },
            "Default": { enabled: true, weight: 0 }
        }
    },
    // Пресет для розкриття творчого потенціалу. Акцент на малюванні, уяві та нестандартних завданнях.
    creative: {
        settings: { reading_timer_duration: 12, max_rounds: 8, initial_skips: 2 },
        categories: {
            "Розкрийся!": { enabled: true, weight: 20 },
            "Штраф": { enabled: true, weight: 10 },
            "Подарунок": { enabled: true, weight: 10 },
            "Виклик": { enabled: true, weight: 25 },
            "Машина Часу": { enabled: true, weight: 20 },
            "Погляд у Майбутнє": { enabled: true, weight: 20 },
            "А що, якби...?": { enabled: true, weight: 50 },
            "Ти і Я": { enabled: true, weight: 25 },
            "Сміхопанорама": { enabled: true, weight: 30 },
            "Етична Дилема": { enabled: true, weight: 10 },
            "Світ Кіно та Музики": { enabled: true, weight: 25 },
            "Таємна Скринька": { enabled: true, weight: 10 },
            "Креативний Куточок": { enabled: true, weight: 50 },
            "Бліц!": { enabled: true, weight: 15 },
            "Хто перший?": { enabled: true, weight: 15 },
            "Ланцюгова Реакція": { enabled: true, weight: 20 },
            "Ігровий Вірус": { enabled: true, weight: 20 },
            "Дуель": { enabled: true, weight: 15 },
            "Спільний Розум": { enabled: true, weight: 25 },
            "Кривий Художник": { enabled: true, weight: 50 },
            "Перекличка": { enabled: true, weight: 15 },
            "Таємно": { enabled: true, weight: 40 },
            "Обирайте": { enabled: true, weight: 20 },
            "тест": { enabled: false, weight: 0 },
            "Default": { enabled: true, weight: 0 }
        }
    },
    // Пресет для компаній, що люблять змагатися. Багато інтерактивних ігрових механік.
    gameNight: {
        settings: { reading_timer_duration: 7, max_rounds: 12, initial_skips: 1 },
        categories: {
            "Розкрийся!": { enabled: true, weight: 10 },
            "Штраф": { enabled: true, weight: 20 },
            "Подарунок": { enabled: true, weight: 20 },
            "Виклик": { enabled: true, weight: 30 },
            "Машина Часу": { enabled: true, weight: 10 },
            "Погляд у Майбутнє": { enabled: true, weight: 10 },
            "А що, якби...?": { enabled: true, weight: 15 },
            "Ти і Я": { enabled: true, weight: 35 },
            "Сміхопанорама": { enabled: true, weight: 20 },
            "Етична Дилема": { enabled: true, weight: 10 },
            "Світ Кіно та Музики": { enabled: true, weight: 20 },
            "Таємна Скринька": { enabled: true, weight: 15 },
            "Креативний Куточок": { enabled: true, weight: 15 },
            "Бліц!": { enabled: true, weight: 35 },
            "Хто перший?": { enabled: true, weight: 45 },
            "Ланцюгова Реакція": { enabled: true, weight: 45 },
            "Ігровий Вірус": { enabled: true, weight: 40 },
            "Дуель": { enabled: true, weight: 40 },
            "Спільний Розум": { enabled: true, weight: 40 },
            "Кривий Художник": { enabled: true, weight: 20 },
            "Перекличка": { enabled: true, weight: 35 },
            "Таємно": { enabled: true, weight: 25 },
            "Обирайте": { enabled: true, weight: 35 },
            "тест": { enabled: false, weight: 0 },
            "Default": { enabled: true, weight: 0 }
        }
    },
    // Пікантний пресет для дорослої компанії. Більше провокаційних та особистих питань.
    adultsOnly: {
        settings: { reading_timer_duration: 10, max_rounds: 10, initial_skips: 2 },
        categories: {
            "Розкрийся!": { enabled: true, weight: 35 },
            "Штраф": { enabled: true, weight: 30 },
            "Подарунок": { enabled: true, weight: 20 },
            "Виклик": { enabled: true, weight: 25 },
            "Машина Часу": { enabled: true, weight: 20 },
            "Погляд у Майбутнє": { enabled: true, weight: 20 },
            "А що, якби...?": { enabled: true, weight: 25 },
            "Ти і Я": { enabled: true, weight: 30 },
            "Сміхопанорама": { enabled: true, weight: 25 },
            "Етична Дилема": { enabled: true, weight: 25 },
            "Світ Кіно та Музики": { enabled: true, weight: 15 },
            "Таємна Скринька": { enabled: true, weight: 50 },
            "Креативний Куточок": { enabled: true, weight: 15 },
            "Бліц!": { enabled: true, weight: 20 },
            "Хто перший?": { enabled: true, weight: 20 },
            "Ланцюгова Реакція": { enabled: true, weight: 20 },
            "Ігровий Вірус": { enabled: true, weight: 25 },
            "Дуель": { enabled: true, weight: 25 },
            "Спільний Розум": { enabled: true, weight: 20 },
            "Кривий Художник": { enabled: true, weight: 20 },
            "Перекличка": { enabled: true, weight: 30 },
            "Таємно": { enabled: true, weight: 35 },
            "Обирайте": { enabled: true, weight: 30 },
            "тест": { enabled: false, weight: 0 },
            "Default": { enabled: true, weight: 0 }
        }
    },
    // Налаштування за замовчуванням: усі категорії увімкнені з рівною вагою.
    default: {
        settings: { reading_timer_duration: 10, max_rounds: 10, initial_skips: 2 },
        categories: {
            "Розкрийся!": { enabled: true, weight: 25 },
            "Штраф": { enabled: true, weight: 15 },
            "Подарунок": { enabled: true, weight: 15 },
            "Виклик": { enabled: true, weight: 25 },
            "Машина Часу": { enabled: true, weight: 25 },
            "Погляд у Майбутнє": { enabled: true, weight: 25 },
            "А що, якби...?": { enabled: true, weight: 25 },
            "Ти і Я": { enabled: true, weight: 25 },
            "Сміхопанорама": { enabled: true, weight: 25 },
            "Етична Дилема": { enabled: true, weight: 15 },
            "Світ Кіно та Музики": { enabled: true, weight: 25 },
            "Таємна Скринька": { enabled: true, weight: 15 },
            "Креативний Куточок": { enabled: true, weight: 25 },
            "Бліц!": { enabled: true, weight: 25 },
            "Хто перший?": { enabled: true, weight: 25 },
            "Ланцюгова Реакція": { enabled: true, weight: 25 },
            "Ігровий Вірус": { enabled: true, weight: 25 },
            "Дуель": { enabled: true, weight: 25 },
            "Спільний Розум": { enabled: true, weight: 25 },
            "Кривий Художник": { enabled: true, weight: 25 },
            "Перекличка": { enabled: true, weight: 25 },
            "Таємно": { enabled: true, weight: 25 },
            "Обирайте": { enabled: true, weight: 25 },
            "тест": { enabled: false, weight: 0 },
            "Default": { enabled: true, weight: 0 }
        }
    }
};

        document.querySelectorAll('.preset-btn').forEach(button => {
            button.addEventListener('click', function() {
                const presetName = this.dataset.preset;
                const preset = presets[presetName];
                if (!preset) return;
                if (preset.settings) {
                    for (const key in preset.settings) {
                        const inputElement = document.getElementById(key);
                        if (inputElement) inputElement.value = preset.settings[key];
                    }
                }
                const categorySettingsContainer = document.getElementById('category-settings-list');
                if (categorySettingsContainer) {
                    categorySettingsContainer.querySelectorAll('.category-setting').forEach(row => {
                        const categoryName = row.dataset.categoryName;
                        const enableCheckbox = row.querySelector('input[type="checkbox"]');
                        const weightInput = row.querySelector('input[type="number"]');
                        if (presetName === 'default') {
                             if (enableCheckbox) enableCheckbox.checked = true;
                             if (weightInput) weightInput.value = weightInput.dataset.defaultWeight || 1;
                        } else if (preset.categories && preset.categories[categoryName]) {
                            const catPreset = preset.categories[categoryName];
                            if (enableCheckbox) enableCheckbox.checked = catPreset.enabled;
                            if (weightInput) weightInput.value = catPreset.weight;
                        } else {
                             if (enableCheckbox) enableCheckbox.checked = true;
                             if (weightInput) weightInput.value = weightInput.dataset.defaultWeight || 10;
                        }
                    });
                }
                if (advancedSettingsContainer && advancedSettingsContainer.style.display === 'none' && advancedSettingsToggleBtn) {
                    advancedSettingsToggleBtn.click();
                }
            });
        });
    }


    // --- Logic for game.php ---
    const gamePage = document.querySelector('.game-page');
    if (gamePage && window.GAME_DATA) {
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

        const doneButton = document.querySelector('.btn-done');
        if (doneButton) {
            doneButton.addEventListener('click', function() {
                const doneSound = new Audio('sounds/ding.mp3'); // Make sure sounds/ding.mp3 exists
                doneSound.play().catch(e => console.warn("Done sound was blocked."));
            });
        }
        
        const timerContainer = document.getElementById('timer-container');
        // readingTimerDuration is now the *effective* reading duration for this question
        const { mainTimerDuration, initialTimerValue, initialPhase, readingTimerDuration } = window.GAME_DATA; 
        
        if (timerContainer && (readingTimerDuration > 0 || mainTimerDuration > 0)) { // Only proceed if any timer is active
            const timerCircle = document.getElementById('timer-circle');
            let secondsLeft = initialTimerValue;
            let currentPhase = initialPhase; // This is correctly set by PHP now
            let timerInterval;
            
            const tickSound = new Audio('sounds/tick-tock.wav'); // Make sure sounds/tick-tock.wav exists
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

                // Initial sound play based on phase determined by PHP
                if (currentPhase === 'reading' && readingTimerDuration > 0 && secondsLeft > 0) {
                    // No sound during reading phase initially by default, uncomment to add
                    // tickSound.play().catch(e => console.warn("Reading timer sound blocked.")); 
                } else if (currentPhase === 'main' && mainTimerDuration > 0 && secondsLeft > 0) {
                    tickSound.play().catch(e => console.warn("Main timer sound blocked."));
                }

                if ((currentPhase === 'main' && secondsLeft <= 0 && mainTimerDuration > 0) ||
                    (currentPhase === 'reading' && secondsLeft <=0 && readingTimerDuration > 0 && mainTimerDuration <= 0)) { // If reading ended and no main timer
                     return; // Timer already ended
                }


                timerInterval = setInterval(() => {
                    secondsLeft--;
                    updateTimerDisplay();

                    if (currentPhase === 'reading' && secondsLeft <= 0 && readingTimerDuration > 0) {
                        currentPhase = 'main';
                        secondsLeft = mainTimerDuration; // Switch to main timer duration
                        updateTimerDisplay(); 
                        stopAllSounds(); // Stop reading phase sound if any
                        if (mainTimerDuration > 0) {
                            tickSound.play().catch(e => console.warn("Timer sound blocked."));
                        } else { 
                            clearInterval(timerInterval);
                            // dingSound.play().catch(e => console.warn("Timer sound blocked.")); // Optional: ding if reading ends and no main timer
                        }
                    } else if (currentPhase === 'main' && secondsLeft <= 0 && mainTimerDuration > 0) {
                        clearInterval(timerInterval);
                        stopAllSounds();
                        dingSound.play().catch(e => console.warn("Timer sound blocked."));
                    } else if ( (currentPhase === 'reading' && readingTimerDuration <=0) || (currentPhase === 'main' && mainTimerDuration <=0) ) {
                        clearInterval(timerInterval);
                        stopAllSounds();
                    }
                }, 1000);
            };
            startTimer();
        } else if (timerContainer) { // No timers active for this question, ensure display is 0 or hidden
            const timerCircle = document.getElementById('timer-circle');
            if (timerCircle) timerCircle.textContent = '0';
            // The container itself is hidden by PHP if no timers are active at all.
        }
    }

    if (navigator.userAgent.match(/iPhone|iPad|iPod/i)) {
        document.documentElement.addEventListener('touchend', (e) => {
            if (e.touches.length > 1) e.preventDefault();
        }, { passive: false });
    }
});
