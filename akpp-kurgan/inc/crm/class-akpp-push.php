<?php
if (!defined('ABSPATH')) exit;

class AKPP_Push {
    
    private $server_key;

    public function __construct() {
        // Загружаем Server Key из настроек WordPress
        $this->server_key = get_option('akpp_fcm_server_key', '');

        // AJAX хуки
        add_action('wp_ajax_akpp_save_push_token', [$this, 'ajax_save_push_token']);
        add_action('wp_ajax_nopriv_akpp_save_push_token', [$this, 'ajax_save_push_token']); // Для неавторизованных (если нужно)
        add_action('wp_ajax_akpp_send_push_notification', [$this, 'ajax_send_push_notification']);
    }

    /**
     * Сохранение FCM токена устройства в базу данных
     */
    public function save_push_token($token, $device_type = 'android') {
        if (empty($token)) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'akpp_push_tokens';
        $user_id = get_current_user_id();

        // Проверяем, есть ли уже такой токен
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE token = %s",
            $token
        ));

        if ($exists) {
            // Обновляем привязку к пользователю, если он вошел в систему
            $wpdb->update(
                $table,
                ['user_id' => $user_id],
                ['token' => $token]
            );
            return true;
        } else {
            // Добавляем новый токен
            $wpdb->insert(
                $table,
                [
                    'user_id' => $user_id,
                    'token' => sanitize_text_field($token),
                    'device_type' => sanitize_text_field($device_type)
                ],
                ['%d', '%s', '%s']
            );
            return true;
        }
    }

    /**
     * Отправка push-уведомления через FCM API
     *
     * @param array $tokens Массив FCM токенов
     * @param string $title Заголовок уведомления
     * @param string $body Текст уведомления
     * @param array $data Дополнительные данные (например, ID сделки для глубокой ссылки)
     * @return array Результат отправки
     */
    public function send_notification($tokens, $title, $body, $data = []) {
        if (empty($this->server_key)) {
            return ['error' => 'FCM Server Key не настроен в админке'];
        }

        if (empty($tokens)) {
            return ['error' => 'Список токенов пуст'];
        }

        // Ограничение FCM: максимум 1000 токенов за один запрос
        $tokens = array_slice($tokens, 0, 1000);

        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'color' => '#00ff88' // Цвет иконки для Android
            ],
            'data' => $data, // Скрытые данные для приложения (например, 'deal_id' => 123)
            'priority' => 'high'
        ];

        $response = wp_remote_post('https://fcm.googleapis.com/fcm/send', [
            'headers' => [
                'Authorization' => 'key=' . $this->server_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            return ['error' => 'Ошибка соединения с FCM: ' . $response->get_error_message()];
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($result['error'])) {
            return ['error' => 'FCM API Error: ' . $result['error']];
        }

        // Очистка невалидных токенов из базы (опционально, но полезно)
        if (isset($result['results']) && is_array($result['results'])) {
            $this->cleanup_invalid_tokens($tokens, $result['results']);
        }

        return [
            'success' => true,
            'success_count' => $result['success'] ?? 0,
            'failure_count' => $result['failure'] ?? 0,
            'details' => $result
        ];
    }

    /**
     * Отправка уведомления конкретному пользователю по его WP User ID
     */
    public function notify_user($user_id, $title, $body, $data = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_push_tokens';
        
        $tokens = $wpdb->get_col($wpdb->prepare(
            "SELECT token FROM $table WHERE user_id = %d",
            $user_id
        ));

        if (!empty($tokens)) {
            return $this->send_notification($tokens, $title, $body, $data);
        }

        return ['error' => 'У пользователя нет сохраненных push-токенов'];
    }

    /**
     * Массовая рассылка всем активным устройствам
     */
    public function broadcast_notification($title, $body, $data = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_push_tokens';
        
        $tokens = $wpdb->get_col("SELECT token FROM $table");
        
        if (!empty($tokens)) {
            return $this->send_notification($tokens, $title, $body, $data);
        }

        return ['error' => 'Нет сохраненных токенов для рассылки'];
    }

    /**
     * Очистка базы от невалидных токенов (например, "NotRegistered" или "InvalidRegistration")
     */
    private function cleanup_invalid_tokens($sent_tokens, $results) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_push_tokens';

        foreach ($results as $index => $result) {
            if (isset($result['error']) && in_array($result['error'], ['NotRegistered', 'InvalidRegistration', 'MismatchSenderId'])) {
                $invalid_token = $sent_tokens[$index];
                $wpdb->delete($table, ['token' => $invalid_token]);
            }
        }
    }

    // --- AJAX ОБРАБОТЧИКИ ---

    public function ajax_save_push_token() {
        // Для сохранения токена можно использовать упрощенную проверку или свой nonce
        $token = sanitize_text_field($_POST['token'] ?? '');
        $device_type = sanitize_text_field($_POST['device_type'] ?? 'android');

        if ($this->save_push_token($token, $device_type)) {
            wp_send_json_success(['message' => 'Токен успешно сохранен']);
        } else {
            wp_send_json_error(['message' => 'Ошибка сохранения токена']);
        }
    }

    public function ajax_send_push_notification() {
        check_ajax_referer('akpp_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Недостаточно прав']);
        }

        $title = sanitize_text_field($_POST['title'] ?? 'Тестовое уведомление');
        $body = sanitize_text_field($_POST['body'] ?? 'Это тестовое push-уведомление из АКПП45 CRM');
        $target = sanitize_text_field($_POST['target'] ?? 'all'); // 'all' или 'user'
        $user_id = intval($_POST['user_id'] ?? 0);

        $data = ['screen' => 'dashboard', 'timestamp' => time()];

        if ($target === 'user' && $user_id > 0) {
            $result = $this->notify_user($user_id, $title, $body, $data);
        } else {
            $result = $this->broadcast_notification($title, $body, $data);
        }

        if (isset($result['error'])) {
            wp_send_json_error(['message' => $result['error']]);
        } else {
            wp_send_json_success([
                'message' => 'Уведомление отправлено',
                'details' => $result
            ]);
        }
    }
}

// Инициализация
new AKPP_Push();
