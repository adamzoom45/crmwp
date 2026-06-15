<?php
/**
 * Класс для интеграции с API Авито
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
    }
    
    private function load_settings() {
        $this->client_id = get_option('akpp_avito_client_id', '');
        $this->client_secret = get_option('akpp_avito_client_secret', '');
    }
    
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
            $this->log_error('Ошибка получения токена. Статус: ' . $status_code . ', Ответ: ' . $body);
            return false;
        }
        
        if (!isset($data['access_token']) || !isset($data['expires_in'])) {
            $this->log_error('Неверный ответ от API: ' . $body);
            return false;
        }
        
        $this->save_token($data['access_token'], $data['expires_in']);
        
        if (isset($data['user_id'])) {
            $this->set_account_id($data['user_id']);
        }
        
        return [
            'access_token' => $data['access_token'],
            'expires_in' => $data['expires_in'],
            'token_type' => $data['token_type'] ?? 'bearer'
        ];
    }
    
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
        
        $this->log_event('Новый токен сохранен. Истекает через ' . $expires_in . ' секунд');
    }
    
    public function get_active_token() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'akpp_avito_tokens';
        
        $token = $wpdb->get_row(
            "SELECT access_token, created_at, expires_in FROM {$table_name} WHERE is_active = 1 LIMIT 1"
        );
        
        if (!$token) {
            $result = $this->get_oauth_token();
            if ($result && isset($result['access_token'])) {
                return $result['access_token'];
            }
            return false;
        }
        
        $created_timestamp = strtotime($token->created_at);
        $expires_timestamp = $created_timestamp + $token->expires_in;
        
        if (($expires_timestamp - time()) < 3600) {
            $this->log_event('Токен истекает, обновляем...');
            $result = $this->get_oauth_token();
            if ($result && isset($result['access_token'])) {
                return $result['access_token'];
            }
        }
        
        return $token->access_token;
    }
    
    public function refresh_token() {
        $result = $this->get_oauth_token();
        return ($result !== false);
    }
    
    public function save_settings($client_id, $client_secret) {
        if (empty($client_id) || empty($client_secret)) {
            return false;
        }
        
        update_option('akpp_avito_client_id', sanitize_text_field($client_id));
        update_option('akpp_avito_client_secret', sanitize_text_field($client_secret));
        
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        
        return $this->get_oauth_token();
    }
    
    public function send_message($dialog_id, $message) {
        $token = $this->get_active_token();
        
        if (!$token) {
            $this->log_error('Нет активного токена для отправки сообщения');
            return false;
        }
        
        $account_id = get_option('akpp_avito_account_id', '');
        
        if (empty($account_id)) {
            $this->log_error('Не указан account_id Авито');
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
            $this->log_error('Ошибка отправки сообщения: ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200 && $status_code !== 201) {
            $this->log_error('Ошибка отправки сообщения. Статус: ' . $status_code . ', Ответ: ' . $body);
            return false;
        }
        
        $this->log_event("Сообщение отправлено в диалог {$dialog_id}");
        $this->save_sent_message($dialog_id, $message);
        
        return true;
    }
    
    private function save_sent_message($dialog_id, $message) {
        global $wpdb;
        
        $table_cache = $wpdb->prefix . 'akpp_avito_messages_cache';
        
        $wpdb->insert(
            $table_cache,
            [
                'dialog_id' => $dialog_id,
                'message_id' => 'sent_' . time() . '_' . rand(1000, 9999),
                'message_text' => $message,
                'is_incoming' => 0,
                'is_sent' => 1,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s']
        );
    }
    
    public function set_account_id($account_id) {
        update_option('akpp_avito_account_id', sanitize_text_field($account_id));
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
