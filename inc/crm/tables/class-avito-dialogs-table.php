<?php
/**
 * Класс для таблицы диалогов Авито в админке
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AKPP_Avito_Dialogs_Table extends WP_List_Table {
    
    private $table_name;
    private $messages_table;
    
    public function __construct() {
        parent::__construct([
            'singular' => 'dialog',
            'plural' => 'dialogs',
            'ajax' => false
        ]);
        
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'akpp_avito_dialogs';
        $this->messages_table = $wpdb->prefix . 'akpp_avito_messages_cache';
    }
    
    /**
     * Получение данных для таблицы
     */
    public function prepare_items() {
        global $wpdb;
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Фильтры
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Построение WHERE
        $where = [];
        $params = [];
        
        if ($status_filter !== 'all') {
            $is_active = $status_filter === 'active' ? 1 : 0;
            $where[] = "is_active = %d";
            $params[] = $is_active;
        }
        
        if (!empty($search)) {
            $where[] = "(user_name LIKE '%%%s%%' OR user_phone LIKE '%%%s%%' OR dialog_id LIKE '%%%s%%')";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Сортировка
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'last_message_time';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Получение общего количества
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, $params);
        }
        $total_items = $wpdb->get_var($count_query);
        
        // Получение данных
        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        $query = $wpdb->prepare($query, $params);
        $this->items = $wpdb->get_results($query);
        
        // Добавляем количество непрочитанных сообщений
        foreach ($this->items as $item) {
            $item->unread_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->messages_table} 
                WHERE dialog_id = %s AND is_read = 0 AND is_incoming = 1",
                $item->dialog_id
            ));
        }
        
        // Настройка пагинации
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
        
        $this->process_bulk_action();
    }
    
    /**
     * Определение колонок
     */
    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'user_name' => 'Пользователь',
            'user_phone' => 'Телефон',
            'last_message' => 'Последнее сообщение',
            'last_message_time' => 'Время',
            'unread_count' => 'Непрочитанные',
            'status' => 'Статус',
            'created_at' => 'Создан',
            'actions' => 'Действия'
        ];
    }
    
    /**
     * Сортируемые колонки
     */
    public function get_sortable_columns() {
        return [
            'user_name' => ['user_name', true],
            'last_message_time' => ['last_message_time', true],
            'created_at' => ['created_at', false]
        ];
    }
    
    /**
     * Массовые действия
     */
    public function get_bulk_actions() {
        return [
            'mark_read' => 'Отметить прочитанными',
            'close' => 'Закрыть диалоги',
            'delete' => 'Удалить'
        ];
    }
    
    /**
     * Обработка массовых действий
     */
    public function process_bulk_action() {
        global $wpdb;
        
        if (!$this->current_action()) return;
        
        $dialog_ids = isset($_GET['dialog']) ? array_map('sanitize_text_field', $_GET['dialog']) : [];
        
        if (empty($dialog_ids)) return;
        
        $ids_placeholder = implode(',', array_fill(0, count($dialog_ids), '%s'));
        
        switch ($this->current_action()) {
            case 'mark_read':
                foreach ($dialog_ids as $dialog_id) {
                    $wpdb->update(
                        $this->messages_table,
                        ['is_read' => 1],
                        [
                            'dialog_id' => $dialog_id,
                            'is_incoming' => 1,
                            'is_read' => 0
                        ]
                    );
                }
                echo '<div class="notice notice-success"><p>Сообщения отмечены прочитанными</p></div>';
                break;
                
            case 'close':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->table_name} SET is_active = 0 WHERE dialog_id IN ({$ids_placeholder})",
                    $dialog_ids
                ));
                echo '<div class="notice notice-success"><p>Диалоги закрыты</p></div>';
                break;
                
            case 'delete':
                foreach ($dialog_ids as $dialog_id) {
                    $wpdb->delete($this->messages_table, ['dialog_id' => $dialog_id]);
                    $wpdb->delete($this->table_name, ['dialog_id' => $dialog_id]);
                }
                echo '<div class="notice notice-success"><p>Диалоги удалены</p></div>';
                break;
        }
    }
    
    /**
     * Отображение колонки cb (чекбокс)
     */
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="dialog[]" value="%s" />', $item->dialog_id);
    }
    
    /**
     * Отображение колонки пользователя
     */
    protected function column_user_name($item) {
        $name = !empty($item->user_name) ? esc_html($item->user_name) : 'Пользователь Авито';
        
        return sprintf(
            '<strong>%s</strong><br><small>ID: %s</small>',
            $name,
            esc_html($item->user_id)
        );
    }
    
    /**
     * Отображение колонки телефона
     */
    protected function column_user_phone($item) {
        return !empty($item->user_phone) ? esc_html($item->user_phone) : '—';
    }
    
    /**
     * Отображение колонки последнего сообщения
     */
    protected function column_last_message($item) {
        $message = !empty($item->last_message) ? esc_html(mb_substr($item->last_message, 0, 60)) : '—';
        
        if (mb_strlen($item->last_message) > 60) {
            $message .= '...';
        }
        
        return $message;
    }
    
    /**
     * Отображение колонки времени
     */
    protected function column_last_message_time($item) {
        if ($item->last_message_time) {
            return date_i18n('d.m.Y H:i', strtotime($item->last_message_time));
        }
        return '—';
    }
    
    /**
     * Отображение колонки непрочитанных
     */
    protected function column_unread_count($item) {
        $count = intval($item->unread_count);
        
        if ($count > 0) {
            return sprintf(
                '<span class="unread-badge">%s</span>',
                $count > 9 ? '9+' : $count
            );
        }
        
        return '0';
    }
    
    /**
     * Отображение колонки статуса
     */
    protected function column_status($item) {
        if ($item->is_active) {
            return '<span class="status-badge status-active">🟢 Активен</span>';
        } else {
            return '<span class="status-badge status-inactive">🔴 Закрыт</span>';
        }
    }
    
    /**
     * Отображение колонки даты создания
     */
    protected function column_created_at($item) {
        return date_i18n('d.m.Y', strtotime($item->created_at));
    }
    
    /**
     * Отображение колонки действий
     */
    protected function column_actions($item) {
        $actions = sprintf(
            '<a href="?page=akpp-crm-avito-dialogs&dialog=%s" class="button button-small">💬 Чат</a> ',
            urlencode($item->dialog_id)
        );
        
        if ($item->is_active) {
            $actions .= sprintf(
                '<button class="button button-small close-dialog" data-dialog="%s">🔒 Закрыть</button> ',
                esc_attr($item->dialog_id)
            );
        }
        
        $actions .= sprintf(
            '<button class="button button-small delete-dialog" data-dialog="%s">🗑️</button>',
            esc_attr($item->dialog_id)
        );
        
        return $actions;
    }
    
    /**
     * Отображение по умолчанию для неизвестных колонок
     */
    protected function column_default($item, $column_name) {
        return isset($item->$column_name) ? esc_html($item->$column_name) : '—';
    }
    
    /**
     * Фильтры над таблицей
     */
    protected function extra_tablenav($which) {
        if ($which !== 'top') return;
        
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        ?>
        <div class="alignleft actions">
            <select name="status_filter">
                <option value="all" <?php selected($status_filter, 'all'); ?>>Все диалоги</option>
                <option value="active" <?php selected($status_filter, 'active'); ?>>Активные</option>
                <option value="closed" <?php selected($status_filter, 'closed'); ?>>Закрытые</option>
            </select>
            
            <input type="submit" name="filter_action" class="button" value="Фильтровать">
            <a href="?page=akpp-crm-avito-dialogs&tab=settings" class="button">⚙️ Настройки Авито</a>
        </div>
        <?php
    }
    
    /**
     * Отображение, если нет данных
     */
    public function no_items() {
        echo 'Нет диалогов для отображения';
    }
}
