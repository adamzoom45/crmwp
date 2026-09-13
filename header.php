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
        <?php /* akpp-menu-anchor-fix: якоря меню -> абсолютные URL вне главной, чтобы навигация работала из /lk/ и др. страниц */ if(!function_exists('is_front_page')||!is_front_page()){ add_filter('wp_nav_menu', function($h){ return preg_replace('/href="#([^"]+)"/', 'href="'.home_url('/#').'$1"', $h); }); } ?>
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
                    echo '<li class="nav-shop-item' . (is_page(['shop','cart','checkout']) ? ' current-menu-item' : '') . '"><a href="' . esc_url(home_url('/shop/')) . '"><span class="nav-shop-ico" aria-hidden="true">🛒</span>Магазин<span class="nav-cart-badge cart-count" data-empty="1">0</span></a></li>';
                    echo '</ul>';
                },
            ]);
            ?>
        </nav>

        <!-- Кнопки действий -->
        <div class="header-actions">
            <a href="tel:<?php echo akpp_obf("+79638669996"); ?>" class="btn-phone" aria-label="Позвонить">
                <span class="phone-icon">📞</span>
                <span class="phone-text desktop-only"><?php echo akpp_obf("+7 (963) 866-99-96"); ?></span>
            </a>
            
            <a href="https://t.me/<?php echo akpp_obf("akppkgn"); ?>" class="btn-telegram" target="_blank" rel="noopener">
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

            <!-- Магазин (корзина) -->
            <a href="<?php echo esc_url(home_url('/shop/')); ?>" class="btn-shop<?php echo is_page(['shop','cart','checkout']) ? ' active' : ''; ?>" aria-label="Магазин">
                <span class="shop-icon">🛒</span>
                <span class="shop-text desktop-only">Магазин</span>
                <span class="nav-cart-badge cart-count" data-empty="1">0</span>
            </a>

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
                    echo '<li class="nav-shop-item' . (is_page(['shop','cart','checkout']) ? ' current-menu-item' : '') . '"><a href="' . esc_url(home_url('/shop/')) . '"><span class="nav-shop-ico" aria-hidden="true">🛒</span>Магазин<span class="nav-cart-badge cart-count" data-empty="1">0</span></a></li>';
                    echo '</ul>';
                },
            ]);
            ?>
        </nav>
        
        <!-- Контакты в мобильном меню -->
        <div class="mobile-contacts">
            <a href="tel:<?php echo akpp_obf("+79638669996"); ?>" class="mobile-contact-link" aria-label="Позвонить">
                <span>📞</span> <?php echo akpp_obf("+7 (963) 866-99-96"); ?>
            </a>
            <a href="https://t.me/<?php echo akpp_obf("akppkgn"); ?>" class="mobile-contact-link" target="_blank" rel="noopener">
                <span>💬</span> <?php echo akpp_obf("@akppkgn"); ?>
            </a>
        </div>
    </div>
</header>
<?php /* bk-modal-global: форма записи на всех страницах кроме главной (на главной — своя из index.php) */ if ( function_exists('is_front_page') && !is_front_page() ) : ?>
<!-- ========== МОДАЛКА: ЗАПИСЬ НА РЕМОНТ (глобальная, вынесена из index.php) ========== -->
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
              <input type="text" name="akpp_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">
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
            <button type="submit" class="btn btn-accent btn-block">Отправить заявку</button>
            <p class="form-note">Нажимая кнопку, вы соглашаетесь с обработкой персональных данных</p>
        </form>
    </div>
</div>

<?php endif; ?>
<script>
/* akpp-menu-js-fix: якорная навигация главной работает с любой страницы (ЛК и др.) */
document.addEventListener('DOMContentLoaded', function(){
  var p = window.location.pathname;
  if (p === '/' || p === '' || document.body.classList.contains('home')) return;
  var home = window.location.origin + '/';
  document.querySelectorAll('a[href^="#"]').forEach(function(a){
    a.addEventListener('click', function(e){
      var h = a.getAttribute('href');
      if (h && h.length > 1){ e.preventDefault(); window.location.href = home + h; }
    });
  });
});
</script>


<main class="site-main" id="main">