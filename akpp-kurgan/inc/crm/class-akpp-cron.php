<?php
/**
 * Класс для управления Cron задачами
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_Cron {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->register_hooks();
    }
    
    private function register_hooks() {
        add_action('wp', [$this, 'schedule_events']);
        add_filter('cron_schedules', [$this, 'add_cron_intervals']);
        add_action('akpp_sync_avito_dialogs', [$this, 'sync_avito_dialogs']);
        add_action('akpp_sync_avito_messages', [$this, 'sync_avito_messages']);
    }
    
    public function add_cron_intervals($schedules) {
        $schedules['every_five_minutes'] = [
            'interval' => 300,
            'display' => __('Каждые 5 минут', 'akpp45-crm')
        ];
        
        $schedules['every_hour'] = [
            'interval' => 3600,
            'display' => __('Каждый час', 'akpp45-crm')
        ];
        
        return $schedules;
    }
    
    public function schedule_events() {
        if (!wp_next_scheduled('akpp_sync_avito_dialogs')) {
            wp_schedule_event(time(), 'every_hour', 'akpp_sync_avito_dialogs');
        }
        
        if (!wp_next_scheduled('akpp_sync_avito_messages')) {
            wp_schedule_event(time(), 'every_five_minutes', 'akpp_sync_avito_messages');
        }
    }
    
    public function sync_avito_dialogs() {
        $this->log_event('Запуск синхронизации диалогов Авито');
        
        $token = $this->get_avito_token();
        if (!$token) {
            $this->log_error('Нет токена для синхронизации диалогов');
            return;
        }
        
        $account_id = get_option('akpp_avito_account_id', '');
        if (empty($account_id)) {
            $this->log_error('Нет account_id для синхронизации диалогов');
            return;
        }
        
        $url = 'https://api.avito.ru/messenger/v1/accounts/' . $account_id . '/chats';
        
        $args = [
            'method' => 'GET',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ]
        ];
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('Ошибка синхронизации диалогов: ' . $response->get_error_message());
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            $this->log_error('Ошибка синхронизации диалогов. Статус: ' . $status_code);
            return;
        }
        
        $data = json_decode($body, true);
        $chats = $data['chats'] ?? $data['results'] ?? [];
        
        if (empty($chats)) {
            $this->log_event('Нет диалогов для синхронизации');
            return;
        }
        
        $this->save_dialogs($chats);
        $this->log_event('Синхронизировано диалогов: ' . count($chats));
    }
    
    public function sync_avito_messages() {
        $this->log_event('Запуск синхронизации сообщений Авито');
        
        $token = $this->get_avito_token();
        if (!$token) {
            $this->log_error('Нет токена для синхронизации сообщений');
            return;
        }
        
        $account_id = get_option('akpp_avito_account_id', '');
        if (empty($account_id)) {
            $this->log_error('Нет account_id для синхронизации сообщений');
            return;
        }
        
        global $wpdb;
        $table_dialogs = $wpdb->prefix . 'akpp_avito_dialogs';
        
        $dialogs = $wpdb->get_col("SELECT dialog_id FROM {$table_dialogs} WHERE is_active = 1");
        
        if (empty($dialogs)) {
            $this->log_event('Нет активных диалогов для синхронизации сообщений');
            return;
        }
        
        foreach ($dialogs as $dialog_id) {
            $this->sync_dialog_messages($account_id, $token, $dialog_id);
        }
        
        $this->log_event('Синхронизация сообщений завершена для ' . count($dialogs) . ' диалогов');
    }
    
    private function sync_dialog_messages($account_id, $token, $dialog_id) {
        $url = 'https://api.avito.ru/messenger/v1/accounts/' . $account_id . '/chats/' . $dialog_id . '/messages';
        
        $args = [
            'method' => 'GET',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ]
        ];
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('Ошибка получения сообщений для диалога ' . $dialog_id . ': ' . $response->get_error_message());
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            $this->log_error('Ошибка получения сообщений для диалога ' . $dialog_id . '. Статус: ' . $status_code);
            return;
        }
        
        $data = json_decode($body, true);
        $messages = $data['messages'] ?? $data['results'] ?? [];
        
        if (!empty($messages)) {
            $this->save_messages($dialog_id, $messages);
        }
    }
    
    private function save_dialogs($chats) {
        global $wpdb;
        $table_dialogs = $wpdb->prefix . 'akpp_avito_dialogs';
        
        foreach ($chats as $chat) {
            $dialog_id = $chat['id'] ?? $chat['chat_id'] ?? '';
            $user_id = $chat['user_id'] ?? $chat['participant_id'] ?? '';
            $user_name = $chat['user_name'] ?? $chat['participant_name'] ?? 'Пользователь Авито';
            $last_message = $chat['last_message'] ?? $chat['last_message_text'] ?? '';
            $last_message_time = $chat['last_message_time'] ?? $chat['updated_at'] ?? current_time('mysql');
            
            if (empty($dialog_id)) {
                continue;
            }
            
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_dialogs} WHERE dialog_id = %s",
                $dialog_id
            ));
            
            if ($exists) {
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
            } else {
                $wpdb->insert(
                    $table_dialogs,
                    [
                        'dialog_id' => $dialog_id,
                        'user_id' => $user_id,
                        'user_name' => $user_name,
                        'last_message' => $last_message,
                        'last_message_time' => $last_message_time,
                        'is_active' => 1,
                        'created_at' => current_time('mysql')
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%d', '%s']
                );
            }
        }
    }
    
    private function save_messages($dialog_id, $messages) {
        global $wpdb;
        $table_cache = $wpdb->prefix . 'akpp_avito_messages_cache';
        
        foreach ($messages as $msg) {
            $message_id = $msg['id'] ?? $msg['message_id'] ?? '';
            $text = $msg['text'] ?? $msg['message'] ?? '';
            $is_incoming = isset($msg['is_incoming']) ? (int)$msg['is_incoming'] : (isset($msg['direction']) && $msg['direction'] === 'in');
            $created_at = $msg['created_at'] ?? $msg['timestamp'] ?? current_time('mysql');
            
            if (empty($message_id) || empty($text)) {
                continue;
            }
            
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
                        'message_text' => $text,
                        'is_incoming' => $is_incoming,
                        'created_at' => $created_at,
                        'synced_at' => current_time('mysql')
                    ],
                    ['%s', '%s', '%s', '%d', '%s', '%s']
                );
            }
        }
    }
    
    private function get_avito_token() {
        if (!class_exists('AKPP_Avito')) {
            require_once AKPP_CRM_PATH . 'class-akpp-avito.php';
        }
        
        $avito = AKPP_Avito::get_instance();
        return $avito->get_active_token();
    }
    
    private function log_error($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_CRON] ОШИБКА: ' . $message);
        }
    }
    
    private function log_event($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_CRON] СОБЫТИЕ: ' . $message);
        }
    }
}
