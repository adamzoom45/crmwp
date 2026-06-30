<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX модуль: Telegram настройки
 * Сохранение настроек бота, тестирование подключения
 */
class AKPP_AJAX_Telegram extends AKPP_AJAX_Base {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->register_hooks();
    }
    
    /**
     * Регистрация AJAX хуков
     */
    private function register_hooks() {
        // Настройки Telegram
        add_action('wp_ajax_akpp_telegram_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_akpp_telegram_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_akpp_telegram_get_updates', [$this, 'ajax_get_updates']);
    }
    
    // ========================================================================
    // СОХРАНЕНИЕ НАСТРОЕК
    // ========================================================================
    
    public function ajax_save_settings() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        $bot_token = sanitize_text_field($_POST['bot_token'] ?? '');
        $chat_id = sanitize_text_field($_POST['chat_id'] ?? '');
        $enable_notifications = isset($_POST['enable_notifications']) ? 1 : 0;
        $notify_new_deals = isset($_POST['notify_new_deals']) ? 1 : 0;
        $notify_new_leads = isset($_POST['notify_new_leads']) ? 1 : 0;
        $notify_new_orders = isset($_POST['notify_new_orders']) ? 1 : 0;
        
        // Валидация токена
        if (!empty($bot_token) && !preg_match('/^\d+:[A-Za-z0-9_-]{30,}$/', $bot_token)) {
            wp_send_json_error(['message' => 'Неверный формат токена бота']);
            return;
        }
        
        update_option('akpp_telegram_bot_token', $bot_token);
        update_option('akpp_telegram_chat_id', $chat_id);
        update_option('akpp_telegram_enabled', $enable_notifications);
        update_option('akpp_telegram_notify_deals', $notify_new_deals);
        update_option('akpp_telegram_notify_leads', $notify_new_leads);
        update_option('akpp_telegram_notify_orders', $notify_new_orders);
        
        wp_send_json_success(['message' => 'Настройки Telegram сохранены']);
    }
    
    // ========================================================================
    // ТЕСТИРОВАНИЕ ПОДКЛЮЧЕНИЯ
    // ========================================================================
    
    public function ajax_test_connection() {
        if (!$this->check_permissions()) return;
        
        $bot_token = sanitize_text_field($_POST['bot_token'] ?? get_option('akpp_telegram_bot_token', ''));
        $chat_id = sanitize_text_field($_POST['chat_id'] ?? get_option('akpp_telegram_chat_id', ''));
        
        if (empty($bot_token)) {
            wp_send_json_error(['message' => 'Токен бота не указан']);
            return;
        }
        
        // Проверяем токен через getMe
        $response = wp_remote_get("https://api.telegram.org/bot{$bot_token}/getMe", [
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Ошибка соединения: ' . $response->get_error_message()]);
            return;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$body || !$body['ok']) {
            wp_send_json_error(['message' => 'Неверный токен бота']);
            return;
        }
        
        $bot_info = $body['result'];
        $result = [
            'message' => 'Подключение успешно!',
            'bot_name' => $bot_info['first_name'] ?? 'Unknown',
            'bot_username' => '@' . ($bot_info['username'] ?? 'unknown')
        ];
        
        // Если указан chat_id - отправляем тестовое сообщение
        if (!empty($chat_id)) {
            $test_message = "✅ <b>Тест подключения АКПП45 CRM</b>\n\n";
            $test_message .= "🤖 Бот: @{$bot_info['username']}\n";
            $test_message .= "🕐 Время: " . current_time('d.m.Y H:i') . "\n";
            $test_message .= "🌐 Сайт: " . home_url();
            
            $send_response = wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
                'timeout' => 15,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'chat_id' => $chat_id,
                    'text' => $test_message,
                    'parse_mode' => 'HTML'
                ])
            ]);
            
            if (is_wp_error($send_response)) {
                $result['warning'] = 'Токен верный, но сообщение не отправлено: ' . $send_response->get_error_message();
            } else {
                $send_body = json_decode(wp_remote_retrieve_body($send_response), true);
                if (!$send_body || !$send_body['ok']) {
                    $result['warning'] = 'Сообщение не доставлено. Проверьте chat_id.';
                } else {
                    $result['message'] .= ' (тестовое сообщение отправлено)';
                }
            }
        } else {
            $result['warning'] = 'Chat ID не указан - тестовое сообщение не отправлено';
        }
        
        wp_send_json_success($result);
    }
    
    // ========================================================================
    // ПОЛУЧЕНИЕ ПОСЛЕДНИХ UPDATE (для поиска chat_id)
    // ========================================================================
    
    public function ajax_get_updates() {
        if (!$this->check_permissions()) return;
        
        $bot_token = sanitize_text_field($_POST['bot_token'] ?? get_option('akpp_telegram_bot_token', ''));
        
        if (empty($bot_token)) {
            wp_send_json_error(['message' => 'Токен бота не указан']);
            return;
        }
        
        $response = wp_remote_get("https://api.telegram.org/bot{$bot_token}/getUpdates", [
            'timeout' => 15,
            'query' => ['limit' => 10, 'offset' => -1]
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Ошибка: ' . $response->get_error_message()]);
            return;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$body || !$body['ok']) {
            wp_send_json_error(['message' => 'Не удалось получить updates']);
            return;
        }
        
        $updates = $body['result'] ?? [];
        $chats = [];
        
        foreach ($updates as $update) {
            $message = $update['message'] ?? $update['channel_post'] ?? null;
            if (!$message) continue;
            
            $chat = $message['chat'] ?? null;
            if (!$chat) continue;
            
            $chat_key = $chat['id'];
            if (!isset($chats[$chat_key])) {
                $chats[$chat_key] = [
                    'id' => $chat['id'],
                    'type' => $chat['type'] ?? 'unknown',
                    'title' => $chat['title'] ?? ($chat['first_name'] ?? 'Unknown'),
                    'username' => $chat['username'] ?? '',
                    'last_message' => mb_substr($message['text'] ?? $message['caption'] ?? '', 0, 100),
                    'date' => date('d.m.Y H:i', $message['date'] ?? 0)
                ];
            }
        }
        
        wp_send_json_success([
            'updates_count' => count($updates),
            'chats' => array_values($chats)
        ]);
    }
}