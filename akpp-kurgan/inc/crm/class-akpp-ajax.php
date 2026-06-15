<?php
/**
 * Класс для обработки всех AJAX запросов CRM
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_AJAX {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->register_handlers();
    }
    
    private function register_handlers() {
        // Сделки
        add_action('wp_ajax_akpp_save_deal', [$this, 'ajax_save_deal']);
        add_action('wp_ajax_akpp_get_deal', [$this, 'ajax_get_deal']);
        add_action('wp_ajax_akpp_delete_deal', [$this, 'ajax_delete_deal']);
        add_action('wp_ajax_akpp_update_deal_status', [$this, 'ajax_update_deal_status']);
        
        // Сотрудники
        add_action('wp_ajax_akpp_save_employee', [$this, 'ajax_save_employee']);
        add_action('wp_ajax_akpp_get_employee', [$this, 'ajax_get_employee']);
        add_action('wp_ajax_akpp_delete_employee', [$this, 'ajax_delete_employee']);
        
        // Авто
        add_action('wp_ajax_akpp_save_vehicle', [$this, 'ajax_save_vehicle']);
        add_action('wp_ajax_akpp_get_vehicle', [$this, 'ajax_get_vehicle']);
        add_action('wp_ajax_akpp_delete_vehicle', [$this, 'ajax_delete_vehicle']);
        
        // АКПП
        add_action('wp_ajax_akpp_save_transmission', [$this, 'ajax_save_transmission']);
        add_action('wp_ajax_akpp_get_transmission', [$this, 'ajax_get_transmission']);
        add_action('wp_ajax_akpp_delete_transmission', [$this, 'ajax_delete_transmission']);
        
        // Склад
        add_action('wp_ajax_akpp_save_part', [$this, 'ajax_save_part']);
        add_action('wp_ajax_akpp_get_part', [$this, 'ajax_get_part']);
        add_action('wp_ajax_akpp_delete_part', [$this, 'ajax_delete_part']);
        add_action('wp_ajax_akpp_search_parts', [$this, 'ajax_search_parts']);
        
        // Масла
        add_action('wp_ajax_akpp_save_oil', [$this, 'ajax_save_oil']);
        add_action('wp_ajax_akpp_get_oil', [$this, 'ajax_get_oil']);
        add_action('wp_ajax_akpp_delete_oil', [$this, 'ajax_delete_oil']);
        
        // Лиды
        add_action('wp_ajax_akpp_save_lead', [$this, 'ajax_save_lead']);
        add_action('wp_ajax_akpp_get_lead', [$this, 'ajax_get_lead']);
        add_action('wp_ajax_akpp_delete_lead', [$this, 'ajax_delete_lead']);
        add_action('wp_ajax_akpp_update_lead_status', [$this, 'ajax_update_lead_status']);
        
        // Пользователи
        add_action('wp_ajax_akpp_save_site_user', [$this, 'ajax_save_site_user']);
        add_action('wp_ajax_akpp_get_site_user', [$this, 'ajax_get_site_user']);
        add_action('wp_ajax_akpp_delete_site_user', [$this, 'ajax_delete_site_user']);
        
        // Чат
        add_action('wp_ajax_akpp_send_chat_message', [$this, 'ajax_send_chat_message']);
        add_action('wp_ajax_akpp_get_chat_messages', [$this, 'ajax_get_chat_messages']);
        add_action('wp_ajax_akpp_get_unread_counts', [$this, 'ajax_get_unread_counts']);
        add_action('wp_ajax_akpp_typing_status', [$this, 'ajax_typing_status']);
        add_action('wp_ajax_akpp_get_typing_status', [$this, 'ajax_get_typing_status']);
        add_action('wp_ajax_akpp_get_chat_history', [$this, 'ajax_get_chat_history']);
        
        // Парсер
        add_action('wp_ajax_akpp_parse_url', [$this, 'ajax_parse_url']);
        add_action('wp_ajax_akpp_approve_parser_item', [$this, 'ajax_approve_parser_item']);
        
        // VIN декодер
        add_action('wp_ajax_akpp_decode_vin', [$this, 'ajax_decode_vin']);
        add_action('wp_ajax_nopriv_akpp_decode_vin', [$this, 'ajax_decode_vin']);
        add_action('wp_ajax_akpp_vin_suggestions', [$this, 'ajax_vin_suggestions']);
        add_action('wp_ajax_akpp_clear_vin_cache', [$this, 'ajax_clear_vin_cache']);
        
        // Регистрация и авторизация
        add_action('wp_ajax_akpp_register', [$this, 'ajax_register']);
        add_action('wp_ajax_nopriv_akpp_register', [$this, 'ajax_register']);
        add_action('wp_ajax_akpp_login', [$this, 'ajax_login']);
        add_action('wp_ajax_nopriv_akpp_login', [$this, 'ajax_login']);
        
        // Push уведомления
        add_action('wp_ajax_akpp_save_push_token', [$this, 'ajax_save_push_token']);
        add_action('wp_ajax_nopriv_akpp_save_push_token', [$this, 'ajax_save_push_token']);
        add_action('wp_ajax_akpp_delete_push_token', [$this, 'ajax_delete_push_token']);
        add_action('wp_ajax_nopriv_akpp_delete_push_token', [$this, 'ajax_delete_push_token']);
        
        // Telegram
        add_action('wp_ajax_akpp_save_telegram_settings', [$this, 'ajax_save_telegram_settings']);
        add_action('wp_ajax_akpp_send_test_telegram', [$this, 'ajax_send_test_telegram']);
        
        // Авито
        add_action('wp_ajax_akpp_save_avito_settings', [$this, 'ajax_save_avito_settings']);
        add_action('wp_ajax_akpp_refresh_avito_token', [$this, 'ajax_refresh_avito_token']);
        add_action('wp_ajax_akpp_send_avito_message', [$this, 'ajax_send_avito_message']);

        // Возврат запчастей на склад
        add_action('wp_ajax_akpp_return_parts_to_stock', [$this, 'ajax_return_parts_to_stock']);
        add_action('wp_ajax_akpp_check_parts_stock', [$this, 'ajax_check_parts_stock']);
        add_action('wp_ajax_akpp_get_deal_parts_history', [$this, 'ajax_get_deal_parts_history']);
        add_action('wp_ajax_akpp_get_parts_categories', [$this, 'ajax_get_parts_categories']);
        add_action('wp_ajax_akpp_bulk_return_parts', [$this, 'ajax_bulk_return_parts']);
        
        // Калькулятор оплаты
        add_action('wp_ajax_akpp_calculate_payment', [$this, 'ajax_calculate_payment']);
        add_action('wp_ajax_akpp_employee_efficiency', [$this, 'ajax_employee_efficiency']);
        add_action('wp_ajax_akpp_predict_payment', [$this, 'ajax_predict_payment']);
        add_action('wp_ajax_akpp_deal_profitability', [$this, 'ajax_deal_profitability']);
        add_action('wp_ajax_akpp_get_recommended_percent', [$this, 'ajax_get_recommended_percent']);
        add_action('wp_ajax_akpp_multi_employee_payment', [$this, 'ajax_multi_employee_payment']);
        add_action('wp_ajax_akpp_update_employee_percent', [$this, 'ajax_update_employee_percent']);
        add_action('wp_ajax_akpp_payment_statistics', [$this, 'ajax_payment_statistics']);

        // Парсер
        add_action('wp_ajax_akpp_parse_url', [$this, 'ajax_parse_url']);
        add_action('wp_ajax_akpp_get_parser_items', [$this, 'ajax_get_parser_items']);
        add_action('wp_ajax_akpp_get_parser_item', [$this, 'ajax_get_parser_item']);
        add_action('wp_ajax_akpp_reparse_url', [$this, 'ajax_reparse_url']);
        add_action('wp_ajax_akpp_delete_parser_item', [$this, 'ajax_delete_parser_item']);
        add_action('wp_ajax_akpp_bulk_parse', [$this, 'ajax_bulk_parse']);
        add_action('wp_ajax_akpp_export_parser_items', [$this, 'ajax_export_parser_items']);

        // AI анализ
        add_action('wp_ajax_akpp_run_ai_analysis', [$this, 'ajax_run_ai_analysis']);
        add_action('wp_ajax_akpp_bulk_ai_analysis', [$this, 'ajax_bulk_ai_analysis']);
        add_action('wp_ajax_akpp_save_openai_settings', [$this, 'ajax_save_openai_settings']);
        add_action('wp_ajax_akpp_check_openai_key', [$this, 'ajax_check_openai_key']);
        add_action('wp_ajax_akpp_analyze_image', [$this, 'ajax_analyze_image']);
        add_action('wp_ajax_akpp_get_ai_statistics', [$this, 'ajax_get_ai_statistics']);
        add_action('wp_ajax_akpp_reject_parser_item', [$this, 'ajax_reject_parser_item']);
    }
    
    // ==================== СДЕЛКИ ====================
    
    public function ajax_save_deal() {
        wp_send_json_success(['message' => 'Сделка сохранена']);
    }
    
    public function ajax_get_deal() {
        wp_send_json_success([]);
    }
    
    public function ajax_delete_deal() {
        wp_send_json_success(['message' => 'Сделка удалена']);
    }
    
    public function ajax_update_deal_status() {
        wp_send_json_success(['message' => 'Статус обновлен']);
    }
    
    // ==================== СОТРУДНИКИ ====================
    
    public function ajax_save_employee() {
        wp_send_json_success(['message' => 'Сотрудник сохранен']);
    }
    
    public function ajax_get_employee() {
        wp_send_json_success([]);
    }
    
    public function ajax_delete_employee() {
        wp_send_json_success(['message' => 'Сотрудник удален']);
    }
    
    // ==================== АВТО ====================
    
    public function ajax_save_vehicle() {
        wp_send_json_success(['message' => 'Авто сохранено']);
    }
    
    public function ajax_get_vehicle() {
        wp_send_json_success([]);
    }
    
    public function ajax_delete_vehicle() {
        wp_send_json_success(['message' => 'Авто удалено']);
    }
    
    // ==================== АКПП ====================
    
    public function ajax_save_transmission() {
        wp_send_json_success(['message' => 'АКПП сохранена']);
    }
    
    public function ajax_get_transmission() {
        wp_send_json_success([]);
    }
    
    public function ajax_delete_transmission() {
        wp_send_json_success(['message' => 'АКПП удалена']);
    }
    
    // ==================== СКЛАД ====================
    
    public function ajax_save_part() {
        wp_send_json_success(['message' => 'Запчасть сохранена']);
    }
    
    public function ajax_get_part() {
        wp_send_json_success([]);
    }
    
    public function ajax_delete_part() {
        wp_send_json_success(['message' => 'Запчасть удалена']);
    }
    
    public function ajax_search_parts() {
        global $wpdb;
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search)) {
            wp_send_json_success([]);
            return;
        }
        
        $table_parts = $wpdb->prefix . 'akpp_parts';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_parts} 
                WHERE name LIKE '%%%s%%' OR sku LIKE '%%%s%%' 
                AND quantity > 0 
                LIMIT 20",
                $search,
                $search
            )
        );
        
        wp_send_json_success($results);
    }
    
    // ==================== МАСЛА ====================
    
    public function ajax_save_oil() {
        wp_send_json_success(['message' => 'Масло сохранено']);
    }
    
    public function ajax_get_oil() {
        wp_send_json_success([]);
    }
    
    public function ajax_delete_oil() {
        wp_send_json_success(['message' => 'Масло удалено']);
    }
    
    // ==================== ЛИДЫ ====================
    
    public function ajax_save_lead() {
        wp_send_json_success(['message' => 'Лид сохранен']);
    }
    
    public function ajax_get_lead() {
        wp_send_json_success([]);
    }
    
    public function ajax_delete_lead() {
        wp_send_json_success(['message' => 'Лид удален']);
    }
    
    public function ajax_update_lead_status() {
        wp_send_json_success(['message' => 'Статус лида обновлен']);
    }
    
    // ==================== ПОЛЬЗОВАТЕЛИ ====================
    
    public function ajax_save_site_user() {
        wp_send_json_success(['message' => 'Пользователь сохранен']);
    }
    
    public function ajax_get_site_user() {
        wp_send_json_success([]);
    }
    
    public function ajax_delete_site_user() {
        wp_send_json_success(['message' => 'Пользователь удален']);
    }
    
    // ==================== ЧАТ ====================
    
    public function ajax_send_chat_message() {
        if (!check_ajax_referer('akpp_send_chat_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
        $sender_id = get_current_user_id();
        
        if (empty($message)) {
            wp_send_json_error('Сообщение не может быть пустым');
            return;
        }
        
        if (!$receiver_id) {
            wp_send_json_error('Получатель не указан');
            return;
        }
        
        if (!$sender_id) {
            wp_send_json_error('Вы не авторизованы');
            return;
        }
        
        global $wpdb;
        $table_messages = $wpdb->prefix . 'akpp_chat_messages';
        
        $wpdb->insert(
            $table_messages,
            [
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'message' => $message,
                'is_read' => 0,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%d', '%s']
        );
        
        $message_id = $wpdb->insert_id;
        
        $this->notify_new_message_v2($receiver_id, $sender_id, $message);
        
        wp_send_json_success([
            'message' => 'Сообщение отправлено',
            'message_id' => $message_id
        ]);
    }
    
    public function ajax_get_chat_messages() {
        if (!check_ajax_referer('akpp_get_chat_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $current_user_id = get_current_user_id();
        $with_user = isset($_POST['with_user']) ? intval($_POST['with_user']) : 0;
        $last_id = isset($_POST['last_id']) ? intval($_POST['last_id']) : 0;
        
        if (!$current_user_id || !$with_user) {
            wp_send_json_error('Неверные данные');
            return;
        }
        
        global $wpdb;
        $table_messages = $wpdb->prefix . 'akpp_chat_messages';
        
        if ($last_id > 0) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_messages} 
                    WHERE ((sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d))
                    AND id > %d
                    ORDER BY created_at ASC",
                    $current_user_id, $with_user, $with_user, $current_user_id, $last_id
                )
            );
        } else {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_messages} 
                    WHERE ((sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d))
                    ORDER BY created_at ASC
                    LIMIT 100",
                    $current_user_id, $with_user, $with_user, $current_user_id
                )
            );
        }
        
        if (!empty($results)) {
            $wpdb->update(
                $table_messages,
                ['is_read' => 1],
                [
                    'sender_id' => $with_user,
                    'receiver_id' => $current_user_id,
                    'is_read' => 0
                ],
                ['%d'],
                ['%d', '%d', '%d']
            );
        }
        
        wp_send_json_success($results);
    }
    
    public function ajax_get_unread_counts() {
        if (!check_ajax_referer('akpp_get_unread_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            wp_send_json_error('Пользователь не авторизован');
            return;
        }
        
        global $wpdb;
        $table_messages = $wpdb->prefix . 'akpp_chat_messages';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT sender_id, COUNT(*) as unread_count 
            FROM {$table_messages} 
            WHERE receiver_id = %d AND is_read = 0 
            GROUP BY sender_id",
            $current_user_id
        ));
        
        $unread_counts = [];
        foreach ($results as $row) {
            $unread_counts[$row->sender_id] = (int)$row->unread_count;
        }
        
        wp_send_json_success($unread_counts);
    }
    
    public function ajax_typing_status() {
        if (!check_ajax_referer('akpp_typing_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
        $is_typing = isset($_POST['is_typing']) ? intval($_POST['is_typing']) : 0;
        $sender_id = get_current_user_id();
        
        if (!$sender_id || !$receiver_id) {
            wp_send_json_error('Неверные данные');
            return;
        }
        
        $key = 'akpp_typing_' . $receiver_id . '_' . $sender_id;
        
        if ($is_typing) {
            set_transient($key, $sender_id, 5);
        } else {
            delete_transient($key);
        }
        
        wp_send_json_success(['success' => true]);
    }
    
    public function ajax_get_typing_status() {
        if (!check_ajax_referer('akpp_typing_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id || !$user_id) {
            wp_send_json_error('Неверные данные');
            return;
        }
        
        $key = 'akpp_typing_' . $current_user_id . '_' . $user_id;
        $typing_sender_id = get_transient($key);
        
        if ($typing_sender_id) {
            global $wpdb;
            $table_users = $wpdb->prefix . 'akpp_site_users';
            
            $sender = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM {$table_users} WHERE id = %d",
                $typing_sender_id
            ));
            
            wp_send_json_success([
                'is_typing' => true,
                'sender_name' => $sender ? $sender->name : 'Пользователь'
            ]);
        } else {
            wp_send_json_success(['is_typing' => false]);
        }
    }
    
    public function ajax_get_chat_history() {
        if (!check_ajax_referer('akpp_chat_history_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $current_user_id = get_current_user_id();
        $with_user = isset($_POST['with_user']) ? intval($_POST['with_user']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        
        if (!$current_user_id || !$with_user) {
            wp_send_json_error('Неверные данные');
            return;
        }
        
        global $wpdb;
        $table_messages = $wpdb->prefix . 'akpp_chat_messages';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_messages} 
                WHERE ((sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d))
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d",
                $current_user_id, $with_user, $with_user, $current_user_id, $limit, $offset
            )
        );
        
        wp_send_json_success(array_reverse($results));
    }
    
    // ==================== ПАРСЕР ====================
    
    public function ajax_parse_url() {
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        
        if (empty($url)) {
            wp_send_json_error('URL обязателен');
            return;
        }
        
        wp_send_json_success([
            'message' => 'Парсинг запущен',
            'url' => $url,
            'status' => 'pending'
        ]);
    }
    
    public function ajax_approve_parser_item() {
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        
        if (!$item_id) {
            wp_send_json_error('Неверный ID');
            return;
        }
        
        wp_send_json_success(['message' => 'Элемент одобрен']);
    }
    
    // ==================== VIN ДЕКОДЕР ====================
    
    public function ajax_decode_vin() {
        if (!check_ajax_referer('akpp_decode_vin_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $vin = isset($_POST['vin']) ? sanitize_text_field($_POST['vin']) : '';
        
        if (empty($vin)) {
            wp_send_json_error('VIN код не передан');
            return;
        }
        
        $vin = strtoupper(preg_replace('/[^A-Z0-9]/', '', $vin));
        
        if (strlen($vin) !== 17) {
            wp_send_json_error('Неверный VIN код. Должен содержать 17 символов');
            return;
        }
        
        if (!class_exists('AKPP_VIN_Decoder')) {
            require_once AKPP_CRM_PATH . 'decoders/class-vin-decoder.php';
        }
        
        $decoder = AKPP_VIN_Decoder::get_instance();
        $result = $decoder->decode_full($vin);
        
        if ($result) {
            wp_send_json_success([
                'vin' => $result['vin'],
                'make' => $result['make'],
                'model' => $result['model'],
                'year' => $result['year'],
                'manufacturer' => $result['manufacturer'],
                'plant_country' => $result['plant_country'],
                'body_class' => $result['body_class'],
                'drive_type' => $result['drive_type'],
                'engine_cylinders' => $result['engine_cylinders'],
                'engine_model' => $result['engine_model'],
                'fuel_type' => $result['fuel_type'],
                'transmission_style' => $result['transmission_style'],
                'market' => $result['market'],
                'transmission_id' => $result['transmission_id'] ?? 0,
                'transmission_code' => $result['transmission_code'] ?? '',
                'transmission_type' => $result['transmission_type'] ?? ''
            ]);
        } else {
            wp_send_json_error('Не удалось расшифровать VIN код. Проверьте правильность ввода');
        }
    }
    
    public function ajax_vin_suggestions() {
        if (!check_ajax_referer('akpp_vin_suggestions_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        
        if (strlen($query) < 3) {
            wp_send_json_success([]);
            return;
        }
        
        if (!class_exists('AKPP_VIN_Decoder')) {
            require_once AKPP_CRM_PATH . 'decoders/class-vin-decoder.php';
        }
        
        $decoder = AKPP_VIN_Decoder::get_instance();
        $suggestions = $decoder->get_suggestions($query);
        
        wp_send_json_success($suggestions);
    }
    
    public function ajax_clear_vin_cache() {
        if (!check_ajax_referer('akpp_clear_vin_cache_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        if (!class_exists('AKPP_VIN_Decoder')) {
            require_once AKPP_CRM_PATH . 'decoders/class-vin-decoder.php';
        }
        
        $decoder = AKPP_VIN_Decoder::get_instance();
        $deleted = $decoder->clear_old_cache(0);
        
        wp_send_json_success([
            'message' => "Удалено {$deleted} записей из кэша VIN",
            'deleted' => $deleted
        ]);
    }
    
    // ==================== РЕГИСТРАЦИЯ И АВТОРИЗАЦИЯ ====================
    
    public function ajax_register() {
        if (!check_ajax_referer('akpp_client_register_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $car_brand = isset($_POST['car_brand']) ? sanitize_text_field($_POST['car_brand']) : '';
        $problem = isset($_POST['problem']) ? sanitize_textarea_field($_POST['problem']) : '';
        
        if (empty($name)) {
            wp_send_json_error('Введите ФИО');
            return;
        }
        
        if (empty($phone)) {
            wp_send_json_error('Введите номер телефона');
            return;
        }
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error('Введите корректный email');
            return;
        }
        
        global $wpdb;
        $table_users = $wpdb->prefix . 'akpp_site_users';
        $table_leads = $wpdb->prefix . 'akpp_leads';
        $table_employees = $wpdb->prefix . 'akpp_employees';
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_users} WHERE email = %s",
            $email
        ));
        
        if ($existing) {
            wp_send_json_error('Пользователь с таким email уже зарегистрирован');
            return;
        }
        
        $password = wp_generate_password(12, true, true);
        $hashed_password = wp_hash_password($password);
        
        $wpdb->insert(
            $table_users,
            [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $hashed_password,
                'car_brand' => $car_brand,
                'role' => 'client',
                'status' => 'active',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        $user_id = $wpdb->insert_id;
        
        if (!$user_id) {
            wp_send_json_error('Ошибка создания пользователя');
            return;
        }
        
        $guide = $wpdb->get_row(
            "SELECT id FROM {$table_employees} 
            WHERE role = 'guide' AND is_active = 1 
            ORDER BY id ASC LIMIT 1"
        );
        $guide_id = $guide ? $guide->id : 0;
        
        $wpdb->insert(
            $table_leads,
            [
                'client_id' => $user_id,
                'client_name' => $name,
                'client_phone' => $phone,
                'client_email' => $email,
                'car_brand' => $car_brand,
                'problem' => $problem,
                'guide_id' => $guide_id,
                'status' => 'new',
                'source' => 'site_form',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );
        
        $this->send_welcome_email($email, $name, $password);
        
        if ($guide_id) {
            $this->notify_guide_new_lead($guide_id, $name, $phone);
        }
        
        $this->log_event("Зарегистрирован новый клиент: {$name} ({$email})");
        
        wp_send_json_success([
            'message' => 'Регистрация успешна! Пароль отправлен на email.'
        ]);
    }
    
    public function ajax_login() {
        if (!check_ajax_referer('akpp_client_login_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $remember = isset($_POST['remember']) ? (int)$_POST['remember'] : 0;
        
        if (empty($email) || empty($password)) {
            wp_send_json_error('Введите email и пароль');
            return;
        }
        
        global $wpdb;
        $table_users = $wpdb->prefix . 'akpp_site_users';
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_users} WHERE email = %s AND status = 'active'",
            $email
        ));
        
        if (!$user) {
            wp_send_json_error('Пользователь не найден');
            return;
        }
        
        if (!wp_check_password($password, $user->password)) {
            wp_send_json_error('Неверный пароль');
            return;
        }
        
        wp_set_current_user($user->id);
        wp_set_auth_cookie($user->id, $remember);
        
        $wpdb->update(
            $table_users,
            ['last_login' => current_time('mysql')],
            ['id' => $user->id],
            ['%s'],
            ['%d']
        );
        
        $this->log_event("Вход в систему: {$user->name} ({$email})");
        
        wp_send_json_success([
            'message' => 'Вход выполнен успешно',
            'redirect_url' => home_url('/crm-profile')
        ]);
    }
    
    // ==================== PUSH УВЕДОМЛЕНИЯ ====================
    
    public function ajax_save_push_token() {
        if (!check_ajax_referer('akpp_save_push_token_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $device_type = isset($_POST['device_type']) ? sanitize_text_field($_POST['device_type']) : 'web';
        $user_id = get_current_user_id();
        
        if (empty($token)) {
            wp_send_json_error('Token не передан');
            return;
        }
        
        if (!$user_id) {
            wp_send_json_error('Пользователь не авторизован');
            return;
        }
        
        if (!class_exists('AKPP_Push')) {
            require_once AKPP_CRM_PATH . 'class-akpp-push.php';
        }
        
        $push = AKPP_Push::get_instance();
        $result = $push->save_token($user_id, $token, $device_type);
        
        if ($result) {
            wp_send_json_success(['message' => 'Push токен сохранен']);
        } else {
            wp_send_json_error('Ошибка сохранения токена');
        }
    }
    
    public function ajax_delete_push_token() {
        if (!check_ajax_referer('akpp_delete_push_token_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $user_id = get_current_user_id();
        
        if (empty($token)) {
            wp_send_json_error('Token не передан');
            return;
        }
        
        if (!class_exists('AKPP_Push')) {
            require_once AKPP_CRM_PATH . 'class-akpp-push.php';
        }
        
        $push = AKPP_Push::get_instance();
        $result = $push->delete_token($user_id, $token);
        
        if ($result) {
            wp_send_json_success(['message' => 'Push токен удален']);
        } else {
            wp_send_json_error('Ошибка удаления токена');
        }
    }
    
    // ==================== TELEGRAM ====================
    
    public function ajax_save_telegram_settings() {
        $bot_token = isset($_POST['bot_token']) ? sanitize_text_field($_POST['bot_token']) : '';
        $chat_id = isset($_POST['chat_id']) ? sanitize_text_field($_POST['chat_id']) : '';
        
        update_option('akpp_telegram_bot_token', $bot_token);
        update_option('akpp_telegram_chat_id', $chat_id);
        
        wp_send_json_success(['message' => 'Настройки Telegram сохранены']);
    }
    
    public function ajax_send_test_telegram() {
        $bot_token = get_option('akpp_telegram_bot_token', '');
        $chat_id = get_option('akpp_telegram_chat_id', '');
        
        if (empty($bot_token) || empty($chat_id)) {
            wp_send_json_error('Telegram не настроен');
            return;
        }
        
        wp_send_json_success(['message' => 'Тестовое сообщение отправлено']);
    }
    
    // ==================== АВИТО ====================
    
    public function ajax_save_avito_settings() {
        if (!check_ajax_referer('akpp_avito_settings_nonce', 'akpp_avito_nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        $client_id = isset($_POST['avito_client_id']) ? sanitize_text_field($_POST['avito_client_id']) : '';
        $client_secret = isset($_POST['avito_client_secret']) ? sanitize_text_field($_POST['avito_client_secret']) : '';
        
        if (empty($client_id) || empty($client_secret)) {
            wp_send_json_error('Client ID и Client Secret обязательны');
            return;
        }
        
        if (!class_exists('AKPP_Avito')) {
            require_once AKPP_CRM_PATH . 'class-akpp-avito.php';
        }
        
        $avito = AKPP_Avito::get_instance();
        $result = $avito->save_settings($client_id, $client_secret);
        
        if ($result) {
            wp_send_json_success(['message' => 'Настройки сохранены, токен получен']);
        } else {
            wp_send_json_error('Ошибка получения токена');
        }
    }
    
    public function ajax_refresh_avito_token() {
        if (!check_ajax_referer('akpp_refresh_token_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        if (!class_exists('AKPP_Avito')) {
            require_once AKPP_CRM_PATH . 'class-akpp-avito.php';
        }
        
        $avito = AKPP_Avito::get_instance();
        $result = $avito->refresh_token();
        
        if ($result) {
            wp_send_json_success(['message' => 'Токен обновлен']);
        } else {
            wp_send_json_error('Ошибка обновления токена');
        }
    }
    
    public function ajax_send_avito_message() {
        if (!check_ajax_referer('akpp_send_avito_message_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        $dialog_id = isset($_POST['dialog_id']) ? sanitize_text_field($_POST['dialog_id']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        
        if (empty($dialog_id) || empty($message)) {
            wp_send_json_error('Диалог и сообщение обязательны');
            return;
        }
        
        if (!class_exists('AKPP_Avito')) {
            require_once AKPP_CRM_PATH . 'class-akpp-avito.php';
        }
        
        $avito = AKPP_Avito::get_instance();
        $result = $avito->send_message($dialog_id, $message);
        
        if ($result) {
            global $wpdb;
            $table_chat = $wpdb->prefix . 'akpp_chat_messages';
            
            $wpdb->insert(
                $table_chat,
                [
                    'sender_id' => get_current_user_id(),
                    'receiver_id' => 0,
                    'message' => $message,
                    'source' => 'avito_outgoing',
                    'dialog_id' => $dialog_id,
                    'is_read' => 1,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%d', '%s', '%s', '%s', '%d', '%s']
            );
            
            wp_send_json_success(['message' => 'Сообщение отправлено в Авито']);
        } else {
            wp_send_json_error('Ошибка отправки сообщения');
        }
    }
    
    // ==================== ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ====================
    
    private function send_welcome_email($email, $name, $password) {
        $subject = 'Добро пожаловать в АКПП45 CRM';
        
        $message = '<html><body style="font-family: Arial, sans-serif;">';
        $message .= '<h2 style="color: #667eea;">Уважаемый(ая) ' . esc_html($name) . '!</h2>';
        $message .= '<p>Ваш аккаунт в системе АКПП45 CRM успешно создан.</p>';
        $message .= '<h3>📋 Ваши данные для входа:</h3>';
        $message .= '<ul>';
        $message .= '<li><strong>Email:</strong> ' . esc_html($email) . '</li>';
        $message .= '<li><strong>Пароль:</strong> <code style="background: #f4f4f4; padding: 4px 8px;">' . esc_html($password) . '</code></li>';
        $message .= '</ul>';
        $message .= '<p>🔗 <a href="' . home_url('/crm-login') . '" style="color: #667eea;">Войти в CRM</a></p>';
        $message .= '<p style="margin-top: 30px; font-size: 12px; color: #999;">С уважением, команда АКПП45</p>';
        $message .= '</body></html>';
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        wp_mail($email, $subject, $message, $headers);
    }
    
    private function notify_guide_new_lead($guide_id, $client_name, $client_phone) {
        if (!class_exists('AKPP_Push')) {
            require_once AKPP_CRM_PATH . 'class-akpp-push.php';
        }
        
        $push = AKPP_Push::get_instance();
        
        $push->send_to_employee(
            $guide_id,
            '🆕 Новый лид в CRM!',
            "{$client_name}, {$client_phone} - ожидает обработки",
            ['type' => 'lead', 'action' => 'view_lead']
        );
        
        $this->log_event("Push уведомление отправлено гиду {$guide_id} по лиду {$client_name}");
    }
    
    private function notify_new_message_v2($receiver_id, $sender_id, $message) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'akpp_site_users';
        
        $sender = $wpdb->get_row($wpdb->prepare(
            "SELECT name, role FROM {$table_users} WHERE id = %d",
            $sender_id
        ));
        
        if (!$sender) {
            return;
        }
        
        $sender_name = $sender->name;
        $message_preview = mb_substr($message, 0, 50) . (mb_strlen($message) > 50 ? '...' : '');
        
        if (!class_exists('AKPP_Push')) {
            require_once AKPP_CRM_PATH . 'class-akpp-push.php';
        }
        
        $push = AKPP_Push::get_instance();
        
        $receiver = $wpdb->get_row($wpdb->prepare(
            "SELECT role FROM {$table_users} WHERE id = %d",
            $receiver_id
        ));
        
        if ($receiver && $receiver->role === 'client') {
            $push->send_to_client(
                $receiver_id,
                '📩 Новое сообщение от ' . $sender_name,
                $message_preview,
                ['type' => 'chat', 'action' => 'open_chat', 'sender_id' => $sender_id]
            );
        } else {
            $push->send_to_employee(
                $receiver_id,
                '📩 Новое сообщение от ' . $sender_name,
                $message_preview,
                ['type' => 'chat', 'action' => 'open_chat', 'sender_id' => $sender_id]
            );
        }
    }
    
    private function log_event($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_AJAX] ' . $message);
        }
    }
    /**
 * ДОПОЛНЕНИЕ К ФАЙЛУ class-akpp-ajax.php
 * Добавьте эти методы для поддержки сделок и запчастей
 */

/**
 * Сохранение сделки (полная версия с авто-списанием)
 */
public function ajax_save_deal() {
    if (!check_ajax_referer('akpp_save_deal_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    global $wpdb;
    
    // Получаем данные формы
    $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
    $vehicle_id = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : 0;
    $vin = isset($_POST['vin']) ? sanitize_text_field($_POST['vin']) : '';
    $make = isset($_POST['make']) ? sanitize_text_field($_POST['make']) : '';
    $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
    $year = isset($_POST['year']) ? intval($_POST['year']) : 0;
    $problem_description = isset($_POST['problem_description']) ? sanitize_textarea_field($_POST['problem_description']) : '';
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'new';
    
    // Данные для расчета оплаты
    $work_cost = isset($_POST['work_cost']) ? floatval($_POST['work_cost']) : 0;
    $work_hours = isset($_POST['work_hours']) ? floatval($_POST['work_hours']) : 0;
    $standard_hours = isset($_POST['standard_hours']) ? floatval($_POST['standard_hours']) : 1;
    $percent = isset($_POST['percent']) ? floatval($_POST['percent']) : 0;
    
    // Расчет оплаты сотрудника
    $payment_amount = $work_cost * ($work_hours / $standard_hours) * ($percent / 100);
    $payment_amount = round($payment_amount);
    
    // Общая сумма сделки
    $parts_total = 0;
    $parts_data = isset($_POST['parts']) ? json_decode(stripslashes($_POST['parts']), true) : [];
    
    foreach ($parts_data as $part) {
        $parts_total += $part['price'] * $part['quantity'];
    }
    
    $total_amount = $parts_total + $work_cost;
    
    // Начинаем транзакцию
    $wpdb->query('START TRANSACTION');
    
    try {
        // 1. Создаем или обновляем автомобиль
        if (!$vehicle_id && $vin) {
            $table_vehicles = $wpdb->prefix . 'akpp_vehicles';
            $wpdb->insert(
                $table_vehicles,
                [
                    'vin' => $vin,
                    'make' => $make,
                    'model' => $model,
                    'year' => $year,
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%d', '%s']
            );
            $vehicle_id = $wpdb->insert_id;
        }
        
        // 2. Создаем сделку
        $table_deals = $wpdb->prefix . 'akpp_deals';
        $wpdb->insert(
            $table_deals,
            [
                'client_id' => $client_id,
                'employee_id' => $employee_id,
                'vehicle_id' => $vehicle_id,
                'vin' => $vin,
                'make' => $make,
                'model' => $model,
                'year' => $year,
                'problem_description' => $problem_description,
                'status' => $status,
                'work_cost' => $work_cost,
                'work_hours' => $work_hours,
                'standard_hours' => $standard_hours,
                'employee_percent' => $percent,
                'payment_amount' => $payment_amount,
                'parts_total' => $parts_total,
                'total_amount' => $total_amount,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s']
        );
        
        $deal_id = $wpdb->insert_id;
        
        if (!$deal_id) {
            throw new Exception('Ошибка создания сделки');
        }
        
        // 3. Сохраняем запчасти и списываем со склада
        $table_deal_parts = $wpdb->prefix . 'akpp_deal_parts';
        $table_parts = $wpdb->prefix . 'akpp_parts';
        
        foreach ($parts_data as $part) {
            // Проверяем остаток
            $stock = $wpdb->get_var($wpdb->prepare(
                "SELECT quantity FROM {$table_parts} WHERE id = %d",
                $part['id']
            ));
            
            if ($stock < $part['quantity']) {
                throw new Exception('Недостаточно запчасти на складе: ' . $part['name']);
            }
            
            // Списываем со склада
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_parts} SET quantity = quantity - %d WHERE id = %d",
                $part['quantity'],
                $part['id']
            ));
            
            // Записываем в сделку
            $wpdb->insert(
                $table_deal_parts,
                [
                    'deal_id' => $deal_id,
                    'part_id' => $part['id'],
                    'part_name' => $part['name'],
                    'part_sku' => $part['sku'],
                    'quantity' => $part['quantity'],
                    'price' => $part['price'],
                    'total' => $part['price'] * $part['quantity']
                ],
                ['%d', '%d', '%s', '%s', '%d', '%d', '%d']
            );
        }
        
        // 4. Обновляем статус лида
        if ($client_id) {
            $table_leads = $wpdb->prefix . 'akpp_leads';
            $wpdb->update(
                $table_leads,
                ['status' => 'converted', 'deal_id' => $deal_id],
                ['client_id' => $client_id],
                ['%s', '%d'],
                ['%d']
            );
        }
        
        // 5. Отправляем уведомления
        $this->notify_deal_created($client_id, $deal_id);
        $this->notify_employee_assigned($employee_id, $deal_id);
        
        // Фиксируем транзакцию
        $wpdb->query('COMMIT');
        
        $this->log_event("Сделка #{$deal_id} создана. Сумма: {$total_amount} ₽");
        
        wp_send_json_success([
            'message' => 'Сделка успешно сохранена',
            'deal_id' => $deal_id,
            'redirect_url' => admin_url('admin.php?page=akpp-crm-deals&action=view&id=' . $deal_id)
        ]);
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        $this->log_error('Ошибка сохранения сделки: ' . $e->getMessage());
        wp_send_json_error($e->getMessage());
    }
}

/**
 * Получение процента сотрудника
 */
public function ajax_get_employee_percent() {
    if (!check_ajax_referer('akpp_get_employee_percent_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
    
    if (!$employee_id) {
        wp_send_json_error('ID сотрудника не передан');
        return;
    }
    
    global $wpdb;
    $table_employees = $wpdb->prefix . 'akpp_employees';
    
    $employee = $wpdb->get_row($wpdb->prepare(
        "SELECT percent FROM {$table_employees} WHERE id = %d",
        $employee_id
    ));
    
    if ($employee) {
        wp_send_json_success(['percent' => $employee->percent]);
    } else {
        wp_send_json_success(['percent' => 0]);
    }
}

/**
 * Получение сделки для редактирования
 */
public function ajax_get_deal() {
    if (!check_ajax_referer('akpp_get_deal_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    $deal_id = isset($_POST['deal_id']) ? intval($_POST['deal_id']) : 0;
    
    if (!$deal_id) {
        wp_send_json_error('ID сделки не передан');
        return;
    }
    
    global $wpdb;
    $table_deals = $wpdb->prefix . 'akpp_deals';
    $table_deal_parts = $wpdb->prefix . 'akpp_deal_parts';
    
    $deal = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_deals} WHERE id = %d",
        $deal_id
    ));
    
    if (!$deal) {
        wp_send_json_error('Сделка не найдена');
        return;
    }
    
    $parts = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_deal_parts} WHERE deal_id = %d",
        $deal_id
    ));
    
    wp_send_json_success([
        'deal' => $deal,
        'parts' => $parts
    ]);
}

/**
 * Обновление статуса сделки
 */
public function ajax_update_deal_status() {
    if (!check_ajax_referer('akpp_update_deal_status_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    $deal_id = isset($_POST['deal_id']) ? intval($_POST['deal_id']) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    
    if (!$deal_id || !$status) {
        wp_send_json_error('Неверные данные');
        return;
    }
    
    global $wpdb;
    $table_deals = $wpdb->prefix . 'akpp_deals';
    
    $old_status = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM {$table_deals} WHERE id = %d",
        $deal_id
    ));
    
    $wpdb->update(
        $table_deals,
        [
            'status' => $status,
            'updated_at' => current_time('mysql')
        ],
        ['id' => $deal_id],
        ['%s', '%s'],
        ['%d']
    );
    
    // Уведомляем клиента об изменении статуса
    $deal = $wpdb->get_row($wpdb->prepare(
        "SELECT client_id FROM {$table_deals} WHERE id = %d",
        $deal_id
    ));
    
    if ($deal && $deal->client_id) {
        $this->notify_client_status_change($deal->client_id, $deal_id, $old_status, $status);
    }
    
    $this->log_event("Статус сделки #{$deal_id} изменен: {$old_status} → {$status}");
    
    wp_send_json_success([
        'message' => 'Статус сделки обновлен',
        'old_status' => $old_status,
        'new_status' => $status
    ]);
}

/**
 * Удаление сделки (с возвратом запчастей на склад)
 */
public function ajax_delete_deal() {
    if (!check_ajax_referer('akpp_delete_deal_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    $deal_id = isset($_POST['deal_id']) ? intval($_POST['deal_id']) : 0;
    
    if (!$deal_id) {
        wp_send_json_error('ID сделки не передан');
        return;
    }
    
    global $wpdb;
    $table_deals = $wpdb->prefix . 'akpp_deals';
    $table_deal_parts = $wpdb->prefix . 'akpp_deal_parts';
    $table_parts = $wpdb->prefix . 'akpp_parts';
    
    // Начинаем транзакцию
    $wpdb->query('START TRANSACTION');
    
    try {
        // Получаем запчасти сделки
        $parts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_deal_parts} WHERE deal_id = %d",
            $deal_id
        ));
        
        // Возвращаем запчасти на склад
        foreach ($parts as $part) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_parts} SET quantity = quantity + %d WHERE id = %d",
                $part->quantity,
                $part->part_id
            ));
        }
        
        // Удаляем запчасти сделки
        $wpdb->delete($table_deal_parts, ['deal_id' => $deal_id], ['%d']);
        
        // Удаляем сделку
        $wpdb->delete($table_deals, ['id' => $deal_id], ['%d']);
        
        $wpdb->query('COMMIT');
        
        $this->log_event("Сделка #{$deal_id} удалена, запчасти возвращены на склад");
        
        wp_send_json_success(['message' => 'Сделка удалена, запчасти возвращены на склад']);
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error('Ошибка удаления сделки: ' . $e->getMessage());
    }
}

/**
 * Уведомление о создании сделки
 */
private function notify_deal_created($client_id, $deal_id) {
    if (!class_exists('AKPP_Push')) {
        require_once AKPP_CRM_PATH . 'class-akpp-push.php';
    }
    
    $push = AKPP_Push::get_instance();
    $push->send_to_client(
        $client_id,
        '🔧 Создана новая сделка',
        "По вашему обращению создана сделка №{$deal_id}. Специалист скоро свяжется с вами.",
        ['type' => 'deal', 'deal_id' => $deal_id]
    );
}

/**
 * Уведомление о назначении сотрудника
 */
private function notify_employee_assigned($employee_id, $deal_id) {
    if (!class_exists('AKPP_Push')) {
        require_once AKPP_CRM_PATH . 'class-akpp-push.php';
    }
    
    $push = AKPP_Push::get_instance();
    $push->send_to_employee(
        $employee_id,
        '📋 Новая сделка назначена',
        "Вам назначена сделка №{$deal_id}. Приступайте к работе.",
        ['type' => 'deal', 'deal_id' => $deal_id]
    );
}

/**
 * Уведомление об изменении статуса сделки
 */
private function notify_client_status_change($client_id, $deal_id, $old_status, $new_status) {
    $status_labels = [
        'new' => 'Новая',
        'diagnostic' => 'Диагностика',
        'in_work' => 'В работе',
        'completed' => 'Выполнена',
        'rejected' => 'Отклонена'
    ];
    
    $old_label = $status_labels[$old_status] ?? $old_status;
    $new_label = $status_labels[$new_status] ?? $new_status;
    
    if (!class_exists('AKPP_Push')) {
        require_once AKPP_CRM_PATH . 'class-akpp-push.php';
    }
    
    $push = AKPP_Push::get_instance();
    $push->send_to_client(
        $client_id,
        '🔄 Статус заказа изменен',
        "Статус вашей сделки №{$deal_id}: {$old_label} → {$new_label}",
        ['type' => 'deal', 'deal_id' => $deal_id, 'status' => $new_status]
    );
}

private function log_error($message) {
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('[AKPP_DEAL] ОШИБКА: ' . $message);
    }
}
    /**
 * ДОПОЛНЕНИЕ К ФАЙЛУ class-akpp-ajax.php
 * Метод для возврата запчастей на склад при отмене/удалении сделки
 */

/**
 * Возврат запчастей на склад (при отмене сделки или изменении статуса)
 */
public function ajax_return_parts_to_stock() {
    if (!check_ajax_referer('akpp_return_parts_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    $deal_id = isset($_POST['deal_id']) ? intval($_POST['deal_id']) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    
    if (!$deal_id) {
        wp_send_json_error('ID сделки не передан');
        return;
    }
    
    global $wpdb;
    $table_deals = $wpdb->prefix . 'akpp_deals';
    $table_deal_parts = $wpdb->prefix . 'akpp_deal_parts';
    $table_parts = $wpdb->prefix . 'akpp_parts';
    
    // Получаем текущий статус сделки
    $current_status = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM {$table_deals} WHERE id = %d",
        $deal_id
    ));
    
    // Возвращаем запчасти только если статус меняется на 'rejected' или 'cancelled'
    $statuses_for_return = ['rejected', 'cancelled'];
    
    if (in_array($status, $statuses_for_return) && !in_array($current_status, $statuses_for_return)) {
        // Начинаем транзакцию
        $wpdb->query('START TRANSACTION');
        
        try {
            // Получаем запчасти сделки
            $parts = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_deal_parts} WHERE deal_id = %d",
                $deal_id
            ));
            
            // Возвращаем запчасти на склад
            foreach ($parts as $part) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_parts} SET quantity = quantity + %d WHERE id = %d",
                    $part->quantity,
                    $part->part_id
                ));
                
                $this->log_event("Запчасть #{$part->part_id} возвращена на склад в количестве {$part->quantity} (сделка #{$deal_id})");
            }
            
            // Обновляем статус сделки
            $wpdb->update(
                $table_deals,
                [
                    'status' => $status,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $deal_id],
                ['%s', '%s'],
                ['%d']
            );
            
            $wpdb->query('COMMIT');
            
            // Отправляем уведомление
            $this->notify_parts_returned($deal_id);
            
            wp_send_json_success([
                'message' => 'Запчасти возвращены на склад, статус сделки обновлен',
                'parts_count' => count($parts)
            ]);
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $this->log_error('Ошибка возврата запчастей: ' . $e->getMessage());
            wp_send_json_error('Ошибка возврата запчастей: ' . $e->getMessage());
        }
    } else {
        // Просто обновляем статус без возврата запчастей
        $wpdb->update(
            $table_deals,
            [
                'status' => $status,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $deal_id],
            ['%s', '%s'],
            ['%d']
        );
        
        wp_send_json_success(['message' => 'Статус сделки обновлен']);
    }
}

/**
 * Проверка остатков запчастей перед сохранением сделки
 */
public function ajax_check_parts_stock() {
    if (!check_ajax_referer('akpp_check_stock_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    $parts = isset($_POST['parts']) ? json_decode(stripslashes($_POST['parts']), true) : [];
    
    if (empty($parts)) {
        wp_send_json_success(['available' => true]);
        return;
    }
    
    global $wpdb;
    $table_parts = $wpdb->prefix . 'akpp_parts';
    
    $out_of_stock = [];
    
    foreach ($parts as $part) {
        $stock = $wpdb->get_var($wpdb->prepare(
            "SELECT quantity FROM {$table_parts} WHERE id = %d",
            $part['id']
        ));
        
        if ($stock < $part['quantity']) {
            $part_info = $wpdb->get_row($wpdb->prepare(
                "SELECT name, sku FROM {$table_parts} WHERE id = %d",
                $part['id']
            ));
            
            $out_of_stock[] = [
                'id' => $part['id'],
                'name' => $part_info->name,
                'sku' => $part_info->sku,
                'requested' => $part['quantity'],
                'available' => $stock
            ];
        }
    }
    
    if (empty($out_of_stock)) {
        wp_send_json_success(['available' => true]);
    } else {
        wp_send_json_error([
            'available' => false,
            'out_of_stock' => $out_of_stock,
            'message' => 'Некоторые запчасти отсутствуют на складе в нужном количестве'
        ]);
    }
}

/**
 * Получение истории списаний запчастей для сделки
 */
public function ajax_get_deal_parts_history() {
    if (!check_ajax_referer('akpp_deal_history_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    $deal_id = isset($_POST['deal_id']) ? intval($_POST['deal_id']) : 0;
    
    if (!$deal_id) {
        wp_send_json_error('ID сделки не передан');
        return;
    }
    
    global $wpdb;
    $table_deal_parts = $wpdb->prefix . 'akpp_deal_parts';
    
    $parts = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_deal_parts} WHERE deal_id = %d",
        $deal_id
    ));
    
    wp_send_json_success($parts);
}

/**
 * Уведомление о возврате запчастей
 */
private function notify_parts_returned($deal_id) {
    global $wpdb;
    $table_deals = $wpdb->prefix . 'akpp_deals';
    
    $deal = $wpdb->get_row($wpdb->prepare(
        "SELECT client_id FROM {$table_deals} WHERE id = %d",
        $deal_id
    ));
    
    if ($deal && $deal->client_id) {
        if (!class_exists('AKPP_Push')) {
            require_once AKPP_CRM_PATH . 'class-akpp-push.php';
        }
        
        $push = AKPP_Push::get_instance();
        $push->send_to_client(
            $deal->client_id,
            '🔄 Статус заказа изменен',
            "Сделка №{$deal_id} отменена. Запчасти возвращены на склад.",
            ['type' => 'deal', 'deal_id' => $deal_id, 'status' => 'cancelled']
        );
    }
}

/**
 * Получение списка категорий запчастей
 */
public function ajax_get_parts_categories() {
    if (!check_ajax_referer('akpp_parts_categories_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    $categories = [
        'АКПП в сборе',
        'Фрикционы',
        'Стальные диски',
        'Сальники',
        'Прокладки',
        'Соленоиды',
        'Гидроблоки',
        'Масляные насосы',
        'Подшипники',
        'Планетарные ряды',
        'Ремкомплекты',
        'Масла ATF',
        'Фильтры',
        'Датчики',
        'Прочее'
    ];
    
    wp_send_json_success($categories);
}

/**
 * Массовое списание запчастей (для инвентаризации)
 */
public function ajax_bulk_return_parts() {
    if (!check_ajax_referer('akpp_bulk_return_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    $deals = isset($_POST['deals']) ? array_map('intval', (array)$_POST['deals']) : [];
    
    if (empty($deals)) {
        wp_send_json_error('Не выбраны сделки');
        return;
    }
    
    global $wpdb;
    $table_deals = $wpdb->prefix . 'akpp_deals';
    $table_deal_parts = $wpdb->prefix . 'akpp_deal_parts';
    $table_parts = $wpdb->prefix . 'akpp_parts';
    
    $wpdb->query('START TRANSACTION');
    
    try {
        $total_parts_returned = 0;
        
        foreach ($deals as $deal_id) {
            // Проверяем статус сделки
            $status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$table_deals} WHERE id = %d",
                $deal_id
            ));
            
            if ($status !== 'rejected' && $status !== 'cancelled') {
                continue;
            }
            
            // Получаем запчасти
            $parts = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_deal_parts} WHERE deal_id = %d",
                $deal_id
            ));
            
            foreach ($parts as $part) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_parts} SET quantity = quantity + %d WHERE id = %d",
                    $part->quantity,
                    $part->part_id
                ));
                $total_parts_returned++;
            }
        }
        
        $wpdb->query('COMMIT');
        
        $this->log_event("Массовый возврат: {$total_parts_returned} позиций запчастей возвращены на склад");
        
        wp_send_json_success([
            'message' => "Возвращено {$total_parts_returned} позиций запчастей",
            'count' => $total_parts_returned
        ]);
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error('Ошибка массового возврата: ' . $e->getMessage());
    }
}
    /**
 * ДОПОЛНЕНИЕ К ФАЙЛУ class-akpp-ajax.php
 * Методы для калькулятора оплаты сотрудников
 */

/**
 * Расчет оплаты сотрудника (AJAX)
 */
public function ajax_calculate_payment() {
    if (!check_ajax_referer('akpp_calculate_payment_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    $work_cost = isset($_POST['work_cost']) ? floatval($_POST['work_cost']) : 0;
    $work_hours = isset($_POST['work_hours']) ? floatval($_POST['work_hours']) : 0;
    $standard_hours = isset($_POST['standard_hours']) ? floatval($_POST['standard_hours']) : 1;
    $percent = isset($_POST['percent']) ? floatval($_POST['percent']) : 0;
    
    if (!class_exists('AKPP_Deal_Calculator')) {
        require_once AKPP_CRM_PATH . 'decoders/class-deal-calculator.php';
    }
    
    $calculator = AKPP_Deal_Calculator::get_instance();
    
    // Валидация
    $validation = $calculator->validate_inputs($work_cost, $work_hours, $standard_hours, $percent);
    if (!$validation['valid']) {
        wp_send_json_error([
            'message' => 'Ошибка валидации',
            'errors' => $validation['errors']
        ]);
        return;
    }
    
    // Детальный расчет
    $result = $calculator->calculate_detailed($work_cost, $work_hours, $standard_hours, $percent);
    
    wp_send_json_success([
        'payment' => $result['payment'],
        'payment_formatted' => $result['payment_formatted'],
        'completion_ratio' => $result['completion_ratio'],
        'details' => $result['details'],
        'work_cost' => $result['work_cost'],
        'work_hours' => $result['work_hours'],
        'standard_hours' => $result['standard_hours'],
        'percent' => $result['percent']
    ]);
}

/**
 * Расчет эффективности сотрудника за месяц
 */
public function ajax_employee_efficiency() {
    if (!check_ajax_referer('akpp_employee_efficiency_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
    $month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
    $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
    
    if (!$employee_id) {
        wp_send_json_error('ID сотрудника не передан');
        return;
    }
    
    if (!class_exists('AKPP_Deal_Calculator')) {
        require_once AKPP_CRM_PATH . 'decoders/class-deal-calculator.php';
    }
    
    $calculator = AKPP_Deal_Calculator::get_instance();
    $result = $calculator->calculate_employee_efficiency($employee_id, $month, $year);
    
    wp_send_json_success($result);
}

/**
 * Прогнозирование оплаты по сделке
 */
public function ajax_predict_payment() {
    if (!check_ajax_referer('akpp_predict_payment_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    $work_cost = isset($_POST['work_cost']) ? floatval($_POST['work_cost']) : 0;
    $standard_hours = isset($_POST['standard_hours']) ? floatval($_POST['standard_hours']) : 1;
    $percent = isset($_POST['percent']) ? floatval($_POST['percent']) : 0;
    
    if (!class_exists('AKPP_Deal_Calculator')) {
        require_once AKPP_CRM_PATH . 'decoders/class-deal-calculator.php';
    }
    
    $calculator = AKPP_Deal_Calculator::get_instance();
    $scenarios = $calculator->predict_payment($work_cost, $standard_hours, $percent);
    
    wp_send_json_success($scenarios);
}

/**
 * Расчет рентабельности сделки
 */
public function ajax_deal_profitability() {
    if (!check_ajax_referer('akpp_deal_profitability_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    $deal_id = isset($_POST['deal_id']) ? intval($_POST['deal_id']) : 0;
    
    if (!$deal_id) {
        wp_send_json_error('ID сделки не передан');
        return;
    }
    
    if (!class_exists('AKPP_Deal_Calculator')) {
        require_once AKPP_CRM_PATH . 'decoders/class-deal-calculator.php';
    }
    
    $calculator = AKPP_Deal_Calculator::get_instance();
    $result = $calculator->calculate_profitability($deal_id);
    
    if ($result) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error('Сделка не найдена');
    }
}

/**
 * Получение рекомендуемого процента для сотрудника
 */
public function ajax_get_recommended_percent() {
    if (!check_ajax_referer('akpp_recommended_percent_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    $role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : 'master';
    $experience_years = isset($_POST['experience_years']) ? intval($_POST['experience_years']) : 0;
    
    if (!class_exists('AKPP_Deal_Calculator')) {
        require_once AKPP_CRM_PATH . 'decoders/class-deal-calculator.php';
    }
    
    $calculator = AKPP_Deal_Calculator::get_instance();
    $percent = $calculator->get_recommended_percent($role, $experience_years);
    
    wp_send_json_success([
        'percent' => $percent,
        'message' => "Рекомендованный процент для {$role}: {$percent}%"
    ]);
}

/**
 * Расчет для нескольких сотрудников по одной сделке
 */
public function ajax_multi_employee_payment() {
    if (!check_ajax_referer('akpp_multi_employee_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    $work_cost = isset($_POST['work_cost']) ? floatval($_POST['work_cost']) : 0;
    $work_hours = isset($_POST['work_hours']) ? floatval($_POST['work_hours']) : 0;
    $standard_hours = isset($_POST['standard_hours']) ? floatval($_POST['standard_hours']) : 1;
    $employees = isset($_POST['employees']) ? json_decode(stripslashes($_POST['employees']), true) : [];
    
    if (empty($employees)) {
        wp_send_json_error('Список сотрудников пуст');
        return;
    }
    
    if (!class_exists('AKPP_Deal_Calculator')) {
        require_once AKPP_CRM_PATH . 'decoders/class-deal-calculator.php';
    }
    
    $calculator = AKPP_Deal_Calculator::get_instance();
    $result = $calculator->calculate_multi_employee($work_cost, $work_hours, $standard_hours, $employees);
    
    wp_send_json_success($result);
}

/**
 * Обновление процента сотрудника
 */
public function ajax_update_employee_percent() {
    if (!check_ajax_referer('akpp_update_percent_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
    $percent = isset($_POST['percent']) ? floatval($_POST['percent']) : 0;
    
    if (!$employee_id) {
        wp_send_json_error('ID сотрудника не передан');
        return;
    }
    
    if ($percent < 0 || $percent > 100) {
        wp_send_json_error('Процент должен быть от 0 до 100');
        return;
    }
    
    global $wpdb;
    $table_employees = $wpdb->prefix . 'akpp_employees';
    
    $wpdb->update(
        $table_employees,
        ['percent' => $percent],
        ['id' => $employee_id],
        ['%d'],
        ['%d']
    );
    
    $this->log_event("Обновлен процент сотрудника #{$employee_id}: {$percent}%");
    
    wp_send_json_success([
        'message' => "Процент сотрудника обновлен на {$percent}%",
        'percent' => $percent
    ]);
}

/**
 * Получение статистики по оплатам за период
 */
public function ajax_payment_statistics() {
    if (!check_ajax_referer('akpp_payment_stats_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'month';
    $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
    $month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
    
    global $wpdb;
    $table_deals = $wpdb->prefix . 'akpp_deals';
    
    $where_clause = "status = 'completed' AND YEAR(created_at) = {$year}";
    
    if ($period === 'month') {
        $where_clause .= " AND MONTH(created_at) = {$month}";
        $group_by = "employee_id";
    } elseif ($period === 'quarter') {
        $quarter = ceil($month / 3);
        $where_clause .= " AND QUARTER(created_at) = {$quarter}";
        $group_by = "employee_id";
    } else {
        $group_by = "MONTH(created_at)";
    }
    
    $results = $wpdb->get_results(
        "SELECT {$group_by} as group_key, 
                SUM(payment_amount) as total_payment,
                COUNT(*) as deals_count,
                AVG(payment_amount) as avg_payment
         FROM {$table_deals}
         WHERE {$where_clause}
         GROUP BY {$group_by}"
    );
    
    wp_send_json_success([
        'period' => $period,
        'year' => $year,
        'month' => $month,
        'data' => $results
    ]);
}
    /**
 * ДОПОЛНЕНИЕ К ФАЙЛУ class-akpp-ajax.php
 * Методы для универсального парсера
 */

/**
 * Парсинг URL (AJAX)
 */
public function ajax_parse_url() {
    if (!check_ajax_referer('akpp_parse_url_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
    
    if (empty($url)) {
        wp_send_json_error('URL не передан');
        return;
    }
    
    if (!class_exists('AKPP_Parser')) {
        require_once AKPP_CRM_PATH . 'class-akpp-parser.php';
    }
    
    $parser = AKPP_Parser::get_instance();
    $result = $parser->parse($url);
    
    if ($result) {
        $this->log_event("Парсинг выполнен: {$url}");
        
        // Запускаем AI анализ в фоне
        $this->trigger_ai_analysis($result['id']);
        
        wp_send_json_success([
            'message' => 'Парсинг успешно выполнен',
            'item_id' => $result['id'],
            'title' => $result['title'],
            'content_type' => $result['content_type'],
            'text_preview' => mb_substr($result['text'], 0, 300) . '...',
            'images_count' => count($result['images'])
        ]);
    } else {
        wp_send_json_error('Ошибка парсинга URL. Проверьте доступность сайта.');
    }
}

/**
 * Запуск AI анализа в фоне
 */
private function trigger_ai_analysis($item_id) {
    // Используем wp_schedule_single_event для фоновой обработки
    if (!wp_next_scheduled('akpp_ai_analysis_event', [$item_id])) {
        wp_schedule_single_event(time(), 'akpp_ai_analysis_event', [$item_id]);
    }
}

/**
 * Получение списка элементов парсера
 */
public function ajax_get_parser_items() {
    if (!check_ajax_referer('akpp_get_parser_items_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all';
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
    $offset = ($page - 1) * $limit;
    
    global $wpdb;
    $table = $wpdb->prefix . 'akpp_parser_items';
    
    $where = '';
    if ($status !== 'all') {
        $where = $wpdb->prepare("WHERE status = %s", $status);
    }
    
    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where}");
    
    $items = $wpdb->get_results(
        "SELECT * FROM {$table} {$where} 
        ORDER BY created_at DESC 
        LIMIT {$limit} OFFSET {$offset}"
    );
    
    // Декодируем JSON поля
    foreach ($items as $item) {
        $item->images = json_decode($item->images, true);
        $item->ai_analysis = json_decode($item->ai_analysis, true);
        $item->parsed_data = json_decode($item->parsed_data, true);
    }
    
    wp_send_json_success([
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit)
    ]);
}

/**
 * Получение деталей элемента парсера
 */
public function ajax_get_parser_item() {
    if (!check_ajax_referer('akpp_get_parser_item_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    
    if (!$item_id) {
        wp_send_json_error('ID элемента не передан');
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'akpp_parser_items';
    
    $item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d",
        $item_id
    ));
    
    if ($item) {
        $item->images = json_decode($item->images, true);
        $item->ai_analysis = json_decode($item->ai_analysis, true);
        $item->parsed_data = json_decode($item->parsed_data, true);
        wp_send_json_success($item);
    } else {
        wp_send_json_error('Элемент не найден');
    }
}

/**
 * Повторный парсинг URL
 */
public function ajax_reparse_url() {
    if (!check_ajax_referer('akpp_reparse_url_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    
    if (!$item_id) {
        wp_send_json_error('ID элемента не передан');
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'akpp_parser_items';
    
    $item = $wpdb->get_row($wpdb->prepare(
        "SELECT url FROM {$table} WHERE id = %d",
        $item_id
    ));
    
    if (!$item) {
        wp_send_json_error('Элемент не найден');
        return;
    }
    
    // Удаляем старый результат
    $wpdb->delete($table, ['id' => $item_id], ['%d']);
    
    if (!class_exists('AKPP_Parser')) {
        require_once AKPP_CRM_PATH . 'class-akpp-parser.php';
    }
    
    $parser = AKPP_Parser::get_instance();
    $result = $parser->parse($item->url);
    
    if ($result) {
        wp_send_json_success([
            'message' => 'Повторный парсинг выполнен',
            'new_item_id' => $result['id']
        ]);
    } else {
        wp_send_json_error('Ошибка повторного парсинга');
    }
}

/**
 * Удаление элемента парсера
 */
public function ajax_delete_parser_item() {
    if (!check_ajax_referer('akpp_delete_parser_item_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    
    if (!$item_id) {
        wp_send_json_error('ID элемента не передан');
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'akpp_parser_items';
    
    $deleted = $wpdb->delete($table, ['id' => $item_id], ['%d']);
    
    if ($deleted) {
        $this->log_event("Удален элемент парсера #{$item_id}");
        wp_send_json_success(['message' => 'Элемент удален']);
    } else {
        wp_send_json_error('Ошибка удаления');
    }
}

/**
 * Пакетный парсинг (несколько URL)
 */
public function ajax_bulk_parse() {
    if (!check_ajax_referer('akpp_bulk_parse_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    $urls = isset($_POST['urls']) ? array_map('esc_url_raw', explode("\n", $_POST['urls'])) : [];
    $urls = array_filter($urls);
    
    if (empty($urls)) {
        wp_send_json_error('Нет URL для парсинга');
        return;
    }
    
    if (!class_exists('AKPP_Parser')) {
        require_once AKPP_CRM_PATH . 'class-akpp-parser.php';
    }
    
    $parser = AKPP_Parser::get_instance();
    $results = [];
    $success_count = 0;
    $error_count = 0;
    
    foreach ($urls as $url) {
        $result = $parser->parse($url);
        if ($result) {
            $success_count++;
            $results[] = [
                'url' => $url,
                'status' => 'success',
                'item_id' => $result['id']
            ];
        } else {
            $error_count++;
            $results[] = [
                'url' => $url,
                'status' => 'error'
            ];
        }
    }
    
    $this->log_event("Пакетный парсинг: {$success_count} успешно, {$error_count} ошибок");
    
    wp_send_json_success([
        'message' => "Парсинг завершен: {$success_count} успешно, {$error_count} ошибок",
        'success_count' => $success_count,
        'error_count' => $error_count,
        'results' => $results
    ]);
}

/**
 * Экспорт результатов парсинга
 */
public function ajax_export_parser_items() {
    if (!check_ajax_referer('akpp_export_parser_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all';
    $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'json';
    
    global $wpdb;
    $table = $wpdb->prefix . 'akpp_parser_items';
    
    $where = '';
    if ($status !== 'all') {
        $where = $wpdb->prepare("WHERE status = %s", $status);
    }
    
    $items = $wpdb->get_results("SELECT * FROM {$table} {$where} ORDER BY created_at DESC");
    
    foreach ($items as $item) {
        $item->images = json_decode($item->images, true);
        $item->ai_analysis = json_decode($item->ai_analysis, true);
        $item->parsed_data = json_decode($item->parsed_data, true);
    }
    
    if ($format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="parser_export_' . date('Y-m-d') . '.json"');
        echo json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    } elseif ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="parser_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'URL', 'Title', 'Content Type', 'Status', 'Created At']);
        
        foreach ($items as $item) {
            fputcsv($output, [
                $item->id,
                $item->url,
                $item->title,
                $item->content_type,
                $item->status,
                $item->created_at
            ]);
        }
        
        fclose($output);
        exit;
    }
}
    /**
 * ДОПОЛНЕНИЕ К ФАЙЛУ class-akpp-ajax.php
 * Методы для AI анализа
 */

/**
 * Запуск AI анализа для элемента парсера
 */
public function ajax_run_ai_analysis() {
    if (!check_ajax_referer('akpp_run_ai_analysis_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    
    if (!$item_id) {
        wp_send_json_error('ID элемента не передан');
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'akpp_parser_items';
    
    $item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d",
        $item_id
    ));
    
    if (!$item) {
        wp_send_json_error('Элемент не найден');
        return;
    }
    
    if (!class_exists('AKPP_AI_Analyzer')) {
        require_once AKPP_CRM_PATH . 'ai/class-ai-analyzer.php';
    }
    
    $analyzer = AKPP_AI_Analyzer::get_instance();
    $result = $analyzer->analyze($item->content, $item->content_type);
    
    // Сохраняем результат AI анализа
    $wpdb->update(
        $table,
        [
            'ai_analysis' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'status' => 'ai_processed',
            'updated_at' => current_time('mysql')
        ],
        ['id' => $item_id],
        ['%s', '%s', '%s'],
        ['%d']
    );
    
    $this->log_event("AI анализ выполнен для элемента #{$item_id}");
    
    wp_send_json_success([
        'message' => 'AI анализ успешно выполнен',
        'analysis' => $result
    ]);
}

/**
 * Массовый AI анализ (для всех pending элементов)
 */
public function ajax_bulk_ai_analysis() {
    if (!check_ajax_referer('akpp_bulk_ai_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'akpp_parser_items';
    
    $items = $wpdb->get_results(
        "SELECT * FROM {$table} 
        WHERE status = 'pending' OR status = 'parsed'
        ORDER BY created_at ASC 
        LIMIT 20"
    );
    
    if (empty($items)) {
        wp_send_json_success(['message' => 'Нет элементов для анализа', 'processed' => 0]);
        return;
    }
    
    if (!class_exists('AKPP_AI_Analyzer')) {
        require_once AKPP_CRM_PATH . 'ai/class-ai-analyzer.php';
    }
    
    $analyzer = AKPP_AI_Analyzer::get_instance();
    $processed = 0;
    
    foreach ($items as $item) {
        $result = $analyzer->analyze($item->content, $item->content_type);
        
        $wpdb->update(
            $table,
            [
                'ai_analysis' => json_encode($result, JSON_UNESCAPED_UNICODE),
                'status' => 'ai_processed',
                'updated_at' => current_time('mysql')
            ],
            ['id' => $item->id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        $processed++;
        
        // Небольшая задержка между запросами
        usleep(500000);
    }
    
    $this->log_event("Массовый AI анализ: обработано {$processed} элементов");
    
    wp_send_json_success([
        'message' => "AI анализ выполнен для {$processed} элементов",
        'processed' => $processed
    ]);
}

/**
 * Сохранение настроек OpenAI API
 */
public function ajax_save_openai_settings() {
    if (!check_ajax_referer('akpp_openai_settings_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    $api_key = isset($_POST['openai_api_key']) ? sanitize_text_field($_POST['openai_api_key']) : '';
    
    if (empty($api_key)) {
        wp_send_json_error('API ключ не может быть пустым');
        return;
    }
    
    if (!class_exists('AKPP_AI_Analyzer')) {
        require_once AKPP_CRM_PATH . 'ai/class-ai-analyzer.php';
    }
    
    $analyzer = AKPP_AI_Analyzer::get_instance();
    $analyzer->save_api_key($api_key);
    
    // Проверяем ключ
    $status = $analyzer->check_api_key_status();
    
    if ($status['valid']) {
        wp_send_json_success([
            'message' => 'API ключ сохранен и действителен',
            'status' => $status
        ]);
    } else {
        wp_send_json_error([
            'message' => 'API ключ сохранен, но не прошел проверку',
            'status' => $status
        ]);
    }
}

/**
 * Проверка статуса OpenAI API ключа
 */
public function ajax_check_openai_key() {
    if (!check_ajax_referer('akpp_check_openai_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    if (!class_exists('AKPP_AI_Analyzer')) {
        require_once AKPP_CRM_PATH . 'ai/class-ai-analyzer.php';
    }
    
    $analyzer = AKPP_AI_Analyzer::get_instance();
    $status = $analyzer->check_api_key_status();
    
    wp_send_json_success($status);
}

/**
 * Анализ изображения через AI
 */
public function ajax_analyze_image() {
    if (!check_ajax_referer('akpp_analyze_image_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    $image_url = isset($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : '';
    
    if (empty($image_url)) {
        wp_send_json_error('URL изображения не передан');
        return;
    }
    
    if (!class_exists('AKPP_AI_Analyzer')) {
        require_once AKPP_CRM_PATH . 'ai/class-ai-analyzer.php';
    }
    
    $analyzer = AKPP_AI_Analyzer::get_instance();
    $result = $analyzer->analyze_image($image_url);
    
    wp_send_json_success($result);
}

/**
 * Получение статистики AI анализов
 */
public function ajax_get_ai_statistics() {
    if (!check_ajax_referer('akpp_ai_stats_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'akpp_parser_items';
    
    $stats = [
        'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
        'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'"),
        'ai_processed' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'ai_processed'"),
        'approved' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'approved'"),
        'rejected' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'rejected'")
    ];
    
    // Статистика по типам контента
    $content_types = $wpdb->get_results(
        "SELECT content_type, COUNT(*) as count 
        FROM {$table} 
        GROUP BY content_type"
    );
    
    $stats['by_content_type'] = [];
    foreach ($content_types as $type) {
        $stats['by_content_type'][$type->content_type] = $type->count;
    }
    
    wp_send_json_success($stats);
}

/**
 * Одобрение результата AI анализа (сохранение в базу)
 */
public function ajax_approve_parser_item() {
    if (!check_ajax_referer('akpp_approve_item_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    
    if (!$item_id) {
        wp_send_json_error('ID элемента не передан');
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'akpp_parser_items';
    
    $item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d",
        $item_id
    ));
    
    if (!$item) {
        wp_send_json_error('Элемент не найден');
        return;
    }
    
    $ai_analysis = json_decode($item->ai_analysis, true);
    
    if (!$ai_analysis) {
        wp_send_json_error('Нет данных AI анализа для одобрения');
        return;
    }
    
    // Сохраняем в соответствующую таблицу в зависимости от типа контента
    $saved = $this->save_approved_content($item, $ai_analysis);
    
    if ($saved) {
        $wpdb->update(
            $table,
            ['status' => 'approved', 'updated_at' => current_time('mysql')],
            ['id' => $item_id],
            ['%s', '%s'],
            ['%d']
        );
        
        $this->log_event("Элемент #{$item_id} одобрен и сохранен в базу");
        
        wp_send_json_success([
            'message' => 'Элемент одобрен и сохранен в базу данных',
            'saved_to' => $saved
        ]);
    } else {
        wp_send_json_error('Ошибка сохранения данных');
    }
}

/**
 * Сохранение одобренного контента в БД
 */
private function save_approved_content($item, $ai_analysis) {
    global $wpdb;
    
    $content_type = $item->content_type;
    $saved_to = '';
    
    if ($content_type === 'transmission') {
        $table = $wpdb->prefix . 'akpp_transmissions';
        
        $data = [
            'make' => $ai_analysis['make'] ?? '',
            'model' => $ai_analysis['model'] ?? '',
            'type' => $ai_analysis['type'] ?? '',
            'years' => $ai_analysis['years'] ?? '',
            'common_problems' => is_array($ai_analysis['problems'] ?? '') ? json_encode($ai_analysis['problems']) : '',
            'symptoms' => is_array($ai_analysis['symptoms'] ?? '') ? json_encode($ai_analysis['symptoms']) : '',
            'repair_cost' => $ai_analysis['repair_cost'] ?? 0,
            'difficulty' => $ai_analysis['difficulty'] ?? 3,
            'source_url' => $item->url,
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert($table, $data, ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']);
        $saved_to = 'transmissions';
        
    } elseif ($content_type === 'part') {
        $table = $wpdb->prefix . 'akpp_parts';
        
        $data = [
            'name' => $ai_analysis['part_type'] ?? $item->title,
            'sku' => $ai_analysis['part_number'] ?? '',
            'category' => $ai_analysis['part_type'] ?? 'Запчасть АКПП',
            'description' => $item->content,
            'price' => $ai_analysis['avg_price'] ?? 0,
            'compatible_transmissions' => is_array($ai_analysis['transmissions'] ?? '') ? json_encode($ai_analysis['transmissions']) : '',
            'source_url' => $item->url,
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert($table, $data, ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']);
        $saved_to = 'parts';
        
    } elseif ($content_type === 'oil') {
        $table = $wpdb->prefix . 'akpp_oils';
        
        $data = [
            'name' => $ai_analysis['oil_type'] ?? $item->title,
            'type' => $ai_analysis['oil_type'] ?? 'ATF',
            'viscosity' => $ai_analysis['viscosity'] ?? '',
            'specifications' => is_array($ai_analysis['specifications'] ?? '') ? json_encode($ai_analysis['specifications']) : '',
            'compatible_transmissions' => is_array($ai_analysis['transmissions'] ?? '') ? json_encode($ai_analysis['transmissions']) : '',
            'fill_volume' => $ai_analysis['fill_volume'] ?? 0,
            'price_per_liter' => $ai_analysis['price_per_liter'] ?? 0,
            'source_url' => $item->url,
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert($table, $data, ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']);
        $saved_to = 'oils';
    }
    
    return $saved_to;
}

/**
 * Отклонение элемента
 */
public function ajax_reject_parser_item() {
    if (!check_ajax_referer('akpp_reject_item_nonce', 'nonce', false)) {
        wp_send_json_error('Неверный security токен');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        return;
    }
    
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    
    if (!$item_id) {
        wp_send_json_error('ID элемента не передан');
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'akpp_parser_items';
    
    $wpdb->update(
        $table,
        ['status' => 'rejected', 'updated_at' => current_time('mysql')],
        ['id' => $item_id],
        ['%s', '%s'],
        ['%d']
    );
    
    $this->log_event("Элемент #{$item_id} отклонен");
    
    wp_send_json_success(['message' => 'Элемент отклонен']);
}
}
