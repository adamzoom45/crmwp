<?php
/**
 * Класс для таблицы запчастей в админке
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AKPP_Parts_Table extends WP_List_Table {
    
    private $table_name;
    
    public function __construct() {
        parent::__construct([
            'singular' => 'part',
            'plural' => 'parts',
            'ajax' => false
        ]);
        
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'akpp_parts';
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
        $category_filter = isset($_GET['category_filter']) ? sanitize_text_field($_GET['category_filter']) : 'all';
        $stock_filter = isset($_GET['stock_filter']) ? sanitize_text_field($_GET['stock_filter']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Построение WHERE
        $where = [];
        $params = [];
        
        if ($category_filter !== 'all') {
            $where[] = "category = %s";
            $params[] = $category_filter;
        }
        
        if ($stock_filter === 'in_stock') {
            $where[] = "quantity > 0";
        } elseif ($stock_filter === 'out_of_stock') {
            $where[] = "quantity <= 0";
        }
        
        if (!empty($search)) {
            $where[] = "(name LIKE '%%%s%%' OR sku LIKE '%%%s%%')";
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Сортировка
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'name';
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
            'sku' => 'Артикул',
            'name' => 'Наименование',
            'category' => 'Категория',
            'quantity' => 'Остаток',
            'price' => 'Цена',
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
            'sku' => ['sku', false],
            'name' => ['name', true],
            'quantity' => ['quantity', false],
            'price' => ['price', false],
            'created_at' => ['created_at', true]
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
        
        $part_ids = isset($_GET['part']) ? array_map('intval', $_GET['part']) : [];
        
        if (empty($part_ids)) return;
        
        switch ($this->current_action()) {
            case 'delete':
                $ids_placeholder = implode(',', array_fill(0, count($part_ids), '%d'));
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
                    $part_ids
                ));
                echo '<div class="notice notice-success"><p>Запчасти удалены</p></div>';
                break;
                
            case 'export':
                $this->export_to_csv($part_ids);
                break;
        }
    }
    
    /**
     * Экспорт в CSV
     */
    private function export_to_csv($ids) {
        global $wpdb;
        
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        $parts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
            $ids
        ));
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="parts_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Артикул', 'Наименование', 'Категория', 'Количество', 'Цена', 'Дата создания']);
        
        foreach ($parts as $part) {
            fputcsv($output, [
                $part->id,
                $part->sku,
                $part->name,
                $part->category,
                $part->quantity,
                $part->price,
                $part->created_at
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Отображение колонки cb (чекбокс)
     */
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="part[]" value="%s" />', $item->id);
    }
    
    /**
     * Отображение колонки ID
     */
    protected function column_id($item) {
        return $item->id;
    }
    
    /**
     * Отображение колонки SKU
     */
    protected function column_sku($item) {
        return !empty($item->sku) ? esc_html($item->sku) : '—';
    }
    
    /**
     * Отображение колонки наименования
     */
    protected function column_name($item) {
        $name = esc_html($item->name);
        
        if ($item->description) {
            $name .= '<br><small style="color:#999;">' . esc_html(substr($item->description, 0, 50)) . '</small>';
        }
        
        return $name;
    }
    
    /**
     * Отображение колонки категории
     */
    protected function column_category($item) {
        $categories = [
            'АКПП в сборе' => '🔧 АКПП в сборе',
            'Фрикционы' => '⚙️ Фрикционы',
            'Стальные диски' => '💿 Стальные диски',
            'Сальники' => '🔘 Сальники',
            'Прокладки' => '📄 Прокладки',
            'Соленоиды' => '⚡ Соленоиды',
            'Гидроблоки' => '💧 Гидроблоки',
            'Масляные насосы' => '🛢️ Масляные насосы',
            'Подшипники' => '🔩 Подшипники',
            'Планетарные ряды' => '⚙️ Планетарные ряды',
            'Ремкомплекты' => '📦 Ремкомплекты',
            'Масла ATF' => '🛢️ Масла ATF',
            'Фильтры' => '🔍 Фильтры',
            'Датчики' => '📊 Датчики',
            'Прочее' => '📦 Прочее'
        ];
        
        $category = $categories[$item->category] ?? $item->category;
        return $category;
    }
    
    /**
     * Отображение колонки остатка
     */
    protected function column_quantity($item) {
        $quantity = intval($item->quantity);
        
        if ($quantity <= 0) {
            return '<span style="color: #dc3545;">0 (Нет в наличии)</span>';
        } elseif ($quantity < 5) {
            return '<span style="color: #ffc107;">' . $quantity . ' (Мало)</span>';
        } else {
            return '<span style="color: #28a745;">' . $quantity . '</span>';
        }
    }
    
    /**
     * Отображение колонки цены
     */
    protected function column_price($item) {
        return number_format($item->price, 0, ',', ' ') . ' ₽';
    }
    
    /**
     * Отображение колонки даты
     */
    protected function column_created_at($item) {
        return date_i18n(get_option('date_format'), strtotime($item->created_at));
    }
    
    /**
     * Отображение колонки действий
     */
    protected function column_actions($item) {
        $actions = sprintf(
            '<a href="?page=akpp-crm-parts&action=edit&id=%d" class="button button-small">✏️ Ред.</a> ',
            $item->id
        );
        
        $actions .= sprintf(
            '<button class="button button-small adjust-stock" data-id="%d" data-name="%s">📦 Склад</button> ',
            $item->id,
            esc_attr($item->name)
        );
        
        $actions .= sprintf(
            '<button class="button button-small delete-part" data-id="%d">🗑️</button>',
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
        
        $category_filter = isset($_GET['category_filter']) ? sanitize_text_field($_GET['category_filter']) : 'all';
        $stock_filter = isset($_GET['stock_filter']) ? sanitize_text_field($_GET['stock_filter']) : 'all';
        
        $categories = [
            'all' => 'Все категории',
            'АКПП в сборе' => '🔧 АКПП в сборе',
            'Фрикционы' => '⚙️ Фрикционы',
            'Стальные диски' => '💿 Стальные диски',
            'Сальники' => '🔘 Сальники',
            'Прокладки' => '📄 Прокладки',
            'Соленоиды' => '⚡ Соленоиды',
            'Гидроблоки' => '💧 Гидроблоки',
            'Масляные насосы' => '🛢️ Масляные насосы',
            'Подшипники' => '🔩 Подшипники',
            'Планетарные ряды' => '⚙️ Планетарные ряды',
            'Ремкомплекты' => '📦 Ремкомплекты',
            'Масла ATF' => '🛢️ Масла ATF',
            'Фильтры' => '🔍 Фильтры',
            'Датчики' => '📊 Датчики',
            'Прочее' => '📦 Прочее'
        ];
        ?>
        <div class="alignleft actions">
            <select name="category_filter">
                <?php foreach ($categories as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($category_filter, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="stock_filter">
                <option value="all" <?php selected($stock_filter, 'all'); ?>>Все запчасти</option>
                <option value="in_stock" <?php selected($stock_filter, 'in_stock'); ?>>В наличии</option>
                <option value="out_of_stock" <?php selected($stock_filter, 'out_of_stock'); ?>>Нет в наличии</option>
            </select>
            
            <input type="submit" name="filter_action" class="button" value="Фильтровать">
            <a href="?page=akpp-crm-parts&action=add" class="button button-primary">➕ Добавить запчасть</a>
        </div>
        <?php
    }
    
    /**
     * Отображение, если нет данных
     */
    public function no_items() {
        echo 'Нет запчастей для отображения';
    }
}
