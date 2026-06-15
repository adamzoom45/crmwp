<?php
/**
 * Класс для таблицы пользователей сайта в админке
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AKPP_Users_Table extends WP_List_Table {
    
    private $table_name;
    
    public function __construct() {
        parent::__construct([
            'singular' => 'user',
            'plural' => 'users',
            'ajax' => false
        ]);
        
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'akpp_site_users';
    }
    
    /**
     * Получение данных для таблицы
     */
    public function prepare_items() {
        global $wpdb;
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Фильтры
        $role_filter = isset($_GET['role_filter']) ? sanitize_text_field($_GET['role_filter']) : 'all';
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Построение WHERE
        $where = [];
        $params = [];
        
        if ($role_filter !== 'all') {
            $where[] = "role = %s";
            $params[] = $role_filter;
        }
        
        if ($status_filter !== 'all') {
            $where[] = "status = %s";
            $params[] = $status_filter;
        }
        
        if (!empty($search)) {
            $where[] = "(name LIKE '%%%s%%' OR email LIKE '%%%s%%' OR phone LIKE '%%%s%%')";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Сортировка
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Получение общего количества
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, $params);
        }
        $total_items = $wpdb->get_var($count_query);
        
        // Получение данных
        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        $query = $wpdb->prepare($query, $params);
        $this->items = $wpdb->get_results($query);
        
        // Настройка пагинации
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
        
        $this->process_bulk_action();
    }
    
    /**
     * Определение колонок
     */
    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'id' => 'ID',
            'name' => 'ФИО',
            'contact' => 'Контакты',
            'car_brand' => 'Автомобиль',
            'role' => 'Роль',
            'status' => 'Статус',
            'last_login' => 'Последний вход',
            'created_at' => 'Дата регистрации',
            'actions' => 'Действия'
        ];
    }
    
    /**
     * Сортируемые колонки
     */
    public function get_sortable_columns() {
        return [
            'id' => ['id', false],
            'name' => ['name', true],
            'created_at' => ['created_at', true],
            'last_login' => ['last_login', false],
            'status' => ['status', false]
        ];
    }
    
    /**
     * Массовые действия
     */
    public function get_bulk_actions() {
        return [
            'activate' => 'Активировать',
            'deactivate' => 'Деактивировать',
            'delete' => 'Удалить',
            'export' => 'Экспорт в CSV'
        ];
    }
    
    /**
     * Обработка массовых действий
     */
    public function process_bulk_action() {
        global $wpdb;
        
        if (!$this->current_action()) return;
        
        $user_ids = isset($_GET['user']) ? array_map('intval', $_GET['user']) : [];
        
        if (empty($user_ids)) return;
        
        $ids_placeholder = implode(',', array_fill(0, count($user_ids), '%d'));
        
        switch ($this->current_action()) {
            case 'activate':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->table_name} SET status = 'active' WHERE id IN ({$ids_placeholder})",
                    $user_ids
                ));
                echo '<div class="notice notice-success"><p>Пользователи активированы</p></div>';
                break;
                
            case 'deactivate':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->table_name} SET status = 'inactive' WHERE id IN ({$ids_placeholder})",
                    $user_ids
                ));
                echo '<div class="notice notice-success"><p>Пользователи деактивированы</p></div>';
                break;
                
            case 'delete':
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
                    $user_ids
                ));
                echo '<div class="notice notice-success"><p>Пользователи удалены</p></div>';
                break;
                
            case 'export':
                $this->export_to_csv($user_ids);
                break;
        }
    }
    
    /**
     * Экспорт в CSV
     */
    private function export_to_csv($ids) {
        global $wpdb;
        
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
            $ids
        ));
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'ФИО', 'Email', 'Телефон', 'Автомобиль', 'Роль', 'Статус', 'Дата регистрации']);
        
        foreach ($users as $user) {
            fputcsv($output, [
                $user->id,
                $user->name,
                $user->email,
                $user->phone,
                $user->car_brand,
                $user->role,
                $user->status,
                $user->created_at
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Отображение колонки cb (чекбокс)
     */
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="user[]" value="%s" />', $item->id);
    }
    
    /**
     * Отображение колонки ID
     */
    protected function column_id($item) {
        return $item->id;
    }
    
    /**
     * Отображение колонки имени
     */
    protected function column_name($item) {
        return '<strong>' . esc_html($item->name) . '</strong>';
    }
    
    /**
     * Отображение колонки контактов
     */
    protected function column_contact($item) {
        $contact = '';
        
        if ($item->email) {
            $contact .= '<div>✉️ ' . esc_html($item->email) . '</div>';
        }
        if ($item->phone) {
            $contact .= '<div>📞 ' . esc_html($item->phone) . '</div>';
        }
        
        return !empty($contact) ? $contact : '—';
    }
    
    /**
     * Отображение колонки автомобиля
     */
    protected function column_car_brand($item) {
        return !empty($item->car_brand) ? esc_html($item->car_brand) : '—';
    }
    
    /**
     * Отображение колонки роли
     */
    protected function column_role($item) {
        $roles = [
            'client' => '👤 Клиент',
            'admin' => '👑 Администратор',
            'manager' => '📋 Менеджер'
        ];
        
        return $roles[$item->role] ?? $item->role;
    }
    
    /**
     * Отображение колонки статуса
     */
    protected function column_status($item) {
        if ($item->status === 'active') {
            return '<span class="status-badge status-active">🟢 Активен</span>';
        } else {
            return '<span class="status-badge status-inactive">🔴 Неактивен</span>';
        }
    }
    
    /**
     * Отображение колонки последнего входа
     */
    protected function column_last_login($item) {
        if ($item->last_login) {
            return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->last_login));
        }
        return '—';
    }
    
    /**
     * Отображение колонки даты регистрации
     */
    protected function column_created_at($item) {
        return date_i18n(get_option('date_format'), strtotime($item->created_at));
    }
    
    /**
     * Отображение колонки действий
     */
    protected function column_actions($item) {
        $actions = sprintf(
            '<a href="?page=akpp-crm-users&action=edit&id=%d" class="button button-small">✏️ Ред.</a> ',
            $item->id
        );
        
        if ($item->status === 'active') {
            $actions .= sprintf(
                '<button class="button button-small deactivate-user" data-id="%d">🔴 Деакт.</button> ',
                $item->id
            );
        } else {
            $actions .= sprintf(
                '<button class="button button-small activate-user" data-id="%d">🟢 Акт.</button> ',
                $item->id
            );
        }
        
        $actions .= sprintf(
            '<button class="button button-small delete-user" data-id="%d">🗑️</button>',
            $item->id
        );
        
        return $actions;
    }
    
    /**
     * Отображение по умолчанию для неизвестных колонок
     */
    protected function column_default($item, $column_name) {
        return isset($item->$column_name) ? esc_html($item->$column_name) : '—';
    }
    
    /**
     * Фильтры над таблицей
     */
    protected function extra_tablenav($which) {
        if ($which !== 'top') return;
        
        $role_filter = isset($_GET['role_filter']) ? sanitize_text_field($_GET['role_filter']) : 'all';
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        ?>
        <div class="alignleft actions">
            <select name="role_filter">
                <option value="all" <?php selected($role_filter, 'all'); ?>>Все роли</option>
                <option value="client" <?php selected($role_filter, 'client'); ?>>👤 Клиенты</option>
                <option value="admin" <?php selected($role_filter, 'admin'); ?>>👑 Администраторы</option>
                <option value="manager" <?php selected($role_filter, 'manager'); ?>>📋 Менеджеры</option>
            </select>
            
            <select name="status_filter">
                <option value="all" <?php selected($status_filter, 'all'); ?>>Все статусы</option>
                <option value="active" <?php selected($status_filter, 'active'); ?>>Активные</option>
                <option value="inactive" <?php selected($status_filter, 'inactive'); ?>>Неактивные</option>
            </select>
            
            <input type="submit" name="filter_action" class="button" value="Фильтровать">
        </div>
        <?php
    }
    
    /**
     * Отображение, если нет данных
     */
    public function no_items() {
        echo 'Нет пользователей для отображения';
    }
}
