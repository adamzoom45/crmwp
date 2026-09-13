<?php
/**
 * АКПП Курган - Theme Functions
 *
 * @package AKPP45
 * @version 5.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// КОНСТАНТЫ ТЕМЫ
// =============================================================================

define('AKPP_THEME_VERSION', '5.1.0');
define('AKPP_THEME_DIR', get_template_directory());
define('AKPP_THEME_URI', get_template_directory_uri());
define('AKPP_CRM_DIR', AKPP_THEME_DIR . '/inc/crm');
define('AKPP_CRM_URI', AKPP_THEME_URI . '/inc/crm');

// =============================================================================
// 1. ОБРАБОТКА ОШИБОК  [ИСПРАВЛЕНО: $e->getMessage() → $error['message']]
// =============================================================================

add_action('init', function() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        ini_set('error_log', AKPP_THEME_DIR . '/debug.log');
    }

    // Логирование фатальных ошибок (error_get_last возвращает МАССИВ, не exception!)
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            error_log(sprintf(
                '[AKPP45 FATAL] %s in %s on line %d',
                $error['message'],
                $error['file'] ?? 'unknown',
                $error['line'] ?? 0
            ));
        }
    });
}, 5);

// =============================================================================
// 2. THEME SETUP
// =============================================================================

function akpp45_theme_setup() {
    add_theme_support('automatic-feed-links');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', [
        'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script',
    ]);
    add_theme_support('custom-logo', [
        'height' => 100, 'width' => 300, 'flex-width' => true, 'flex-height' => true,
    ]);
    add_theme_support('responsive-embeds');
    add_theme_support('custom-background', ['default-color' => '0a0f1c']);

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
    $theme_uri  = AKPP_THEME_URI;
    $assets_uri = $theme_uri . '/assets';

    // Принудительно регистрируем jQuery
    if (!wp_script_is('jquery', 'registered')) {
        wp_register_script('jquery', includes_url('/js/jquery/jquery.js'), [], '3.7.1');
    }

    // === СТИЛИ ===
    wp_enqueue_style('akpp45-fonts',
        'https://fonts.googleapis.com/css2?family=Unbounded:wght@500;600;700;800&family=Inter:wght@300;400;500;600;700;800;900&display=swap',
        [], null
    );

    wp_enqueue_style('akpp45-style', get_stylesheet_uri(), ['akpp45-fonts'], AKPP_THEME_VERSION);

    // Admin CSS
    if (is_admin()) {
        wp_enqueue_style('akpp45-admin', $assets_uri . '/css/admin.css', [], AKPP_THEME_VERSION);
    } else {
        // Frontend CSS
        wp_enqueue_style('akpp45-frontend', $assets_uri . '/css/frontend.css', [], AKPP_THEME_VERSION);
        wp_enqueue_style('akpp45-modal', $assets_uri . '/css/modal.css', [], AKPP_THEME_VERSION);
    }

    // === СКРИПТЫ ===
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-core');

    // Frontend скрипты
    if (!is_admin()) {
        // Main.js
        wp_enqueue_script('akpp45-main', $assets_uri . '/js/main.js', ['jquery'], AKPP_THEME_VERSION, false);
        wp_localize_script('akpp45-main', 'akpp_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp45_nonce'),
            'home'     => home_url('/'),
        ]);

        // Booking.js
        wp_enqueue_script('akpp45-contact-protect', $assets_uri . '/js/contact-protect.js', [], AKPP_THEME_VERSION, true);
        if (file_exists(AKPP_THEME_DIR . '/assets/js/booking.js')) {
            wp_enqueue_script('akpp45-booking', $assets_uri . '/js/booking.js', ['jquery', 'akpp45-main'], AKPP_THEME_VERSION, false);
            wp_localize_script('akpp45-booking', 'akpp_frontend', [
                'ajax_url'     => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('akpp_booking_nonce'),
                'is_logged_in' => is_user_logged_in(),
                'home_url'     => home_url('/'),
            ]);
        }

        // Auth.js
        if (file_exists(AKPP_THEME_DIR . '/assets/js/auth.js')) {
            wp_enqueue_script('akpp45-auth', $assets_uri . '/js/auth.js', ['jquery'], AKPP_THEME_VERSION, false);
            wp_localize_script('akpp45-auth', 'akpp_auth_config', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('akpp_client_auth_nonce'),
            ]);
        }

        // Chat.js
        if (file_exists(AKPP_THEME_DIR . '/assets/js/chat.js')) {
            wp_enqueue_script('akpp45-chat', $assets_uri . '/js/chat.js', ['jquery'], AKPP_THEME_VERSION, false);
            wp_localize_script('akpp45-chat', 'akpp_chat_config', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('akpp_chat_action_nonce'),
            ]);
        }
    }

    // Admin скрипты
    if (is_admin()) {
        wp_enqueue_script('akpp45-admin-js', $assets_uri . '/js/admin.js', ['jquery'], AKPP_THEME_VERSION, true);
        wp_localize_script('akpp45-admin-js', 'akpp_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp45_nonce'),
        ]);

        wp_enqueue_script('akpp45-deal-calculator', $assets_uri . '/js/deal-calculator.js', ['jquery'], AKPP_THEME_VERSION, true);
        wp_localize_script('akpp45-deal-calculator', 'akpp_deal', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp45_nonce'),
        ]);

        wp_enqueue_script('akpp45-vin-decoder', $assets_uri . '/js/vin-decoder.js', ['jquery'], AKPP_THEME_VERSION, true);
        wp_localize_script('akpp45-vin-decoder', 'akpp_vin_decoder_config', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp45_nonce'),
        ]);
    }

    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
require_once get_template_directory() . '/inc/akpp-obf.php';
add_action('wp_enqueue_scripts', 'akpp45_enqueue_assets', 1);
add_action('admin_enqueue_scripts', 'akpp45_enqueue_assets', 1);

// =============================================================================
// 4. WIDGETS
// =============================================================================

function akpp45_widgets_init() {
    register_sidebar([
        'name'          => __('Боковая панель', 'akpp45'),
        'id'            => 'sidebar-1',
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ]);

    register_sidebar([
        'name'          => __('Подвал сайта', 'akpp45'),
        'id'            => 'footer-1',
        'before_widget' => '<div id="%1$s" class="footer-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="footer-widget-title">',
        'after_title'   => '</h3>',
    ]);
}
add_action('widgets_init', 'akpp45_widgets_init');

// =============================================================================
// 5. ШОРТКОДЫ  [ИСПРАВЛЕНО: защита от дублей через shortcode_exists]
// =============================================================================

if (!shortcode_exists('akpp_registration_form')) {
    add_shortcode('akpp_registration_form', function() {
        $file = AKPP_CRM_DIR . '/templates/frontend/registration.php';
        if (file_exists($file)) {
            ob_start();
            include $file;
            return ob_get_clean();
        }
        return '<p>Форма регистрации недоступна.</p>';
    });
}

if (!shortcode_exists('akpp_client_chat')) {
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
}

if (!shortcode_exists('akpp_contact_btn')) {
    add_shortcode('akpp_contact_btn', function($atts) {
        $atts = shortcode_atts([
            'text' => 'Связаться с нами',
            'url'  => '#contact',
        ], $atts);
        return '<a href="' . esc_url($atts['url']) . '" class="btn btn-primary btn-glow">' . esc_html($atts['text']) . '</a>';
    });
}

// =============================================================================
// 6. AJAX: ЗАЯВКА НА РЕМОНТ (С САЙТА)
// =============================================================================

function akpp_booking_request() {
    if (!isset($_POST['booking_nonce']) || !wp_verify_nonce($_POST['booking_nonce'], 'akpp_booking_nonce')) {
        wp_send_json_error(['message' => 'Ошибка безопасности. Обновите страницу.']);
    }

    $form_time = isset($_POST['form_time']) ? intval($_POST['form_time']) : 0;
    if ($form_time > 0 && (time() - $form_time) < 3) {
        wp_send_json_error(['message' => 'Слишком быстро. Вы бот?']);
    }

    if (!empty($_POST['website'])) {
        wp_send_json_error(['message' => 'Ошибка отправки']);
    }

    global $wpdb;

    $data = [
        'full_name' => sanitize_text_field($_POST['full_name'] ?? ''),
        'phone'     => sanitize_text_field($_POST['phone'] ?? ''),
        'city'      => sanitize_text_field($_POST['city'] ?? ''),
        'car_info'  => sanitize_text_field($_POST['car_info'] ?? ''),
        'car_year'  => intval($_POST['car_year'] ?? 0),
        'problem'   => sanitize_textarea_field($_POST['problem'] ?? ''),
    ];

    if (empty($data['full_name'])) {
        wp_send_json_error(['message' => 'Укажите ФИО']);
    }
    if (empty($data['phone']) || strlen(preg_replace('/[^0-9]/', '', $data['phone'])) < 10) {
        wp_send_json_error(['message' => 'Укажите корректный телефон']);
    }
    if (empty($data['car_info'])) {
        wp_send_json_error(['message' => 'Укажите марку и модель авто']);
    }
    if (empty($data['problem'])) {
        wp_send_json_error(['message' => 'Опишите проблему']);
    }

    $result = $wpdb->insert($wpdb->prefix . 'akpp_leads', [
        'client_name'  => $data['full_name'],
        'client_phone' => $data['phone'],
        'car_brand'    => $data['car_info'] . ($data['car_year'] ? ' ' . $data['car_year'] : ''),
        'problem'      => $data['problem'] . ($data['city'] ? "\n🏙 Город: " . $data['city'] : ''),
        'status'       => 'new',
        'source'       => 'site_booking',
            'client_id'    => is_user_logged_in() ? get_current_user_id() : null, /* client_email_bind */
            'client_email' => is_user_logged_in() ? wp_get_current_user()->user_email : '',
        'created_at'   => current_time('mysql'),
    ]);

    if (!$result) {
        wp_send_json_error(['message' => 'Ошибка сохранения заявки. Попробуйте позже.']);
    }

    $lead_id = $wpdb->insert_id;

    // Telegram
    $bot_token = get_option('akpp_telegram_bot_token', '');
    $chat_id   = get_option('akpp_telegram_chat_id', '');

    if ($bot_token && $chat_id) {
        $message  = "🔔 *НОВАЯ ЗАЯВКА С САЙТА* #{$lead_id}\n";
        $message .= "👤 *Клиент:* " . $data['full_name'] . "\n";
        $message .= "📞 *Телефон:* " . $data['phone'] . "\n";
        if ($data['city']) {
            $message .= "🏙 *Город:* " . $data['city'] . "\n";
        }
        $message .= "🚗 *Авто:* " . $data['car_info'];
        if ($data['car_year']) {
            $message .= " (" . $data['car_year'] . ")";
        }
        $message .= "\n";
        $message .= "🔧 *Проблема:* " . $data['problem'];

        wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
            'body'    => [
                'chat_id'    => $chat_id,
                'text'       => $message,
                'parse_mode' => 'Markdown',
            ],
            'timeout' => 5,
        ]);
    }

    // Email
    $admin_email = get_option('admin_email', 'adamzoom@bk.ru');
    $subject     = "🔧 Новая заявка на ремонт АКПП #{$lead_id}";
    $email_body  = "Поступила новая заявка с сайта akpp45.ru\n\n";
    $email_body .= "Клиент: {$data['full_name']}\n";
    $email_body .= "Телефон: {$data['phone']}\n";
    if ($data['city']) {
        $email_body .= "Город: {$data['city']}\n";
    }
    $email_body .= "Авто: {$data['car_info']}";
    if ($data['car_year']) {
        $email_body .= " ({$data['car_year']})";
    }
    $email_body .= "\nПроблема: {$data['problem']}\n";

    wp_mail($admin_email, $subject, $email_body);

    wp_send_json_success([
        'message' => '✅ Заявка принята! Свяжусь с вами в течение часа.',
        'lead_id' => $lead_id,
    ]);
}
add_action('wp_ajax_akpp_booking_request', 'akpp_booking_request');
add_action('wp_ajax_nopriv_akpp_booking_request', 'akpp_booking_request');

// =============================================================================
// 6.1. РЕГИСТРАЦИЯ / ВХОД / ВЫХОД КЛИЕНТА
// =============================================================================

function akpp_client_register() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'akpp_client_auth_nonce')) {
        wp_send_json_error(['message' => 'Ошибка безопасности']);
    }

    $full_name = sanitize_text_field($_POST['full_name'] ?? '');
    $phone     = sanitize_text_field($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = sanitize_text_field($_POST['role'] ?? 'repair');

    if (empty($full_name) || empty($phone) || empty($password)) {
        wp_send_json_error(['message' => 'Заполните все обязательные поля']);
    }

    if (strlen($password) < 6) {
        wp_send_json_error(['message' => 'Пароль должен быть не менее 6 символов']);
    }

    // Обязательное согласие с договором-офертой
    if (empty($_POST['agreement_accepted'])) {
        wp_send_json_error(['message' => 'Необходимо согласие с условиями договора-оферты']);
    }

    $existing_user = get_users([
        'meta_key'   => 'phone',
        'meta_value' => $phone,
        'number'     => 1,
    ]);

    if (!empty($existing_user)) {
        wp_send_json_error(['message' => 'Пользователь с таким телефоном уже зарегистрирован']);
    }

    $username = 'client_' . preg_replace('/[^0-9]/', '', $phone);
    $user_id  = wp_create_user($username, $password, $username . '@akpp45.local');

    if (is_wp_error($user_id)) {
        wp_send_json_error(['message' => 'Ошибка регистрации: ' . $user_id->get_error_message()]);
    }

    update_user_meta($user_id, 'phone', $phone);
    update_user_meta($user_id, 'full_name', $full_name);
    update_user_meta($user_id, 'car_info', ($role === 'repair') ? sanitize_text_field($_POST['car_info'] ?? '') : '');
    update_user_meta($user_id, 'client_role', $role);

    // Сохраняем согласие с офертой (юридический след)
    global $wpdb;
    $agr_table = $wpdb->prefix . 'akpp_agreements';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$agr_table}'") == $agr_table) {
        $wpdb->insert($agr_table, [
            'deal_id'           => null,
            'client_name'       => $full_name,
            'client_phone'      => $phone,
            'client_email'      => sanitize_email($_POST['email'] ?? ''),
            'agreement_version' => get_option('akpp_agreement_version', '1.0'),
            'source'            => 'registration',
            'ip_address'        => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent'        => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'accepted_at'       => current_time('mysql'),
            'created_at'        => current_time('mysql'),
        ]);
    }

    wp_set_auth_cookie($user_id, true);
    wp_set_current_user($user_id);

    wp_send_json_success([
        'message'  => '✅ Регистрация успешна! Добро пожаловать.',
        'redirect' => home_url('/'),
    ]);
}
add_action('wp_ajax_akpp_register_client', 'akpp_client_register');
add_action('wp_ajax_nopriv_akpp_register_client', 'akpp_client_register');

function akpp_client_login() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'akpp_client_auth_nonce')) {
        wp_send_json_error(['message' => 'Ошибка безопасности']);
    }

    $phone    = sanitize_text_field($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($phone) || empty($password)) {
        wp_send_json_error(['message' => 'Заполните все поля']);
    }

    $users = get_users([
        'meta_key'   => 'phone',
        'meta_value' => $phone,
        'number'     => 1,
    ]);

    if (empty($users)) {
        wp_send_json_error(['message' => 'Пользователь не найден']);
    }

    $user = $users[0];

    if (!wp_check_password($password, $user->user_pass, $user->ID)) {
        wp_send_json_error(['message' => 'Неверный пароль']);
    }

    wp_set_auth_cookie($user->ID, true);
    wp_set_current_user($user->ID);

    wp_send_json_success([
        'message'  => '✅ Вход выполнен!',
        'redirect' => home_url('/'),
    ]);
}
add_action('wp_ajax_akpp_login_client', 'akpp_client_login');
add_action('wp_ajax_nopriv_akpp_login_client', 'akpp_client_login');

function akpp_client_logout() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'akpp_client_auth_nonce')) {
        wp_send_json_error(['message' => 'Ошибка безопасности']);
    }

    wp_logout();

    wp_send_json_success([
        'message'  => '✅ Вы вышли из системы',
        'redirect' => home_url('/'),
    ]);
}
add_action('wp_ajax_akpp_logout_client', 'akpp_client_logout');

// =============================================================================
// 7. ОПТИМИЗАЦИЯ И БЕЗОПАСНОСТЬ
// =============================================================================

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

remove_action('wp_head', 'wp_generator');
add_filter('xmlrpc_enabled', '__return_false');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_shortlink_wp_head');

function akpp45_limit_login_attempts($username) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (empty($ip)) return;

    $key      = 'login_attempts_' . md5($ip);
    $attempts = (int) get_transient($key);

    if ($attempts >= 5) {
        wp_die('Слишком много попыток входа. Подождите 15 минут.', 'Ошибка входа', ['response' => 429]);
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
// 8. КАСТОМИЗАЦИЯ АДМИНКИ
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
// 9. УВЕДОМЛЕНИЯ ОБ ОШИБКАХ
// =============================================================================

function akpp45_admin_notices() {
    if (!current_user_can('manage_options')) return;

    $critical_files = [
        AKPP_CRM_DIR . '/class-akpp-crm.php',
        AKPP_CRM_DIR . '/ajax/class-ajax-base.php',
        AKPP_CRM_DIR . '/ajax/class-ajax-loader.php',
    ];

    foreach ($critical_files as $file) {
        if (!file_exists($file)) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>АКПП CRM:</strong> Отсутствует критический файл: <code>' . esc_html(basename($file)) . '</code></p>';
            echo '</div>';
            break;
        }
    }
}
add_action('admin_notices', 'akpp45_admin_notices');

// =============================================================================
// 10. ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// =============================================================================

function akpp_get_option($key, $default = '') {
    return get_option('akpp_' . $key, $default);
}

function akpp_format_phone($phone) {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($clean) === 11) {
        return '+7 (' . substr($clean, 1, 3) . ') ' . substr($clean, 4, 3) . '-' . substr($clean, 7, 2) . '-' . substr($clean, 9, 2);
    }
    return $phone;
}

function akpp_e($text) {
    echo esc_html($text);
}

function akpp_log($message, $level = 'info') {
    error_log(sprintf('[AKPP45] [%s] %s', strtoupper($level), $message));
}

// =============================================================================
// 11. CRON INTERVALS
// =============================================================================

function akpp_add_cron_schedules($schedules) {
    $schedules['every_15_minutes'] = [
        'interval' => 900,
        'display'  => __('Каждые 15 минут', 'akpp-crm'),
    ];
    $schedules['every_5_minutes'] = [
        'interval' => 300,
        'display'  => __('Каждые 5 минут', 'akpp-crm'),
    ];
    return $schedules;
}
add_filter('cron_schedules', 'akpp_add_cron_schedules');

// =============================================================================
// 12. СТРАНИЦА КАТЕГОРИЙ СКЛАДА
// =============================================================================

function akpp_register_part_categories_page() {
    add_submenu_page(
        'akpp-crm-dashboard',
        'Категории склада',
        '📂 Категории',
        'manage_options',
        'akpp-crm-part-categories',
        'akpp_render_part_categories_page'
    );
}
add_action('admin_menu', 'akpp_register_part_categories_page', 20);

function akpp_render_part_categories_page() {
    $file = AKPP_CRM_DIR . '/templates/part-categories.php';
    if (file_exists($file)) {
        require_once $file;
    } else {
        echo '<div class="notice notice-error"><p>❌ Файл part-categories.php не найден</p></div>';
    }
}

// =============================================================================
// 13. ИНТЕРНЕТ-МАГАЗИН
// =============================================================================

$shop_file = AKPP_CRM_DIR . '/class-akpp-shop.php';
if (file_exists($shop_file)) {
    require_once $shop_file;
}

function akpp45_enqueue_shop_assets() {
    if (is_admin()) {
        wp_enqueue_style('akpp45-shop-admin', AKPP_THEME_URI . '/assets/css/shop.css', [], '1.0.0');
    } else {
        wp_enqueue_style('akpp45-shop-frontend', AKPP_THEME_URI . '/assets/css/shop.css', [], '1.0.0');
        wp_enqueue_script('akpp45-shop-js', AKPP_THEME_URI . '/assets/js/shop.js', ['jquery'], '1.0.2', true);
        wp_localize_script('akpp45-shop-js', 'akpp_shop_ajax', [ /* shop-api-isolated */
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp45_nonce'),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'akpp45_enqueue_shop_assets');
add_action('admin_enqueue_scripts', 'akpp45_enqueue_shop_assets');

// =============================================================================
// 14. ПОДКЛЮЧЕНИЕ CRM  [ИСПРАВЛЕНО: принудительная инициализация через after_setup_theme]
// =============================================================================

$akpp_crm_file = AKPP_CRM_DIR . '/class-akpp-crm.php';
if (file_exists($akpp_crm_file)) {
    require_once $akpp_crm_file;

    // ВАЖНО: plugins_loaded уже прошёл к моменту загрузки темы,
    // поэтому хук внутри class-akpp-crm.php НЕ сработает.
    // Запускаем CRM вручную на after_setup_theme (Singleton защитит от дублей).
    add_action('after_setup_theme', function() {
        if (class_exists('AKPP_CRM')) {
            try {
                AKPP_CRM::get_instance();
            } catch (Throwable $e) {
                error_log('[AKPP45 CRM] Ошибка инициализации: ' . $e->getMessage());
            }
        }
    }, 5);
}

// АКПП45: настройки магазина (способы оплаты, доставка, контакты)
if (file_exists(get_template_directory() . '/inc/crm/class-akpp-shop-settings.php')) {
    require_once get_template_directory() . '/inc/crm/class-akpp-shop-settings.php';
}

/* АКПП45: пункт «Магазин» в назначенное меню WP (primary/mobile) + живой счётчик корзины */
add_filter('wp_nav_menu_items', function ($items, $args) {
    if (isset($args->theme_location) && in_array($args->theme_location, ['primary', 'mobile'], true)) {
        $active = is_page(['shop', 'cart', 'checkout']) ? ' current-menu-item' : '';
        $items .= '<li class="nav-shop-item' . $active . '"><a href="' . esc_url(home_url('/shop/')) . '"><span class="nav-shop-ico" aria-hidden="true">🛒</span>Магазин<span class="nav-cart-badge cart-count" data-empty="1">0</span></a></li>';
    }
    return $items;
}, 10, 2);
// АКПП45: SEO (title/description/OG/Schema.org)
if (file_exists(get_template_directory() . '/inc/akpp-seo.php')) {
    require_once get_template_directory() . '/inc/akpp-seo.php';
}

// АКПП45: безопасность (заголовки, лимит входа, rate limit AJAX)
if (file_exists(get_template_directory() . '/inc/akpp-security.php')) {
    require_once get_template_directory() . '/inc/akpp-security.php';
}

// АКПП45: интеграция с 1С (универсальный обмен XML)
if (file_exists(get_template_directory() . '/inc/crm/class-akpp-1c.php')) {
    require_once get_template_directory() . '/inc/crm/class-akpp-1c.php';
}

// АКПП45: лицензирование / аренда CRM (master + агент)
if (file_exists(get_template_directory() . '/inc/crm/class-akpp-license.php')) {
    require_once get_template_directory() . '/inc/crm/class-akpp-license.php';
}

// === Лендинг-шаблон: заявка с продающего сайта -> лид в CRM ===
add_action('wp_ajax_akpp_saas_lead', 'akpp_saas_lead_handler');
add_action('wp_ajax_nopriv_akpp_saas_lead', 'akpp_saas_lead_handler');
function akpp_saas_lead_handler() {
    check_ajax_referer('akpp_saas_nonce', 'nonce');
    if (!empty($_POST['akpp_hp'])) { wp_send_json_success(['message' => 'OK']); }
    global $wpdb;
    $name     = sanitize_text_field($_POST['name'] ?? '');
    $phone    = sanitize_text_field($_POST['phone'] ?? '');
    $email    = sanitize_email($_POST['email'] ?? '');
    $business = sanitize_text_field($_POST['business'] ?? '');
    $plan     = sanitize_text_field($_POST['plan'] ?? '');
    $message  = sanitize_textarea_field($_POST['message'] ?? '');
    if ($name === '' || $phone === '') wp_send_json_error(['message' => 'Заполните имя и телефон']);
    $problem = trim(($business !== '' ? "Бизнес: $business. " : '') . ($plan !== '' ? "Тариф: $plan. " : '') . $message);
    $now = current_time('mysql');
    $ins = $wpdb->insert($wpdb->prefix . 'akpp_leads', [
        'client_id' => 0, 'client_name' => $name, 'client_phone' => $phone, 'client_email' => $email,
        'car_brand' => '', 'problem' => $problem, 'guide_id' => 0, 'status' => 'new', 'client_agreed' => 0,
        'source' => 'saas_landing', 'avito_dialog_id' => 0, 'deal_id' => 0,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    if ($ins === false) {
        error_log('AKPP SaaS lead INSERT error: ' . $wpdb->last_error);
        wp_send_json_error(['message' => 'Не удалось сохранить заявку. Попробуйте ещё раз.']);
    }
    $lid = (int) $wpdb->insert_id;
    @wp_mail(get_option('admin_email'), 'Новая заявка с лендинга: ' . $name,
        "Имя: $name\nТелефон: $phone\nEmail: $email\nБизнес: $business\nТариф: $plan\nСообщение: $message");
    wp_send_json_success(['message' => 'Заявка отправлена. Мы свяжемся с вами в течение часа.', 'lead_id' => $lid]);
}
// === Шаблоны сайта (арендатор выбирает фронт в один клик) ===
function akpp_site_templates() {
    return [
        'impulse' => ['name' => 'Импульс', 'file' => 'template-saas-landing.php', 'has_shop' => true,  'tone' => 'dark',  'desc' => 'Энергичный лендинг с дашбордом, воронкой и тарифами. Полный комплект с магазином.'],
        'minimal' => ['name' => 'Минимал', 'file' => 'template-saas-minimal.php', 'has_shop' => false, 'tone' => 'light', 'desc' => 'Светлый и лёгкий. Фокус на заявке и простоте — без магазина.'],
        'vitrina' => ['name' => 'Витрина', 'file' => 'template-saas-vitrina.php', 'has_shop' => true, 'tone' => 'dark', 'desc' => 'Магазин + CRM: каталог товаров, корзина, заказы сразу становятся сделками.'],
        'conversion' => ['name' => 'Конверсия', 'file' => 'template-saas-conversion.php', 'has_shop' => false, 'tone' => 'dark', 'desc' => 'Одностраничник-воронка для максимального числа заявок. Без магазина.'],
        'corporate' => ['name' => 'Корпоративный', 'file' => 'template-saas-corporate.php', 'has_shop' => true, 'tone' => 'dark', 'desc' => 'Строгий деловой стиль для B2B: модули, отчётность, внедрение под компанию. С магазином.'],
    'light' => ['name' => 'Лайт', 'file' => 'template-saas-light.php', 'has_shop' => true, 'tone' => 'light', 'desc' => 'Светлая классика: белый фон, акцент на контент и магазин.'],
'clean' => ['name' => 'Чистый', 'file' => 'template-saas-clean.php', 'has_shop' => false, 'tone' => 'light', 'desc' => 'Чистый белый минимализм для услуг без магазина.'],
];
}
function akpp_current_site_template() {
    $pid = (int) get_option('page_on_front');
    $tpl = $pid ? get_post_meta($pid, '_wp_page_template', true) : '';
    foreach (akpp_site_templates() as $id => $t) if ($t['file'] === $tpl) return $id;
    return get_option('akpp_site_template', 'impulse');
}
function akpp_apply_site_template($id) {
    $tpls = akpp_site_templates(); if (!isset($tpls[$id])) return false;
    $pid = (int) get_option('page_on_front');
    if (!$pid || get_option('show_on_front') !== 'page') {
        $pid = wp_insert_post(['post_type' => 'page', 'post_title' => 'Главная', 'post_name' => 'home-landing', 'post_status' => 'publish']);
        update_option('show_on_front', 'page'); update_option('page_on_front', $pid);
    }
    update_post_meta($pid, '_wp_page_template', $tpls[$id]['file']);
    update_option('akpp_site_template', $id);
    update_option('akpp_shop_enabled', $tpls[$id]['has_shop'] ? 1 : 0);
    clean_post_cache($pid);
    wp_cache_delete('page_on_front', 'options');
    wp_cache_delete('show_on_front', 'options');
    wp_cache_delete('akpp_site_template', 'options');
    wp_cache_delete('akpp_shop_enabled', 'options');
    return true;
}
add_action('admin_menu', function () {
    add_menu_page('Шаблоны сайта', '🎨 Шаблоны сайта', 'manage_options', 'akpp-site-templates', function () {
        include get_template_directory() . '/inc/crm/templates/templates-admin.php';
    }, 'dashicons-layout', 37);
});
add_action('admin_post_akpp_apply_template', function () {
    if (!current_user_can('manage_options')) wp_die('Нет прав');
    check_admin_referer('akpp_apply_template');
    $id = sanitize_key($_POST['template'] ?? '');
    akpp_apply_site_template($id);
    wp_safe_redirect(admin_url('admin.php?page=akpp-site-templates&applied=' . $id)); exit;
});
// АКПП45: защита клиентского сайта (только при AKPP_TENANT_MODE)
if (file_exists(get_template_directory() . '/inc/crm/class-akpp-tenant-guard.php')) {
    require_once get_template_directory() . '/inc/crm/class-akpp-tenant-guard.php';
}

// === Версия БЕЗ магазина: при akpp_shop_enabled=0 магазин недоступен на фронте ===
add_action('template_redirect', function () {
    if (get_option('akpp_shop_enabled', 1)) return;
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (preg_match('#^/(shop|checkout|cart|order)(/|$)#', $path) || is_page(['shop', 'checkout', 'cart']) || is_page_template(['shop-frontend.php', 'inc/crm/templates/shop-frontend.php'])) {
        wp_safe_redirect(home_url('/'), 302); exit;
    }
}, 1);
add_action('admin_init', function () {
    if (!wp_doing_ajax() || get_option('akpp_shop_enabled', 1)) return;
    $a = sanitize_key($_REQUEST['action'] ?? '');
    $blocked = ['akpp_shop_add_to_cart', 'akpp_shop_checkout', 'akpp_shop_get_cart', 'akpp_shop_update_cart', 'akpp_shop_remove_from_cart', 'akpp_shop_confirm_payment', 'akpp_shop_get_products', 'akpp_shop_send_order_message'];
    if (in_array($a, $blocked, true)) wp_send_json_error(['message' => 'Магазин отключён в этом тарифе'], 403);
}, 0);
// === Версия БЕЗ магазина: скрыть кнопки/счётчик магазина на фронте при akpp_shop_enabled=0 ===
add_action('wp_head', function () {
    if (get_option('akpp_shop_enabled', 1)) return;
    echo '<style id="akpp-shop-off">a[href*="/shop"],a[href*="/checkout"],a[href*="/cart"],.nav-shop-item,.nav-cart-badge,.header-cart,.btn-cart,.shop-cart-wrap{display:none!important}</style>' . "\n";
}, 99);
// === Реквизиты оферты: self-service экран + сохранение опций ===
add_action('admin_menu', function () {
    add_submenu_page('akpp-crm-deals', 'Реквизиты оферты', '📄 Реквизиты оферты', 'manage_options', 'akpp-crm-requisites', function () {
        include get_template_directory() . '/inc/crm/templates/agreement-requisites.php';
    });
}, 99);
add_action('admin_post_akpp_save_requisites', function () {
    if (!current_user_can('manage_options')) wp_die('Недостаточно прав');
    check_admin_referer('akpp_save_requisites');
    $fields = ['akpp_company_name','akpp_company_inn','akpp_company_status','akpp_company_address','akpp_company_city','akpp_company_phone','akpp_company_email','akpp_company_site','akpp_company_service_subject','akpp_agreement_version','akpp_avito_ad_url','akpp_avito_ad_slug','akpp_avito_ad_id'];
    foreach ($fields as $f) if (isset($_POST[$f])) update_option($f, sanitize_text_field(wp_unslash($_POST[$f])));
    wp_safe_redirect(admin_url('admin.php?page=akpp-crm-requisites&saved=1')); exit;
});if (file_exists(get_template_directory() . '/inc/crm/class-akpp-updater.php')) { require_once get_template_directory() . '/inc/crm/class-akpp-updater.php'; } // akpp-updater-require
if (file_exists(get_template_directory() . '/inc/akpp-tpl-data.php')) { require_once get_template_directory() . '/inc/akpp-tpl-data.php'; } // akpp-tpl-data-require
