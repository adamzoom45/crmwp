<?php
/**
 * Таблица парсера
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AKPP_Parser_Table extends WP_List_Table {
    private $table_name;
    
    public function __construct() {
        parent::__construct([
            'singular' => 'parser_item',
            'plural' => 'parser_items',
            'ajax' => false
        ]);
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'akpp_parser_items';
    }
    
    public function prepare_items() {
        global $wpdb;
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $type_filter = isset($_GET['type_filter']) ? sanitize_text_field($_GET['type_filter']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $where = [];
        $params = [];
        
        if ($status_filter !== 'all') {
            $where[] = "status = %s";
            $params[] = $status_filter;
        }
        if ($type_filter !== 'all') {
            $where[] = "content_type = %s";
            $params[] = $type_filter;
        }
        if (!empty($search)) {
            $where[] = "(title LIKE %s OR url LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        if (!empty($params)) $count_query = $wpdb->prepare($count_query, ...$params);
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
            'title' => 'Заголовок',
            'content_type' => 'Тип',
            'status' => 'Статус',
            'created_at' => 'Дата',
            'actions' => 'Действия'
        ];
    }
    
    public function get_sortable_columns() {
        return [
            'id' => ['id', false],
            'title' => ['title', false],
            'created_at' => ['created_at', true]
        ];
    }
    
    public function get_bulk_actions() {
        return ['delete' => '️ Удалить'];
    }
    
    public function process_bulk_action() {
        global $wpdb;
        if (!$this->current_action()) return;
        
        $item_ids = isset($_GET['parser_item']) ? array_map('intval', (array)$_GET['parser_item']) : [];
        if (empty($item_ids)) return;
        
        if ($this->current_action() === 'delete') {
            $ids_placeholder = implode(',', array_fill(0, count($item_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
                $item_ids
            ));
            echo '<div class="notice notice-success"><p>Удалено: ' . count($item_ids) . '</p></div>';
        }
    }
    
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="parser_item[]" value="%s" />', $item->id);
    }
    
    protected function column_id($item) {
        return intval($item->id);
    }
    
    protected function column_title($item) {
        $title = !empty($item->title) ? esc_html($item->title) : 'Без заголовка';
        $url = esc_url($item->url);
        return sprintf(
            '<a href="%s" target="_blank"><strong>%s</strong></a><br><small>%s</small>',
            $url, $title, substr($url, 0, 60)
        );
    }
    
    protected function column_content_type($item) {
        $types = [
            'transmission_related' => ' АКПП',
            'parts_store' => '🔩 Запчасти',
            'general' => '📄 Общее'
        ];
        $type = $item->content_type ?? 'general';
        return '<span class="content-type-badge">' . ($types[$type] ?? $type) . '</span>';
    }
    
    protected function column_status($item) {
        $statuses = [
            'pending' => '⏳ Ожидает',
            'parsed' => ' Распаршено',
            'ai_processed' => '🤖 AI обработан',
            'approved' => '✅ Одобрено',
            'rejected' => '❌ Отклонено'
        ];
        $status = $item->status ?? 'pending';
        return '<span class="status-badge">' . ($statuses[$status] ?? $status) . '</span>';
    }
    
    protected function column_created_at($item) {
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at));
    }
    
    protected function column_actions($item) {
        $actions = '';
        if ($item->status === 'pending') {
            $actions .= sprintf(
                '<button class="button button-small btn-ai-analyze" data-id="%d">🤖 AI</button> ',
                $item->id
            );
        }
        $actions .= sprintf(
            '<button class="button button-small btn-view-item" data-id="%d">👁️</button> ',
            $item->id
        );
        $actions .= sprintf(
            '<button class="button button-small btn-delete-parser" data-id="%d">🗑️</button>',
            $item->id
        );
        return $actions;
    }
    
    protected function extra_tablenav($which) {
        if ($which !== 'top') return;
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $type_filter = isset($_GET['type_filter']) ? sanitize_text_field($_GET['type_filter']) : 'all';
        ?>
        <div class="alignleft actions">
            <select name="status_filter">
                <option value="all" <?php selected($status_filter, 'all'); ?>>Все статусы</option>
                <option value="pending" <?php selected($status_filter, 'pending'); ?>>Ожидает</option>
                <option value="ai_processed" <?php selected($status_filter, 'ai_processed'); ?>>AI обработан</option>
                <option value="approved" <?php selected($status_filter, 'approved'); ?>>Одобрено</option>
            </select>
            <select name="type_filter">
                <option value="all" <?php selected($type_filter, 'all'); ?>>Все типы</option>
                <option value="transmission_related" <?php selected($type_filter, 'transmission_related'); ?>>АКПП</option>
                <option value="parts_store" <?php selected($type_filter, 'parts_store'); ?>>Запчасти</option>
                <option value="general" <?php selected($type_filter, 'general'); ?>>Общее</option>
            </select>
            <input type="submit" name="filter_action" class="button" value="Фильтровать">
        </div>
        <?php
    }
    
    public function no_items() {
        echo 'Нет элементов для отображения';
    }
}