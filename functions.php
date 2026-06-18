<?php
/**
 * АКПП Курган - Theme Functions
 * Корень репозитория = тема WordPress
 *
 * @package AKPP45
 * @version 5.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

// =============================================================================
// КОНСТАНТЫ ТЕМЫ
// =============================================================================
define('AKPP_THEME_VERSION', '5.0.0');
define('AKPP_THEME_DIR', get_template_directory());
define('AKPP_THEME_URI', get_template_directory_uri());
define('AKPP_CRM_DIR', AKPP_THEME_DIR . '/inc/crm');
define('AKPP_CRM_URI', AKPP_THEME_URI . '/inc/crm');

// =============================================================================
// 1. ПОДКЛЮЧЕНИЕ CRM СИСТЕМЫ (КРИТИЧЕСКИ ВАЖНО!)
// =============================================================================

// Функция безопасного подключения файла
function akpp_require_file($file) {
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    error_log('[AKPP45] Файл не найден: ' . $file);
    return false;
}


// ИНИЦИАЛИЗАЦИЯ CRM
$crm_main_file = AKPP_CRM_DIR . '/class-akpp-crm.php';
if (file_exists($crm_main_file)) {
    require_once $crm_main_file;
    add_action('after_setup_theme', function() {
        if (class_exists('AKPP_CRM')) {
            try {
                AKPP_CRM::get_instance();
            } catch (Exception $e) {
                error_log('[AKPP45 CRM] Ошибка: ' . $e->getMessage());
            }
        }
    }, 5);
}


// =============================================================================
// 2. THEME SETUP
// =============================================================================

function akpp45_theme_setup() {
    // RSS feeds
    add_theme_support('automatic-feed-links');
    
    // Title tag
    add_theme_support('title-tag');
    
    // Post thumbnails
    add_theme_support('post-thumbnails');
    
    // HTML5 support
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);
    
    // Custom logo
    add_theme_support('custom-logo', [
        'height'      => 100,
        'width'       => 300,
        'flex-width'  => true,
        'flex-height' => true,
    ]);
    
    // Responsive embeds
    add_theme_support('responsive-embeds');
    
    // Custom background
    add_theme_support('custom-background', [
        'default-color' => '0a0f1c',
    ]);

    // Регистрация меню
    register_nav_menus([
        'primary' => __('Главное меню', 'akpp45'),
        'footer'  => __('Меню в подвале', 'akpp45'),
        'mobile'  => __('Мобильное меню', 'akpp45'),
    ]);
}
add_action('after_setup_theme', 'akpp45_theme_setup');

// =============================================================================
// 3. ПОДКЛЮЧЕНИЕ СТИЛЕЙ И СКРИПТОВ
// =============================================================================

function akpp45_enqueue_assets() {
    $theme_uri = AKPP_THEME_URI;
    $assets_uri = $theme_uri . '/assets';
    
    // === СТИЛИ ===
    
    // Google Fonts - Inter
    wp_enqueue_style(
        'akpp45-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap',
        [],
        null
    );
    
    // Основной стиль темы
    wp_enqueue_style(
        'akpp45-style',
        get_stylesheet_uri(),
        ['akpp45-fonts'],
        AKPP_THEME_VERSION
    );
    
    // Admin CSS (только в админке)
    if (is_admin()) {
        wp_enqueue_style(
            'akpp45-admin',
            $assets_uri . '/css/admin.css',
            [],
            AKPP_THEME_VERSION
        );
    } else {
        // Frontend CSS
        wp_enqueue_style(
            'akpp45-frontend',
            $assets_uri . '/css/frontend.css',
            [],
            AKPP_THEME_VERSION
        );
        
        wp_enqueue_style(
            'akpp45-modal',
            $assets_uri . '/css/modal.css',
            [],
            AKPP_THEME_VERSION
        );
    }
    
    // === СКРИПТЫ ===
    
    // jQuery (уже есть в WP, но явно указываем)
    wp_enqueue_script('jquery');
    
    // Основной JS
    wp_enqueue_script(
        'akpp45-main',
        $assets_uri . '/js/admin.js', // admin.js содержит общие функции
        ['jquery'],
        AKPP_THEME_VERSION,
        true
    );
    
    // Локализация для AJAX
    wp_localize_script('akpp45-main', 'akpp45_ajax', [
        'url'   => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('akpp45_nonce'),
        'home'  => home_url('/'),
    ]);
    
    // Frontend скрипты
    if (!is_admin()) {
        wp_enqueue_script(
            'akpp45-auth',
            $assets_uri . '/js/auth.js',
            ['jquery'],
            AKPP_THEME_VERSION,
            true
        );
        
        wp_enqueue_script(
            'akpp45-chat',
            $assets_uri . '/js/chat.js',
            ['jquery'],
            AKPP_THEME_VERSION,
            true
        );
        
        wp_localize_script('akpp45-chat', 'akpp_chat_config', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp_chat_action_nonce'),
        ]);
    }
    
    // Admin скрипты
    if (is_admin()) {
        // Калькулятор сделок
        wp_enqueue_script(
            'akpp45-deal-calculator',
            $assets_uri . '/js/deal-calculator.js',
            ['jquery'],
            AKPP_THEME_VERSION,
            true
        );
        
        wp_localize_script('akpp45-deal-calculator', 'akpp_deal', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp_save_deal_nonce'),
        ]);
        
        // VIN декодер
        wp_enqueue_script(
            'akpp45-vin-decoder',
            $assets_uri . '/js/vin-decoder.js',
            ['jquery'],
            AKPP_THEME_VERSION,
            true
        );
        
        wp_localize_script('akpp45-vin-decoder', 'akpp_vin_decoder_config', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp_vin_decode_nonce'),
        ]);
    }
    
    // Comment reply
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
add_action('wp_enqueue_scripts', 'akpp45_enqueue_assets');
add_action('admin_enqueue_scripts', 'akpp45_enqueue_assets');

// =============================================================================
// 4. WIDGETS
// =============================================================================

function akpp45_widgets_init() {
    register_sidebar([
        'name'          => __('Боковая панель', 'akpp45'),
        'id'            => 'sidebar-1',
        'description'   => __('Виджеты боковой панели', 'akpp45'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ]);

    register_sidebar([
        'name'          => __('Подвал сайта', 'akpp45'),
        'id'            => 'footer-1',
        'description'   => __('Виджеты в подвале', 'akpp45'),
        'before_widget' => '<div id="%1$s" class="footer-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="footer-widget-title">',
        'after_title'   => '</h3>',
    ]);
}
add_action('widgets_init', 'akpp45_widgets_init');

// =============================================================================
// 5. ШОРТКОДЫ
// =============================================================================

// Форма регистрации
add_shortcode('akpp_registration_form', function() {
    $file = AKPP_CRM_DIR . '/templates/frontend/registration.php';
    if (file_exists($file)) {
        ob_start();
        include $file;
        return ob_get_clean();
    }
    return '<p>Форма регистрации недоступна.</p>';
});

// Клиентский чат
add_shortcode('akpp_client_chat', function() {
    if (!is_user_logged_in()) {
        return '<p>Пожалуйста, войдите в систему для доступа к чату.</p>';
    }
    $file = AKPP_CRM_DIR . '/templates/frontend/chat.php';
    if (file_exists($file)) {
        ob_start();
        include $file;
        return ob_get_clean();
    }
    return '<p>Чат недоступен.</p>';
});

// Кнопка связи
add_shortcode('akpp_contact_btn', function($atts) {
    $atts = shortcode_atts([
        'text' => 'Связаться с нами',
        'url'  => '#contact',
    ], $atts);
    
    return '<a href="' . esc_url($atts['url']) . '" class="btn btn-primary btn-glow">' . esc_html($atts['text']) . '</a>';
});

// =============================================================================
// 6. ОПТИМИЗАЦИЯ И БЕЗОПАСНОСТЬ
// =============================================================================

// Отключение emoji
function akpp45_disable_emojis() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
}
add_action('init', 'akpp45_disable_emojis');

// Удаление версии WP
remove_action('wp_head', 'wp_generator');

// Отключение XML-RPC
add_filter('xmlrpc_enabled', '__return_false');

// Удаление лишних ссылок из head
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_shortlink_wp_head');

// Ограничение попыток входа
function akpp45_limit_login_attempts($username) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (empty($ip)) return;
    
    $key = 'login_attempts_' . md5($ip);
    $attempts = (int) get_transient($key);
    
    if ($attempts >= 5) {
        wp_die(
            'Слишком много попыток входа. Подождите 15 минут.',
            'Ошибка входа',
            ['response' => 429]
        );
    }
    
    set_transient($key, $attempts + 1, 900);
}
add_action('wp_login_failed', 'akpp45_limit_login_attempts');

function akpp45_clear_login_attempts($username) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (empty($ip)) return;
    delete_transient('login_attempts_' . md5($ip));
}
add_action('wp_login', 'akpp45_clear_login_attempts');

// =============================================================================
// 7. КАСТОМИЗАЦИЯ АДМИНКИ
// =============================================================================

function akpp45_admin_footer_text($text) {
    return '<span style="color: #00ff88;">АКПП Курган CRM</span> | v' . AKPP_THEME_VERSION . ' | <a href="https://akpp45.ru" target="_blank">akpp45.ru</a>';
}
add_filter('admin_footer_text', 'akpp45_admin_footer_text');

function akpp45_admin_styles() {
    ?>
    <style>
        #adminmenu .wp-menu-image.dashicons-before:before {
            color: #00ff88 !important;
        }
        #adminmenu li.menu-top:hover .wp-menu-image:before,
        #adminmenu a:focus .wp-menu-image:before,
        #adminmenu li.opensub .wp-menu-image:before,
        #adminmenu li.current .wp-menu-image:before {
            color: #fff !important;
        }
        .wrap h1 {
            border-left: 4px solid #00ff88;
            padding-left: 15px;
        }
    </style>
    <?php
}
add_action('admin_head', 'akpp45_admin_styles');

// =============================================================================
// 8. УВЕДОМЛЕНИЯ ОБ ОШИБКАХ (только для админов)
// =============================================================================

function akpp45_admin_notices() {
    if (!current_user_can('manage_options')) return;
    
    // Проверка наличия критических файлов
    $critical_files = [
        AKPP_CRM_DIR . '/class-akpp-crm.php',
        AKPP_CRM_DIR . '/class-akpp-ajax.php',
        AKPP_CRM_DIR . '/class-akpp-install.php',
    ];
    
    foreach ($critical_files as $file) {
        if (!file_exists($file)) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>АКПП CRM:</strong> Отсутствует критический файл: <code>' . basename($file) . '</code></p>';
            echo '</div>';
            break;
        }
    }
}
add_action('admin_notices', 'akpp45_admin_notices');

// =============================================================================
// 9. ДОПОЛНИТЕЛЬНЫЕ ФУНКЦИИ
// =============================================================================

/**
 * Получение настроек CRM
 */
function akpp_get_option($key, $default = '') {
    return get_option('akpp_' . $key, $default);
}

/**
 * Форматирование телефона
 */
function akpp_format_phone($phone) {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($clean) === 11) {
        return '+7 (' . substr($clean, 1, 3) . ') ' . substr($clean, 4, 3) . '-' . substr($clean, 7, 2) . '-' . substr($clean, 9, 2);
    }
    return $phone;
}

/**
 * Безопасный вывод
 */
function akpp_e($text) {
    echo esc_html($text);
}

/**
 * Логирование
 */
function akpp_log($message, $level = 'info') {
    error_log(sprintf('[AKPP45] [%s] %s', strtoupper($level), $message));
}


// =============================================================================
// CRON INTERVALS (должны быть доступны всегда, даже при cron запуске)
// =============================================================================
function akpp_add_cron_schedules($schedules) {
    $schedules['every_15_minutes'] = [
        'interval' => 900,
        'display'  => __('Каждые 15 минут', 'akpp-crm')
    ];
    $schedules['every_5_minutes'] = [
        'interval' => 300,
        'display'  => __('Каждые 5 минут', 'akpp-crm')
    ];
    return $schedules;
}
add_filter('cron_schedules', 'akpp_add_cron_schedules');
