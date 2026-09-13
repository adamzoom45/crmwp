<?php
/**
 * АКПП45 Shop - Интернет-магазин запчастей и АКПП
 * 
 * @package AKPP_CRM
 * @version 1.0.0
 */
if (!defined('ABSPATH')) exit;

class AKPP_Shop {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // AJAX обработчики
        add_action('wp_ajax_akpp_shop_add_to_cart', [$this, 'ajax_add_to_cart']);
        add_action('wp_ajax_nopriv_akpp_shop_add_to_cart', [$this, 'ajax_add_to_cart']);
        
        add_action('wp_ajax_akpp_shop_remove_from_cart', [$this, 'ajax_remove_from_cart']);
        add_action('wp_ajax_nopriv_akpp_shop_remove_from_cart', [$this, 'ajax_remove_from_cart']);
        
        add_action('wp_ajax_akpp_shop_update_cart', [$this, 'ajax_update_cart']);
        add_action('wp_ajax_nopriv_akpp_shop_update_cart', [$this, 'ajax_update_cart']);
        
        add_action('wp_ajax_akpp_shop_get_cart', [$this, 'ajax_get_cart']);
        add_action('wp_ajax_nopriv_akpp_shop_get_cart', [$this, 'ajax_get_cart']);
        
        add_action('wp_ajax_akpp_shop_checkout', [$this, 'ajax_checkout']);
        add_action('wp_ajax_nopriv_akpp_shop_checkout', [$this, 'ajax_checkout']);
        
        add_action('wp_ajax_akpp_shop_get_products', [$this, 'ajax_get_products']);
        add_action('wp_ajax_nopriv_akpp_shop_get_products', [$this, 'ajax_get_products']);

        add_action('wp_ajax_akpp_shop_get_order_messages', [$this, 'ajax_get_order_messages']);
        add_action('wp_ajax_akpp_shop_send_order_message', [$this, 'ajax_send_order_message']);
        add_action('wp_ajax_akpp_shop_confirm_payment', [$this, 'ajax_confirm_payment']);
        
        add_action('wp_ajax_akpp_shop_save_product', [$this, 'ajax_save_product']);
        add_action('wp_ajax_akpp_shop_update_order_status', [$this, 'ajax_update_order_status']);
        
        // Шорткоды
        add_shortcode('akpp_shop_catalog', [$this, 'shortcode_catalog']);
        add_shortcode('akpp_shop_cart', [$this, 'shortcode_cart']);
        add_shortcode('akpp_shop_checkout', [$this, 'shortcode_checkout']);
        add_shortcode('akpp_shop_products', [$this, 'shortcode_products']);
    }
    
    // ========================================================================
    // ТОВАРЫ
    // ========================================================================
    
    /** Единая воронка статусов заказа: label / цвет / иконка / шаг ленты / ветка доставки */
    public static function order_statuses() {
        return [
            'new'            => ['label' => 'Новый',               'icon' => '🆕', 'color' => '#63b3ed', 'step' => 0, 'branch' => ''],
            'payment_pending'=> ['label' => 'Ожидает оплаты',      'icon' => '💳', 'color' => '#f6ad55', 'step' => 1, 'branch' => ''],
            'paid'           => ['label' => 'Оплата подтверждена', 'icon' => '✅', 'color' => '#00ff88', 'step' => 2, 'branch' => ''],
            'processing'     => ['label' => 'В обработке',         'icon' => '⚙️', 'color' => '#f6ad55', 'step' => 3, 'branch' => ''],
            'preparing'      => ['label' => 'В сборке',            'icon' => '📦', 'color' => '#f6ad55', 'step' => 3, 'branch' => ''],
            'ready_pickup'   => ['label' => 'Готов к выдаче',      'icon' => '🏁', 'color' => '#00ff88', 'step' => 4, 'branch' => 'pickup'],
            'ready_ship'     => ['label' => 'Готов к отправке',    'icon' => '📤', 'color' => '#00ff88', 'step' => 4, 'branch' => 'delivery'],
            'shipped'        => ['label' => 'Отправлен',           'icon' => '🚚', 'color' => '#41d2e2', 'step' => 5, 'branch' => 'delivery'],
            'completed'      => ['label' => 'Выдан / получен',     'icon' => '✔️', 'color' => '#00ff88', 'step' => 5, 'branch' => ''],
            'cancelled'      => ['label' => 'Отменён',             'icon' => '❌', 'color' => '#fc8181', 'step' => -1, 'branch' => ''],
            'refunded'       => ['label' => 'Возврат',             'icon' => '↩️', 'color' => '#fc8181', 'step' => -1, 'branch' => ''],
        ];
    }
    public static function status_meta($code) {
        $all = self::order_statuses();
        return $all[$code] ?? ['label' => $code, 'icon' => '•', 'color' => '#9ca3af', 'step' => 0, 'branch' => ''];
    }
    public function get_products($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_products';
        
        $defaults = [
            'category' => '',
            'condition' => '',
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'active_only' => true,
            'search' => '',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = [];
        $params = [];
        
        if ($args['active_only']) {
            $where[] = "is_active = 1";
        }
        
        if (!empty($args['category'])) {
            $where[] = "category = %s";
            $params[] = $args['category'];
        }
        
        if (!empty($args['condition'])) {
            $where[] = "condition_type = %s";
            $params[] = $args['condition'];
        }
        
        if (!empty($args['search'])) {
            $where[] = "(name LIKE %s OR sku LIKE %s OR description LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $query = "SELECT * FROM {$table} {$where_clause} ORDER BY {$args['orderby']} {$args['order']} LIMIT {$args['per_page']} OFFSET {$offset}";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return $wpdb->get_results($query);
    }
    
    public function get_product($product_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_products';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND is_active = 1",
            $product_id
        ));
    }
    
    public function get_categories() {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_categories';
        
        return $wpdb->get_results(
            "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
        );
    }
    
    // ========================================================================
    // КОРЗИНА
    // ========================================================================
    
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        return session_id();
    }
    
    public function get_cart() {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_cart';
        $session_id = $this->get_session_id();
        
        $cart_items = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, p.name, p.price, p.sku, p.condition_type, p.quality_grade, p.images, p.purchase_price
             FROM {$table} c
             JOIN {$wpdb->prefix}akpp_shop_products p ON c.product_id = p.id
             WHERE c.session_id = %s",
            $session_id
        ));
        
        $total = 0;
        foreach ($cart_items as $item) {
            $item->item_total = $item->price * $item->quantity;
            $total += $item->item_total;
        }
        
        return [
            'items' => $cart_items,
            'total' => $total,
            'count' => count($cart_items)
        ];
    }
    
    public function add_to_cart($product_id, $quantity = 1) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_cart';
        $session_id = $this->get_session_id();
        
        // Проверяем наличие товара
        $product = $this->get_product($product_id);
        if (!$product) {
            return ['success' => false, 'message' => 'Товар не найден'];
        }
        
        // Проверяем наличие на складе
        if ($product->stock < $quantity) {
            return ['success' => false, 'message' => 'Недостаточно товара на складе'];
        }
        
        // Проверяем, есть ли уже в корзине
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE session_id = %s AND product_id = %d",
            $session_id, $product_id
        ));
        
        if ($existing) {
            $wpdb->update($table, 
                ['quantity' => $quantity],
                ['id' => $existing]
            );
        } else {
            $wpdb->insert($table, [
                'session_id' => $session_id,
                'product_id' => $product_id,
                'quantity' => $quantity
            ]);
        }
        
        return ['success' => true, 'message' => 'Товар добавлен в корзину'];
    }
    
    public function remove_from_cart($cart_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_cart';
        $session_id = $this->get_session_id();
        
        $wpdb->delete($table, [
            'id' => $cart_id,
            'session_id' => $session_id
        ]);
        
        return ['success' => true, 'message' => 'Товар удалён из корзины'];
    }
    
    public function update_cart_quantity($cart_id, $quantity) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_cart';
        $session_id = $this->get_session_id();
        
        $wpdb->update($table,
            ['quantity' => $quantity],
            ['id' => $cart_id, 'session_id' => $session_id]
        );
        
        return ['success' => true, 'message' => 'Количество обновлено'];
    }
    
    public function clear_cart() {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_cart';
        $session_id = $this->get_session_id();
        
        $wpdb->delete($table, ['session_id' => $session_id]);
        
        return ['success' => true, 'message' => 'Корзина очищена'];
    }
    
    // ========================================================================
    // ЗАКАЗЫ
    // ========================================================================
    
    public function create_order($data) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'akpp_shop_orders';
        $items_table = $wpdb->prefix . 'akpp_shop_order_items';
        $cart_table = $wpdb->prefix . 'akpp_shop_cart';
        
        $session_id = $this->get_session_id();
        $cart = $this->get_cart();
        
        if (empty($cart['items'])) {
            return ['success' => false, 'message' => 'Корзина пуста'];
        }
        
        // Генерируем номер заказа
        $order_number = 'AKPP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Создаём заказ
        $order_data = [
            'order_number' => $order_number,
            'client_name' => sanitize_text_field($data['name']),
            'client_phone' => sanitize_text_field($data['phone']),
            'client_email' => sanitize_email($data['email'] ?? ''),
            'client_address' => sanitize_textarea_field($data['address'] ?? ''),
            'payment_method' => sanitize_text_field($data['payment_method'] ?? 'cash'),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
              'delivery_type' => sanitize_text_field($data['delivery_type'] ?? 'pickup'),
            'subtotal' => $cart['total'],
            'discount' => floatval($data['discount'] ?? 0),
            'shipping_cost' => floatval($data['shipping_cost'] ?? 0),
            'total' => $cart['total'] - floatval($data['discount'] ?? 0) + floatval($data['shipping_cost'] ?? 0),
        ];
        
        $wpdb->insert($orders_table, $order_data);
        $order_id = $wpdb->insert_id;
        
        // Добавляем позиции
        foreach ($cart['items'] as $item) {
            $wpdb->insert($items_table, [
                'order_id' => $order_id,
                'product_id' => $item->product_id,
                'product_name' => $item->name,
                'product_sku' => $item->sku,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $item->item_total,
                'purchase_price' => (float) ($item->purchase_price ?? 0),
            ]);
            
            // Уменьшаем количество на складе
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}akpp_shop_products SET stock = stock - %d WHERE id = %d",
                $item->quantity, $item->product_id
            ));
        }
        
        // Очищаем корзину
        $this->clear_cart();
        
        // Отправляем уведомление в Telegram
        $this->send_order_notification($order_id);
        
        return [
            'success' => true,
            'message' => 'Заказ оформлен',
            'order_number' => $order_number,
            'order_id' => $order_id
        ];
    }
    
    public function get_order($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_orders';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $order_id
        ));
    }
    
    public function get_order_items($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_order_items';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE order_id = %d",
            $order_id
        ));
    }
    
    public function update_order_status($order_id, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_orders';
        
        $wpdb->update($table,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $order_id]
        );
        
        return true;
    }
    
    private function send_order_notification($order_id) {
        $order = $this->get_order($order_id);
        if (!$order) return;
        
        $bot_token = get_option('akpp_telegram_bot_token', '');
        $chat_id = get_option('akpp_telegram_chat_id', '');
        
        if (empty($bot_token) || empty($chat_id)) return;
        
        $message = "🛒 *НОВЫЙ ЗАКАЗ #{$order->order_number}*\n\n";
        $message .= "👤 *Клиент:* {$order->client_name}\n";
        $message .= "📞 *Телефон:* {$order->client_phone}\n";
        if ($order->client_email) {
            $message .= "📧 *Email:* {$order->client_email}\n";
        }
        $message .= "\n💰 *Сумма:* " . number_format($order->total, 0, ',', ' ') . " ₽\n";
        $message .= "💳 *Оплата:* " . $this->get_payment_method_name($order->payment_method) . "\n";
        
        wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
            'body' => [
                'chat_id' => $chat_id,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ],
            'timeout' => 5
        ]);
    }
    
    private function get_payment_method_name($method) {
        $methods = [
            'cash' => 'Наличные',
            'card' => 'Карта',
            'transfer' => 'Перевод',
            'online' => 'Онлайн',
            'sbp' => 'СБП',
            'gateway' => 'Онлайн-эквайринг',
            'crypto' => 'Криптовалюта',
        ];
        return $methods[$method] ?? $method;
    }
    
    // ========================================================================
    // AJAX ОБРАБОТЧИКИ
    // ========================================================================
    
    public function ajax_add_to_cart() {
        $product_id = intval($_POST['product_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        $result = $this->add_to_cart($product_id, $quantity);
        wp_send_json($result);
    }
    
    public function ajax_remove_from_cart() {
        $cart_id = intval($_POST['cart_id'] ?? 0);
        $result = $this->remove_from_cart($cart_id);
        wp_send_json($result);
    }
    
    public function ajax_update_cart() {
        $cart_id = intval($_POST['cart_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        $result = $this->update_cart_quantity($cart_id, $quantity);
        wp_send_json($result);
    }
    
    public function ajax_get_cart() {
        $cart = $this->get_cart();
        wp_send_json_success($cart);
    }
    
    public function ajax_checkout() {
        if (!check_ajax_referer('akpp_shop_nonce', 'nonce', false)) {
            wp_send_json(['success' => false, 'message' => 'Ошибка безопасности']);
        }
        
        $data = [
            'name' => $_POST['name'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'address' => $_POST['address'] ?? '',
            'payment_method' => $_POST['payment_method'] ?? 'cash',
            'notes' => $_POST['notes'] ?? '',
              'delivery_type' => (isset($_POST['shipping']) && $_POST['shipping'] === 'delivery') ? 'delivery' : 'pickup',
        ];
        
        if (empty($data['name']) || empty($data['phone'])) {
            wp_send_json(['success' => false, 'message' => 'Заполните имя и телефон']);
        }
        
        $result = $this->create_order($data);
        wp_send_json($result);
    }
    
    public function ajax_get_products() {
        $args = [
            'category' => sanitize_text_field($_POST['category'] ?? ''),
            'condition' => sanitize_text_field($_POST['condition'] ?? ''),
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'per_page' => intval($_POST['per_page'] ?? 20),
            'page' => intval($_POST['page'] ?? 1),
        ];
        
        $products = $this->get_products($args);
        wp_send_json_success($products);
    }
    
    public function ajax_save_product() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Недостаточно прав'], 403);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_products';
        
        $id = intval($_POST['id'] ?? 0);
        $data = [
            'part_id' => intval($_POST['part_id'] ?? 0),
            'sku' => sanitize_text_field($_POST['sku'] ?? ''),
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? 'parts'),
            'condition_type' => sanitize_text_field($_POST['condition_type'] ?? 'new'),
            'quality_grade' => sanitize_text_field($_POST['quality_grade'] ?? 'A'),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'price' => floatval($_POST['price'] ?? 0),
            'old_price' => floatval($_POST['old_price'] ?? 0),
              'purchase_price' => floatval($_POST['purchase_price'] ?? 0),
            'stock' => intval($_POST['stock'] ?? 0),
            'is_active' => intval($_POST['is_active'] ?? 1),
            'is_featured' => intval($_POST['is_featured'] ?? 0),
        ];
        
        if (empty($data['name']) || empty($data['sku'])) {
            wp_send_json_error(['message' => 'Заполните название и артикул']);
        }
        
        try {
            if ($id > 0) {
                $result = $wpdb->update($table, $data, ['id' => $id]);
            } else {
                $result = $wpdb->insert($table, $data);
                $id = $wpdb->insert_id;
            }
            
            if ($result !== false) {
                wp_send_json_success(['message' => 'Товар сохранён', 'id' => $id]);
            } else {
                wp_send_json_error(['message' => 'Ошибка БД: ' . $wpdb->last_error]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }
    
    public function ajax_update_order_status() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Недостаточно прав'], 403);
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        $this->update_order_status($order_id, $status);
        wp_send_json_success(['message' => 'Статус обновлён']);
    }
    
    // ========================================================================
    // ЧАТ ПО ЗАКАЗУ (клиент <-> менеджер)
    // ========================================================================
    private function order_belongs_to_client($order_id, $user_id) {
        global $wpdb;
        $email = $wpdb->get_var($wpdb->prepare("SELECT user_email FROM {$wpdb->users} WHERE ID = %d", $user_id));
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_shop_orders WHERE id = %d AND client_email = %s",
            $order_id, $email));
    }

    public function ajax_get_order_messages() {
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Необходимо войти']);
        $order_id = intval($_POST['order_id'] ?? 0);
        $is_manager = current_user_can('akpp_view_shop');
        if (!$is_manager && !$this->order_belongs_to_client($order_id, get_current_user_id())) {
            wp_send_json_error(['message' => 'Заказ не найден']);
        }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT sender_type, message, created_at, msg_type, attachment, tx_hash FROM {$wpdb->prefix}akpp_shop_order_messages
             WHERE order_id = %d ORDER BY created_at ASC LIMIT 500", $order_id), ARRAY_A);
        // помечаем прочитанным то, что прислал собеседник
        $who = $is_manager ? 'client' : 'manager';
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}akpp_shop_order_messages SET is_read = 1 WHERE order_id = %d AND sender_type = %s AND is_read = 0",
            $order_id, $who));
        wp_send_json_success(['messages' => $rows ?: []]);
    }

    public function ajax_send_order_message() {
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Необходимо войти']);
        $order_id = intval($_POST['order_id'] ?? 0);
        $message  = sanitize_textarea_field($_POST['message'] ?? '');
        if (empty($message) && empty($_FILES['attachment']['name']) && empty($_POST['tx_hash'])) wp_send_json_error(['message' => 'Сообщение пустое']);
        $is_manager = current_user_can('akpp_view_shop');
        $nonce_ok = check_ajax_referer('akpp45_nonce', 'nonce', false) || check_ajax_referer('akpp_shop_order_chat_nonce', 'nonce', false);
        if (!$nonce_ok) wp_send_json_error(['message' => 'Ошибка безопасности']);
        if (!$is_manager && !$this->order_belongs_to_client($order_id, get_current_user_id())) {
            wp_send_json_error(['message' => 'Заказ не найден']);
        }
        global $wpdb;
        $attachment = ''; $tx_hash = sanitize_text_field($_POST['tx_hash'] ?? ''); $msg_type = 'message';
        if (!empty($_FILES['attachment']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $up = wp_handle_upload($_FILES['attachment'], ['test_form' => false]);
            if ($up && empty($up['error'])) { $attachment = esc_url_raw($up['url']); $msg_type = 'payment_proof'; }
        }
        if ($tx_hash !== '') $msg_type = 'payment_proof';
        if ($msg_type === 'payment_proof' && $message === '') $message = 'Подтверждение оплаты';
        $wpdb->insert($wpdb->prefix . 'akpp_shop_order_messages', [
            'order_id' => $order_id, 'user_id' => get_current_user_id(),
            'sender_type' => $is_manager ? 'manager' : 'client', 'msg_type' => $msg_type,
            'message' => $message, 'attachment' => $attachment, 'tx_hash' => $tx_hash,
            'is_read' => 0, 'created_at' => current_time('mysql'),
        ]);
        wp_send_json_success(['message' => 'Отправлено', 'time' => current_time('mysql')]);
    }
    public function ajax_confirm_payment() {
        if (!current_user_can('akpp_view_shop')) wp_send_json_error(['message' => 'Недостаточно прав']);
        check_ajax_referer('akpp45_nonce', 'nonce');
        $order_id = intval($_POST['order_id'] ?? 0);
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'akpp_shop_orders',
            ['payment_status' => 'paid', 'status' => 'paid', 'updated_at' => current_time('mysql')],
            ['id' => $order_id]);
        wp_send_json_success(['message' => 'Оплата подтверждена']);
    }
    // ========================================================================
    // ШОРТКОДЫ
    // ========================================================================
    
    public function shortcode_catalog($atts) {
        $atts = shortcode_atts([
            'category' => '',
            'per_page' => '20',
        ], $atts);
        
        $products = $this->get_products([
            'category' => $atts['category'],
            'per_page' => intval($atts['per_page'])
        ]);
        
        $categories = $this->get_categories();
        
        ob_start();
        include AKPP_CRM_DIR . '/templates/shop-frontend.php';
        return ob_get_clean();
    }
    
    public function shortcode_cart($atts) {
        $cart = $this->get_cart();
        ob_start();
        ?>
        <style>
        .edit-link{ display:none !important; } /* скрыть «Редактировать» страницы /cart/ */
        .akppc-wrap{ max-width:920px; margin:0 auto; padding:44px 24px 80px; }
        .akppc-title{ font-family:var(--font-display,'Unbounded',sans-serif); font-size:clamp(26px,4vw,40px); font-weight:800; letter-spacing:-.6px; margin:0 0 26px; color:var(--text-main,#fff); }
        .akppc-empty{ text-align:center; padding:56px 20px; color:var(--text-muted,#9ca3af); border:1px dashed rgba(255,255,255,.14); border-radius:18px; }
        .akppc-empty a{ color:var(--accent,#00ff88); font-weight:700; }
        .akppc-line{ display:grid; grid-template-columns:64px 1fr auto; gap:16px; align-items:center; padding:16px; margin-bottom:12px; border-radius:14px; border:1px solid var(--border,rgba(0,255,136,.2)); background:linear-gradient(180deg,var(--bg-secondary,#111827),var(--bg-primary,#0a0f1c)); }
        .akppc-ico{ width:64px; height:64px; border-radius:12px; display:grid; place-items:center; font-size:30px; background:rgba(0,255,136,.08); }
        .akppc-info .nm{ font-weight:700; color:var(--text-main,#fff); font-size:15px; line-height:1.35; }
        .akppc-info .sku{ color:var(--text-muted,#9ca3af); font-size:11.5px; letter-spacing:.6px; margin-top:3px; font-variant-numeric:tabular-nums; }
        .akppc-info .pr{ color:var(--accent,#00ff88); font-weight:800; margin-top:6px; font-variant-numeric:tabular-nums; }
        .akppc-right{ display:flex; flex-direction:column; align-items:flex-end; gap:10px; }
        .akppc-qty{ display:inline-flex; align-items:center; border:1px solid rgba(255,255,255,.14); border-radius:10px; overflow:hidden; }
        .akppc-qty button{ width:34px; height:34px; border:none; background:rgba(255,255,255,.05); color:#fff; font-size:18px; cursor:pointer; transition:background .2s; }
        .akppc-qty button:hover{ background:rgba(0,255,136,.18); color:var(--accent,#00ff88); }
        .akppc-qty input{ width:42px; height:34px; border:none; background:transparent; color:#fff; text-align:center; font-weight:700; font-variant-numeric:tabular-nums; -moz-appearance:textfield; }
        .akppc-qty input::-webkit-outer-spin-button,.akppc-qty input::-webkit-inner-spin-button{ -webkit-appearance:none; margin:0; }
        .akppc-sum{ font-weight:800; color:var(--text-main,#fff); font-variant-numeric:tabular-nums; min-width:84px; text-align:right; }
        .akppc-rm{ width:34px; height:34px; border-radius:9px; border:1px solid rgba(255,255,255,.12); background:transparent; color:var(--text-muted,#9ca3af); cursor:pointer; font-size:15px; transition:all .2s; }
        .akppc-rm:hover{ border-color:#fc8181; color:#fc8181; background:rgba(252,129,129,.1); }
        .akppc-foot{ display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-top:22px; padding:20px 22px; border-radius:16px; border:1px solid var(--border,rgba(0,255,136,.2)); background:linear-gradient(180deg,var(--bg-secondary,#111827),var(--bg-primary,#0a0f1c)); }
        .akppc-total{ display:flex; align-items:baseline; gap:12px; }
        .akppc-total .lbl{ color:var(--text-muted,#9ca3af); font-weight:700; letter-spacing:.3px; }
        .akppc-total .val{ font-family:var(--font-display,'Unbounded',sans-serif); font-size:clamp(24px,4vw,34px); font-weight:800; letter-spacing:-.5px; color:var(--accent,#00ff88); font-variant-numeric:tabular-nums; }
        .akppc-checkout{ display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:14px 30px; border-radius:12px; text-decoration:none; font-weight:800; font-size:15px; letter-spacing:.2px; background:linear-gradient(135deg,var(--accent,#00ff88),var(--accent-dark,#00cc6a)); color:#08120c; transition:transform .2s ease, box-shadow .25s ease, filter .2s ease; }
        .akppc-checkout:hover{ transform:translateY(-2px); box-shadow:0 16px 36px -16px rgba(0,255,136,.7); filter:brightness(1.05); }
        @media (max-width:560px){ .akppc-line{ grid-template-columns:52px 1fr; } .akppc-ico{ width:52px; height:52px; font-size:24px; } .akppc-right{ grid-column:1 / -1; flex-direction:row; align-items:center; justify-content:space-between; } }
        </style>

        <section class="akppc-wrap">
            <h2 class="akppc-title">🛒 Корзина</h2>
            <?php if (empty($cart['items'])): ?>
                <div class="akppc-empty">
                    <p style="font-size:42px;margin:0 0 12px">🛒</p>
                    <p>Корзина пуста.</p>
                    <p><a href="<?php echo esc_url(home_url('/shop/')); ?>">Перейти в магазин →</a></p>
                </div>
            <?php else: ?>
                <div id="akppc-lines">
                    <?php foreach ($cart['items'] as $item): ?>
                    <div class="akppc-line" data-line="<?php echo (int) $item->id; ?>" data-price="<?php echo esc_attr($item->price); ?>">
                        <div class="akppc-ico">📦</div>
                        <div class="akppc-info">
                            <div class="nm"><?php echo esc_html($item->name); ?></div>
                            <div class="sku">Арт. <?php echo esc_html($item->sku); ?></div>
                            <div class="pr"><?php echo number_format($item->price, 0, ',', ' '); ?> ₽ / шт</div>
                        </div>
                        <div class="akppc-right">
                            <div class="akppc-qty">
                                <button type="button" class="akppc-minus" data-cart-id="<?php echo (int) $item->id; ?>" aria-label="Меньше">−</button>
                                <input type="number" class="akppc-qty-input" data-cart-id="<?php echo (int) $item->id; ?>" value="<?php echo (int) $item->quantity; ?>" min="1" max="99">
                                <button type="button" class="akppc-plus" data-cart-id="<?php echo (int) $item->id; ?>" aria-label="Больше">+</button>
                            </div>
                            <div class="akppc-sum"><?php echo number_format($item->item_total, 0, ',', ' '); ?> ₽</div>
                            <button type="button" class="akppc-rm" data-cart-id="<?php echo (int) $item->id; ?>" aria-label="Удалить">✕</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="akppc-foot">
                    <div class="akppc-total"><span class="lbl">Итого</span><span class="val" id="akppc-grand"><?php echo number_format($cart['total'], 0, ',', ' '); ?> ₽</span></div>
                    <a href="<?php echo esc_url(home_url('/checkout/')); ?>" class="akppc-checkout">Оформить заказ →</a>
                </div>
            <?php endif; ?>
        </section>

        <script>
        (function(){
            var AJAX = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            var fmt = function(n){ return Math.round(Number(n)||0).toLocaleString('ru-RU') + ' ₽'; };
            function recalc(){
                var total = 0;
                document.querySelectorAll('.akppc-line').forEach(function(line){
                    var p = Number(line.getAttribute('data-price')) || 0;
                    var q = Number(line.querySelector('.akppc-qty-input').value) || 0;
                    line.querySelector('.akppc-sum').textContent = fmt(p * q);
                    total += p * q;
                });
                var g = document.getElementById('akppc-grand'); if (g) g.textContent = fmt(total);
            }
            function post(data){
                var fd = new FormData(); for (var k in data) fd.append(k, data[k]);
                return fetch(AJAX, { method:'POST', body:fd, credentials:'same-origin' }).then(function(r){ return r.json(); });
            }
            function setQty(cartId, qty){
                qty = Math.max(1, Math.min(99, qty));
                var line = document.querySelector('.akppc-line[data-line="' + cartId + '"]'); if (!line) return;
                line.querySelector('.akppc-qty-input').value = qty;
                post({ action:'akpp_shop_update_cart', cart_id:cartId, quantity:qty }).then(function(res){
                    if (res && res.success) recalc();
                }).catch(function(){});
            }
            document.addEventListener('click', function(e){
                var m = e.target.closest('.akppc-minus'); if (m){ var i = m.closest('.akppc-line').querySelector('.akppc-qty-input'); setQty(m.getAttribute('data-cart-id'), (Number(i.value)||1) - 1); return; }
                var p = e.target.closest('.akppc-plus'); if (p){ var i2 = p.closest('.akppc-line').querySelector('.akppc-qty-input'); setQty(p.getAttribute('data-cart-id'), (Number(i2.value)||1) + 1); return; }
                var rm = e.target.closest('.akppc-rm'); if (rm){
                    var cartId = rm.getAttribute('data-cart-id');
                    post({ action:'akpp_shop_remove_from_cart', cart_id:cartId }).then(function(res){
                        if (res && res.success){ var line = document.querySelector('.akppc-line[data-line="' + cartId + '"]'); if (line) line.remove(); recalc(); if (!document.querySelectorAll('.akppc-line').length) location.reload(); }
                    }).catch(function(){});
                }
            });
            document.addEventListener('change', function(e){
                if (e.target.classList.contains('akppc-qty-input')) setQty(e.target.getAttribute('data-cart-id'), Number(e.target.value)||1);
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public function shortcode_checkout($atts) {
        $cart = $this->get_cart();
        $u = function_exists('is_user_logged_in') && is_user_logged_in() ? wp_get_current_user() : null;
        $u_name  = $u ? $u->display_name : '';
        $u_email = $u ? $u->user_email : '';
        $u_phone = $u ? get_user_meta($u->ID, 'phone', true) : '';
        $S = function ($k, $d = '') { return AKPP_Shop_Settings::get($k, $d); };

        $methods = [];
        $methods[] = ['cash', '🤝 При получении', 'Оплата при самовывозе или курьеру'];
        if (AKPP_Shop_Settings::has_payment('card'))    $methods[] = ['card', '💳 Перевод на карту', trim($S('card_bank') . ' · ' . $S('card_number'))];
        if (AKPP_Shop_Settings::has_payment('sbp'))     $methods[] = ['sbp', '📱 СБП', trim($S('sbp_bank') . ' · ' . $S('sbp_phone'))];
        if (AKPP_Shop_Settings::has_payment('gateway')) $methods[] = ['gateway', '🌐 Онлайн‑оплата', 'Банковской картой через защищённый шлюз'];
        if (AKPP_Shop_Settings::has_payment('crypto'))  $methods[] = ['crypto', '₿ Криптовалюта', 'USDT / BTC'];
        if (count($methods) < 2) { $methods[] = ['card', '💳 Перевод на карту', 'Реквизиты пришлём в подтверждении']; }

        $pickup = $S('pickup_address', 'г. Курган, ул. Бурова-Петрова, 121 (ГСК КАС №8, тёплый бокс)');

        ob_start();
        ?>
        <style>
        .akpp-checkout{ max-width:1040px; margin:0 auto; padding:44px 24px 80px; }
        .akpp-checkout-title{ font-family:var(--font-display,'Unbounded',sans-serif); font-size:clamp(26px,4vw,40px); font-weight:800; letter-spacing:-.6px; margin:0 0 8px; color:var(--text-main,#fff); }
        .akpp-checkout-sub{ color:var(--text-muted,#9ca3af); margin:0 0 30px; }
        .akpp-co-grid{ display:grid; grid-template-columns:1.4fr 1fr; gap:24px; align-items:start; }
        .akpp-co-panel{ background:linear-gradient(180deg,var(--bg-secondary,#111827),var(--bg-primary,#0a0f1c)); border:1px solid var(--border,rgba(0,255,136,.2)); border-radius:18px; padding:26px; position:relative; overflow:hidden; }
        .akpp-co-panel::before{ content:''; position:absolute; left:0; right:0; top:0; height:3px; background:linear-gradient(90deg,var(--accent,#00ff88),var(--accent-2,#41d2e2) 60%,var(--accent-3,#f5b544)); }
        .akpp-co-h{ position:relative; padding-left:14px; margin:0 0 18px; font-size:15px; font-weight:800; letter-spacing:.3px; color:var(--text-main,#fff); }
        .akpp-co-h::before{ content:''; position:absolute; left:0; top:.1em; bottom:.1em; width:3px; border-radius:3px; background:linear-gradient(180deg,var(--accent,#00ff88),var(--accent-dark,#00cc6a)); }
        .akpp-co-row{ display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .akpp-co-field{ margin-bottom:16px; }
        .akpp-co-field label{ display:block; color:var(--text-muted,#9ca3af); font-size:12.5px; font-weight:700; letter-spacing:.3px; margin-bottom:7px; }
        .akpp-co-field input,.akpp-co-field textarea,.akpp-co-field select{ width:100%; background:#0e1524; border:1.5px solid rgba(255,255,255,.12); border-radius:11px; color:#fff; padding:12px 14px; font-size:14px; box-sizing:border-box; transition:border-color .22s ease, box-shadow .22s ease, transform .22s ease; }
        .akpp-co-field input:focus,.akpp-co-field textarea:focus{ outline:none; border-color:var(--accent,#00ff88); box-shadow:0 0 0 4px rgba(0,255,136,.13); transform:translateY(-1px); }
        .akpp-pay-list{ display:flex; flex-direction:column; gap:10px; }
        .akpp-pay{ border:1.5px solid rgba(255,255,255,.12); border-radius:13px; background:#0e1524; overflow:hidden; transition:border-color .22s ease, box-shadow .22s ease; }
        .akpp-pay:has(input:checked){ border-color:var(--accent,#00ff88); box-shadow:0 0 0 3px rgba(0,255,136,.12); }
        .akpp-pay-head{ display:flex; align-items:center; gap:12px; padding:14px 16px; cursor:pointer; }
        .akpp-pay-head input{ width:18px; height:18px; accent-color:var(--accent,#00ff88); flex:0 0 auto; }
        .akpp-pay-name{ font-weight:700; color:var(--text-main,#fff); font-size:14.5px; }
        .akpp-pay-hint{ margin-left:auto; color:var(--text-muted,#9ca3af); font-size:12px; }
        .akpp-pay-detail{ display:none; padding:0 16px 16px 46px; color:var(--text-muted,#9ca3af); font-size:13.5px; line-height:1.6; }
        .akpp-pay:has(input:checked) .akpp-pay-detail{ display:block; animation:co-fade .3s ease both; }
        .akpp-pay-detail .rekv{ display:flex; align-items:center; justify-content:space-between; gap:10px; background:#111827; border:1px solid rgba(255,255,255,.1); border-radius:9px; padding:9px 12px; margin-top:8px; color:#fff; font-variant-numeric:tabular-nums; word-break:break-all; }
        .akpp-copy{ border:none; background:rgba(0,255,136,.14); color:var(--accent,#00ff88); font-weight:800; font-size:11.5px; padding:6px 11px; border-radius:8px; cursor:pointer; white-space:nowrap; transition:background .2s; }
        .akpp-copy:hover{ background:rgba(0,255,136,.26); }
        .akpp-ship{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .akpp-ship label{ display:flex; flex-direction:column; gap:4px; border:1.5px solid rgba(255,255,255,.12); border-radius:12px; padding:13px 15px; cursor:pointer; background:#0e1524; transition:border-color .22s ease; }
        .akpp-ship label:has(input:checked){ border-color:var(--accent,#00ff88); }
        .akpp-ship input{ accent-color:var(--accent,#00ff88); }
        .akpp-ship b{ color:var(--text-main,#fff); font-size:14px; }
        .akpp-ship small{ color:var(--text-muted,#9ca3af); font-size:12px; line-height:1.4; }
        .akpp-co-submit{ margin-top:8px; width:100%; border:none; cursor:pointer; padding:15px; border-radius:12px; font-weight:800; font-size:15px; letter-spacing:.3px; background:linear-gradient(135deg,var(--accent,#00ff88),var(--accent-dark,#00cc6a)); color:#08120c; transition:transform .2s ease, box-shadow .25s ease, filter .2s ease; }
        .akpp-co-submit:hover{ transform:translateY(-2px); box-shadow:0 16px 36px -16px rgba(0,255,136,.7); filter:brightness(1.05); }
        .akpp-co-submit:active{ transform:translateY(0) scale(.985); }
        .akpp-sum-line{ display:flex; justify-content:space-between; padding:9px 0; color:var(--text-muted,#9ca3af); font-size:14px; border-bottom:1px solid rgba(255,255,255,.07); }
        .akpp-sum-line span:last-child{ color:var(--text-main,#fff); font-variant-numeric:tabular-nums; }
        .akpp-sum-item{ display:flex; justify-content:space-between; gap:12px; padding:8px 0; font-size:13.5px; border-bottom:1px solid rgba(255,255,255,.06); }
        .akpp-sum-item .nm{ color:var(--text-main,#fff); }
        .akpp-sum-item .qt{ color:var(--text-muted,#9ca3af); white-space:nowrap; }
        .akpp-sum-total{ display:flex; justify-content:space-between; align-items:baseline; margin-top:16px; padding-top:14px; }
        .akpp-sum-total .lbl{ color:var(--text-muted,#9ca3af); font-weight:700; letter-spacing:.3px; }
        .akpp-sum-total .val{ font-family:var(--font-display,'Unbounded',sans-serif); font-size:clamp(26px,4vw,38px); font-weight:800; letter-spacing:-.6px; color:var(--accent,#00ff88); font-variant-numeric:tabular-nums; }
        .akpp-co-empty{ text-align:center; padding:50px 20px; color:var(--text-muted,#9ca3af); }
        .akpp-co-empty a{ color:var(--accent,#00ff88); font-weight:700; }
        @keyframes co-fade{ from{ opacity:0; transform:translateY(-6px);} to{ opacity:1; transform:none;} }
        @media (max-width:820px){ .akpp-co-grid{ grid-template-columns:1fr; } .akpp-co-row,.akpp-ship{ grid-template-columns:1fr; } }
        </style>

        <section class="akpp-checkout">
            <h2 class="akpp-checkout-title">Оформление заказа</h2>
            <p class="akpp-checkout-sub">Проверьте состав, выберите оплату и доставку — после оформления пришлю подтверждение и реквизиты.</p>

            <?php if (empty($cart['items'])): ?>
                <div class="akpp-co-panel akpp-co-empty">
                    <p style="font-size:42px;margin:0 0 12px">🛒</p>
                    <p>Корзина пуста — нечего оформлять.</p>
                    <p><a href="<?php echo esc_url(home_url('/shop/')); ?>">Перейти в магазин →</a></p>
                </div>
            <?php else: ?>
            <form id="akpp-co-form" class="akpp-co-grid">
                <?php wp_nonce_field('akpp_shop_nonce', 'nonce'); ?>
                <input type="text" name="akpp_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">

                <div class="akpp-co-panel">
                    <h3 class="akpp-co-h">Контактные данные</h3>
                    <div class="akpp-co-row">
                        <div class="akpp-co-field"><label>Имя *</label><input type="text" name="name" required value="<?php echo esc_attr($u_name); ?>"></div>
                        <div class="akpp-co-field"><label>Телефон *</label><input type="tel" name="phone" required value="<?php echo esc_attr($u_phone); ?>"></div>
                    </div>
                    <div class="akpp-co-field"><label>Email</label><input type="email" name="email" value="<?php echo esc_attr($u_email); ?>"></div>

                    <h3 class="akpp-co-h">Способ оплаты</h3>
                    <div class="akpp-pay-list">
                        <?php foreach ($methods as $i => $m): ?>
                        <div class="akpp-pay">
                            <label class="akpp-pay-head">
                                <input type="radio" name="payment_method" value="<?php echo esc_attr($m[0]); ?>" <?php checked($i, 0); ?>>
                                <span class="akpp-pay-name"><?php echo esc_html($m[1]); ?></span>
                                <?php if ($m[2]): ?><span class="akpp-pay-hint"><?php echo esc_html($m[2]); ?></span><?php endif; ?>
                            </label>
                            <div class="akpp-pay-detail">
                                <?php if ($m[0] === 'card'): ?>
                                    Получатель: <?php echo esc_html($S('card_holder', '—')); ?>
                                    <div class="rekv"><span><?php echo esc_html($S('card_number', 'реквизиты уточнит менеджер')); ?></span><button type="button" class="akpp-copy" data-copy="<?php echo esc_attr(preg_replace('/\s+/', '', $S('card_number'))); ?>">Копировать</button></div>
                                <?php elseif ($m[0] === 'sbp'): ?>
                                    Перевод по номеру <?php echo esc_html($S('sbp_phone', '—')); ?> (<?php echo esc_html($S('sbp_bank', 'банк')); ?>), получатель <?php echo esc_html($S('sbp_name', '—')); ?>.
                                    <div class="rekv"><span><?php echo esc_html($S('sbp_phone', '')); ?></span><button type="button" class="akpp-copy" data-copy="<?php echo esc_attr(preg_replace('/\D/', '', $S('sbp_phone'))); ?>">Копировать</button></div>
                                <?php elseif ($m[0] === 'gateway'): ?>
                                    После нажатия «Оформить заказ» откроется защищённая страница оплаты <?php echo esc_html(strtoupper($S('gateway_provider', 'платёжной системы'))); ?>.
                                <?php elseif ($m[0] === 'crypto'): ?>
                                    <?php if ($S('crypto_usdt')): ?>USDT (TRC‑20):<div class="rekv"><span><?php echo esc_html($S('crypto_usdt')); ?></span><button type="button" class="akpp-copy" data-copy="<?php echo esc_attr($S('crypto_usdt')); ?>">Копировать</button></div><?php endif; ?>
                                    <?php if ($S('crypto_btc')): ?>BTC:<div class="rekv"><span><?php echo esc_html($S('crypto_btc')); ?></span><button type="button" class="akpp-copy" data-copy="<?php echo esc_attr($S('crypto_btc')); ?>">Копировать</button></div><?php endif; ?>
                                    <small><?php echo esc_html($S('crypto_note', 'Сумма в крипте по курсу на момент оплаты.')); ?></small>
                                <?php else: ?>
                                    Оплатите при получении заказа — наличными или картой на месте.
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <h3 class="akpp-co-h" style="margin-top:22px">Доставка</h3>
                    <div class="akpp-ship">
                        <label><input type="radio" name="shipping" value="pickup" checked><b>📍 Самовывоз</b><small><?php echo esc_html($pickup); ?></small></label>
                        <label><input type="radio" name="shipping" value="delivery"><b>🚚 Транспортной компанией</b><small>СДЭК · Деловые Линии · ПЭК — стоимость рассчитаем по тарифу после оформления</small></label>
                    </div>
                    <div class="akpp-co-field" style="margin-top:14px"><label>Адрес доставки / пункт выдачи</label><textarea name="address" rows="2" placeholder="Город, индекс, пункт выдачи ТК или пожелания"></textarea></div>
                    <div class="akpp-co-field"><label>Комментарий к заказу</label><textarea name="notes" rows="2" placeholder="Марка/модель авто, срочность, вопросы"></textarea></div>

                    <button type="submit" class="akpp-co-submit">Оформить заказ</button>
                </div>

                <aside class="akpp-co-panel">
                    <h3 class="akpp-co-h">Ваш заказ</h3>
                    <?php foreach ($cart['items'] as $it): ?>
                        <div class="akpp-sum-item"><span class="nm"><?php echo esc_html($it->name); ?> <span class="qt">×<?php echo (int) $it->quantity; ?></span></span><span><?php echo number_format($it->item_total, 0, ',', ' '); ?> ₽</span></div>
                    <?php endforeach; ?>
                    <div class="akpp-sum-line"><span>Товары</span><span id="co-subtotal"><?php echo number_format($cart['total'], 0, ',', ' '); ?> ₽</span></div>
                    <div class="akpp-sum-line"><span>Доставка</span><span id="co-ship">Самовывоз — 0 ₽</span></div>
                    <div class="akpp-sum-total"><span class="lbl">Итого</span><span class="val" id="co-grand"><?php echo number_format($cart['total'], 0, ',', ' '); ?> ₽</span></div>
                    <p style="color:var(--text-muted,#9ca3af);font-size:12px;margin:14px 0 0;line-height:1.5">При доставке ТК итоговая стоимость уточняется после взвешивания по тарифу перевозчика.</p>
                </aside>
            </form>
            <?php endif; ?>
        </section>

        <script>
        (function(){
            var sub = <?php echo (float) $cart['total']; ?>;
            var fmt = function(n){ return Math.round(n).toLocaleString('ru-RU') + ' ₽'; };
            var shipEl = document.getElementById('co-ship');
            var grandEl = document.getElementById('co-grand');
            function recalc(){
                var r = document.querySelector('input[name="shipping"]:checked');
                var isTk = r && r.value === 'delivery';
                if (shipEl) shipEl.textContent = isTk ? 'ТК — по тарифу' : 'Самовывоз — 0 ₽';
                if (grandEl) grandEl.textContent = fmt(sub); /* доставка ТК не включается — считается отдельно */
            }
            document.querySelectorAll('input[name="shipping"]').forEach(function(r){ r.addEventListener('change', recalc); });
            document.querySelectorAll('.akpp-copy').forEach(function(b){
                b.addEventListener('click', function(e){
                    e.preventDefault(); var t = b.getAttribute('data-copy') || '';
                    var done = function(){ var o = b.textContent; b.textContent = '✓ Скопировано'; setTimeout(function(){ b.textContent = o; }, 1400); };
                    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).then(done, done); else done();
                });
            });
            /* самодостаточная отправка заказа (не зависит от shop.js) */
            var form = document.getElementById('akpp-co-form');
            if (form) form.addEventListener('submit', function(e){
                e.preventDefault();
                var btn = form.querySelector('button[type="submit"]'); var orig = btn.textContent;
                btn.disabled = true; btn.textContent = '⏳ Оформление…';
                var fd = new FormData(form); fd.append('action', 'akpp_shop_checkout');
                fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', { method:'POST', body:fd, credentials:'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        if (res && res.success){
                            var d = document.createElement('div'); d.textContent = '✅ Заказ оформлен! Номер: ' + (res.order_number || '');
                            d.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;padding:15px 22px;border-radius:10px;font-weight:700;box-shadow:0 10px 30px rgba(0,0,0,.4);color:#08120c;background:#00ff88;';
                            document.body.appendChild(d);
                            setTimeout(function(){ window.location.href = '<?php echo esc_js(home_url('/')); ?>'; }, 2000);
                        } else {
                            var d2 = document.createElement('div'); d2.textContent = '❌ ' + (res && res.message ? res.message : 'Ошибка');
                            d2.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;padding:15px 22px;border-radius:10px;font-weight:700;box-shadow:0 10px 30px rgba(0,0,0,.4);color:#fff;background:#fc8181;';
                            document.body.appendChild(d2); setTimeout(function(){ d2.remove(); }, 3500);
                            btn.disabled = false; btn.textContent = orig;
                        }
                    })
                    .catch(function(){ btn.disabled = false; btn.textContent = orig; });
            });
            recalc();
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public function shortcode_products($atts) {
        $atts = shortcode_atts([
            'category' => '',
            'per_page' => '12',
        ], $atts);
        
        $products = $this->get_products([
            'category' => $atts['category'],
            'per_page' => intval($atts['per_page'])
        ]);
        
        ob_start();
        ?>
        <div class="akpp-shop-products-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <div class="product-badge badge-<?php echo $product->condition_type; ?>">
                        <?php echo $product->condition_type === 'new' ? 'Новый' : 'Б/У'; ?>
                    </div>
                    <h3><?php echo esc_html($product->name); ?></h3>
                    <p class="product-sku">Артикул: <?php echo esc_html($product->sku); ?></p>
                    <div class="product-price">
                        <?php if ($product->old_price): ?>
                            <span class="old-price"><?php echo number_format($product->old_price, 0, ',', ' '); ?> ₽</span>
                        <?php endif; ?>
                        <span class="current-price"><?php echo number_format($product->price, 0, ',', ' '); ?> ₽</span>
                    </div>
                    <button class="btn-add-to-cart" data-product-id="<?php echo $product->id; ?>">
                        В корзину
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Инициализация
add_action('init', function() {
    if (class_exists('AKPP_Shop')) {
        AKPP_Shop::get_instance();
    }
});