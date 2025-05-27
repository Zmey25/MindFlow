<?php
// telegram_handler.php
// Цей файл слугує точкою входу для вебхука Telegram бота.

// Налаштовуємо виведення помилок для налагодження. У продакшн-середовищі краще лише логувати помилки.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Визначаємо шлях до кореня проекту.
define('ROOT_DIR', __DIR__);
// Визначаємо шляхи до директорій з даними та відповідями
define('DATA_DIR', ROOT_DIR . '/data');
define('ANSWERS_DIR_PATH', DATA_DIR . '/answers');
define('USERS_FILE_PATH', DATA_DIR . '/users.json');
define('QUESTIONS_FILE_PATH', DATA_DIR . '/questions.json'); // Додано
define('TRAITS_FILE_PATH', DATA_DIR . '/traits.json');       // Додано
define('BADGES_FILE_PATH', DATA_DIR . '/badges.json');       // Додано


// Завантажуємо змінні оточення з файлу .env.
// Припускаємо, що .env лежить на рівень вище ROOT_DIR (наприклад, поза public_html)
// Якщо .env лежить в ROOT_DIR, змініть на ROOT_DIR . '/.env'
require_once ROOT_DIR . '/includes/env-loader.php';
loadEnv(ROOT_DIR . '/../.env'); // Використовуємо ROOT_DIR, визначений вище

// Завантажуємо загальні службові функції, включаючи `custom_log` та `readJsonFile`.
require_once ROOT_DIR . '/includes/functions.php';

// Завантажуємо функції взаємодії з Gemini API, включаючи determineRelevantData та getGeminiAnswer.
require_once ROOT_DIR . '/includes/gemini_api.php'; // Цей файл тепер містить LLM логіку та loadUserData (або припускає її наявність у functions)


// Отримуємо Telegram Bot Token зі змінних оточення.
$telegramToken = getenv('TELEGRAM_TOKEN');

// Визначаємо Admin Chat ID
define('ADMIN_CHAT_ID', 1282207313); // Ваш Telegram Chat ID

if (!$telegramToken) {
    custom_log('TELEGRAM_TOKEN не встановлено в файлі .env. Неможливо обробити вебхук Telegram.', 'telegram_error');
    http_response_code(500);
    die('Помилка конфігурації: відсутній токен Telegram.');
}

$input = file_get_contents('php://input');
$update = json_decode($input, true);

custom_log('Отримано оновлення Telegram Webhook: ' . $input, 'telegram_webhook');

if (json_last_error() !== JSON_ERROR_NONE) {
    custom_log('Не вдалося декодувати JSON з вебхука Telegram: ' . json_last_error_msg(), 'telegram_error');
    http_response_code(400);
    die('Отримано недійсний JSON ввід.');
}

/**
 * Функція для надсилання повідомлення назад до Telegram.
 * (Залишається без змін)
 */
function sendTelegramMessage(int $chatId, string $text, string $telegramToken): void {
    $apiUrl = "https://api.telegram.org/bot{$telegramToken}/sendMessage";
    $postFields = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Тайм-аут для надсилання повідомлення

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        custom_log("Помилка cURL при надсиланні повідомлення до Chat ID {$chatId}: " . $curlError, 'telegram_error');
    } elseif ($httpCode !== 200) {
         $responseBody = is_string($response) ? $response : json_encode($response); // Логуємо тіло відповіді
        custom_log("Telegram API повернув HTTP {$httpCode} для Chat ID {$chatId}: " . $responseBody, 'telegram_error');
    } else {
        custom_log("Успішно надіслано повідомлення до Chat ID {$chatId}", 'telegram_webhook');
    }
}


if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = trim($message['text'] ?? '');
    $lowerText = mb_strtolower($text); // Для перевірки ключових слів без урахування регістру

    // Визначаємо, чи запит від адміністратора
    $is_admin_request = ($chatId == ADMIN_CHAT_ID);

    custom_log("Обробка повідомлення з Chat ID: {$chatId}. Текст: '{$text}' (Admin: " . ($is_admin_request ? 'Так' : 'Ні') . ")", 'telegram_webhook');

    $responseText = '';

    // Обробка статичних/простих команд ПЕРЕД викликом LLM
    if (strpos($text, '/start') === 0) {
        $responseText = "Вітаю! Я ваш персональний бот для аналізу особистості. Ви можете запитати мене про користувачів, питання, риси чи бейджи. Спробуйте '/ask [ваше питання]' або просто поставте питання.";
    } elseif (strpos($text, '/help') === 0) {
        $responseText = "Я розумію кілька команд: \n/start - почати діалог.\n/help - отримати допомогу.\n/ask [питання] або просто ваше питання - задати питання про дані проекту.\n\nТакож ви можете запитати:\n- 'хто ти?' або 'про проект' - для інформації про мене.\n- 'порівняй [користувач1] та [користувач2]' - для порівняння результатів.\n- 'які результати у [користувач]?' - для інформації по конкретному користувачу.";
    } elseif (strpos($text, '/test_log') === 0) {
        custom_log("Користувач {$chatId} використав команду /test_log.", 'telegram_test');
        $responseText = "Перевіряю лог. Якщо все працює, ви побачите запис в `logs/telegram_test.log`.";
    }
    // Перевірка на запити про бота/проект - статична відповідь
    elseif (preg_match('/(хто ти\??|про проект|що це за бот\??|про mindflow)/ui', $lowerText)) {
        $responseText = "Я Маскот проєкту психологічних тестів MindFlow! Я кіт (або кішка, як вам більше подобається 😉), ваш персональний секретар і помічник. Моя робота - швидко знаходити та надавати вам інформацію з результатів тестів користувачів. Запитуйте!";
    }
    // Основна логіка обробки, якщо це не проста команда або запит "про проект"
    elseif (!empty($text)) {
        // Надсилаємо проміжне повідомлення, щоб користувач не чекав довго
        sendTelegramMessage($chatId, "🤖 Аналізую ваш запит і готую дані, хвилинку...", $telegramToken);

        // Крок 1: Маршрутизація за допомогою LLM1
        custom_log("Calling determineRelevantData with query: '{$text}'", 'telegram_webhook');
        $geminiRouteResult = determineRelevantData($text);

        if (isset($geminiRouteResult['error'])) {
            $responseText = "Вибачте, виникла помилка під час аналізу вашого запиту (LLM1): " . $geminiRouteResult['error'];
            custom_log("LLM1 routing error: " . $responseText, 'telegram_error');
        } else {
            $potentialDataSources = $geminiRouteResult['potential_data_sources'];
            $targetUsernames = $geminiRouteResult['target_usernames']; // Це канонічні імена, знайдені LLM1 у списку
            $refinedQuery = $geminiRouteResult['refined_query'];

            custom_log("LLM1 Route Result: potential_data_sources=" . json_encode($potentialDataSources) . ", target_usernames=" . json_encode($targetUsernames) . ", refined_query='{$refinedQuery}'", 'telegram_webhook');

            $contextData = []; // Масив для збору всіх даних для LLM2
            $dataLoadingError = null; // Для фіксації помилки завантаження даних для відповіді LLM2

            // Крок 2: Завантаження даних на основі рекомендацій LLM1
            custom_log("Starting data loading based on potential_data_sources: " . json_encode($potentialDataSources), 'telegram_webhook');

            // Спочатку спробуємо завантажити дані користувачів, якщо вони потрібні.
            // Це важливо зробити першими, щоб обробити помилки доступу/існування користувачів.
            $loadedUserDataSets = []; // Для даних одного або двох користувачів
            $failedUsernames = []; // Для користувачів, чиї дані не вдалося завантажити
            $userAnswersRequested = in_array('user_answers', $potentialDataSources);

            if ($userAnswersRequested) {
                 if (empty($targetUsernames)) {
                     // LLM1 хотів user_answers, але не ідентифікував користувачів
                     $dataLoadingError = "Запит стосується даних користувача(ів), але ШІ не визначив жодного імені користувача.";
                     custom_log($dataLoadingError, 'telegram_error');
                 } else {
                    foreach ($targetUsernames as $username) {
                         // --- loadUserData handles existence, hide_results, and admin check ---
                         $loadResult = loadUserData($username, $is_admin_request);
                         if ($loadResult['success']) {
                             $loadedUserDataSets[$username] = $loadResult['data']; // Зберігаємо дані під іменем користувача
                         } else {
                             // Якщо дані користувача не завантажилися (наприклад, hide_results або не знайдено файл)
                             // Зберігаємо повідомлення про помилку для цього користувача.
                             // Ми НЕ перериваємо цикл одразу, якщо запит на порівняння - спробуємо завантажити іншого.
                             custom_log("Failed to load data for user '{$username}': " . $loadResult['message'], 'telegram_error');
                             $failedUsernames[] = $username . " (" . $loadResult['message'] . ")";
                         }
                    }

                    // Після спроби завантажити всіх цільових користувачів
                    if (!empty($failedUsernames)) {
                         $dataLoadingError = "Не вдалося завантажити дані для наступних користувачів: " . implode(", ", $failedUsernames) . ".";
                         if (empty($loadedUserDataSets)) {
                             // Якщо ЖОДЕН з цільових користувачів не завантажився
                             custom_log("No target user data loaded. Setting dataLoadingError: " . $dataLoadingError, 'telegram_error');
                         } else {
                             // Якщо деякі користувачі завантажились, але деякі ні (наприклад, при порівнянні)
                             // dataLoadingError встановлено, але ми продовжимо, щоб LLM2 міг прокоментувати це.
                             custom_log("Some target user data failed to load, but others succeeded. Message: " . $dataLoadingError, 'telegram_warning');
                         }
                    }

                     // Додаємо завантажені дані користувачів до контексту для LLM2
                     if (!empty($loadedUserDataSets)) {
                        if (count($loadedUserDataSets) === 1) {
                            $contextData['user_data'] = reset($loadedUserDataSets); // Беремо першого (і єдиного)
                            custom_log("Added 1 user data set to context.", 'telegram_webhook');
                        } elseif (count($loadedUserDataSets) === 2) {
                             // Припускаємо, що $targetUsernames має 2 імені, і $loadedUsersData має дані для обох
                            $usernamesLoaded = array_keys($loadedUserDataSets);
                             $contextData['comparison_data'] = [
                                'user1_data' => $loadedUserDataSets[$usernamesLoaded[0]],
                                'user2_data' => $loadedUserDataSets[$usernamesLoaded[1]],
                                'user1_username' => $usernamesLoaded[0],
                                'user2_username' => $usernamesLoaded[1]
                             ];
                            custom_log("Added 2 user data sets for comparison to context.", 'telegram_webhook');
                        } else {
                             // Більше 2 користувачів (хоча LLM1 мав би повернути 0, 1 або 2 для user_answers)
                             // Це малоймовірно, але варто обробити
                             $dataLoadingError = "Завантажено невірну кількість наборів даних користувачів для обробки (" . count($loadedUserDataSets) . "). Очікувалось 1 або 2.";
                             custom_log($dataLoadingError, 'telegram_error');
                        }
                     }
                 }
            }


            // Завантажуємо інші типи даних, незалежно від помилок користувачів (якщо вони некритичні)
            foreach ($potentialDataSources as $sourceType) {
                 // Дані користувачів вже оброблені вище
                 if ($sourceType === 'user_answers' || $sourceType === 'none') {
                     continue;
                 }

                switch ($sourceType) {
                    case 'users':
                        $allUsersData = readJsonFile(USERS_FILE_PATH);
                        // Фільтруємо чутливі поля перед передачею LLM2
                        $filteredUsers = [];
                        if (!empty($allUsersData)) {
                            foreach($allUsersData as $user) {
                                unset($user['password_hash'], $user['password'], $user['google_id']); // Залишаємо is_admin, hide_results тощо
                                $filteredUsers[] = $user;
                            }
                        }
                        $contextData['all_users_list'] = $filteredUsers;
                        custom_log("Loaded 'users' list data.", 'telegram_webhook');
                        break;
                    case 'questions':
                        $contextData['all_questions'] = readJsonFile(QUESTIONS_FILE_PATH);
                         custom_log("Loaded 'questions' data.", 'telegram_webhook');
                        break;
                    case 'traits':
                        $contextData['all_traits'] = readJsonFile(TRAITS_FILE_PATH);
                         custom_log("Loaded 'traits' data.", 'telegram_webhook');
                        break;
                    case 'badges':
                        $contextData['all_badges'] = readJsonFile(BADGES_FILE_PATH);
                         custom_log("Loaded 'badges' data.", 'telegram_webhook');
                        break;
                    // 'user_answers' та 'none' оброблені вище
                    default:
                        custom_log("Невідомий тип джерела даних від LLM1 під час завантаження: {$sourceType}", 'telegram_warning');
                        // Продовжуємо завантажувати інші джерела, якщо є
                        break;
                }
            } // Кінець циклу по potentialDataSources


            // Крок 3: Передача даних та уточненого запиту до LLM2 для генерації відповіді
            custom_log("Proceeding to LLM2. Data loading error: " . ($dataLoadingError ?? 'None'), 'telegram_webhook');

            // Якщо була помилка завантаження КРИТИЧНИХ даних (наприклад, жодного користувача не завантажено, хоча LLM1 їх запросив)
             // АБО якщо контекст порожній, хоча LLM1 запросив дані (не 'none')
             // АБО якщо refinedQuery порожній (хоча це малоймовірно з новим промптом для LLM1)
            if (($userAnswersRequested && empty($loadedUserDataSets)) ||
                 (empty($contextData) && !in_array('none', $potentialDataSources)) ||
                 empty($refinedQuery) )
            {
                if ($dataLoadingError === null) {
                     // Якщо specific error wasn't set, provide a general one
                    $dataLoadingError = "Не вдалося завантажити необхідні дані для відповіді на запит. Можливо, файли даних порожні або пошкоджені.";
                    custom_log("General data loading failure or empty context for non-'none' request.", 'telegram_error');
                }
                // Встановлюємо final responseText з помилкою
                $responseText = "Виникла проблема під час підготовки даних: " . $dataLoadingError . " " .
                                "Спробуйте переформулювати запит.";

            } else {
                // Якщо дані (або їх частина) завантажені і є уточнений запит
                // Перетворюємо зібрані дані контексту в JSON
                $contextDataJson = json_encode($contextData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                if (json_last_error() !== JSON_ERROR_NONE) {
                     custom_log("JSON encode error for contextData for LLM2: " . json_last_error_msg(), 'telegram_error');
                     $responseText = "Внутрішня помилка: не вдалося підготувати дані для ШІ аналізу.";
                } else {
                    // Викликаємо LLM2 з уточненим запитом та контекстними даними
                    custom_log("Calling getGeminiAnswer with refinedQuery='{$refinedQuery}' and context...", 'telegram_webhook');
                    $finalAnswer = getGeminiAnswer($refinedQuery, $contextDataJson);

                    if ($finalAnswer) {
                        // Якщо dataLoadingError був (наприклад, один користувач з двох недоступний),
                        // додаємо його до відповіді LLM2
                        if ($dataLoadingError !== null) {
                            $responseText = "⚠️ Частина даних недоступна: " . $dataLoadingError . "\n\n" . $finalAnswer;
                             custom_log("Added partial data error to LLM2 response.", 'telegram_warning');
                        } else {
                             $responseText = $finalAnswer;
                        }

                    } else {
                        // Якщо LLM2 не повернув відповіді
                        $responseText = "Вибачте, не вдалося отримати відповідь від ШІ аналізатора (LLM2). Можливо, питання занадто складне або сталася внутрішня помилка ШІ.";
                         custom_log("LLM2 returned empty response.", 'telegram_error');
                    }
                }
            }
        }
    } elseif (empty($text) && isset($update['message']['message_id'])) { // Якщо це не текстове повідомлення, але є $message
        $responseText = "Я отримав ваше повідомлення, але воно не містить тексту. Будь ласка, надсилайте текстові повідомлення.";
        custom_log("Received non-text message.", 'telegram_webhook');
    }

    // Крок 4: Відправка відповіді користувачу
    if (!empty($responseText)) {
        sendTelegramMessage($chatId, $responseText, $telegramToken);
    } else {
        // Якщо відповідь порожня, логуємо, але не надсилаємо нічого користувачу
        custom_log("No response generated for update (Chat ID: {$chatId}, Text: '{$text}'). Update: " . $input, 'telegram_webhook');
    }
    http_response_code(200); // Завжди повертаємо 200 OK для Telegram, навіть при помилках обробки

} else {
    // Обробка інших типів оновлень (не повідомлень)
    custom_log('Отримано не-повідомлення або непідтримуваний тип оновлення Telegram. Вміст оновлення: ' . $input, 'telegram_webhook');
    http_response_code(200); // Telegram очікує 200 OK, навіть якщо ми не обробляємо цей тип
}
?>
