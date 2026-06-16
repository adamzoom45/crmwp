<?php
/**
 * Класс для обработки Webhook запросов от Авито
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_Webhook {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', [$this, 'register_endpoint']);
    }
    
    /**
     * Регистрация REST endpoint
     */
    public function register_endpoint() {
        register_rest_route('akpp/v1', '/avito-webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_request'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * Обработка входящего webhook запроса
     */
    public function handle_request($request) {
        $this->log_event('Получен webhook запрос');
        
        $body = $request->get_body();
        $data = json_decode($body, true);
        
        if (!$data) {
            $this->log_error('Неверный JSON: ' . $body);
            return new WP_REST_Response(['error' => 'Invalid JSON'], 400);
        }
        
        // Проверка подписи (если настроена)
        if (!$this->verify_signature($body)) {
            $this->log_error('Неверная подпись webhook');
            return new WP_REST_Response(['error' => 'Invalid signature'], 401);
        }
        
        // Обработка в зависимости от типа события
        $event_type = $data['type'] ?? $data['event_type'] ?? '';
        
        switch ($event_type) {
            case 'message':
            case 'new_message':
                $this->handle_new_message($data);
                break;
            case 'dialog':
            case 'new_dialog':
                $this->handle_new_dialog($data);
                break;
            default:
                $this->handle_generic_message($data);
        }
        
        return new WP_REST_Response(['status' => 'ok'], 200);
    }
    
    /**
     * Обработка нового сообщения
     */
    private function handle_new_message($data) {
        global $wpdb;
        
        $dialog_id = $data['dialog_id'] ?? $data['chat_id'] ?? '';
        $message_id = $data['message_id'] ?? $data['id'] ?? '';
        $text = $data['text'] ?? $data['message'] ?? '';
        $sender_id = $data['sender_id'] ?? $data['from'] ?? '';
        $sender_name = $data['sender_name'] ?? $data['from_name'] ?? '';
        $created_at = $data['created_at'] ?? $data['timestamp'] ?? current_time('mysql');
        
        if (empty($dialog_id) || empty($text)) {
            $this->log_error('Недостаточно данных для сообщения');
            return;
        }
        
        // Сохраняем в кэш сообщений
        $table_cache = $wpdb->prefix . 'akpp_avito_messages_cache';
        
        $wpdb->insert(
            $table_cache,
            [
                'dialog_id' => $dialog_id,
                'message_id' => $message_id,
                'sender_id' => $sender_id,
                'sender_name' => $sender_name,
                'message_text' => $text,
                'is_incoming' => 1,
                'created_at' => $created_at
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );
        
        // Сохраняем в общий чат CRM
        $this->save_to_crm_chat($dialog_id, $sender_id, $sender_name, $text, $created_at);
        
        // Обновляем диалог
        $this->update_dialog($dialog_id, $text, $created_at);
        
        $this->log_event("Сообщение сохранено из диалога {$dialog_id}");
    }
    
    /**
     * Сохранение в общий чат CRM
     */
    private function save_to_crm_chat($dialog_id, $sender_id, $sender_name, $text, $created_at) {
        global $wpdb;
        
        // Получаем или создаем пользователя
        $user_id = $this->get_or_create_avito_user($sender_id, $sender_name);
        
        // Получаем ID гида для диалога
        $guide_id = $this->get_assigned_guide($dialog_id);
        
        if (!$guide_id) {
            return;
        }
        
        $table_chat = $wpdb->prefix . 'akpp_chat_messages';
        
        $wpdb->insert(
            $table_chat,
            [
                'sender_id' => $user_id,
                'receiver_id' => $guide_id,
                'message' => $text,
                'is_read' => 0,
                'source' => 'avito',
                'dialog_id' => $dialog_id,
                'created_at' => $created_at
            ],
            ['%d', '%d', '%s', '%d', '%s', '%s', '%s']
        );
        
        // Отправляем уведомление гиду
        $this->notify_guide($guide_id, $sender_name, $text);
    }
    
    /**
     * Получение или создание пользователя Авито
     */
    private function get_or_create_avito_user($avito_id, $name) {
        global $wpdb;
        $table_users = $wpdb->prefix . 'akpp_site_users';
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_users} WHERE avito_id = %s",
            $avito_id
        ));
        
        if ($user) {
            return $user->id;
        }
        
        $wpdb->insert(
            $table_users,
            [
                'name' => $name,
                'avito_id' => $avito_id,
                'role' => 'client',
                'status' => 'active',
                'source' => 'avito',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Получение назначенного гида
     */
    private function get_assigned_guide($dialog_id) {
        global $wpdb;
        $table_dialogs = $wpdb->prefix . 'akpp_avito_dialogs';
        
        $dialog = $wpdb->get_row($wpdb->prepare(
            "SELECT assigned_guide_id FROM {$table_dialogs} WHERE dialog_id = %s",
            $dialog_id
        ));
        
        if ($dialog && $dialog->assigned_guide_id) {
            return $dialog->assigned_guide_id;
        }
        
        // Назначаем первого активного гида
        $table_employees = $wpdb->prefix . 'akpp_employees';
        $guide = $wpdb->get_row(
            "SELECT id FROM {$table_employees} 
            WHERE role = 'guide' AND is_active = 1 
            ORDER BY id ASC LIMIT 1"
        );
        
        if ($guide) {
            $wpdb->update(
                $table_dialogs,
                ['assigned_guide_id' => $guide->id],
                ['dialog_id' => $dialog_id],
                ['%d'],
                ['%s']
            );
            return $guide->id;
        }
        
        return false;
    }
    
    /**
     * Обновление диалога
     */
    private function update_dialog($dialog_id, $last_message, $last_message_time) {
        global $wpdb;
        $table_dialogs = $wpdb->prefix . 'akpp_avito_dialogs';
        
        $wpdb->update(
            $table_dialogs,
            [
                'last_message' => $last_message,
                'last_message_time' => $last_message_time,
                'updated_at' => current_time('mysql')
            ],
            ['dialog_id' => $dialog_id],
            ['%s', '%s', '%s'],
            ['%s']
        );
    }
    
    /**
     * Обработка нового диалога
     */
    private function handle_new_dialog($data) {
        global $wpdb;
        $table_dialogs = $wpdb->prefix . 'akpp_avito_dialogs';
        
        $dialog_id = $data['dialog_id'] ?? $data['chat_id'] ?? '';
        $user_id = $data['user_id'] ?? $data['client_id'] ?? '';
        $user_name = $data['user_name'] ?? $data['client_name'] ?? '';
        
        if (empty($dialog_id)) {
            return;
        }
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_dialogs} WHERE dialog_id = %s",
            $dialog_id
        ));
        
        if (!$exists) {
            $wpdb->insert(
                $table_dialogs,
                [
                    'dialog_id' => $dialog_id,
                    'user_id' => $user_id,
                    'user_name' => $user_name,
                    'is_active' => 1,
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%d', '%s']
            );
            
            // Создаем лид
            $this->create_lead_from_dialog($dialog_id, $user_name);
        }
    }
    
    /**
     * Создание лида из диалога
     */
    private function create_lead_from_dialog($dialog_id, $user_name) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'akpp_leads';
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_leads} WHERE avito_dialog_id = %s",
            $dialog_id
        ));
        
        if (!$exists) {
            $wpdb->insert(
                $table_leads,
                [
                    'client_name' => $user_name,
                    'source' => 'avito',
                    'avito_dialog_id' => $dialog_id,
                    'status' => 'new',
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );
        }
    }
    
    /**
     * Обработка generic сообщения
     */
    private function handle_generic_message($data) {
        if (isset($data['message']) || isset($data['text'])) {
            $this->handle_new_message($data);
        } elseif (isset($data['dialog_id']) || isset($data['chat_id'])) {
            $this->handle_new_dialog($data);
        } else {
            $this->log_error('Неизвестный тип webhook');
        }
    }
    
    /**
     * Уведомление гида
     */
    private function notify_guide($guide_id, $sender_name, $message) {
        if (class_exists('AKPP_Push')) {
            $push = AKPP_Push::get_instance();
            $push->send_to_employee(
                $guide_id,
                '📩 Новое сообщение из Авито',
                "{$sender_name}: " . mb_substr($message, 0, 50),
                ['type' => 'chat', 'action' => 'open_avito_chat']
            );
        }
        
        if (class_exists('AKPP_Telegram')) {
            global $wpdb;
            $employee = $wpdb->get_row($wpdb->prepare(
                "SELECT telegram_chat_id FROM {$wpdb->prefix}akpp_employees WHERE id = %d",
                $guide_id
            ));
            
            if ($employee && $employee->telegram_chat_id) {
                $telegram = AKPP_Telegram::get_instance();
                $telegram->send_message(
                    $employee->telegram_chat_id,
                    "📩 Новое сообщение из Авито\n\nОт: {$sender_name}\nСообщение: " . mb_substr($message, 0, 100)
                );
            }
        }
    }
    
    /**
     * Проверка подписи webhook
     */
    private function verify_signature($body) {
        $secret = get_option('akpp_avito_webhook_secret', '');
        
        if (empty($secret)) {
            return true;
        }
        
        $signature = isset($_SERVER['HTTP_X_WEBHOOK_SIGNATURE']) ? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] : '';
        
        if (empty($signature)) {
            return false;
        }
        
        $expected = hash_hmac('sha256', $body, $secret);
        
        return hash_equals($expected, $signature);
    }
    
    private function log_error($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_WEBHOOK] ОШИБКА: ' . $message);
        }
    }
    
    private function log_event($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_WEBHOOK] СОБЫТИЕ: ' . $message);
        }
    }
}
