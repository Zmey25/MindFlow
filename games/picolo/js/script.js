document.addEventListener('DOMContentLoaded', function() {
    // --- Logic for index.php (no changes) ---
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

    // --- Logic for game.php ---
    const gamePage = document.querySelector('.game-page');
    if (gamePage && window.GAME_DATA) {
        // --- Background setup (no changes) ---
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
        
        // --- NEW: Timer and Sound Logic ---
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
            let audioUnlocked = false;

            const stopAllSounds = () => {
                tickSound.pause();
                tickSound.currentTime = 0;
            };

            const playTickSoundIfNeeded = () => {
                if (audioUnlocked && currentPhase === 'main' && secondsLeft > 0) {
                    tickSound.play().catch(e => console.warn("Tick sound play failed:", e));
                }
            };
            
            const unlockAudio = () => {
                if (audioUnlocked) return;
                const promise = tickSound.play();
                if (promise !== undefined) {
                    promise.then(() => {
                        tickSound.pause();
                        audioUnlocked = true;
                        playTickSoundIfNeeded(); // Try to play immediately after unlock
                        console.log("Audio unlocked.");
                    }).catch(error => {
                        console.log("Audio unlock failed, will retry on next interaction.");
                    });
                }
            };
            document.querySelectorAll('.action-btn').forEach(btn => btn.addEventListener('click', unlockAudio, { once: true }));

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
                playTickSoundIfNeeded();

                if (secondsLeft <= 0) return;

                timerInterval = setInterval(() => {
                    secondsLeft--;
                    updateTimerDisplay();

                    if (currentPhase === 'reading' && secondsLeft <= 0) {
                        currentPhase = 'main';
                        secondsLeft = mainTimerDuration;
                        updateTimerDisplay();
                        playTickSoundIfNeeded();
                    } else if (currentPhase === 'main' && secondsLeft <= 0) {
                        clearInterval(timerInterval);
                        stopAllSounds();
                        if (audioUnlocked) dingSound.play().catch(e => console.warn("Ding sound blocked:", e));
                    }
                }, 1000);
            };

            startTimer();
        }
    }

    // --- Prevent iOS scaling (no changes) ---
    if (navigator.userAgent.match(/iPhone|iPad|iPod/i)) {
        document.documentElement.addEventListener('touchend', (e) => {
            if (e.touches.length > 1) e.preventDefault();
        }, { passive: false });
    }
});
