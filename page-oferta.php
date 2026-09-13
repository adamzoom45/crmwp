<?php
/* Template Name: Page Оферта */
if (!defined('ABSPATH')) exit;
$d = akpp_tpl_data();
$skin = akpp_tpl_skin_class();
wp_enqueue_style('akpp-tpl-shared', get_template_directory_uri() . '/assets/css/saas-templates.css', [], '20260907a');
$custom = trim((string) get_option('akpp_custom_agreement', ''));
if ($custom === '') {
    require_once get_template_directory() . '/inc/crm/templates/agreement-text.php';
    $content = akpp_get_agreement_text('1.1');
} else {
    $content = '<div class="agreement-full-text agreement-protected">' . wp_kses_post($custom) . '</div>';
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($d['brand']); ?> — Оферта</title>
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
    <div class="tpl-section-in" style="max-width:900px;margin:0 auto;">
        <h1><?php echo esc_html(get_the_title()); ?></h1>
        <div class="tpl-card" style="padding:0;margin-top:20px;overflow:hidden;">
            <?php echo $content; ?>
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
