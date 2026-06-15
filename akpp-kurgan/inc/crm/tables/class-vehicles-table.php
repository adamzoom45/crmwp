<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class AKPP_Vehicles_Table extends WP_List_Table {
    
    public function __construct() {
        parent::__construct([
            'singular' => __('Автомобиль', 'akpp-crm'),
            'plural'   => __('Автомобили', 'akpp-crm'),
            'ajax'     => false
        ]);
    }

    /**
     * Получение данных из базы данных с учетом фильтров
     */
    public function get_vehicles() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_vehicles';

        $per_page = $this->get_items_per_page('vehicles_per_page', 20);
        $current_page = $this->get_pagenum();
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $market_filter = isset($_REQUEST['market']) ? sanitize_text_field($_REQUEST['market']) : '';
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) && $_REQUEST['order'] === 'desc' ? 'DESC' : 'ASC';

        // Построение условий WHERE
        $where_clauses = ["1=1"];
        $prepare_args = [];

        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = "(brand LIKE %s OR model LIKE %s OR vin LIKE %s)";
            $prepare_args[] = $search_like;
            $prepare_args[] = $search_like;
            $prepare_args[] = $search_like;
        }

        if (!empty($market_filter) && in_array($market_filter, ['japan', 'asia', 'europe', 'usa'])) {
            $where_clauses[] = "market = %s";
            $prepare_args[] = $market_filter;
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Общее количество записей для пагинации
        $count_query = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";
        if (!empty($prepare_args)) {
            $total_items = $wpdb->get_var($wpdb->prepare($count_query, $prepare_args));
        } else {
            $total_items = $wpdb->get_var($count_query);
        }

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
        $this->items = $this->get_vehicles();
    }

    /**
     * Определение колонок
     */
    public function get_columns() {
        return [
            'cb'    => '<input type="checkbox" />',
            'id'    => __('ID', 'akpp-crm'),
            'brand' => __('Марка', 'akpp-crm'),
            'model' => __('Модель', 'akpp-crm'),
            'year'  => __('Год', 'akpp-crm'),
            'market'=> __('Рынок', 'akpp-crm'),
            'vin'   => __('VIN / Кузов', 'akpp-crm'),
            'actions' => __('Действия', 'akpp-crm')
        ];
    }

    /**
     * Сортируемые колонки
     */
    public function get_sortable_columns() {
        return [
            'id'    => ['id', true],
            'brand' => ['brand', false],
            'model' => ['model', false],
            'year'  => ['year', false]
        ];
    }

    /**
     * Дополнительная навигация (Фильтр по рынку)
     */
    public function extra_tablenav($which) {
        if ($which === 'top') {
            $current_market = isset($_REQUEST['market']) ? sanitize_text_field($_REQUEST['market']) : '';
            ?>
            <div class="alignleft actions">
                <select name="market">
                    <option value=""><?php _e('Все рынки', 'akpp-crm'); ?></option>
                    <option value="japan" <?php selected($current_market, 'japan'); ?>><?php _e('🇯🇵 Япония', 'akpp-crm'); ?></option>
                    <option value="asia" <?php selected($current_market, 'asia'); ?>><?php _e('🌏 Азия', 'akpp-crm'); ?></option>
                    <option value="europe" <?php selected($current_market, 'europe'); ?>><?php _e('🇪🇺 Европа', 'akpp-crm'); ?></option>
                    <option value="usa" <?php selected($current_market, 'usa'); ?>><?php _e('🇺🇸 США', 'akpp-crm'); ?></option>
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
        return sprintf('<input type="checkbox" name="vehicle[]" value="%s" />', $item['id']);
    }

    /**
     * Колонка ID
     */
    public function column_id($item) {
        return '#' . esc_html($item['id']);
    }

    /**
     * Колонка Марка
     */
    public function column_brand($item) {
        return '<strong>' . esc_html($item['brand']) . '</strong>';
    }

    /**
     * Колонка Модель
     */
    public function column_model($item) {
        return esc_html($item['model']);
    }

    /**
     * Колонка Год
     */
    public function column_year($item) {
        return esc_html($item['year']);
    }

    /**
     * Колонка Рынок (с бейджем)
     */
    public function column_market($item) {
        $markets = [
            'japan'  => '<span class="akpp-badge akpp-badge-japan">🇯🇵 Япония</span>',
            'asia'   => '<span class="akpp-badge akpp-badge-asia">🌏 Азия</span>',
            'europe' => '<span class="akpp-badge akpp-badge-europe">🇪🇺 Европа</span>',
            'usa'    => '<span class="akpp-badge akpp-badge-usa">🇺🇸 США</span>'
        ];
        return isset($markets[$item['market']]) ? $markets[$item['market']] : esc_html($item['market']);
    }

    /**
     * Колонка VIN
     */
    public function column_vin($item) {
        return '<code>' . esc_html($item['vin'] ?: '—') . '</code>';
    }

    /**
     * Колонка Действия
     */
    public function column_actions($item) {
        $edit_url = add_query_arg([
            'page' => 'akpp-vehicles',
            'action' => 'edit',
            'id' => $item['id'],
            '_wpnonce' => wp_create_nonce('akpp_edit_vehicle_' . $item['id'])
        ], admin_url('admin.php'));

        $delete_url = wp_nonce_url(
            add_query_arg(['page' => 'akpp-vehicles', 'action' => 'delete', 'id' => $item['id']], admin_url('admin.php')), 
            'akpp_delete_vehicle_' . $item['id']
        );

        return sprintf(
            '<a href="%s" class="button button-small">Изменить</a> 
             <a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'Удалить этот автомобиль?\');">Удалить</a>',
            esc_url($edit_url),
            esc_url($delete_url)
        );
    }

    /**
     * Значение по умолчанию
     */
    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
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
        $table_name = $wpdb->prefix . 'akpp_vehicles';

        // Одиночное удаление
        if ('delete' === $this->current_action() && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            check_admin_referer('akpp_delete_vehicle_' . $id);
            $wpdb->delete($table_name, ['id' => $id]);
            wp_redirect(add_query_arg(['page' => 'akpp-vehicles', 'deleted' => '1'], admin_url('admin.php')));
            exit;
        }

        // Массовое удаление
        if ('delete' === $this->current_action() && isset($_POST['vehicle']) && is_array($_POST['vehicle'])) {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $ids = array_map('intval', $_POST['vehicle']);
            $ids_str = implode(',', $ids);
            $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids_str)");
            wp_redirect(add_query_arg(['page' => 'akpp-vehicles', 'deleted' => count($ids)], admin_url('admin.php')));
            exit;
        }
    }
}
