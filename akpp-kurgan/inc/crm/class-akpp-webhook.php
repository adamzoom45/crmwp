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
    
    /**
     * Экземпляр класса (Singleton)
     */
    private static $instance = null;
    
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
        $this->register_endpoint();
    }
    
    /**
     * Регистрация REST endpoint для webhook
     */
    private function register_endpoint() {
        add_action('rest_api_init', function() {
            register_rest_route('akpp/v1', '/avito-webhook', [
                'methods' => 'POST',
                'callback' => [$this, 'handle_request'],
                'permission_callback' => '__return_true'
            ]);
        });
    }
    
    /**
     * Обработка входящего webhook запроса
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_request($request) {
        $this->log_event('Получен webhook запрос');
        
        // Получаем тело запроса
        $body = $request->get_body();
        $data = json_decode($body, true);
        
        if (!$data) {
            $this->log_error('Неверный JSON: ' . $body);
            return new WP_REST_Response(['error' => 'Invalid JSON'], 400);
        }
        
        // Проверяем подпись (если Авито отправляет)
        $signature = $request->get_header('X-Webhook-Signature');
        if (!$this->verify_signature($body, $signature)) {
            $this->log_error('Неверная подпись webhook');
            return new WP_REST_Response(['error' => 'Invalid signature'], 401);
        }
        
        // Обрабатываем в зависимости от типа события
        $event_type = $data['type'] ?? $data['event_type'] ?? '';
        
        switch ($event_type) {
            case 'message':
            case 'new_message':
                $result = $this->handle_new_message($data);
                break;
            case 'dialog':
            case 'new_dialog':
                $result = $this->handle_new_dialog($data);
                break;
            default:
                $result = $this->handle_generic_message($data);
        }
        
        if ($result) {
            $this->log_event('Webhook обработан успешно');
            return new WP_REST_Response(['status' => 'ok'], 200);
        } else {
            $this->log_error('Ошибка обработки webhook');
            return new WP_REST_Response(['error' => 'Processing error'], 500);
        }
    }
    
    /**
     * Обработка нового сообщения
     * 
     * @param array $data Данные сообщения
     * @return bool
     */
    private function handle_new_message($data) {
        global $wpdb;
        
        // Извлекаем данные сообщения
        $message_id = $data['message_id'] ?? $data['id'] ?? '';
        $dialog_id = $data['dialog_id'] ?? $data['chat_id'] ?? '';
        $text = $data['text'] ?? $data['message'] ?? '';
        $sender_id = $data['sender_id'] ?? $data['from'] ?? '';
        $sender_name = $data['sender_name'] ?? $data['from_name'] ?? '';
        $created_at = $data['created_at'] ?? $data['timestamp'] ?? current_time('mysql');
        
        if (empty($message_id) || empty($dialog_id) || empty($text)) {
            $this->log_error('Недостаточно данных для сообщения: ' . json_encode($data));
            return false;
        }
        
        // Сохраняем в кэш сообщений
        $table_cache = $wpdb->prefix . 'akpp_avito_messages_cache';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_cache} WHERE message_id = %s",
            $message_id
        ));
        
        if (!$exists) {
            $wpdb->insert(
                $table_cache,
                [
                    'dialog_id' => $dialog_id,
                    'message_id' => $message_id,
                    'sender_id' => $sender_id,
                    'sender_name' => $sender_name,
                    'message_text' => $text,
                    'is_incoming' => 1,
                    'created_at' => $created_at,
                    'processed_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
            );
        }
        
        // Сохраняем в общий чат CRM
        $table_chat = $wpdb->prefix . 'akpp_chat_messages';
        
        // Получаем или создаем пользователя для отправителя
        $user_id = $this->get_or_create_avito_user($sender_id, $sender_name);
        
        // Получаем ID сотрудника (гида) для диалога
        $guide_id = $this->get_assigned_guide($dialog_id);
        
        if ($guide_id) {
            $wpdb->insert(
                $table_chat,
                [
                    'sender_id' => $user_id,
                    'receiver_id' => $guide_id,
                    'message' => $text,
                    'is_read' => 0,
                    'source' => 'avito',
                    'created_at' => $created_at
                ],
                ['%d', '%d', '%s', '%d', '%s', '%s']
            );
        }
        
        // Обновляем диалог
        $this->update_dialog($dialog_id, $text, $created_at);
        
        // Отправляем уведомление гиду
        if ($guide_id) {
            $this->notify_guide($guide_id, $text, $sender_name);
        }
        
        return true;
    }
    
    /**
     * Обработка нового диалога
     * 
     * @param array $data Данные диалога
     * @return bool
     */
    private function handle_new_dialog($data) {
        global $wpdb;
        
        $dialog_id = $data['dialog_id'] ?? $data['chat_id'] ?? '';
        $user_id = $data['user_id'] ?? $data['client_id'] ?? '';
        $user_name = $data['user_name'] ?? $data['client_name'] ?? '';
        $user_phone = $data['user_phone'] ?? '';
        
        if (empty($dialog_id)) {
            $this->log_error('Недостаточно данных для диалога: ' . json_encode($data));
            return false;
        }
        
        $table_dialogs = $wpdb->prefix . 'akpp_avito_dialogs';
        
        $wpdb->insert(
            $table_dialogs,
            [
                'dialog_id' => $dialog_id,
                'user_id' => $user_id,
                'user_name' => $user_name,
                'user_phone' => $user_phone,
                'last_message' => '',
                'last_message_time' => current_time('mysql'),
                'is_active' => 1,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );
        
        // Создаем лид
        $this->create_lead_from_dialog($dialog_id, $user_name, $user_phone);
        
        return true;
    }
    
    /**
     * Обработка generic сообщения (если тип не указан)
     * 
     * @param array $data Данные
     * @return bool
     */
    private function handle_generic_message($data) {
        // Пытаемся определить тип по структуре
        if (isset($data['message']) || isset($data['text'])) {
            return $this->handle_new_message($data);
        } elseif (isset($data['dialog_id']) || isset($data['chat_id'])) {
            return $this->handle_new_dialog($data);
        }
        
        $this->log_error('Неизвестный тип webhook: ' . json_encode($data));
        return false;
    }
    
    /**
     * Получение или создание пользователя Авито
     * 
     * @param string $avito_id ID пользователя в Авито
     * @param string $name Имя
     * @return int ID пользователя в CRM
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
        
        // Создаем нового пользователя
        $wpdb->insert(
            $table_users,
            [
                'name' => $name,
                'avito_id' => $avito_id,
                'source' => 'avito',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s']
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Получение назначенного гида для диалога
     * 
     * @param string $dialog_id ID диалога
     * @return int|false ID гида или false
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
     * 
     * @param string $dialog_id ID диалога
     * @param string $last_message Последнее сообщение
     * @param string $last_message_time Время
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
     * Создание лида из диалога
     * 
     * @param string $dialog_id ID диалога
     * @param string $user_name Имя пользователя
     * @param string $user_phone Телефон
     */
    private function create_lead_from_dialog($dialog_id, $user_name, $user_phone) {
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
                    'client_phone' => $user_phone,
                    'source' => 'avito',
                    'avito_dialog_id' => $dialog_id,
                    'status' => 'new',
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s']
            );
        }
    }
    
    /**
     * Уведомление гида о новом сообщении
     * 
     * @param int $guide_id ID гида
     * @param string $message Текст сообщения
     * @param string $sender_name Имя отправителя
     */
    private function notify_guide($guide_id, $message, $sender_name) {
        // Push уведомление
        if (class_exists('AKPP_Push')) {
            $push = AKPP_Push::get_instance();
            $push->send_to_employee(
                $guide_id,
                'Новое сообщение из Авито',
                "{$sender_name}: " . substr($message, 0, 50)
            );
        }
        
        // Email уведомление (опционально)
        $this->log_event("Уведомление отправлено гиду {$guide_id}");
    }
    
    /**
     * Проверка подписи webhook
     * 
     * @param string $body Тело запроса
     * @param string $signature Подпись
     * @return bool
     */
    private function verify_signature($body, $signature) {
        // Если Авито не отправляет подпись - пропускаем проверку
        if (empty($signature)) {
            $this->log_event('Подпись отсутствует, проверка пропущена');
            return true;
        }
        
        // Получаем секрет из настроек
        $secret = get_option('akpp_avito_webhook_secret', '');
        
        if (empty($secret)) {
            $this->log_event('Секрет webhook не настроен, проверка пропущена');
            return true;
        }
        
        $expected = hash_hmac('sha256', $body, $secret);
        
        if (!hash_equals($expected, $signature)) {
            $this->log_error("Неверная подпись: ожидалось {$expected}, получено {$signature}");
            return false;
        }
        
        return true;
    }
    
    /**
     * Логирование ошибок
     */
    private function log_error($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_WEBHOOK] ОШИБКА: ' . $message);
        }
    }
    
    /**
     * Логирование событий
     */
    private function log_event($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_WEBHOOK] СОБЫТИЕ: ' . $message);
        }
    }
}
