<?php
/**
 * Класс для таблицы АКПП (упрощённый, рабочий)
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AKPP_Transmissions_Table extends WP_List_Table {
    
    private $table_name;
    
    public function __construct() {
        parent::__construct([
            'singular' => 'transmission',
            'plural'   => 'transmissions',
            'ajax'     => false
        ]);
        
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'akpp_transmissions';
    }
    
    public function prepare_items() {
        global $wpdb;
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Получаем фильтры из GET
        $type_filter   = isset($_GET['type_filter']) ? sanitize_text_field($_GET['type_filter']) : 'all';
        $region_filter = isset($_GET['region_filter']) ? sanitize_text_field($_GET['region_filter']) : 'all';
        $search        = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Строим WHERE
        $where_sql = '1=1';
        $where_params = [];
        
        if ($type_filter !== 'all') {
            $where_sql .= ' AND type = %s';
            $where_params[] = $type_filter;
        }
        if ($region_filter !== 'all') {
            $where_sql .= ' AND region = %s';
            $where_params[] = $region_filter;
        }
        if (!empty($search)) {
            $where_sql .= ' AND (code LIKE %s OR manufacturer LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_params[] = $like;
            $where_params[] = $like;
        }
        
        // Подсчёт общего количества – используем prepare с явными параметрами
        $count_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_sql}";
        if (!empty($where_params)) {
            $count_sql = $wpdb->prepare($count_sql, ...$where_params);
        }
        $total_items = (int) $wpdb->get_var($count_sql);
        
        // Основной запрос – всегда сортируем по code, без сложностей
        $orderby = 'code';
        $order = 'ASC';
        $sql = "SELECT * FROM {$this->table_name} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        // Собираем параметры: сначала условия, потом лимит и оффсет
        $params = array_merge($where_params, [$per_page, $offset]);
        $sql = $wpdb->prepare($sql, ...$params);
        
        $this->items = $wpdb->get_results($sql);
        
        // Если данных нет, но общее количество больше нуля – логируем ошибку
        if (empty($this->items) && $total_items > 0) {
            error_log('[AKPP_Transmissions] Данные не получены. SQL: ' . $sql);
        }
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
        
        $this->process_bulk_action();
    }
    
    public function get_columns() {
        return [
            'cb'           => '<input type="checkbox" />',
            'id'           => 'ID',
            'code'         => 'Код АКПП',
            'type'         => 'Тип',
            'manufacturer' => 'Производитель',
            'region'       => 'Регион',
            'actions'      => 'Действия'
        ];
    }
    
    public function get_sortable_columns() {
        return [
            'code' => ['code', true],
        ];
    }
    
    public function get_bulk_actions() {
        return [
            'delete' => 'Удалить',
            'export' => 'Экспорт в CSV'
        ];
    }
    
    public function process_bulk_action() {
        global $wpdb;
        if (!$this->current_action()) return;
        $trans_ids = isset($_GET['transmission']) ? array_map('intval', $_GET['transmission']) : [];
        if (empty($trans_ids)) return;
        $ids_placeholder = implode(',', array_fill(0, count($trans_ids), '%d'));
        
        switch ($this->current_action()) {
            case 'delete':
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
                    ...$trans_ids
                ));
                echo '<div class="notice notice-success"><p>АКПП удалены.</p></div>';
                break;
            case 'export':
                $this->export_to_csv($trans_ids);
                break;
        }
    }
    
    private function export_to_csv($ids) {
        global $wpdb;
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
            ...$ids
        ));
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="transmissions_export_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Код', 'Тип', 'Производитель', 'Регион']);
        foreach ($items as $item) {
            fputcsv($output, [
                $item->id,
                $item->code,
                $item->type,
                $item->manufacturer,
                $item->region
            ]);
        }
        fclose($output);
        exit;
    }
    
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="transmission[]" value="%s" />', $item->id);
    }
    
    protected function column_id($item) {
        return $item->id;
    }
    
    protected function column_code($item) {
        return sprintf('<strong><code>%s</code></strong>', esc_html($item->code));
    }
    
    protected function column_type($item) {
        $types = ['AT' => 'AT', 'CVT' => 'CVT', 'DCT' => 'DCT', 'AMT' => 'AMT', 'MT' => 'MT'];
        $label = $types[$item->type] ?? $item->type;
        return '<span class="type-badge">' . esc_html($label) . '</span>';
    }
    
    protected function column_manufacturer($item) {
        return esc_html($item->manufacturer ?: '—');
    }
    
    protected function column_region($item) {
        $regions = [
            'japan'   => '🇯🇵 Япония',
            'korea'   => '🇰🇷 Корея',
            'china'   => '🇨🇳 Китай',
            'europe'  => '🇪🇺 Европа',
            'america' => '🇺🇸 Америка'
        ];
        $label = isset($regions[$item->region]) ? $regions[$item->region] : esc_html($item->region);
        return $label;
    }
    
    protected function column_actions($item) {
        $actions = sprintf(
            '<a href="?page=akpp-crm-transmissions&action=edit&id=%d" class="button button-small">✏️ Ред.</a> ',
            $item->id
        );
        $actions .= sprintf(
            '<button class="button button-small delete-transmission" data-id="%d">🗑️</button>',
            $item->id
        );
        return $actions;
    }
    
    protected function column_default($item, $column_name) {
        return isset($item->$column_name) ? esc_html($item->$column_name) : '—';
    }
    
    protected function extra_tablenav($which) {
        if ($which !== 'top') return;
        $type_filter   = isset($_GET['type_filter']) ? sanitize_text_field($_GET['type_filter']) : 'all';
        $region_filter = isset($_GET['region_filter']) ? sanitize_text_field($_GET['region_filter']) : 'all';
        ?>
        <div class="alignleft actions">
            <select name="type_filter">
                <option value="all" <?php selected($type_filter, 'all'); ?>>Все типы</option>
                <option value="AT" <?php selected($type_filter, 'AT'); ?>>AT</option>
                <option value="CVT" <?php selected($type_filter, 'CVT'); ?>>CVT</option>
                <option value="DCT" <?php selected($type_filter, 'DCT'); ?>>DCT</option>
                <option value="AMT" <?php selected($type_filter, 'AMT'); ?>>AMT</option>
                <option value="MT" <?php selected($type_filter, 'MT'); ?>>MT</option>
            </select>
            <select name="region_filter">
                <option value="all" <?php selected($region_filter, 'all'); ?>>Все регионы</option>
                <option value="japan" <?php selected($region_filter, 'japan'); ?>>Япония</option>
                <option value="korea" <?php selected($region_filter, 'korea'); ?>>Корея</option>
                <option value="china" <?php selected($region_filter, 'china'); ?>>Китай</option>
                <option value="europe" <?php selected($region_filter, 'europe'); ?>>Европа</option>
                <option value="america" <?php selected($region_filter, 'america'); ?>>Америка</option>
            </select>
            <input type="submit" name="filter_action" class="button" value="Фильтровать">
            <a href="?page=akpp-crm-transmissions&action=add" class="button button-primary">➕ Добавить АКПП</a>
        </div>
        <?php
    }
    
    public function no_items() {
        echo 'Нет АКПП для отображения';
    }
}