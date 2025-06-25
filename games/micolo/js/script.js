document.addEventListener('DOMContentLoaded', function() {
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
            advancedSettingsToggleBtn.addEventListener('click', function() {
                const isHidden = advancedSettingsContainer.style.display === 'none';
                advancedSettingsContainer.style.display = isHidden ? 'block' : 'none';
                this.textContent = isHidden ? 'Сховати розширені налаштування' : 'Показати розширені налаштування';
            });
        }

    const presets = {
        party: {
            settings: { reading_timer_duration: 15, max_rounds: 10, initial_skips: 2 },
            categories: {
                "Розкрийся!": { enabled: !0, weight: 20 },
                "Штраф": { enabled: !0, weight: 15 },
                "Подарунок": { enabled: !0, weight: 15 },
                "Виклик": { enabled: !0, weight: 35 },
                "Машина Часу": { enabled: !0, weight: 15 },
                "Погляд у Майбутнє": { enabled: !0, weight: 15 },
                "А що, якби...?": { enabled: !0, weight: 25 },
                "Ти і Я": { enabled: !0, weight: 30 },
                "Сміхопанорама": { enabled: !0, weight: 40 },
                "Етична Дилема": { enabled: !0, weight: 10 },
                "Світ Кіно та Музики": { enabled: !0, weight: 25 },
                "Таємна Скринька": { enabled: !0, weight: 15 },
                "Креативний Куточок": { enabled: !0, weight: 15 },
                "Бліц!": { enabled: !0, weight: 20 },
                "Хто перший?": { enabled: !0, weight: 30 },
                "Ланцюгова Реакція": { enabled: !0, weight: 35 },
                "Ігровий Вірус": { enabled: !0, weight: 25 },
                "Дуель": { enabled: !0, weight: 25 },
                "Спільний Розум": { enabled: !0, weight: 30 },
                "Кривий Художник": { enabled: !0, weight: 15 },
                "Перекличка": { enabled: !0, weight: 35 },
                "Таємно": { enabled: !0, weight: 25 },
                "Обирайте": { enabled: !0, weight: 30 },
                "18+": { enabled: !1, weight: 10 },
                "Я ніколи не...": { enabled: !1, weight: 10 },
                "Default": { enabled: !0, weight: 0 }
            }
        },
        toTalk: { // Раніше "deepTalk", перейменовано на "Поговорити"
            settings: { reading_timer_duration: 15, max_rounds: 10, initial_skips: 3 },
            categories: {
                "Розкрийся!": { enabled: !0, weight: 50 },
                "Штраф": { enabled: !1, weight: 1 },
                "Подарунок": { enabled: !1, weight: 1 },
                "Виклик": { enabled: !0, weight: 1 },
                "Машина Часу": { enabled: !0, weight: 45 },
                "Погляд у Майбутнє": { enabled: !0, weight: 45 },
                "А що, якби...?": { enabled: !0, weight: 35 },
                "Ти і Я": { enabled: !0, weight: 30 },
                "Сміхопанорама": { enabled: !0, weight: 20 },
                "Етична Дилема": { enabled: !0, weight: 40 },
                "Світ Кіно та Музики": { enabled: !0, weight: 25 },
                "Таємна Скринька": { enabled: !0, weight: 1 },
                "Креативний Куточок": { enabled: !0, weight: 1 },
                "Бліц!": { enabled: !1, weight: 1 },
                "Хто перший?": { enabled: !1, weight: 1 },
                "Ланцюгова Реакція": { enabled: !0, weight: 1 },
                "Ігровий Вірус": { enabled: !1, weight: 1 },
                "Дуель": { enabled: !1, weight: 1 },
                "Спільний Розум": { enabled: !0, weight: 1 },
                "Кривий Художник": { enabled: !0, weight: 1 },
                "Перекличка": { enabled: !0, weight: 1 },
                "Таємно": { enabled: !0, weight: 1 },
                "Обирайте": { enabled: !0, weight: 1 },
                "18+": { enabled: !1, weight: 10 },
                "Я ніколи не...": { enabled: !1, weight: 10 },
                "Default": { enabled: !0, weight: 0 }
            }
        },
        creative: {
            settings: { reading_timer_duration: 20, max_rounds: 7, initial_skips: 2 },
            categories: {
                "Розкрийся!": { enabled: !0, weight: 20 },
                "Штраф": { enabled: !0, weight: 10 },
                "Подарунок": { enabled: !0, weight: 10 },
                "Виклик": { enabled: !0, weight: 25 },
                "Машина Часу": { enabled: !0, weight: 20 },
                "Погляд у Майбутнє": { enabled: !0, weight: 20 },
                "А що, якби...?": { enabled: !0, weight: 50 },
                "Ти і Я": { enabled: !0, weight: 25 },
                "Сміхопанорама": { enabled: !0, weight: 30 },
                "Етична Дилема": { enabled: !0, weight: 10 },
                "Світ Кіно та Музики": { enabled: !0, weight: 25 },
                "Таємна Скринька": { enabled: !0, weight: 10 },
                "Креативний Куточок": { enabled: !0, weight: 50 },
                "Бліц!": { enabled: !0, weight: 15 },
                "Хто перший?": { enabled: !0, weight: 15 },
                "Ланцюгова Реакція": { enabled: !0, weight: 20 },
                "Ігровий Вірус": { enabled: !0, weight: 20 },
                "Дуель": { enabled: !0, weight: 15 },
                "Спільний Розум": { enabled: !0, weight: 25 },
                "Кривий Художник": { enabled: !0, weight: 50 },
                "Перекличка": { enabled: !0, weight: 15 },
                "Таємно": { enabled: !0, weight: 40 },
                "Обирайте": { enabled: !0, weight: 20 },
                "18+": { enabled: !1, weight: 10 },
                "Я ніколи не...": { enabled: !1, weight: 10 },
                "Default": { enabled: !0, weight: 0 }
            }
        },
        gameNight: {
            settings: { reading_timer_duration: 12, max_rounds: 12, initial_skips: 1 },
            categories: {
                "Розкрийся!": { enabled: !0, weight: 10 },
                "Штраф": { enabled: !0, weight: 20 },
                "Подарунок": { enabled: !0, weight: 20 },
                "Виклик": { enabled: !0, weight: 30 },
                "Машина Часу": { enabled: !0, weight: 10 },
                "Погляд у Майбутнє": { enabled: !0, weight: 10 },
                "А що, якби...?": { enabled: !0, weight: 15 },
                "Ти і Я": { enabled: !0, weight: 35 },
                "Сміхопанорама": { enabled: !0, weight: 20 },
                "Етична Дилема": { enabled: !0, weight: 10 },
                "Світ Кіно та Музики": { enabled: !0, weight: 20 },
                "Таємна Скринька": { enabled: !0, weight: 15 },
                "Креативний Куточок": { enabled: !0, weight: 15 },
                "Бліц!": { enabled: !0, weight: 35 },
                "Хто перший?": { enabled: !0, weight: 45 },
                "Ланцюгова Реакція": { enabled: !0, weight: 45 },
                "Ігровий Вірус": { enabled: !0, weight: 40 },
                "Дуель": { enabled: !0, weight: 40 },
                "Спільний Розум": { enabled: !0, weight: 40 },
                "Кривий Художник": { enabled: !0, weight: 20 },
                "Перекличка": { enabled: !0, weight: 35 },
                "Таємно": { enabled: !0, weight: 25 },
                "Обирайте": { enabled: !0, weight: 35 },
                "18+": { enabled: !1, weight: 10 },
                "Я ніколи не...": { enabled: !1, weight: 10 },
                "Default": { enabled: !0, weight: 0 }
            }
        },
        adultsOnly: { // Пресет 18+
            settings: { reading_timer_duration: 10, max_rounds: 10, initial_skips: 2 },
            categories: {
                "18+": { enabled: !0, weight: 60 },
                "Я ніколи не...": { enabled: !0, weight: 50 },
                "Розкрийся!": { enabled: !0, weight: 15 },
                "Штраф": { enabled: !0, weight: 15 },
                "Подарунок": { enabled: !0, weight: 10 },
                "Виклик": { enabled: !0, weight: 15 },
                "Машина Часу": { enabled: !0, weight: 1 },
                "Погляд у Майбутнє": { enabled: !0, weight: 1 },
                "А що, якби...?": { enabled: !0, weight: 1 },
                "Ти і Я": { enabled: !0, weight: 15 },
                "Сміхопанорама": { enabled: !0, weight: 1 },
                "Етична Дилема": { enabled: !0, weight: 1 },
                "Світ Кіно та Музики": { enabled: !0, weight: 1 },
                "Таємна Скринька": { enabled: !0, weight: 40 },
                "Креативний Куточок": { enabled: !1, weight: 1 },
                "Бліц!": { enabled: !0, weight: 1 },
                "Хто перший?": { enabled: !0, weight: 1 },
                "Ланцюгова Реакція": { enabled: !0, weight: 1 },
                "Ігровий Вірус": { enabled: !0, weight: 1 },
                "Дуель": { enabled: !0, weight: 1 },
                "Спільний Розум": { enabled: !0, weight: 1 },
                "Кривий Художник": { enabled: !1, weight: 1 }, 
                "Перекличка": { enabled: !0, weight: 1 },
                "Таємно": { enabled: !0, weight: 1 },
                "Обирайте": { enabled: !0, weight: 1 },
                "Default": { enabled: !0, weight: 0 }
            }
        },
        default: {
            settings: { reading_timer_duration: 10, max_rounds: 10, initial_skips: 2 },
            categories: {
                "Розкрийся!": { enabled: !0, weight: 25 },
                "Штраф": { enabled: !0, weight: 15 },
                "Подарунок": { enabled: !0, weight: 15 },
                "Виклик": { enabled: !0, weight: 25 },
                "Машина Часу": { enabled: !0, weight: 25 },
                "Погляд у Майбутнє": { enabled: !0, weight: 25 },
                "А що, якби...?": { enabled: !0, weight: 25 },
                "Ти і Я": { enabled: !0, weight: 25 },
                "Сміхопанорама": { enabled: !0, weight: 25 },
                "Етична Дилема": { enabled: !0, weight: 15 },
                "Світ Кіно та Музики": { enabled: !0, weight: 25 },
                "Таємна Скринька": { enabled: !0, weight: 15 },
                "Креативний Куточок": { enabled: !0, weight: 25 },
                "Бліц!": { enabled: !0, weight: 25 },
                "Хто перший?": { enabled: !0, weight: 25 },
                "Ланцюгова Реакція": { enabled: !0, weight: 25 },
                "Ігровий Вірус": { enabled: !0, weight: 25 },
                "Дуель": { enabled: !0, weight: 25 },
                "Спільний Розум": { enabled: !0, weight: 25 },
                "Кривий Художник": { enabled: !0, weight: 25 },
                "Перекличка": { enabled: !0, weight: 25 },
                "Таємно": { enabled: !0, weight: 25 },
                "Обирайте": { enabled: !0, weight: 25 },
                "18+": { enabled: !1, weight: 10 },
                "Я ніколи не": { enabled: !1, weight: 10 },
                "тест": { enabled: !1, weight: 0 },
                "Default": { enabled: !0, weight: 0 }
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


    const gamePage = document.querySelector('.game-page');
    if (gamePage && window.INITIAL_GAME_STATE) {
        let questionPool = [...window.INITIAL_GAME_STATE.questionPool];
        let players = JSON.parse(JSON.stringify(window.INITIAL_GAME_STATE.players)); 
        let currentPlayerIndex = window.INITIAL_GAME_STATE.currentPlayerIndex;
        let currentRound = window.INITIAL_GAME_STATE.currentRound;
        const gameConfig = window.INITIAL_GAME_STATE.gameConfig;
        const categoryStyles = window.INITIAL_GAME_STATE.categoryStyles;
        const allQuestionsDataMap = window.INITIAL_GAME_STATE.allQuestionsDataMap; // For accessing questions by ID

        // Game UI Elements
        const questionTextDisplay = document.getElementById('question-text-display');
        const currentPlayerNameDisplay = document.getElementById('current-player-name-display');
        const skipsLeftDisplay = document.getElementById('skips-left-display');
        const nextPlayerBtnInfo = document.getElementById('next-player-btn-info');
        const roundNumDisplay = document.getElementById('round-num');
        const activePlayersCountDisplay = document.getElementById('active-players-count');
        const categoryDisplay = document.getElementById('q-category');
        const questionIdDisplay = document.getElementById('q-id');
        const backgroundIconsContainer = document.querySelector('.background-icons-container');
        const btnSkip = document.getElementById('btn-skip');
        const btnCompleted = document.getElementById('btn-completed');
        const btnGoBack = document.getElementById('btn-go-back');
        const btnQuit = document.getElementById('btn-quit');
        const deferredMessagesDisplay = document.getElementById('deferred-messages-display');
        const deferredMessagesContent = document.getElementById('deferred-messages-content');

        // Timer Elements
        const timerContainer = document.getElementById('timer-container');
        const timerCircle = document.getElementById('timer-circle');
        let countdownTimer;
        let currentTimerValue = window.INITIAL_GAME_STATE.initialTimerValue;
        let currentTimerPhase = window.INITIAL_GAME_STATE.initialPhase;
        const mainTimerDurationSetting = window.INITIAL_GAME_STATE.initialMainTimerDuration;
        const readingTimerDurationSetting = gameConfig.general.reading_timer_duration;
        const serverTimeAtStart = window.INITIAL_GAME_STATE.serverTimeAtStart;


        // NEW: TTS Elements and Logic
        const ttsControlsContainer = document.querySelector('.tts-controls-container');
        const btnTts = document.getElementById('btn-tts');
        const ttsLanguageSelect = document.getElementById('tts-language-select');

        let voices = [];
        let selectedVoice = null;
        let currentUtterance = null; // To keep track of the current speech

        if ('speechSynthesis' in window && 'SpeechSynthesisUtterance' in window) {
            ttsControlsContainer.style.display = 'flex'; // Show controls if supported

            function populateVoiceList() {
                voices = speechSynthesis.getVoices();
                ttsLanguageSelect.innerHTML = ''; // Clear previous options
                
                // Prioritize Ukrainian voices, then English, then any available
                let voicesToDisplay = voices.filter(voice => voice.lang.startsWith('uk-'));
                if (voicesToDisplay.length === 0) {
                    voicesToDisplay = voices.filter(voice => voice.lang.startsWith('en-'));
                }
                if (voicesToDisplay.length === 0) {
                    voicesToDisplay = voices; // Fallback to any voice
                }

                if (voicesToDisplay.length === 0) {
                    btnTts.disabled = true;
                    btnTts.innerHTML = '<i class="fas fa-volume-off"></i> TTS недоступний'; // Update icon and text
                    ttsLanguageSelect.style.display = 'none';
                    return;
                }

                voicesToDisplay.forEach(voice => {
                    const option = document.createElement('option');
                    option.textContent = voice.name + ' (' + voice.lang + ')';
                    option.value = voice.name;
                    ttsLanguageSelect.appendChild(option);
                });

                // Set initial selected voice to the first suitable one
                selectedVoice = voicesToDisplay[0] || null;
                if (selectedVoice) {
                    ttsLanguageSelect.value = selectedVoice.name;
                }

                // Show language select only if multiple options are available
                if (voicesToDisplay.length > 1) {
                    ttsLanguageSelect.style.display = 'block';
                } else {
                    ttsLanguageSelect.style.display = 'none';
                }
            }

            // Load voices, handling asynchronous nature
            // The 'voiceschanged' event fires when the list of voices is loaded or changes.
            speechSynthesis.onvoiceschanged = populateVoiceList;
            // Also try to load immediately if voices are already available (e.g., on page reload)
            if (speechSynthesis.getVoices().length > 0) {
                populateVoiceList();
            }

            btnTts.addEventListener('click', () => {
                if (speechSynthesis.speaking) {
                    speechSynthesis.cancel(); // Stop current speech if any
                }

                if (!selectedVoice) {
                    console.warn('No voice selected for TTS.');
                    alert('Для озвучення не знайдено відповідного голосу.');
                    return;
                }
                
                // Get the current question text from the display
                let textToSpeak = questionTextDisplay.innerText;
                // Add deferred messages if present
                if (deferredMessagesDisplay.style.display !== 'none' && deferredMessagesContent.innerText.trim() !== '') {
                    textToSpeak += `. Активні ефекти: ${deferredMessagesContent.innerText}`;
                }


                currentUtterance = new SpeechSynthesisUtterance(textToSpeak);
                currentUtterance.voice = selectedVoice;
                currentUtterance.lang = selectedVoice.lang; // Crucial for correct pronunciation and voice selection

                currentUtterance.onstart = () => {
                    btnTts.disabled = true;
                    btnTts.innerHTML = '<i class="fas fa-volume-mute"></i>'; // Indicate speaking/muted
                };
                currentUtterance.onend = () => {
                    btnTts.disabled = false;
                    btnTts.innerHTML = '<i class="fas fa-volume-up"></i>'; // Back to speak icon
                };
                currentUtterance.onerror = (event) => {
                    console.error('SpeechSynthesisUtterance.onerror', event);
                    btnTts.disabled = false;
                    btnTts.innerHTML = '<i class="fas fa-volume-up"></i>'; // Back to speak icon
                    alert('Помилка відтворення голосу: ' + event.error);
                };

                speechSynthesis.speak(currentUtterance);
            });

            ttsLanguageSelect.addEventListener('change', (event) => {
                const selectedVoiceName = event.target.value;
                selectedVoice = voices.find(voice => voice.name === selectedVoiceName) || null;
            });

        } else {
            console.warn('Web Speech API is not supported in this browser.');
            ttsControlsContainer.style.display = 'none'; // Ensure it's hidden if not supported
        }
        // END NEW TTS Elements and Logic

        let playedQuestionIds = [window.INITIAL_GAME_STATE.questionPool[0].id]; // Track played question IDs for logging

        // History for "Go Back" button
        let questionHistory = [];

        function saveCurrentStateToHistory() {
            questionHistory.push({
                question: JSON.parse(JSON.stringify(window.INITIAL_GAME_STATE.questionPool[0])), // Deep copy
                playerIndex: currentPlayerIndex,
                round: currentRound,
                playersState: JSON.parse(JSON.stringify(players)), // Deep copy players state
                timerValue: currentTimerValue,
                timerPhase: currentTimerPhase
            });
            updateGoBackButtonState();
        }

        function restoreStateFromHistory() {
            if (questionHistory.length > 1) { // Need at least two items to go back
                // Pop the current state (that we're leaving)
                questionHistory.pop(); 
                const previousState = questionHistory[questionHistory.length - 1];

                // Restore state
                window.INITIAL_GAME_STATE.questionPool.unshift(previousState.question); // Add back to the front
                currentPlayerIndex = previousState.playerIndex;
                currentRound = previousState.round;
                players = JSON.parse(JSON.stringify(previousState.playersState)); // Restore players state
                currentTimerValue = previousState.timerValue;
                currentTimerPhase = previousState.timerPhase;

                // Re-render UI based on restored state
                updateQuestionDisplay();
                updatePlayerInfoDisplay();
                updateGoBackButtonState();
                updateSkipButtonState();
                startTimer(); // Restart timer with restored values
            }
        }

        function updateGoBackButtonState() {
            btnGoBack.disabled = questionHistory.length <= 1;
        }


        // Initial state save
        saveCurrentStateToHistory();


        function updatePlayerInfoDisplay() {
            const currentPlayer = players[currentPlayerIndex];
            currentPlayerNameDisplay.textContent = currentPlayer.name;
            skipsLeftDisplay.textContent = currentPlayer.skips_left;
            roundNumDisplay.textContent = currentRound;
            activePlayersCountDisplay.textContent = players.filter(p => p.active).length;

            let activeIndices = players.map((p, idx) => p.active ? idx : -1).filter(idx => idx !== -1);
            let nextPlayerName = 'Нікого';
            if (activeIndices.length > 0) {
                let currentPosInActive = activeIndices.indexOf(currentPlayerIndex);
                if (currentPosInActive !== -1) {
                    let nextPosInActive = (currentPosInActive + 1) % activeIndices.length;
                    let nextPlayerIdxVal = activeIndices[nextPosInActive];
                    nextPlayerName = players[nextPlayerIdxVal].name;
                } else { // Current player somehow not in active list, pick first active
                    nextPlayerName = players[activeIndices[0]].name;
                }
            }
            nextPlayerBtnInfo.textContent = nextPlayerName;
            updateSkipButtonState();
            updateDeferredMessagesDisplay(currentPlayer);
        }

        function updateDeferredMessagesDisplay(currentPlayer) {
            deferredMessagesContent.innerHTML = '';
            let hasDeferredMessages = false;
            if (currentPlayer.deferred_effects && currentPlayer.deferred_effects.length > 0) {
                currentPlayer.deferred_effects.forEach(effect => {
                    // Only display if turns_left is positive
                    if (effect.turns_left > 0) {
                        const message = effect.template
                            .replace('{TURNS_LEFT}', effect.turns_left)
                            .replace('{PLAYER_NAME}', currentPlayer.name);
                        const p = document.createElement('p');
                        p.textContent = message;
                        deferredMessagesContent.appendChild(p);
                        hasDeferredMessages = true;
                    }
                });
            }
            deferredMessagesDisplay.style.display = hasDeferredMessages ? 'block' : 'none';
        }

        function applyDeferredEffects() {
            players.forEach(player => {
                if (player.deferred_effects && player.deferred_effects.length > 0) {
                    player.deferred_effects.forEach(effect => {
                        if (effect.type === 'skip_turn') {
                            // This effect means the player *will* skip their *next* turn
                            // For simplicity, we apply it here and then remove.
                            // A more complex system might apply it at the start of their turn.
                            if (effect.turns_left > 0) {
                                // Logic for skipping the turn could go here or be managed differently
                                // For now, we'll just decrement and remove.
                            }
                        }
                    });
                    // Decrement turns_left and filter out expired effects
                    player.deferred_effects = player.deferred_effects.filter(effect => {
                        effect.turns_left--;
                        return effect.turns_left > 0;
                    });
                }
            });
        }


        function getRandomIcon() {
            const icons = ["fas fa-star", "fas fa-heart", "fas fa-ghost", "fas fa-brain", "fas fa-lightbulb", "fas fa-fire", "fas fa-dice", "fas fa-moon", "fas fa-sun", "fas fa-flask", "fas fa-trophy", "fas fa-gem"];
            return icons[Math.floor(Math.random() * icons.length)];
        }

        function addBackgroundIcons(categoryIconClasses, categoryIconColor, categoryIconOpacity) {
            // Clear existing icons
            backgroundIconsContainer.innerHTML = '';
            const numIcons = 5; 

            for (let i = 0; i < numIcons; i++) {
                const icon = document.createElement('i');
                const randomIconClass = categoryIconClasses && categoryIconClasses.length > 0 ? 
                                        categoryIconClasses[Math.floor(Math.random() * categoryIconClasses.length)] : 
                                        getRandomIcon(); // Fallback if no specific category icons

                icon.className = randomIconClass;
                icon.style.left = `${Math.random() * 100}vw`;
                icon.style.top = `${Math.random() * 100}vh`;
                icon.style.animationDelay = `${Math.random() * 10}s`;
                icon.style.animationDuration = `${20 + Math.random() * 20}s`;
                icon.style.setProperty('--icon-color', categoryIconColor || 'rgba(255,255,255,0.1)');
                icon.style.setProperty('--icon-opacity', categoryIconOpacity || 0.1);
                backgroundIconsContainer.appendChild(icon);
            }
        }
        
        function updateQuestionDisplay() {
            if (questionPool.length === 0) {
                endGame('Усі питання вичерпано!');
                return;
            }

            const currentQuestion = questionPool[0];
            const currentPlayer = players[currentPlayerIndex];

            // Replace placeholders in question text
            let displayQuestionText = currentQuestion.text.replace('{PLAYER_NAME}', currentPlayer.name);
            if (displayQuestionText.includes('{RANDOM_PLAYER_NAME}')) {
                const otherActivePlayers = players.filter((p, idx) => p.active && idx !== currentPlayerIndex);
                if (otherActivePlayers.length > 0) {
                    const randomOtherPlayer = otherActivePlayers[Math.floor(Math.random() * otherActivePlayers.length)];
                    displayQuestionText = displayQuestionText.replace('{RANDOM_PLAYER_NAME}', randomOtherPlayer.name);
                } else {
                    displayQuestionText = displayQuestionText.replace('{RANDOM_PLAYER_NAME}', '(інший гравець)');
                }
            }

            questionIdDisplay.textContent = currentQuestion.id;
            categoryDisplay.textContent = currentQuestion.category;
            questionTextDisplay.innerHTML = displayQuestionText.replace(/\n/g, '<br>');

            // Update background and icons
            const categoryStyle = categoryStyles[currentQuestion.category] || categoryStyles['Default'];
            document.documentElement.style.setProperty('--game-background', categoryStyle.background);
            addBackgroundIcons(categoryStyle.icon_classes, categoryStyle.icon_color, categoryStyle.icon_opacity);

            // Update timer state
            currentTimerValue = currentQuestion.timer || 0;
            if (currentTimerValue > 0 && readingTimerDurationSetting > 0) {
                currentTimerPhase = 'reading';
                currentTimerValue = readingTimerDurationSetting;
            } else {
                currentTimerPhase = 'main';
            }
            startTimer();

            updatePlayerInfoDisplay();
        }

        function startTimer() {
            clearInterval(countdownTimer);
            if (currentTimerValue <= 0 && currentTimerPhase === 'main') {
                timerContainer.style.display = 'none';
                timerCircle.textContent = '0';
                return;
            } else {
                 timerContainer.style.display = 'flex';
            }

            timerContainer.classList.remove('timer-reading', 'timer-main');
            timerContainer.classList.add('timer-' + currentTimerPhase);
            timerCircle.textContent = currentTimerValue;

            countdownTimer = setInterval(() => {
                currentTimerValue--;
                timerCircle.textContent = currentTimerValue;

                if (currentTimerValue <= 0) {
                    if (currentTimerPhase === 'reading') {
                        currentTimerPhase = 'main';
                        currentTimerValue = questionPool[0].timer || 0;
                        timerContainer.classList.remove('timer-reading');
                        timerContainer.classList.add('timer-main');
                        timerCircle.textContent = currentTimerValue; // Update immediately
                        if (currentTimerValue <= 0) { // If main timer is also 0, stop
                            clearInterval(countdownTimer);
                            timerContainer.style.display = 'none';
                            playTickTockSound(true); // Stop sound
                        } else {
                            playDingSound(); // Sound to indicate phase change
                            playTickTockSound(false); // Start tick-tock for main phase
                        }
                    } else { // currentTimerPhase === 'main' and timer reached 0
                        clearInterval(countdownTimer);
                        timerContainer.style.display = 'none';
                        playTickTockSound(true); // Stop sound
                    }
                } else {
                    if (currentTimerValue <= 5 && currentTimerPhase === 'main') {
                        playTickTockSound();
                    } else if (currentTimerValue === 10 && currentTimerPhase === 'reading') {
                         playTickTockSound(); // Optionally play tick-tock for reading too
                    }
                }
            }, 1000);
        }

        function playDingSound() {
            const audio = new Audio('sounds/ding.mp3');
            audio.play();
        }

        let tickTockAudio = null;
        function playTickTockSound(stop = false) {
            if (stop) {
                if (tickTockAudio) {
                    tickTockAudio.pause();
                    tickTockAudio.currentTime = 0;
                }
                return;
            }

            if (!tickTockAudio) {
                tickTockAudio = new Audio('sounds/tick-tock.wav');
                tickTockAudio.loop = true;
            }
            if (tickTockAudio.paused) {
                tickTockAudio.play().catch(e => console.warn("Failed to play tick-tock sound:", e));
            }
        }

        function nextPlayerAndQuestion() {
            playTickTockSound(true); // Stop any active timer sound

            // Apply deferred effects for all players before moving to the next turn
            applyDeferredEffects();
            
            // Remove the current question from the pool as it's been "played"
            const playedQid = questionPool.shift().id;
            playedQuestionIds.push(playedQid);

            // Find the next active player
            let nextPlayerFound = false;
            let originalPlayerIndex = currentPlayerIndex;
            for (let i = 0; i < players.length; i++) {
                currentPlayerIndex = (originalPlayerIndex + 1 + i) % players.length;
                if (players[currentPlayerIndex].active) {
                    nextPlayerFound = true;
                    break;
                }
            }

            if (!nextPlayerFound) {
                endGame('Не знайдено активних гравців для продовження гри. Гру завершено.');
                return;
            }

            // If we've circled back to the first player, increment the round
            if (currentPlayerIndex <= originalPlayerIndex && players.filter(p => p.active).indexOf(originalPlayerIndex) !== -1) {
                currentRound++;
            }

            const maxRounds = gameConfig.general.max_rounds || 5;
            if (currentRound > maxRounds) {
                endGame('Досягнуто максимальної кількості раундів. Гра завершена.');
                return;
            }
            
            saveCurrentStateToHistory(); // Save state before displaying new question
            updateQuestionDisplay();
        }

        function updateSkipButtonState() {
            btnSkip.disabled = players[currentPlayerIndex].skips_left <= 0;
        }

        btnCompleted.addEventListener('click', nextPlayerAndQuestion);

        btnSkip.addEventListener('click', () => {
            const currentPlayer = players[currentPlayerIndex];
            if (currentPlayer.skips_left > 0) {
                currentPlayer.skips_left--;
                updateSkipButtonState();
                nextPlayerAndQuestion();
            }
        });
        
        btnGoBack.addEventListener('click', () => {
            if (questionHistory.length > 1) { // Can go back only if there's previous history
                restoreStateFromHistory();
            }
        });

        btnQuit.addEventListener('click', () => {
            if (confirm('Ви впевнені, що хочете вийти з гри?')) {
                endGame('Гра завершена гравцем.');
            }
        });

        function endGame(message) {
            clearInterval(countdownTimer);
            playTickTockSound(true); // Stop any playing sounds
            if (speechSynthesis.speaking) {
                speechSynthesis.cancel(); // Stop any active speech
            }

            fetch('ajax_game_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'end_game',
                    played_question_ids: JSON.stringify(playedQuestionIds),
                    game_over_message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'game_over.php';
                } else {
                    console.error('Failed to end game:', data.message);
                    alert('Помилка при завершенні гри: ' + data.message + ' Будь ласка, перезавантажте сторінку.');
                    // Fallback in case of AJAX error, still go to game_over page
                    window.location.href = 'game_over.php'; 
                }
            })
            .catch(error => {
                console.error('Error ending game:', error);
                alert('Сталася помилка при спілкуванні з сервером. Спробуйте ще раз.');
                // Fallback in case of network error
                window.location.href = 'game_over.php';
            });
        }

        // Initialize display and timer with initial state
        updateQuestionDisplay(); 
        updatePlayerInfoDisplay();
        updateGoBackButtonState();
        updateSkipButtonState();
    }
});
