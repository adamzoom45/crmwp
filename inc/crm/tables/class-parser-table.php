<?php
/**
 * Класс для таблицы элементов парсера в админке
 *
 * @package AKPP45_CRM
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AKPP_Parser_Table extends WP_List_Table {
    
    private $table_name;
    
    public function __construct() {
        parent::__construct([
            'singular' => 'parser_item',
            'plural'   => 'parser_items',
            'ajax'     => false
        ]);
        
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'akpp_parser_items';
    }
    
    /**
     * Получение данных для таблицы
     */
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="parser_item[]" value="%s" />', $item['id']);
    }
    
    protected function column_id($item) {
        return $item['id'];
    }
    
    protected function column_title($item) {
        $title = !empty($item['title']) ? esc_html($item['title']) : 'Без заголовка';
        $url = esc_url($item['url']);
        return sprintf(
            '<a href="%s" target="_blank"><strong>%s</strong></a><br><small>%s</small>',
            $url,
            $title,
            substr($url, 0, 60)
        );
    }
    
    protected function column_content_type($item) {
        $types = [
            'transmission' => '🔧 АКПП',
            'part' => '🔩 Запчасть',
            'oil' => '🛢️ Масло',
            'general' => '📄 Общее'
        ];
        $type_name = $types[$item['content_type']] ?? $item['content_type'];
        return '<span class="content-type-badge">' . $type_name . '</span>';
    }
    
    protected function column_status($item) {
        $statuses = [
            'pending' => '⏳ Ожидает',
            'parsed' => '📄 Распаршено',
            'ai_processed' => '🤖 AI обработан',
            'approved' => '✅ Одобрено',
            'rejected' => '❌ Отклонено'
        ];
        $status_name = $statuses[$item['status']] ?? $item['status'];
        return '<span class="status-badge">' . $status_name . '</span>';
    }
    
    protected function column_ai_confidence($item) {
        $ai_analysis = !empty($item['ai_analysis']) ? json_decode($item['ai_analysis'], true) : null;
        $confidence = $ai_analysis['confidence'] ?? 0;
        if ($confidence === 0) return '—';
        
        $color = $confidence >= 80 ? '#28a745' : ($confidence >= 60 ? '#ffc107' : '#dc3545');
        
        return sprintf(
            '<div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 80px; height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden;">
                    <div style="width: %d%%; height: 100%%; background: %s;"></div>
                </div>
                <span>%d%%</span>
            </div>',
            $confidence,
            $color,
            $confidence
        );
    }
    
    protected function column_created_at($item) {
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item['created_at']));
    }
    
    protected function column_actions($item) {
        $actions = '';
        if ($item['status'] !== 'approved') {
            $actions .= sprintf(
                '<button class="button button-small approve-item" data-id="%d">✅ Одобрить</button> ',
                $item['id']
            );
        }
        if ($item['status'] !== 'rejected' && $item['status'] !== 'approved') {
            $actions .= sprintf(
                '<button class="button button-small reject-item" data-id="%d">❌ Отклонить</button> ',
                $item['id']
            );
        }
        $actions .= sprintf(
            '<button class="button button-small view-item" data-id="%d">👁️ Просмотр</button>',
            $item['id']
        );
        return $actions;
    }
    
    /**
     * Определение колонок
     */
    public function get_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'id'            => 'ID',
            'title'         => 'Заголовок',
            'content_type'  => 'Тип',
            'status'        => 'Статус',
            'ai_status'     => 'AI анализ',   // НОВАЯ КОЛОНКА
            'ai_confidence' => 'Уверенность AI',
            'created_at'    => 'Дата',
            'actions'       => 'Действия'
        ];
    }
    
    /**
     * Сортируемые колонки
     */
    public function get_sortable_columns() {
        return [
            'id'           => ['id', false],
            'title'        => ['title', false],
            'content_type' => ['content_type', false],
            'created_at'   => ['created_at', true]
        ];
    }
    
    /**
     * Массовые действия
     */
    public function get_bulk_actions() {
        return [
            'approve' => 'Одобрить',
            'reject'  => 'Отклонить',
            'delete'  => 'Удалить'
        ];
    }
    
    /**
     * Обработка массовых действий
     */
    public function process_bulk_action() {
        global $wpdb;
        
        if (!$this->current_action()) return;
        
        $item_ids = isset($_GET['parser_item']) ? array_map('intval', $_GET['parser_item']) : [];
        if (empty($item_ids)) return;
        
        $ids_placeholder = implode(',', array_fill(0, count($item_ids), '%d'));
        
        switch ($this->current_action()) {
            case 'approve':
                foreach ($item_ids as $item_id) {
                    $this->approve_item($item_id);
                }
                echo '<div class="notice notice-success"><p>Элементы одобрены</p></div>';
                break;
                
            case 'reject':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->table_name} SET status = 'rejected' WHERE id IN ({$ids_placeholder})",
                    $item_ids
                ));
                echo '<div class="notice notice-success"><p>Элементы отклонены</p></div>';
                break;
                
            case 'delete':
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
                    $item_ids
                ));
                echo '<div class="notice notice-success"><p>Элементы удалены</p></div>';
                break;
        }
    }
    
    /**
     * Одобрение элемента
     */
    private function approve_item($item_id) {
        global $wpdb;
        
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $item_id
        ));
        
        if (!$item) return;
        
        $ai_analysis = !empty($item->ai_analysis) ? json_decode($item->ai_analysis, true) : null;
        if (!$ai_analysis) return;
        
        // Сохраняем в соответствующую таблицу
        if ($item->content_type === 'transmission') {
            $this->save_to_transmissions($item, $ai_analysis);
        } elseif ($item->content_type === 'part') {
            $this->save_to_parts($item, $ai_analysis);
        } elseif ($item->content_type === 'oil') {
            $this->save_to_oils($item, $ai_analysis);
        }
        
        $wpdb->update(
            $this->table_name,
            ['status' => 'approved', 'updated_at' => current_time('mysql')],
            ['id' => $item_id],
            ['%s', '%s'],
            ['%d']
        );
    }
    
    /**
     * Сохранение в таблицу АКПП
     */
    private function save_to_transmissions($item, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_transmissions';
        
        $wpdb->insert(
            $table,
            [
                'code'                 => $data['code'] ?? '',
                'type'                 => $data['type'] ?? '',
                'make'                 => $data['make'] ?? '',
                'model'                => $data['model'] ?? '',
                'years'                => $data['years'] ?? '',
                'common_problems'      => is_array($data['problems'] ?? null) ? json_encode($data['problems']) : '',
                'symptoms'             => is_array($data['symptoms'] ?? null) ? json_encode($data['symptoms']) : '',
                'repair_cost'          => $data['repair_cost'] ?? 0,
                'difficulty'           => $data['difficulty'] ?? 3,
                'source_url'           => $item->url,
                'created_at'           => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );
    }
    
    /**
     * Сохранение в таблицу запчастей
     */
    private function save_to_parts($item, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parts';
        
        $wpdb->insert(
            $table,
            [
                'name'                 => $data['part_type'] ?? $item->title,
                'sku'                  => $data['part_number'] ?? '',
                'category'             => $data['part_type'] ?? 'Запчасть АКПП',
                'description'          => $item->content,
                'price'                => $data['avg_price'] ?? 0,
                'compatible_transmissions' => is_array($data['transmissions'] ?? null) ? json_encode($data['transmissions']) : '',
                'source_url'           => $item->url,
                'created_at'           => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );
    }
    
    /**
     * Сохранение в таблицу масел
     */
    private function save_to_oils($item, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_oils';
        
        $wpdb->insert(
            $table,
            [
                'name'                 => $data['oil_type'] ?? $item->title,
                'type'                 => $data['oil_type'] ?? 'ATF',
                'viscosity'            => $data['viscosity'] ?? '',
                'specifications'       => is_array($data['specifications'] ?? null) ? json_encode($data['specifications']) : '',
                'compatible_transmissions' => is_array($data['transmissions'] ?? null) ? json_encode($data['transmissions']) : '',
                'fill_volume'          => $data['fill_volume'] ?? 0,
                'price_per_liter'      => $data['price_per_liter'] ?? 0,
                'source_url'           => $item->url,
                'created_at'           => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );
    }
    
    /**
     * Отображение колонки cb
     */
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="parser_item[]" value="%s" />', $item->id);
    }
    
    /**
     * Отображение колонки ID
     */
    protected function column_id($item) {
        return $item->id;
    }
    
    /**
     * Отображение колонки заголовка
     */
    protected function column_title($item) {
        $title = !empty($item->title) ? esc_html($item->title) : 'Без заголовка';
        $url = esc_url($item->url);
        
        return sprintf(
            '<a href="%s" target="_blank"><strong>%s</strong></a><br><small>%s</small>',
            $url,
            $title,
            substr($url, 0, 60)
        );
    }
    
    /**
     * Отображение колонки типа
     */
    protected function column_content_type($item) {
        $types = [
            'transmission' => '🔧 АКПП',
            'part'         => '🔩 Запчасть',
            'oil'          => '🛢️ Масло',
            'general'      => '📄 Общее'
        ];
        
        $type_name = $types[$item->content_type] ?? $item->content_type;
        return '<span class="content-type-badge">' . $type_name . '</span>';
    }
    
    /**
     * Отображение колонки статуса
     */
    protected function column_status($item) {
        $statuses = [
            'pending'      => '⏳ Ожидает',
            'parsed'       => '📄 Распаршено',
            'ai_processed' => '🤖 AI обработан',
            'approved'     => '✅ Одобрено',
            'rejected'     => '❌ Отклонено'
        ];
        
        $status_name = $statuses[$item->status] ?? $item->status;
        return '<span class="status-badge">' . $status_name . '</span>';
    }
    
    /**
     * НОВАЯ КОЛОНКА: AI статус
     */
    protected function column_ai_status($item) {
        if (!empty($item->ai_analysis)) {
            $analysis = json_decode($item->ai_analysis, true);
            if ($analysis && !empty($analysis['vehicles'])) {
                return '🚗 ' . count($analysis['vehicles']) . ' авто';
            }
            return '✅ Есть';
        }
        return '—';
    }
    
    /**
     * Отображение колонки уверенности AI
     */
    protected function column_ai_confidence($item) {
        $confidence = $item->ai_analysis['confidence'] ?? 0;
        if ($confidence === 0) return '—';
        
        $color = $confidence >= 80 ? '#28a745' : ($confidence >= 60 ? '#ffc107' : '#dc3545');
        
        return sprintf(
            '<div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 80px; height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden;">
                    <div style="width: %d%%; height: 100%%; background: %s;"></div>
                </div>
                <span>%d%%</span>
            </div>',
            $confidence,
            $color,
            $confidence
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
        $actions = '';
        
        if ($item->status !== 'approved') {
            $actions .= sprintf(
                '<button class="button button-small approve-item" data-id="%d">✅ Одобрить</button> ',
                $item->id
            );
        }
        
        if ($item->status !== 'rejected' && $item->status !== 'approved') {
            $actions .= sprintf(
                '<button class="button button-small reject-item" data-id="%d">❌ Отклонить</button> ',
                $item->id
            );
        }
        
        // Кнопка AI анализа (если ещё не обработан)
        if ($item->status !== 'ai_processed' && $item->status !== 'approved') {
            $actions .= sprintf(
                '<button class="button button-small btn-ai-analyze" data-id="%d">🤖 AI</button> ',
                $item->id
            );
        }
        
        $actions .= sprintf(
            '<button class="button button-small view-item" data-id="%d">👁️ Просмотр</button>',
            $item->id
        );
        
        return $actions;
    }
    
    /**
     * Фильтры над таблицей
     */
    protected function extra_tablenav($which) {
        if ($which !== 'top') return;
        
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $type_filter = isset($_GET['type_filter']) ? sanitize_text_field($_GET['type_filter']) : 'all';
        ?>
        <div class="alignleft actions">
            <select name="status_filter">
                <option value="all" <?php selected($status_filter, 'all'); ?>>Все статусы</option>
                <option value="pending" <?php selected($status_filter, 'pending'); ?>>Ожидает</option>
                <option value="parsed" <?php selected($status_filter, 'parsed'); ?>>Распаршено</option>
                <option value="ai_processed" <?php selected($status_filter, 'ai_processed'); ?>>AI обработан</option>
                <option value="approved" <?php selected($status_filter, 'approved'); ?>>Одобрено</option>
                <option value="rejected" <?php selected($status_filter, 'rejected'); ?>>Отклонено</option>
            </select>
            
            <select name="type_filter">
                <option value="all" <?php selected($type_filter, 'all'); ?>>Все типы</option>
                <option value="transmission" <?php selected($type_filter, 'transmission'); ?>>АКПП</option>
                <option value="part" <?php selected($type_filter, 'part'); ?>>Запчасти</option>
                <option value="oil" <?php selected($type_filter, 'oil'); ?>>Масла</option>
                <option value="general" <?php selected($type_filter, 'general'); ?>>Общее</option>
            </select>
            
            <input type="submit" name="filter_action" class="button" value="Фильтровать">
        </div>
        <?php
    }
    
    /**
     * Отображение, если нет данных
     */
    public function no_items() {
        echo 'Нет элементов для отображения';
    }
}
