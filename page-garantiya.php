<?php
/* Template Name: Page Гарантия */
if (!defined('ABSPATH')) exit;
$d = akpp_tpl_data();
$skin = akpp_tpl_skin_class();
wp_enqueue_style('akpp-tpl-shared', get_template_directory_uri() . '/assets/css/saas-templates.css', [], '20260831c');
global $post;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($d['brand']); ?> — Гарантия</title>
<?php wp_head(); ?>
</head>
<body class="tpl-page <?php echo esc_attr($skin); ?>">
<header class="tpl-nav"><div class="tpl-nav-in">
    <a class="tpl-brand" href="<?php echo esc_url(home_url('/')); ?>"><span class="tpl-brand-mark">✓</span><?php echo esc_html($d['brand']); ?></a>
    <nav class="tpl-links">
        <a href="<?php echo esc_url(home_url('/#services')); ?>">Услуги</a>
        <a href="<?php echo esc_url(home_url('/shop/')); ?>">Магазин</a>
        <a href="<?php echo esc_url(home_url('/#warranty')); ?>">Гарантия</a>
        <a href="<?php echo esc_url(home_url('/#faq')); ?>">Вопросы</a>
        <a href="<?php echo esc_url(home_url('/#contacts')); ?>">Контакты</a>
    </nav>
    <a class="tpl-btn tpl-btn-solid tpl-btn-sm" href="<?php echo esc_url(home_url('/lk/')); ?>">Кабинет</a>
</div></header>
<main>
<section class="tpl-section">
    <div class="tpl-section-in">
        <h1><?php echo esc_html(get_the_title()); ?></h1>
        <div class="tpl-card" style="max-width:800px;margin:20px auto;padding:30px;">
            <h2><?php echo esc_html($d['warranty_title']); ?></h2>
            <p><?php echo esc_html($d['warranty_text']); ?></p>
            <?php if ($post->post_content): ?>
            <hr style="margin:30px 0;border:0;border-top:1px solid var(--line);">
            <?php echo apply_filters('the_content', $post->post_content); ?>
            <?php endif; ?>
            <div style="margin-top:30px;">
                <a class="tpl-btn tpl-btn-solid" href="<?php echo esc_url(home_url('/oferta/')); ?>">Читать полную оферту →</a>
            </div>
        </div>
    </div>
</section>
</main>
<footer class="tpl-footer"><div class="tpl-footer-in">
    <div><?php echo esc_html($d['footer']); ?></div>
    <nav class="tpl-footer-links">
        <a href="<?php echo esc_url(home_url('/')); ?>">Главная</a>
        <a href="<?php echo esc_url(home_url('/shop/')); ?>">Магазин</a>
        <a href="<?php echo esc_url(home_url('/lk/')); ?>">Кабинет</a>
    </nav>
</div></footer>
<?php wp_footer(); ?>
</body>
</html>
