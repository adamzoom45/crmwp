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
    // Поддержка заголовков
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', [
        'height' => 100,
        'width' => 400,
        'flex-height' => true,
        'flex-width' => true,
    ]);
    
    // Регистрация меню
    register_nav_menus([
        'primary' => 'Главное меню',
        'footer' => 'Меню в подвале',
        'mobile' => 'Мобильное меню'
    ]);
    
    // Поддержка HTML5
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
    // Стили темы
    wp_enqueue_style('akpp45-style', get_stylesheet_uri(), [], '1.0.0');
    wp_enqueue_style('akpp45-main', get_template_directory_uri() . '/assets/css/main.css', [], '1.0.0');
    wp_enqueue_style('akpp-modal', get_template_directory_uri() . '/assets/css/modal.css', [], '1.0.0');
    
    // Скрипты темы
    wp_enqueue_script('akpp45-main', get_template_directory_uri() . '/assets/js/main.js', ['jquery'], '1.0.0', true);
    wp_enqueue_script('akpp-modal-auth', get_template_directory_uri() . '/assets/js/modal-auth.js', ['jquery'], '1.0.0', true);
    
    // Локализация для AJAX
    wp_localize_script('akpp-modal-auth', 'akpp_modal_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('akpp_modal_nonce')
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

/**
 * Перенаправление неавторизованных пользователей на страницу входа
 */
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
// 6. ПРИНУДИТЕЛЬНОЕ СОЗДАНИЕ ТАБЛИЦ БД
// ============================================================

/**
 * Создание таблиц при необходимости
 */
function akpp45_check_and_create_tables() {
    global $wpdb;
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}akpp_deals'");
    
    if (!$table_exists && class_exists('AKPP_Install')) {
        $install = AKPP_Install::get_instance();
        $install->create_tables();
        
        // Добавляем тестовые данные
        akpp45_add_test_data();
    }
}
add_action('admin_init', 'akpp45_check_and_create_tables');

/**
 * Добавление тестовых данных
 */
function akpp45_add_test_data() {
    global $wpdb;
    
    $table_employees = $wpdb->prefix . 'akpp_employees';
    $exists = $wpdb->get_var("SELECT COUNT(*) FROM {$table_employees}");
    
    if ($exists == 0) {
        $wpdb->insert($table_employees, [
            'name' => 'Администратор',
            'email' => 'admin@akpp45.ru',
            'phone' => '+7 (999) 123-45-67',
            'role' => 'admin',
            'percent' => 50,
            'is_active' => 1,
            'created_at' => current_time('mysql')
        ]);
        
        $wpdb->insert($table_employees, [
            'name' => 'Гид',
            'email' => 'guide@akpp45.ru',
            'phone' => '+7 (999) 234-56-78',
            'role' => 'guide',
            'percent' => 40,
            'is_active' => 1,
            'created_at' => current_time('mysql')
        ]);
        
        $wpdb->insert($table_employees, [
            'name' => 'Мастер',
            'email' => 'master@akpp45.ru',
            'phone' => '+7 (999) 345-67-89',
            'role' => 'master',
            'percent' => 45,
            'is_active' => 1,
            'created_at' => current_time('mysql')
        ]);
    }
    
    $table_parts = $wpdb->prefix . 'akpp_parts';
    $parts_exists = $wpdb->get_var("SELECT COUNT(*) FROM {$table_parts}");
    
    if ($parts_exists == 0) {
        $test_parts = [
            ['name' => 'Ремкомплект АКПП A750E', 'sku' => 'RC-A750E-001', 'category' => 'Ремкомплекты', 'price' => 8500, 'quantity' => 10],
            ['name' => 'Фрикционы A750E (комплект)', 'sku' => 'FR-A750E-001', 'category' => 'Фрикционы', 'price' => 12500, 'quantity' => 5],
            ['name' => 'Масло ATF WS 4л', 'sku' => 'OIL-ATF-WS-4L', 'category' => 'Масла ATF', 'price' => 3200, 'quantity' => 20],
            ['name' => 'Фильтр АКПП A750E', 'sku' => 'FIL-A750E-001', 'category' => 'Фильтры', 'price' => 850, 'quantity' => 15],
            ['name' => 'Соленоид Shift Solenoid', 'sku' => 'SOL-SHIFT-001', 'category' => 'Соленоиды', 'price' => 3200, 'quantity' => 8],
        ];
        
        foreach ($test_parts as $part) {
            $wpdb->insert($table_parts, array_merge($part, ['created_at' => current_time('mysql')]));
        }
    }
}

// ============================================================
// 7. БЕЗОПАСНОСТЬ
// ============================================================

/**
 * Отключение вывода версии WordPress
 */
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rsd_link');

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
// 8. ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================

/**
 * Форматирование цены
 */
function akpp45_format_price($price) {
    return number_format(floatval($price), 0, ',', ' ') . ' ₽';
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
 * Проверка авторизации в CRM
 */
function akpp45_is_crm_logged_in() {
    if (function_exists('AKPP_Auth')) {
        return AKPP_Auth::is_logged_in();
    }
    return false;
}

/**
 * Получение текущего пользователя CRM
 */
function akpp45_get_current_crm_user() {
    if (function_exists('AKPP_Auth') && AKPP_Auth::is_logged_in()) {
        return AKPP_Auth::get_current_user();
    }
    return null;
}

// Конец файла functions.php
