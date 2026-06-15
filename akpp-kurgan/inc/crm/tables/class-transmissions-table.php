<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class AKPP_Transmissions_Table extends WP_List_Table {
    
    public function __construct() {
        parent::__construct([
            'singular' => __('АКПП', 'akpp-crm'),
            'plural'   => __('Каталог АКПП', 'akpp-crm'),
            'ajax'     => false
        ]);
    }

    /**
     * Получение данных из базы данных
     */
    public function get_transmissions() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_transmissions';

        $per_page = $this->get_items_per_page('transmissions_per_page', 20);
        $current_page = $this->get_pagenum();
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) && $_REQUEST['order'] === 'desc' ? 'DESC' : 'ASC';

        // Построение условия WHERE
        $where = "1=1";
        $prepare_args = [];
        
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where = $wpdb->prepare("(name LIKE %s OR code LIKE %s OR description LIKE %s)", $search_like, $search_like, $search_like);
        }

        // Общее количество записей
        $count_query = "SELECT COUNT(*) FROM $table_name WHERE $where";
        $total_items = empty($prepare_args) ? $wpdb->get_var($count_query) : $wpdb->get_var($wpdb->prepare($count_query, $prepare_args));

        // Данные для текущей страницы
        $offset = ($current_page - 1) * $per_page;
        $prepare_args[] = $per_page;
        $prepare_args[] = $offset;

        $query = "SELECT * FROM $table_name WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
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
        $this->items = $this->get_transmissions();
    }

    /**
     * Определение колонок
     */
    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'id'          => __('ID', 'akpp-crm'),
            'code'        => __('Код АКПП', 'akpp-crm'),
            'name'        => __('Название', 'akpp-crm'),
            'type'        => __('Тип', 'akpp-crm'),
            'description' => __('Описание', 'akpp-crm'),
            'actions'     => __('Действия', 'akpp-crm')
        ];
    }

    /**
     * Сортируемые колонки
     */
    public function get_sortable_columns() {
        return [
            'id'   => ['id', true],
            'code' => ['code', false],
            'name' => ['name', false],
            'type' => ['type', false]
        ];
    }

    /**
     * Колонка с чекбоксом
     */
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="transmission[]" value="%s" />', $item['id']);
    }

    /**
     * Колонка ID
     */
    public function column_id($item) {
        return '#' . esc_html($item['id']);
    }

    /**
     * Колонка Код АКПП (жирным, так как это ключевой идентификатор)
     */
    public function column_code($item) {
        return '<strong>' . esc_html($item['code']) . '</strong>';
    }

    /**
     * Колонка Название
     */
    public function column_name($item) {
        return esc_html($item['name']);
    }

    /**
     * Колонка Тип (с цветным бейджем)
     */
    public function column_type($item) {
        $types = [
            'AT'  => '<span class="akpp-badge akpp-badge-info">AT (Гидротрансформатор)</span>',
            'CVT' => '<span class="akpp-badge akpp-badge-warning">CVT (Вариатор)</span>',
            'DCT' => '<span class="akpp-badge akpp-badge-primary">DCT (Робот)</span>',
            'AMT' => '<span class="akpp-badge akpp-badge-secondary">AMT (Робот)</span>',
            'MT'  => '<span class="akpp-badge akpp-badge-dark">MT (Механика)</span>'
        ];
        return isset($types[$item['type']]) ? $types[$item['type']] : esc_html($item['type']);
    }

    /**
     * Колонка Описание
     */
    public function column_description($item) {
        return wp_kses_post(wp_trim_words($item['description'], 15, '...'));
    }

    /**
     * Колонка Действия
     */
    public function column_actions($item) {
        $edit_url = add_query_arg([
            'page' => 'akpp-transmissions',
            'action' => 'edit',
            'id' => $item['id'],
            '_wpnonce' => wp_create_nonce('akpp_edit_transmission_' . $item['id'])
        ], admin_url('admin.php'));

        $delete_url = wp_nonce_url(
            add_query_arg(['page' => 'akpp-transmissions', 'action' => 'delete', 'id' => $item['id']], admin_url('admin.php')), 
            'akpp_delete_transmission_' . $item['id']
        );

        return sprintf(
            '<a href="%s" class="button button-small">Изменить</a> 
             <a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'Удалить эту позицию из каталога?\');">Удалить</a>',
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
        $table_name = $wpdb->prefix . 'akpp_transmissions';

        // Одиночное удаление
        if ('delete' === $this->current_action() && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            check_admin_referer('akpp_delete_transmission_' . $id);
            $wpdb->delete($table_name, ['id' => $id]);
            wp_redirect(add_query_arg(['page' => 'akpp-transmissions', 'deleted' => '1'], admin_url('admin.php')));
            exit;
        }

        // Массовое удаление
        if ('delete' === $this->current_action() && isset($_POST['transmission']) && is_array($_POST['transmission'])) {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $ids = array_map('intval', $_POST['transmission']);
            $ids_str = implode(',', $ids);
            $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids_str)");
            wp_redirect(add_query_arg(['page' => 'akpp-transmissions', 'deleted' => count($ids)], admin_url('admin.php')));
            exit;
        }
    }
}
