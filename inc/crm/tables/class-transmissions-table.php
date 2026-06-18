<?php
/**
 * Класс для таблицы АКПП в админке
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
            'plural' => 'transmissions',
            'ajax' => false
        ]);
        
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'akpp_transmissions';
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
        $type_filter = isset($_GET['type_filter']) ? sanitize_text_field($_GET['type_filter']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Построение WHERE
        $where = [];
        $params = [];
        
        if ($type_filter !== 'all') {
            $where[] = "type = %s";
            $params[] = $type_filter;
        }
        
        if (!empty($search)) {
            $where[] = "(code LIKE '%%%s%%' OR make LIKE '%%%s%%' OR model LIKE '%%%s%%')";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Сортировка
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'code';
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
            'code' => 'Код АКПП',
            'type' => 'Тип',
            'make' => 'Марка',
            'model' => 'Модель',
            'years' => 'Годы',
            'engine' => 'Двигатель',
            'difficulty' => 'Сложность',
            'repair_cost' => 'Стоимость ремонта',
            'actions' => 'Действия'
        ];
    }
    
    /**
     * Сортируемые колонки
     */
    public function get_sortable_columns() {
        return [
            'id' => ['id', false],
            'code' => ['code', true],
            'type' => ['type', false],
            'make' => ['make', false],
            'repair_cost' => ['repair_cost', false],
            'difficulty' => ['difficulty', false]
        ];
    }
    
    /**
     * Массовые действия
     */
    public function get_bulk_actions() {
        return [
            'delete' => 'Удалить',
            'export' => 'Экспорт в CSV'
        ];
    }
    
    /**
     * Обработка массовых действий
     */
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
                    $trans_ids
                ));
                echo '<div class="notice notice-success"><p>АКПП удалены</p></div>';
                break;
                
            case 'export':
                $this->export_to_csv($trans_ids);
                break;
        }
    }
    
    /**
     * Экспорт в CSV
     */
    private function export_to_csv($ids) {
        global $wpdb;
        
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
            $ids
        ));
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="transmissions_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Код', 'Тип', 'Марка', 'Модель', 'Годы', 'Двигатель', 'Сложность', 'Стоимость ремонта']);
        
        foreach ($items as $item) {
            fputcsv($output, [
                $item->id,
                $item->code,
                $item->type,
                $item->make,
                $item->model,
                $item->years,
                $item->engine,
                $item->difficulty,
                $item->repair_cost
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Отображение колонки cb (чекбокс)
     */
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="transmission[]" value="%s" />', $item->id);
    }
    
    /**
     * Отображение колонки ID
     */
    protected function column_id($item) {
        return $item->id;
    }
    
    /**
     * Отображение колонки кода АКПП
     */
    protected function column_code($item) {
        return sprintf(
            '<strong><code>%s</code></strong>',
            esc_html($item->code)
        );
    }
    
    /**
     * Отображение колонки типа
     */
    protected function column_type($item) {
        $types = [
            '4AT' => '4AT',
            '5AT' => '5AT',
            '6AT' => '6AT',
            '8AT' => '8AT',
            '9AT' => '9AT',
            '10AT' => '10AT',
            'CVT' => 'CVT',
            'DCT' => 'DCT'
        ];
        
        $type_label = $types[$item->type] ?? $item->type;
        
        return sprintf(
            '<span class="type-badge type-%s">%s</span>',
            strtolower($item->type),
            esc_html($type_label)
        );
    }
    
    /**
     * Отображение колонки марки
     */
    protected function column_make($item) {
        return esc_html($item->make);
    }
    
    /**
     * Отображение колонки модели
     */
    protected function column_model($item) {
        return esc_html($item->model);
    }
    
    /**
     * Отображение колонки годов
     */
    protected function column_years($item) {
        return esc_html($item->years);
    }
    
    /**
     * Отображение колонки двигателя
     */
    protected function column_engine($item) {
        return esc_html($item->engine);
    }
    
    /**
     * Отображение колонки сложности
     */
    protected function column_difficulty($item) {
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $item->difficulty) {
                $stars .= '⭐';
            } else {
                $stars .= '☆';
            }
        }
        return $stars;
    }
    
    /**
     * Отображение колонки стоимости ремонта
     */
    protected function column_repair_cost($item) {
        return number_format($item->repair_cost, 0, ',', ' ') . ' ₽';
    }
    
    /**
     * Отображение колонки действий
     */
    protected function column_actions($item) {
        $actions = sprintf(
            '<a href="?page=akpp-crm-transmissions&action=edit&id=%d" class="button button-small">✏️ Ред.</a> ',
            $item->id
        );
        
        $actions .= sprintf(
            '<button class="button button-small view-transmission" data-id="%d" data-code="%s">👁️</button> ',
            $item->id,
            esc_attr($item->code)
        );
        
        $actions .= sprintf(
            '<button class="button button-small delete-transmission" data-id="%d">🗑️</button>',
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
        
        $type_filter = isset($_GET['type_filter']) ? sanitize_text_field($_GET['type_filter']) : 'all';
        ?>
        <div class="alignleft actions">
            <select name="type_filter">
                <option value="all" <?php selected($type_filter, 'all'); ?>>Все типы</option>
                <option value="4AT" <?php selected($type_filter, '4AT'); ?>>4AT</option>
                <option value="5AT" <?php selected($type_filter, '5AT'); ?>>5AT</option>
                <option value="6AT" <?php selected($type_filter, '6AT'); ?>>6AT</option>
                <option value="8AT" <?php selected($type_filter, '8AT'); ?>>8AT</option>
                <option value="9AT" <?php selected($type_filter, '9AT'); ?>>9AT</option>
                <option value="10AT" <?php selected($type_filter, '10AT'); ?>>10AT</option>
                <option value="CVT" <?php selected($type_filter, 'CVT'); ?>>CVT</option>
                <option value="DCT" <?php selected($type_filter, 'DCT'); ?>>DCT</option>
            </select>
            
            <input type="submit" name="filter_action" class="button" value="Фильтровать">
            <a href="?page=akpp-crm-transmissions&action=add" class="button button-primary">➕ Добавить АКПП</a>
        </div>
        <?php
    }
    
    /**
     * Отображение, если нет данных
     */
    public function no_items() {
        echo 'Нет АКПП для отображения';
    }
}
