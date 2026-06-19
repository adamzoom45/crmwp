<?php
/**
 * Главная страница АКПП45 - Ремонт АКПП в Кургане
 * Одностраничный сайт
 *
 * @package AKPP45
 * @version 1.0
 */

get_header();
?>

<main class="site-main">
    
    <!-- ========== HERO ========== -->
    <section class="hero" id="home">
        <div class="container">
            <div class="hero-content">
                <div class="hero-badge">⚡ Опыт 11 лет • Запчасти по себестоимости</div>
                <h1 class="hero-title">
                    Ремонт <span class="accent">АКПП Toyota и Lexus</span><br>
                    в Кургане
                </h1>
                <p class="hero-subtitle">
                    Специализированный сервис по восстановлению классических гидравлических трансмиссий. 
                    Также Hyundai, Kia, Mazda, Ford, Renault, Mitsubishi.
                </p>
                <div class="hero-actions">
                    <a href="tel:+79638669996" class="btn btn-primary">
                        📞 +7 (963) 866-99-96
                    </a>
                    <a href="https://t.me/akppkgn" target="_blank" class="btn btn-secondary">
                        💬 Telegram
                    </a>
                    <button class="btn btn-accent open-booking-modal">
                        📝 Записаться на ремонт
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== СПЕЦИАЛИЗАЦИЯ ========== -->
    <section class="section" id="specialization">
        <div class="container">
            <h2 class="section-title">Моя специализация</h2>
            <div class="cards-grid">
                
                <div class="card card-accent">
                    <div class="card-icon">👑</div>
                    <h3>Toyota / Lexus</h3>
                    <p>Главная специализация. Знаю эти агрегаты до винтика, имею опыт, оборудование и прямые каналы поставки запчастей.</p>
                    <ul class="card-list">
                        <li>Land Cruiser 80/100, Prado 90/120</li>
                        <li>Camry, RAV4, Highlander</li>
                        <li>Lexus RX, GS, IS</li>
                        <li>Mark II, Chaser, Crown</li>
                    </ul>
                </div>

                <div class="card">
                    <div class="card-icon">🚗</div>
                    <h3>Другие марки</h3>
                    <p>Ремонт классических гидроавтоматов на автомобилях других производителей.</p>
                    <ul class="card-list">
                        <li>Hyundai / Kia — от 50 000 ₽</li>
                        <li>Mazda / Ford — от 50 000 ₽</li>
                        <li>Renault / Mitsubishi — по диагностике</li>
                    </ul>
                </div>

                <div class="card card-danger">
                    <div class="card-icon">⛔</div>
                    <h3>НЕ работаю с:</h3>
                    <ul class="card-list">
                        <li>❌ Немецкие авто (VAG, BMW, Mercedes)</li>
                        <li>❌ Вариаторы (CVT) и роботы (DSG)</li>
                        <li>❌ Контрактные (б/у) АКПП</li>
                    </ul>
                    <p class="card-note">Только качественный ремонт вашего родного агрегата.</p>
                </div>

            </div>
        </div>
    </section>

    <!-- ========== ПРАЙС ========== -->
    <section class="section section-dark" id="price">
        <div class="container">
            <h2 class="section-title">Стоимость работ</h2>
            <p class="section-subtitle">Цены указаны ТОЛЬКО за работу. Запчасти — по себестоимости (отдаю строго по чеку).</p>
            
            <div class="price-list">
                
                <div class="price-item">
                    <div class="price-header" data-toggle="price-1">
                        <div>
                            <h3>Серии 03-70 / 30-40</h3>
                            <p class="price-cars">A340E, A340F, A343F, A341F • Land Cruiser 80/100, Prado 90/120, Mark II, Crown</p>
                        </div>
                        <div class="price-value">от 50 000 ₽</div>
                    </div>
                </div>

                <div class="price-item">
                    <div class="price-header" data-toggle="price-2">
                        <div>
                            <h3>Серии 35-50</h3>
                            <p class="price-cars">A350E, A540E, A541E • Camry V6, Highlander, Sienna, Lexus GS300/IS300</p>
                        </div>
                        <div class="price-value">от 50 000 ₽</div>
                    </div>
                </div>

                <div class="price-item">
                    <div class="price-header" data-toggle="price-3">
                        <div>
                            <h3>Серия 650</h3>
                            <p class="price-cars">A650E • Lexus IS 250/350, GS 300/350, Toyota Mark X, Crown 120/130</p>
                        </div>
                        <div class="price-value">от 50 000 ₽</div>
                    </div>
                </div>

                <div class="price-item">
                    <div class="price-header" data-toggle="price-4">
                        <div>
                            <h3>Все серии U</h3>
                            <p class="price-cars">U660E, U760E, U880E • Camry, RAV4, Highlander, Lexus RX 350/450h</p>
                        </div>
                        <div class="price-value">от 50 000 ₽</div>
                    </div>
                </div>

            </div>

            <div class="price-note">
                <strong>Важно:</strong> При согласии на ремонт стоимость диагностики (3 000 — 5 000 ₽) вычитается из итоговой суммы.
            </div>
        </div>
    </section>

    <!-- ========== ПРЕИМУЩЕСТВА ========== -->
    <section class="section" id="why">
        <div class="container">
            <h2 class="section-title">Почему выбирают меня</h2>
            <div class="features-grid">
                
                <div class="feature">
                    <div class="feature-icon">💰</div>
                    <h3>Запчасти по себестоимости</h3>
                    <p>Не зарабатываю на деталях. Все комплектующие отдаю строго по чеку. Моя прибыль — только работа.</p>
                </div>

                <div class="feature">
                    <div class="feature-icon">🏭</div>
                    <h3>Заводской ремонт ГДТ</h3>
                    <p>Гидротрансформатор отправляю на специализированный завод. Балансировка и сварка на промышленном стенде.</p>
                </div>

                <div class="feature">
                    <div class="feature-icon">🧼</div>
                    <h3>Автоматическая мойка</h3>
                    <p>Сборка в стерильной чистоте. Вымываю всю стружку и шлам из каналов под давлением.</p>
                </div>

                <div class="feature">
                    <div class="feature-icon">🏆</div>
                    <h3>11 лет опыта</h3>
                    <p>Тысячи успешно восстановленных агрегатов. Специализация на Toyota/Lexus.</p>
                </div>

            </div>
        </div>
    </section>

    <!-- ========== УСЛОВИЯ ========== -->
    <section class="section section-dark" id="conditions">
        <div class="container">
            <h2 class="section-title">Условия работы</h2>
            <div class="conditions-grid">
                
                <div class="condition-block">
                    <h3>🔍 Диагностика</h3>
                    <ul>
                        <li>Передний привод — <strong>3 000 ₽</strong></li>
                        <li>Задний/полный привод — <strong>5 000 ₽</strong></li>
                        <li>При ремонте — <strong>вычитается из стоимости</strong></li>
                    </ul>
                </div>

                <div class="condition-block">
                    <h3>🔧 Снятие АКПП</h3>
                    <ul>
                        <li>По умолчанию работаю с привезённым агрегатом</li>
                        <li>Снятие на месте — <strong>от 20 000 ₽</strong></li>
                    </ul>
                </div>

                <div class="condition-block">
                    <h3>📦 Хранение</h3>
                    <ul>
                        <li>Авто в тёплом боксе — <strong>10 000 ₽/сутки</strong></li>
                        <li>Авто на улице — <strong>500 ₽/сутки</strong></li>
                        <li>Разобранная АКПП — <strong>300 ₽/сутки</strong></li>
                    </ul>
                </div>

                <div class="condition-block">
                    <h3>⚙️ Доп. услуги</h3>
                    <ul>
                        <li>СВАП 1GZ-FE (A340) под LC/Prado</li>
                        <li>Промывка гидроблока — от 5 000 ₽</li>
                        <li>Замена соленоидов — от 13 000 ₽</li>
                        <li>Мойка деталей — от 1 000 ₽</li>
                    </ul>
                </div>

            </div>
        </div>
    </section>

    <!-- ========== КОНТАКТЫ ========== -->
    <section class="section" id="contacts">
        <div class="container">
            <h2 class="section-title">Контакты</h2>
            
            <div class="contacts-grid">
                
                <div class="contact-card">
                    <div class="contact-icon">📍</div>
                    <h3>Адрес</h3>
                    <p>г. Курган, ул. Бурова-Петрова, 121<br><small>(ГСК КАС №8, тёплый бокс)</small></p>
                </div>

                <div class="contact-card">
                    <div class="contact-icon">📞</div>
                    <h3>Телефон</h3>
                    <p><a href="tel:+79638669996">+7 (963) 866-99-96</a></p>
                </div>

                <div class="contact-card">
                    <div class="contact-icon">💬</div>
                    <h3>Telegram</h3>
                    <p><a href="https://t.me/akppkgn" target="_blank">@akppkgn</a></p>
                </div>

                <div class="contact-card">
                    <div class="contact-icon">✉️</div>
                    <h3>Email</h3>
                    <p><span class="email-protected" data-user="adamzoom" data-domain="bk.ru">Написать</span></p>
                </div>

            </div>

            <div class="contacts-cta">
                <button class="btn btn-accent btn-large open-booking-modal">
                    📝 Записаться на ремонт
                </button>
                <p class="contacts-note">Отвечаю в течение часа. Укажите марку, модель, год, пробег и симптомы.</p>
            </div>

        </div>
    </section>

</main>

<!-- ========== МОДАЛКА: ЗАПИСЬ НА РЕМОНТ ========== -->
<div class="modal booking-modal" id="booking-modal">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <button class="modal-close">&times;</button>
        <div class="modal-header">
            <h2>📝 Запись на ремонт</h2>
            <p>Заполните форму — свяжусь в течение часа</p>
        </div>
        
        <form class="booking-form" id="booking-form">
            <?php wp_nonce_field('akpp_booking_nonce', 'booking_nonce'); ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label>ФИО <span class="required">*</span></label>
                    <input type="text" name="full_name" required placeholder="Иванов Иван Иванович">
                </div>
            </div>

            <div class="form-row two-cols">
                <div class="form-group">
                    <label>Телефон <span class="required">*</span></label>
                    <input type="tel" name="phone" required placeholder="+7 (___) ___-__-__">
                </div>
                <div class="form-group">
                    <label>Город</label>
                    <input type="text" name="city" placeholder="Курган">
                </div>
            </div>

            <div class="form-row two-cols">
                <div class="form-group">
                    <label>Марка и модель авто <span class="required">*</span></label>
                    <input type="text" name="car_info" required placeholder="Toyota Camry">
                </div>
                <div class="form-group">
                    <label>Год выпуска <span class="required">*</span></label>
                    <input type="number" name="car_year" required min="1980" max="2026" placeholder="2010">
                </div>
            </div>

            <div class="form-group">
                <label>Опишите проблему <span class="required">*</span></label>
                <textarea name="problem" required rows="4" placeholder="Пинается при переключении, горит ошибка, не едет задняя..."></textarea>
            </div>

            <button type="submit" class="btn btn-accent btn-block">
                Отправить заявку
            </button>
            
            <p class="form-note">Нажимая кнопку, вы соглашаетесь с обработкой персональных данных</p>
        </form>
    </div>
</div>

<!-- ========== МОДАЛКА: АВТОРИЗАЦИЯ ========== -->
<?php if (!is_user_logged_in()) : ?>
<div class="modal auth-modal" id="auth-modal">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <button class="modal-close">&times;</button>
        
        <div class="auth-tabs">
            <button class="auth-tab active" data-tab="login">Вход</button>
            <button class="auth-tab" data-tab="register">Регистрация</button>
        </div>

        <!-- Форма входа -->
        <form class="auth-form" id="login-form" data-type="login">
            <?php wp_nonce_field('akpp_client_login_nonce', 'nonce'); ?>
            
            <div class="form-group">
                <label>Телефон</label>
                <input type="tel" name="phone" required placeholder="+7 (___) ___-__-__">
            </div>
            
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-accent btn-block">Войти</button>
        </form>

        <!-- Форма регистрации -->
        <form class="auth-form hidden" id="register-form" data-type="register">
            <?php wp_nonce_field('akpp_client_register_nonce', 'nonce'); ?>
            
            <div class="form-group">
                <label>Тип аккаунта</label>
                <div class="role-selector">
                    <label class="role-option">
                        <input type="radio" name="role" value="repair" checked>
                        <span>🔧 Запись на ремонт</span>
                    </label>
                    <label class="role-option">
                        <input type="radio" name="role" value="buyer">
                        <span>🛒 Покупатель запчастей</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label>ФИО <span class="required">*</span></label>
                <input type="text" name="full_name" required>
            </div>

            <div class="form-group">
                <label>Телефон <span class="required">*</span></label>
                <input type="tel" name="phone" required placeholder="+7 (___) ___-__-__">
            </div>

            <!-- Поля для ремонта -->
            <div class="repair-fields">
                <div class="form-row two-cols">
                    <div class="form-group">
                        <label>Авто <span class="required">*</span></label>
                        <input type="text" name="car_info" placeholder="Toyota Camry">
                    </div>
                    <div class="form-group">
                        <label>Год <span class="required">*</span></label>
                        <input type="number" name="car_year" min="1980" max="2026">
                    </div>
                </div>
                <div class="form-group">
                    <label>Проблема <span class="required">*</span></label>
                    <textarea name="problem" rows="3"></textarea>
                </div>
            </div>

            <!-- Поля для покупателя -->
            <div class="buyer-fields hidden">
                <div class="form-group">
                    <label>Город <span class="required">*</span></label>
                    <input type="text" name="city">
                </div>
            </div>

            <div class="form-group">
                <label>Пароль <span class="required">*</span></label>
                <input type="password" name="password" required minlength="6">
            </div>

            <button type="submit" class="btn btn-accent btn-block">Зарегистрироваться</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php get_footer(); ?>