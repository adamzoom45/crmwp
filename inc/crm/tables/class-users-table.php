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
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Построение WHERE
        $where = [];
        $params = [];
        
        if ($status_filter !== 'all') {
            $where[] = "status = %s";
            $params[] = $status_filter;
        }
        
        if (!empty($search)) {
            $where[] = "(full_name LIKE '%%%s%%' OR email LIKE '%%%s%%' OR phone LIKE '%%%s%%')";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Сортировка
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'registered_at';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Получение данных
        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        $this->items = $wpdb->get_results($wpdb->prepare($query, ...$params));
        
        // Общее количество
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        $total_items = $wpdb->get_var($wpdb->prepare($count_query, ...$params));
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page
        ]);
        
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
    }
    
    /**
     * Колонки таблицы
     */
    public function get_columns() {
        return [
            'id' => 'ID',
            'full_name' => 'Имя',
            'email' => 'Email',
            'phone' => 'Телефон',
            'status' => 'Статус',
            'registered_at' => 'Дата регистрации'
        ];
    }
    
    /**
     * Сортируемые колонки
     */
    protected function get_sortable_columns() {
        return [
            'id' => ['id', true],
            'full_name' => ['full_name', false],
            'email' => ['email', false],
            'registered_at' => ['registered_at', true]
        ];
    }
    
    /**
     * Вывод колонки
     */
    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $item->id;
            case 'full_name':
                return esc_html($item->full_name);
            case 'email':
                return esc_html($item->email);
            case 'phone':
                return esc_html($item->phone);
            case 'status':
                $status_class = $item->status === 'active' ? 'active' : 'inactive';
                return '<span class="status-badge ' . $status_class . '">' . esc_html($item->status) . '</span>';
            case 'registered_at':
                return $item->registered_at ? date_i18n('d.m.Y H:i', strtotime($item->registered_at)) : '-';
            default:
                return '';
        }
    }
    
    /**
     * Фильтры
     */
    protected function extra_tablenav($which) {
        if ($which === 'top') {
            $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
            ?>
            <div class="alignleft actions">
                <select name="status_filter">
                    <option value="all" <?php selected($status_filter, 'all'); ?>>Все статусы</option>
                    <option value="active" <?php selected($status_filter, 'active'); ?>>Активные</option>
                    <option value="inactive" <?php selected($status_filter, 'inactive'); ?>>Неактивные</option>
                </select>
                <input type="submit" class="button" value="Фильтр">
            </div>
            <?php
        }
    }
    
    /**
     * Сообщение при отсутствии элементов
     */
    public function no_items() {
        echo 'Клиенты не найдены.';
    }
}
