<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class AKPP_Oils_Table extends WP_List_Table {
    
    // Основные типы трансмиссионных масел
    private $oil_types = [
        'ATF' => 'ATF (Гидромеханические)',
        'CVT' => 'CVT (Вариаторные)',
        'DCT' => 'DCT (Роботизированные)',
        'MTF' => 'MTF (Механические)'
    ];

    public function __construct() {
        parent::__construct([
            'singular' => __('Масло', 'akpp-crm'),
            'plural'   => __('Склад масел', 'akpp-crm'),
            'ajax'     => false
        ]);
    }

    /**
     * Получение данных о маслах из БД с учетом фильтров
     */
    public function get_oils() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_oils';

        $per_page = $this->get_items_per_page('oils_per_page', 20);
        $current_page = $this->get_pagenum();
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $type_filter = isset($_REQUEST['type']) ? sanitize_text_field($_REQUEST['type']) : '';
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) && $_REQUEST['order'] === 'asc' ? 'ASC' : 'DESC';

        // Построение условий WHERE
        $where_clauses = ["1=1"];
        $prepare_args = [];

        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = "(name LIKE %s)";
            $prepare_args[] = $search_like;
        }

        if (!empty($type_filter) && array_key_exists($type_filter, $this->oil_types)) {
            $where_clauses[] = "type = %s";
            $prepare_args[] = $type_filter;
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
        $this->items = $this->get_oils();
    }

    /**
     * Определение колонок
     */
    public function get_columns() {
        return [
            'cb'       => '<input type="checkbox" />',
            'id'       => __('ID', 'akpp-crm'),
            'name'     => __('Название / Бренд', 'akpp-crm'),
            'type'     => __('Тип', 'akpp-crm'),
            'volume'   => __('Объем тары (л)', 'akpp-crm'),
            'quantity' => __('Остаток (шт/л)', 'akpp-crm'),
            'price'    => __('Цена', 'akpp-crm'),
            'actions'  => __('Действия', 'akpp-crm')
        ];
    }

    /**
     * Сортируемые колонки
     */
    public function get_sortable_columns() {
        return [
            'id'       => ['id', true],
            'name'     => ['name', false],
            'quantity' => ['quantity', false],
            'price'    => ['price', false]
        ];
    }

    /**
     * Дополнительная навигация (Фильтр по типу масла)
     */
    public function extra_tablenav($which) {
        if ($which === 'top') {
            $current_type = isset($_REQUEST['type']) ? sanitize_text_field($_REQUEST['type']) : '';
            ?>
            <div class="alignleft actions">
                <select name="type">
                    <option value=""><?php _e('Все типы масел', 'akpp-crm'); ?></option>
                    <?php foreach ($this->oil_types as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($current_type, $value); ?>>
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
        return sprintf('<input type="checkbox" name="oil[]" value="%s" />', $item['id']);
    }

    /**
     * Колонка ID
     */
    public function column_id($item) {
        return '#' . esc_html($item['id']);
    }

    /**
     * Колонка Название
     */
    public function column_name($item) {
        return '<strong>' . esc_html($item['name']) . '</strong>';
    }

    /**
     * Колонка Тип (с цветным бейджем)
     */
    public function column_type($item) {
        $badges = [
            'ATF' => '<span class="akpp-badge akpp-badge-info">ATF</span>',
            'CVT' => '<span class="akpp-badge akpp-badge-warning">CVT</span>',
            'DCT' => '<span class="akpp-badge akpp-badge-primary">DCT</span>',
            'MTF' => '<span class="akpp-badge akpp-badge-secondary">MTF</span>'
        ];
        return isset($badges[$item['type']]) ? $badges[$item['type']] : esc_html($item['type']);
    }

    /**
     * Колонка Объем
     */
    public function column_volume($item) {
        return floatval($item['volume_liters']) . ' л';
    }

    /**
     * Колонка Остаток (с предупреждением о низком запасе)
     */
    public function column_quantity($item) {
        $qty = intval($item['quantity']);
        if ($qty === 0) {
            return '<span class="akpp-badge akpp-badge-danger">Нет в наличии</span>';
        } elseif ($qty < 10) {
            return '<span class="akpp-badge akpp-badge-warning">Мало: ' . $qty . '</span>';
        }
        return '<span class="akpp-badge akpp-badge-success">' . $qty . '</span>';
    }

    /**
     * Колонка Цена
     */
    public function column_price($item) {
        return number_format(floatval($item['price']), 2, '.', ' ') . ' ₽';
    }

    /**
     * Колонка Действия
     */
    public function column_actions($item) {
        $edit_url = add_query_arg([
            'page' => 'akpp-oils',
            'action' => 'edit',
            'id' => $item['id'],
            '_wpnonce' => wp_create_nonce('akpp_edit_oil_' . $item['id'])
        ], admin_url('admin.php'));

        $delete_url = wp_nonce_url(
            add_query_arg(['page' => 'akpp-oils', 'action' => 'delete', 'id' => $item['id']], admin_url('admin.php')), 
            'akpp_delete_oil_' . $item['id']
        );

        return sprintf(
            '<a href="%s" class="button button-small">Изменить</a> 
             <a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'Списать масло со склада?\');">Удалить</a>',
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
        $table_name = $wpdb->prefix . 'akpp_oils';

        // Одиночное удаление
        if ('delete' === $this->current_action() && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            check_admin_referer('akpp_delete_oil_' . $id);
            $wpdb->delete($table_name, ['id' => $id]);
            wp_redirect(add_query_arg(['page' => 'akpp-oils', 'deleted' => '1'], admin_url('admin.php')));
            exit;
        }

        // Массовое удаление
        if ('delete' === $this->current_action() && isset($_POST['oil']) && is_array($_POST['oil'])) {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $ids = array_map('intval', $_POST['oil']);
            $ids_str = implode(',', $ids);
            $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids_str)");
            wp_redirect(add_query_arg(['page' => 'akpp-oils', 'deleted' => count($ids)], admin_url('admin.php')));
            exit;
        }
    }
}
