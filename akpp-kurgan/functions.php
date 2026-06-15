<?php
/**
 * Функции темы АКПП45
 * 
 * @package AKPP45
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================================
// 1. НАСТРОЙКИ ТЕМЫ
// ============================================================

/**
 * Инициализация темы
 */
function akpp45_theme_setup() {
    // Поддержка заголовков
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    
    // Регистрация меню
    register_nav_menus([
        'primary' => 'Главное меню',
        'footer' => 'Меню в подвале',
        'mobile' => 'Мобильное меню'
    ]);
    
    // Поддержка HTML5
    add_theme_support('html5', [
        'search-form', 'comment-form', 'comment-list', 'gallery', 'caption'
    ]);
}
add_action('after_setup_theme', 'akpp45_theme_setup');

// ============================================================
// 2. ПОДКЛЮЧЕНИЕ CRM СИСТЕМЫ
// ============================================================

/**
 * Загрузка CRM системы
 */
require_once get_template_directory() . '/inc/crm/class-akpp-crm.php';

/**
 * Инициализация CRM
 */
function akpp45_init_crm() {
    if (class_exists('AKPP_CRM')) {
        AKPP_CRM::get_instance();
    }
}
add_action('init', 'akpp45_init_crm');

// ============================================================
// 3. ПОДКЛЮЧЕНИЕ СТИЛЕЙ И СКРИПТОВ
// ============================================================

/**
 * Подключение ассетов темы
 */
function akpp45_enqueue_scripts() {
    // Стили темы
    wp_enqueue_style('akpp45-style', get_stylesheet_uri(), [], '1.0.0');
    
    // Дополнительные стили
    wp_enqueue_style('akpp45-main', get_template_directory_uri() . '/assets/css/main.css', [], '1.0.0');
    
    // Скрипты темы
    wp_enqueue_script('akpp45-main', get_template_directory_uri() . '/assets/js/main.js', ['jquery'], '1.0.0', true);
    
    // Локализация для AJAX
    wp_localize_script('akpp45-main', 'akpp45_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('akpp45_ajax_nonce')
    ]);
}
add_action('wp_enqueue_scripts', 'akpp45_enqueue_scripts');

// ============================================================
// 4. РЕГИСТРАЦИЯ СТРАНИЦ ФРОНТЕНДА
// ============================================================

/**
 * Создание страниц при активации темы
 */
function akpp45_create_pages() {
    $pages = [
        'crm-login' => 'Вход в CRM',
        'crm-register' => 'Регистрация CRM',
        'crm-profile' => 'Личный кабинет',
        'crm-chat' => 'Чат поддержки'
    ];
    
    foreach ($pages as $slug => $title) {
        $page = get_page_by_path($slug);
        
        if (!$page) {
            wp_insert_post([
                'post_title' => $title,
                'post_name' => $slug,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_content' => '<!-- CRM страница -->'
            ]);
        }
    }
}
add_action('after_switch_theme', 'akpp45_create_pages');

// ============================================================
// 5. ДОПОЛНИТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================

/**
 * Кастомные типы записей (при необходимости)
 */
function akpp45_custom_post_types() {
    // Здесь можно добавить CPT
}
add_action('init', 'akpp45_custom_post_types');

/**
 * Короткие коды
 */
function akpp45_shortcodes() {
    add_shortcode('crm_login_form', function() {
        if (!AKPP_Auth::is_logged_in()) {
            include get_template_directory() . '/inc/crm/templates/frontend/login.php';
        } else {
            wp_redirect(home_url('/crm-profile'));
            exit;
        }
    });
    
    add_shortcode('crm_register_form', function() {
        if (!AKPP_Auth::is_logged_in()) {
            include get_template_directory() . '/inc/crm/templates/frontend/register.php';
        } else {
            wp_redirect(home_url('/crm-profile'));
            exit;
        }
    });
}
add_action('init', 'akpp45_shortcodes');

// ============================================================
// 6. БЕЗОПАСНОСТЬ
// ============================================================

/**
 * Отключение вывода версии WordPress
 */
remove_action('wp_head', 'wp_generator');

/**
 * Защита от XSS
 */
function akpp45_security_headers() {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
}
add_action('send_headers', 'akpp45_security_headers');

// ============================================================
// 7. ОПТИМИЗАЦИЯ
// ============================================================

/**
 * Отключение эмодзи
 */
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

/**
 * Отключение oEmbed
 */
remove_action('wp_head', 'wp_oembed_add_discovery_links');
remove_action('wp_head', 'wp_oembed_add_host_js');

// ============================================================
// 8. ПЕРЕХВАТ НЕАВТОРИЗОВАННЫХ ПОЛЬЗОВАТЕЛЕЙ
// ============================================================

/**
 * Перенаправление неавторизованных пользователей на страницу входа
 */
function akpp45_check_crm_access() {
    if (is_page(['crm-profile', 'crm-chat'])) {
        if (!AKPP_Auth::is_logged_in()) {
            wp_redirect(home_url('/crm-login'));
            exit;
        }
    }
}
add_action('wp', 'akpp45_check_crm_access');

// ============================================================
// 9. СТАТИСТИКА ДЛЯ ПАНЕЛИ АДМИНИСТРАТОРА
// ============================================================

/**
 * Добавление виджета статистики в админку
 */
function akpp45_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'akpp45_stats_widget',
        '📊 Статистика АКПП45 CRM',
        'akpp45_render_dashboard_widget'
    );
}
add_action('wp_dashboard_setup', 'akpp45_add_dashboard_widget');

function akpp45_render_dashboard_widget() {
    global $wpdb;
    
    $deals_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_deals WHERE status = 'in_work'");
    $leads_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_leads WHERE status = 'new'");
    $parts_low = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_parts WHERE quantity < 5");
    
    echo '<ul style="margin: 0; padding: 0; list-style: none;">';
    echo '<li style="margin-bottom: 10px;">🟢 Активных сделок: <strong>' . $deals_count . '</strong></li>';
    echo '<li style="margin-bottom: 10px;">🟡 Новых лидов: <strong>' . $leads_count . '</strong></li>';
    echo '<li style="margin-bottom: 10px;">⚠️ Запчастей мало: <strong>' . $parts_low . '</strong></li>';
    echo '</ul>';
}

// ============================================================
// 10. ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ДЛЯ ШАБЛОНОВ
// ============================================================

/**
 * Форматирование даты
 */
function akpp45_format_date($date, $format = 'd.m.Y H:i') {
    return date_i18n($format, strtotime($date));
}

/**
 * Форматирование цены
 */
function akpp45_format_price($price) {
    return number_format($price, 0, ',', ' ') . ' ₽';
}

/**
 * Получение статуса сделки в виде HTML
 */
function akpp45_get_status_badge($status) {
    $statuses = [
        'new' => ['label' => '🆕 Новая', 'class' => 'status-new'],
        'diagnostic' => ['label' => '🔧 Диагностика', 'class' => 'status-diagnostic'],
        'in_work' => ['label' => '⚙️ В работе', 'class' => 'status-in_work'],
        'completed' => ['label' => '✅ Выполнена', 'class' => 'status-completed'],
        'rejected' => ['label' => '❌ Отклонена', 'class' => 'status-rejected']
    ];
    
    $status = $statuses[$status] ?? ['label' => $status, 'class' => ''];
    
    return sprintf('<span class="status-badge %s">%s</span>', $status['class'], $status['label']);
}

/**
 * Получение текущего пользователя CRM
 */
function akpp45_get_current_crm_user() {
    if (AKPP_Auth::is_logged_in()) {
        return AKPP_Auth::get_current_user();
    }
    return null;
}

// ============================================================
// 11. ОБРАБОТЧИКИ ОШИБОК
// ============================================================

/**
 * Кастомная страница 404
 */
function akpp45_custom_404() {
    if (is_404()) {
        status_header(404);
        get_template_part('404');
        exit;
    }
}
add_action('template_redirect', 'akpp45_custom_404');

// ============================================================
// 12. ОЧИСТКА ВРЕМЕННЫХ ДАННЫХ
// ============================================================

/**
 * Очистка временных файлов
 */
function akpp45_cleanup_temp_files() {
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/akpp45_temp/';
    
    if (file_exists($temp_dir)) {
        $files = glob($temp_dir . '*');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < (time() - 86400)) {
                unlink($file);
            }
        }
    }
}
add_action('wp_daily', 'akpp45_cleanup_temp_files');

// Конец файла functions.php
