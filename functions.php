<?php
/**
 * АКПП45 - Функции темы и подключение CRM
 *
 * @package AKPP45
 * @version 4.3
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// ПОДКЛЮЧЕНИЕ CRM СИСТЕМЫ
// =============================================================================
require_once get_template_directory() . '/inc/crm/class-akpp-crm.php';

// Инициализация CRM после загрузки темы
add_action('after_setup_theme', function() {
    if (class_exists('AKPP_CRM')) {
        AKPP_CRM::get_instance();
    }
});

// =============================================================================
// ПОДКЛЮЧЕНИЕ СТИЛЕЙ И СКРИПТОВ
// =============================================================================
function akpp45_enqueue_assets() {
    $theme_uri = get_template_directory_uri();
    $theme_dir = get_template_directory();
    
    // CSS
    wp_enqueue_style('akpp-style', get_stylesheet_uri(), [], '4.3.0');
    wp_enqueue_style('akpp-admin', $theme_uri . '/assets/css/admin.css', [], '4.3.0');
    wp_enqueue_style('akpp-frontend', $theme_uri . '/assets/css/frontend.css', [], '4.3.0');
    
    // JS (все из корня темы /assets/js/)
    wp_enqueue_script('jquery');
    wp_enqueue_script('akpp-admin', $theme_uri . '/assets/js/admin.js', ['jquery'], '4.3.0', true);
    wp_enqueue_script('akpp-auth', $theme_uri . '/assets/js/auth.js', ['jquery'], '4.3.0', true);
    wp_enqueue_script('akpp-deal-calculator', $theme_uri . '/assets/js/deal-calculator.js', ['jquery'], '4.3.0', true);
    wp_enqueue_script('akpp-vin-decoder', $theme_uri . '/assets/js/vin-decoder.js', ['jquery'], '4.3.0', true);
    wp_enqueue_script('akpp-chat', $theme_uri . '/assets/js/chat.js', ['jquery'], '4.3.0', true);
    
    // Локализация для JS
    wp_localize_script('akpp-admin', 'akpp_ajax', [
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('akpp_ajax_nonce')
    ]);
    
    wp_localize_script('akpp-deal-calculator', 'akpp_deal', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('akpp_save_deal_nonce')
    ]);
    
    wp_localize_script('akpp-vin-decoder', 'akpp_vin_decoder_config', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('akpp_vin_decode_nonce')
    ]);
    
    wp_localize_script('akpp-chat', 'akpp_chat_config', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('akpp_chat_action_nonce'),
        'strings' => [
            'sending' => 'Отправка...',
            'error' => 'Ошибка отправки сообщения'
        ]
    ]);
}
add_action('wp_enqueue_scripts', 'akpp45_enqueue_assets');
add_action('admin_enqueue_scripts', 'akpp45_enqueue_assets');

// =============================================================================
// ВИДЖЕТ CRM НА ДАШБОРДЕ WORDPRESS
// =============================================================================
function akpp45_render_dashboard_widget() {
    global $wpdb;
    
    // Получаем статистику из БД
    $deals_table = $wpdb->prefix . 'akpp_deals';
    $leads_table = $wpdb->prefix . 'akpp_leads';
    $parts_table = $wpdb->prefix . 'akpp_parts';
    
    // Проверяем существование таблиц
    $deals_count = 0;
    $leads_count = 0;
    $parts_low = 0;
    
    if ($wpdb->get_var("SHOW TABLES LIKE '{$deals_table}'") == $deals_table) {
        $deals_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$deals_table} WHERE status IN ('new', 'in_work')");
    }
    
    if ($wpdb->get_var("SHOW TABLES LIKE '{$leads_table}'") == $leads_table) {
        $leads_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$leads_table} WHERE status = 'new'");
    }
    
    if ($wpdb->get_var("SHOW TABLES LIKE '{$parts_table}'") == $parts_table) {
        $parts_low = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$parts_table} WHERE quantity < 5");
    }
    
    // Вывод виджета
    echo '<div class="akpp-dashboard-widget" style="padding: 15px; background: #f9f9f9; border-left: 4px solid #00ff88;">';
    echo '<h3 style="margin: 0 0 15px 0; color: #1d2327;">🚗 АКПП45 CRM</h3>';
    echo '<p style="margin: 8px 0;"><strong>📊 Активных сделок:</strong> ' . $deals_count . '</p>';
    echo '<p style="margin: 8px 0;"><strong>🆕 Новых лидов:</strong> ' . $leads_count . '</p>';
    echo '<p style="margin: 8px 0;"><strong>⚠️ Запчастей мало:</strong> ' . $parts_low . '</p>';
    echo '<p style="margin: 15px 0 0 0;">';
    echo '<a href="' . admin_url('admin.php?page=akpp-crm-dashboard') . '" class="button button-primary" style="background: #00ff88; border-color: #00cc6a; color: #0a0f1c;">Перейти в CRM</a>';
    echo '</p>';
    echo '</div>';
}

function akpp45_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'akpp45_crm_widget',
        'АКПП45 CRM Статистика',
        'akpp45_render_dashboard_widget'
    );
}
add_action('wp_dashboard_setup', 'akpp45_add_dashboard_widget');

// =============================================================================
// ПОДДЕРЖКА ТЕМЫ
// =============================================================================
function akpp45_theme_setup() {
    // Поддержка заголовков
    add_theme_support('title-tag');
    
    // Поддержка миниатюр
    add_theme_support('post-thumbnails');
    
    // Регистрация меню
    register_nav_menus([
        'primary' => __('Главное меню', 'akpp45'),
        'footer' => __('Меню в подвале', 'akpp45')
    ]);
    
    // HTML5 поддержка
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption'
    ]);
}
add_action('after_setup_theme', 'akpp45_theme_setup');

// =============================================================================
// РЕГИСТРАЦИЯ САЙДБАРОВ
// =============================================================================
function akpp45_widgets_init() {
    register_sidebar([
        'name' => __('Боковая панель', 'akpp45'),
        'id' => 'sidebar-1',
        'description' => __('Виджеты боковой панели', 'akpp45'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget' => '</section>',
        'before_title' => '<h2 class="widget-title">',
        'after_title' => '</h2>',
    ]);
}
add_action('widgets_init', 'akpp45_widgets_init');

// =============================================================================
// БЕЗОПАСНОСТЬ И ОПТИМИЗАЦИЯ
// =============================================================================

// Отключение версии WordPress в head
remove_action('wp_head', 'wp_generator');

// Отключение XML-RPC (если не используется)
add_filter('xmlrpc_enabled', '__return_false');

// Ограничение попыток входа
function akpp45_limit_login_attempts() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $transient_key = 'login_attempts_' . md5($ip);
    $attempts = get_transient($transient_key);
    
    if ($attempts >= 5) {
        wp_die('Слишком много попыток входа. Подождите 15 минут.', 'Ошибка входа', ['response' => 403]);
    }
}
add_action('wp_login_failed', 'akpp45_login_failed');
function akpp45_login_failed($username) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $transient_key = 'login_attempts_' . md5($ip);
    $attempts = get_transient($transient_key);
    
    if ($attempts === false) {
        set_transient($transient_key, 1, 900); // 15 минут
    } else {
        set_transient($transient_key, $attempts + 1, 900);
    }
}

// =============================================================================
// КОРОТКИЕ КОДЫ (SHORTCODES)
// =============================================================================

// Шорткод для формы регистрации
add_shortcode('akpp_registration_form', function() {
    ob_start();
    include get_template_directory() . '/inc/crm/templates/frontend/registration.php';
    return ob_get_clean();
});

// Шорткод для клиентского чата
add_shortcode('akpp_client_chat', function() {
    if (!is_user_logged_in()) {
        return '<p>Пожалуйста, войдите в систему для доступа к чату.</p>';
    }
    ob_start();
    include get_template_directory() . '/inc/crm/templates/frontend/chat.php';
    return ob_get_clean();
});

// =============================================================================
// КАСТОМИЗАЦИЯ ADMIN PANEL
// =============================================================================
function akpp45_custom_admin_footer() {
    echo '<span style="color: #00ff88;">АКПП45 CRM</span> | Версия 4.3 | <a href="https://akpp45.ru" target="_blank">akpp45.ru</a>';
}
add_filter('admin_footer_text', 'akpp45_custom_admin_footer');

// Удаление лишних виджетов с дашборда
function akpp45_remove_dashboard_widgets() {
    remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
    remove_meta_box('dashboard_primary', 'dashboard', 'side');
    remove_meta_box('dashboard_secondary', 'dashboard', 'side');
}
add_action('wp_dashboard_setup', 'akpp45_remove_dashboard_widgets', 999);
