<?php
/**
 * Template Name: Главная страница (CRM System)
 */

// Подключаем шапку (меню, логотип)
get_header(); 
?>

<style>
    /* Локальные стили для Hero-секции, чтобы гарантировать визуал akpp45 */
    .akpp-hero-wrapper {
        position: relative;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: radial-gradient(circle at center, #0d1424 0%, #04070f 100%);
        color: #fff;
        padding: 60px 20px;
        overflow: hidden;
    }

    /* Сетка (фон) */
    .akpp-hero-wrapper::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-image: 
            linear-gradient(rgba(0, 255, 136, 0.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(0, 255, 136, 0.03) 1px, transparent 1px);
        background-size: 50px 50px;
        z-index: 1;
    }

    .akpp-hero-container {
        position: relative;
        z-index: 2;
        max-width: 1100px;
        margin: 0 auto;
        text-align: center;
    }

    .akpp-hero-title {
        font-family: 'Inter', sans-serif;
        font-weight: 800;
        font-size: clamp(32px, 5vw, 64px);
        line-height: 1.2;
        margin-bottom: 20px;
        text-transform: uppercase;
    }

    .akpp-accent-text {
        color: #00ff88;
        text-shadow: 0 0 25px rgba(0, 255, 136, 0.4);
    }

    .akpp-hero-desc {
        font-size: clamp(16px, 2vw, 20px);
        color: #9ca3af;
        margin-bottom: 40px;
        max-width: 800px;
        margin-left: auto;
        margin-right: auto;
    }

    .akpp-features-list {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 15px;
        margin-bottom: 50px;
    }

    .akpp-feature-tag {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(0, 255, 136, 0.3);
        padding: 10px 20px;
        border-radius: 50px;
        font-size: 14px;
        color: #e5e7eb;
    }

    .akpp-actions-row {
        display: flex;
        flex-direction: column;
        gap: 20px;
        align-items: center;
    }

    /* Кнопка действия */
    .akpp-btn-main {
        background: #00ff88;
        color: #0a0f1c;
        font-family: 'Inter', sans-serif;
        font-weight: 700;
        font-size: 18px;
        text-transform: uppercase;
        padding: 20px 40px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        box-shadow: 0 0 30px rgba(0, 255, 136, 0.3);
        transition: all 0.3s ease;
        width: 100%;
        max-width: 350px;
    }

    .akpp-btn-main:hover {
        transform: translateY(-3px);
        box-shadow: 0 0 50px rgba(0, 255, 136, 0.6);
        background: #00e676;
    }

    .akpp-btn-outline {
        background: transparent;
        color: #00ff88;
        border: 2px solid #00ff88;
        font-weight: 700;
        font-size: 18px;
        padding: 18px 40px;
        border-radius: 8px;
        text-decoration: none;
        transition: all 0.3s ease;
        width: 100%;
        max-width: 350px;
    }

    .akpp-btn-outline:hover {
        background: rgba(0, 255, 136, 0.1);
        color: #fff;
    }

    @media (min-width: 768px) {
        .akpp-actions-row {
            flex-direction: row;
        }
    }
</style>

<main>
    <!-- HERO SECTION -->
    <section class="akpp-hero-wrapper">
        <div class="akpp-hero-container">
            <h1 class="akpp-hero-title">
                Профессиональный<br>
                <span class="akpp-accent-text">Ремонт АКПП в Кургане</span>
            </h1>
            
            <p class="akpp-hero-desc">
                Точная диагностика, ремонт ЭБУ, замена масла.<br>
                Современное оборудование и ПО.
            </p>

            <!-- Преимущества -->
            <div class="akpp-features-list">
                <span class="akpp-feature-tag">✓ Гарантия на работы</span>
                <span class="akpp-feature-tag">✓ Диагностика на дилерском оборудовании</span>
                <span class="akpp-feature-tag">✓ Запчасти в наличии</span>
            </div>

            <!-- КНОПКИ -->
            <div class="akpp-actions-row">
                
                <!-- КЛЮЧЕВОЙ МОМЕНТ: Класс open-auth-modal вызывает твой скрипт из папки assets/js/modal-auth.js -->
                <button type="button" class="akpp-btn-main open-auth-modal">
                    ЗАПИСАТЬСЯ НА ДИАГНОСТИКУ
                </button>

                <a href="tel:+73522123456" class="akpp-btn-outline">
                    +7 (3522) 123-45-67
                </a>

            </div>
        </div>
    </section>
</main>

<?php
// ВАЖНО: Подгружаем HTML модалок сюда, чтобы они были доступны DOM'у
get_template_part('inc/crm/templates/frontend/modal', 'login');
get_template_part('inc/crm/templates/frontend/modal', 'register');

get_footer();
?>