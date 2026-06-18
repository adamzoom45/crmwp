<?php
/**
 * Класс для таблицы сделок в админке
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AKPP_Deals_Table extends WP_List_Table {
    
    private $table_name;
    
    public function __construct() {
        parent::__construct([
            'singular' => 'deal',
            'plural' => 'deals',
            'ajax' => false
        ]);
        
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'akpp_deals';
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
        $employee_filter = isset($_GET['employee_filter']) ? intval($_GET['employee_filter']) : 0;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Построение WHERE
        $where = [];
        $params = [];
        
        if ($status_filter !== 'all') {
            $where[] = "status = %s";
            $params[] = $status_filter;
        }
        
        if ($employee_filter > 0) {
            $where[] = "employee_id = %d";
            $params[] = $employee_filter;
        }
        
        if (!empty($search)) {
            $where[] = "(make LIKE '%%%s%%' OR model LIKE '%%%s%%' OR vin LIKE '%%%s%%')";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Сортировка
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Получение общего количества
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, ...$params);
        }
        $total_items = $wpdb->get_var($count_query);
        
        // Получение данных
        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        $query = $wpdb->prepare($query, ...$params);
        $this->items = $wpdb->get_results($query);
        
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
            'id' => 'ID',
            'client' => 'Клиент',
            'vehicle' => 'Автомобиль',
            'employee' => 'Сотрудник',
            'total_amount' => 'Сумма',
            'status' => 'Статус',
            'created_at' => 'Дата',
            'actions' => 'Действия'
        ];
    }
    
    /**
     * Сортируемые колонки
     */
    public function get_sortable_columns() {
        return [
            'id' => ['id', false],
            'total_amount' => ['total_amount', false],
            'created_at' => ['created_at', true],
            'status' => ['status', false]
        ];
    }
    
    /**
     * Массовые действия
     */
    public function get_bulk_actions() {
        return [
            'delete' => 'Удалить',
            'change_status_new' => 'Изменить статус на "Новая"',
            'change_status_diagnostic' => 'Изменить статус на "Диагностика"',
            'change_status_in_work' => 'Изменить статус на "В работе"',
            'change_status_completed' => 'Изменить статус на "Выполнена"',
            'change_status_rejected' => 'Изменить статус на "Отклонена"'
        ];
    }
    
    /**
     * Обработка массовых действий
     */
    public function process_bulk_action() {
        global $wpdb;
        
        if (!$this->current_action()) return;
        
        $deal_ids = isset($_GET['deal']) ? array_map('intval', $_GET['deal']) : [];
        
        if (empty($deal_ids)) return;
        
        $ids_placeholder = implode(',', array_fill(0, count($deal_ids), '%d'));
        
        switch ($this->current_action()) {
            case 'delete':
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
                    $deal_ids
                ));
                echo '<div class="notice notice-success"><p>Сделки удалены</p></div>';
                break;
                
            case 'change_status_new':
                $this->bulk_update_status($deal_ids, 'new');
                break;
                
            case 'change_status_diagnostic':
                $this->bulk_update_status($deal_ids, 'diagnostic');
                break;
                
            case 'change_status_in_work':
                $this->bulk_update_status($deal_ids, 'in_work');
                break;
                
            case 'change_status_completed':
                $this->bulk_update_status($deal_ids, 'completed');
                break;
                
            case 'change_status_rejected':
                $this->bulk_update_status($deal_ids, 'rejected');
                break;
        }
    }
    
    /**
     * Массовое обновление статуса
     */
    private function bulk_update_status($ids, $status) {
        global $wpdb;
        
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} SET status = %s, updated_at = %s WHERE id IN ({$ids_placeholder})",
            $status,
            current_time('mysql'),
            ...$ids
        ));
        
        echo '<div class="notice notice-success"><p>Статус обновлен для ' . count($ids) . ' сделок</p></div>';
    }
    
    /**
     * Отображение колонки cb (чекбокс)
     */
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="deal[]" value="%s" />', $item->id);
    }
    
    /**
     * Отображение колонки ID
     */
    protected function column_id($item) {
        return sprintf(
            '<a href="?page=akpp-crm-deals&action=view&id=%d">#%d</a>',
            $item->id,
            $item->id
        );
    }
    
    /**
     * Отображение колонки клиента
     */
    protected function column_client($item) {
        global $wpdb;
        
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT name, phone FROM {$wpdb->prefix}akpp_site_users WHERE id = %d",
            $item->client_id
        ));
        
        if ($client) {
            return sprintf(
                '<strong>%s</strong><br><small>%s</small>',
                esc_html($client->name),
                esc_html($client->phone)
            );
        }
        
        return '—';
    }
    
    /**
     * Отображение колонки автомобиля
     */
    protected function column_vehicle($item) {
        $vehicle = '';
        if ($item->make) {
            $vehicle .= esc_html($item->make);
        }
        if ($item->model) {
            $vehicle .= ' ' . esc_html($item->model);
        }
        if ($item->year) {
            $vehicle .= ', ' . $item->year;
        }
        if ($item->vin) {
            $vehicle .= '<br><small>VIN: ' . esc_html($item->vin) . '</small>';
        }
        
        return !empty($vehicle) ? $vehicle : '—';
    }
    
    /**
     * Отображение колонки сотрудника
     */
    protected function column_employee($item) {
        global $wpdb;
        
        if (!$item->employee_id) {
            return '—';
        }
        
        $employee = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}akpp_employees WHERE id = %d",
            $item->employee_id
        ));
        
        return $employee ? esc_html($employee->name) : '—';
    }
    
    /**
     * Отображение колонки суммы
     */
    protected function column_total_amount($item) {
        return number_format($item->total_amount, 0, ',', ' ') . ' ₽';
    }
    
    /**
     * Отображение колонки статуса
     */
    protected function column_status($item) {
        $statuses = [
            'new' => ['label' => '🆕 Новая', 'class' => 'status-new'],
            'diagnostic' => ['label' => '🔧 Диагностика', 'class' => 'status-diagnostic'],
            'in_work' => ['label' => '⚙️ В работе', 'class' => 'status-in_work'],
            'completed' => ['label' => '✅ Выполнена', 'class' => 'status-completed'],
            'rejected' => ['label' => '❌ Отклонена', 'class' => 'status-rejected']
        ];
        
        $status = $statuses[$item->status] ?? ['label' => $item->status, 'class' => ''];
        
        return sprintf(
            '<span class="status-badge %s">%s</span>',
            esc_attr($status['class']),
            esc_html($status['label'])
        );
    }
    
    /**
     * Отображение колонки даты
     */
    protected function column_created_at($item) {
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at));
    }
    
    /**
     * Отображение колонки действий
     */
    protected function column_actions($item) {
        $actions = sprintf(
            '<a href="?page=akpp-crm-deal-form&id=%d" class="button button-small">✏️ Редактировать</a> ',
            $item->id
        );
        
        $actions .= sprintf(
            '<button class="button button-small delete-deal" data-id="%d">🗑️ Удалить</button>',
            $item->id
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
        
        global $wpdb;
        
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $employee_filter = isset($_GET['employee_filter']) ? intval($_GET['employee_filter']) : 0;
        
        // Фильтр по статусу
        ?>
        <div class="alignleft actions">
            <select name="status_filter">
                <option value="all" <?php selected($status_filter, 'all'); ?>>Все статусы</option>
                <option value="new" <?php selected($status_filter, 'new'); ?>>🆕 Новая</option>
                <option value="diagnostic" <?php selected($status_filter, 'diagnostic'); ?>>🔧 Диагностика</option>
                <option value="in_work" <?php selected($status_filter, 'in_work'); ?>>⚙️ В работе</option>
                <option value="completed" <?php selected($status_filter, 'completed'); ?>>✅ Выполнена</option>
                <option value="rejected" <?php selected($status_filter, 'rejected'); ?>>❌ Отклонена</option>
            </select>
            
            <?php
            // Фильтр по сотрудникам
            $employees = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}akpp_employees ORDER BY name ASC");
            if (!empty($employees)) :
            ?>
            <select name="employee_filter">
                <option value="0" <?php selected($employee_filter, 0); ?>>Все сотрудники</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?php echo $emp->id; ?>" <?php selected($employee_filter, $emp->id); ?>>
                        <?php echo esc_html($emp->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            
            <input type="submit" name="filter_action" class="button" value="Фильтровать">
        </div>
        <?php
    }
    
    /**
     * Отображение, если нет данных
     */
    public function no_items() {
        echo 'Нет сделок для отображения';
    }
}
