<?php
/**
 * Таблица склада в админке
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AKPP_Parts_Table extends WP_List_Table {
    private $table_name;
    
    public function __construct() {
        parent::__construct([
            'singular' => 'part',
            'plural' => 'parts',
            'ajax' => false
        ]);
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'akpp_parts';
    }
    
    public function prepare_items() {
        global $wpdb;
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        $category_filter = isset($_GET['category_filter']) ? sanitize_text_field($_GET['category_filter']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $where = [];
        $params = [];
        
        if ($category_filter !== 'all') {
            $where[] = "category = %s";
            $params[] = $category_filter;
        }
        
        if (!empty($search)) {
            $where[] = "(name LIKE %s OR sku LIKE %s OR description LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, ...$params);
        }
        $total_items = $wpdb->get_var($count_query);
        
        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        $query = $wpdb->prepare($query, ...$params);
        
        $this->items = $wpdb->get_results($query);
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
        
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns()
        ];
        
        $this->process_bulk_action();
    }
    
    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'id' => 'ID',
            'name' => 'Наименование',
            'sku' => 'Артикул',
            'category' => 'Категория',
            'quantity' => 'Остаток',
            'purchase_price' => 'Закуп',
            'markup' => 'Наценка',
            'price' => 'Цена',
            'actions' => 'Действия'
        ];
    }
    
    public function get_sortable_columns() {
        return [
            'id' => ['id', false],
            'name' => ['name', false],
            'category' => ['category', false],
            'quantity' => ['quantity', true],
            'price' => ['price', true],
            'purchase_price' => ['purchase_price', true]
        ];
    }
    
    public function get_bulk_actions() {
        return ['delete' => '🗑️ Удалить'];
    }
    
    public function process_bulk_action() {
        global $wpdb;
        if (!$this->current_action()) return;
        
        $ids = isset($_REQUEST['part']) ? array_map('intval', (array)$_REQUEST['part']) : [];
        if (empty($ids)) return;
        
        if ($this->current_action() === 'delete') {
            $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
                $ids
            ));
            echo '<div class="notice notice-success"><p>Удалено: ' . count($ids) . '</p></div>';
        }
    }
    
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="part[]" value="%s" />', $item->id);
    }
    
    protected function column_id($item) {
        return intval($item->id);
    }
    
    protected function column_name($item) {
        $name = esc_html($item->name);
        $desc = !empty($item->description) ? '<br><small style="color:#718096;">' . esc_html(mb_substr($item->description, 0, 60)) . '...</small>' : '';
        return "<strong>{$name}</strong>{$desc}";
    }
    
    protected function column_sku($item) {
        return '<code style="background:#2d3748;padding:2px 6px;border-radius:4px;">' . esc_html($item->sku ?: '—') . '</code>';
    }
    
    protected function column_category($item) {
        $icons = [
            'parts' => '🔧 Запчасти',
            'oils' => '🛢️ Масло',
            'filters' => '🔰 Фильтры',
            'consumables' => '📎 Расходники',
            'tools' => '🔨 Инструмент'
        ];
        $cat = $item->category ?: 'parts';
        return '<span class="category-badge">' . ($icons[$cat] ?? $cat) . '</span>';
    }
    
    protected function column_quantity($item) {
        $qty = intval($item->quantity);
        $unit = $item->unit ?: 'шт';
        $color = $qty === 0 ? '#fc8181' : ($qty < 5 ? '#f6ad55' : '#00ff88');
        return "<strong style=\"color:{$color};\">{$qty}</strong> {$unit}";
    }
    
    protected function column_purchase_price($item) {
        $price = floatval($item->purchase_price);
        return $price > 0 ? number_format($price, 0, ',', ' ') . ' ₽' : '—';
    }
    
    protected function column_markup($item) {
        $markup = floatval($item->markup_percent);
        $color = $markup > 50 ? '#00ff88' : ($markup > 20 ? '#f6ad55' : '#fc8181');
        return "<strong style=\"color:{$color};\">{$markup}%</strong>";
    }
    
    protected function column_price($item) {
        $price = floatval($item->price);
        return "<strong style=\"color:#00ff88;font-size:15px;\">" . number_format($price, 0, ',', ' ') . " ₽</strong>";
    }
    
    protected function column_actions($item) {
        return sprintf(
            '<button class="button button-small btn-edit-part" data-id="%d" data-name="%s" data-sku="%s" data-category="%s" data-description="%s" data-quantity="%d" data-unit="%s" data-purchase-price="%.2f" data-markup="%.2f" data-price="%.2f" data-supplier="%s" data-location="%s" style="background:#00ff88;border-color:#00ff88;color:#1a1f2e;">✏️</button> <button class="button button-small btn-delete-part" data-id="%d" style="background:#fc8181;border-color:#fc8181;color:#fff;">🗑️</button>',
            $item->id,
            esc_attr($item->name),
            esc_attr($item->sku),
            esc_attr($item->category),
            esc_attr($item->description),
            intval($item->quantity),
            esc_attr($item->unit),
            floatval($item->purchase_price),
            floatval($item->markup_percent),
            floatval($item->price),
            esc_attr($item->supplier),
            esc_attr($item->location),
            $item->id
        );
    }
    
    protected function extra_tablenav($which) {
        if ($which !== 'top') return;
        $category_filter = isset($_GET['category_filter']) ? sanitize_text_field($_GET['category_filter']) : 'all';
        ?>
        <div class="alignleft actions">
            <select name="category_filter">
                <option value="all" <?php selected($category_filter, 'all'); ?>>Все категории</option>
                <option value="parts" <?php selected($category_filter, 'parts'); ?>>🔧 Запчасти</option>
                <option value="oils" <?php selected($category_filter, 'oils'); ?>>🛢️ Масла</option>
                <option value="filters" <?php selected($category_filter, 'filters'); ?>>🔰 Фильтры</option>
                <option value="consumables" <?php selected($category_filter, 'consumables'); ?>>📎 Расходники</option>
                <option value="tools" <?php selected($category_filter, 'tools'); ?>>🔨 Инструмент</option>
            </select>
            <input type="submit" name="filter_action" class="button" value="Фильтровать">
        </div>
        <?php
    }
    
    public function no_items() {
        echo 'Склад пуст. Добавьте первую позицию.';
    }
}