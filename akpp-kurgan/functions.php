<?php
/**
 * АКПП Курган - Theme Functions
 * Version: 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Theme Setup
function akpp_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption'));
    
    register_nav_menus(array(
        'primary' => 'Главное меню',
        'footer'  => 'Меню в подвале',
    ));
}
add_action('after_setup_theme', 'akpp_setup');

// Enqueue Scripts & Styles
function akpp_enqueue_assets() {
    // Frontend стили
    wp_enqueue_style('akpp-style', get_stylesheet_uri(), array(), '2.0.0');
    wp_enqueue_style('akpp-main', get_template_directory_uri() . '/assets/css/main.css', array(), '2.0.0');
    wp_enqueue_script('akpp-main', get_template_directory_uri() . '/assets/js/main.js', array('jquery'), '2.0.0', true);
    
    // Admin стили (CRM) - ТОЛЬКО В АДМИНКЕ
    if (is_admin()) {
        // Подключаем CRM стили
        wp_enqueue_style('akpp-crm', get_template_directory_uri() . '/assets/css/crm.css', array(), '2.0.0');
        wp_enqueue_style('akpp-crm-admin', get_template_directory_uri() . '/inc/crm/assets/css/crm-admin.css', array(), '2.0.0');
        
        // Подключаем CRM скрипты
        wp_enqueue_script('akpp-crm', get_template_directory_uri() . '/assets/js/crm.js', array('jquery'), '2.0.0', true);
        
        // Передаем переменные в JavaScript
        wp_localize_script('akpp-crm', 'akppCrm', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('akpp_crm_nonce'),
        ));
    }
}
add_action('wp_enqueue_scripts', 'akpp_enqueue_assets');
add_action('admin_enqueue_scripts', 'akpp_enqueue_assets');

// Helper Functions
function akpp_get_option($key, $default = '') {
    return get_theme_mod($key, $default);
}

// Customizer
function akpp_customize_register($wp_customize) {
    $wp_customize->add_section('akpp_contacts', array(
        'title'    => 'Контакты',
        'priority' => 30,
    ));
    
    $fields = array(
        'phone_1'  => array('label' => 'Телефон 1', 'default' => '+7 (963) 866-99-96'),
        'phone_2'  => array('label' => 'Телефон 2', 'default' => ''),
        'telegram' => array('label' => 'Telegram', 'default' => '@akppkgn'),
        'address'  => array('label' => 'Адрес', 'default' => 'г. Курган'),
    );
    
    foreach ($fields as $key => $field) {
        $wp_customize->add_setting($key, array(
            'default'           => $field['default'],
            'sanitize_callback' => 'sanitize_text_field',
        ));
        $wp_customize->add_control($key, array(
            'label'   => $field['label'],
            'section' => 'akpp_contacts',
            'type'    => 'text',
        ));
    }
}
add_action('customize_register', 'akpp_customize_register');

// Contact Form Handler
function akpp_handle_contact_form() {
    check_ajax_referer('akpp_kurgan_nonce', 'nonce');
    
    $name    = sanitize_text_field($_POST['name'] ?? '');
    $phone   = sanitize_text_field($_POST['phone'] ?? '');
    $message = sanitize_textarea_field($_POST['message'] ?? '');
    
    if (empty($name) || empty($phone)) {
        wp_send_json_error(array('message' => 'Заполните обязательные поля'));
    }
    
    $to      = get_option('admin_email');
    $subject = 'Новая заявка с сайта АКПП Курган';
    $body    = "Имя: {$name}\nТелефон: {$phone}\n\nСообщение:\n{$message}";
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    if (wp_mail($to, $subject, $body, $headers)) {
        wp_send_json_success(array('message' => 'Заявка отправлена!'));
    } else {
        wp_send_json_error(array('message' => 'Ошибка отправки'));
    }
}
add_action('wp_ajax_akpp_contact_form', 'akpp_handle_contact_form');
add_action('wp_ajax_nopriv_akpp_contact_form', 'akpp_handle_contact_form');

// ============================================
// ПОДКЛЮЧЕНИЕ CRM СИСТЕМЫ
// ============================================
if (is_admin()) {
    require_once get_template_directory() . '/inc/crm/config.php';
    require_once get_template_directory() . '/inc/crm/database.php';
    require_once get_template_directory() . '/inc/crm/class-crm-admin.php';
    require_once get_template_directory() . '/inc/crm/class-crm-deals.php';
    require_once get_template_directory() . '/inc/crm/class-crm-employees.php';
    require_once get_template_directory() . '/inc/crm/class-vin-decoder.php';
    require_once get_template_directory() . '/inc/crm/class-amayama.php';
    require_once get_template_directory() . '/inc/crm/class-transakpp.php';
    require_once get_template_directory() . '/inc/crm/class-vehicles.php';
    require_once get_template_directory() . '/inc/crm/class-transmissions.php';
}
// Подключение импортера TransAKPP
if (is_admin()) {
    require_once get_template_directory() . '/inc/crm/class-transakpp-importer.php';
}
// Универсальный парсер
if (is_admin()) {
    require_once get_template_directory() . '/inc/crm/class-universal-parser.php';
}
// CRM Лиды и интеграции
if (is_admin()) {
    require_once get_template_directory() . '/inc/crm/class-leads.php';
    require_once get_template_directory() . '/inc/crm/class-telegram-bot.php';
    require_once get_template_directory() . '/inc/crm/class-avito-integration.php';
    require_once get_template_directory() . '/inc/crm/class-2gis-integration.php';
}
// Интеграции
if (is_admin()) {
    require_once get_template_directory() . '/inc/crm/class-integrations.php';
}
// Telegram CRM Bot
if (is_admin()) {
    require_once get_template_directory() . '/inc/crm/class-telegram-crm.php';
}