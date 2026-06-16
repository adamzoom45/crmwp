<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); bloginfo('name'); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
    <div class="header-inner">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo">
            АКПП<span>45</span>
        </a>

        <nav class="main-nav">
            <?php
            wp_nav_menu(array(
                'theme_location' => 'primary',
                'container'      => false,
                'fallback_cb'    => 'akpp_fallback_menu',
            ));
            ?>
        </nav>

        <a href="tel:<?php echo esc_attr(str_replace(array(' ', '(', ')', '-'), '', akpp_get_option('phone_1', '+79638669996'))); ?>" class="header-phone">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
            <?php echo esc_html(akpp_get_option('phone_1', '+7 (963) 866-99-96')); ?>
        </a>

        <button class="mobile-menu-btn" id="mobile-menu-toggle" aria-label="Меню">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>

    <div class="mobile-menu" id="mobile-menu">
        <nav class="mobile-nav">
            <?php
            wp_nav_menu(array(
                'theme_location' => 'primary',
                'container'      => false,
                'fallback_cb'    => 'akpp_fallback_menu',
            ));
            ?>
        </nav>
    </div>
</header>

<main id="main-content">