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
    protected function check_permissions($capability = null) {
        // Этап 3: если capability не задан — определяем по action (разграничение по ролям)
        if ($capability === null) {
            $capability = $this->get_required_capability();
        }
        if (!current_user_can($capability)) {
            wp_send_json_error(['message' => 'Недостаточно прав'], 403);
            return false;
        }
        return true;
    }

    /**
     * Авто-определение требуемого capability по текущему AJAX-action.
     * Неизвестные action → manage_options (только админ) — безопасно по умолчанию.
     */
    protected function get_required_capability() {
        $action = $_REQUEST['action'] ?? '';
        $map = [
            // --- Сделки ---
            'akpp_save_deal'          => 'akpp_edit_deals',
            'akpp_delete_deal'        => 'akpp_edit_deals',
            'akpp_update_deal_status' => 'akpp_edit_deals',
            'akpp_get_deal'           => 'akpp_view_deals',
            'akpp_get_deals'          => 'akpp_view_deals',
            'akpp_decode_vin'         => 'akpp_edit_deals',
            'akpp_decode_vin_ai'      => 'akpp_edit_deals',
            // --- Настройки Qwen AI (только директор/админ) ---
            'akpp_save_qwen_settings'   => 'akpp_use_parser',
            'akpp_get_qwen_settings'    => 'akpp_use_parser',
            'akpp_test_qwen_connection' => 'akpp_use_parser',
            // --- Лиды ---
            'akpp_save_lead'          => 'akpp_manage_leads',
            'akpp_update_lead_status' => 'akpp_manage_leads',
            'akpp_convert_lead'       => 'akpp_manage_leads',
            'akpp_assign_lead'        => 'akpp_manage_leads',
            'akpp_delete_lead'        => 'akpp_manage_leads',
            'akpp_get_lead'           => 'akpp_manage_leads',
            // --- Справочники ---
            'akpp_save_employee'      => 'akpp_view_employees',
            'akpp_delete_employee'    => 'akpp_view_employees',
            'akpp_save_vehicle'       => 'akpp_view_vehicles',
            'akpp_delete_vehicle'     => 'akpp_view_vehicles',
            'akpp_save_part'          => 'akpp_view_parts',
            'akpp_delete_part'        => 'akpp_view_parts',
            'akpp_save_oil'           => 'akpp_view_parts',
            'akpp_delete_oil'         => 'akpp_view_parts',
            'akpp_save_transmission'  => 'akpp_view_vehicles',
            'akpp_delete_transmission'=> 'akpp_view_vehicles',
            // --- Оферты ---
            'akpp_save_agreement_text'   => 'akpp_manage_settings',
            'akpp_get_agreement_text'    => 'akpp_view_agreements',
            'akpp_get_agreements'        => 'akpp_view_agreements',
            'akpp_export_agreements'     => 'akpp_view_agreements',
            'akpp_delete_agreement'      => 'akpp_view_agreements',
            'akpp_bulk_delete_agreements'=> 'akpp_view_agreements',
            // --- Парсер + AI ---
            'akpp_parse_url'          => 'akpp_use_parser',
            'akpp_parse_multiple'     => 'akpp_use_parser',
            'akpp_get_parser_item'    => 'akpp_use_parser',
            'akpp_save_parser_item'   => 'akpp_use_parser',
            'akpp_delete_parser_item' => 'akpp_use_parser',
            'akpp_update_parser_status'=> 'akpp_use_parser',
            'akpp_ai_analyze'         => 'akpp_use_parser',
            'akpp_ai_analyze_batch'   => 'akpp_use_parser',
            'akpp_ai_approve'         => 'akpp_use_parser',
            'akpp_ai_reject'          => 'akpp_use_parser',
            'akpp_test_parser'        => 'akpp_use_parser',
            'akpp_test_ai'            => 'akpp_use_parser',
            // --- Магазин ---
            'akpp_shop_save_product'      => 'akpp_view_shop',
            'akpp_shop_delete_product'    => 'akpp_view_shop',
            'akpp_shop_get_products'      => 'akpp_view_shop',
            'akpp_shop_get_product'       => 'akpp_view_shop',
            'akpp_shop_update_stock'      => 'akpp_view_shop',
            'akpp_shop_save_category'     => 'akpp_view_shop',
            'akpp_shop_delete_category'   => 'akpp_view_shop',
            'akpp_shop_get_categories'    => 'akpp_view_shop',
            'akpp_shop_get_orders'        => 'akpp_view_shop',
            'akpp_shop_get_order'         => 'akpp_view_shop',
            'akpp_shop_update_order_status'=> 'akpp_view_shop',
            'akpp_shop_delete_order'      => 'akpp_view_shop',
            // --- Telegram ---
            'akpp_telegram_save_settings'  => 'akpp_manage_integrations',
            'akpp_telegram_test_connection'=> 'akpp_manage_integrations',
            'akpp_telegram_get_updates'    => 'akpp_manage_integrations',
            // --- Поиск ---
            'akpp_search_deals'   => 'akpp_view_deals',
            'akpp_global_search'  => 'akpp_access_crm',
        ];
        return $map[$action] ?? 'manage_options';
    }
    
    /**
     * Проверка что пользователь авторизован как клиент
     */

    /**
     * Этап 4: ID сотрудника для текущего WP-юзера (связка akpp_employees.wp_user_id)
     */
    protected function get_my_employee_id() {
        $user_id = get_current_user_id();
        if (!$user_id) return 0;
        global $wpdb;
        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_employees WHERE wp_user_id = %d LIMIT 1",
            $user_id
        )));
    }

    /**
     * Этап 4: фильтровать ли данные по employee_id (true только для механика)
     */
    protected function should_filter_by_employee() {
        $user = wp_get_current_user();
        if (!$user || !$user->exists()) return false;
        if (current_user_can('manage_options') ||
            in_array('akpp_manager', (array) $user->roles, true) ||
            in_array('akpp_director', (array) $user->roles, true) ||
            in_array('akpp_accountant', (array) $user->roles, true)) {
            return false;
        }
        return in_array('akpp_mechanic', (array) $user->roles, true);
    }

    /**
     * Этап 4: employee_id для сделки (механику — принудительно свой, чтобы не подменил)
     */
    protected function resolve_deal_employee_id() {
        if ($this->should_filter_by_employee()) {
            $my_emp_id = $this->get_my_employee_id();
            return $my_emp_id > 0 ? $my_emp_id : null;
        }
        return intval($_POST['employee_id'] ?? 0) ?: null;
    }
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
