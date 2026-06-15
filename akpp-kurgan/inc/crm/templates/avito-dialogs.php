<?php
/**
 * Шаблон страницы диалогов Авито
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Получаем список диалогов
$table_dialogs = $wpdb->prefix . 'akpp_avito_dialogs';
$dialogs = $wpdb->get_results(
    "SELECT * FROM {$table_dialogs} 
    ORDER BY last_message_time DESC 
    LIMIT 50"
);

//
