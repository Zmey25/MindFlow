<?php
// Оголошуємо змінні, які використовуються в тексті, якщо вони ще не глобальні
global $minScaleOverall, $maxScaleOverall, $normZoneMin, $normZoneMax;
$avgScaleValue = ($minScaleOverall + $maxScaleOverall) / 2;
?>

<div class="heartstyles-detailed-interpretation">
    <h2>Як детально трактувати результати Heartstyles</h2>

    <p>Індикатор Heartstyles розроблений як інструмент для особистісного розвитку, а не для остаточної оцінки вашої особистості. Він показує, як ваші моделі мислення та поведінки сприймаються вами та іншими людьми на даний момент.</p>

    <h3>Ключові принципи інтерпретації:</h3>
    <ul>
        <li><strong>Немає "хороших" чи "поганих" результатів:</strong> Кожен показник несе інформацію. Важливо розуміти, які наслідки має та чи інша вираженість поведінки для вас та вашого оточення.</li>
        <li><strong>Фокус на розвитку:</strong> Головна мета - визначити сфери для зростання та корекції для підвищення особистої та професійної ефективності.</li>
        <li><strong>Контекст важливий:</strong> Поведінка може змінюватися залежно від ситуації. Аналізуйте результати в контексті "Ситуація + Мислення = Поведінка".</li>
        <li><strong>Самооцінка vs. Оцінка Інших:</strong> Розбіжності між тим, як ви бачите себе, і як вас бачать інші, є цінним джерелом для саморефлексії.</li>
    </ul>

    <h3>Аналіз Квадрантів та Поведінки:</h3>
    <p>Діаграми показують 16 моделей поведінки, згрупованих у чотири квадранти. Показники на діаграмах відображають ступінь вираженості кожної поведінки на шкалі від <?php echo $minScaleOverall; ?> до <?php echo $maxScaleOverall; ?>.</p>

    <h4>Ефективні Квадранти (показники вгору):</h4>
    <p>Це поведінки, які зазвичай сприяють успіху та здоровим стосункам.</p>
    <ul>
        <li><strong>Голубий квадрат (Цілеспрямованість - Фокус на собі):</strong>
            <ul>
                <li><em>Автентичний, Самовдосконалюючийся, Надійний, Досягаючий.</em></li>
                <li><strong>Високі показники (ближче до <?php echo $maxScaleOverall; ?>):</strong> Зазвичай позитивно. Вказують на розвиненість цих якостей.</li>
                <li><strong>Низькі показники (ближче до <?php echo $minScaleOverall; ?> або нижче <?php echo $normZoneMin; ?>):</strong> Вказують на потенційну зону для розвитку. Наприклад, низька "Надійність" може означати, що людям важко на вас покластися.</li>
            </ul>
        </li>
        <li><strong>Червоний квадрат (Любов - Фокус на інших):</strong>
            <ul>
                <li><em>Будуючий стосунки, Надихаючий, Розвиваючий, Розуміючий інших.</em></li>
                <li><strong>Високі показники:</strong> Зазвичай позитивно. Вказують на сильну орієнтацію на людей, вміння будувати зв'язки та допомагати іншим.</li>
                <li><strong>Низькі показники:</strong> Можуть свідчити про необхідність розвивати навички міжособистісної взаємодії. Наприклад, низький показник "Розуміючий інших" може ускладнювати емпатію.</li>
            </ul>
        </li>
    </ul>

    <h4>Неефективні Квадранти (показники вниз, але значення на шкалі від 1 до 7):</h4>
    <p>Це поведінки, які часто є захисними реакціями і можуть обмежувати ваш потенціал та псувати стосунки. Тут <strong>бажані нижчі показники</strong> на шкалі.</p>
    <ul>
        <li><strong>Зелений квадрат (Гординя - Фокус на собі):</strong>
            <ul>
                <li><em>Пихатий, Конкуруючий, Контролюючий, Перфекціоніст.</em></li>
                <li><strong>Високі показники (ближче до <?php echo $maxScaleOverall; ?> або вище <?php echo $normZoneMax; ?>):</strong> Сигнал про те, що ця поведінка може бути надмірною і заважати. Наприклад, високий "Перфекціонізм" може призводити до стресу та нездатності делегувати.</li>
                <li><strong>Низькі показники (ближче до <?php echo $minScaleOverall; ?>):</strong> Зазвичай позитивно. Вказують на меншу схильність до цих неефективних проявів.</li>
            </ul>
        </li>
        <li><strong>Оранжевий квадрат (Страх - Фокус на інших):</strong>
            <ul>
                <li><em>Шукаючий схвалення, Образливий, Залежний, Уникаючий.</em></li>
                <li><strong>Високі показники:</strong> Вказують на домінування страхів та захисних механізмів. Наприклад, високий показник "Уникаючий" може свідчити про труднощі з прийняттям відповідальності або вирішенням конфліктів.</li>
                <li><strong>Низькі показники:</strong> Зазвичай позитивно. Означають більшу впевненість та меншу залежність від зовнішніх факторів.</li>
            </ul>
        </li>
    </ul>

    <h3>Сіра Зона (Заштрихована область <?php echo $normZoneMin; ?>-<?php echo $normZoneMax; ?>):</h3>
    <p>На діаграмах ця зона позначена світло-сірим фоном. Вона представляє умовний "сірий" діапазон вираженості поведінки для вашої шкали від <?php echo $minScaleOverall; ?> до <?php echo $maxScaleOverall; ?>.</p>
    <ul>
        <li><strong>Для ефективних поводжень:</strong> Якщо стовпчик знаходиться вище зони, це добре. Якщо сіра - це зона росту.</li>
        <li><strong>Для неефективних поводжень:</strong> Якщо стовпчик НЕ знаходиться в цій зоні та має меньше значення, це добре. Якщо в цій зоні - це сигнал для корекції.</li>
    </ul>
     <p><em>Важливо пам'ятати, що це умовна "норма" для даного інструменту та вашої шкали. Головне - це динаміка, самоусвідомлення та бажання розвиватися.</em></p>

    <h3>Що далі?</h3>
    <ol>
        <li><strong>Визначте 1-2 ефективні поведінки,</strong> які ви хотіли б посилити (де показники нижчі, ніж хотілося б).</li>
        <li><strong>Визначте 1-2 неефективні поведінки,</strong> прояви яких ви хотіли б зменшити (де показники вищі, ніж хотілося б).</li>
        <li><strong>Складіть план дій:</strong> Які конкретні кроки ви можете зробити, щоб змінити своє мислення та поведінку в цих напрямках?</li>
        <li><strong>Обговоріть результати:</strong> Поділіться своїми думками з довіреною особою (другом, колегою, коучем) для отримання додаткової перспективи.</li>
        <li><strong>Повторіть оцінку:</strong> Через деякий час (наприклад, 3-6 місяців) пройдіть оцінку знову, щоб побачити прогрес.</li>
    </ol>
    <p>Пам'ятайте, особистісний розвиток - це шлях, а не пункт призначення. Успіхів вам на цьому шляху!</p>
</div>
<style>
    .heartstyles-explanation {
        background-color: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .heartstyles-explanation h2, .heartstyles-explanation h3 {
        color: #333;
        margin-top: 0;
    }
    .heartstyles-explanation p, .heartstyles-explanation ul {
        font-size: 0.95em;
        line-height: 1.6;
        color: #555;
    }
    .heartstyles-explanation ul {
        padding-left: 20px;
    }
    .quadrants-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }
    .quadrant {
        padding: 15px;
        border-radius: 6px;
        color: white;
        font-size: 0.9em;
    }
    .quadrant strong {
        display: block;
        margin-bottom: 5px;
        font-size: 1.1em;
    }
    .quadrant.purpose { background-color: #4A90E2; } /* Blue */
    .quadrant.love { background-color: #D0021B; }    /* Red */
    .quadrant.pride { background-color: #7ED321; }   /* Green */
    .quadrant.fear { background-color: #F5A623; }    /* Orange */

    .category-card { /* Copied from results.css for consistency if not already included */
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        padding: 20px;
        margin-bottom: 20px;
        /* min-height: 350px; /* Ensure cards have some height for charts */
    }
    .category-title {
        font-size: 1.4em;
        color: #333;
        margin-top: 0;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 3px solid; /* Color will be set by style attribute */
    }
    .chart-container {
        position: relative;
        height: 350px; /* Or adjust as needed */
        width: 100%;
    }
    .results-layout { /* For side-by-side charts */
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .heartstyles-behaviors-details {
        margin-top: 30px;
        padding: 20px;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .heartstyles-behaviors-details h2 {
        text-align: center;
        margin-bottom: 25px;
    }
    .hs-quadrant-section {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px dashed #eee;
    }
    .hs-quadrant-section:last-child {
        border-bottom: none;
    }
    .hs-quadrant-section h4 {
        font-size: 1.1em;
        color: #333;
        margin-bottom: 10px;
    }
     .hs-quadrant-section p {
        margin-bottom: 8px;
        font-size: 0.9em;
        color: #555;
     }
    .hs-quadrant-section p strong {
        color: #444;
    }
</style>