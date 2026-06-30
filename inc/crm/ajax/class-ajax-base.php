<?php
if (!defined('ABSPATH')) exit;

/**
 * Базовый класс для AJAX модулей
 * Содержит общие методы проверки прав и работы с данными
 */
abstract class AKPP_AJAX_Base {
    
    /**
     * Проверка прав доступа (админка)
     */
    protected function check_permissions($capability = 'manage_options') {
        if (!current_user_can($capability)) {
            wp_send_json_error(['message' => 'Недостаточно прав'], 403);
            return false;
        }
        return true;
    }
    
    /**
     * Проверка что пользователь авторизован как клиент
     */
    protected function check_client_auth() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Требуется авторизация', 'code' => 'not_logged_in'], 401);
            return false;
        }
        return true;
    }
    
    /**
     * Получить ID пользователя для корзины
     */
    protected function get_cart_user_id() {
        if (is_user_logged_in()) {
            return get_current_user_id();
        }
        
        if (empty($_COOKIE['akpp_cart_session'])) {
            $session_id = 'guest_' . wp_generate_password(32, false);
            setcookie('akpp_cart_session', $session_id, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
            $_COOKIE['akpp_cart_session'] = $session_id;
        }
        
        return -abs(crc32($_COOKIE['akpp_cart_session']));
    }
    
    /**
     * Получить товары корзины
     */
    protected function get_cart_items($user_id) {
        global $wpdb;
        
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id as cart_id, c.*, p.name, p.sku, p.price, p.images, p.condition_type, p.stock
             FROM {$wpdb->prefix}akpp_client_cart c
             LEFT JOIN {$wpdb->prefix}akpp_shop_products p ON c.product_id = p.id
             WHERE c.user_id = %d AND p.id IS NOT NULL",
            $user_id
        ), ARRAY_A);
        
        return $items ?: [];
    }
    
    /**
     * Общая сумма корзины
     */
    protected function get_cart_total($user_id) {
        global $wpdb;
        
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(p.price * c.quantity), 0)
             FROM {$wpdb->prefix}akpp_client_cart c
             LEFT JOIN {$wpdb->prefix}akpp_shop_products p ON c.product_id = p.id
             WHERE c.user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Количество товаров в корзине
     */
    protected function get_cart_count($user_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM {$wpdb->prefix}akpp_client_cart WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Уведомление админу о новом заказе
     */
    protected function notify_admin_new_order($order_id, $order_number, $total) {
        $chat_id = get_option('akpp_telegram_chat_id');
        $bot_token = get_option('akpp_telegram_bot_token');
        
        if (empty($chat_id) || empty($bot_token)) return;
        
        $message = "🛒 <b>Новый заказ #{$order_number}</b>\n\n";
        $message .= "💰 Сумма: <b>" . number_format($total, 0, ',', ' ') . " ₽</b>\n";
        $message .= "📅 Дата: " . current_time('d.m.Y H:i');
        
        wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
            'body' => [
                'chat_id' => $chat_id,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]
        ]);
    }
    
    /**
     * Уведомление сотруднику о новом сообщении от клиента
     */
    protected function notify_employee_new_message($deal_id, $client_name, $message_text) {
        global $wpdb;
        
        $deal = $wpdb->get_row($wpdb->prepare(
            "SELECT employee_id FROM {$wpdb->prefix}akpp_deals WHERE id = %d",
            $deal_id
        ), ARRAY_A);
        
        if (!$deal || empty($deal['employee_id'])) return;
        
        $employee = $wpdb->get_row($wpdb->prepare(
            "SELECT telegram_id FROM {$wpdb->prefix}akpp_employees WHERE id = %d",
            $deal['employee_id']
        ), ARRAY_A);
        
        if (!$employee || empty($employee['telegram_id'])) return;
        
        $bot_token = get_option('akpp_telegram_bot_token');
        if (empty($bot_token)) return;
        
        $message = "💬 <b>Новое сообщение от клиента</b>\n\n";
        $message .= "👤 Клиент: {$client_name}\n";
        $message .= "📝 Сообщение: " . mb_substr($message_text, 0, 200);
        
        wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
            'body' => [
                'chat_id' => $employee['telegram_id'],
                'text' => $message,
                'parse_mode' => 'HTML'
            ]
        ]);
    }
    
    /**
     * Отправка уведомления о новой сделке
     */
    protected function send_deal_notification($deal_id, $client_name, $client_phone, $total) {
        $chat_id = get_option('akpp_telegram_chat_id');
        $bot_token = get_option('akpp_telegram_bot_token');
        
        if (empty($chat_id) || empty($bot_token)) return;
        
        $message = "💼 <b>Новая сделка #{$deal_id}</b>\n\n";
        $message .= "👤 Клиент: {$client_name}\n";
        $message .= "📞 Телефон: {$client_phone}\n";
        $message .= "💰 Сумма: <b>" . number_format($total, 0, ',', ' ') . " ₽</b>\n";
        $message .= "📅 Дата: " . current_time('d.m.Y H:i');
        
        wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
            'body' => [
                'chat_id' => $chat_id,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]
        ]);
    }
}