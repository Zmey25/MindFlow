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
            party: { settings: { reading_timer_duration: 8, max_rounds: 10, initial_skips: 2 }, categories: {"Розкрийся!":{enabled:!0,weight:20},"Штраф":{enabled:!0,weight:15},"Подарунок":{enabled:!0,weight:15},"Виклик":{enabled:!0,weight:35},"Машина Часу":{enabled:!0,weight:15},"Погляд у Майбутнє":{enabled:!0,weight:15},"А що, якби...?":{enabled:!0,weight:25},"Ти і Я":{enabled:!0,weight:30},"Сміхопанорама":{enabled:!0,weight:40},"Етична Дилема":{enabled:!0,weight:10},"Світ Кіно та Музики":{enabled:!0,weight:25},"Таємна Скринька":{enabled:!0,weight:15},"Креативний Куточок":{enabled:!0,weight:15},"Бліц!":{enabled:!0,weight:20},"Хто перший?":{enabled:!0,weight:30},"Ланцюгова Реакція":{enabled:!0,weight:35},"Ігровий Вірус":{enabled:!0,weight:25},"Дуель":{enabled:!0,weight:25},"Спільний Розум":{enabled:!0,weight:30},"Кривий Художник":{enabled:!0,weight:15},"Перекличка":{enabled:!0,weight:35},"Таємно":{enabled:!0,weight:25},"Обирайте":{enabled:!0,weight:30},"тест":{enabled:!1,weight:0},"Default":{enabled:!0,weight:0}}},
            deepTalk: { settings: { reading_timer_duration: 15, max_rounds: 8, initial_skips: 3 }, categories: {"Розкрийся!":{enabled:!0,weight:50},"Штраф":{enabled:!1,weight:10},"Подарунок":{enabled:!1,weight:10},"Виклик":{enabled:!0,weight:10},"Машина Часу":{enabled:!0,weight:45},"Погляд у Майбутнє":{enabled:!0,weight:45},"А що, якби...?":{enabled:!0,weight:35},"Ти і Я":{enabled:!0,weight:30},"Сміхопанорама":{enabled:!0,weight:20},"Етична Дилема":{enabled:!0,weight:40},"Світ Кіно та Музики":{enabled:!0,weight:25},"Таємна Скринька":{enabled:!0,weight:20},"Креативний Куточок":{enabled:!0,weight:15},"Бліц!":{enabled:!1,weight:10},"Хто перший?":{enabled:!1,weight:10},"Ланцюгова Реакція":{enabled:!0,weight:10},"Ігровий Вірус":{enabled:!1,weight:10},"Дуель":{enabled:!1,weight:10},"Спільний Розум":{enabled:!0,weight:15},"Кривий Художник":{enabled:!0,weight:10},"Перекличка":{enabled:!0,weight:10},"Таємно":{enabled:!0,weight:15},"Обирайте":{enabled:!0,weight:20},"тест":{enabled:!1,weight:0},"Default":{enabled:!0,weight:0}}},
            creative: { settings: { reading_timer_duration: 12, max_rounds: 8, initial_skips: 2 }, categories: {"Розкрийся!":{enabled:!0,weight:20},"Штраф":{enabled:!0,weight:10},"Подарунок":{enabled:!0,weight:10},"Виклик":{enabled:!0,weight:25},"Машина Часу":{enabled:!0,weight:20},"Погляд у Майбутнє":{enabled:!0,weight:20},"А що, якби...?":{enabled:!0,weight:50},"Ти і Я":{enabled:!0,weight:25},"Сміхопанорама":{enabled:!0,weight:30},"Етична Дилема":{enabled:!0,weight:10},"Світ Кіно та Музики":{enabled:!0,weight:25},"Таємна Скринька":{enabled:!0,weight:10},"Креативний Куточок":{enabled:!0,weight:50},"Бліц!":{enabled:!0,weight:15},"Хто перший?":{enabled:!0,weight:15},"Ланцюгова Реакція":{enabled:!0,weight:20},"Ігровий Вірус":{enabled:!0,weight:20},"Дуель":{enabled:!0,weight:15},"Спільний Розум":{enabled:!0,weight:25},"Кривий Художник":{enabled:!0,weight:50},"Перекличка":{enabled:!0,weight:15},"Таємно":{enabled:!0,weight:40},"Обирайте":{enabled:!0,weight:20},"тест":{enabled:!1,weight:0},"Default":{enabled:!0,weight:0}}},
            gameNight: { settings: { reading_timer_duration: 7, max_rounds: 12, initial_skips: 1 }, categories: {"Розкрийся!":{enabled:!0,weight:10},"Штраф":{enabled:!0,weight:20},"Подарунок":{enabled:!0,weight:20},"Виклик":{enabled:!0,weight:30},"Машина Часу":{enabled:!0,weight:10},"Погляд у Майбутнє":{enabled:!0,weight:10},"А що, якби...?":{enabled:!0,weight:15},"Ти і Я":{enabled:!0,weight:35},"Сміхопанорама":{enabled:!0,weight:20},"Етична Дилема":{enabled:!0,weight:10},"Світ Кіно та Музики":{enabled:!0,weight:20},"Таємна Скринька":{enabled:!0,weight:15},"Креативний Куточок":{enabled:!0,weight:15},"Бліц!":{enabled:!0,weight:35},"Хто перший?":{enabled:!0,weight:45},"Ланцюгова Реакція":{enabled:!0,weight:45},"Ігровий Вірус":{enabled:!0,weight:40},"Дуель":{enabled:!0,weight:40},"Спільний Розум":{enabled:!0,weight:40},"Кривий Художник":{enabled:!0,weight:20},"Перекличка":{enabled:!0,weight:35},"Таємно":{enabled:!0,weight:25},"Обирайте":{enabled:!0,weight:35},"тест":{enabled:!1,weight:0},"Default":{enabled:!0,weight:0}}},
            adultsOnly: { settings: { reading_timer_duration: 10, max_rounds: 10, initial_skips: 2 }, categories: {"Розкрийся!":{enabled:!0,weight:35},"Штраф":{enabled:!0,weight:30},"Подарунок":{enabled:!0,weight:20},"Виклик":{enabled:!0,weight:25},"Машина Часу":{enabled:!0,weight:20},"Погляд у Майбутнє":{enabled:!0,weight:20},"А що, якби...?":{enabled:!0,weight:25},"Ти і Я":{enabled:!0,weight:30},"Сміхопанорама":{enabled:!0,weight:25},"Етична Дилема":{enabled:!0,weight:25},"Світ Кіно та Музики":{enabled:!0,weight:15},"Таємна Скринька":{enabled:!0,weight:50},"Креативний Куточок":{enabled:!0,weight:15},"Бліц!":{enabled:!0,weight:20},"Хто перший?":{enabled:!0,weight:20},"Ланцюгова Реакція":{enabled:!0,weight:20},"Ігровий Вірус":{enabled:!0,weight:25},"Дуель":{enabled:!0,weight:25},"Спільний Розум":{enabled:!0,weight:20},"Кривий Художник":{enabled:!0,weight:20},"Перекличка":{enabled:!0,weight:30},"Таємно":{enabled:!0,weight:35},"Обирайте":{enabled:!0,weight:30},"тест":{enabled:!1,weight:0},"Default":{enabled:!0,weight:0}}},
            default: { settings: { reading_timer_duration: 10, max_rounds: 10, initial_skips: 2 }, categories: {"Розкрийся!":{enabled:!0,weight:25},"Штраф":{enabled:!0,weight:15},"Подарунок":{enabled:!0,weight:15},"Виклик":{enabled:!0,weight:25},"Машина Часу":{enabled:!0,weight:25},"Погляд у Майбутнє":{enabled:!0,weight:25},"А що, якби...?":{enabled:!0,weight:25},"Ти і Я":{enabled:!0,weight:25},"Сміхопанорама":{enabled:!0,weight:25},"Етична Дилема":{enabled:!0,weight:15},"Світ Кіно та Музики":{enabled:!0,weight:25},"Таємна Скринька":{enabled:!0,weight:15},"Креативний Куточок":{enabled:!0,weight:25},"Бліц!":{enabled:!0,weight:25},"Хто перший?":{enabled:!0,weight:25},"Ланцюгова Реакція":{enabled:!0,weight:25},"Ігровий Вірус":{enabled:!0,weight:25},"Дуель":{enabled:!0,weight:25},"Спільний Розум":{enabled:!0,weight:25},"Кривий Художник":{enabled:!0,weight:25},"Перекличка":{enabled:!0,weight:25},"Таємно":{enabled:!0,weight:25},"Обирайте":{enabled:!0,weight:25},"тест":{enabled:!1,weight:0},"Default":{enabled:!0,weight:0}}}
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
        
        let currentQuestion = null;
        let gameHistoryForUndo = []; 
        let playedQuestionIdsThisSession = new Set(); 

        let timerInterval;
        let secondsLeft;
        let currentTimerPhase; 
        let effectiveReadingDuration;
        let mainTimerDurationFromQuestion;
        const tickSound = new Audio('sounds/tick-tock.wav');
        tickSound.loop = true;
        const dingSound = new Audio('sounds/ding.mp3');
        const doneSound = new Audio('sounds/ding.mp3');

        const qIdDisplay = document.getElementById('q-id');
        const qCategoryDisplay = document.getElementById('q-category');
        const roundNumDisplay = document.getElementById('round-num');
        const activePlayersCountDisplay = document.getElementById('active-players-count');
        const timerContainer = document.getElementById('timer-container');
        const timerCircle = document.getElementById('timer-circle');
        const currentPlayerNameDisplay = document.getElementById('current-player-name-display');
        const questionTextDisplay = document.getElementById('question-text-display');
        const deferredMessagesDisplay = document.getElementById('deferred-messages-display');
        const deferredMessagesContent = document.getElementById('deferred-messages-content');
        const nextPlayerBtnInfo = document.getElementById('next-player-btn-info');
        const skipsLeftDisplay = document.getElementById('skips-left-display');
        const btnCompleted = document.getElementById('btn-completed');
        const btnSkip = document.getElementById('btn-skip');
        const btnGoBack = document.getElementById('btn-go-back');
        const btnQuit = document.getElementById('btn-quit');
        const backgroundIconsContainer = document.querySelector('.background-icons-container');
        
        function deepCopy(obj) {
            return JSON.parse(JSON.stringify(obj));
        }

        function updateTimerDisplayOnly() {
            if (timerCircle) {
                timerCircle.textContent = Math.max(0, Math.floor(secondsLeft));
            }
            if (timerContainer) {
                timerContainer.className = `timer-container timer-${currentTimerPhase}`;
                if (effectiveReadingDuration <= 0 && mainTimerDurationFromQuestion <= 0) {
                     timerContainer.style.display = 'none';
                } else {
                     timerContainer.style.display = 'flex';
                }
            }
        }
        
        function stopAllTimerSounds() {
            tickSound.pause();
            tickSound.currentTime = 0;
        }

        function startTimerLogic() {
            clearInterval(timerInterval);
            stopAllTimerSounds();
            updateTimerDisplayOnly();

            if (currentTimerPhase === 'reading' && effectiveReadingDuration > 0 && secondsLeft > 0) {
                // No sound
            } else if (currentTimerPhase === 'main' && mainTimerDurationFromQuestion > 0 && secondsLeft > 0) {
                tickSound.play().catch(e => console.warn("Main timer sound blocked."));
            }

             if ( (currentTimerPhase === 'main' && secondsLeft <= 0 && mainTimerDurationFromQuestion > 0) ||
                  (currentTimerPhase === 'reading' && secondsLeft <= 0 && effectiveReadingDuration > 0 && mainTimerDurationFromQuestion <= 0) ) {
                 return; 
            }
            if (effectiveReadingDuration <= 0 && mainTimerDurationFromQuestion <= 0) return; 

            timerInterval = setInterval(() => {
                secondsLeft--;
                updateTimerDisplayOnly();

                if (currentTimerPhase === 'reading' && secondsLeft <= 0 && effectiveReadingDuration > 0) {
                    currentTimerPhase = 'main';
                    secondsLeft = mainTimerDurationFromQuestion; 
                    updateTimerDisplayOnly();
                    stopAllTimerSounds();
                    if (mainTimerDurationFromQuestion > 0) {
                        tickSound.play().catch(e => console.warn("Timer sound blocked."));
                    } else {
                        clearInterval(timerInterval);
                    }
                } else if (currentTimerPhase === 'main' && secondsLeft <= 0 && mainTimerDurationFromQuestion > 0) {
                    clearInterval(timerInterval);
                    stopAllTimerSounds();
                    dingSound.play().catch(e => console.warn("Timer sound blocked."));
                } else if ((currentTimerPhase === 'reading' && effectiveReadingDuration <=0) || (currentTimerPhase === 'main' && mainTimerDurationFromQuestion <=0)) {
                     clearInterval(timerInterval);
                     stopAllTimerSounds();
                }
            }, 1000);
        }

        function setupTimersForCurrentQuestion() {
            mainTimerDurationFromQuestion = parseInt(currentQuestion.timer || 0);
            const readingTimerSetting = parseInt(gameConfig.general.reading_timer_duration || 0);
            
            effectiveReadingDuration = 0;
            if (mainTimerDurationFromQuestion > 0 && readingTimerSetting > 0) {
                effectiveReadingDuration = readingTimerSetting;
            }

            if (effectiveReadingDuration > 0) {
                currentTimerPhase = 'reading';
                secondsLeft = effectiveReadingDuration;
            } else if (mainTimerDurationFromQuestion > 0) {
                currentTimerPhase = 'main';
                secondsLeft = mainTimerDurationFromQuestion;
            } else {
                currentTimerPhase = 'main'; 
                secondsLeft = 0;
            }
            startTimerLogic();
        }

        function getActivePlayerIndices() {
            return players.map((p, i) => p.active ? i : -1).filter(i => i !== -1);
        }
        
        function getNextActivePlayerIndex(currentIdx) {
            const activeIndices = getActivePlayerIndices();
            if (activeIndices.length === 0) return null;
            
            const currentPositionInActive = activeIndices.indexOf(currentIdx);
            if (currentPositionInActive === -1) { 
                return activeIndices[0]; 
            }
            
            const nextPositionInActive = (currentPositionInActive + 1) % activeIndices.length;
            return activeIndices[nextPositionInActive];
        }

        function updateDisplay(isRestoringFromHistory = false) {
            if (!currentQuestion) return; 

            const currentPlayer = players[currentPlayerIndex];
            document.title = `Гра: Хід ${currentPlayer.name}`;

            qIdDisplay.textContent = currentQuestion.id;
            qCategoryDisplay.textContent = currentQuestion.category;
            roundNumDisplay.textContent = currentRound;
            activePlayersCountDisplay.textContent = getActivePlayerIndices().length;
            currentPlayerNameDisplay.textContent = currentPlayer.name;
            
            let qText = currentQuestion.text.replace('{PLAYER_NAME}', currentPlayer.name);
            if (qText.includes('{RANDOM_PLAYER_NAME}')) {
                const otherActivePlayers = players.filter((p, i) => p.active && i !== currentPlayerIndex);
                const randomOtherPlayerName = otherActivePlayers.length > 0 ? otherActivePlayers[Math.floor(Math.random() * otherActivePlayers.length)].name : '(інший гравець)';
                qText = qText.replace('{RANDOM_PLAYER_NAME}', randomOtherPlayerName);
            }
            questionTextDisplay.innerHTML = qText.replace(/\n/g, '<br>');
            
            const deferredEffectsForCurrentPlayer = Array.isArray(currentPlayer.deferred_effects) ? currentPlayer.deferred_effects : [];
            const activeDeferredEffects = deferredEffectsForCurrentPlayer.filter(effect => effect.turns_left > 0);

            if (activeDeferredEffects.length > 0) {
                let effectsHtml = '';
                activeDeferredEffects.forEach(effect => {
                    effectsHtml += `<p>${effect.template.replace('{TURNS_LEFT}', effect.turns_left).replace('{PLAYER_NAME}', currentPlayer.name)}</p>`;
                });
                deferredMessagesContent.innerHTML = effectsHtml;
                deferredMessagesDisplay.style.display = 'block';
            } else {
                deferredMessagesContent.innerHTML = '';
                deferredMessagesDisplay.style.display = 'none';
            }

            skipsLeftDisplay.textContent = currentPlayer.skips_left;
            btnSkip.disabled = currentPlayer.skips_left <= 0;

            const nextPlayerIdx = getNextActivePlayerIndex(currentPlayerIndex);
            nextPlayerBtnInfo.textContent = nextPlayerIdx !== null ? players[nextPlayerIdx].name : 'Нікого';
            
            btnGoBack.disabled = gameHistoryForUndo.length === 0;

            const styleInfo = categoryStyles[currentQuestion.category] || categoryStyles['Default'] || {background: 'linear-gradient(to right, #74ebd5, #ACB6E5)', icon_classes:['fas fa-question-circle'], icon_color:'rgba(255,255,255,0.1)', icon_opacity:0.1};
            document.documentElement.style.setProperty('--game-background', styleInfo.background);
            document.documentElement.style.setProperty('--icon-color', styleInfo.icon_color || 'rgba(255,255,255,0.1)');
            document.documentElement.style.setProperty('--icon-opacity', styleInfo.icon_opacity || 0.1);
            
            if (!isRestoringFromHistory) { 
                backgroundIconsContainer.innerHTML = ''; 
                const iconClasses = styleInfo.icon_classes || ['fas fa-question-circle'];
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
                        backgroundIconsContainer.appendChild(icon);
                    }
                }
            }
            setupTimersForCurrentQuestion();
        }
        
        function saveCurrentStateForUndo() {
            if (gameHistoryForUndo.length >= 20) gameHistoryForUndo.shift();
            gameHistoryForUndo.push({
                question: deepCopy(currentQuestion),
                playerIndex: currentPlayerIndex,
                round: currentRound,
                playersSnapshot: deepCopy(players)
            });
        }

        function selectAndDisplayQuestion(isAfterSkip = false) {
            if (!isAfterSkip && currentQuestion) { 
                saveCurrentStateForUndo();
            }

            if (questionPool.length === 0) {
                triggerGameOverJS("Питання закінчились!");
                return;
            }
            currentQuestion = questionPool.shift();
            playedQuestionIdsThisSession.add(currentQuestion.id);
            updateDisplay();
        }

        function handlePlayerAction(isCompletedOrQuitAction) {
             const actingPlayer = players[currentPlayerIndex]; 

            if (isCompletedOrQuitAction) { 
                if (!Array.isArray(actingPlayer.deferred_effects)) {
                    actingPlayer.deferred_effects = [];
                }
                if (actingPlayer.deferred_effects.length > 0) {
                    actingPlayer.deferred_effects = actingPlayer.deferred_effects.map(effect => ({
                        ...effect,
                        turns_left: effect.turns_left - 1
                    })).filter(effect => effect.turns_left > 0);
                }
            }

            const activePlayerIndices = getActivePlayerIndices();
            if (activePlayerIndices.length < 2) {
                triggerGameOverJS(activePlayerIndices.length === 1 ? "Залишився переможець!" : "Гравців не залишилось!");
                return;
            }

            const oldPlayerIndex = currentPlayerIndex;
            const nextPlayerIdx = getNextActivePlayerIndex(oldPlayerIndex);

            if (nextPlayerIdx === null) { 
                triggerGameOverJS("Не вдалося знайти наступного гравця.");
                return;
            }
            
            const firstActivePlayerIndex = activePlayerIndices[0];
            if (nextPlayerIdx === firstActivePlayerIndex && activePlayerIndices.indexOf(oldPlayerIndex) === activePlayerIndices.length -1) {
                 if (oldPlayerIndex !== nextPlayerIdx || activePlayerIndices.length > 1) { 
                    currentRound++;
                 }
            }
            
            currentPlayerIndex = nextPlayerIdx;

            if (currentRound > gameConfig.general.max_rounds) {
                triggerGameOverJS(`${gameConfig.general.max_rounds} кіл зіграно. Гра завершена!`);
                return;
            }
            selectAndDisplayQuestion();
        }

        btnCompleted.addEventListener('click', () => {
            doneSound.play().catch(e => console.warn("Done sound was blocked."));
            const q = currentQuestion;
            const player = players[currentPlayerIndex];
            if (q.bonus_skip_on_complete) player.skips_left++;
            
            if (q.deferred_text_template && q.deferred_turns_player) {
                if (!Array.isArray(player.deferred_effects)) {
                    player.deferred_effects = [];
                }
                player.deferred_effects.push({
                    template: q.deferred_text_template,
                    turns_left: parseInt(q.deferred_turns_player),
                    question_id: q.id
                });
            }
            handlePlayerAction(true);
        });

        btnSkip.addEventListener('click', () => {
            const player = players[currentPlayerIndex];
            if (player.skips_left > 0) {
                saveCurrentStateForUndo(); 
                player.skips_left--;
                selectAndDisplayQuestion(true); 
            }
        });
        
        btnQuit.addEventListener('click', () => {
            saveCurrentStateForUndo(); 
            players[currentPlayerIndex].active = false;
            handlePlayerAction(true);
        });

        btnGoBack.addEventListener('click', () => {
            if (gameHistoryForUndo.length > 0) {
                const questionToPutBack = deepCopy(currentQuestion);
                const prevState = gameHistoryForUndo.pop();
                const previousPlayersSnapshot = deepCopy(prevState.playersSnapshot);

                players = players.map((currentPlayerInLoop, index) => {
                    const snapshotForThisPlayer = previousPlayersSnapshot[index];
                    if (snapshotForThisPlayer) {
                        return {
                            ...deepCopy(snapshotForThisPlayer),
                            skips_left: currentPlayerInLoop.skips_left 
                        };
                    }
                    return currentPlayerInLoop; 
                });
                
                currentQuestion = deepCopy(prevState.question);
                currentPlayerIndex = prevState.playerIndex; 
                currentRound = prevState.round;
                
                if (questionToPutBack && questionToPutBack.id !== currentQuestion.id) { 
                    questionPool.unshift(questionToPutBack);
                    if (playedQuestionIdsThisSession.has(questionToPutBack.id)) {
                        playedQuestionIdsThisSession.delete(questionToPutBack.id); 
                    }
                }
                
                updateDisplay(true); 
                btnGoBack.disabled = gameHistoryForUndo.length === 0;
            }
        });

        function triggerGameOverJS(message) {
            clearInterval(timerInterval);
            stopAllTimerSounds();
            
            const formData = new FormData();
            formData.append('action', 'end_game');
            formData.append('played_question_ids', JSON.stringify(Array.from(playedQuestionIdsThisSession)));
            formData.append('game_over_message', message);

            fetch('ajax_game_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'game_over.php';
                } else {
                    alert('Помилка завершення гри: ' + data.message + '\nСпробуйте оновити сторінку.');
                }
            })
            .catch(error => {
                console.error('Error ending game:', error);
                alert('Помилка зв\'язку з сервером при завершенні гри.');
            });
        }
        
        function initializeFirstQuestionDisplay() {
            effectiveReadingDuration = window.INITIAL_GAME_STATE.initialEffectiveReadingDuration;
            mainTimerDurationFromQuestion = window.INITIAL_GAME_STATE.initialMainTimerDuration;
            secondsLeft = window.INITIAL_GAME_STATE.initialTimerValue;
            currentTimerPhase = window.INITIAL_GAME_STATE.initialPhase;
            
            const serverTimeNow = Math.floor(Date.now() / 1000);
            const serverTimeAtPageLoad = window.INITIAL_GAME_STATE.serverTimeAtStart || serverTimeNow;
            const timeElapsedOnClientSinceLoad = serverTimeNow - serverTimeAtPageLoad;

            if (currentTimerPhase === 'reading' && effectiveReadingDuration > 0) {
                 secondsLeft -= timeElapsedOnClientSinceLoad;
                 if(secondsLeft <=0) { 
                    const deficit = Math.abs(secondsLeft);
                    currentTimerPhase = 'main';
                    secondsLeft = mainTimerDurationFromQuestion - deficit;
                 }
            } else if (currentTimerPhase === 'main' && mainTimerDurationFromQuestion > 0) {
                secondsLeft -= timeElapsedOnClientSinceLoad;
            }
            secondsLeft = Math.max(0, secondsLeft);

            currentQuestion = questionPool[0]; 
            updateDisplay(); 
            questionPool.shift(); 
            playedQuestionIdsThisSession.add(currentQuestion.id);

            const currentPlayer = players[currentPlayerIndex];
            skipsLeftDisplay.textContent = currentPlayer.skips_left;
            btnSkip.disabled = currentPlayer.skips_left <= 0;
            btnGoBack.disabled = gameHistoryForUndo.length === 0; 
        }

        if (questionPool && questionPool.length > 0) {
            initializeFirstQuestionDisplay();
        } else {
            triggerGameOverJS("Немає питань для гри.");
        }
    }

    if (navigator.userAgent.match(/iPhone|iPad|iPod/i)) {
        document.documentElement.addEventListener('touchend', (e) => {
            if (e.touches.length > 1) e.preventDefault();
        }, { passive: false });
    }
});
