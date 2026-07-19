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
// 1. ПОДКЛЮЧЕНИЕ CRM СИСТЕМЫ
// =============================================================================
function akpp_require_file($file) {
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    error_log('[AKPP45] Файл не найден: ' . $file);
    return false;
}

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
    add_theme_support('automatic-feed-links');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', [
        'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script',
    ]);
    add_theme_support('custom-logo', [
        'height'      => 100,
        'width'       => 300,
        'flex-width'  => true,
        'flex-height' => true,
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
    $theme_uri = AKPP_THEME_URI;
    $assets_uri = $theme_uri . '/assets';
    
    // 🔴 КРИТИЧНО: Принудительно регистрируем jQuery
    if (!wp_script_is('jquery', 'registered')) {
        wp_register_script('jquery', includes_url('/js/jquery/jquery.js'), [], '3.7.1');
    }
    
    // === СТИЛИ ===
    
    // Google Fonts
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
    
    // Admin CSS
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
    
    // 🔴 ПРИНУДИТЕЛЬНО загружаем jQuery
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-core');
    
    // Frontend скрипты
    if (!is_admin()) {
        
        // Main.js - ОСНОВНОЙ файл (загружается ПЕРВЫМ после jQuery)
        wp_enqueue_script(
            'akpp45-main',
            $assets_uri . '/js/main.js',
            ['jquery'],  // ← Зависимость от jQuery
            AKPP_THEME_VERSION,
            false  // ← false = загрузка в HEADER (до контента)
        );
        
        // Локализация для AJAX
        wp_localize_script('akpp45-main', 'akpp_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp45_nonce'),
            'home'     => home_url('/'),
        ]);
        
        // Booking.js
        if (file_exists(AKPP_THEME_DIR . '/assets/js/booking.js')) {
            wp_enqueue_script(
                'akpp45-booking',
                $assets_uri . '/js/booking.js',
                ['jquery', 'akpp45-main'],  // ← Зависит от main.js
                AKPP_THEME_VERSION,
                false  // ← false = в HEADER
            );
            
            wp_localize_script('akpp45-booking', 'akpp_frontend', [
                'ajax_url'     => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('akpp_booking_nonce'),
                'is_logged_in' => is_user_logged_in(),
                'home_url'     => home_url('/'),
            ]);
        }
        
        // Auth.js
        if (file_exists(AKPP_THEME_DIR . '/assets/js/auth.js')) {
            wp_enqueue_script(
                'akpp45-auth',
                $assets_uri . '/js/auth.js',
                ['jquery'],
                AKPP_THEME_VERSION,
                false  // ← false = в HEADER
            );
            
            wp_localize_script('akpp45-auth', 'akpp_auth_config', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('akpp_client_auth_nonce'),
            ]);
        }
        
        // Chat.js
        if (file_exists(AKPP_THEME_DIR . '/assets/js/chat.js')) {
            wp_enqueue_script(
                'akpp45-chat',
                $assets_uri . '/js/chat.js',
                ['jquery'],
                AKPP_THEME_VERSION,
                false
            );
            
            wp_localize_script('akpp45-chat', 'akpp_chat_config', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('akpp_chat_action_nonce'),
            ]);
        }
    }
    
    // Admin скрипты
    if (is_admin()) {
     
        wp_localize_script('akpp45-admin-js', 'akpp_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp45_nonce'),
        ]);
        
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
    
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
add_action('wp_enqueue_scripts', 'akpp45_enqueue_assets', 1);  // ← Приоритет 1 (раньше всех)
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
// 5. ШОРТКОДЫ
// =============================================================================
add_shortcode('akpp_registration_form', function() {
    $file = AKPP_CRM_DIR . '/templates/frontend/registration.php';
    if (file_exists($file)) {
        ob_start();
        include $file;
        return ob_get_clean();
    }
    return '<p>Форма регистрации недоступна.</p>';
});

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

add_shortcode('akpp_contact_btn', function($atts) {
    $atts = shortcode_atts([
        'text' => 'Связаться с нами',
        'url'  => '#contact',
    ], $atts);
    
    return '<a href="' . esc_url($atts['url']) . '" class="btn btn-primary btn-glow">' . esc_html($atts['text']) . '</a>';
});

// =============================================================================
// 6. AJAX: ЗАЯВКА НА РЕМОНТ (С САЙТА)
// =============================================================================

/**
 * Обработка заявки с главной страницы
 */
function akpp_booking_request() {
    // Проверка nonce
    if (!isset($_POST['booking_nonce']) || !wp_verify_nonce($_POST['booking_nonce'], 'akpp_booking_nonce')) {
        wp_send_json_error(['message' => 'Ошибка безопасности. Обновите страницу.']);
    }
    
    // Защита от спама - проверка времени заполнения формы (боты заполняют мгновенно)
    $form_time = isset($_POST['form_time']) ? intval($_POST['form_time']) : 0;
    if ($form_time > 0 && (time() - $form_time) < 3) {
        wp_send_json_error(['message' => 'Слишком быстро. Вы бот?']);
    }
    
    // Honeypot - если заполнено скрытое поле, это бот
    if (!empty($_POST['website'])) {
        wp_send_json_error(['message' => 'Ошибка отправки']);
    }
    
    global $wpdb;
    
    // Сбор данных
    $data = [
        'full_name' => sanitize_text_field($_POST['full_name'] ?? ''),
        'phone'     => sanitize_text_field($_POST['phone'] ?? ''),
        'city'      => sanitize_text_field($_POST['city'] ?? ''),
        'car_info'  => sanitize_text_field($_POST['car_info'] ?? ''),
        'car_year'  => intval($_POST['car_year'] ?? 0),
        'problem'   => sanitize_textarea_field($_POST['problem'] ?? ''),
    ];
    
    // Валидация
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
    
    // Сохранение в таблицу leads
    $result = $wpdb->insert($wpdb->prefix . 'akpp_leads', [
        'client_name'  => $data['full_name'],
        'client_phone' => $data['phone'],
        'car_brand'    => $data['car_info'] . ($data['car_year'] ? ' ' . $data['car_year'] : ''),
        'problem'      => $data['problem'] . ($data['city'] ? "\n\n🏙 Город: " . $data['city'] : ''),
        'status'       => 'new',
        'source'       => 'site_booking',
        'created_at'   => current_time('mysql'),
    ]);
    
    if (!$result) {
        wp_send_json_error(['message' => 'Ошибка сохранения заявки. Попробуйте позже.']);
    }
    
    $lead_id = $wpdb->insert_id;
    
    // Отправка уведомления в Telegram
    $bot_token = get_option('akpp_telegram_bot_token', '');
    $chat_id   = get_option('akpp_telegram_chat_id', '');
    
    if ($bot_token && $chat_id) {
        $message  = "🔔 *НОВАЯ ЗАЯВКА С САЙТА* #{$lead_id}\n\n";
        $message .= "👤 *Клиент:* " . $data['full_name'] . "\n";
        $message .= "📞 *Телефон:* " . $data['phone'] . "\n";
        if ($data['city']) {
            $message .= "🏙 *Город:* " . $data['city'] . "\n";
        }
        $message .= "🚗 *Авто:* " . $data['car_info'];
        if ($data['car_year']) {
            $message .= " (" . $data['car_year'] . ")";
        }
        $message .= "\n\n";
        $message .= "🔧 *Проблема:*\n" . $data['problem'];
        
        wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
            'body' => [
                'chat_id'    => $chat_id,
                'text'       => $message,
                'parse_mode' => 'Markdown',
            ],
            'timeout' => 5,
        ]);
    }
    
    // Email уведомление (если настроено)
    $admin_email = get_option('admin_email', 'adamzoom@bk.ru');
    $subject = "🔧 Новая заявка на ремонт АКПП #{$lead_id}";
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
    $email_body .= "\n\nПроблема:\n{$data['problem']}\n";
    
    wp_mail($admin_email, $subject, $email_body);
    
    wp_send_json_success([
        'message' => '✅ Заявка принята! Свяжусь с вами в течение часа.',
        'lead_id' => $lead_id,
    ]);
}
add_action('wp_ajax_akpp_booking_request', 'akpp_booking_request');
add_action('wp_ajax_nopriv_akpp_booking_request', 'akpp_booking_request');

/**
 * Регистрация нового пользователя (клиент)
 */
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
    
    // Проверка существует ли пользователь с таким телефоном
    $existing_user = get_users([
        'meta_key'   => 'phone',
        'meta_value' => $phone,
        'number'     => 1,
    ]);
    
    if (!empty($existing_user)) {
        wp_send_json_error(['message' => 'Пользователь с таким телефоном уже зарегистрирован']);
    }
    
    // Создаём пользователя WordPress
    $username = 'client_' . preg_replace('/[^0-9]/', '', $phone);
    $user_id  = wp_create_user($username, $password, $username . '@akpp45.local');
    
    if (is_wp_error($user_id)) {
        wp_send_json_error(['message' => 'Ошибка регистрации: ' . $user_id->get_error_message()]);
    }
    
    // Сохраняем мета-данные
    update_user_meta($user_id, 'full_name', $full_name);
    update_user_meta($user_id, 'phone', $phone);
    update_user_meta($user_id, 'client_role', $role);
    
    if ($role === 'repair') {
        update_user_meta($user_id, 'car_info', sanitize_text_field($_POST['car_info'] ?? ''));
        update_user_meta($user_id, 'car_year', intval($_POST['car_year'] ?? 0));
        update_user_meta($user_id, 'problem', sanitize_textarea_field($_POST['problem'] ?? ''));
    } else {
        update_user_meta($user_id, 'city', sanitize_text_field($_POST['city'] ?? ''));
    }
    
    // Сохраняем в таблицу site_users
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'akpp_site_users', [
        'wp_user_id'    => $user_id,
        'full_name'     => $full_name,
        'phone'         => $phone,
        'car_info'      => ($role === 'repair') ? sanitize_text_field($_POST['car_info'] ?? '') : '',
        'status'        => 'active',
        'registered_at' => current_time('mysql'),
    ]);
    
    // Автоматический вход
    wp_set_auth_cookie($user_id, true);
    wp_set_current_user($user_id);
    
    wp_send_json_success([
        'message' => '✅ Регистрация успешна! Добро пожаловать.',
        'redirect' => home_url('/'),
    ]);
}
add_action('wp_ajax_akpp_register_client', 'akpp_register_client');
add_action('wp_ajax_nopriv_akpp_register_client', 'akpp_register_client');

/**
 * Вход клиента
 */
function akpp_client_login() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'akpp_client_auth_nonce')) {
        wp_send_json_error(['message' => 'Ошибка безопасности']);
    }
    
    $phone    = sanitize_text_field($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($phone) || empty($password)) {
        wp_send_json_error(['message' => 'Заполните все поля']);
    }
    
    // Ищем пользователя по телефону
    $users = get_users([
        'meta_key'   => 'phone',
        'meta_value' => $phone,
        'number'     => 1,
    ]);
    
    if (empty($users)) {
        wp_send_json_error(['message' => 'Пользователь не найден']);
    }
    
    $user = $users[0];
    
    // Проверяем пароль
    if (!wp_check_password($password, $user->user_pass, $user->ID)) {
        wp_send_json_error(['message' => 'Неверный пароль']);
    }
    
    // Авторизуем
    wp_set_auth_cookie($user->ID, true);
    wp_set_current_user($user->ID);
    
    wp_send_json_success([
        'message'  => '✅ Вход выполнен!',
        'redirect' => home_url('/'),
    ]);
}
add_action('wp_ajax_akpp_login_client', 'akpp_login_client');
add_action('wp_ajax_nopriv_akpp_login_client', 'akpp_login_client');

/**
 * Выход клиента
 */
function akpp_client_logout() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'akpp_client_auth_nonce')) {
        wp_send_json_error(['message' => 'Ошибка безопасности']);
    }
    
    wp_logout();
    wp_send_json_success([
        'message'  => 'Вы вышли из аккаунта',
        'redirect' => home_url('/'),
    ]);
}
add_action('wp_ajax_akpp_logout_client', 'akpp_client_logout');

// =============================================================================
// 7. ОПТИМИЗАЦИЯ И БЕЗОПАСНОСТЬ
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
        AKPP_CRM_DIR . '/class-akpp-install.php',
        AKPP_CRM_DIR . '/ajax/class-ajax-base.php',  // ✅ Новые модули
        AKPP_CRM_DIR . '/ajax/class-ajax-loader.php',
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
        'display'  => __('Каждые 15 минут', 'akpp-crm')
    ];
    $schedules['every_5_minutes'] = [
        'interval' => 300,
        'display'  => __('Каждые 5 минут', 'akpp-crm')
    ];
    return $schedules;
}
add_filter('cron_schedules', 'akpp_add_cron_schedules');

// =============================================================================
// 12. РЕГИСТРАЦИЯ СТРАНИЦЫ КАТЕГОРИЙ СКЛАДА
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
// 12. ИНТЕРНЕТ-МАГАЗИН
// =============================================================================
$shop_file = AKPP_CRM_DIR . '/class-akpp-shop.php';
if (file_exists($shop_file)) {
    require_once $shop_file;
}

// Регистрация страницы магазина в админке
//add_action('admin_menu', function() {
//    add_submenu_page(
//        'akpp-crm-dashboard',
//        'Магазин',
//        ' Магазин',
//        'manage_options',
//        'akpp-crm-shop',
//        function() {
//            if (class_exists('AKPP_Shop')) {
//                require_once AKPP_CRM_DIR . '/templates/shop-admin.php';
//            } else {
//                echo '<div class="notice notice-error"><p>❌ Класс магазина не найден</p></div>';
//            }
//        }
//    );
//}, 20);//
// Подключение стилей и скриптов магазина
function akpp45_enqueue_shop_assets() {
    if (is_admin()) {
        wp_enqueue_style(
            'akpp45-shop-admin',
            AKPP_THEME_URI . '/assets/css/shop.css',
            [],
            '1.0.0'
        );
    } else {
        wp_enqueue_style(
            'akpp45-shop-frontend',
            AKPP_THEME_URI . '/assets/css/shop.css',
            [],
            '1.0.0'
        );
        
        wp_enqueue_script(
            'akpp45-shop-js',
            AKPP_THEME_URI . '/assets/js/shop.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        wp_localize_script('akpp45-shop-js', 'akpp_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('akpp_shop_nonce'),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'akpp45_enqueue_shop_assets');
add_action('admin_enqueue_scripts', 'akpp45_enqueue_shop_assets');