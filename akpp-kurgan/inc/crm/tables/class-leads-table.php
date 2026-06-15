<?php
/**
 * Класс для таблицы лидов в админке
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AKPP_Leads_Table extends WP_List_Table {
    
    private $table_name;
    
    public function __construct() {
        parent::__construct([
            'singular' => 'lead',
            'plural' => 'leads',
            'ajax' => false
        ]);
        
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'akpp_leads';
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
        $source_filter = isset($_GET['source_filter']) ? sanitize_text_field($_GET['source_filter']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Построение WHERE
        $where = [];
        $params = [];
        
        if ($status_filter !== 'all') {
            $where[] = "status = %s";
            $params[] = $status_filter;
        }
        
        if ($source_filter !== 'all') {
            $where[] = "source = %s";
            $params[] = $source_filter;
        }
        
        if (!empty($search)) {
            $where[] = "(client_name LIKE '%%%s%%' OR client_phone LIKE '%%%s%%' OR client_email LIKE '%%%s%%')";
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
            $count_query = $wpdb->prepare($count_query, $params);
        }
        $total_items = $wpdb->get_var($count_query);
        
        // Получение данных
        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        $query = $wpdb->prepare($query, $params);
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
            'client_name' => 'Клиент',
            'contact' => 'Контакты',
            'car_brand' => 'Автомобиль',
            'source' => 'Источник',
            'guide' => 'Гид',
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
            'client_name' => ['client_name', false],
            'created_at' => ['created_at', true],
            'status' => ['status', false],
            'source' => ['source', false]
        ];
    }
    
    /**
     * Массовые действия
     */
    public function get_bulk_actions() {
        return [
            'delete' => 'Удалить',
            'change_status_new' => 'Изменить статус на "Новый"',
            'change_status_contacted' => 'Изменить статус на "Связались"',
            'change_status_converted' => 'Изменить статус на "Конвертирован"',
            'change_status_lost' => 'Изменить статус на "Потерян"'
        ];
    }
    
    /**
     * Обработка массовых действий
     */
    public function process_bulk_action() {
        global $wpdb;
        
        if (!$this->current_action()) return;
        
        $lead_ids = isset($_GET['lead']) ? array_map('intval', $_GET['lead']) : [];
        
        if (empty($lead_ids)) return;
        
        switch ($this->current_action()) {
            case 'delete':
                $ids_placeholder = implode(',', array_fill(0, count($lead_ids), '%d'));
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
                    $lead_ids
                ));
                echo '<div class="notice notice-success"><p>Лиды удалены</p></div>';
                break;
                
            case 'change_status_new':
                $this->bulk_update_status($lead_ids, 'new');
                break;
                
            case 'change_status_contacted':
                $this->bulk_update_status($lead_ids, 'contacted');
                break;
                
            case 'change_status_converted':
                $this->bulk_update_status($lead_ids, 'converted');
                break;
                
            case 'change_status_lost':
                $this->bulk_update_status($lead_ids, 'lost');
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
        
        echo '<div class="notice notice-success"><p>Статус обновлен для ' . count($ids) . ' лидов</p></div>';
    }
    
    /**
     * Отображение колонки cb (чекбокс)
     */
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="lead[]" value="%s" />', $item->id);
    }
    
    /**
     * Отображение колонки ID
     */
    protected function column_id($item) {
        return sprintf(
            '<a href="?page=akpp-crm-leads&action=view&id=%d">#%d</a>',
            $item->id,
            $item->id
        );
    }
    
    /**
     * Отображение колонки клиента
     */
    protected function column_client_name($item) {
        $name = esc_html($item->client_name);
        
        // Если есть сделка, показываем ссылку
        if ($item->deal_id) {
            return sprintf(
                '<strong>%s</strong><br><small>Сделка #%d</small>',
                $name,
                $item->deal_id
            );
        }
        
        return $name;
    }
    
    /**
     * Отображение колонки контактов
     */
    protected function column_contact($item) {
        $contact = '';
        
        if ($item->client_phone) {
            $contact .= '<div>📞 ' . esc_html($item->client_phone) . '</div>';
        }
        if ($item->client_email) {
            $contact .= '<div>✉️ ' . esc_html($item->client_email) . '</div>';
        }
        
        return !empty($contact) ? $contact : '—';
    }
    
    /**
     * Отображение колонки автомобиля
     */
    protected function column_car_brand($item) {
        return !empty($item->car_brand) ? esc_html($item->car_brand) : '—';
    }
    
    /**
     * Отображение колонки источника
     */
    protected function column_source($item) {
        $sources = [
            'site_form' => '🌐 Форма на сайте',
            'avito' => '📱 Авито',
            'telegram' => '📨 Telegram',
            'phone' => '📞 Телефон',
            'manual' => '✍️ Вручную'
        ];
        
        $source_label = $sources[$item->source] ?? $item->source;
        
        // Для Авито показываем диалог
        if ($item->source === 'avito' && $item->avito_dialog_id) {
            $source_label .= sprintf(
                '<br><small><a href="?page=akpp-crm-avito-dialogs&dialog=%s">Диалог</a></small>',
                urlencode($item->avito_dialog_id)
            );
        }
        
        return $source_label;
    }
    
    /**
     * Отображение колонки гида
     */
    protected function column_guide($item) {
        global $wpdb;
        
        if (!$item->guide_id) {
            return '—';
        }
        
        $guide = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}akpp_employees WHERE id = %d",
            $item->guide_id
        ));
        
        return $guide ? esc_html($guide) : '—';
    }
    
    /**
     * Отображение колонки статуса
     */
    protected function column_status($item) {
        $statuses = [
            'new' => ['label' => '🆕 Новый', 'class' => 'status-new'],
            'contacted' => ['label' => '📞 Связались', 'class' => 'status-contacted'],
            'converted' => ['label' => '✅ Конвертирован', 'class' => 'status-converted'],
            'lost' => ['label' => '❌ Потерян', 'class' => 'status-lost']
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
            '<button class="button button-small view-lead" data-id="%d">👁️ Просмотр</button> ',
            $item->id
        );
        
        if (!$item->deal_id) {
            $actions .= sprintf(
                '<a href="?page=akpp-crm-deal-form&lead_id=%d" class="button button-small">➕ В сделку</a> ',
                $item->id
            );
        }
        
        $actions .= sprintf(
            '<button class="button button-small delete-lead" data-id="%d">🗑️ Удалить</button>',
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
        
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $source_filter = isset($_GET['source_filter']) ? sanitize_text_field($_GET['source_filter']) : 'all';
        ?>
        <div class="alignleft actions">
            <select name="status_filter">
                <option value="all" <?php selected($status_filter, 'all'); ?>>Все статусы</option>
                <option value="new" <?php selected($status_filter, 'new'); ?>>🆕 Новый</option>
                <option value="contacted" <?php selected($status_filter, 'contacted'); ?>>📞 Связались</option>
                <option value="converted" <?php selected($status_filter, 'converted'); ?>>✅ Конвертирован</option>
                <option value="lost" <?php selected($status_filter, 'lost'); ?>>❌ Потерян</option>
            </select>
            
            <select name="source_filter">
                <option value="all" <?php selected($source_filter, 'all'); ?>>Все источники</option>
                <option value="site_form" <?php selected($source_filter, 'site_form'); ?>>🌐 Форма на сайте</option>
                <option value="avito" <?php selected($source_filter, 'avito'); ?>>📱 Авито</option>
                <option value="telegram" <?php selected($source_filter, 'telegram'); ?>>📨 Telegram</option>
                <option value="phone" <?php selected($source_filter, 'phone'); ?>>📞 Телефон</option>
                <option value="manual" <?php selected($source_filter, 'manual'); ?>>✍️ Вручную</option>
            </select>
            
            <input type="submit" name="filter_action" class="button" value="Фильтровать">
        </div>
        <?php
    }
    
    /**
     * Отображение, если нет данных
     */
    public function no_items() {
        echo 'Нет лидов для отображения';
    }
}
