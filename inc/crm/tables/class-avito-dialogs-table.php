<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('WP_List_Table')) require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

class AKPP_Avito_Dialogs_Table extends WP_List_Table {
    private $table_name;
    private $messages_table;
    
    public function __construct() {
        parent::__construct(['singular' => 'dialog', 'plural' => 'dialogs', 'ajax' => false]);
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'akpp_avito_dialogs';
        $this->messages_table = $wpdb->prefix . 'akpp_avito_messages_cache';
    }
    
    public function prepare_items() {
        global $wpdb;
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $where = [];
        $params = [];
        
        if ($status_filter !== 'all') {
            $where[] = "status = %s";
            $params[] = $status_filter;
        }
        
        if (!empty($search)) {
            $where[] = "(client_name LIKE '%%%s%%' OR client_phone LIKE '%%%s%%' OR avito_dialog_id LIKE '%%%s%%')";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'last_message_date';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        $this->items = $wpdb->get_results($wpdb->prepare($query, ...$params));
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        $total_items = $wpdb->get_var($wpdb->prepare($count_query, ...$params));
        
        $this->set_pagination_args(['total_items' => $total_items, 'per_page' => $per_page]);
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
    }
    
    public function get_columns() {
        return ['id' => 'ID', 'client_name' => 'Клиент', 'client_phone' => 'Телефон', 'status' => 'Статус', 'unread_count' => 'Непрочитанные', 'last_message_text' => 'Сообщение', 'last_message_date' => 'Время'];
    }
    
    protected function get_sortable_columns() {
        return ['id' => ['id', true], 'client_name' => ['client_name', false], 'last_message_date' => ['last_message_date', true], 'unread_count' => ['unread_count', true]];
    }
    
    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id': return $item->id;
            case 'client_name': return esc_html($item->client_name ?: 'Без имени');
            case 'client_phone': return esc_html($item->client_phone ?: '-');
            case 'status': return '<span class="status-badge ' . ($item->status === 'active' ? 'active' : 'inactive') . '">' . esc_html($item->status) . '</span>';
            case 'unread_count': $c = intval($item->unread_count); return $c > 0 ? '<span class="badge badge-warning">' . $c . '</span>' : '0';
            case 'last_message_text': return '<span class="message-preview">' . esc_html(mb_substr($item->last_message_text ?: '-', 0, 50)) . '</span>';
            case 'last_message_date': return $item->last_message_date ? date_i18n('d.m.Y H:i', strtotime($item->last_message_date)) : '-';
            default: return '';
        }
    }
    
    protected function extra_tablenav($which) {
        if ($which === 'top') {
            $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
            echo '<div class="alignleft actions"><select name="status_filter">';
            echo '<option value="all"' . ($status_filter === 'all' ? ' selected' : '') . '>Все статусы</option>';
            echo '<option value="active"' . ($status_filter === 'active' ? ' selected' : '') . '>Активные</option>';
            echo '<option value="closed"' . ($status_filter === 'closed' ? ' selected' : '') . '>Закрытые</option>';
            echo '</select> <input type="submit" class="button" value="Фильтр"></div>';
        }
    }
    
    public function no_items() { echo 'Диалоги не найдены.'; }
}
