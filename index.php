<?php
// index.php
session_start(); // Start the session

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$pageTitle = "MindFlow - Розвиток самопізнання";
include __DIR__ . '/includes/header.php';
?>

<div class="landing-page">
    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Пізнай себе через очі інших</h1>
            <p class="hero-subtitle">MindFlow допомагає краще розуміти себе та своє сприйняття оточуючими</p>
            <div class="cta-buttons">
                <a href="register.php" class="btn btn-primary btn-lg">Розпочати безкоштовно</a>
                <a href="login.php" class="btn btn-outline btn-lg">Увійти</a>
            </div>
        </div>
        <div class="hero-image">
            <div class="hero-shape"></div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="features">
        <h2 class="section-title">Як це працює</h2>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <h3>Самооцінка</h3>
                <p>Пройдіть наше опитування і оцініть себе за різними параметрами</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Відгуки інших</h3>
                <p>Поділіться посиланням з друзями, колегами та отримайте їх оцінку</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Аналіз результатів</h3>
                <p>Порівняйте своє сприйняття з тим, як вас бачать інші</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-brain"></i>
                </div>
                <h3>Особистісний ріст</h3>
                <p>Розвивайтеся на основі отриманого зворотного зв'язку</p>
            </div>
        </div>
    </section>
    
    <!-- Benefits Section -->
    <section class="benefits">
        <div class="benefits-content">
            <h2 class="section-title">Переваги MindFlow</h2>
            <ul class="benefits-list">
                <li><i class="fas fa-check-circle"></i> Безкоштовний сервіс</li>
                <li><i class="fas fa-check-circle"></i> Анонімні відгуки від інших</li>
                <li><i class="fas fa-check-circle"></i> Інтуїтивний та зручний інтерфейс</li>
                <li><i class="fas fa-check-circle"></i> Наочні діаграми та графіки результатів</li>
                <li><i class="fas fa-check-circle"></i> Можливість відстежувати динаміку своїх якостей</li>
            </ul>
            <a href="register.php" class="btn btn-primary">Почати користуватися</a>
        </div>
        <div class="benefits-image">
            <div class="benefits-shape"></div>
        </div>
    </section>
    
    <!-- Testimonials Section -->
    <section class="testimonials">
        <h2 class="section-title">Що кажуть наші користувачі</h2>
        <div class="testimonial-carousel">
            <div class="testimonial-card">
                <div class="quote"><i class="fas fa-quote-left"></i></div>
                <p class="testimonial-text">Цей проєкт лежав в мене на чердаку досить довго, але прийшов час струсити з нього пил.</p>
                <div class="testimonial-author">Олександр, менеджер проєктів</div>
            </div>
            <div class="testimonial-card">
                <div class="quote"><i class="fas fa-quote-left"></i></div>
                <p class="testimonial-text">Боже, ти всеж таки вирішив це зробити?</p>
                <div class="testimonial-author">Аліса, Психолог</div>
            </div>
            <div class="testimonial-card">
                <div class="quote"><i class="fas fa-quote-left"></i></div>
                <p class="testimonial-text">Када вже?</p>
                <div class="testimonial-author">Анастасія, студент</div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="cta">
        <h2>Готові почати шлях до кращого розуміння себе?</h2>
        <p>Приєднуйтесь до тисяч користувачів, які вже використовують MindFlow для особистісного зростання</p>
        <a href="register.php" class="btn btn-primary btn-lg">Зареєструватися безкоштовно</a>
        <div class="cta-note">Реєстрація займає менше хвилини і не потребує банківської картки</div>
    </section>
</div>

<!-- CSS for landing page -->
<style>
/* Base styling for landing page */
.landing-page {
    color: #333;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.6;
}

.section-title {
    text-align: center;
    margin-bottom: 40px;
    color: #333;
    font-size: 2.2rem;
}

/* Hero Section */
.hero {
    display: flex;
    align-items: center;
    min-height: 500px;
    background: linear-gradient(135deg, #f5f7fa 0%, #e9e9e9 100%);
    padding: 60px 5%;
    position: relative;
    overflow: hidden;
}

.hero-content {
    flex: 1;
    max-width: 600px;
    z-index: 2;
}

.hero h1 {
    font-size: 2.8rem;
    margin-bottom: 20px;
    color: #333;
    line-height: 1.2;
}

.hero-subtitle {
    font-size: 1.2rem;
    margin-bottom: 30px;
    color: #555;
}

.cta-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.btn-lg {
    padding: 12px 28px;
    font-size: 1rem;
    font-weight: 600;
}

.btn-outline {
    background: transparent;
    border: 2px solid #5C67F2;
    color: #5C67F2;
}

.btn-outline:hover {
    background-color: rgba(92, 103, 242, 0.1);
}

.hero-image {
    flex: 1;
    position: relative;
    height: 100%;
}

.hero-shape {
    position: absolute;
    top: -100px;
    right: -100px;
    width: 600px;
    height: 600px;
    background: linear-gradient(135deg, #5C67F2 0%, #6f7ef2 100%);
    border-radius: 70% 30% 50% 50% / 50% 60% 40% 50%;
    z-index: 1;
    animation: morph 15s linear infinite alternate;
}

@keyframes morph {
    0% {
        border-radius: 70% 30% 50% 50% / 50% 60% 40% 50%;
    }
    25% {
        border-radius: 40% 60% 60% 40% / 60% 30% 70% 40%;
    }
    50% {
        border-radius: 50% 50% 30% 70% / 40% 40% 60% 60%;
    }
    75% {
        border-radius: 60% 40% 40% 60% / 30% 60% 40% 70%;
    }
    100% {
        border-radius: 30% 70% 70% 30% / 50% 50% 50% 50%;
    }
}

/* Features Section */
.features {
    padding: 80px 5%;
    background-color: #fff;
}

.feature-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
}

.feature-card {
    background-color: #f9f9f9;
    border-radius: 8px;
    padding: 30px;
    text-align: center;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s, box-shadow 0.3s;
}

.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
}

.feature-icon {
    width: 70px;
    height: 70px;
    background: #5C67F2;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    color: white;
    font-size: 28px;
}

.feature-card h3 {
    margin-bottom: 15px;
    color: #333;
    font-size: 1.3rem;
}

/* Benefits Section */
.benefits {
    display: flex;
    align-items: center;
    padding: 80px 5%;
    background: linear-gradient(135deg, #f9f9f9 0%, #f1f1f1 100%);
}

.benefits-content {
    flex: 1;
    max-width: 600px;
}

.benefits-list {
    list-style: none;
    padding: 0;
    margin-bottom: 30px;
}

.benefits-list li {
    padding: 10px 0;
    display: flex;
    align-items: center;
    font-size: 1.1rem;
}

.benefits-list li i {
    color: #5C67F2;
    margin-right: 10px;
    font-size: 1.2rem;
}

.benefits-image {
    flex: 1;
    position: relative;
    height: 400px;
}

.benefits-shape {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, rgba(92, 103, 242, 0.2) 0%, rgba(111, 126, 242, 0.2) 100%);
    border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
    z-index: 1;
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0% {
        transform: translate(-50%, -50%);
    }
    50% {
        transform: translate(-50%, -60%);
    }
    100% {
        transform: translate(-50%, -50%);
    }
}

/* Testimonials Section */
.testimonials {
    padding: 80px 5%;
    background-color: #fff;
}

.testimonial-carousel {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
}

.testimonial-card {
    background-color: #f9f9f9;
    border-radius: 8px;
    padding: 30px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    width: 100%;
    max-width: 350px;
    position: relative;
}

.quote {
    color: #5C67F2;
    font-size: 2rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

.testimonial-text {
    font-style: italic;
    margin-bottom: 20px;
    color: #555;
}

.testimonial-author {
    font-weight: 600;
    color: #333;
}

/* CTA Section */
.cta {
    text-align: center;
    padding: 80px 5%;
    background: linear-gradient(135deg, #5C67F2 0%, #6F7EF2 100%);
    color: white;
}

.cta h2 {
    font-size: 2.2rem;
    margin-bottom: 20px;
}

.cta p {
    max-width: 800px;
    margin: 0 auto 30px;
    font-size: 1.2rem;
    opacity: 0.9;
}

.cta .btn-primary {
    background-color: white;
    color: #5C67F2;
    border: none;
}

.cta .btn-primary:hover {
    background-color: rgba(255, 255, 255, 0.9);
}

.cta-note {
    margin-top: 15px;
    font-size: 0.9rem;
    opacity: 0.8;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .hero, .benefits {
        flex-direction: column;
        gap: 40px;
    }
    
    .hero-content, .benefits-content {
        max-width: 100%;
    }
    
    .hero h1 {
        font-size: 2.3rem;
    }
    
    .section-title {
        font-size: 1.8rem;
    }
}

@media (max-width: 768px) {
    .feature-grid {
        grid-template-columns: 1fr;
    }
    
    .testimonial-carousel {
        flex-direction: column;
        align-items: center;
    }
    
    .cta h2 {
        font-size: 1.8rem;
    }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>