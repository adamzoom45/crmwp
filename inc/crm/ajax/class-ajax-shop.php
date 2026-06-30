<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX модуль: Магазин
 * Товары, категории, заказы, управление остатками
 */
class AKPP_AJAX_Shop extends AKPP_AJAX_Base {
    
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
        // Товары
        add_action('wp_ajax_akpp_shop_save_product', [$this, 'ajax_save_product']);
        add_action('wp_ajax_akpp_shop_delete_product', [$this, 'ajax_delete_product']);
        add_action('wp_ajax_akpp_shop_get_products', [$this, 'ajax_get_products']);
        add_action('wp_ajax_akpp_shop_get_product', [$this, 'ajax_get_product']);
        add_action('wp_ajax_akpp_shop_update_stock', [$this, 'ajax_update_stock']);
        
        // Категории
        add_action('wp_ajax_akpp_shop_save_category', [$this, 'ajax_save_category']);
        add_action('wp_ajax_akpp_shop_delete_category', [$this, 'ajax_delete_category']);
        add_action('wp_ajax_akpp_shop_get_categories', [$this, 'ajax_get_categories']);
        
        // Заказы
        add_action('wp_ajax_akpp_shop_get_orders', [$this, 'ajax_get_orders']);
        add_action('wp_ajax_akpp_shop_get_order', [$this, 'ajax_get_order']);
        add_action('wp_ajax_akpp_shop_update_order_status', [$this, 'ajax_update_order_status']);
        add_action('wp_ajax_akpp_shop_delete_order', [$this, 'ajax_delete_order']);
    }
    
    // ========================================================================
    // ТОВАРЫ
    // ========================================================================
    
    public function ajax_save_product() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_products';
        $id = intval($_POST['id'] ?? 0);
        
        $data = [
            'name'           => sanitize_text_field($_POST['name'] ?? ''),
            'sku'            => sanitize_text_field($_POST['sku'] ?? ''),
            'category_id'    => intval($_POST['category_id'] ?? 0),
            'price'          => floatval($_POST['price'] ?? 0),
            'old_price'      => floatval($_POST['old_price'] ?? 0),
            'stock'          => intval($_POST['stock'] ?? 0),
            'condition_type' => sanitize_text_field($_POST['condition_type'] ?? 'used'),
            'description'    => sanitize_textarea_field($_POST['description'] ?? ''),
            'images'         => sanitize_textarea_field($_POST['images'] ?? ''),
            'is_active'      => isset($_POST['is_active']) ? 1 : 0,
            'updated_at'     => current_time('mysql')
        ];
        
        if (empty($data['name'])) {
            wp_send_json_error(['message' => 'Название товара обязательно']);
            return;
        }
        
        if ($data['price'] <= 0) {
            wp_send_json_error(['message' => 'Цена должна быть больше 0']);
            return;
        }
        
        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
            $id = $wpdb->insert_id;
        }
        
        wp_send_json_success([
            'message' => 'Товар сохранён',
            'id' => $id
        ]);
    }
    
    public function ajax_delete_product() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $wpdb->delete($wpdb->prefix . 'akpp_shop_products', ['id' => $id]);
        
        wp_send_json_success(['message' => 'Товар удалён']);
    }
    
    public function ajax_get_products() {
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_products';
        $category_id = intval($_POST['category_id'] ?? 0);
        $search = sanitize_text_field($_POST['search'] ?? '');
        $limit = min(100, intval($_POST['limit'] ?? 50));
        $offset = intval($_POST['offset'] ?? 0);
        
        $where = "1=1";
        $params = [];
        
        if ($category_id > 0) {
            $where .= " AND p.category_id = %d";
            $params[] = $category_id;
        }
        
        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (p.name LIKE %s OR p.sku LIKE %s)";
            $params[] = $like;
            $params[] = $like;
        }
        
        // Общее количество
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} p WHERE {$where}",
            $params
        ));
        
        // Товары с категориями
        $params[] = $limit;
        $params[] = $offset;
        
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, c.name as category_name
             FROM {$table} p
             LEFT JOIN {$wpdb->prefix}akpp_shop_categories c ON p.category_id = c.id
             WHERE {$where}
             ORDER BY p.created_at DESC
             LIMIT %d OFFSET %d",
            $params
        ), ARRAY_A);
        
        wp_send_json_success([
            'products' => $products,
            'total' => (int) $total
        ]);
    }
    
    public function ajax_get_product() {
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, c.name as category_name
             FROM {$wpdb->prefix}akpp_shop_products p
             LEFT JOIN {$wpdb->prefix}akpp_shop_categories c ON p.category_id = c.id
             WHERE p.id = %d",
            $id
        ), ARRAY_A);
        
        if (!$product) {
            wp_send_json_error(['message' => 'Товар не найден']);
            return;
        }
        
        wp_send_json_success(['product' => $product]);
    }
    
    public function ajax_update_stock() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $stock = intval($_POST['stock'] ?? 0);
        
        $wpdb->update(
            $wpdb->prefix . 'akpp_shop_products',
            ['stock' => $stock, 'updated_at' => current_time('mysql')],
            ['id' => $id]
        );
        
        wp_send_json_success(['message' => 'Остаток обновлён', 'stock' => $stock]);
    }
    
    // ========================================================================
    // КАТЕГОРИИ
    // ========================================================================
    
    public function ajax_save_category() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_categories';
        $id = intval($_POST['id'] ?? 0);
        
        $data = [
            'name'        => sanitize_text_field($_POST['name'] ?? ''),
            'slug'        => sanitize_title($_POST['slug'] ?? $_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'sort_order'  => intval($_POST['sort_order'] ?? 0),
            'is_active'   => isset($_POST['is_active']) ? 1 : 0,
            'updated_at'  => current_time('mysql')
        ];
        
        if (empty($data['name'])) {
            wp_send_json_error(['message' => 'Название категории обязательно']);
            return;
        }
        
        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
            $id = $wpdb->insert_id;
        }
        
        wp_send_json_success([
            'message' => 'Категория сохранена',
            'id' => $id
        ]);
    }
    
    public function ajax_delete_category() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        // Проверяем есть ли товары в категории
        $products_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}akpp_shop_products WHERE category_id = %d",
            $id
        ));
        
        if ($products_count > 0) {
            wp_send_json_error([
                'message' => "Нельзя удалить категорию: в ней {$products_count} товаров"
            ]);
            return;
        }
        
        $wpdb->delete($wpdb->prefix . 'akpp_shop_categories', ['id' => $id]);
        
        wp_send_json_success(['message' => 'Категория удалена']);
    }
    
    public function ajax_get_categories() {
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        
        $categories = $wpdb->get_results(
            "SELECT c.*, COUNT(p.id) as products_count
             FROM {$wpdb->prefix}akpp_shop_categories c
             LEFT JOIN {$wpdb->prefix}akpp_shop_products p ON c.id = p.category_id
             WHERE c.is_active = 1
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC",
            ARRAY_A
        );
        
        wp_send_json_success(['categories' => $categories]);
    }
    
    // ========================================================================
    // ЗАКАЗЫ
    // ========================================================================
    
    public function ajax_get_orders() {
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_orders';
        $status = sanitize_text_field($_POST['status'] ?? '');
        $limit = min(100, intval($_POST['limit'] ?? 50));
        
        $where = "1=1";
        $params = [];
        
        if (!empty($status)) {
            $where .= " AND o.status = %s";
            $params[] = $status;
        }
        
        $params[] = $limit;
        
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT o.*, u.display_name as user_name, u.user_email
             FROM {$table} o
             LEFT JOIN {$wpdb->users} u ON o.user_id = u.ID
             WHERE {$where}
             ORDER BY o.created_at DESC
             LIMIT %d",
            $params
        ), ARRAY_A);
        
        // Добавляем количество позиций в каждый заказ
        foreach ($orders as &$order) {
            $order['items_count'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}akpp_shop_order_items WHERE order_id = %d",
                $order['id']
            ));
        }
        
        wp_send_json_success(['orders' => $orders]);
    }
    
    public function ajax_get_order() {
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT o.*, u.display_name as user_name, u.user_email, u.user_phone
             FROM {$wpdb->prefix}akpp_shop_orders o
             LEFT JOIN {$wpdb->users} u ON o.user_id = u.ID
             WHERE o.id = %d",
            $id
        ), ARRAY_A);
        
        if (!$order) {
            wp_send_json_error(['message' => 'Заказ не найден']);
            return;
        }
        
        // Позиции заказа
        $order['items'] = $wpdb->get_results($wpdb->prepare(
            "SELECT oi.*, p.name, p.sku, p.images
             FROM {$wpdb->prefix}akpp_shop_order_items oi
             LEFT JOIN {$wpdb->prefix}akpp_shop_products p ON oi.product_id = p.id
             WHERE oi.order_id = %d",
            $id
        ), ARRAY_A);
        
        wp_send_json_success(['order' => $order]);
    }
    
    public function ajax_update_order_status() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        $valid_statuses = ['pending', 'processing', 'completed', 'cancelled', 'refunded'];
        if (!in_array($status, $valid_statuses)) {
            wp_send_json_error(['message' => 'Недопустимый статус']);
            return;
        }
        
        $wpdb->update(
            $wpdb->prefix . 'akpp_shop_orders',
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $id]
        );
        
        // Если статус "completed" - уменьшаем остатки
        if ($status === 'completed') {
            $this->decrease_stock($id);
        }
        
        wp_send_json_success(['message' => 'Статус заказа обновлён']);
    }
    
    public function ajax_delete_order() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        // Удаляем позиции заказа
        $wpdb->delete($wpdb->prefix . 'akpp_shop_order_items', ['order_id' => $id]);
        
        // Удаляем заказ
        $wpdb->delete($wpdb->prefix . 'akpp_shop_orders', ['id' => $id]);
        
        wp_send_json_success(['message' => 'Заказ удалён']);
    }
    
    /**
     * Уменьшение остатков при завершении заказа
     */
    private function decrease_stock($order_id) {
        global $wpdb;
        
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, quantity FROM {$wpdb->prefix}akpp_shop_order_items WHERE order_id = %d",
            $order_id
        ), ARRAY_A);
        
        foreach ($items as $item) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}akpp_shop_products 
                 SET stock = GREATEST(0, stock - %d), updated_at = NOW() 
                 WHERE id = %d",
                $item['quantity'],
                $item['product_id']
            ));
        }
    }
}