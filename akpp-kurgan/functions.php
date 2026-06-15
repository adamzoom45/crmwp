<?php
/**
 * Функции темы АКПП45
 */

// Подключение CRM системы
require_once get_template_directory() . '/inc/crm/class-akpp-crm.php';

// Инициализация CRM
add_action('after_setup_theme', function() {
    if (class_exists('AKPP_CRM')) {
        AKPP_CRM::get_instance();
    }
});

// Подключение стилей и скриптов
add_action('wp_enqueue_scripts', function() {
    // Стили темы
    wp_enqueue_style('akpp45-style', get_stylesheet_uri());
    wp_enqueue_style('akpp45-main', get_template_directory_uri() . '/assets/css/main.css');
    wp_enqueue_style('akpp-modal', get_template_directory_uri() . '/assets/css/modal.css');
    
    // Скрипты
    wp_enqueue_script('akpp45-main', get_template_directory_uri() . '/assets/js/main.js', ['jquery']);
    wp_enqueue_script('akpp-modal-auth', get_template_directory_uri() . '/assets/js/modal-auth.js', ['jquery']);
    
    // AJAX настройки
    wp_localize_script('akpp-modal-auth', 'akpp_modal_ajax', [
        'ajax_url' => admin_url('admin-ajax.php')
    ]);
});

// Поддержка темы
add_theme_support('title-tag');
add_theme_support('post-thumbnails');
register_nav_menus([
    'primary' => 'Главное меню'
]);
