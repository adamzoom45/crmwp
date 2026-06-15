<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class AKPP_Parser_Table extends WP_List_Table {
    
    private $statuses = [
        'pending'  => 'Ожидает проверки',
        'approved' => 'Одобрено',
        'rejected' => 'Отклонено'
    ];

    public function __construct() {
        parent::__construct([
            'singular' => __('Запись парсера', 'akpp-crm'),
            'plural'   => __('Парсер', 'akpp-crm'),
            'ajax'     => false
        ]);
    }

    /**
     * Получение данных из базы данных с учетом фильтров
     */
    public function get_parser_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_parser_items';

        $per_page = $this->get_items_per_page('parser_per_page', 15);
        $current_page = $this->get_pagenum();
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $status_filter = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'created_at';
        $order = isset($_REQUEST['order']) && $_REQUEST['order'] === 'asc' ? 'ASC' : 'DESC';

        // Построение условий WHERE
        $where_clauses = ["1=1"];
        $prepare_args = [];

        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = "(source_url LIKE %s OR title LIKE %s OR ai_analysis LIKE %s)";
            $prepare_args[] = $search_like;
            $prepare_args[] = $search_like;
            $prepare_args[] = $search_like;
        }

        if (!empty($status_filter) && array_key_exists($status_filter, $this->statuses)) {
            $where_clauses[] = "status = %s";
            $prepare_args[] = $status_filter;
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Общее количество записей
        $count_query = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";
        $total_items = empty($prepare_args) ? $wpdb->get_var($count_query) : $wpdb->get_var($wpdb->prepare($count_query, $prepare_args));

        // Данные для текущей страницы
        $offset = ($current_page - 1) * $per_page;
        $prepare_args[] = $per_page;
        $prepare_args[] = $offset;

        $query = "SELECT * FROM $table_name WHERE $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $data = $wpdb->get_results($wpdb->prepare($query, $prepare_args), ARRAY_A);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        return $data;
    }

    /**
     * Подготовка элементов
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];
        $this->process_bulk_action();
        $this->items = $this->get_parser_items();
    }

    /**
     * Определение колонок
     */
    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'id'          => __('ID', 'akpp-crm'),
            'source'      => __('Источник (URL)', 'akpp-crm'),
            'title'       => __('Заголовок', 'akpp-crm'),
            'ai_analysis' => __('AI Анализ', 'akpp-crm'),
            'status'      => __('Статус', 'akpp-crm'),
            'date'        => __('Дата', 'akpp-crm'),
            'actions'     => __('Действия', 'akpp-crm')
        ];
    }

    /**
     * Сортируемые колонки
     */
    public function get_sortable_columns() {
        return [
            'id'   => ['id', true],
            'date' => ['created_at', false]
        ];
    }

    /**
     * Дополнительная навигация (Фильтр по статусу)
     */
    public function extra_tablenav($which) {
        if ($which === 'top') {
            $current_status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
            ?>
            <div class="alignleft actions">
                <select name="status">
                    <option value=""><?php _e('Все статусы', 'akpp-crm'); ?></option>
                    <?php foreach ($this->statuses as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($current_status, $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('Фильтр', 'akpp-crm'), '', 'filter_action', false); ?>
            </div>
            <?php
        }
    }

    /**
     * Колонка с чекбоксом
     */
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="parser_item[]" value="%s" />', $item['id']);
    }

    /**
     * Колонка ID
     */
    public function column_id($item) {
        return '#' . esc_html($item['id']);
    }

    /**
     * Колонка Источник (URL)
     */
    public function column_source($item) {
        $url = esc_url($item['source_url']);
        $display_url = strlen($url) > 40 ? substr($url, 0, 37) . '...' : $url;
        return '<a href="' . $url . '" target="_blank" title="' . esc_attr($url) . '">' . esc_html($display_url) . ' <span class="dashicons dashicons-external"></span></a>';
    }

    /**
     * Колонка Заголовок
     */
    public function column_title($item) {
        return '<strong>' . esc_html(wp_trim_words($item['title'], 8, '...')) . '</strong>';
    }

    /**
     * Колонка AI Анализ (краткая выжимка)
     */
    public function column_ai_analysis($item) {
        if (empty($item['ai_analysis'])) {
            return '<span class="akpp-text-muted">Не проводился</span>';
        }
        
        // Пытаемся распарсить JSON, если AI вернул его
        $analysis = json_decode($item['ai_analysis'], true);
        if (json_last_error() === JSON_ERROR_NONE && isset($analysis['problem_type'])) {
            $problem = esc_html($analysis['problem_type']);
            $severity = isset($analysis['severity']) ? intval($analysis['severity']) : 0;
            $color = $severity >= 7 ? '#ff4444' : ($severity >= 4 ? '#ffaa00' : '#00ff88');
            return '<div style="font-size:12px;">' . 
                   '<strong style="color:' . $color . ';">[' . $severity . '/10]</strong> ' . 
                   esc_html(wp_trim_words($problem, 10, '...')) . 
                   '</div>';
        }
        
        return '<div style="font-size:12px;">' . esc_html(wp_trim_words(strip_tags($item['ai_analysis']), 15, '...')) . '</div>';
    }

    /**
     * Колонка Статус
     */
    public function column_status($item) {
        $badges = [
            'pending'  => '<span class="akpp-badge akpp-badge-warning">Ожидает</span>',
            'approved' => '<span class="akpp-badge akpp-badge-success">Одобрено</span>',
            'rejected' => '<span class="akpp-badge akpp-badge-danger">Отклонено</span>'
        ];
        return isset($badges[$item['status']]) ? $badges[$item['status']] : esc_html($item['status']);
    }

    /**
     * Колонка Дата
     */
    public function column_date($item) {
        return date('d.m.Y H:i', strtotime($item['created_at']));
    }

    /**
     * Колонка Действия
     */
    public function column_actions($item) {
        $actions = [];

        if ($item['status'] !== 'approved') {
            $approve_url = wp_nonce_url(
                add_query_arg(['page' => 'akpp-parser', 'action' => 'approve', 'id' => $item['id']], admin_url('admin.php')), 
                'akpp_approve_parser_' . $item['id']
            );
            $actions['approve'] = sprintf('<a href="%s" style="color:#00aa55;">%s</a>', esc_url($approve_url), __('Одобрить', 'akpp-crm'));
        }

        if ($item['status'] !== 'rejected') {
            $reject_url = wp_nonce_url(
                add_query_arg(['page' => 'akpp-parser', 'action' => 'reject', 'id' => $item['id']], admin_url('admin.php')), 
                'akpp_reject_parser_' . $item['id']
            );
            $actions['reject'] = sprintf('<a href="%s" style="color:#cc0000;">%s</a>', esc_url($reject_url), __('Отклонить', 'akpp-crm'));
        }

        $delete_url = wp_nonce_url(
            add_query_arg(['page' => 'akpp-parser', 'action' => 'delete', 'id' => $item['id']], admin_url('admin.php')), 
            'akpp_delete_parser_' . $item['id']
        );
        $actions['delete'] = sprintf('<a href="%s" style="color:#a00;" onclick="return confirm(\'Удалить запись?\');">%s</a>', esc_url($delete_url), __('Удалить', 'akpp-crm'));

        return $this->row_actions($actions);
    }

    /**
     * Значение по умолчанию
     */
    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '—';
    }

    /**
     * Массовые действия
     */
    public function get_bulk_actions() {
        return [
            'approve' => __('Одобрить', 'akpp-crm'),
            'reject'  => __('Отклонить', 'akpp-crm'),
            'delete'  => __('Удалить', 'akpp-crm')
        ];
    }

    /**
     * Обработка массовых и одиночных действий
     */
    public function process_bulk_action() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_parser_items';

        $action = $this->current_action();
        
        // Одиночное действие
        if (in_array($action, ['approve', 'reject', 'delete']) && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            
            if ($action === 'delete') {
                check_admin_referer('akpp_delete_parser_' . $id);
                $wpdb->delete($table_name, ['id' => $id]);
            } else {
                $nonce_action = $action === 'approve' ? 'akpp_approve_parser_' . $id : 'akpp_reject_parser_' . $id;
                check_admin_referer($nonce_action);
                $wpdb->update($table_name, ['status' => $action], ['id' => $id]);
            }
            
            wp_redirect(add_query_arg(['page' => 'akpp-parser', 'updated' => '1'], admin_url('admin.php')));
            exit;
        }

        // Массовое действие
        if (in_array($action, ['approve', 'reject', 'delete']) && isset($_POST['parser_item']) && is_array($_POST['parser_item'])) {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $ids = array_map('intval', $_POST['parser_item']);
            $ids_str = implode(',', $ids);

            if ($action === 'delete') {
                $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids_str)");
            } else {
                $wpdb->query($wpdb->prepare("UPDATE $table_name SET status = %s WHERE id IN ($ids_str)", $action));
            }
            
            wp_redirect(add_query_arg(['page' => 'akpp-parser', 'updated' => count($ids)], admin_url('admin.php')));
            exit;
        }
    }
}
