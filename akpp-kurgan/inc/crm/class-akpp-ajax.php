<?php
/**
 * Добавьте эти методы в существующий класс AKPP_AJAX
 * 
 * @package AKPP45_CRM
 */

// ВНИМАНИЕ: Это ДОПОЛНЕНИЕ к существующему файлу class-akpp-ajax.php
// Не заменяйте весь файл, только добавьте эти методы в класс!

/**
 * Сохранение настроек Авито и получение токена
 */
public function ajax_save_avito_settings() {
    // Проверка nonce
    if (!check_ajax_referer('akpp_avito_settings_nonce', 'akpp_avito_nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    // Проверка прав (только администратор)
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    // Получение и санитизация данных
    $client_id = isset($_POST['avito_client_id']) ? sanitize_text_field($_POST['avito_client_id']) : '';
    $client_secret = isset($_POST['avito_client_secret']) ? sanitize_text_field($_POST['avito_client_secret']) : '';
    
    if (empty($client_id) || empty($client_secret)) {
        wp_send_json_error('Client ID и Client Secret обязательны для заполнения');
        return;
    }
    
    // Загружаем класс Авито
    if (!class_exists('AKPP_Avito')) {
        require_once AKPP_CRM_PATH . 'class-akpp-avito.php';
    }
    
    $avito = AKPP_Avito::get_instance();
    $result = $avito->save_settings($client_id, $client_secret);
    
    if ($result) {
        wp_send_json_success(['message' => 'Настройки сохранены, токен успешно получен']);
    } else {
        wp_send_json_error('Ошибка получения токена. Проверьте Client ID и Client Secret');
    }
}

/**
 * Обновление токена Авито (ручное обновление)
 */
public function ajax_refresh_avito_token() {
    // Проверка nonce
    if (!check_ajax_referer('akpp_refresh_token_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    // Проверка прав
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    // Загружаем класс Авито
    if (!class_exists('AKPP_Avito')) {
        require_once AKPP_CRM_PATH . 'class-akpp-avito.php';
    }
    
    $avito = AKPP_Avito::get_instance();
    $result = $avito->refresh_token();
    
    if ($result) {
        wp_send_json_success(['message' => 'Токен успешно обновлен']);
    } else {
        wp_send_json_error('Ошибка обновления токена. Проверьте настройки Client ID и Client Secret');
    }
}

/**
 * Регистрация AJAX действий
 * Добавьте эти строки в существующий метод __construct() или register_ajax_handlers()
 */
public function register_avito_ajax_handlers() {
    // Для авторизованных пользователей
    add_action('wp_ajax_akpp_save_avito_settings', [$this, 'ajax_save_avito_settings']);
    add_action('wp_ajax_akpp_refresh_avito_token', [$this, 'ajax_refresh_avito_token']);
}
