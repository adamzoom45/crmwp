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
            'plural'   => 'leads',
            'ajax'     => false
        ]);
        
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'akpp_leads';
    }
    
    public function prepare_items() {
        global $wpdb;
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $source_filter = isset($_GET['source_filter']) ? sanitize_text_field($_GET['source_filter']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
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
            $where[] = "(client_name LIKE %s OR client_phone LIKE %s OR car_brand LIKE %s OR problem LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $allowed_orderby = ['id', 'client_name', 'created_at', 'status', 'source'];
        $orderby = isset($_GET['orderby']) && in_array($_GET['orderby'], $allowed_orderby) 
            ? sanitize_text_field($_GET['orderby']) 
            : 'created_at';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, ...$params);
        }
        $total_items = $wpdb->get_var($count_query);
        
        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $query_params = array_merge($params, [$per_page, $offset]);
        $query = $wpdb->prepare($query, ...$query_params);
        
        $this->items = $wpdb->get_results($query, ARRAY_A);
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
        
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns()
        ];
        
        $this->process_bulk_action();
    }
    
    public function get_columns() {
        return [
            'cb'           => '<input type="checkbox" />',
            'id'           => 'ID',
            'client_name'  => 'Клиент',
            'client_phone' => 'Телефон',
            'car_brand'    => 'Автомобиль',
            'problem'      => 'Проблема',
            'source'       => 'Источник',
            'status'       => 'Статус',
            'created_at'   => 'Дата',
            'actions'      => 'Действия',
        ];
    }
    
    public function get_sortable_columns() {
        return [
            'id'         => ['id', false],
            'client_name'=> ['client_name', false],
            'created_at' => ['created_at', true],
            'status'     => ['status', false],
            'source'     => ['source', false]
        ];
    }
    
    public function get_bulk_actions() {
        return [
            'delete'            => '🗑️ Удалить выбранные',
            'change_status_new' => '🆕 Статус: Новый',
            'change_status_work'=> '🔧 Статус: В работе',
            'change_status_done'=> '✅ Статус: Выполнено',
        ];
    }
    
    public function process_bulk_action() {
        global $wpdb;
        
        $action = $this->current_action();
        if (!$action) return;
        
        check_admin_referer('bulk-' . $this->_args['plural']);
        
        $lead_ids = isset($_REQUEST['lead']) ? array_map('intval', (array)$_REQUEST['lead']) : [];
        if (empty($lead_ids)) return;
        
        $ids_placeholder = implode(',', array_fill(0, count($lead_ids), '%d'));
        
        switch ($action) {
            case 'delete':
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
                    ...$lead_ids
                ));
                echo '<div class="notice notice-success is-dismissible"><p>✅ Удалено лидов: ' . count($lead_ids) . '</p></div>';
                break;
                
            case 'change_status_new':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->table_name} SET status = 'new', updated_at = %s WHERE id IN ({$ids_placeholder})",
                    current_time('mysql'),
                    ...$lead_ids
                ));
                echo '<div class="notice notice-success is-dismissible"><p>✅ Статус изменён на "Новый" для ' . count($lead_ids) . ' лидов</p></div>';
                break;
                
            case 'change_status_work':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->table_name} SET status = 'in_work', updated_at = %s WHERE id IN ({$ids_placeholder})",
                    current_time('mysql'),
                    ...$lead_ids
                ));
                echo '<div class="notice notice-success is-dismissible"><p>✅ Статус изменён на "В работе" для ' . count($lead_ids) . ' лидов</p></div>';
                break;
                
            case 'change_status_done':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->table_name} SET status = 'completed', updated_at = %s WHERE id IN ({$ids_placeholder})",
                    current_time('mysql'),
                    ...$lead_ids
                ));
                echo '<div class="notice notice-success is-dismissible"><p>✅ Статус изменён на "Выполнено" для ' . count($lead_ids) . ' лидов</p></div>';
                break;
        }
    }
    
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="lead[]" value="%s" />', esc_attr($item['id']));
    }
    
    protected function column_id($item) {
        return sprintf('#%d', $item['id']);
    }
    
    protected function column_client_name($item) {
        $name = esc_html($item['client_name'] ?? '—');
        return "<strong>{$name}</strong>";
    }
    
    protected function column_client_phone($item) {
        $phone = $item['client_phone'] ?? '';
        if (empty($phone)) return '—';
        return '<a href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a>';
    }
    
    protected function column_car_brand($item) {
        return !empty($item['car_brand']) ? esc_html($item['car_brand']) : '—';
    }
    
    protected function column_problem($item) {
        $problem = $item['problem'] ?? '';
        if (empty($problem)) return '—';
        if (mb_strlen($problem) > 80) {
            $problem = mb_substr($problem, 0, 80) . '...';
        }
        return '<span title="' . esc_attr($item['problem']) . '">' . esc_html($problem) . '</span>';
    }
    
    protected function column_source($item) {
        $sources = [
            'site_form'    => '🌐 Форма',
            'site_booking' => '🌐 Сайт',
            'avito'        => '📱 Авито',
            'telegram'     => '📨 Telegram',
            'phone'        => '📞 Телефон',
            'manual'       => '✍️ Вручную',
        ];
        $source = $item['source'] ?? '';
        return $sources[$source] ?? esc_html($source);
    }
    
    protected function column_status($item) {
        $statuses = [
            'new'        => ['label' => '🆕 Новый', 'color' => '#63b3ed'],
            'contacted'  => ['label' => '📞 Связались', 'color' => '#f6ad55'],
            'diagnostic' => ['label' => '🔍 Диагностика', 'color' => '#f6ad55'],
            'in_work'    => ['label' => '🔧 В работе', 'color' => '#f6ad55'],
            'completed'  => ['label' => '✅ Выполнено', 'color' => '#00ff88'],
            'converted'  => ['label' => '✅ Конвертирован', 'color' => '#00ff88'],
            'cancelled'  => ['label' => '❌ Отменено', 'color' => '#fc8181'],
            'lost'       => ['label' => '❌ Потерян', 'color' => '#fc8181'],
        ];
        $status = $item['status'] ?? 'new';
        $info = $statuses[$status] ?? ['label' => $status, 'color' => '#a0aec0'];
        return sprintf(
            '<span style="display:inline-block;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;background:%s22;color:%s;">%s</span>',
            esc_attr($info['color']),
            esc_attr($info['color']),
            esc_html($info['label'])
        );
    }
    
    protected function column_created_at($item) {
        $date = $item['created_at'] ?? '';
        if (empty($date)) return '—';
        return date_i18n('d.m.Y H:i', strtotime($date));
    }
    
    protected function column_actions($item) {
        $actions = [];
        
        $actions[] = sprintf(
            '<a href="?page=akpp-crm-leads&action=edit&id=%d" class="button button-small" title="Редактировать">✏️</a>',
            $item['id']
        );
        
        $actions[] = sprintf(
            '<a href="?page=akpp-crm-new-deal&lead_id=%d&_wpnonce=%s" class="button button-small" title="Создать сделку">💰</a>',
            $item['id'],
            wp_create_nonce('create_deal_from_lead_' . $item['id'])
        );
        
        $delete_url = wp_nonce_url(
            add_query_arg([
                'page'   => 'akpp-crm-leads',
                'action' => 'delete',
                'id'     => $item['id']
            ]),
            'delete_lead_' . $item['id']
        );
        
        $actions[] = sprintf(
            '<a href="%s" class="button button-small" title="Удалить" onclick="return confirm(\'Удалить лид?\')">🗑️</a>',
            esc_url($delete_url)
        );
        
        return implode(' ', $actions);
    }
    
    protected function column_default($item, $column_name) {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '—';
    }
    
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
                <option value="in_work" <?php selected($status_filter, 'in_work'); ?>>🔧 В работе</option>
                <option value="completed" <?php selected($status_filter, 'completed'); ?>>✅ Выполнено</option>
                <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>>❌ Отменено</option>
            </select>
            
            <select name="source_filter">
                <option value="all" <?php selected($source_filter, 'all'); ?>>Все источники</option>
                <option value="site_booking" <?php selected($source_filter, 'site_booking'); ?>>🌐 Сайт</option>
                <option value="avito" <?php selected($source_filter, 'avito'); ?>>📱 Авито</option>
                <option value="telegram" <?php selected($source_filter, 'telegram'); ?>>📨 Telegram</option>
                <option value="phone" <?php selected($source_filter, 'phone'); ?>>📞 Телефон</option>
            </select>
            
            <input type="submit" name="filter_action" class="button" value="Фильтровать">
        </div>
        <?php
    }
    
    public function no_items() {
        echo 'Нет лидов для отображения. Заявки с сайта будут появляться здесь.';
    }
}