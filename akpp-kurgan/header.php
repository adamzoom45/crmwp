<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
    <div class="container">
        <div class="site-branding">
            <?php if (has_custom_logo()) : ?>
                <div class="site-logo">
                    <?php the_custom_logo(); ?>
                </div>
            <?php else : ?>
                <h1 class="site-title">
                    <a href="<?php echo esc_url(home_url('/')); ?>" rel="home">
                        <?php bloginfo('name'); ?>
                    </a>
                </h1>
                <p class="site-description"><?php bloginfo('description'); ?></p>
            <?php endif; ?>
        </div>

        <nav class="main-navigation">
            <button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false">
                ☰ Меню
            </button>
            <?php
            wp_nav_menu([
                'theme_location' => 'primary',
                'menu_class' => 'nav-menu',
                'fallback_cb' => false,
            ]);
            ?>
        </nav>
    </div>
</header>

<main id="main" class="site-main">
