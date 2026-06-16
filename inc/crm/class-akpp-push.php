<?php
/**
 * Класс для управления Push уведомлениями через Firebase Cloud Messaging
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_Push {
    
    private static $instance = null;
    private $fcm_server_key = '';
    private $fcm_api_url = 'https://fcm.googleapis.com/fcm/send';
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_settings();
        add_action('wp_ajax_akpp_save_push_token', [$this, 'ajax_save_push_token']);
        add_action('wp_ajax_nopriv_akpp_save_push_token', [$this, 'ajax_save_push_token']);
        add_action('wp_ajax_akpp_delete_push_token', [$this, 'ajax_delete_push_token']);
        add_action('wp_ajax_nopriv_akpp_delete_push_token', [$this, 'ajax_delete_push_token']);
    }
    
    /**
     * Загрузка настроек
     */
    private function load_settings() {
        $this->fcm_server_key = get_option('akpp_fcm_server_key', '');
    }
    
    /**
     * Сохранение настроек FCM
     */
    public function save_settings($server_key) {
        update_option('akpp_fcm_server_key', sanitize_text_field($server_key));
        $this->fcm_server_key = $server_key;
        return true;
    }
    
    /**
     * Сохранение push токена (AJAX)
     */
    public function ajax_save_push_token() {
        if (!check_ajax_referer('akpp_save_push_token_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $device_type = isset($_POST['device_type']) ? sanitize_text_field($_POST['device_type']) : 'web';
        
        if (empty($token)) {
            wp_send_json_error('Token не передан');
            return;
        }
        
        $user_id = $this->get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error('Пользователь не авторизован');
            return;
        }
        
        $this->save_token($user_id, $token, $device_type);
        
        wp_send_json_success(['message' => 'Push токен сохранен']);
    }
    
    /**
     * Удаление push токена (AJAX)
     */
    public function ajax_delete_push_token() {
        if (!check_ajax_referer('akpp_delete_push_token_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        
        if (empty($token)) {
            wp_send_json_error('Token не передан');
            return;
        }
        
        $user_id = $this->get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error('Пользователь не авторизован');
            return;
        }
        
        $this->delete_token($user_id, $token);
        
        wp_send_json_success(['message' => 'Push токен удален']);
    }
    
    /**
     * Сохранение токена в БД
     */
    public function save_token($user_id, $token, $device_type = 'web') {
        global $wpdb;
        $table_tokens = $wpdb->prefix . 'akpp_push_tokens';
        
        // Удаляем старый токен, если существует
        $wpdb->delete($table_tokens, ['token' => $token]);
        
        // Сохраняем новый
        $wpdb->insert(
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
     * Удаление токена из БД
     */
    public function delete_token($user_id, $token) {
        global $wpdb;
        $table_tokens = $wpdb->prefix . 'akpp_push_tokens';
        
        $wpdb->delete($table_tokens, ['token' => $token]);
        
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
     * Отправка уведомления через FCM
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
                'icon' => home_url('/favicon.ico'),
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
        
        $response = wp_remote_post($this->fcm_api_url, $args);
        
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
     * Обработка неудачных токенов
     */
    private function handle_failed_tokens($tokens, $result) {
        global $wpdb;
        $table_tokens = $wpdb->prefix . 'akpp_push_tokens';
        
        if (isset($result['results']) && is_array($result['results'])) {
            foreach ($result['results'] as $index => $res) {
                if (isset($res['error']) && isset($tokens[$index])) {
                    $error = $res['error'];
                    if ($error === 'NotRegistered' || $error === 'InvalidRegistration') {
                        $wpdb->delete($table_tokens, ['token' => $tokens[$index]]);
                        $this->log_event("Удален невалидный токен: {$tokens[$index]}");
                    }
                }
            }
        }
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
     * Уведомление о новом лиде (для гида)
     */
    public function notify_new_lead($guide_id, $client_name, $client_phone) {
        return $this->send_to_employee(
            $guide_id,
            '🆕 Новый лид!',
            "{$client_name}, {$client_phone} - ожидает обработки",
            ['type' => 'lead', 'action' => 'view_lead']
        );
    }
    
    /**
     * Уведомление о новой сделке
     */
    public function notify_new_deal($employee_id, $deal_id, $client_name) {
        return $this->send_to_employee(
            $employee_id,
            '📋 Новая сделка',
            "Сделка #{$deal_id} от клиента {$client_name}",
            ['type' => 'deal', 'deal_id' => $deal_id]
        );
    }
    
    /**
     * Уведомление об изменении статуса сделки (для клиента)
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
            ['type' => 'deal', 'deal_id' => $deal_id, 'status' => $new_status]
        );
    }
    
    /**
     * Получение ID текущего пользователя
     */
    private function get_current_user_id() {
        if (function_exists('get_current_user_id') && get_current_user_id() > 0) {
            return get_current_user_id();
        }
        
        if (class_exists('AKPP_Auth') && AKPP_Auth::is_logged_in()) {
            $user = AKPP_Auth::get_current_user();
            return $user ? $user->id : 0;
        }
        
        return 0;
    }
    
    /**
     * Логирование ошибок
     */
    private function log_error($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_PUSH] ОШИБКА: ' . $message);
        }
    }
    
    /**
     * Логирование событий
     */
    private function log_event($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_PUSH] СОБЫТИЕ: ' . $message);
        }
    }
}
