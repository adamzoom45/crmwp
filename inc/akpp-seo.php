<?php
/**
 * АКПП45 — SEO: title/description, Open Graph, Twitter, canonical, Schema.org
 */
if (!defined('ABSPATH')) exit;

add_action('after_setup_theme', function () { add_theme_support('title-tag'); });

function akpp_seo_site() {
    $S = class_exists('AKPP_Shop_Settings') ? 'AKPP_Shop_Settings' : null;
    $get = function ($k, $d) use ($S) { $v = $S ? $S::get($k, $d) : $d; return ($v !== '' && $v !== null) ? $v : $d; };
    $phone_raw = $get('contact_phone', '+7 (963) 866-99-96');
    $digits = preg_replace('/\D/', '', $phone_raw);
    if (strlen($digits) === 11 && $digits[0] === '8') $digits = '7' . substr($digits, 1);
    if (strlen($digits) === 10) $digits = '7' . $digits;
    return [
        'name'        => 'АКПП45 — Ремонт АКПП в Кургане',
        'short'       => 'АКПП45',
        'url'         => home_url('/'),
        'phone'       => '+' . $digits,
        'email'       => $get('contact_email', 'adamzoom@bk.ru'),
        'telegram'    => ltrim($get('contact_telegram', '@akppkgn'), '@'),
        'address'     => $get('pickup_address', 'г. Курган, ул. Бурова-Петрова, 121 (ГСК КАС №8)'),
        'desc_main'   => 'Специализированный ремонт АКПП Toyota и Lexus в Кургане. 11 лет опыта, запчасти по себестоимости, гарантия. Диагностика, ремонт гидротрансформатора, продажа запчастей для АКПП.',
        'desc_shop'   => 'Запчасти для АКПП Toyota, Lexus и других марок в наличии в Кургане. Фильтры, масла, фрикционы, прокладки. Доставка по России. Запчасти по себестоимости, строго по чеку.',
    ];
}

function akpp_seo_title() {
    if (is_front_page())            return 'Ремонт АКПП Toyota и Lexus в Кургане — АКПП45';
    if (is_page('shop'))            return 'Запчасти для АКПП в Кургане — магазин АКПП45';
    if (is_page('cart'))            return 'Корзина — АКПП45';
    if (is_page('checkout'))        return 'Оформление заказа — АКПП45';
    if (is_page('lk'))              return 'Личный кабинет — АКПП45';
    return akpp_seo_site()['name'];
}

function akpp_seo_description() {
    $s = akpp_seo_site();
    if (is_front_page())     return $s['desc_main'];
    if (is_page('shop'))     return $s['desc_shop'];
    if (is_page('cart'))     return 'Корзина магазина запчастей АКПП45. Оформление заказа, доставка по России или самовывоз в Кургане.';
    if (is_page('checkout')) return 'Оформление заказа в магазине запчастей АКПП45. Оплата картой, СБП, криптовалютой. Доставка по России.';
    return $s['desc_main'];
}

function akpp_seo_noindex() { return is_page(['lk', 'cart', 'checkout']); }

add_filter('document_title_parts', function ($p) { $p['title'] = akpp_seo_title(); unset($p['tagline']); return $p; });
add_filter('document_title_separator', function () { return '—'; });

add_action('wp_head', 'akpp_seo_head', 1);
function akpp_seo_head() {
    $s = akpp_seo_site();
    $desc = akpp_seo_description();
    $url = home_url($_SERVER['REQUEST_URI'] ?? '/');
    $title = akpp_seo_title();

    echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
    echo '<meta name="robots" content="' . (akpp_seo_noindex() ? 'noindex,nofollow' : 'index,follow,max-image-preview:large') . '">' . "\n";
    echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";

    // Open Graph
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr($s['short']) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    echo '<meta property="og:locale" content="ru_RU">' . "\n";

    // Twitter
    echo '<meta name="twitter:card" content="summary">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($desc) . '">' . "\n";

    // Schema.org: AutoRepair (организация)
    $org = [
        '@context' => 'https://schema.org',
        '@type' => 'AutoRepair',
        'name' => $s['name'],
        'description' => $s['desc_main'],
        'url' => $s['url'],
        'telephone' => $s['phone'],
        'email' => $s['email'],
        'priceRange' => '₽₽',
        'currenciesAccepted' => 'RUB',
        'address' => ['@type' => 'PostalAddress', 'streetAddress' => $s['address'], 'addressLocality' => 'Курган', 'addressCountry' => 'RU'],
        'openingHoursSpecification' => [[
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
            'opens' => '09:00', 'closes' => '19:00',
        ]],
        'sameAs' => ['https://t.me/' . $s['telegram']],
    ];
    echo '<script type="application/ld+json">' . wp_json_encode($org, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";

    // Schema.org: WebSite
    $ws = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $s['short'], 'url' => $s['url'], 'inLanguage' => 'ru'];
    echo '<script type="application/ld+json">' . wp_json_encode($ws, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
