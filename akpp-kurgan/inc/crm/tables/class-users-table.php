<?php
/**
 * АКПП45 CRM - Таблица пользователей сайта (клиентов)
 * WP_List_Table для управления зарегистрированными клиентами.
 *
 * @package AKPP_CRM
 * @version 4.2
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class AKPP_Users_Table extends WP_List_Table {

    /**
     * Конструктор таблицы
     */
    public function __construct() {
        parent::__construct([
            'singular' => __('Пользователь', 'akpp-crm'),
            'plural'   => __('Пользователи', 'akpp-crm'),
            'ajax'     => false
        ]);
    }

    /**
     * Получение данных пользователей из БД с учётом фильтров и поиска
     */
    public function get_users() {
        global $wpdb;
        $site_users_table  = $wpdb->prefix . 'akpp_site_users';
        $wp_users_table    = $wpdb->users;

        // Параметры пагинации
        $per_page     = $this->get_items_per_page('users_per_page', 20);
        $current_page = $this->get_pagenum();

        // Параметры поиска и сортировки
        $search  = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'su.id';
        $order   = isset($_REQUEST['order']) && $_REQUEST['order'] === 'asc' ? 'ASC' : 'DESC';

        // Построение условий WHERE
        $where_clauses = ["1=1"];
        $prepare_args  = [];

        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = "(su.full_name LIKE %s OR su.phone LIKE %s OR su.car_info LIKE %s OR u.user_email LIKE %s)";
            $prepare_args[] = $search_like;
            $prepare_args[] = $search_like;
            $prepare_args[] = $search_like;
            $prepare_args[] = $search_like;
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Общее количество записей
        $count_query = "
            SELECT COUNT(*)
            FROM $site_users_table su
            LEFT JOIN $wp_users_table u ON su.wp_user_id = u.ID
            WHERE $where_sql
        ";
        $total_items = empty($prepare_args)
            ? $wpdb->get_var($count_query)
            : $wpdb->get_var($wpdb->prepare($count_query, $prepare_args));

        // Данные для текущей страницы
        $offset = ($current_page - 1) * $per_page;
        $prepare_args[] = $per_page;
        $prepare_args[] = $offset;

        $query = "
            SELECT
                su.id,
                su.wp_user_id,
                su.full_name,
                su.phone,
                su.car_info,
                u.user_email,
                u.user_registered
            FROM $site_users_table su
            LEFT JOIN $wp_users_table u ON su.wp_user_id = u.ID
            WHERE $where_sql
            ORDER BY $orderby $order
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
     * Подготовка элементов для вывода
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden  = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        // Обработка массовых действий
        $this->process_bulk_action();

        $this->items = $this->get_users();
    }

    /**
     * Определение колонок таблицы
     */
    public function get_columns() {
        return [
            'cb'        => '<input type="checkbox" />',
            'id'        => __('ID', 'akpp-crm'),
            'full_name' => __('ФИО', 'akpp-crm'),
            'email'     => __('Email', 'akpp-crm'),
            'phone'     => __('Телефон', 'akpp-crm'),
            'car_info'  => __('Автомобиль', 'akpp-crm'),
            'registered'=> __('Дата регистрации', 'akpp-crm'),
            'actions'   => __('Действия', 'akpp-crm')
        ];
    }

    /**
     * Сортируемые колонки
     */
    public function get_sortable_columns() {
        return [
            'id'         => ['su.id', true],
            'full_name'  => ['su.full_name', false],
            'registered' => ['u.user_registered', false]
        ];
    }

    /**
     * Колонка с чекбоксом для массовых действий
     */
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="user[]" value="%s" />', $item['id']);
    }

    /**
     * Колонка ID
     */
    public function column_id($item) {
        return '<strong>#' . esc_html($item['id']) . '</strong>';
    }

    /**
     * Колонка ФИО (с быстрыми действиями)
     */
    public function column_full_name($item) {
        $name = !empty($item['full_name']) ? esc_html($item['full_name']) : '—';

        $edit_url = add_query_arg([
            'page'    => 'akpp-users',
            'action'  => 'edit',
            'id'      => $item['id'],
            '_wpnonce' => wp_create_nonce('akpp_edit_user_' . $item['id'])
        ], admin_url('admin.php'));

        $actions = [
            'edit' => sprintf('<a href="%s">Редактировать</a>', esc_url($edit_url)),
            'wp_profile' => sprintf(
                '<a href="%s" target="_blank">Профиль WP</a>',
                esc_url(get_edit_user_link($item['wp_user_id']))
            )
        ];

        return sprintf('<strong>%1$s</strong> %2$s', $name, $this->row_actions($actions));
    }

    /**
     * Колонка Email
     */
    public function column_email($item) {
        if (!empty($item['user_email'])) {
            return '<a href="mailto:' . esc_attr($item['user_email']) . '">' . esc_html($item['user_email']) . '</a>';
        }
        return '—';
    }

    /**
     * Колонка Телефон
     */
    public function column_phone($item) {
        if (!empty($item['phone'])) {
            return '<a href="tel:' . esc_attr($item['phone']) . '">' . esc_html($item['phone']) . '</a>';
        }
        return '—';
    }

    /**
     * Колонка Автомобиль
     */
    public function column_car_info($item) {
        return !empty($item['car_info']) ? esc_html($item['car_info']) : '—';
    }

    /**
     * Колонка Дата регистрации
     */
    public function column_registered($item) {
        if (!empty($item['user_registered'])) {
            return date('d.m.Y H:i', strtotime($item['user_registered']));
        }
        return '—';
    }

    /**
     * Колонка Действия
     */
    public function column_actions($item) {
        $delete_url = wp_nonce_url(
            add_query_arg([
                'page'   => 'akpp-users',
                'action' => 'delete',
                'id'     => $item['id']
            ], admin_url('admin.php')),
            'akpp_delete_user_' . $item['id']
        );

        return sprintf(
            '<a href="%s" class="button button-small" target="_blank">Профиль</a> ' .
            '<a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'Удалить пользователя?\');">Удалить</a>',
            esc_url(get_author_posts_url($item['wp_user_id'])),
            esc_url($delete_url)
        );
    }

    /**
     * Значение по умолчанию для колонок
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
     * Обработка массовых и одиночных действий
     */
    public function process_bulk_action() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_site_users';

        // Одиночное удаление
        if ('delete' === $this->current_action() && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            check_admin_referer('akpp_delete_user_' . $id);

            // Получаем wp_user_id для удаления связанного пользователя WP
            $wp_user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT wp_user_id FROM $table_name WHERE id = %d", $id
            ));

            // Удаляем из таблицы site_users
            $wpdb->delete($table_name, ['id' => $id]);

            // Опционально: удалить пользователя WP (раскомментируйте если нужно)
            // if ($wp_user_id && function_exists('wp_delete_user')) {
            //     require_once(ABSPATH . 'wp-admin/includes/user.php');
            //     wp_delete_user($wp_user_id);
            // }

            wp_redirect(add_query_arg([
                'page'    => 'akpp-users',
                'deleted' => '1'
            ], admin_url('admin.php')));
            exit;
        }

        // Массовое удаление
        if ('delete' === $this->current_action() && isset($_POST['user']) && is_array($_POST['user'])) {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $ids = array_map('intval', $_POST['user']);
            $ids_str = implode(',', $ids);

            $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids_str)");

            wp_redirect(add_query_arg([
                'page'    => 'akpp-users',
                'deleted' => count($ids)
            ], admin_url('admin.php')));
            exit;
        }
    }

    /**
     * Сообщение при отсутствии данных
     */
    public function no_items() {
        _e('Зарегистрированных клиентов пока нет.', 'akpp-crm');
    }
}
