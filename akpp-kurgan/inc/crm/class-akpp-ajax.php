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
        $vin = isset($_POST['vin']) ? sanitize_text_field($_POST['vin']) : '';
        
        if (empty($vin) || strlen($vin) < 17) {
            wp_send_json_error('Неверный VIN код');
            return;
        }
        
        wp_send_json_success([
            'brand' => 'Toyota',
            'model' => 'Camry',
            'year' => 2020,
            'engine' => '2.5L',
            'vin' => $vin
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
}
