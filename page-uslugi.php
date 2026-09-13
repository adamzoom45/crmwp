<?php
/* Template Name: Page Услуги */
if (!defined('ABSPATH')) exit;
$d = akpp_tpl_data();
$skin = akpp_tpl_skin_class();
wp_enqueue_style('akpp-tpl-shared', get_template_directory_uri() . '/assets/css/saas-templates.css', [], '20260831c');
global $wpdb, $post;
$services = [];
$tbl = $wpdb->prefix . 'akpp_services';
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tbl)) === $tbl) {
    $services = $wpdb->get_results("SELECT * FROM $tbl ORDER BY id DESC LIMIT 20");
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($d['brand']); ?> — Услуги</title>
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
        <?php if ($post->post_content): ?>
        <div class="tpl-card" style="max-width:800px;margin:0 auto 30px;padding:24px;">
            <?php echo apply_filters('the_content', $post->post_content); ?>
        </div>
        <?php endif; ?>
        <?php if ($services): ?>
        <div class="tpl-products" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr));">
            <?php foreach ($services as $s): ?>
            <div class="tpl-card">
                <b><?php echo esc_html($s->name); ?></b>
                <?php if (!empty($s->price)): ?><h3 style="margin:10px 0 4px;font-size:14px;color:var(--acc);">от <?php echo esc_html(number_format((float)$s->price, 0, '.', ' ')); ?> ₽</h3><?php endif; ?>
                <?php if (!empty($s->description)): ?><p><?php echo esc_html($s->description); ?></p><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="tpl-section-sub">Услуги пока не добавлены в систему. Добавьте через админку: CRM → Услуги.</p>
        <?php endif; ?>
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
