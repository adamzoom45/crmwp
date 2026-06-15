<?php
/**
 * Главная страница сайта
 * 
 * @package AKPP45
 */

get_header();

// Подключаем модальные окна
get_template_part('inc/crm/templates/frontend/modal', 'login');
get_template_part('inc/crm/templates/frontend/modal', 'register');

// Подключаем hero секцию
get_template_part('template-parts/hero', 'section');

// Остальные секции страницы...
?>

<div class="container">
    <div class="services">
        <h2 class="services-title">Наши услуги</h2>
        <div class="services-grid">
            <div class="service-card">
                <div class="service-icon">🔍</div>
                <h3>Компьютерная диагностика</h3>
                <p>Профессиональная диагностика АКПП на современном оборудовании</p>
                <div class="service-price">от 1 500 ₽</div>
                <button class="service-btn book-diagnostic">Записаться</button>
            </div>
            
            <div class="service-card">
                <div class="service-icon">🔧</div>
                <h3>Ремонт АКПП</h3>
                <p>Ремонт любой сложности. Восстановление работоспособности коробки</p>
                <div class="service-price">от 30 000 ₽</div>
                <button class="service-btn book-diagnostic">Записаться</button>
            </div>
            
            <div class="service-card">
                <div class="service-icon">⚙️</div>
                <h3>Замена масла</h3>
                <p>Полная или частичная замена масла с промывкой</p>
                <div class="service-price">от 3 500 ₽</div>
                <button class="service-btn book-diagnostic">Записаться</button>
            </div>
            
            <div class="service-card">
                <div class="service-icon">🔄</div>
                <h3>Ремонт гидротрансформатора</h3>
                <p>Восстановление и ремонт гидротрансформатора любой сложности</p>
                <div class="service-price">от 8 000 ₽</div>
                <button class="service-btn book-diagnostic">Записаться</button>
            </div>
        </div>
    </div>
</div>

<style>
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.services {
    padding: 60px 0;
}

.services-title {
    text-align: center;
    font-size: 36px;
    margin-bottom: 40px;
    color: #333;
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

@media (max-width: 768px) {
    .services-title {
        font-size: 28px;
    }
}
</style>

<?php get_footer(); ?>
