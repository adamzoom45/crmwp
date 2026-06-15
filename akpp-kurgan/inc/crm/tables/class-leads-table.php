<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class AKPP_Leads_Table extends WP_List_Table {
    
    private $sources = [
        'site'     => '🌐 Сайт',
        'avito'    => '🟢 Авито',
        'telegram' => '🔵 Telegram',
        'call'     => '📞 Звонок'
    ];

    private $statuses = [
        'new'       => 'Новый',
        'contacted' => 'На связи',
        'converted' => 'Конвертирован в сделку',
        'rejected'  => 'Отклонен'
    ];

    public function __construct() {
        parent::__construct([
            'singular' => __('Лид', 'akpp-crm'),
            'plural'   => __('Лиды', 'akpp-crm'),
            'ajax'     => false
        ]);
    }

    /**
     * Получение данных о лидах из БД с учетом фильтров
     */
    public function get_leads() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_leads';
        $employees_table = $wpdb->prefix . 'akpp_employees';

        $per_page = $this->get_items_per_page('leads_per_page', 20);
        $current_page = $this->get_pagenum();
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $source_filter = isset($_REQUEST['source']) ? sanitize_text_field($_REQUEST['source']) : '';
        $status_filter = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'created_at';
        $order = isset($_REQUEST['order']) && $_REQUEST['order'] === 'asc' ? 'ASC' : 'DESC'; // По умолчанию новые сверху

        // Построение условий WHERE
        $where_clauses = ["1=1"];
        $prepare_args = [];

        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = "(name LIKE %s OR phone LIKE %s OR message LIKE %s)";
            $prepare_args[] = $search_like;
            $prepare_args[] = $search_like;
            $prepare_args[] = $search_like;
        }

        if (!empty($source_filter) && array_key_exists($source_filter, $this->sources)) {
            $where_clauses[] = "source = %s";
            $prepare_args[] = $source_filter;
        }

        if (!empty($status_filter) && array_key_exists($status_filter, $this->statuses)) {
            $where_clauses[] = "status = %s";
            $prepare_args[] = $status_filter;
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Общее количество записей
        $count_query = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";
        $total_items = empty($prepare_args) ? $wpdb->get_var($count_query) : $wpdb->get_var($wpdb->prepare($count_query, $prepare_args));

        // Данные для текущей страницы (с JOIN для получения имени сотрудника)
        $offset = ($current_page - 1) * $per_page;
        $prepare_args[] = $per_page;
        $prepare_args[] = $offset;

        $query = "
            SELECT l.*, e.full_name as assigned_name 
            FROM $table_name l 
            LEFT JOIN $employees_table e ON l.assigned_to = e.id 
            WHERE $where_sql 
            ORDER BY l.$orderby $order 
            LIMIT %d OFFSET %d
        ";
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
        $this->items = $this->get_leads();
    }

    /**
     * Определение колонок
     */
    public function get_columns() {
        return [
            'cb'        => '<input type="checkbox" />',
            'id'        => __('ID', 'akpp-crm'),
            'source'    => __('Источник', 'akpp-crm'),
            'name'      => __('Клиент', 'akpp-crm'),
            'phone'     => __('Телефон', 'akpp-crm'),
            'message'   => __('Сообщение', 'akpp-crm'),
            'status'    => __('Статус', 'akpp-crm'),
            'assigned'  => __('Гид/Менеджер', 'akpp-crm'),
            'date'      => __('Дата', 'akpp-crm'),
            'actions'   => __('Действия', 'akpp-crm')
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
     * Дополнительная навигация (Фильтры по источнику и статусу)
     */
    public function extra_tablenav($which) {
        if ($which === 'top') {
            $current_source = isset($_REQUEST['source']) ? sanitize_text_field($_REQUEST['source']) : '';
            $current_status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
            ?>
            <div class="alignleft actions">
                <select name="source">
                    <option value=""><?php _e('Все источники', 'akpp-crm'); ?></option>
                    <?php foreach ($this->sources as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($current_source, $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

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
        return sprintf('<input type="checkbox" name="lead[]" value="%s" />', $item['id']);
    }

    /**
     * Колонка ID
     */
    public function column_id($item) {
        return '<strong>#' . esc_html($item['id']) . '</strong>';
    }

    /**
     * Колонка Источник
     */
    public function column_source($item) {
        $badges = [
            'site'     => '<span class="akpp-badge akpp-badge-primary">Сайт</span>',
            'avito'    => '<span class="akpp-badge akpp-badge-success">Авито</span>',
            'telegram' => '<span class="akpp-badge akpp-badge-info">Telegram</span>',
            'call'     => '<span class="akpp-badge akpp-badge-warning">Звонок</span>'
        ];
        return isset($badges[$item['source']]) ? $badges[$item['source']] : esc_html($item['source']);
    }

    /**
     * Колонка Клиент
     */
    public function column_name($item) {
        return !empty($item['name']) ? esc_html($item['name']) : '<span class="akpp-text-muted">Аноним</span>';
    }

    /**
     * Колонка Телефон
     */
    public function column_phone($item) {
        if (!empty($item['phone'])) {
            return '<a href="tel:' . esc_attr($item['phone']) . '">' . esc_html($item['phone']) . '</a>';
        }
        return '<span class="akpp-text-muted">—</span>';
    }

    /**
     * Колонка Сообщение (обрезанное)
     */
    public function column_message($item) {
        return !empty($item['message']) ? wp_kses_post(wp_trim_words($item['message'], 10, '...')) : '<span class="akpp-text-muted">—</span>';
    }

    /**
     * Колонка Статус
     */
    public function column_status($item) {
        $badges = [
            'new'       => '<span class="akpp-badge akpp-badge-info">Новый</span>',
            'contacted' => '<span class="akpp-badge akpp-badge-warning">На связи</span>',
            'converted' => '<span class="akpp-badge akpp-badge-success">Конвертирован</span>',
            'rejected'  => '<span class="akpp-badge akpp-badge-danger">Отклонен</span>'
        ];
        return isset($badges[$item['status']]) ? $badges[$item['status']] : esc_html($item['status']);
    }

    /**
     * Колонка Назначенный сотрудник
     */
    public function column_assigned($item) {
        return !empty($item['assigned_name']) ? esc_html($item['assigned_name']) : '<span class="akpp-text-muted">Не назначен</span>';
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
        $delete_url = wp_nonce_url(
            add_query_arg(['page' => 'akpp-leads', 'action' => 'delete', 'id' => $item['id']], admin_url('admin.php')), 
            'akpp_delete_lead_' . $item['id']
        );

        return sprintf(
            '<a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'Удалить этот лид?\');">Удалить</a>',
            esc_url($delete_url)
        );
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
            'contacted' => __('Отметить как "На связи"', 'akpp-crm'),
            'converted' => __('Отметить как "Конвертирован"', 'akpp-crm'),
            'rejected'  => __('Отметить как "Отклонен"', 'akpp-crm'),
            'delete'    => __('Удалить', 'akpp-crm')
        ];
    }

    /**
     * Обработка массовых действий
     */
    public function process_bulk_action() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_leads';

        $action = $this->current_action();
        
        // Одиночное действие
        if (in_array($action, ['contacted', 'converted', 'rejected', 'delete']) && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            if ($action === 'delete') {
                check_admin_referer('akpp_delete_lead_' . $id);
                $wpdb->delete($table_name, ['id' => $id]);
            } else {
                check_admin_referer('akpp_update_lead_status_' . $id);
                $wpdb->update($table_name, ['status' => $action], ['id' => $id]);
            }
            wp_redirect(add_query_arg(['page' => 'akpp-leads', 'updated' => '1'], admin_url('admin.php')));
            exit;
        }

        // Массовое действие
        if (in_array($action, ['contacted', 'converted', 'rejected', 'delete']) && isset($_POST['lead']) && is_array($_POST['lead'])) {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $ids = array_map('intval', $_POST['lead']);
            $ids_str = implode(',', $ids);

            if ($action === 'delete') {
                $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids_str)");
            } else {
                $wpdb->query($wpdb->prepare("UPDATE $table_name SET status = %s WHERE id IN ($ids_str)", $action));
            }
            
            wp_redirect(add_query_arg(['page' => 'akpp-leads', 'updated' => count($ids)], admin_url('admin.php')));
            exit;
        }
    }
}
