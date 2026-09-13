<?php
/**
 * АКПП45 — безопасность: заголовки, скрытие версии, XML-RPC, лимит входа, rate limit AJAX
 */
if (!defined('ABSPATH')) exit;

// === 1. Security-заголовки (CSP добавим отдельно после проверки совместимости) ===
add_action('send_headers', function () {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    header('X-XSS-Protection: 1; mode=block');
});

// === 2. Скрыть версию WordPress ===
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');

// === 3. Отключить XML-RPC и ссылки на него ===
add_filter('xmlrpc_enabled', '__return_false');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');

// === 4. Запретить редактор файлов темы/плагинов из админки ===
if (!defined('DISALLOW_FILE_EDIT')) define('DISALLOW_FILE_EDIT', true);

// === 5. Обезличенные ошибки входа (не раскрывать, существует ли пользователь) ===
add_filter('login_errors', function () { return 'Неверные учётные данные. Попробуйте ещё раз.'; });

// === 6. Лимит попыток входа по IP (5 попыток / 15 минут) ===
function akpp_sec_ip() { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }

add_action('wp_login_failed', function ($username) {
    $key = 'akpp_lf_' . md5(akpp_sec_ip());
    set_transient($key, (int) get_transient($key) + 1, 15 * MINUTE_IN_SECONDS);
});

add_filter('authenticate', function ($user, $username, $password) {
    if (empty($username)) return $user;
    $key = 'akpp_lf_' . md5(akpp_sec_ip());
    if ((int) get_transient($key) >= 5) {
        return new WP_Error('akpp_too_many', '<strong>Слишком много попыток входа.</strong> Попробуйте через 15 минут.');
    }
    return $user;
}, 30, 3);

add_action('wp_login', function ($login, $user) {
    delete_transient('akpp_lf_' . md5(akpp_sec_ip()));
}, 10, 2);

// === 7. Rate limiting на публичные AJAX (флуд / перебор) ===
add_action('admin_init', function () {
    if (!wp_doing_ajax()) return;
    $action = sanitize_key($_REQUEST['action'] ?? '');
    $ip = akpp_sec_ip();

    // строгий лимит для действий авторизации
    if (in_array($action, ['akpp_client_login', 'akpp_client_register'], true)) {
        $key = 'akpp_rl_auth_' . md5($ip . $action);
        $cnt = (int) get_transient($key);
        if ($cnt >= 8) wp_send_json_error(['message' => 'Слишком много попыток. Подождите минуту.'], 429);
        set_transient($key, $cnt + 1, MINUTE_IN_SECONDS);
        return;
    }

    // общий лимит для гостей (не залогиненных)
    if (!is_user_logged_in()) {
        $key = 'akpp_rl_ajax_' . md5($ip);
        $cnt = (int) get_transient($key);
        if ($cnt >= 90) wp_send_json_error(['message' => 'Слишком много запросов. Подождите.'], 429);
        set_transient($key, $cnt + 1, MINUTE_IN_SECONDS);
    }
});

// === 8. Honeypot: скрытое поле-ловушка для ботов (человек не заполняет, бот — да) ===
add_action('admin_init', function () {
    if (!wp_doing_ajax()) return;
    if (!empty($_REQUEST['akpp_hp'])) { wp_send_json_success(['message' => 'OK']); }
}, 1);

// === 9. Валидация загрузок: только изображения, до 5 МБ ===
add_filter('wp_handle_upload_prefilter', function ($file) {
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed, true)) {
        $file['error'] = 'Разрешены только изображения (JPG, PNG, GIF, WEBP).';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $file['error'] = 'Файл слишком большой (максимум 5 МБ).';
    }
    return $file;
});
// === 10. Content-Security-Policy (мягкий; рубильник: опция akpp_csp_off=1 или константа AKPP_CSP_OFF) ===
add_action('send_headers', function () {
    if (headers_sent()) return;
    if ((defined('AKPP_CSP_OFF') && AKPP_CSP_OFF) || get_option('akpp_csp_off') === '1') return;
    $csp = "default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
         . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
         . "font-src 'self' https://fonts.gstatic.com data:; "
         . "img-src 'self' data: https: blob:; "
         . "connect-src 'self'; "
         . "frame-ancestors 'self'; "
         . "base-uri 'self'; "
         . "form-action 'self'; "
         . "upgrade-insecure-requests";
    header('Content-Security-Policy: ' . $csp);
}, 20);