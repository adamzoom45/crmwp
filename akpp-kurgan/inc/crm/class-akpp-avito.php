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
    
    /**
     * Экземпляр класса (Singleton)
     */
    private static $instance = null;
    
    /**
     * API URL
     */
    private $api_url = 'https://api.avito.ru';
    
    /**
     * Client ID из настроек
     */
    private $client_id = '';
    
    /**
     * Client Secret из настроек
     */
    private $client_secret = '';
    
    /**
     * Получить экземпляр класса
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор
     */
    private function __construct() {
        $this->load_settings();
    }
    
    /**
     * Загрузка настроек из базы данных
     */
    private function load_settings() {
        $this->client_id = get_option('akpp_avito_client_id', '');
        $this->client_secret = get_option('akpp_avito_client_secret', '');
    }
    
    /**
     * Получение OAuth токена
     * 
     * @return array|false Массив с токеном или false при ошибке
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
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
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
        
        // Сохраняем токен в базу данных
        $this->save_token($data['access_token'], $data['expires_in']);
        
        return [
            'access_token' => $data['access_token'],
            'expires_in' => $data['expires_in'],
            'token_type' => $data['token_type'] ?? 'bearer'
        ];
    }
    
    /**
     * Сохранение токена в таблицу wp_akpp_avito_tokens
     * 
     * @param string $access_token Токен доступа
     * @param int $expires_in Время жизни в секундах
     */
    private function save_token($access_token, $expires_in) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'akpp_avito_tokens';
        
        // Удаляем старые токены
        $wpdb->delete($table_name, ['is_active' => 1]);
        
        // Сохраняем новый токен
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
    
    /**
     * Получение активного токена из базы
     * 
     * @return string|false Токен или false
     */
    public function get_active_token() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'akpp_avito_tokens';
        
        $token = $wpdb->get_row(
            "SELECT access_token, created_at, expires_in FROM {$table_name} WHERE is_active = 1 LIMIT 1"
        );
        
        if (!$token) {
            // Нет токена - пробуем получить новый
            $result = $this->get_oauth_token();
            if ($result && isset($result['access_token'])) {
                return $result['access_token'];
            }
            return false;
        }
        
        // Проверяем, не истек ли токен
        $created_timestamp = strtotime($token->created_at);
        $expires_timestamp = $created_timestamp + $token->expires_in;
        
        // Если до истечения меньше 1 часа (3600 секунд) - обновляем
        if (($expires_timestamp - time()) < 3600) {
            $this->log_event('Токен истекает, обновляем...');
            $result = $this->get_oauth_token();
            if ($result && isset($result['access_token'])) {
                return $result['access_token'];
            }
        }
        
        return $token->access_token;
    }
    
    /**
     * Обновление токена (публичный метод для cron)
     * 
     * @return bool
     */
    public function refresh_token() {
        $result = $this->get_oauth_token();
        return ($result !== false);
    }
    
    /**
     * Сохранение настроек Client ID и Client Secret
     * 
     * @param string $client_id
     * @param string $client_secret
     * @return bool
     */
    public function save_settings($client_id, $client_secret) {
        if (empty($client_id) || empty($client_secret)) {
            return false;
        }
        
        update_option('akpp_avito_client_id', sanitize_text_field($client_id));
        update_option('akpp_avito_client_secret', sanitize_text_field($client_secret));
        
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        
        // Пробуем получить токен
        return $this->get_oauth_token();
    }
    
    /**
     * Логирование ошибок
     */
    private function log_error($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_AVITO] ОШИБКА: ' . $message);
        }
    }
    
    /**
     * Логирование событий
     */
    private function log_event($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_AVITO] СОБЫТИЕ: ' . $message);
        }
    }
}
