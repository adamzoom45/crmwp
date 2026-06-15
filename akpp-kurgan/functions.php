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
// 0. КОМПЛЕКСНОЕ ИСПРАВЛЕНИЕ ОШИБОК CRM
// ============================================================

// 1. Определяем недостающую функцию (для header.php)
if (!function_exists('akpp_get_option')) {
    function akpp_get_option($key, $default = '') {
        return get_option($key, $default);
    }
}

// 2. Добавляем недостающие колонки в таблицы при загрузке
add_action('init', function() {
    global $wpdb;
    
    // Проверяем, существуют ли таблицы
    $table_employees = $wpdb->prefix . 'akpp_employees';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_employees}'");
    
    if ($table_exists) {
        // Добавляем колонку status в employees (если нет)
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_employees} LIKE 'status'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_employees} ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
        }
        
        // Добавляем колонку full_name в employees (если нет)
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_employees} LIKE 'full_name'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_employees} ADD COLUMN full_name VARCHAR(100) AFTER name");
            $wpdb->query("UPDATE {$table_employees} SET full_name = name");
        }
    }
    
    $table_site_users = $wpdb->prefix . 'akpp_site_users';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_site_users}'")) {
        // Добавляем колонку wp_user_id в site_users (если нет)
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_site_users} LIKE 'wp_user_id'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_site_users} ADD COLUMN wp_user_id BIGINT(20) DEFAULT NULL");
        }
    }
    
    $table_vehicles = $wpdb->prefix . 'akpp_vehicles';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_vehicles}'")) {
        // Добавляем колонку brand в vehicles (для обратной совместимости)
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_vehicles} LIKE 'brand'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_vehicles} ADD COLUMN brand VARCHAR(100) DEFAULT ''");
            $wpdb->query("UPDATE {$table_vehicles} SET brand = make");
        }
    }
});

// 3. Перехватываем и исправляем проблемные запросы
add_filter('query', function($query) {
    global $wpdb;
    
    // Исправляем запрос в leads.php
    if (strpos($query, "SELECT id, full_name FROM wp_akpp_employees WHERE status = 'active'") !== false) {
        $query = str_replace("status = 'active'", "is_active = 1", $query);
        $query = str_replace("full_name", "name as full_name", $query);
    }
    
    // Исправляем запрос в deal-form.php
    if (strpos($query, "SELECT id, name, percent FROM wp_akpp_employees WHERE status = 'active'") !== false) {
        $query = str_replace("status = 'active'", "is_active = 1", $query);
    }
    
    // Исправляем запрос в users.php
    if (strpos($query, "LEFT JOIN wp_users u ON su.wp_user_id = u.ID") !== false) {
        $query = "SELECT su.* FROM {$wpdb->prefix}akpp_site_users su ORDER BY su.id DESC";
    }
    
    // Исправляем запрос в dashboard.php
    if (strpos($query, "v.brand") !== false && strpos($query, "akpp_deals d") !== false) {
        $query = str_replace("v.brand", "v.make", $query);
        $query = str_replace("e.full_name", "e.name as employee_name", $query);
    }
    
    return $query;
});

// 4. Регистрация интервалов Cron
add_filter('cron_schedules', function($schedules) {
    $schedules['every_five_minutes'] = [
        'interval' => 300,
        'display' => 'Каждые 5 минут'
    ];
    return $schedules;
});

// 5. Отключение проблемных Cron задач временно
add_action('init', function() {
    wp_clear_scheduled_hook('akpp_sync_avito_messages');
    wp_clear_scheduled_hook('akpp_sync_avito_dialogs');
});

// ============================================================
// 1. ПОДКЛЮЧЕНИЕ CRM СИСТЕМЫ
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
add_action('after_setup_theme', 'akpp45_init_crm');

// ============================================================
// 2. НАСТРОЙКИ ТЕМЫ
// ============================================================

/**
 * Инициализация темы
 */
function akpp45_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', [
        'height' => 100,
        'width' => 400,
        'flex-height' => true,
        'flex-width' => true,
    ]);
    
    register_nav_menus([
        'primary' => 'Главное меню',
        'footer' => 'Меню в подвале',
        'mobile' => 'Мобильное меню'
    ]);
    
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script'
    ]);
}
add_action('after_setup_theme', 'akpp45_theme_setup');

// ============================================================
// 3. ПОДКЛЮЧЕНИЕ СТИЛЕЙ И СКРИПТОВ
// ============================================================

/**
 * Подключение ассетов темы
 */
function akpp45_enqueue_scripts() {
    wp_enqueue_style('akpp45-style', get_stylesheet_uri(), [], '1.0.0');
    wp_enqueue_style('akpp45-main', get_template_directory_uri() . '/assets/css/main.css', [], '1.0.0');
    wp_enqueue_style('akpp-modal', get_template_directory_uri() . '/assets/css/modal.css', [], '1.0.0');
    
    wp_enqueue_script('akpp45-main', get_template_directory_uri() . '/assets/js/main.js', ['jquery'], '1.0.0', true);
    wp_enqueue_script('akpp-modal-auth', get_template_directory_uri() . '/assets/js/modal-auth.js', ['jquery'], '1.0.0', true);
    
    wp_localize_script('akpp-modal-auth', 'akpp_modal_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('akpp_modal_nonce')
    ]);
}
add_action('wp_enqueue_scripts', 'akpp45_enqueue_scripts');

// ============================================================
// 4. РЕГИСТРАЦИЯ СТРАНИЦ ФРОНТЕНДА
// ============================================================

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
                'post_content' => '<!-- CRM страница -->',
                'comment_status' => 'closed',
                'ping_status' => 'closed'
            ]);
        }
    }
}
add_action('after_switch_theme', 'akpp45_create_pages');

// ============================================================
// 5. ПЕРЕХВАТ НЕАВТОРИЗОВАННЫХ ПОЛЬЗОВАТЕЛЕЙ
// ============================================================

function akpp45_check_crm_access() {
    if (is_page(['crm-profile', 'crm-chat'])) {
        if (function_exists('AKPP_Auth') && !AKPP_Auth::is_logged_in()) {
            wp_redirect(home_url('/crm-login'));
            exit;
        }
    }
}
add_action('wp', 'akpp45_check_crm_access');

// ============================================================
// 6. ПРИНУДИТЕЛЬНОЕ СОЗДАНИЕ ТАБЛИЦ
// ============================================================

function akpp45_check_and_create_tables() {
    global $wpdb;
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}akpp_deals'");
    
    if (!$table_exists && class_exists('AKPP_Install')) {
        $install = AKPP_Install::get_instance();
        $install->create_tables();
    }
}
add_action('admin_init', 'akpp45_check_and_create_tables');

// Конец файла functions.php
?>
