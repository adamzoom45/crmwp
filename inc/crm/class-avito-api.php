<?php
/**
 * АКПП45 CRM - Класс для работы с Avito API
 * Реализует OAuth 2.0 (Client Credentials), управление токенами и базовые запросы.
 *
 * @package AKPP_CRM
 * @version 4.2
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

class AKPP_Avito_API {

    /**
     * Единственный экземпляр класса
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
     * Конструктор
     */
    private function __construct() {
        $this->client_id     = get_option('akpp_avito_client_id', '');
        $this->client_secret = get_option('akpp_avito_client_secret', '');
    }

    /**
     * Получение экземпляра (Singleton)
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
            $error_msg = isset($body['error_description']) ? $body['error_description'] : 'Unknown API error';
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
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
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

        // Обработка ошибок API (например, 401 Unauthorized -> токен протух, но мы его уже обновили, так что это другая ошибка)
        if ($response_code >= 400) {
            $error_msg = isset($response_body['error']) ? $response_body['error'] : 'API Error';
            $this->log_error("API Error $response_code ($endpoint)", $error_msg);
            return new WP_Error('avito_api_error', $error_msg, ['status' => $response_code, 'data' => $response_body]);
        }

        return $response_body;
    }

    /**
     * Пример метода: Отправка сообщения в диалог
     *
     * @param int    $dialog_id ID диалога в Авито
     * @param string $message   Текст сообщения
     * @param string $type      Тип сообщения: 'text' или 'image'
     * @return array|WP_Error
     */
    public function send_message($dialog_id, $message, $type = 'text') {
        $endpoint = "/messages/1/dialogs/{$dialog_id}/messages";
        
        $payload = [
            'type'    => $type,
            'content' => $message,
        ];

        return $this->make_request($endpoint, 'POST', $payload);
    }

    /**
     * Пример метода: Получение списка диалогов
     *
     * @param int $limit Количество диалогов
     * @return array|WP_Error
     */
    public function get_dialogs($limit = 50) {
        $endpoint = "/messages/1/dialogs?limit={$limit}";
        return $this->make_request($endpoint, 'GET');
    }

    /**
     * Логирование ошибок (для отладки)
     *
     * @param string $context Контекст ошибки
     * @param string $message Текст ошибки
     */
    private function log_error($context, $message) {
        // В продакшене лучше использовать error_log или кастомную таблицу логов
        // Здесь используем стандартный error_log WordPress
        error_log(sprintf('[AKPP Avito API] %s: %s', $context, $message));
    }
}
