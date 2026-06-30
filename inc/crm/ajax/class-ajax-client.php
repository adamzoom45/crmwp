<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX модуль: Личный кабинет клиента
 * Авторизация, профиль, сделки, корзина, чат
 */
class AKPP_AJAX_Client extends AKPP_AJAX_Base {
    
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
        // Авторизация
        add_action('wp_ajax_akpp_client_login', [$this, 'ajax_client_login']);
        add_action('wp_ajax_nopriv_akpp_client_login', [$this, 'ajax_client_login']);
        
        add_action('wp_ajax_akpp_client_register', [$this, 'ajax_client_register']);
        add_action('wp_ajax_nopriv_akpp_client_register', [$this, 'ajax_client_register']);
        
        add_action('wp_ajax_akpp_client_logout', [$this, 'ajax_client_logout']);
        
        // Профиль
        add_action('wp_ajax_akpp_client_get_profile', [$this, 'ajax_client_get_profile']);
        add_action('wp_ajax_akpp_client_update_profile', [$this, 'ajax_client_update_profile']);
        
        // Сделки клиента
        add_action('wp_ajax_akpp_client_get_deals', [$this, 'ajax_client_get_deals']);
        add_action('wp_ajax_akpp_client_get_deal', [$this, 'ajax_client_get_deal']);
        
        // Корзина
        add_action('wp_ajax_akpp_client_add_to_cart', [$this, 'ajax_client_add_to_cart']);
        add_action('wp_ajax_nopriv_akpp_client_add_to_cart', [$this, 'ajax_client_add_to_cart']);
        
        add_action('wp_ajax_akpp_client_update_cart', [$this, 'ajax_client_update_cart']);
        add_action('wp_ajax_nopriv_akpp_client_update_cart', [$this, 'ajax_client_update_cart']);
        
        add_action('wp_ajax_akpp_client_remove_from_cart', [$this, 'ajax_client_remove_from_cart']);
        add_action('wp_ajax_nopriv_akpp_client_remove_from_cart', [$this, 'ajax_client_remove_from_cart']);
        
        add_action('wp_ajax_akpp_client_get_cart', [$this, 'ajax_client_get_cart']);
        add_action('wp_ajax_nopriv_akpp_client_get_cart', [$this, 'ajax_client_get_cart']);
        
        // Оформление заказа
        add_action('wp_ajax_akpp_client_checkout', [$this, 'ajax_client_checkout']);
        add_action('wp_ajax_nopriv_akpp_client_checkout', [$this, 'ajax_client_checkout']);
        
        // Чат с мастером
        add_action('wp_ajax_akpp_client_send_message', [$this, 'ajax_client_send_message']);
        add_action('wp_ajax_akpp_client_get_messages', [$this, 'ajax_client_get_messages']);
        
        // Загрузка файлов
        add_action('wp_ajax_akpp_client_upload_file', [$this, 'ajax_client_upload_file']);
        add_action('wp_ajax_nopriv_akpp_client_upload_file', [$this, 'ajax_client_upload_file']);
        
        // Обратный звонок / запись
        add_action('wp_ajax_akpp_client_request_callback', [$this, 'ajax_client_request_callback']);
        add_action('wp_ajax_nopriv_akpp_client_request_callback', [$this, 'ajax_client_request_callback']);
        
        add_action('wp_ajax_akpp_client_booking', [$this, 'ajax_client_booking']);
        add_action('wp_ajax_nopriv_akpp_client_booking', [$this, 'ajax_client_booking']);
    }
    
    // ========================================================================
    // АВТОРИЗАЦИЯ
    // ========================================================================
    
    public function ajax_client_login() {
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => 'Заполните все поля']);
            return;
        }
        
        $user = get_user_by('email', $email);
        if (!$user) {
            wp_send_json_error(['message' => 'Пользователь не найден']);
            return;
        }
        
        if (!wp_check_password($password, $user->user_pass, $user->ID)) {
            wp_send_json_error(['message' => 'Неверный пароль']);
            return;
        }
        
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        
        wp_send_json_success([
            'message' => 'Вход выполнен',
            'user' => [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email
            ]
        ]);
    }
    
    public function ajax_client_register() {
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($name) || empty($email) || empty($password)) {
            wp_send_json_error(['message' => 'Заполните обязательные поля']);
            return;
        }
        
        if (email_exists($email)) {
            wp_send_json_error(['message' => 'Email уже зарегистрирован']);
            return;
        }
        
        $user_id = wp_create_user($email, $password, $email);
        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
            return;
        }
        
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $name,
            'first_name' => $name
        ]);
        
        update_user_meta($user_id, 'phone', $phone);
        
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        wp_send_json_success([
            'message' => 'Регистрация успешна',
            'user_id' => $user_id
        ]);
    }
    
    public function ajax_client_logout() {
        check_ajax_referer('akpp45_nonce', 'nonce');
        wp_logout();
        wp_send_json_success(['message' => 'Выход выполнен']);
    }
    
    // ========================================================================
    // ПРОФИЛЬ
    // ========================================================================
    
    public function ajax_client_get_profile() {
        if (!$this->check_client_auth()) return;
        
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        
        wp_send_json_success([
            'user' => [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'phone' => get_user_meta($user_id, 'phone', true)
            ]
        ]);
    }
    
    public function ajax_client_update_profile() {
        if (!$this->check_client_auth()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $name = sanitize_text_field($_POST['name'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        
        if (empty($name)) {
            wp_send_json_error(['message' => 'Имя обязательно']);
            return;
        }
        
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $name,
            'first_name' => $name
        ]);
        
        update_user_meta($user_id, 'phone', $phone);
        
        wp_send_json_success(['message' => 'Профиль обновлён']);
    }
    
    // ========================================================================
    // СДЕЛКИ КЛИЕНТА
    // ========================================================================
    
    public function ajax_client_get_deals() {
        if (!$this->check_client_auth()) return;
        
        global $wpdb;
        $user_id = get_current_user_id();
        
        $deals = $wpdb->get_results($wpdb->prepare(
            "SELECT d.*, v.make, v.model, v.year, v.vin
             FROM {$wpdb->prefix}akpp_deals d
             LEFT JOIN {$wpdb->prefix}akpp_vehicles v ON d.vehicle_id = v.id
             WHERE d.client_id = %d
             ORDER BY d.created_at DESC",
            $user_id
        ), ARRAY_A);
        
        wp_send_json_success(['deals' => $deals]);
    }
    
    public function ajax_client_get_deal() {
        if (!$this->check_client_auth()) return;
        
        global $wpdb;
        $deal_id = intval($_POST['deal_id'] ?? 0);
        $user_id = get_current_user_id();
        
        $deal = $wpdb->get_row($wpdb->prepare(
            "SELECT d.*, v.make, v.model, v.year, v.vin
             FROM {$wpdb->prefix}akpp_deals d
             LEFT JOIN {$wpdb->prefix}akpp_vehicles v ON d.vehicle_id = v.id
             WHERE d.id = %d AND d.client_id = %d",
            $deal_id, $user_id
        ), ARRAY_A);
        
        if (!$deal) {
            wp_send_json_error(['message' => 'Сделка не найдена']);
            return;
        }
        
        wp_send_json_success(['deal' => $deal]);
    }
    
    // ========================================================================
    // КОРЗИНА
    // ========================================================================
    
    public function ajax_client_add_to_cart() {
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $user_id = $this->get_cart_user_id();
        $product_id = intval($_POST['product_id'] ?? 0);
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        
        if ($product_id <= 0) {
            wp_send_json_error(['message' => 'Товар не указан']);
            return;
        }
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_client_cart 
             WHERE user_id = %d AND product_id = %d",
            $user_id, $product_id
        ));
        
        if ($existing) {
            $wpdb->update(
                $wpdb->prefix . 'akpp_client_cart',
                ['quantity' => $quantity, 'updated_at' => current_time('mysql')],
                ['id' => $existing]
            );
        } else {
            $wpdb->insert($wpdb->prefix . 'akpp_client_cart', [
                'user_id' => $user_id,
                'product_id' => $product_id,
                'quantity' => $quantity,
                'created_at' => current_time('mysql')
            ]);
        }
        
        wp_send_json_success([
            'message' => 'Товар добавлен в корзину',
            'cart_count' => $this->get_cart_count($user_id)
        ]);
    }
    
    public function ajax_client_update_cart() {
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $user_id = $this->get_cart_user_id();
        $cart_id = intval($_POST['cart_id'] ?? 0);
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        
        $wpdb->update(
            $wpdb->prefix . 'akpp_client_cart',
            ['quantity' => $quantity, 'updated_at' => current_time('mysql')],
            ['id' => $cart_id, 'user_id' => $user_id]
        );
        
        wp_send_json_success([
            'message' => 'Корзина обновлена',
            'total' => $this->get_cart_total($user_id)
        ]);
    }
    
    public function ajax_client_remove_from_cart() {
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $user_id = $this->get_cart_user_id();
        $cart_id = intval($_POST['cart_id'] ?? 0);
        
        $wpdb->delete(
            $wpdb->prefix . 'akpp_client_cart',
            ['id' => $cart_id, 'user_id' => $user_id]
        );
        
        wp_send_json_success([
            'message' => 'Товар удалён из корзины',
            'cart_count' => $this->get_cart_count($user_id),
            'total' => $this->get_cart_total($user_id)
        ]);
    }
    
    public function ajax_client_get_cart() {
        $user_id = $this->get_cart_user_id();
        
        wp_send_json_success([
            'items' => $this->get_cart_items($user_id),
            'total' => $this->get_cart_total($user_id),
            'count' => $this->get_cart_count($user_id)
        ]);
    }
    
    // ========================================================================
    // ОФОРМЛЕНИЕ ЗАКАЗА
    // ========================================================================
    
    public function ajax_client_checkout() {
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        if (!$this->check_client_auth()) return;
        
        global $wpdb;
        $user_id = get_current_user_id();
        $cart_items = $this->get_cart_items($user_id);
        
        if (empty($cart_items)) {
            wp_send_json_error(['message' => 'Корзина пуста']);
            return;
        }
        
        $total = $this->get_cart_total($user_id);
        $order_number = 'ORD-' . date('Ymd') . '-' . str_pad($user_id, 5, '0', STR_PAD_LEFT);
        
        $wpdb->insert($wpdb->prefix . 'akpp_shop_orders', [
            'order_number' => $order_number,
            'user_id' => $user_id,
            'total' => $total,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ]);
        
        $order_id = $wpdb->insert_id;
        
        foreach ($cart_items as $item) {
            $wpdb->insert($wpdb->prefix . 'akpp_shop_order_items', [
                'order_id' => $order_id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price']
            ]);
        }
        
        $wpdb->delete($wpdb->prefix . 'akpp_client_cart', ['user_id' => $user_id]);
        
        $this->notify_admin_new_order($order_id, $order_number, $total);
        
        wp_send_json_success([
            'message' => 'Заказ оформлен',
            'order_number' => $order_number,
            'order_id' => $order_id
        ]);
    }
    
    // ========================================================================
    // ЧАТ С МАСТЕРОМ
    // ========================================================================
    
    public function ajax_client_send_message() {
        if (!$this->check_client_auth()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $user_id = get_current_user_id();
        $deal_id = intval($_POST['deal_id'] ?? 0);
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (empty($message)) {
            wp_send_json_error(['message' => 'Сообщение пустое']);
            return;
        }
        
        $wpdb->insert($wpdb->prefix . 'akpp_chat_messages', [
            'deal_id' => $deal_id,
            'sender_type' => 'client',
            'sender_id' => $user_id,
            'message' => $message,
            'created_at' => current_time('mysql')
        ]);
        
        $client_name = get_user_meta($user_id, 'first_name', true) ?: 'Клиент';
        $this->notify_employee_new_message($deal_id, $client_name, $message);
        
        wp_send_json_success(['message' => 'Сообщение отправлено']);
    }
    
    public function ajax_client_get_messages() {
        if (!$this->check_client_auth()) return;
        
        global $wpdb;
        $user_id = get_current_user_id();
        $deal_id = intval($_POST['deal_id'] ?? 0);
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}akpp_chat_messages 
             WHERE deal_id = %d 
             ORDER BY created_at ASC",
            $deal_id
        ), ARRAY_A);
        
        wp_send_json_success(['messages' => $messages]);
    }
    
    // ========================================================================
    // ЗАГРУЗКА ФАЙЛОВ
    // ========================================================================
    
    public function ajax_client_upload_file() {
        if (!$this->check_client_auth()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        if (empty($_FILES['file'])) {
            wp_send_json_error(['message' => 'Файл не загружен']);
            return;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $upload = wp_handle_upload($_FILES['file'], ['test_form' => false]);
        
        if (isset($upload['error'])) {
            wp_send_json_error(['message' => $upload['error']]);
            return;
        }
        
        wp_send_json_success([
            'url' => $upload['url'],
            'path' => $upload['file']
        ]);
    }
    
    // ========================================================================
    // ОБРАТНЫЙ ЗВОНОК / ЗАПИСЬ
    // ========================================================================
    
    public function ajax_client_request_callback() {
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        
        if (empty($name) || empty($phone)) {
            wp_send_json_error(['message' => 'Заполните все поля']);
            return;
        }
        
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'akpp_leads', [
            'full_name' => $name,
            'phone' => $phone,
            'source' => 'callback',
            'status' => 'new',
            'created_at' => current_time('mysql')
        ]);
        
        wp_send_json_success(['message' => 'Заявка принята, перезвоним в течение часа']);
    }
    
    public function ajax_client_booking() {
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $car = sanitize_text_field($_POST['car'] ?? '');
        $problem = sanitize_textarea_field($_POST['problem'] ?? '');
        $date = sanitize_text_field($_POST['date'] ?? '');
        
        if (empty($name) || empty($phone)) {
            wp_send_json_error(['message' => 'Заполните обязательные поля']);
            return;
        }
        
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'akpp_leads', [
            'full_name' => $name,
            'phone' => $phone,
            'car_brand' => $car,
            'problem' => $problem,
            'booking_date' => $date,
            'source' => 'site_booking',
            'status' => 'new',
            'created_at' => current_time('mysql')
        ]);
        
        wp_send_json_success(['message' => 'Заявка на запись принята']);
    }
}