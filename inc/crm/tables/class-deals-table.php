<?php
/**
 * Класс для таблицы сделок в админке
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AKPP_Deals_Table extends WP_List_Table {
    
    public function __construct() {
        parent::__construct([
            'singular' => 'deal',
            'plural'   => 'deals',
            'ajax'     => false
        ]);
    }
    
    public function prepare_items() {
        global $wpdb;
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Фильтры
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $employee_filter = isset($_GET['employee_filter']) ? intval($_GET['employee_filter']) : 0;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Построение WHERE
        $where = [];
        $params = [];
        
        if ($status_filter !== 'all') {
            $where[] = "d.status = %s";
            $params[] = $status_filter;
        }
        
        if ($employee_filter > 0) {
            $where[] = "d.employee_id = %d";
            $params[] = $employee_filter;
        }
        
        if (!empty($search)) {
            $where[] = "(c.full_name LIKE %s OR c.phone LIKE %s OR d.vin LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Сортировка
        $allowed_orderby = ['id', 'created_at', 'total_amount', 'status'];
        $orderby = isset($_GET['orderby']) && in_array($_GET['orderby'], $allowed_orderby) 
            ? sanitize_text_field($_GET['orderby']) 
            : 'created_at';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Получение общего количества
        $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}akpp_deals d {$where_clause}";
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, ...$params);
        }
        $total_items = $wpdb->get_var($count_query);
        
        // Получение данных с JOIN
        $query = "SELECT 
            d.*,
            c.full_name as client_name,
            c.phone as client_phone,
            v.make,
            v.model,
            v.year,
            e.name as employee_name
        FROM {$wpdb->prefix}akpp_deals d
        LEFT JOIN {$wpdb->prefix}akpp_site_users c ON d.client_id = c.id
        LEFT JOIN {$wpdb->prefix}akpp_vehicles v ON d.vehicle_id = v.id
        LEFT JOIN {$wpdb->prefix}akpp_employees e ON d.employee_id = e.id
        {$where_clause}
        ORDER BY d.{$orderby} {$order}
        LIMIT %d OFFSET %d";
        
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
            'cb'           => '<input type="checkbox" />',
            'id'           => 'ID',
            'client_name'  => 'Клиент',
            'vehicle'      => 'Автомобиль',
            'status'       => 'Статус',
            'total_amount' => 'Сумма',
            'created_at'   => 'Дата',
            'actions'      => 'Действия',
        ];
    }
    
    public function get_sortable_columns() {
        return [
            'id'           => ['id', false],
            'created_at'   => ['created_at', true],
            'total_amount' => ['total_amount', false],
            'status'       => ['status', false],
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
        
        $deal_ids = isset($_REQUEST['deal']) ? array_map('intval', (array)$_REQUEST['deal']) : [];
        if (empty($deal_ids)) return;
        
        $ids_placeholder = implode(',', array_fill(0, count($deal_ids), '%d'));
        
        switch ($action) {
            case 'delete':
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}akpp_deals WHERE id IN ({$ids_placeholder})",
                    ...$deal_ids
                ));
                echo '<div class="notice notice-success is-dismissible"><p>✅ Удалено сделок: ' . count($deal_ids) . '</p></div>';
                break;
        }
    }
    
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="deal[]" value="%s" />', esc_attr($item['id']));
    }
    
    protected function column_id($item) {
        return sprintf('#%d', $item['id']);
    }
    
    protected function column_client_name($item) {
        $name = $item['client_name'] ?? '—';
        $phone = $item['client_phone'] ?? '';
        
        $output = '<div style="font-weight: 600;">' . esc_html($name) . '</div>';
        if ($phone) {
            $output .= '<div style="font-size: 13px; color: #718096;">' . esc_html($phone) . '</div>';
        }
        
        return $output;
    }
    
    protected function column_vehicle($item) {
        $make = $item['make'] ?? '';
        $model = $item['model'] ?? '';
        $year = $item['year'] ?? '';
        $vin = $item['vin'] ?? '';
        
        $car_info = trim("{$make} {$model} {$year}");
        if (empty($car_info)) {
            $car_info = $vin ?: '—';
        }
        
        return esc_html($car_info);
    }
    
    protected function column_status($item) {
        $statuses = [
            'lead' => ['label' => '🔵 Лид', 'color' => '#63b3ed'],
            'new' => ['label' => '🟢 Новая', 'color' => '#00ff88'],
            'diagnostic' => ['label' => '🟡 Диагностика', 'color' => '#f6ad55'],
            'in_work' => ['label' => '🟠 В работе', 'color' => '#f6ad55'],
            'completed' => ['label' => '✅ Завершена', 'color' => '#00ff88'],
            'cancelled' => ['label' => '❌ Отменена', 'color' => '#fc8181'],
        ];
        
        $status = $item['status'] ?? 'new';
        $info = $statuses[$status] ?? ['label' => $status, 'color' => '#a0aec0'];
        
        return sprintf(
            '<span style="display:inline-block;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;background:%s22;color:%s;">%s</span>',
            esc_attr($info['color']),
            esc_attr($info['color']),
            esc_html($info['label'])
        );
    }
    
    protected function column_total_amount($item) {
        $amount = floatval($item['total_amount'] ?? 0);
        return '<span style="color: #00ff88; font-weight: 600;">' . number_format($amount, 0, '.', ' ') . ' ₽</span>';
    }
    
    protected function column_created_at($item) {
        $date = $item['created_at'] ?? '';
        return $date ? date_i18n('d.m.Y H:i', strtotime($date)) : '—';
    }
    
    protected function column_default($item, $column_name) {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '—';
    }
    
    protected function extra_tablenav($which) {
        if ($which !== 'top') return;
        
        global $wpdb;
        
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $employee_filter = isset($_GET['employee_filter']) ? intval($_GET['employee_filter']) : 0;
        
        $statuses = [
            'all' => 'Все статусы',
            'lead' => '🔵 Лид',
            'new' => '🟢 Новая',
            'diagnostic' => '🟡 Диагностика',
            'in_work' => '🟠 В работе',
            'completed' => '✅ Завершена',
            'cancelled' => '❌ Отменена',
        ];
        
        $employees = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}akpp_employees WHERE is_active = 1 ORDER BY name ASC", ARRAY_A);
        ?>
        <div class="alignleft actions">
            <select name="status_filter">
                <?php foreach ($statuses as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($status_filter, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="employee_filter">
                <option value="0" <?php selected($employee_filter, 0); ?>>Все сотрудники</option>
                <?php foreach ($employees as $emp) : ?>
                    <option value="<?php echo esc_attr($emp['id']); ?>" <?php selected($employee_filter, $emp['id']); ?>>
                        <?php echo esc_html($emp['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="submit" name="filter_action" class="button" value="Фильтровать">
        </div>
        <?php
    }
    
    public function no_items() {
        echo 'Нет сделок для отображения';
    }

        /**
     * Отображение колонки действий
     */
    protected function column_actions($item) {
        $actions = [];
        
        // Кнопка редактирования
        $actions[] = sprintf(
            '<a href="?page=akpp-crm-new-deal&action=edit&id=%d" class="button button-small" title="Редактировать">✏️</a>',
            $item['id']
        );
        
        // Кнопка удаления
        $delete_url = wp_nonce_url(
            add_query_arg([
                'page'   => 'akpp-crm-deals',
                'action' => 'delete',
                'id'     => $item['id']
            ]),
            'delete_deal_' . $item['id']
        );
        
        $actions[] = sprintf(
            '<a href="%s" class="button button-small" title="Удалить" onclick="return confirm(\'Удалить сделку?\')">🗑️</a>',
            esc_url($delete_url)
        );
        
        return implode(' ', $actions);
    }
}