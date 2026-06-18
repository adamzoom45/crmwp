<?php
/**
 * Template Name: AKPP Landing Page
 * Главный шаблон лендинга АКПП Курган
 *
 * @package AKPP45
 * @version 5.0
 */

get_header();
?>

<!-- ================================================================== -->
<!-- HERO SECTION (Главный экран) -->
<!-- ================================================================== -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">
                Профессиональный<br>
                <span class="highlight">Ремонт АКПП в Кургане</span>
            </h1>
            <p class="hero-description">
                Точная диагностика, ремонт ЭБУ, замена масла. 
                Современное оборудование и ПО.
            </p>
            
            <div class="hero-badges">
                <span class="badge">✓ Гарантия на работы</span>
                <span class="badge">✓ Диагностика на дилерском оборудовании</span>
                <span class="badge">✓ Запчасти в наличии</span>
            </div>
            
            <div class="hero-actions">
                <a href="#contact" class="btn btn-primary btn-lg btn-glow">ЗАПИСАТЬСЯ НА ДИАГНОСТИКУ</a>
                <a href="tel:+73522123456" class="btn btn-outline">+7 (3522) 123-45-67</a>
            </div>
        </div>
    </div>
</section>

<!-- ================================================================== -->
<!-- SERVICES (Услуги) -->
<!-- ================================================================== -->
<section class="services-section" id="services">
    <div class="container">
        <h2 class="section-title">Наши Услуги</h2>
        
        <div class="services-grid">
            <!-- Service 1 -->
            <div class="service-card">
                <div class="service-icon">
                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#00ff88" stroke-width="1.5">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M12 1v6m0 6v6m4.22-10.22l4.24-4.24M6.34 17.66l-4.24 4.24M23 12h-6m-6 0H1m20.24 4.24l-4.24-4.24M6.34 6.34L2.1 2.1"></path>
                    </svg>
                </div>
                <h3>Точная Диагностика</h3>
                <p>Современного агрегата и ПО</p>
            </div>
            
            <!-- Service 2 -->
            <div class="service-card">
                <div class="service-icon">
                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#00ff88" stroke-width="1.5">
                        <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                        <rect x="9" y="9" width="6" height="6"></rect>
                        <line x1="9" y1="1" x2="9" y2="4"></line>
                        <line x1="15" y1="1" x2="15" y2="4"></line>
                        <line x1="9" y1="20" x2="9" y2="23"></line>
                        <line x1="15" y1="20" x2="15" y2="23"></line>
                        <line x1="20" y1="9" x2="23" y2="9"></line>
                        <line x1="20" y1="14" x2="23" y2="14"></line>
                        <line x1="1" y1="9" x2="4" y2="9"></line>
                        <line x1="1" y1="14" x2="4" y2="14"></line>
                    </svg>
                </div>
                <h3>Ремонт ЭБУ</h3>
                <p>Калибровка и перепрошивка блоков управления</p>
            </div>
            
            <!-- Service 3 -->
            <div class="service-card">
                <div class="service-icon">
                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#00ff88" stroke-width="1.5">
                        <path d="M19 13l-7 7-7-7m14-8l-7 7-7-7"></path>
                        <path d="M12 3v17"></path>
                    </svg>
                </div>
                <h3>Замена Масла</h3>
                <p>Оригинальные жидкости и строгий регламент</p>
            </div>
        </div>
    </div>
</section>

<!-- ================================================================== -->
<!-- PRICING (Цены) -->
<!-- ================================================================== -->
<section class="pricing-section" id="pricing">
    <div class="container">
        <h2 class="section-title">Стоимость Услуг</h2>
        
        <div class="pricing-table">
            <div class="pricing-header">
                <div class="col-service">Наименование сервиса</div>
                <div class="col-price">Standard</div>
                <div class="col-price premium">Premium</div>
                <div class="col-price vip">VIP</div>
            </div>
            
            <div class="pricing-row">
                <div class="col-service">Диагностика + замена масла</div>
                <div class="col-price">12 500 ₽</div>
                <div class="col-price premium">18 900 ₽</div>
                <div class="col-price vip">24 800 ₽</div>
            </div>
            
            <div class="pricing-row">
                <div class="col-service">Диагностика + ремонт гидроблока</div>
                <div class="col-price">—</div>
                <div class="col-price premium">✓ Включено</div>
                <div class="col-price vip">✓ Включено</div>
            </div>
            
            <div class="pricing-row">
                <div class="col-service">Полный комплекс: диагностика, ремонт, адаптация, тест-драйв</div>
                <div class="col-price">—</div>
                <div class="col-price premium">—</div>
                <div class="col-price vip">✓ Включено</div>
            </div>
        </div>
    </div>
</section>

<!-- ================================================================== -->
<!-- WORKFLOW (Процесс работы) -->
<!-- ================================================================== -->
<section class="workflow-section">
    <div class="container">
        <h2 class="section-title">Process Workflow</h2>
        
        <div class="workflow-timeline">
            <div class="workflow-step">
                <div class="step-icon">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#00ff88" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                </div>
                <div class="step-label">Заявка</div>
            </div>
            
            <div class="workflow-line"></div>
            
            <div class="workflow-step">
                <div class="step-icon">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#00ff88" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </div>
                <div class="step-label">Диагностика</div>
            </div>
            
            <div class="workflow-line"></div>
            
            <div class="workflow-step">
                <div class="step-icon">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#00ff88" stroke-width="2">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                    </svg>
                </div>
                <div class="step-label">Ремонт</div>
            </div>
            
            <div class="workflow-line"></div>
            
            <div class="workflow-step">
                <div class="step-icon">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#00ff88" stroke-width="2">
                        <path d="M9 11l3 3L22 4"></path>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                    </svg>
                </div>
                <div class="step-label">Тестирования</div>
            </div>
            
            <div class="workflow-line"></div>
            
            <div class="workflow-step">
                <div class="step-icon">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#00ff88" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <div class="step-label">Сдача авто</div>
            </div>
        </div>
    </div>
</section>

<!-- ================================================================== -->
<!-- CONTACTS (Контакты) -->
<!-- ================================================================== -->
<section class="contacts-section" id="contact">
    <div class="container">
        <h2 class="section-title">Контакты</h2>
        
        <div class="contacts-grid">
            <div class="contact-card">
                <div class="contact-icon">
                    <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="#00ff88" stroke-width="1.5">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                </div>
                <div class="contact-info">
                    <h4>Адрес</h4>
                    <p>г. Курган</p>
                </div>
            </div>
            
            <div class="contact-card">
                <div class="contact-icon">
                    <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="#00ff88" stroke-width="1.5">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                </div>
                <div class="contact-info">
                    <h4>Телефон</h4>
                    <p><a href="tel:+73522123456">+7 (3522) 123-45-67</a></p>
                </div>
            </div>
            
            <div class="contact-card">
                <div class="contact-icon">
                    <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="#00ff88" stroke-width="1.5">
                        <path d="M21.198 2.433a2.242 2.242 0 0 0-1.022.215l-16.5 7.5a2.25 2.25 0 0 0 .126 4.073l3.9 1.205 1.765 5.6a1.5 1.5 0 0 0 2.55.502l2.422-2.879 4.283 3.166a2.25 2.25 0 0 0 3.502-1.272l3.25-15.5a2.25 2.25 0 0 0-2.276-2.61z"></path>
                    </svg>
                </div>
                <div class="contact-info">
                    <h4>Telegram</h4>
                    <p><a href="https://t.me/akpp-kurgan" target="_blank">@akpp-kurgan</a></p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>