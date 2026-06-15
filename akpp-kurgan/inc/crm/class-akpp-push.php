<?php
/**
 * Класс для управления Push уведомлениями (FCM)
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_Push {
    
    private static $instance = null;
    private $fcm_server_key = '';
    private $fcm_api_url = 'https://fcm.googleapis.com/v1/projects/';
    private $project_id = '';
    
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
        $this->fcm_server_key = get_option('akpp_fcm_server_key', '');
        $this->project_id = get_option('akpp_fcm_project_id', '');
    }
    
    /**
     * Сохранение настроек FCM
     */
    public function save_settings($server_key, $project_id) {
        update_option('akpp_fcm_server_key', sanitize_text_field($server_key));
        update_option('akpp_fcm_project_id', sanitize_text_field($project_id));
        $this->fcm_server_key = $server_key;
        $this->project_id = $project_id;
        return true;
    }
    
    /**
     * Сохранение push токена пользователя
     */
    public function save_token($user_id, $token, $device_type = 'web') {
        global $wpdb;
        
        $table_tokens = $wpdb->prefix . 'akpp_push_tokens';
        
        $wpdb->replace(
            $table_tokens,
            [
                'user_id' => $user_id,
                'token' => $token,
                'device_type' => $device_type,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s']
        );
        
        $this->log_event("Push токен сохранен для пользователя {$user_id}");
        return true;
    }
    
    /**
     * Удаление токена пользователя
     */
    public function delete_token($user_id, $token) {
        global $wpdb;
        
        $table_tokens = $wpdb->prefix . 'akpp_push_tokens';
        
        $wpdb->delete(
            $table_tokens,
            [
                'user_id' => $user_id,
                'token' => $token
            ],
            ['%d', '%s']
        );
        
        return true;
    }
    
    /**
     * Отправка уведомления сотруднику
     */
    public function send_to_employee($employee_id, $title, $body, $data = []) {
        global $wpdb;
        
        $table_tokens = $wpdb->prefix . 'akpp_push_tokens';
        
        $tokens = $wpdb->get_col($wpdb->prepare(
            "SELECT token FROM {$table_tokens} WHERE user_id = %d",
            $employee_id
        ));
        
        if (empty($tokens)) {
            $this->log_event("Нет push токенов для сотрудника {$employee_id}");
            return false;
        }
        
        return $this->send_notification($tokens, $title, $body, $data);
    }
    
    /**
     * Отправка уведомления клиенту
     */
    public function send_to_client($client_id, $title, $body, $data = []) {
        global $wpdb;
        
        $table_tokens = $wpdb->prefix . 'akpp_push_tokens';
        
        $tokens = $wpdb->get_col($wpdb->prepare(
            "SELECT token FROM {$table_tokens} WHERE user_id = %d",
            $client_id
        ));
        
        if (empty($tokens)) {
            $this->log_event("Нет push токенов для клиента {$client_id}");
            return false;
        }
        
        return $this->send_notification($tokens, $title, $body, $data);
    }
    
    /**
     * Отправка уведомления всем пользователям с ролью
     */
    public function send_to_role($role, $title, $body, $data = []) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'akpp_site_users';
        $table_tokens = $wpdb->prefix . 'akpp_push_tokens';
        
        $users = $wpdb->get_col($wpdb->prepare(
            "SELECT u.id FROM {$table_users} u 
            WHERE u.role = %s AND u.status = 'active'",
            $role
        ));
        
        if (empty($users)) {
            return false;
        }
        
        $tokens = $wpdb->get_col(
            "SELECT token FROM {$table_tokens} 
            WHERE user_id IN (" . implode(',', array_map('intval', $users)) . ")"
        );
        
        if (empty($tokens)) {
            return false;
        }
        
        return $this->send_notification($tokens, $title, $body, $data);
    }
    
    /**
     * Отправка уведомления через FCM (Legacy API)
     */
    public function send_notification($tokens, $title, $body, $data = []) {
        if (empty($this->fcm_server_key)) {
            $this->log_error('FCM Server Key не настроен');
            return false;
        }
        
        if (empty($tokens)) {
            return false;
        }
        
        $tokens = (array)$tokens;
        
        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'icon' => 'https://akpp45.ru/favicon.ico',
                'click_action' => isset($data['url']) ? $data['url'] : home_url('/crm-profile')
            ],
            'data' => array_merge($data, [
                'title' => $title,
                'body' => $body,
                'click_action' => isset($data['url']) ? $data['url'] : home_url('/crm-profile')
            ]),
            'priority' => 'high'
        ];
        
        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'key=' . $this->fcm_server_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload)
        ];
        
        $response = wp_remote_post('https://fcm.googleapis.com/fcm/send', $args);
        
        if (is_wp_error($response)) {
            $this->log_error('Ошибка отправки push: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (isset($result['failure']) && $result['failure'] > 0) {
            $this->handle_failed_tokens($tokens, $result);
        }
        
        $this->log_event("Push отправлен: {$title} - {$body}");
        return true;
    }
    
    /**
     * Отправка через FCM v1 API (более новый)
     */
    public function send_notification_v1($token, $title, $body, $data = []) {
        if (empty($this->project_id)) {
            $this->log_error('FCM Project ID не настроен');
            return false;
        }
        
        $access_token = $this->get_fcm_access_token();
        if (!$access_token) {
            return false;
        }
        
        $url = $this->fcm_api_url . $this->project_id . '/messages:send';
        
        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'high'
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10'
                    ]
                ]
            ]
        ];
        
        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload)
        ];
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('Ошибка отправки push v1: ' . $response->get_error_message());
            return false;
        }
        
        return true;
    }
    
    /**
     * Получение access token для FCM v1
     */
    private function get_fcm_access_token() {
        $credentials_file = WP_CONTENT_DIR . '/fcm-credentials.json';
        
        if (!file_exists($credentials_file)) {
            $this->log_error('Файл credentials.json не найден');
            return false;
        }
        
        $credentials = json_decode(file_get_contents($credentials_file), true);
        
        $jwt = $this->generate_jwt($credentials);
        
        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ])
        ];
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return isset($body['access_token']) ? $body['access_token'] : false;
    }
    
    /**
     * Генерация JWT для FCM v1
     */
    private function generate_jwt($credentials) {
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $claims = json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => time() + 3600,
            'iat' => time()
        ]);
        
        $base64_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64_claims = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claims));
        
        $signature_input = $base64_header . '.' . $base64_claims;
        
        $private_key = openssl_get_privatekey($credentials['private_key']);
        openssl_sign($signature_input, $signature, $private_key, 'SHA256');
        
        $base64_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $signature_input . '.' . $base64_signature;
    }
    
    /**
     * Обработка неудачных токенов (удаляем невалидные)
     */
    private function handle_failed_tokens($tokens, $result) {
        global $wpdb;
        
        $table_tokens = $wpdb->prefix . 'akpp_push_tokens';
        
        if (isset($result['results']) && is_array($result['results'])) {
            foreach ($result['results'] as $index => $res) {
                if (isset($res['error']) && isset($tokens[$index])) {
                    $error = $res['error'];
                    if ($error === 'NotRegistered' || $error === 'InvalidRegistration') {
                        $wpdb->delete(
                            $table_tokens,
                            ['token' => $tokens[$index]],
                            ['%s']
                        );
                        $this->log_event("Удален невалидный токен: {$tokens[$index]}");
                    }
                }
            }
        }
    }
    
    /**
     * Тестовое уведомление
     */
    public function test_notification($token) {
        return $this->send_notification([$token], 'Тестовое уведомление', 'Если вы видите это сообщение, push уведомления работают корректно!');
    }
    
    /**
     * Уведомление о новом сообщении в чате
     */
    public function notify_new_message($user_id, $sender_name, $message_preview) {
        return $this->send_to_client(
            $user_id,
            '📩 Новое сообщение от ' . $sender_name,
            $message_preview,
            ['type' => 'chat', 'action' => 'open_chat']
        );
    }
    
    /**
     * Уведомление об изменении статуса сделки
     */
    public function notify_deal_status_change($client_id, $deal_id, $old_status, $new_status) {
        $status_labels = [
            'new' => 'Новая',
            'diagnostic' => 'Диагностика',
            'in_work' => 'В работе',
            'completed' => 'Выполнена',
            'rejected' => 'Отклонена'
        ];
        
        $old_label = $status_labels[$old_status] ?? $old_status;
        $new_label = $status_labels[$new_status] ?? $new_status;
        
        return $this->send_to_client(
            $client_id,
            '🔄 Статус заказа изменен',
            "Статус вашей сделки №{$deal_id}: {$old_label} → {$new_label}",
            ['type' => 'deal', 'deal_id' => $deal_id]
        );
    }
    
    private function log_error($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_PUSH] ОШИБКА: ' . $message);
        }
    }
    
    private function log_event($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_PUSH] СОБЫТИЕ: ' . $message);
        }
    }
}
