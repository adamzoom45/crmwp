<?php
/**
 * Класс для таблицы сотрудников в админке
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AKPP_Employees_Table extends WP_List_Table {
    
    public function __construct() {
        parent::__construct([
            'singular' => 'employee',
            'plural'   => 'employees',
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
        $role_filter = isset($_GET['role_filter']) ? sanitize_text_field($_GET['role_filter']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Построение WHERE
        $where = [];
        $params = [];
        
        if ($status_filter !== 'all') {
            $is_active = ($status_filter === 'active') ? 1 : 0;
            $where[] = "is_active = %d";
            $params[] = $is_active;
        }
        
        if ($role_filter !== 'all') {
            $where[] = "role = %s";
            $params[] = $role_filter;
        }
        
        if (!empty($search)) {
            $where[] = "(name LIKE %s OR phone LIKE %s OR email LIKE %s OR role LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Сортировка
        $allowed_orderby = ['id', 'name', 'role', 'is_active'];
        $orderby = isset($_GET['orderby']) && in_array($_GET['orderby'], $allowed_orderby) 
            ? sanitize_text_field($_GET['orderby']) 
            : 'name';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'ASC';
        
        // Общее количество
        $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}akpp_employees {$where_clause}";
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, ...$params);
        }
        $total_items = $wpdb->get_var($count_query);
        
        // Получение данных
        $query = "SELECT * FROM {$wpdb->prefix}akpp_employees {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
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
            'name'      => 'ФИО',
            'role'      => 'Должность',
            'phone'     => 'Телефон',
            'email'     => 'Email',
            'is_active' => 'Статус',
            'actions'   => 'Действия',
        ];
    }
    
    public function get_sortable_columns() {
        return [
            'id'        => ['id', false],
            'name'      => ['name', true],
            'role'      => ['role', false],
            'is_active' => ['is_active', false],
        ];
    }
    
    public function get_bulk_actions() {
        return [
            'activate'   => '✅ Активировать',
            'deactivate' => '❌ Деактивировать',
            'delete'     => '🗑️ Удалить',
        ];
    }
    
    public function process_bulk_action() {
        global $wpdb;
        $action = $this->current_action();
        if (!$action) return;
        
        check_admin_referer('bulk-' . $this->_args['plural']);
        
        $ids = isset($_REQUEST['employee']) ? array_map('intval', (array)$_REQUEST['employee']) : [];
        if (empty($ids)) return;
        
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        
        switch ($action) {
            case 'activate':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}akpp_employees SET is_active = 1 WHERE id IN ({$ids_placeholder})",
                    ...$ids
                ));
                echo '<div class="notice notice-success is-dismissible"><p>✅ Активировано: ' . count($ids) . '</p></div>';
                break;
            case 'deactivate':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}akpp_employees SET is_active = 0 WHERE id IN ({$ids_placeholder})",
                    ...$ids
                ));
                echo '<div class="notice notice-success is-dismissible"><p>❌ Деактивировано: ' . count($ids) . '</p></div>';
                break;
            case 'delete':
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}akpp_employees WHERE id IN ({$ids_placeholder})",
                    ...$ids
                ));
                echo '<div class="notice notice-success is-dismissible"><p>🗑️ Удалено: ' . count($ids) . '</p></div>';
                break;
        }
    }
    
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="employee[]" value="%s" />', esc_attr($item['id']));
    }
    
    protected function column_id($item) {
        return '#' . intval($item['id']);
    }
    
    protected function column_name($item) {
        $name = $item['name'] ?? '—';
        $email = $item['email'] ?? '';
        $output = '<div style="font-weight: 600;">' . esc_html($name) . '</div>';
        if ($email) {
            $output .= '<div style="font-size: 13px; color: #718096;">' . esc_html($email) . '</div>';
        }
        return $output;
    }
    
    protected function column_role($item) {
        $roles = [
            'mechanic' => ['label' => '🔧 Механик', 'color' => '#00ff88'],
            'manager'  => ['label' => '💼 Менеджер', 'color' => '#63b3ed'],
            'admin'    => ['label' => '⚙️ Админ', 'color' => '#f6ad55'],
            'director' => ['label' => '👑 Директор', 'color' => '#fc8181'],
        ];
        $role = $item['role'] ?? 'mechanic';
        $info = $roles[$role] ?? ['label' => ucfirst($role), 'color' => '#a0aec0'];
        return sprintf(
            '<span style="display:inline-block;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;background:%s22;color:%s;">%s</span>',
            esc_attr($info['color']),
            esc_attr($info['color']),
            esc_html($info['label'])
        );
    }
    
    protected function column_phone($item) {
        $phone = $item['phone'] ?? '';
        if (empty($phone)) return '—';
        return '<a href="tel:' . esc_attr($phone) . '" style="color:#00ff88;">' . esc_html($phone) . '</a>';
    }
    
    protected function column_email($item) {
        $email = $item['email'] ?? '';
        if (empty($email)) return '—';
        return '<a href="mailto:' . esc_attr($email) . '" style="color:#63b3ed;">' . esc_html($email) . '</a>';
    }
    
    protected function column_is_active($item) {
        $is_active = intval($item['is_active'] ?? 0);
        if ($is_active) {
            return '<span style="display:inline-block;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;background:#00ff8822;color:#00ff88;">✅ Активен</span>';
        }
        return '<span style="display:inline-block;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;background:#fc818122;color:#fc8181;">❌ Неактивен</span>';
    }
    
    protected function column_actions($item) {
        $actions = [];
        
        // Редактирование
        $actions[] = sprintf(
            '<a href="?page=akpp-crm-employees&action=edit&id=%d" class="button button-small" title="Редактировать">✏️</a>',
            $item['id']
        );
        
        // Активировать/деактивировать
        $is_active = intval($item['is_active'] ?? 0);
        if ($is_active) {
            $toggle_url = wp_nonce_url(
                add_query_arg(['page' => 'akpp-crm-employees', 'action' => 'deactivate', 'id' => $item['id']]),
                'toggle_employee_' . $item['id']
            );
            $actions[] = sprintf(
                '<a href="%s" class="button button-small" title="Деактивировать">❌</a>',
                esc_url($toggle_url)
            );
        } else {
            $toggle_url = wp_nonce_url(
                add_query_arg(['page' => 'akpp-crm-employees', 'action' => 'activate', 'id' => $item['id']]),
                'toggle_employee_' . $item['id']
            );
            $actions[] = sprintf(
                '<a href="%s" class="button button-small" title="Активировать">✅</a>',
                esc_url($toggle_url)
            );
        }
        
        // Удаление
        $delete_url = wp_nonce_url(
            add_query_arg(['page' => 'akpp-crm-employees', 'action' => 'delete', 'id' => $item['id']]),
            'delete_employee_' . $item['id']
        );
        $actions[] = sprintf(
            '<a href="%s" class="button button-small" title="Удалить" onclick="return confirm(\'Удалить сотрудника?\')">🗑️</a>',
            esc_url($delete_url)
        );
        
        return implode(' ', $actions);
    }
    
    protected function column_default($item, $column_name) {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '—';
    }
    
    protected function extra_tablenav($which) {
        if ($which !== 'top') return;
        
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $role_filter = isset($_GET['role_filter']) ? sanitize_text_field($_GET['role_filter']) : 'all';
        
        $statuses = [
            'all'      => 'Все статусы',
            'active'   => '✅ Активные',
            'inactive' => '❌ Неактивные',
        ];
        
        $roles = [
            'all'      => 'Все должности',
            'mechanic' => '🔧 Механик',
            'manager'  => '💼 Менеджер',
            'admin'    => '⚙️ Админ',
            'director' => '👑 Директор',
        ];
        ?>
        <div class="alignleft actions">
            <select name="status_filter">
                <?php foreach ($statuses as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($status_filter, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="role_filter">
                <?php foreach ($roles as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($role_filter, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="submit" name="filter_action" class="button" value="Фильтровать">
        </div>
        <?php
    }
    
    public function no_items() {
        echo 'Сотрудников не найдено';
    }
}