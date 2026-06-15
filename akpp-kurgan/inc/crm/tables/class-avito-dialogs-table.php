<?php
/**
 * АКПП45 CRM - Таблица диалогов Авито
 * WP_List_Table для управления входящими диалогами с Авито.
 *
 * @package AKPP_CRM
 * @version 4.2
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class AKPP_Avito_Dialogs_Table extends WP_List_Table {

    /**
     * Статусы диалогов
     */
    const STATUS_ACTIVE   = 'active';
    const STATUS_ARCHIVED = 'archived';
    const STATUS_CLOSED   = 'closed';

    /**
     * Конструктор таблицы
     */
    public function __construct() {
        parent::__construct([
            'singular' => __('Диалог', 'akpp-crm'),
            'plural'   => __('Диалоги', 'akpp-crm'),
            'ajax'     => false
        ]);
    }

    /**
     * Получение данных диалогов из БД с группировкой
     */
    public function get_dialogs() {
        global $wpdb;
        $messages_table = $wpdb->prefix . 'akpp_avito_messages_cache';
        $dialogs_table  = $wpdb->prefix . 'akpp_avito_dialogs';

        // Параметры пагинации
        $per_page     = $this->get_items_per_page('avito_dialogs_per_page', 20);
        $current_page = $this->get_pagenum();

        // Параметры фильтрации
        $status_filter = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : 'all';
        $unread_filter = isset($_REQUEST['unread']) ? intval($_REQUEST['unread']) : 0;
        $search        = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';

        // Параметры сортировки
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'last_message_date';
        $order   = isset($_REQUEST['order']) && $_REQUEST['order'] === 'asc' ? 'ASC' : 'DESC';

        // Построение WHERE условий
        $where_clauses = ["1=1"];
        $prepare_args  = [];

        // Фильтр по статусу
        if ($status_filter !== 'all' && in_array($status_filter, [self::STATUS_ACTIVE, self::STATUS_ARCHIVED, self::STATUS_CLOSED])) {
            $where_clauses[] = "d.status = %s";
            $prepare_args[] = $status_filter;
        } else {
            // По умолчанию показываем только активные
            $where_clauses[] = "d.status != %s";
            $prepare_args[] = self::STATUS_ARCHIVED;
        }

        // Фильтр только непрочитанные
        if ($unread_filter === 1) {
            $where_clauses[] = "d.unread_count > 0";
        }

        // Поиск по имени клиента
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = "(d.client_name LIKE %s OR d.client_phone LIKE %s)";
            $prepare_args[] = $search_like;
            $prepare_args[] = $search_like;
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Валидация orderby для защиты от SQL injection
        $allowed_orderby = [
            'id'                => 'd.id',
            'client_name'       => 'd.client_name',
            'last_message_date' => 'd.last_message_date',
            'unread_count'      => 'd.unread_count'
        ];
        $orderby_column = isset($allowed_orderby[$orderby]) ? $allowed_orderby[$orderby] : 'd.last_message_date';

        // Общее количество записей
        $count_query = "SELECT COUNT(*) FROM $dialogs_table d WHERE $where_sql";
        $total_items = empty($prepare_args)
            ? $wpdb->get_var($count_query)
            : $wpdb->get_var($wpdb->prepare($count_query, $prepare_args));

        // Получение данных для текущей страницы
        $offset = ($current_page - 1) * $per_page;
        $prepare_args[] = $per_page;
        $prepare_args[] = $offset;

        $query = "
            SELECT
                d.id,
                d.avito_item_id,
                d.client_id,
                d.client_name,
                d.client_phone,
                d.client_avatar,
                d.status,
                d.unread_count,
                d.last_message_id,
                d.last_message_date,
                d.last_message_text,
                d.last_message_direction,
                d.assigned_to,
                d.created_at,
                d.updated_at,
                i.title AS item_title,
                i.price AS item_price
            FROM $dialogs_table d
            LEFT JOIN {$wpdb->prefix}akpp_avito_items i ON d.avito_item_id = i.item_id
            WHERE $where_sql
            ORDER BY $orderby_column $order
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
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        // Обработка массовых действий
        $this->process_bulk_action();

        $this->items = $this->get_dialogs();
    }

    /**
     * Дополнительные представления (фильтры сверху)
     */
    public function get_views() {
        global $wpdb;
        $dialogs_table = $wpdb->prefix . 'akpp_avito_dialogs';

        $current_status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : 'active';

        // Подсчёт количества диалогов по статусам
        $all_count      = (int) $wpdb->get_var("SELECT COUNT(*) FROM $dialogs_table WHERE status != 'archived'");
        $active_count   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $dialogs_table WHERE status = %s", self::STATUS_ACTIVE));
        $unread_count   = (int) $wpdb->get_var("SELECT COUNT(*) FROM $dialogs_table WHERE status != 'archived' AND unread_count > 0");
        $archived_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $dialogs_table WHERE status = %s", self::STATUS_ARCHIVED));
        $closed_count   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $dialogs_table WHERE status = %s", self::STATUS_CLOSED));

        $base_url = admin_url('admin.php?page=akpp-avito-dialogs');

        $views = [
            'all'      => sprintf(
                '<a href="%s" class="%s">Все <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', 'all', $base_url)),
                $current_status === 'all' ? 'current' : '',
                $all_count
            ),
            'active'   => sprintf(
                '<a href="%s" class="%s">Активные <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', self::STATUS_ACTIVE, $base_url)),
                $current_status === self::STATUS_ACTIVE ? 'current' : '',
                $active_count
            ),
            'unread'   => sprintf(
                '<a href="%s" class="%s">Непрочитанные <span class="count">(%d)</span></a>',
                esc_url(add_query_arg(['status' => 'all', 'unread' => 1], $base_url)),
                (isset($_REQUEST['unread']) && $_REQUEST['unread'] == 1) ? 'current' : '',
                $unread_count
            ),
            'archived' => sprintf(
                '<a href="%s" class="%s">Архив <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', self::STATUS_ARCHIVED, $base_url)),
                $current_status === self::STATUS_ARCHIVED ? 'current' : '',
                $archived_count
            ),
            'closed'   => sprintf(
                '<a href="%s" class="%s">Закрытые <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', self::STATUS_CLOSED, $base_url)),
                $current_status === self::STATUS_CLOSED ? 'current' : '',
                $closed_count
            ),
        ];

        return $views;
    }

    /**
     * Определение колонок таблицы
     */
    public function get_columns() {
        return [
            'cb'                => '<input type="checkbox" />',
            'client_name'       => __('Клиент', 'akpp-crm'),
            'item_title'        => __('Объявление', 'akpp-crm'),
            'last_message_text' => __('Последнее сообщение', 'akpp-crm'),
            'last_message_date' => __('Дата', 'akpp-crm'),
            'unread_count'      => __('🔔', 'akpp-crm'),
            'assigned_to'       => __('Менеджер', 'akpp-crm'),
            'actions'           => __('Действия', 'akpp-crm')
        ];
    }

    /**
     * Сортируемые колонки
     */
    public function get_sortable_columns() {
        return [
            'client_name'       => ['client_name', false],
            'last_message_date' => ['last_message_date', true],
            'unread_count'      => ['unread_count', false]
        ];
    }

    /**
     * Колонка с чекбоксом
     */
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="dialog[]" value="%s" />', $item['id']);
    }

    /**
     * Колонка Клиент (с аватаром и именем)
     */
    public function column_client_name($item) {
        $avatar = !empty($item['client_avatar'])
            ? '<img src="' . esc_url($item['client_avatar']) . '" class="avito-avatar" style="width:32px;height:32px;border-radius:50%;margin-right:8px;vertical-align:middle;">'
            : '<span style="display:inline-block;width:32px;height:32px;border-radius:50%;background:#00ff88;color:#0a0f1c;text-align:center;line-height:32px;margin-right:8px;vertical-align:middle;font-weight:bold;">' . esc_html(mb_substr($item['client_name'], 0, 1)) . '</span>';

        $name = !empty($item['client_name']) ? esc_html($item['client_name']) : 'Аноним #' . $item['client_id'];
        $phone = !empty($item['client_phone']) ? '<br><small style="color:#888;">' . esc_html($item['client_phone']) . '</small>' : '';

        return '<div style="display:flex;align-items:center;">' . $avatar . '<div><strong>' . $name . '</strong>' . $phone . '</div></div>';
    }

    /**
     * Колонка Объявление
     */
    public function column_item_title($item) {
        if (empty($item['item_title'])) {
            return '<span style="color:#888;">—</span>';
        }

        $price = !empty($item['item_price']) ? '<br><small style="color:#00ff88;">' . number_format($item['item_price'], 0, ',', ' ') . ' ₽</small>' : '';

        return '<div style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' . esc_attr($item['item_title']) . '">' . esc_html($item['item_title']) . $price . '</div>';
    }

    /**
     * Колонка Последнее сообщение
     */
    public function column_last_message_text($item) {
        if (empty($item['last_message_text'])) {
            return '<span style="color:#888;">Нет сообщений</span>';
        }

        $direction_icon = '';
        if ($item['last_message_direction'] === 'outgoing') {
            $direction_icon = '<span style="color:#00ff88;margin-right:4px;">➤</span>';
        } else {
            $direction_icon = '<span style="color:#ffaa00;margin-right:4px;">⬅</span>';
        }

        $text = mb_substr($item['last_message_text'], 0, 80);
        if (mb_strlen($item['last_message_text']) > 80) {
            $text .= '...';
        }

        $style = $item['unread_count'] > 0 ? 'font-weight:bold;color:#fff;' : 'color:#aaa;';

        return '<div style="' . $style . 'max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' . $direction_icon . esc_html($text) . '</div>';
    }

    /**
     * Колонка Дата
     */
    public function column_last_message_date($item) {
        if (empty($item['last_message_date'])) {
            return '—';
        }

        $timestamp = strtotime($item['last_message_date']);
        $now       = current_time('timestamp');
        $diff      = $now - $timestamp;

        // Форматирование "X минут/часов/дней назад"
        if ($diff < 60) {
            $time_ago = 'только что';
        } elseif ($diff < 3600) {
            $time_ago = floor($diff / 60) . ' мин назад';
        } elseif ($diff < 86400) {
            $time_ago = floor($diff / 3600) . ' ч назад';
        } elseif ($diff < 604800) {
            $time_ago = floor($diff / 86400) . ' дн назад';
        } else {
            $time_ago = date('d.m.Y', $timestamp);
        }

        return '<span title="' . date('d.m.Y H:i:s', $timestamp) . '">' . $time_ago . '</span>';
    }

    /**
     * Колонка Непрочитанные
     */
    public function column_unread_count($item) {
        if ($item['unread_count'] > 0) {
            return '<span style="display:inline-block;background:#00ff88;color:#0a0f1c;padding:2px 8px;border-radius:10px;font-weight:bold;font-size:12px;">' . intval($item['unread_count']) . '</span>';
        }
        return '';
    }

    /**
     * Колонка Менеджер
     */
    public function column_assigned_to($item) {
        if (empty($item['assigned_to'])) {
            return '<span style="color:#ff4444;">Не назначен</span>';
        }

        // Получаем имя сотрудника
        global $wpdb;
        $employees_table = $wpdb->prefix . 'akpp_employees';
        $employee_name   = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM $employees_table WHERE id = %d", $item['assigned_to']
        ));

        return $employee_name ? esc_html($employee_name) : 'ID: ' . intval($item['assigned_to']);
    }

    /**
     * Колонка Действия
     */
    public function column_actions($item) {
        $chat_url = add_query_arg([
            'page'      => 'akpp-avito-chat',
            'dialog_id' => $item['id']
        ], admin_url('admin.php'));

        $archive_url = wp_nonce_url(
            add_query_arg([
                'page'   => 'akpp-avito-dialogs',
                'action' => 'archive',
                'id'     => $item['id']
            ], admin_url('admin.php')),
            'akpp_archive_dialog_' . $item['id']
        );

        $delete_url = wp_nonce_url(
            add_query_arg([
                'page'   => 'akpp-avito-dialogs',
                'action' => 'delete',
                'id'     => $item['id']
            ], admin_url('admin.php')),
            'akpp_delete_dialog_' . $item['id']
        );

        $actions = [];

        if ($item['status'] !== self::STATUS_ARCHIVED) {
            $actions['open']   = sprintf('<a href="%s" class="button button-small button-primary">💬 Открыть</a>', esc_url($chat_url));
            $actions['archive'] = sprintf('<a href="%s" class="button button-small" onclick="return confirm(\'Архивировать диалог?\');">📦 В архив</a>', esc_url($archive_url));
        } else {
            $actions['restore'] = sprintf('<a href="%s" class="button button-small">♻️ Восстановить</a>', esc_url(wp_nonce_url(add_query_arg(['action' => 'restore', 'id' => $item['id']], admin_url('admin.php')), 'akpp_restore_dialog_' . $item['id'])));
        }

        $actions['delete'] = sprintf('<a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'Удалить диалог и все сообщения? Это действие необратимо!\');">🗑️</a>', esc_url($delete_url));

        return implode(' ', $actions);
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
            'archive' => __('📦 Архивировать', 'akpp-crm'),
            'restore' => __('♻️ Восстановить', 'akpp-crm'),
            'delete'  => __('🗑️ Удалить', 'akpp-crm')
        ];
    }

    /**
     * Обработка массовых и одиночных действий
     */
    public function process_bulk_action() {
        global $wpdb;
        $dialogs_table  = $wpdb->prefix . 'akpp_avito_dialogs';
        $messages_table = $wpdb->prefix . 'akpp_avito_messages_cache';

        // Одиночные действия
        if (in_array($this->current_action(), ['archive', 'restore', 'delete']) && isset($_GET['id'])) {
            $id = intval($_GET['id']);

            // Проверка nonce
            $nonce_action = 'akpp_' . $this->current_action() . '_dialog_' . $id;
            check_admin_referer($nonce_action);

            $this->process_dialog_action($this->current_action(), [$id], $dialogs_table, $messages_table);

            wp_redirect(add_query_arg([
                'page'    => 'akpp-avito-dialogs',
                'updated' => '1'
            ], admin_url('admin.php')));
            exit;
        }

        // Массовые действия
        if (in_array($this->current_action(), ['archive', 'restore', 'delete']) && isset($_POST['dialog']) && is_array($_POST['dialog'])) {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $ids = array_map('intval', $_POST['dialog']);

            $this->process_dialog_action($this->current_action(), $ids, $dialogs_table, $messages_table);

            wp_redirect(add_query_arg([
                'page'    => 'akpp-avito-dialogs',
                'updated' => count($ids)
            ], admin_url('admin.php')));
            exit;
        }
    }

    /**
     * Выполнение действия над диалогами
     */
    private function process_dialog_action($action, $ids, $dialogs_table, $messages_table) {
        global $wpdb;
        $ids_str = implode(',', array_map('intval', $ids));

        switch ($action) {
            case 'archive':
                $wpdb->query("UPDATE $dialogs_table SET status = '" . self::STATUS_ARCHIVED . "' WHERE id IN ($ids_str)");
                break;

            case 'restore':
                $wpdb->query("UPDATE $dialogs_table SET status = '" . self::STATUS_ACTIVE . "' WHERE id IN ($ids_str)");
                break;

            case 'delete':
                // Сначала удаляем все сообщения диалога
                $wpdb->query("DELETE FROM $messages_table WHERE dialog_id IN ($ids_str)");
                // Затем удаляем сами диалоги
                $wpdb->query("DELETE FROM $dialogs_table WHERE id IN ($ids_str)");
                break;
        }
    }

    /**
     * Сообщение при отсутствии данных
     */
    public function no_items() {
        _e('Диалогов с Авито пока нет. Они появятся после настройки интеграции и получения первых сообщений.', 'akpp-crm');
    }

    /**
     * Дополнительные элементы управления сверху (фильтры)
     */
    public function extra_tablenav($which) {
        if ($which !== 'top') return;

        // Поиск
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        ?>
        <div class="alignleft actions">
            <label for="avito-search" class="screen-reader-text">Поиск по клиенту</label>
            <input type="search" id="avito-search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="🔍 Поиск по имени или телефону...">
            <input type="submit" class="button" value="Найти">
        </div>
        <?php
    }
}
