<?php
/**
 * Главная страница сайта
 *
 * @package AKPP45
 */

get_header();

// Подключение модальных окон CRM
get_template_part('inc/crm/templates/frontend/modal', 'login');
get_template_part('inc/crm/templates/frontend/modal', 'register');
?>

<!-- HERO СЕКЦИЯ -->
<section class="hero">
    <div class="hero-content">
        <h1>Профессиональный <span class="hero-highlight">ремонт АКПП</span> в Кургане</h1>
        <p>Диагностика, ремонт и восстановление автоматических коробок передач любой сложности</p>
        <button class="hero-btn btn-diagnostic">🔧 Записаться на диагностику</button>
        <div class="hero-features">
            <div class="feature"><span class="feature-icon">✅</span> Гарантия до 2 лет</div>
            <div class="feature"><span class="feature-icon">✅</span> Оригинальные запчасти</div>
            <div class="feature"><span class="feature-icon">✅</span> Диагностика 30 минут</div>
        </div>
    </div>
</section>

<div class="container">
    <!-- УСЛУГИ -->
    <div class="services">
        <h2 class="section-title">Наши услуги</h2>
        <div class="services-grid">
            <div class="service-card">
                <div class="service-icon">🔍</div>
                <h3>Компьютерная диагностика</h3>
                <p>Профессиональная диагностика АКПП на современном оборудовании</p>
                <div class="service-price">от 1 500 ₽</div>
                <button class="service-btn btn-diagnostic">Записаться</button>
            </div>
            <div class="service-card">
                <div class="service-icon">🔧</div>
                <h3>Ремонт АКПП</h3>
                <p>Ремонт любой сложности. Восстановление работоспособности коробки</p>
                <div class="service-price">от 30 000 ₽</div>
                <button class="service-btn btn-diagnostic">Записаться</button>
            </div>
            <div class="service-card">
                <div class="service-icon">⚙️</div>
                <h3>Замена масла</h3>
                <p>Полная или частичная замена масла с промывкой</p>
                <div class="service-price">от 3 500 ₽</div>
                <button class="service-btn btn-diagnostic">Записаться</button>
            </div>
            <div class="service-card">
                <div class="service-icon">🔄</div>
                <h3>Ремонт гидротрансформатора</h3>
                <p>Восстановление и ремонт гидротрансформатора любой сложности</p>
                <div class="service-price">от 8 000 ₽</div>
                <button class="service-btn btn-diagnostic">Записаться</button>
            </div>
        </div>
    </div>

    <!-- ПРЕИМУЩЕСТВА -->
    <div class="advantages">
        <h2 class="section-title">Почему выбирают нас</h2>
        <div class="advantages-grid">
            <div class="advantage-item">
                <div class="advantage-icon">🏆</div>
                <h3>Опыт 10+ лет</h3>
                <p>Более 500 отремонтированных АКПП</p>
            </div>
            <div class="advantage-item">
                <div class="advantage-icon">🔧</div>
                <h3>Современное оборудование</h3>
                <p>Дилерское диагностическое оборудование</p>
            </div>
            <div class="advantage-item">
                <div class="advantage-icon">📦</div>
                <h3>Запчасти в наличии</h3>
                <p>Оригинальные запчасти на складе</p>
            </div>
            <div class="advantage-item">
                <div class="advantage-icon">📋</div>
                <h3>Отчет о работе</h3>
                <p>Фото и видео отчет каждого этапа</p>
            </div>
        </div>
    </div>

    <!-- ПРАЙС-ЛИСТ -->
    <div class="pricing">
        <h2 class="section-title">Цены на услуги</h2>
        <div class="pricing-table">
            <div class="pricing-row header">
                <span>Услуга</span>
                <span>Цена</span>
            </div>
            <div class="pricing-row">
                <span>Компьютерная диагностика АКПП</span>
                <span>1 500 ₽</span>
            </div>
            <div class="pricing-row">
                <span>Частичная замена масла</span>
                <span>3 500 ₽</span>
            </div>
            <div class="pricing-row">
                <span>Полная замена масла с промывкой</span>
                <span>6 500 ₽</span>
            </div>
            <div class="pricing-row">
                <span>Ремонт гидротрансформатора</span>
                <span>от 8 000 ₽</span>
            </div>
            <div class="pricing-row">
                <span>Капитальный ремонт АКПП</span>
                <span>от 30 000 ₽</span>
            </div>
        </div>
    </div>
</div>

<style>
/* ============================================================
   ГЛОБАЛЬНЫЕ СТИЛИ
   ============================================================ */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #fff;
    color: #333;
    line-height: 1.5;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.section-title {
    text-align: center;
    font-size: 36px;
    margin-bottom: 50px;
    color: #333;
    position: relative;
}

.section-title:after {
    content: '';
    display: block;
    width: 60px;
    height: 4px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    margin: 15px auto 0;
    border-radius: 2px;
}

/* ============================================================
   HERO СЕКЦИЯ (ВАШ СТИЛЬ)
   ============================================================ */
.hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 100px 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.hero-content {
    max-width: 800px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

.hero h1 {
    font-size: 48px;
    margin-bottom: 20px;
    font-weight: 700;
}

.hero-highlight {
    position: relative;
    display: inline-block;
}

.hero p {
    font-size: 18px;
    margin-bottom: 30px;
    opacity: 0.9;
}

.hero-btn {
    background: white;
    color: #667eea;
    border: none;
    padding: 15px 40px;
    font-size: 18px;
    font-weight: 600;
    border-radius: 50px;
    cursor: pointer;
    transition: transform 0.3s, box-shadow 0.3s;
    margin-bottom: 40px;
}

.hero-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

.hero-features {
    display: flex;
    justify-content: center;
    gap: 40px;
    flex-wrap: wrap;
}

.feature {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    opacity: 0.9;
}

/* ============================================================
   КАРТОЧКИ УСЛУГ (ВАШ СТИЛЬ)
   ============================================================ */
.services {
    padding: 80px 0;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
}

.service-card {
    background: #fff;
    border-radius: 16px;
    padding: 30px;
    text-align: center;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: transform 0.3s, box-shadow 0.3s;
    border: 1px solid #f0f2f5;
}

.service-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

.service-icon {
    font-size: 48px;
    margin-bottom: 20px;
}

.service-card h3 {
    font-size: 20px;
    margin-bottom: 15px;
    color: #333;
}

.service-card p {
    color: #666;
    margin-bottom: 20px;
    line-height: 1.5;
}

.service-price {
    font-size: 24px;
    font-weight: bold;
    color: #667eea;
    margin-bottom: 20px;
}

.service-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 10px 30px;
    border-radius: 25px;
    cursor: pointer;
    transition: transform 0.2s;
}

.service-btn:hover {
    transform: translateY(-2px);
}

/* ============================================================
   ПРЕИМУЩЕСТВА (ВАШ СТИЛЬ)
   ============================================================ */
.advantages {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 80px 20px;
    border-radius: 30px;
    margin: 40px 0;
}

.advantages-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 40px;
    text-align: center;
}

.advantage-icon {
    font-size: 48px;
    margin-bottom: 20px;
}

.advantage-item h3 {
    font-size: 20px;
    margin-bottom: 10px;
    color: #333;
}

.advantage-item p {
    color: #666;
    font-size: 14px;
}

/* ============================================================
   ПРАЙС-ЛИСТ (ВАШ СТИЛЬ)
   ============================================================ */
.pricing {
    padding: 80px 0;
}

.pricing-table {
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border: 1px solid #f0f2f5;
}

.pricing-row {
    display: flex;
    justify-content: space-between;
    padding: 15px 25px;
    border-bottom: 1px solid #f0f2f5;
}

.pricing-row:last-child {
    border-bottom: none;
}

.pricing-row.header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
}

.pricing-row span:first-child {
    font-weight: 500;
}

.pricing-row span:last-child {
    color: #667eea;
    font-weight: 600;
}

/* ============================================================
   ФОРМЫ (ВАШ СТИЛЬ)
   ============================================================ */
.akpp-auth-container {
    max-width: 500px;
    margin: 50px auto;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    overflow: hidden;
}

.akpp-auth-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 35px 30px;
    text-align: center;
    color: #fff;
}

.akpp-auth-header h1 {
    font-size: 28px;
    margin-bottom: 10px;
}

.akpp-auth-form {
    padding: 30px;
}

.akpp-form-group {
    margin-bottom: 20px;
}

.akpp-form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.akpp-form-group input,
.akpp-form-group textarea,
.akpp-form-group select {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    font-size: 15px;
    transition: border-color 0.3s;
}

.akpp-form-group input:focus,
.akpp-form-group textarea:focus {
    outline: none;
    border-color: #667eea;
}

.akpp-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border: none;
    padding: 14px;
    width: 100%;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s;
}

.akpp-btn:hover {
    transform: translateY(-2px);
}

/* ============================================================
   АДАПТИВНОСТЬ
   ============================================================ */
@media (max-width: 768px) {
    .hero h1 {
        font-size: 32px;
    }
    
    .section-title {
        font-size: 28px;
    }
    
    .services-grid {
        grid-template-columns: 1fr;
    }
    
    .advantages-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 25px;
    }
    
    .hero-features {
        gap: 20px;
        flex-direction: column;
        align-items: center;
    }
}

@media (max-width: 480px) {
    .advantages-grid {
        grid-template-columns: 1fr;
    }
    
    .pricing-row {
        flex-direction: column;
        text-align: center;
        gap: 5px;
    }
}
</style>

<?php get_footer(); ?>
