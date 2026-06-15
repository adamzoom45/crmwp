<?php
/**
 * Класс для интеграции с API Авито (OAuth, чат, диалоги)
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_Avito {
    
    private static $instance = null;
    private $api_url = 'https://api.avito.ru';
    private $client_id = '';
    private $client_secret = '';
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_settings();
        add_action('wp_ajax_akpp_save_avito_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_akpp_refresh_avito_token', [$this, 'ajax_refresh_token']);
        add_action('wp_ajax_akpp_send_avito_message', [$this, 'ajax_send_message']);
    }
    
    /**
     * Загрузка настроек
     */
    private function load_settings() {
        $this->client_id = get_option('akpp_avito_client_id', '');
        $this->client_secret = get_option('akpp_avito_client_secret', '');
    }
    
    /**
     * Сохранение настроек (AJAX)
     */
    public function ajax_save_settings() {
        if (!check_ajax_referer('akpp_avito_settings_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        $client_id = isset($_POST['avito_client_id']) ? sanitize_text_field($_POST['avito_client_id']) : '';
        $client_secret = isset($_POST['avito_client_secret']) ? sanitize_text_field($_POST['avito_client_secret']) : '';
        
        if (empty($client_id) || empty($client_secret)) {
            wp_send_json_error('Client ID и Client Secret обязательны');
            return;
        }
        
        $result = $this->save_settings($client_id, $client_secret);
        
        if ($result) {
            wp_send_json_success(['message' => 'Настройки сохранены, токен получен']);
        } else {
            wp_send_json_error('Ошибка получения токена');
        }
    }
    
    /**
     * Обновление токена (AJAX)
     */
    public function ajax_refresh_token() {
        if (!check_ajax_referer('akpp_refresh_token_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        $result = $this->refresh_token();
        
        if ($result) {
            wp_send_json_success(['message' => 'Токен обновлен']);
        } else {
            wp_send_json_error('Ошибка обновления токена');
        }
    }
    
    /**
     * Отправка сообщения (AJAX)
     */
    public function ajax_send_message() {
        if (!check_ajax_referer('akpp_send_avito_message_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        $dialog_id = isset($_POST['dialog_id']) ? sanitize_text_field($_POST['dialog_id']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        
        if (empty($dialog_id) || empty($message)) {
            wp_send_json_error('Диалог и сообщение обязательны');
            return;
        }
        
        $result = $this->send_message($dialog_id, $message);
        
        if ($result) {
            wp_send_json_success(['message' => 'Сообщение отправлено в Авито']);
        } else {
            wp_send_json_error('Ошибка отправки сообщения');
        }
    }
    
    /**
     * Сохранение настроек
     */
    public function save_settings($client_id, $client_secret) {
        update_option('akpp_avito_client_id', sanitize_text_field($client_id));
        update_option('akpp_avito_client_secret', sanitize_text_field($client_secret));
        
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        
        return $this->get_oauth_token();
    }
    
    /**
     * Получение OAuth токена
     */
    public function get_oauth_token() {
        if (empty($this->client_id) || empty($this->client_secret)) {
            $this->log_error('Client ID или Client Secret не настроены');
            return false;
        }
        
        $url = 'https://api.avito.ru/token';
        
        $body = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'client_credentials'
        ];
        
        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => http_build_query($body)
        ];
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('Ошибка запроса токена: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            $this->log_error('Ошибка получения токена. Статус: ' . $status_code);
            return false;
        }
        
        if (!isset($data['access_token']) || !isset($data['expires_in'])) {
            $this->log_error('Неверный ответ от API');
            return false;
        }
        
        $this->save_token($data['access_token'], $data['expires_in']);
        
        if (isset($data['user_id'])) {
            update_option('akpp_avito_account_id', $data['user_id']);
        }
        
        return true;
    }
    
    /**
     * Сохранение токена в БД
     */
    private function save_token($access_token, $expires_in) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_avito_tokens';
        
        $wpdb->delete($table_name, ['is_active' => 1]);
        
        $wpdb->insert(
            $table_name,
            [
                'access_token' => $access_token,
                'expires_in' => $expires_in,
                'created_at' => current_time('mysql'),
                'is_active' => 1
            ],
            ['%s', '%d', '%s', '%d']
        );
        
        $this->log_event('Новый токен сохранен');
    }
    
    /**
     * Получение активного токена
     */
    public function get_active_token() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_avito_tokens';
        
        $token = $wpdb->get_row("SELECT * FROM {$table_name} WHERE is_active = 1 LIMIT 1");
        
        if (!$token) {
            $result = $this->get_oauth_token();
            if ($result) {
                $token = $wpdb->get_row("SELECT * FROM {$table_name} WHERE is_active = 1 LIMIT 1");
            }
        }
        
        if ($token) {
            $created_timestamp = strtotime($token->created_at);
            $expires_timestamp = $created_timestamp + $token->expires_in;
            
            if (($expires_timestamp - time()) < 3600) {
                $this->refresh_token();
                $token = $wpdb->get_row("SELECT * FROM {$table_name} WHERE is_active = 1 LIMIT 1");
            }
        }
        
        return $token ? $token->access_token : false;
    }
    
    /**
     * Обновление токена
     */
    public function refresh_token() {
        return $this->get_oauth_token();
    }
    
    /**
     * Отправка сообщения
     */
    public function send_message($dialog_id, $message) {
        $token = $this->get_active_token();
        
        if (!$token) {
            $this->log_error('Нет активного токена');
            return false;
        }
        
        $account_id = get_option('akpp_avito_account_id', '');
        
        if (empty($account_id)) {
            $this->log_error('Не указан account_id');
            return false;
        }
        
        $url = $this->api_url . '/messenger/v1/accounts/' . $account_id . '/chats/' . $dialog_id . '/messages';
        
        $body = ['message' => ['text' => $message]];
        
        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body)
        ];
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('Ошибка отправки: ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200 && $status_code !== 201) {
            $this->log_error('Ошибка отправки. Статус: ' . $status_code);
            return false;
        }
        
        $this->log_event("Сообщение отправлено в диалог {$dialog_id}");
        
        return true;
    }
    
    /**
     * Получение диалогов
     */
    public function get_dialogs($limit = 50) {
        $token = $this->get_active_token();
        
        if (!$token) {
            return [];
        }
        
        $account_id = get_option('akpp_avito_account_id', '');
        
        if (empty($account_id)) {
            return [];
        }
        
        $url = $this->api_url . '/messenger/v1/accounts/' . $account_id . '/chats?limit=' . $limit;
        
        $args = [
            'method' => 'GET',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ]
        ];
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('Ошибка получения диалогов: ' . $response->get_error_message());
            return [];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['chats'])) {
            $this->save_dialogs($data['chats']);
            return $data['chats'];
        }
        
        return [];
    }
    
    /**
     * Сохранение диалогов в БД
     */
    private function save_dialogs($chats) {
        global $wpdb;
        $table_dialogs = $wpdb->prefix . 'akpp_avito_dialogs';
        
        foreach ($chats as $chat) {
            $dialog_id = $chat['id'] ?? '';
            if (empty($dialog_id)) continue;
            
            $wpdb->replace(
                $table_dialogs,
                [
                    'dialog_id' => $dialog_id,
                    'user_id' => $chat['user_id'] ?? '',
                    'user_name' => $chat['user_name'] ?? 'Пользователь Авито',
                    'last_message' => $chat['last_message'] ?? '',
                    'last_message_time' => $chat['last_message_time'] ?? current_time('mysql'),
                    'is_active' => 1,
                    'updated_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%s', '%s', '%d', '%s']
            );
        }
    }
    
    private function log_error($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_AVITO] ОШИБКА: ' . $message);
        }
    }
    
    private function log_event($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_AVITO] СОБЫТИЕ: ' . $message);
        }
    }
}
