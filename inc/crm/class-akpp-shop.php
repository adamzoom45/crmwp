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
            "SELECT c.*, p.name, p.price, p.sku, p.condition_type, p.quality_grade, p.images
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
                'total' => $item->item_total
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
            'online' => 'Онлайн'
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
        <div class="akpp-shop-cart">
            <h2>🛒 Корзина</h2>
            
            <?php if (empty($cart['items'])): ?>
                <p>Корзина пуста</p>
            <?php else: ?>
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Товар</th>
                            <th>Цена</th>
                            <th>Количество</th>
                            <th>Сумма</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart['items'] as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($item->name); ?></strong><br>
                                    <small>Артикул: <?php echo esc_html($item->sku); ?></small>
                                </td>
                                <td><?php echo number_format($item->price, 0, ',', ' '); ?> ₽</td>
                                <td>
                                    <input type="number" class="cart-quantity" 
                                           data-cart-id="<?php echo $item->id; ?>" 
                                           value="<?php echo $item->quantity; ?>" 
                                           min="1" max="99">
                                </td>
                                <td><?php echo number_format($item->item_total, 0, ',', ' '); ?> ₽</td>
                                <td>
                                    <button class="btn-remove-from-cart" data-cart-id="<?php echo $item->id; ?>">
                                        ✕
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3"><strong>Итого:</strong></td>
                            <td colspan="2"><strong><?php echo number_format($cart['total'], 0, ',', ' '); ?> ₽</strong></td>
                        </tr>
                    </tfoot>
                </table>
                
                <a href="<?php echo esc_url(get_permalink()); ?>?page=checkout" class="btn-checkout">
                    Оформить заказ
                </a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function shortcode_checkout($atts) {
        ob_start();
        ?>
        <div class="akpp-shop-checkout">
            <h2> Оформление заказа</h2>
            
            <form id="akpp-checkout-form">
                <?php wp_nonce_field('akpp_shop_nonce', 'nonce'); ?>
                
                <div class="form-group">
                    <label>Имя *</label>
                    <input type="text" name="name" required>
                </div>
                
                <div class="form-group">
                    <label>Телефон *</label>
                    <input type="tel" name="phone" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email">
                </div>
                
                <div class="form-group">
                    <label>Адрес доставки</label>
                    <textarea name="address" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Способ оплаты</label>
                    <select name="payment_method">
                        <option value="cash">Наличные</option>
                        <option value="card">Карта</option>
                        <option value="transfer">Банковский перевод</option>
                        <option value="online">Онлайн оплата</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Комментарий</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn-submit-order">Оформить заказ</button>
            </form>
        </div>
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