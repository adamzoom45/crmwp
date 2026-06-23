<?php
/**
 * Класс для таблицы АКПП в админке
 */
if (!defined('ABSPATH')) exit;

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
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $type_filter = isset($_GET['type_filter']) ? sanitize_text_field($_GET['type_filter']) : '';
        
        $where = [];
        $params = [];
        
        if (!empty($type_filter)) {
            $where[] = "type = %s";
            $params[] = $type_filter;
        }
        
        if (!empty($search)) {
            $where[] = "(code LIKE %s OR make LIKE %s OR model LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, ...$params);
        }
        $total_items = $wpdb->get_var($count_query);
        
        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        $query = $wpdb->prepare($query, ...$params);
        
        // ✅ ЗАГРУЗКА ДАННЫХ
        $this->items = $wpdb->get_results($query);
        
        // ✅ УСТАНОВКА COLUMN HEADERS (ОБЯЗАТЕЛЬНО!)
        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns()
        );
        
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
        
        $this->process_bulk_action();
    }
    
    public function get_columns() {
        return [
            'cb'              => '<input type="checkbox" />',
            'id'              => 'ID',
            'code'            => 'Код АКПП',
            'type'            => 'Тип',
            'make'            => 'Марка',
            'model'           => 'Модель',
            'years'           => 'Годы',
            'common_problems' => 'Проблемы',
            'repair_cost'     => 'Стоимость',
            'difficulty'      => 'Сложность',
            'actions'         => 'Действия'
        ];
    }
    
    public function get_sortable_columns() {
        return [
            'id'          => ['id', false],
            'code'        => ['code', false],
            'make'        => ['make', false],
            'repair_cost' => ['repair_cost', false],
            'difficulty'  => ['difficulty', false]
        ];
    }
    
    public function get_bulk_actions() {
        return ['delete' => '🗑️ Удалить'];
    }
    
    public function process_bulk_action() {
        global $wpdb;
        $action = $this->current_action();
        if (!$action) return;
        
        $ids = isset($_REQUEST['transmission']) ? array_map('intval', (array)$_REQUEST['transmission']) : [];
        if (empty($ids)) return;
        
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        
        switch ($action) {
            case 'delete':
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
                    $ids
                ));
                echo '<div class="notice notice-success"><p>Удалено АКПП: ' . count($ids) . '</p></div>';
                break;
        }
    }
    
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="transmission[]" value="%s" />', $item->id);
    }
    
    protected function column_id($item) {
        return '#' . intval($item->id);
    }
    
    protected function column_code($item) {
        return '<strong style="color: #00ff88;">' . esc_html($item->code ?? '—') . '</strong>';
    }
    
    protected function column_type($item) {
        $type = $item->type ?? '';
        if (empty($type)) return '—';
        
        $types = [
            'гидротрансформатор' => ['label' => '🔄 Гидро', 'color' => '#63b3ed'],
            'вариатор'           => ['label' => '⚙️ CVT', 'color' => '#f6ad55'],
            'робот'              => ['label' => '🤖 DCT', 'color' => '#9f7aea'],
            'cvt'                => ['label' => '⚙️ CVT', 'color' => '#f6ad55'],
        ];
        
        $lower_type = mb_strtolower($type);
        foreach ($types as $key => $info) {
            if (strpos($lower_type, $key) !== false) {
                return sprintf(
                    '<span style="display:inline-block;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;background:%s22;color:%s;">%s</span>',
                    $info['color'], $info['color'], $info['label']
                );
            }
        }
        
        return esc_html($type);
    }
    
    protected function column_make($item) {
        return esc_html($item->make ?? '—');
    }
    
    protected function column_model($item) {
        return esc_html($item->model ?? '—');
    }
    
    protected function column_years($item) {
        return esc_html($item->years ?? '—');
    }
    
    protected function column_common_problems($item) {
        $problems = $item->common_problems ?? '';
        if (empty($problems)) return '<span style="color:#718096;">—</span>';
        return '<span title="' . esc_attr($problems) . '">' . esc_html(mb_substr($problems, 0, 50)) . (mb_strlen($problems) > 50 ? '...' : '') . '</span>';
    }
    
    protected function column_repair_cost($item) {
        $cost = intval($item->repair_cost ?? 0);
        if ($cost === 0) return '—';
        return '<strong style="color: #f6ad55;">' . number_format($cost, 0, ',', ' ') . ' ₽</strong>';
    }
    
    protected function column_difficulty($item) {
        $difficulty = intval($item->difficulty ?? 3);
        $stars = str_repeat('⭐', $difficulty);
        return $stars;
    }
    
    protected function column_actions($item) {
        return sprintf(
            '<button class="button button-small btn-edit-transmission" data-id="%d" data-code="%s" data-type="%s" data-make="%s" data-model="%s" data-years="%s" data-engine="%s" data-problems="%s" data-cost="%d" data-difficulty="%d" style="background:#00ff88;border-color:#00ff88;color:#1a1f2e;">✏️</button> <button class="button button-small btn-delete-transmission" data-id="%d" style="background:#fc8181;border-color:#fc8181;color:#fff;">🗑️</button>',
            $item->id,
            esc_attr($item->code ?? ''),
            esc_attr($item->type ?? ''),
            esc_attr($item->make ?? ''),
            esc_attr($item->model ?? ''),
            esc_attr($item->years ?? ''),
            esc_attr($item->engine ?? ''),
            esc_attr($item->common_problems ?? ''),
            intval($item->repair_cost ?? 0),
            intval($item->difficulty ?? 3),
            $item->id
        );
    }
    
    protected function extra_tablenav($which) {
        if ($which !== 'top') return;
        
        $type_filter = isset($_GET['type_filter']) ? sanitize_text_field($_GET['type_filter']) : '';
        ?>
        <div class="alignleft actions">
            <select name="type_filter">
                <option value="">Все типы</option>
                <option value="гидротрансформатор" <?php selected($type_filter, 'гидротрансформатор'); ?>>Гидротрансформатор</option>
                <option value="вариатор" <?php selected($type_filter, 'вариатор'); ?>>Вариатор</option>
                <option value="робот" <?php selected($type_filter, 'робот'); ?>>Робот</option>
            </select>
            <input type="submit" name="filter_action" class="button" value="Фильтровать">
        </div>
        <?php
    }
    
    public function no_items() {
        echo 'Нет АКПП для отображения. Добавьте первую АКПП.';
    }
}