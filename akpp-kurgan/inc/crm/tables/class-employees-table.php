<?php
if (!defined('ABSPATH')) exit;

// Подключаем класс WP_List_Table, если он еще не загружен
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class AKPP_Employees_Table extends WP_List_Table {
    
    public function __construct() {
        parent::__construct([
            'singular' => __('Сотрудник', 'akpp-crm'),
            'plural'   => __('Сотрудники', 'akpp-crm'),
            'ajax'     => false
        ]);
    }

    /**
     * Получение данных из базы данных
     */
    public function get_employees() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_employees';

        // Параметры запроса
        $per_page = $this->get_items_per_page('employees_per_page', 20);
        $current_page = $this->get_pagenum();
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) && $_REQUEST['order'] === 'desc' ? 'DESC' : 'ASC';

        // Построение условия WHERE для поиска
        $where = "1=1";
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where = $wpdb->prepare("(full_name LIKE %s OR phone LIKE %s OR role LIKE %s)", $search_like, $search_like, $search_like);
        }

        // Получение общего количества записей для пагинации
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $where");

        // Получение данных для текущей страницы
        $offset = ($current_page - 1) * $per_page;
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d",
            $per_page, $offset
        );
        $data = $wpdb->get_results($query, ARRAY_A);

        // Установка свойств для пагинации
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        return $data;
    }

    /**
     * Подготовка элементов для вывода
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        // Обработка массовых действий
        $this->process_bulk_action();

        $this->items = $this->get_employees();
    }

    /**
     * Определение колонок таблицы
     */
    public function get_columns() {
        return [
            'cb'       => '<input type="checkbox" />',
            'id'       => __('ID', 'akpp-crm'),
            'full_name'=> __('ФИО', 'akpp-crm'),
            'role'     => __('Должность', 'akpp-crm'),
            'phone'    => __('Телефон', 'akpp-crm'),
            'status'   => __('Статус', 'akpp-crm'),
            'actions'  => __('Действия', 'akpp-crm')
        ];
    }

    /**
     * Определение сортируемых колонок
     */
    public function get_sortable_columns() {
        return [
            'id'        => ['id', true],
            'full_name' => ['full_name', false],
            'role'      => ['role', false]
        ];
    }

    /**
     * Колонка с чекбоксом
     */
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="employee[]" value="%s" />', $item['id']);
    }

    /**
     * Колонка ID
     */
    public function column_id($item) {
        return '#' . esc_html($item['id']);
    }

    /**
     * Колонка ФИО (с ссылками действий)
     */
    public function column_full_name($item) {
        $edit_url = add_query_arg([
            'page' => 'akpp-employees',
            'action' => 'edit',
            'id' => $item['id'],
            '_wpnonce' => wp_create_nonce('akpp_edit_employee_' . $item['id'])
        ], admin_url('admin.php'));

        $actions = [
            'edit' => sprintf('<a href="%s">%s</a>', esc_url($edit_url), __('Редактировать', 'akpp-crm')),
            'delete' => sprintf('<a href="%s" style="color:#a00;" onclick="return confirm(\'Удалить сотрудника?\');">%s</a>', 
                wp_nonce_url(add_query_arg(['page' => 'akpp-employees', 'action' => 'delete', 'id' => $item['id']], admin_url('admin.php')), 'akpp_delete_employee_' . $item['id']), 
                __('Удалить', 'akpp-crm')
            )
        ];

        return sprintf('<strong>%1$s</strong> %2$s', esc_html($item['full_name']), $this->row_actions($actions));
    }

    /**
     * Колонка Должность
     */
    public function column_role($item) {
        $roles = [
            'admin' => 'Администратор',
            'manager' => 'Менеджер',
            'mechanic' => 'Механик'
        ];
        return esc_html($roles[$item['role']] ?? $item['role']);
    }

    /**
     * Колонка Телефон
     */
    public function column_phone($item) {
        return esc_html($item['phone']);
    }

    /**
     * Колонка Статус (с цветным бейджем)
     */
    public function column_status($item) {
        if ($item['status'] === 'active') {
            return '<span class="akpp-badge akpp-badge-success">Активен</span>';
        }
        return '<span class="akpp-badge akpp-badge-secondary">Неактивен</span>';
    }

    /**
     * Колонка Действия (дублирует row_actions, но для явного столбца)
     */
    public function column_actions($item) {
        $edit_url = add_query_arg([
            'page' => 'akpp-employees',
            'action' => 'edit',
            'id' => $item['id'],
            '_wpnonce' => wp_create_nonce('akpp_edit_employee_' . $item['id'])
        ], admin_url('admin.php'));

        $delete_url = wp_nonce_url(
            add_query_arg(['page' => 'akpp-employees', 'action' => 'delete', 'id' => $item['id']], admin_url('admin.php')), 
            'akpp_delete_employee_' . $item['id']
        );

        return sprintf(
            '<a href="%s" class="button button-small">Изменить</a> 
             <a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'Вы уверены?\');">Удалить</a>',
            esc_url($edit_url),
            esc_url($delete_url)
        );
    }

    /**
     * Значение по умолчанию для колонок
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
        $table_name = $wpdb->prefix . 'akpp_employees';

        // Одиночное удаление
        if ('delete' === $this->current_action() && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            check_admin_referer('akpp_delete_employee_' . $id);
            $wpdb->delete($table_name, ['id' => $id]);
            wp_redirect(add_query_arg(['page' => 'akpp-employees', 'deleted' => '1'], admin_url('admin.php')));
            exit;
        }

        // Массовое удаление
        if ('delete' === $this->current_action() && isset($_POST['employee']) && is_array($_POST['employee'])) {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $ids = array_map('intval', $_POST['employee']);
            $ids_str = implode(',', $ids);
            $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids_str)");
            wp_redirect(add_query_arg(['page' => 'akpp-employees', 'deleted' => count($ids)], admin_url('admin.php')));
            exit;
        }
    }
}
