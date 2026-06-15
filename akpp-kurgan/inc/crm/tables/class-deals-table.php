<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class AKPP_Deals_Table extends WP_List_Table {
    
    public function __construct() {
        parent::__construct([
            'singular' => __('Сделка', 'akpp-crm'),
            'plural'   => __('Сделки', 'akpp-crm'),
            'ajax'     => false
        ]);
    }

    /**
     * Получение данных о сделках из БД с учетом фильтров
     */
    public function get_deals() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_deals';
        $vehicles_table = $wpdb->prefix . 'akpp_vehicles';
        $employees_table = $wpdb->prefix . 'akpp_employees';

        $per_page = $this->get_items_per_page('deals_per_page', 20);
        $current_page = $this->get_pagenum();
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $status_filter = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'created_at';
        $order = isset($_REQUEST['order']) && $_REQUEST['order'] === 'asc' ? 'ASC' : 'DESC'; // По умолчанию новые сверху

        // Построение условий WHERE
        $where_clauses = ["1=1"];
        $prepare_args = [];

        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = "(d.client_name LIKE %s OR d.client_phone LIKE %s OR v.vin LIKE %s)";
            $prepare_args[] = $search_like;
            $prepare_args[] = $search_like;
            $prepare_args[] = $search_like;
        }

        if (!empty($status_filter)) {
            $where_clauses[] = "d.status = %s";
            $prepare_args[] = $status_filter;
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Общее количество записей
        $count_query = "SELECT COUNT(*) FROM $table_name d LEFT JOIN $vehicles_table v ON d.vehicle_id = v.id WHERE $where_sql";
        $total_items = empty($prepare_args) ? $wpdb->get_var($count_query) : $wpdb->get_var($wpdb->prepare($count_query, $prepare_args));

        // Данные для текущей страницы (с JOIN для получения названия авто и имени сотрудника)
        $offset = ($current_page - 1) * $per_page;
        $prepare_args[] = $per_page;
        $prepare_args[] = $offset;

        $query = "
            SELECT d.*, v.brand, v.model, v.vin, e.full_name as employee_name 
            FROM $table_name d 
            LEFT JOIN $vehicles_table v ON d.vehicle_id = v.id 
            LEFT JOIN $employees_table e ON d.employee_id = e.id 
            WHERE $where_sql 
            ORDER BY d.$orderby $order 
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
        $this->items = $this->get_deals();
    }

    /**
     * Определение колонок
     */
    public function get_columns() {
        return [
            'cb'        => '<input type="checkbox" />',
            'id'        => __('№', 'akpp-crm'),
            'client'    => __('Клиент', 'akpp-crm'),
            'vehicle'   => __('Автомобиль / VIN', 'akpp-crm'),
            'status'    => __('Статус', 'akpp-crm'),
            'total'     => __('Сумма', 'akpp-crm'),
            'employee'  => __('Мастер', 'akpp-crm'),
            'date'      => __('Дата', 'akpp-crm'),
            'actions'   => __('Действия', 'akpp-crm')
        ];
    }

    /**
     * Сортируемые колонки
     */
    public function get_sortable_columns() {
        return [
            'id'    => ['id', true],
            'total' => ['total_amount', false],
            'date'  => ['created_at', false]
        ];
    }

    /**
     * Дополнительная навигация (Фильтр по статусу воронки)
     */
    public function extra_tablenav($which) {
        if ($which === 'top') {
            $current_status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
            $statuses = [
                '' => 'Все статусы',
                'lead' => '🔵 Лид',
                'new' => '🟢 Новая',
                'diagnostic' => '🟡 Диагностика',
                'in_work' => '🟠 В работе',
                'completed' => '✅ Завершена',
                'cancelled' => '❌ Отменена'
            ];
            ?>
            <div class="alignleft actions">
                <select name="status">
                    <?php foreach ($statuses as $value => $label) : ?>
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
        return sprintf('<input type="checkbox" name="deal[]" value="%s" />', $item['id']);
    }

    /**
     * Колонка ID
     */
    public function column_id($item) {
        return '<strong>#' . esc_html($item['id']) . '</strong>';
    }

    /**
     * Колонка Клиент
     */
    public function column_client($item) {
        $output = '<strong>' . esc_html($item['client_name']) . '</strong><br>';
        $output .= '<small class="akpp-text-muted">' . esc_html($item['client_phone']) . '</small>';
        return $output;
    }

    /**
     * Колонка Автомобиль
     */
    public function column_vehicle($item) {
        if (!empty($item['brand']) && !empty($item['model'])) {
            $output = esc_html($item['brand'] . ' ' . $item['model']);
        } else {
            $output = '<span class="akpp-text-muted">Не указано</span>';
        }
        if (!empty($item['vin'])) {
            $output .= '<br><code style="font-size: 11px;">' . esc_html($item['vin']) . '</code>';
        }
        return $output;
    }

    /**
     * Колонка Статус (с цветным бейджем)
     */
    public function column_status($item) {
        $badges = [
            'lead'       => '<span class="akpp-badge akpp-badge-info">Лид</span>',
            'new'        => '<span class="akpp-badge akpp-badge-success">Новая</span>',
            'diagnostic' => '<span class="akpp-badge akpp-badge-warning">Диагностика</span>',
            'in_work'    => '<span class="akpp-badge akpp-badge-primary">В работе</span>',
            'completed'  => '<span class="akpp-badge akpp-badge-success" style="background:#00aa55;">Завершена</span>',
            'cancelled'  => '<span class="akpp-badge akpp-badge-danger">Отменена</span>'
        ];
        return isset($badges[$item['status']]) ? $badges[$item['status']] : esc_html($item['status']);
    }

    /**
     * Колонка Сумма
     */
    public function column_total($item) {
        return '<strong>' . number_format(floatval($item['total_amount']), 0, '.', ' ') . ' ₽</strong>';
    }

    /**
     * Колонка Сотрудник
     */
    public function column_employee($item) {
        return !empty($item['employee_name']) ? esc_html($item['employee_name']) : '<span class="akpp-text-muted">Не назначен</span>';
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
        $edit_url = add_query_arg([
            'page' => 'akpp-new-deal',
            'action' => 'edit',
            'id' => $item['id'],
            '_wpnonce' => wp_create_nonce('akpp_edit_deal_' . $item['id'])
        ], admin_url('admin.php'));

        $delete_url = wp_nonce_url(
            add_query_arg(['page' => 'akpp-deals', 'action' => 'delete', 'id' => $item['id']], admin_url('admin.php')), 
            'akpp_delete_deal_' . $item['id']
        );

        return sprintf(
            '<a href="%s" class="button button-small">Открыть</a> 
             <a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'Удалить сделку?\');">Удалить</a>',
            esc_url($edit_url),
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
            'delete' => __('Удалить', 'akpp-crm')
        ];
    }

    /**
     * Обработка массовых действий
     */
    public function process_bulk_action() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_deals';
        $parts_table = $wpdb->prefix . 'akpp_deal_parts';

        // Одиночное удаление
        if ('delete' === $this->current_action() && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            check_admin_referer('akpp_delete_deal_' . $id);
            $wpdb->delete($table_name, ['id' => $id]);
            $wpdb->delete($parts_table, ['deal_id' => $id]); // Каскадное удаление запчастей
            wp_redirect(add_query_arg(['page' => 'akpp-deals', 'deleted' => '1'], admin_url('admin.php')));
            exit;
        }

        // Массовое удаление
        if ('delete' === $this->current_action() && isset($_POST['deal']) && is_array($_POST['deal'])) {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $ids = array_map('intval', $_POST['deal']);
            $ids_str = implode(',', $ids);
            $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids_str)");
            $wpdb->query("DELETE FROM $parts_table WHERE deal_id IN ($ids_str)");
            wp_redirect(add_query_arg(['page' => 'akpp-deals', 'deleted' => count($ids)], admin_url('admin.php')));
            exit;
        }
    }
}
