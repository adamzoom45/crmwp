<?php
/**
 * The header for our theme
 *
 * @package AKPP45
 * @version 5.0
 */

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Профессиональный ремонт АКПП в Кургане. Диагностика, ремонт ЭБУ, замена масла. Гарантия качества.">
    <meta name="keywords" content="ремонт АКПП Курган, диагностика АКПП, ремонт ЭБУ, замена масла АКПП">
    
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- ================================================================== -->
<!-- HEADER -->
<!-- ================================================================== -->
<header class="site-header">
    <div class="header-inner">
        <!-- Logo -->
        <div class="site-logo">
            <a href="<?php echo esc_url(home_url('/')); ?>">
                АКПП <span>Курган</span>
            </a>
        </div>

        <!-- Desktop Navigation -->
        <nav class="main-nav">
            <?php
            wp_nav_menu([
                'theme_location' => 'primary',
                'menu_class'     => 'nav-menu',
                'container'      => false,
                'fallback_cb'    => 'akpp_default_menu'
            ]);
            ?>
        </nav>

        <!-- Phone -->
        <div class="header-phone">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path>
            </svg>
            <a href="tel:+73522123456">+7 (3522) 123-45-67</a>
        </div>

        <!-- Mobile Menu Button -->
        <button class="mobile-menu-btn" aria-label="Открыть меню">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>

    <!-- Mobile Menu -->
    <div class="mobile-menu">
        <nav class="mobile-nav">
            <?php
            wp_nav_menu([
                'theme_location' => 'mobile',
                'menu_class'     => 'mobile-menu-list',
                'container'      => false,
                'fallback_cb'    => 'akpp_default_menu'
            ]);
            ?>
        </nav>
        <div class="mobile-phone">
            <a href="tel:+73522123456">+7 (3522) 123-45-67</a>
        </div>
    </div>
</header>

<!-- Fallback Menu Function -->
<?php
if (!function_exists('akpp_default_menu')) {
    function akpp_default_menu() {
        echo '<ul class="nav-menu">';
        echo '<li><a href="#services">Услуги</a></li>';
        echo '<li><a href="#pricing">Цены</a></li>';
        echo '<li><a href="#workflow">Процесс</a></li>';
        echo '<li><a href="#contact">Контакты</a></li>';
        echo '</ul>';
    }
}
?>

<script>
// Mobile Menu Toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const mobileMenu = document.querySelector('.mobile-menu');
    
    if (menuBtn && mobileMenu) {
        menuBtn.addEventListener('click', function() {
            mobileMenu.classList.toggle('active');
            this.classList.toggle('active');
        });
        
        // Close menu when clicking on a link
        const mobileLinks = mobileMenu.querySelectorAll('a');
        mobileLinks.forEach(link => {
            link.addEventListener('click', function() {
                mobileMenu.classList.remove('active');
                menuBtn.classList.remove('active');
            });
        });
    }
});
</script>