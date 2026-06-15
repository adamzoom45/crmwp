<?php
if (!defined('ABSPATH')) exit;

class AKPP_Avito {
    
    private $client_id;
    private $client_secret;
    private $api_base = 'https://api.avito.ru';
    private $token_data = null;

    public function __construct() {
        $this->client_id = get_option('akpp_avito_client_id', '');
        $this->client_secret = get_option('akpp_avito_client_secret', '');
        
        // Хуки для AJAX запросов из админки
        add_action('wp_ajax_akpp_avito_auth', [$this, 'ajax_auth']);
        add_action('wp_ajax_akpp_get_avito_dialogs', [$this, 'ajax_get_dialogs']);
        add_action('wp_ajax_akpp_get_avito_messages', [$this, 'ajax_get_messages']);
    }

    /**
     * Получение или обновление токена доступа
     */
    private function get_access_token() {
        // Проверяем, есть ли сохраненный валидный токен в БД
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_avito_tokens';
        $this->token_data = $wpdb->get_row("SELECT * FROM $table ORDER BY id DESC LIMIT 1");

        $now = time();
        if ($this->token_data && $this->token_data->expires_at > $now) {
            return $this->token_data->access_token;
        }

        // Если токена нет или он истек, получаем новый
        return $this->refresh_or_get_new_token();
    }

    /**
     * Запрос нового токена или обновление по refresh_token
     */
    private function refresh_or_get_new_token() {
        $body = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'client_credentials' // Для сервер-сервер взаимодействия
        ];

        // Если есть refresh_token, можно использовать grant_type=refresh_token
        if (!empty($this->token_data->refresh_token)) {
            $body['grant_type'] = 'refresh_token';
            $body['refresh_token'] = $this->token_data->refresh_token;
        }

        $response = wp_remote_post($this->api_base . '/token', [
            'body' => $body,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
        ]);

        if (is_wp_error($response)) {
            error_log('Avito API Token Error: ' . $response->get_error_message());
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['access_token'])) {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_avito_tokens';
            
            $expires_in = isset($data['expires_in']) ? $data['expires_in'] : 3600;
            $expires_at = time() + $expires_in - 60; // Запас 60 секунд

            $wpdb->insert($table, [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? ($this->token_data->refresh_token ?? ''),
                'expires_at' => $expires_at
            ]);

            return $data['access_token'];
        }

        error_log('Avito API Token Response: ' . print_r($data, true));
        return false;
    }

    /**
     * Получение списка диалогов
     */
    public function get_dialogs() {
        $token = $this->get_access_token();
        if (!$token) return ['error' => 'Не удалось получить токен авторизации'];

        $response = wp_remote_get($this->api_base . '/messenger/v1/accounts/self/dialogs', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Сохраняем диалоги в локальный кэш для быстрого доступа
        if (isset($body['dialogs']) && is_array($body['dialogs'])) {
            $this->cache_dialogs($body['dialogs']);
        }

        return $body;
    }

    /**
     * Получение сообщений конкретного диалога
     */
    public function get_messages($dialog_id) {
        $token = $this->get_access_token();
        if (!$token) return ['error' => 'Не удалось получить токен авторизации'];

        $response = wp_remote_get($this->api_base . '/messenger/v1/accounts/self/dialogs/' . urlencode($dialog_id) . '/messages', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Кэшируем сообщения
        if (isset($body['messages']) && is_array($body['messages'])) {
            $this->cache_messages($dialog_id, $body['messages']);
        }

        return $body;
    }

    /**
     * Отправка сообщения в диалог
     */
    public function send_message($dialog_id, $text) {
        $token = $this->get_access_token();
        if (!$token) return false;

        $response = wp_remote_post($this->api_base . '/messenger/v1/accounts/self/dialogs/' . urlencode($dialog_id) . '/messages', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'text' => $text,
                'type' => 'text'
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('Avito Send Message Error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['message_id']);
    }

    /**
     * Кэширование диалогов в БД
     */
    private function cache_dialogs($dialogs) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_avito_dialogs';

        foreach ($dialogs as $dialog) {
            $dialog_id = $dialog['id'] ?? '';
            $item_id = $dialog['item_id'] ?? '';
            $user_name = $dialog['user']['name'] ?? 'Неизвестно';
            $last_message = $dialog['last_message']['text'] ?? '';
            $last_message_at = isset($dialog['last_message']['created']) ? date('Y-m-d H:i:s', strtotime($dialog['last_message']['created'])) : current_time('mysql');

            $wpdb->replace($table, [
                'dialog_id' => $dialog_id,
                'item_id' => $item_id,
                'user_name' => $user_name,
                'last_message' => $last_message,
                'last_message_at' => $last_message_at,
                'is_read' => 0
            ]);
        }
    }

    /**
     * Кэширование сообщений в БД
     */
    private function cache_messages($dialog_id, $messages) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_avito_messages_cache';

        foreach ($messages as $msg) {
            $message_id = $msg['id'] ?? '';
            $author = ($msg['author'] === 'user') ? 'client' : 'manager';
            $text = $msg['text'] ?? '';
            $created_at = isset($msg['created']) ? date('Y-m-d H:i:s', strtotime($msg['created'])) : current_time('mysql');

            // Используем replace, чтобы не дублировать сообщения при повторной синхронизации
            // Для этого нужен UNIQUE KEY по message_id (создан в install.php)
            $wpdb->replace($table, [
                'dialog_id' => $dialog_id,
                'message_id' => $message_id,
                'author' => $author,
                'text' => $text,
                'created_at' => $created_at
            ]);
        }
    }

    // --- AJAX ОБРАБОТЧИКИ ---

    public function ajax_auth() {
        check_ajax_referer('akpp_crm_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Недостаточно прав']);
        }

        $this->client_id = sanitize_text_field($_POST['client_id']);
        $this->client_secret = sanitize_text_field($_POST['client_secret']);
        
        update_option('akpp_avito_client_id', $this->client_id);
        update_option('akpp_avito_client_secret', $this->client_secret);

        $token = $this->get_access_token();
        if ($token) {
            wp_send_json_success(['message' => 'Авторизация успешна! Токен получен.']);
        } else {
            wp_send_json_error(['message' => 'Ошибка авторизации. Проверьте Client ID и Secret.']);
        }
    }

    public function ajax_get_dialogs() {
        check_ajax_referer('akpp_crm_nonce', 'nonce');
        $result = $this->get_dialogs();
        
        if (isset($result['error'])) {
            wp_send_json_error(['message' => $result['error']]);
        } else {
            wp_send_json_success(['dialogs' => $result]);
        }
    }

    public function ajax_get_messages() {
        check_ajax_referer('akpp_crm_nonce', 'nonce');
        $dialog_id = sanitize_text_field($_POST['dialog_id']);
        $result = $this->get_messages($dialog_id);
        
        if (isset($result['error'])) {
            wp_send_json_error(['message' => $result['error']]);
        } else {
            wp_send_json_success(['messages' => $result]);
        }
    }
}

// Инициализация
new AKPP_Avito();
