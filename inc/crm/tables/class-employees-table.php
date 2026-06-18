<?php
/**
 * Класс для таблицы сотрудников в админке
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AKPP_Employees_Table extends WP_List_Table {
    
    private $table_name;
    
    public function __construct() {
        parent::__construct([
            'singular' => 'employee',
            'plural' => 'employees',
            'ajax' => false
        ]);
        
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'akpp_employees';
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
            $is_active = $status_filter === 'active' ? 1 : 0;
            $where[] = "is_active = %d";
            $params[] = $is_active;
        }
        
        if (!empty($search)) {
            $where[] = "(name LIKE '%%%s%%' OR email LIKE '%%%s%%' OR phone LIKE '%%%s%%')";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Сортировка
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'name';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Получение общего количества
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, ...$params);
        }
        $total_items = $wpdb->get_var($count_query);
        
        // Получение данных
        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        $query = $wpdb->prepare($query, ...$params);
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
            'role' => 'Должность',
            'percent' => '%',
            'telegram' => 'Telegram',
            'status' => 'Статус',
            'created_at' => 'Дата',
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
            'role' => ['role', false],
            'percent' => ['percent', false],
            'created_at' => ['created_at', true]
        ];
    }
    
    /**
     * Массовые действия
     */
    public function get_bulk_actions() {
        return [
            'activate' => 'Активировать',
            'deactivate' => 'Деактивировать',
            'delete' => 'Удалить'
        ];
    }
    
    /**
     * Обработка массовых действий
     */
    public function process_bulk_action() {
        global $wpdb;
        
        if (!$this->current_action()) return;
        
        $employee_ids = isset($_GET['employee']) ? array_map('intval', $_GET['employee']) : [];
        
        if (empty($employee_ids)) return;
        
        $ids_placeholder = implode(',', array_fill(0, count($employee_ids), '%d'));
        
        switch ($this->current_action()) {
            case 'activate':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->table_name} SET is_active = 1 WHERE id IN ({$ids_placeholder})",
                    $employee_ids
                ));
                echo '<div class="notice notice-success"><p>Сотрудники активированы</p></div>';
                break;
                
            case 'deactivate':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->table_name} SET is_active = 0 WHERE id IN ({$ids_placeholder})",
                    $employee_ids
                ));
                echo '<div class="notice notice-success"><p>Сотрудники деактивированы</p></div>';
                break;
                
            case 'delete':
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
                    $employee_ids
                ));
                echo '<div class="notice notice-success"><p>Сотрудники удалены</p></div>';
                break;
        }
    }
    
    /**
     * Отображение колонки cb (чекбокс)
     */
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="employee[]" value="%s" />', $item->id);
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
     * Отображение колонки должности
     */
    protected function column_role($item) {
        $roles = [
            'admin' => '👑 Администратор',
            'guide' => '🎯 Гид',
            'master' => '🔧 Мастер',
            'senior_master' => '⭐ Старший мастер',
            'lead_master' => '🏆 Ведущий мастер',
            'foreman' => '📋 Бригадир',
            'assistant' => '👨‍🔧 Помощник'
        ];
        
        $role_label = $roles[$item->role] ?? $item->role;
        
        // Бейдж для гида
        if ($item->role === 'guide') {
            $role_label = '<span class="role-badge guide">' . $role_label . '</span>';
        }
        
        return $role_label;
    }
    
    /**
     * Отображение колонки процента
     */
    protected function column_percent($item) {
        return '<strong>' . $item->percent . '%</strong>';
    }
    
    /**
     * Отображение колонки Telegram
     */
    protected function column_telegram($item) {
        if ($item->telegram_username) {
            return sprintf(
                '<a href="https://t.me/%s" target="_blank">@%s</a>',
                esc_attr($item->telegram_username),
                esc_html($item->telegram_username)
            );
        } elseif ($item->telegram_id) {
            return '📱 ID: ' . esc_html($item->telegram_id);
        }
        
        return '—';
    }
    
    /**
     * Отображение колонки статуса
     */
    protected function column_status($item) {
        if ($item->is_active) {
            return '<span class="status-badge status-active">🟢 Активен</span>';
        } else {
            return '<span class="status-badge status-inactive">🔴 Неактивен</span>';
        }
    }
    
    /**
     * Отображение колонки даты
     */
    protected function column_created_at($item) {
        return date_i18n(get_option('date_format'), strtotime($item->created_at));
    }
    
    /**
     * Отображение колонки действий
     */
    protected function column_actions($item) {
        $actions = sprintf(
            '<a href="?page=akpp-crm-employees&action=edit&id=%d" class="button button-small">✏️ Ред.</a> ',
            $item->id
        );
        
        if ($item->is_active) {
            $actions .= sprintf(
                '<button class="button button-small deactivate-employee" data-id="%d">🔴 Деакт.</button> ',
                $item->id
            );
        } else {
            $actions .= sprintf(
                '<button class="button button-small activate-employee" data-id="%d">🟢 Акт.</button> ',
                $item->id
            );
        }
        
        $actions .= sprintf(
            '<button class="button button-small delete-employee" data-id="%d">🗑️</button>',
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
                <option value="all" <?php selected($role_filter, 'all'); ?>>Все должности</option>
                <option value="admin" <?php selected($role_filter, 'admin'); ?>>👑 Администратор</option>
                <option value="guide" <?php selected($role_filter, 'guide'); ?>>🎯 Гид</option>
                <option value="master" <?php selected($role_filter, 'master'); ?>>🔧 Мастер</option>
                <option value="senior_master" <?php selected($role_filter, 'senior_master'); ?>>⭐ Старший мастер</option>
                <option value="lead_master" <?php selected($role_filter, 'lead_master'); ?>>🏆 Ведущий мастер</option>
                <option value="foreman" <?php selected($role_filter, 'foreman'); ?>>📋 Бригадир</option>
                <option value="assistant" <?php selected($role_filter, 'assistant'); ?>>👨‍🔧 Помощник</option>
            </select>
            
            <select name="status_filter">
                <option value="all" <?php selected($status_filter, 'all'); ?>>Все сотрудники</option>
                <option value="active" <?php selected($status_filter, 'active'); ?>>Активные</option>
                <option value="inactive" <?php selected($status_filter, 'inactive'); ?>>Неактивные</option>
            </select>
            
            <input type="submit" name="filter_action" class="button" value="Фильтровать">
            <a href="?page=akpp-crm-employees&action=add" class="button button-primary">➕ Добавить сотрудника</a>
        </div>
        <?php
    }
    
    /**
     * Отображение, если нет данных
     */
    public function no_items() {
        echo 'Нет сотрудников для отображения';
    }
}
