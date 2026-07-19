<?php
/**
 * Header template
 *
 * @package AKPP45
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header" id="masthead">
    <div class="header-container">
        
        <!-- Логотип -->
        <div class="header-logo">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="logo-link">
                <span class="logo-text">АКПП<span class="accent">45</span></span>
                <span class="logo-subtitle">Курган</span>
            </a>
        </div>

        <!-- Навигация (десктоп) -->
        <nav class="header-nav desktop-nav">
            <?php
            wp_nav_menu([
                'theme_location' => 'primary',
                'menu_class'     => 'primary-menu',
                'container'      => false,
                'fallback_cb'    => function() {
                    echo '<ul class="primary-menu">';
                    echo '<li><a href="#home">Главная</a></li>';
                    echo '<li><a href="#specialization">Специализация</a></li>';
                    echo '<li><a href="#price">Цены</a></li>';
                    echo '<li><a href="#why">Почему я</a></li>';
                    echo '<li><a href="#conditions">Условия</a></li>';
                    echo '<li><a href="#contacts">Контакты</a></li>';
                    echo '</ul>';
                },
            ]);
            ?>
        </nav>

        <!-- Кнопки действий -->
        <div class="header-actions">
            <a href="tel:+79638669996" class="btn-phone">
                <span class="phone-icon">📞</span>
                <span class="phone-text desktop-only">+7 (963) 866-99-96</span>
            </a>
            
            <a href="https://t.me/@akppkgn" target="_blank" class="btn-telegram" rel="noopener">
                <span class="telegram-icon">💬</span>
                <span class="telegram-text desktop-only">Telegram</span>
            </a>
            
            <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo esc_url(home_url('/lk/')); ?>" class="btn-profile">
                    <span class="profile-icon">👤</span>
                    <span class="profile-text desktop-only">Кабинет</span>
                </a>
                <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="btn-logout mobile-hidden">
                    <span>Выход</span>
                </a>
            <?php else : ?>
                <button class="btn-auth open-auth-modal" type="button">
                    <span class="auth-icon">🔐</span>
                    <span class="auth-text desktop-only">Войти</span>
                </button>
            <?php endif; ?>

            <!-- Кнопка записи (акцентная) -->
            <button class="btn-booking open-booking-modal" type="button">
                <span class="booking-icon">📝</span>
                <span class="booking-text">Записаться</span>
            </button>

            <!-- Мобильное меню -->
            <button class="mobile-menu-toggle" type="button" aria-label="Открыть меню" aria-expanded="false">
                <span class="hamburger">
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                </span>
            </button>
        </div>
    </div>

    <!-- Мобильное меню (выпадающее) -->
    <div class="mobile-menu" id="mobileMenu">
        <nav class="mobile-nav">
            <?php
            wp_nav_menu([
                'theme_location' => 'mobile',
                'menu_class'     => 'mobile-menu-list',
                'container'      => false,
                'fallback_cb'    => function() {
                    echo '<ul class="mobile-menu-list">';
                    echo '<li><a href="#home">Главная</a></li>';
                    echo '<li><a href="#specialization">Специализация</a></li>';
                    echo '<li><a href="#price">Цены</a></li>';
                    echo '<li><a href="#why">Почему я</a></li>';
                    echo '<li><a href="#conditions">Условия</a></li>';
                    echo '<li><a href="#contacts">Контакты</a></li>';
                    echo '</ul>';
                },
            ]);
            ?>
        </nav>
        
        <!-- Контакты в мобильном меню -->
        <div class="mobile-contacts">
            <a href="tel:+79638669996" class="mobile-contact-link">
                <span>📞</span> +7 (963) 866-99-96
            </a>
            <a href="https://t.me/akppkgn" target="_blank" class="mobile-contact-link">
                <span>💬</span> @akppkgn
            </a>
        </div>
    </div>
</header>

<main class="site-main" id="main">