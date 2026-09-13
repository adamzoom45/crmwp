<?php
if (!defined('ABSPATH')) exit;
$d = akpp_tpl_data();
$tpl_class = isset($tpl_class) ? $tpl_class : 'tpl-impulse';
$show_shop = !empty($tpl_show_shop) && (bool) get_option('akpp_shop_enabled', 1);
wp_enqueue_style('akpp-tpl-shared', get_template_directory_uri() . '/assets/css/saas-templates.css', [], '20260830b');

// ЛК: реальная страница кабинета, если есть; иначе вход
$lk_url = wp_login_url(home_url('/'));
foreach (['account', 'my', 'lk', 'cabinet', 'lichnyj-kabinet'] as $slug) {
    $pg = get_page_by_path($slug);
    if ($pg) { $lk_url = get_permalink($pg); break; }
}
// Товары магазина (живые данные)
$products = [];
if ($show_shop) {
    global $wpdb;
    $tbl = $wpdb->prefix . 'akpp_shop_products';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tbl)) === $tbl) {
        $products = $wpdb->get_results("SELECT * FROM $tbl ORDER BY id DESC LIMIT 4"); // phpcs:ignore
    }
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html(get_bloginfo('name')); ?> — <?php echo esc_html($d['hero_title']); ?></title>
<?php wp_head(); ?>
</head>
<body class="tpl-page <?php echo esc_attr($tpl_class); ?>">
<header class="tpl-nav"><div class="tpl-nav-in">
    <a class="tpl-brand" href="<?php echo esc_url(home_url('/')); ?>"><span class="tpl-brand-mark">✓</span><?php echo esc_html($d['brand']); ?></a>
    <nav class="tpl-links">
        <a href="#services"><?php echo esc_html($d['nav1']); ?></a>
        <a href="<?php echo esc_url(home_url('/shop/')); ?>">Магазин</a>
        <a href="#warranty">Гарантия</a>
        <a href="#faq">Вопросы</a>
        <a href="#contacts"><?php echo esc_html($d['nav3']); ?></a>
    </nav>
    <div class="tpl-nav-cta">
        <a class="tpl-btn tpl-btn-ghost tpl-btn-sm" href="<?php echo esc_url($lk_url); ?>"><?php echo esc_html($d['lk_label']); ?></a>
        <?php if (!empty($d['cta_nav'])): ?><a class="tpl-btn tpl-btn-solid tpl-btn-sm" href="<?php echo esc_url(!empty($d['phone_href']) ? $d['phone_href'] : '#contacts'); ?>"><?php echo esc_html($d['cta_nav']); ?></a><?php endif; ?>
    </div>
</div></header>
<main>
<section class="tpl-hero">
    <div>
        <?php if (!empty($d['badge'])): ?><div class="tpl-badge"><?php echo esc_html($d['badge']); ?></div><?php endif; ?>
        <h1><?php echo esc_html($d['hero_title']); ?> <span class="tpl-accent"><?php echo esc_html($d['hero_accent']); ?></span></h1>
        <p class="tpl-sub"><?php echo esc_html($d['hero_sub']); ?></p>
        <div class="tpl-cta" id="cta">
            <?php if (!empty($d['phone_href'])): ?><a class="tpl-btn tpl-btn-solid" href="<?php echo esc_url($d['phone_href']); ?>"><?php echo esc_html($d['cta1']); ?></a><?php endif; ?>
            <?php if (!empty($d['telegram'])): ?><a class="tpl-btn tpl-btn-ghost" href="https://t.me/<?php echo esc_attr(ltrim($d['telegram'], '@/')); ?>" target="_blank" rel="noopener"><?php echo esc_html($d['telegram'] !== '' ? $d['telegram'] : $d['cta2']); ?></a><?php endif; ?>
        </div>
        <?php if (!empty($d['phone_display'])): ?><div class="tpl-phone">📞 <?php echo esc_html($d['phone_display']); ?></div><?php endif; ?>
    </div>
    <aside class="tpl-cards" id="services">
        <div class="tpl-card"><b>01</b><h3><?php echo esc_html($d['b1_title']); ?></h3><p><?php echo esc_html($d['b1_text']); ?></p></div>
        <div class="tpl-card"><b>02</b><h3><?php echo esc_html($d['b2_title']); ?></h3><p><?php echo esc_html($d['b2_text']); ?></p></div>
        <div class="tpl-card"><b>03</b><h3><?php echo esc_html($d['b3_title']); ?></h3><p><?php echo esc_html($d['b3_text']); ?></p></div>
    </aside>
</section>

<?php if ($show_shop): ?>
<section class="tpl-section" id="shop">
    <div class="tpl-section-in">
        <h2><?php echo esc_html($d['shop_title']); ?></h2>
        <p class="tpl-section-sub"><?php echo esc_html($d['shop_sub']); ?></p>
        <?php if ($products): ?>
        <div class="tpl-products">
            <?php foreach ($products as $p):
                $pname = isset($p->name) ? $p->name : (isset($p->title) ? $p->title : 'Товар');
                $pprice = isset($p->price) ? number_format((float) $p->price, 0, '.', ' ') : '';
            ?>
            <a class="tpl-product" href="<?php echo esc_url(home_url('/shop/')); ?>">
                <span class="tpl-product-name"><?php echo esc_html($pname); ?></span>
                <?php if ($pprice !== ''): ?><span class="tpl-product-price"><?php echo esc_html($pprice); ?> ₽</span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <a class="tpl-btn tpl-btn-ghost" href="<?php echo esc_url(home_url('/shop/')); ?>">Открыть весь магазин →</a>
    </div>
</section>
<?php endif; ?>

<section class="tpl-section" id="steps">
    <div class="tpl-section-in">
        <h2><?php echo esc_html($d['steps_title']); ?></h2>
        <ol class="tpl-steps-list">
            <li><b>01</b><?php echo esc_html($d['s1']); ?></li>
            <li><b>02</b><?php echo esc_html($d['s2']); ?></li>
            <li><b>03</b><?php echo esc_html($d['s3']); ?></li>
        </ol>
    </div>
</section>

<section class="tpl-section tpl-section-alt" id="warranty">
    <div class="tpl-section-in tpl-warranty">
        <div>
            <h2><?php echo esc_html($d['warranty_title']); ?></h2>
            <p><?php echo esc_html($d['warranty_text']); ?></p>
        </div>
        <div class="tpl-warranty-badge">🛡</div>
    </div>
</section>

<section class="tpl-section" id="faq">
    <div class="tpl-section-in">
        <h2>Вопросы и ответы</h2>
        <div class="tpl-faq">
            <?php for ($i = 1; $i <= 3; $i++): if (empty($d['faq'.$i.'_q'])) continue; ?>
            <details class="tpl-faq-item">
                <summary><?php echo esc_html($d['faq'.$i.'_q']); ?></summary>
                <p><?php echo esc_html($d['faq'.$i.'_a']); ?></p>
            </details>
            <?php endfor; ?>
        </div>
    </div>
</section>

<section class="tpl-section tpl-section-alt" id="contacts">
    <div class="tpl-section-in tpl-contacts">
        <h2>Контакты</h2>
        <div class="tpl-contacts-grid">
            <?php if (!empty($d['address'])): ?><div class="tpl-contact"><b>📍 Адрес</b><?php echo esc_html($d['address']); ?></div><?php endif; ?>
            <?php if (!empty($d['hours'])): ?><div class="tpl-contact"><b>🕘 Часы</b><?php echo esc_html($d['hours']); ?></div><?php endif; ?>
            <?php if (!empty($d['phone_display'])): ?><div class="tpl-contact"><b>📞 Телефон</b><a href="<?php echo esc_url($d['phone_href']); ?>"><?php echo esc_html($d['phone_display']); ?></a></div><?php endif; ?>
            <?php if (!empty($d['telegram'])): ?><div class="tpl-contact" style="grid-column:1/-1;"><b>Мы в сети</b>
                <div class="tpl-contact-logos">
                    <?php if (!empty($d['telegram'])): ?><a class="tpl-logo-link" href="https://t.me/<?php echo esc_attr(ltrim($d['telegram'], '@/')); ?>" target="_blank" rel="noopener" aria-label="Telegram" title="Telegram"><svg viewBox="0 0 24 24" width="26" height="26" fill="#229ED9"><path d="M9.04 15.51l-.38 5.35c.54 0 .78-.23 1.06-.5l2.55-2.44 5.28 3.87c.97.53 1.66.25 1.92-.9l3.48-16.3c.31-1.44-.52-2.02-1.47-1.66L1.06 8.82c-1.4.55-1.38 1.33-.24 1.68l5.2 1.62L18.1 4.5c.57-.37 1.08-.17.66.2L9.04 15.51z"/></svg></a><?php endif; ?>
                    <?php $gis = get_option('akpp_2gis_url', ''); if ($gis !== ''): ?><a class="tpl-logo-link" href="<?php echo esc_url($gis); ?>" target="_blank" rel="noopener" aria-label="2ГИС" title="2ГИС"><svg viewBox="0 0 24 24" width="26" height="26"><rect width="24" height="24" rx="6" fill="#00b33c"/><text x="12" y="16" font-size="8" font-weight="800" fill="#fff" text-anchor="middle" font-family="Arial,sans-serif">2GIS</text></svg></a><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($d['email'])): ?><div class="tpl-contact"><b>✉️ Email</b><a href="mailto:<?php echo esc_attr($d['email']); ?>"><?php echo esc_html($d['email']); ?></a></div><?php endif; ?>
            <div class="tpl-contact"><b>👤 Кабинет</b><a href="<?php echo esc_url($lk_url); ?>"><?php echo esc_html($d['lk_label']); ?></a></div>
        </div>
    </div>
</section>
</main>
<footer class="tpl-footer" id="footer">
    <div class="tpl-footer-in">
        <div><?php echo esc_html($d['footer']); ?></div>
        <nav class="tpl-footer-links">
            <a href="#services"><?php echo esc_html($d['nav1']); ?></a>
            <a href="<?php echo esc_url(home_url('/shop/')); ?>">Магазин</a>
            <a href="#warranty">Гарантия</a>
            <a href="#faq">Вопросы</a>
            <a href="#contacts"><?php echo esc_html($d['nav3']); ?></a>
            <a href="<?php echo esc_url($lk_url); ?>">Кабинет</a>
        </nav>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
