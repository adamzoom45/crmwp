<?php
/**
 * АКПП45 CRM - Класс для работы с Avito API
 * Реализует OAuth 2.0 (Client Credentials), управление токенами и базовые запросы.
 *
 * @package AKPP_CRM
 * @version 4.3
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

class AKPP_Avito_API {

    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;

    /**
     * Настройки API
     */
    private $client_id;
    private $client_secret;
    private $token_endpoint = 'https://api.avito.ru/token';
    private $api_base_url   = 'https://api.avito.ru';

    /**
     * Конструктор (защищен для Singleton)
     */
    private function __construct() {
        $this->client_id     = get_option('akpp_avito_client_id', '');
        $this->client_secret = get_option('akpp_avito_client_secret', '');
    }

    /**
     * Получение экземпляра
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Получение валидного Access Token
     * Проверяет кэш, если токен истёк или отсутствует — запрашивает новый.
     *
     * @return string|WP_Error Access token или ошибка
     */
    public function get_access_token() {
        $token_data = get_option('akpp_avito_token_data', null);

        // Проверяем, есть ли токен и не истёк ли он (с запасом 60 секунд)
        if ($token_data && isset($token_data['access_token']) && isset($token_data['expires_at'])) {
            if (time() < ($token_data['expires_at'] - 60)) {
                return $token_data['access_token'];
            }
        }

        // Токен истёк или отсутствует, получаем новый
        return $this->fetch_new_token();
    }

    /**
     * Запрос нового токена у Авито (OAuth 2.0 Client Credentials)
     *
     * @return string|WP_Error
     */
    private function fetch_new_token() {
        if (empty($this->client_id) || empty($this->client_secret)) {
            return new WP_Error('avito_missing_credentials', __('Не указаны Client ID или Client Secret в настройках.', 'akpp-crm'));
        }

        $response = wp_remote_post($this->token_endpoint, [
            'body' => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $this->log_error('Token Request Failed', $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200 || !isset($body['access_token'])) {
            $error_msg = isset($body['error_description']) ? $body['error_description'] : 'Unknown API error (Code: ' . $response_code . ')';
            $this->log_error('Token Fetch Failed', $error_msg);
            return new WP_Error('avito_token_error', $error_msg);
        }

        // Сохраняем токен и время его истечения
        $expires_in = isset($body['expires_in']) ? intval($body['expires_in']) : 86400; // По умолчанию 24 часа
        $token_data = [
            'access_token' => $body['access_token'],
            'expires_at'   => time() + $expires_in,
        ];

        update_option('akpp_avito_token_data', $token_data);
        $this->log_info('Token successfully refreshed');

        return $body['access_token'];
    }

    /**
     * Выполнение защищённого запроса к Avito API
     *
     * @param string $endpoint Путь относительно api_base_url (например, '/messages/1/dialogs')
     * @param string $method   HTTP метод (GET, POST, PUT, DELETE)
     * @param array  $body     Тело запроса (для POST/PUT)
     * @return array|WP_Error  Декодированный ответ или ошибка
     */
    public function make_request($endpoint, $method = 'GET', $body = null) {
        $token = $this->get_access_token();

        if (is_wp_error($token)) {
            return $token;
        }

        $url = $this->api_base_url . $endpoint;
        
        $args = [
            'method'  => strtoupper($method),
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'timeout' => 15,
        ];

        if ($body && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->log_error("API Request Failed ($method $endpoint)", $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        // Обработка ошибок API (например, 401 Unauthorized)
        if ($response_code >= 400) {
            $error_msg = isset($response_body['error']) ? $response_body['error'] : 'API Error';
            $error_desc = isset($response_body['error_description']) ? $response_body['error_description'] : '';
            
            $this->log_error("API Error $response_code ($endpoint)", $error_msg . ' ' . $error_desc);
            
            // Если токен протух (401), пробуем обновить его один раз и повторить запрос
            if ($response_code === 401) {
                delete_option('akpp_avito_token_data'); // Удаляем протухший токен
                $new_token = $this->fetch_new_token();
                
                if (!is_wp_error($new_token)) {
                    $args['headers']['Authorization'] = 'Bearer ' . $new_token;
                    $retry_response = wp_remote_request($url, $args);
                    
                    if (!is_wp_error($retry_response)) {
                        $retry_code = wp_remote_retrieve_response_code($retry_response);
                        $retry_body = json_decode(wp_remote_retrieve_body($retry_response), true);
                        
                        if ($retry_code < 400) {
                            return $retry_body;
                        }
                    }
                }
            }
            
            return new WP_Error('avito_api_error', $error_msg, ['status' => $response_code, 'data' => $response_body]);
        }

        return $response_body ?: [];
    }

    /**
     * Получение списка диалогов
     *
     * @param int $limit Количество диалогов (макс. 100)
     * @return array|WP_Error
     */
    public function get_dialogs($limit = 50) {
        $limit = min(100, max(1, intval($limit)));
        $endpoint = "/messages/1/dialogs?limit={$limit}";
        return $this->make_request($endpoint, 'GET');
    }

    /**
     * Получение сообщений конкретного диалога
     *
     * @param int $avito_dialog_id ID диалога в Авито
     * @param int $limit Количество сообщений
     * @return array|WP_Error
     */
    public function get_dialog_messages($avito_dialog_id, $limit = 50) {
        $limit = min(100, max(1, intval($limit)));
        $endpoint = "/messages/1/dialogs/{$avito_dialog_id}/messages?limit={$limit}";
        return $this->make_request($endpoint, 'GET');
    }

    /**
     * Отправка сообщения в диалог
     *
     * @param int    $avito_dialog_id ID диалога в Авито
     * @param string $message         Текст сообщения
     * @param string $type            Тип сообщения: 'text' или 'image'
     * @return array|WP_Error
     */
    public function send_message($avito_dialog_id, $message, $type = 'text') {
        $endpoint = "/messages/1/dialogs/{$avito_dialog_id}/messages";
        
        $payload = [
            'type'    => $type,
            'content' => $message,
        ];

        return $this->make_request($endpoint, 'POST', $payload);
    }

    /**
     * Логирование ошибок (для отладки)
     *
     * @param string $context Контекст ошибки
     * @param string $msg     Текст ошибки
     */
    private function log_error($context, $msg) {
        error_log(sprintf('[AKPP Avito API] ERROR: %s - %s', $context, $msg));
    }

    /**
     * Логирование информационных сообщений
     *
     * @param string $msg Текст сообщения
     */
    private function log_info($msg) {
        error_log(sprintf('[AKPP Avito API] INFO: %s', $msg));
    }
}
