<?php
/**
 * Класс для таблицы автомобилей в админке
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AKPP_Vehicles_Table extends WP_List_Table {
    
    public function __construct() {
        parent::__construct([
            'singular' => 'vehicle',
            'plural'   => 'vehicles',
            'ajax'     => false
        ]);
    }
    
    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_vehicles';
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Фильтры
        $market_filter = isset($_GET['market_filter']) ? sanitize_text_field($_GET['market_filter']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Построение WHERE
        $where = [];
        $params = [];
        
        if (!empty($market_filter)) {
            $where[] = "market = %s";
            $params[] = $market_filter;
        }
        
        if (!empty($search)) {
            $where[] = "(make LIKE %s OR model LIKE %s OR vin LIKE %s OR engine LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Сортировка
        $allowed_orderby = ['id', 'make', 'model', 'year', 'created_at'];
        $orderby = isset($_GET['orderby']) && in_array($_GET['orderby'], $allowed_orderby) 
            ? sanitize_text_field($_GET['orderby']) 
            : 'created_at';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Общее количество
        $count_query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, ...$params);
        }
        $total_items = $wpdb->get_var($count_query);
        
        // Получение данных
        $query = "SELECT * FROM {$table_name} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $query_params = array_merge($params, [$per_page, $offset]);
        $query = $wpdb->prepare($query, ...$query_params);
        
        $this->items = $wpdb->get_results($query, ARRAY_A);
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
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
            'cb'        => '<input type="checkbox" />',
            'id'        => 'ID',
            'make'      => 'Марка',
            'model'     => 'Модель',
            'year'      => 'Год',
            'vin'       => 'VIN',
            'engine'    => 'Двигатель',
            'market'    => 'Рынок',
            'actions'   => 'Действия',
        ];
    }
    
    public function get_sortable_columns() {
        return [
            'id'        => ['id', false],
            'make'      => ['make', true],
            'model'     => ['model', false],
            'year'      => ['year', false],
            'created_at' => ['created_at', true],
        ];
    }
    
    public function get_bulk_actions() {
        return [
            'delete' => '🗑️ Удалить',
        ];
    }
    
    public function process_bulk_action() {
        global $wpdb;
        $action = $this->current_action();
        if (!$action) return;
        
        check_admin_referer('bulk-' . $this->_args['plural']);
        
        $ids = isset($_REQUEST['vehicle']) ? array_map('intval', (array)$_REQUEST['vehicle']) : [];
        if (empty($ids)) return;
        
        $table_name = $wpdb->prefix . 'akpp_vehicles';
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        
        switch ($action) {
            case 'delete':
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$table_name} WHERE id IN ({$ids_placeholder})",
                    ...$ids
                ));
                echo '<div class="notice notice-success is-dismissible"><p>️ Удалено автомобилей: ' . count($ids) . '</p></div>';
                break;
        }
    }
    
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="vehicle[]" value="%s" />', esc_attr($item['id']));
    }
    
    protected function column_id($item) {
        return '#' . intval($item['id']);
    }
    
    protected function column_make($item) {
        return '<strong>' . esc_html($item['make'] ?? '—') . '</strong>';
    }
    
    protected function column_model($item) {
        return esc_html($item['model'] ?? '—');
    }
    
    protected function column_year($item) {
        $year = intval($item['year'] ?? 0);
        return $year > 0 ? esc_html($year) : '—';
    }
    
    protected function column_vin($item) {
        $vin = $item['vin'] ?? '';
        if (empty($vin)) return '—';
        return '<code style="background:#2d3748;padding:2px 6px;border-radius:4px;">' . esc_html($vin) . '</code>';
    }
    
    protected function column_engine($item) {
        return esc_html($item['engine'] ?? '—');
    }
    
    protected function column_market($item) {
        $market = $item['market'] ?? '';
        if (empty($market)) return '—';
        
        $market_labels = [
            'japan'  => ['label' => '🇵 Япония', 'color' => '#63b3ed'],
            'korea'  => ['label' => '🇰 Корея', 'color' => '#f6ad55'],
            'europe' => ['label' => '🇪🇺 Европа', 'color' => '#00ff88'],
            'usa'    => ['label' => '🇺🇸 США', 'color' => '#fc8181'],
            'asia'   => ['label' => '🌏 Азия', 'color' => '#9f7aea'],
        ];
        
        $info = $market_labels[$market] ?? ['label' => ucfirst($market), 'color' => '#a0aec0'];
        
        return sprintf(
            '<span style="display:inline-block;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;background:%s22;color:%s;">%s</span>',
            esc_attr($info['color']),
            esc_attr($info['color']),
            esc_html($info['label'])
        );
    }
    
    protected function column_actions($item) {
        $actions = [];
        
        // Кнопка редактирования
        $actions[] = sprintf(
            '<a href="?page=akpp-crm-vehicles&action=edit&id=%d" class="button button-small" title="Редактировать">✏️</a>',
            $item['id']
        );
        
        // Кнопка удаления
        $delete_url = wp_nonce_url(
            add_query_arg([
                'page'   => 'akpp-crm-vehicles',
                'action' => 'delete',
                'id'     => $item['id']
            ]),
            'delete_vehicle_' . $item['id']
        );
        
        $actions[] = sprintf(
            '<a href="%s" class="button button-small" title="Удалить" onclick="return confirm(\'Удалить автомобиль?\')">🗑️</a>',
            esc_url($delete_url)
        );
        
        return implode(' ', $actions);
    }
    
    protected function column_default($item, $column_name) {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '—';
    }
    
    protected function extra_tablenav($which) {
        if ($which !== 'top') return;
        
        $market_filter = isset($_GET['market_filter']) ? sanitize_text_field($_GET['market_filter']) : '';
        
        $markets = [
            ''       => 'Все рынки',
            'japan'  => '🇵 Япония',
            'korea'  => '🇰🇷 Корея',
            'europe' => '🇺 Европа',
            'usa'    => '🇺🇸 США',
            'asia'   => '🌏 Азия',
        ];
        ?>
        <div class="alignleft actions">
            <select name="market_filter">
                <?php foreach ($markets as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($market_filter, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="submit" name="filter_action" class="button" value="Фильтровать">
        </div>
        <?php
    }
    
    public function no_items() {
        echo 'Нет автомобилей для отображения';
    }
}