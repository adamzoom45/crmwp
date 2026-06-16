<?php
/**
 * АКПП45 CRM - Обработчик всех AJAX-запросов
 * Безопасная работа с БД, валидация, логирование и обработка ошибок.
 *
 * @package AKPP_CRM
 * @version 4.3
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

class AKPP_AJAX {

    /**
     * Конструктор: регистрация всех AJAX хуков
     */
    public function __construct() {
        // Поиск по справочникам
        add_action('wp_ajax_akpp_search_parts', [$this, 'ajax_search_parts']);
        add_action('wp_ajax_akpp_search_vehicles', [$this, 'ajax_search_vehicles']);
        add_action('wp_ajax_akpp_search_employees', [$this, 'ajax_search_employees']);
        
        // Операции с данными
        add_action('wp_ajax_akpp_save_deal', [$this, 'ajax_save_deal']);
        add_action('wp_ajax_akpp_decode_vin', [$this, 'ajax_decode_vin']);
        
        // Парсер
        add_action('wp_ajax_akpp_parse_url', [$this, 'ajax_parse_url']);
        add_action('wp_ajax_akpp_get_parser_items', [$this, 'ajax_get_parser_items']);
        add_action('wp_ajax_akpp_get_parser_item', [$this, 'ajax_get_parser_item']);
        add_action('wp_ajax_akpp_reparse_url', [$this, 'ajax_reparse_url']);
        add_action('wp_ajax_akpp_delete_parser_item', [$this, 'ajax_delete_parser_item']);
        add_action('wp_ajax_akpp_bulk_parse', [$this, 'ajax_bulk_parse']);
        add_action('wp_ajax_akpp_export_parser_items', [$this, 'ajax_export_parser_items']);
        
        // AI анализ
        add_action('wp_ajax_akpp_run_ai_analysis', [$this, 'ajax_run_ai_analysis']);
        add_action('wp_ajax_akpp_bulk_ai_analysis', [$this, 'ajax_bulk_ai_analysis']);
        add_action('wp_ajax_akpp_save_openai_settings', [$this, 'ajax_save_openai_settings']);
        add_action('wp_ajax_akpp_check_openai_key', [$this, 'ajax_check_openai_key']);
        add_action('wp_ajax_akpp_analyze_image', [$this, 'ajax_analyze_image']);
        add_action('wp_ajax_akpp_get_ai_statistics', [$this, 'ajax_get_ai_statistics']);
        add_action('wp_ajax_akpp_approve_parser_item', [$this, 'ajax_approve_parser_item']);
        add_action('wp_ajax_akpp_reject_parser_item', [$this, 'ajax_reject_parser_item']);
        
        // Telegram
        add_action('wp_ajax_akpp_save_telegram_settings', [$this, 'ajax_save_telegram_settings']);
        add_action('wp_ajax_akpp_send_test_telegram', [$this, 'ajax_send_test_telegram']);
        add_action('wp_ajax_akpp_set_telegram_webhook', [$this, 'ajax_set_telegram_webhook']);
        
        // Фоновые задачи
        add_action('akpp_ai_analysis_event', [$this, 'process_ai_analysis_event']);
    }

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Логирование событий
     */
    private function log_event($message, $level = 'info') {
        $log_message = sprintf('[AKPP CRM] [%s] [%s] %s', 
            current_time('mysql'), 
            strtoupper($level), 
            $message
        );
        error_log($log_message);
    }

    /**
     * Проверка прав доступа
     */
    private function check_permissions($capability = 'edit_posts') {
        if (!current_user_can($capability)) {
            wp_send_json_error(['message' => 'Недостаточно прав для выполнения этого действия'], 403);
            return false;
        }
        return true;
    }

    /**
     * Безопасное декодирование JSON
     */
    private function safe_json_decode($json_string) {
        if (empty($json_string)) {
            return null;
        }
        
        $decoded = json_decode($json_string, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_event('JSON decode error: ' . json_last_error_msg(), 'warning');
            return null;
        }
        
        return $decoded;
    }

    // =========================================================================
    // 1. ПОИСК ПО СПРАВОЧНИКАМ
    // =========================================================================

    /**
     * AJAX: Поиск запчастей по названию или артикулу
     */
    public function ajax_search_parts() {
        if (!check_ajax_referer('akpp_parts_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) {
            return;
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search) || strlen($search) < 2) {
            wp_send_json_success([]);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parts';
        
        // Проверка существования таблицы
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            wp_send_json_error(['message' => 'Таблица запчастей не найдена'], 500);
            return;
        }
        
        // БЕЗОПАСНАЯ подготовка LIKE-запроса
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        
        try {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name, sku, price, quantity, category 
                 FROM {$table} 
                 WHERE (name LIKE %s OR sku LIKE %s) AND quantity > 0 
                 ORDER BY quantity DESC, name ASC 
                 LIMIT 20",
                $search_like,
                $search_like
            ), ARRAY_A);
            
            $formatted = [];
            foreach ($results as $part) {
                $formatted[] = [
                    'id' => intval($part['id']),
                    'name' => esc_html($part['name']),
                    'sku' => esc_html($part['sku']),
                    'price' => floatval($part['price']),
                    'quantity' => intval($part['quantity']),
                    'display_text' => sprintf(
                        '%s (%s) — %s ₽ | Остаток: %d шт.',
                        $part['name'], $part['sku'], 
                        number_format($part['price'], 0, ',', ' '), 
                        $part['quantity']
                    )
                ];
            }
            
            wp_send_json_success($formatted);
            
        } catch (Exception $e) {
            $this->log_event('Search parts error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка поиска запчастей'], 500);
        }
    }

    /**
     * AJAX: Поиск автомобилей
     */
    public function ajax_search_vehicles() {
        if (!check_ajax_referer('akpp_vehicles_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) {
            return;
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        if (empty($search) || strlen($search) < 2) {
            wp_send_json_success([]);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_vehicles';
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        
        try {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT id, make, model, year, vin, market 
                 FROM {$table} 
                 WHERE (make LIKE %s OR model LIKE %s OR vin LIKE %s) 
                 ORDER BY year DESC, make ASC 
                 LIMIT 20",
                $search_like, $search_like, $search_like
            ), ARRAY_A);
            
            $formatted = [];
            foreach ($results as $v) {
                $formatted[] = [
                    'id' => intval($v['id']),
                    'display_text' => sprintf('%s %s (%d) — %s', 
                        esc_html($v['make']), esc_html($v['model']), 
                        intval($v['year']), esc_html($v['market']))
                ];
            }
            wp_send_json_success($formatted);
            
        } catch (Exception $e) {
            $this->log_event('Search vehicles error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка поиска автомобилей'], 500);
        }
    }

    /**
     * AJAX: Поиск сотрудников
     */
    public function ajax_search_employees() {
        if (!check_ajax_referer('akpp_employees_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) {
            return;
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        if (empty($search) || strlen($search) < 2) {
            wp_send_json_success([]);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_employees';
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        
        try {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name, position, percent 
                 FROM {$table} 
                 WHERE (name LIKE %s OR position LIKE %s) AND status = 'active'
                 ORDER BY name ASC 
                 LIMIT 20",
                $search_like, $search_like
            ), ARRAY_A);
            
            $formatted = [];
            foreach ($results as $emp) {
                $formatted[] = [
                    'id' => intval($emp['id']),
                    'display_text' => sprintf('%s — %s (%d%%)', 
                        esc_html($emp['name']), esc_html($emp['position']), 
                        intval($emp['percent']))
                ];
            }
            wp_send_json_success($formatted);
            
        } catch (Exception $e) {
            $this->log_event('Search employees error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка поиска сотрудников'], 500);
        }
    }

    // =========================================================================
    // 2. ОПЕРАЦИИ С ДАННЫМИ
    // =========================================================================

    /**
     * AJAX: Сохранение или обновление сделки + авто-списание запчастей
     */
    public function ajax_save_deal() {
        if (!check_ajax_referer('akpp_save_deal_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) {
            return;
        }
        
        global $wpdb;
        $deals_table = $wpdb->prefix . 'akpp_deals';
        $deal_parts_table = $wpdb->prefix . 'akpp_deal_parts';
        $parts_table = $wpdb->prefix . 'akpp_parts';
        
        // Санитизация данных
        $deal_data = [
            'client_name'    => sanitize_text_field($_POST['client_name'] ?? ''),
            'client_phone'   => sanitize_text_field($_POST['client_phone'] ?? ''),
            'vin'            => sanitize_text_field($_POST['vin'] ?? ''),
            'car_make'       => sanitize_text_field($_POST['car_make'] ?? ''),
            'car_model'      => sanitize_text_field($_POST['car_model'] ?? ''),
            'car_year'       => intval($_POST['car_year'] ?? 0),
            'work_cost'      => floatval($_POST['work_cost'] ?? 0),
            'work_hours'     => floatval($_POST['work_hours'] ?? 0),
            'standard_hours' => floatval($_POST['standard_hours'] ?? 0),
            'employee_id'    => intval($_POST['employee_id'] ?? 0),
            'percent'        => floatval($_POST['percent'] ?? 0),
            'payment_amount' => floatval($_POST['payment_amount'] ?? 0),
            'status'         => sanitize_text_field($_POST['status'] ?? 'new'),
            'updated_at'     => current_time('mysql')
        ];
        
        $deal_id = intval($_POST['deal_id'] ?? 0);
        
        try {
            // 1. Сохранение основной сделки
            if ($deal_id > 0) {
                $result = $wpdb->update($deals_table, $deal_data, ['id' => $deal_id]);
            } else {
                $deal_data['created_at'] = current_time('mysql');
                $result = $wpdb->insert($deals_table, $deal_data);
                $deal_id = $wpdb->insert_id;
            }
            
            if ($result === false) {
                throw new Exception('Ошибка БД: ' . $wpdb->last_error);
            }
            
            // 2. Обработка запчастей (авто-списание)
            if (!empty($_POST['parts'])) {
                $parts = $this->safe_json_decode(stripslashes($_POST['parts']));
                
                if (is_array($parts)) {
                    // Удаляем старые привязки запчастей к этой сделке
                    $wpdb->delete($deal_parts_table, ['deal_id' => $deal_id]);
                    
                    foreach ($parts as $part) {
                        $part_id = intval($part['id'] ?? 0);
                        $quantity = intval($part['quantity'] ?? 0);
                        $price = floatval($part['price'] ?? 0);
                        
                        if ($quantity > 0 && $part_id > 0) {
                            // Записываем в историю сделки
                            $wpdb->insert($deal_parts_table, [
                                'deal_id' => $deal_id,
                                'part_id' => $part_id,
                                'quantity' => $quantity,
                                'price' => $price,
                                'created_at' => current_time('mysql')
                            ]);
                            
                            // Списание со склада (только если остаток позволяет)
                            $wpdb->query($wpdb->prepare(
                                "UPDATE {$parts_table} 
                                 SET quantity = quantity - %d 
                                 WHERE id = %d AND quantity >= %d",
                                $quantity, $part_id, $quantity
                            ));
                        }
                    }
                }
            }
            
            $this->log_event("Сделка #{$deal_id} сохранена");
            
            wp_send_json_success([
                'message' => 'Сделка успешно сохранена',
                'deal_id' => $deal_id,
                'redirect_url' => admin_url('admin.php?page=akpp-crm-deals')
            ]);
            
        } catch (Exception $e) {
            $this->log_event('Save deal error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Декодирование VIN через бесплатный API NHTSA
     */
    public function ajax_decode_vin() {
        if (!check_ajax_referer('akpp_vin_decode_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) {
            return;
        }
        
        $vin = isset($_POST['vin']) ? strtoupper(sanitize_text_field($_POST['vin'])) : '';
        
        // Валидация VIN (17 символов, без I, O, Q)
        if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
            wp_send_json_error(['message' => 'Некорректный формат VIN (17 символов, без I, O, Q)'], 400);
            return;
        }
        
        global $wpdb;
        $cache_table = $wpdb->prefix . 'akpp_vin_cache';
        
        try {
            // 1. Проверка кэша
            $cached = $wpdb->get_row($wpdb->prepare(
                "SELECT make, model, year, body_number FROM {$cache_table} WHERE vin = %s",
                $vin
            ), ARRAY_A);
            
            if ($cached) {
                wp_send_json_success($cached);
                return;
            }
            
            // 2. Запрос к NHTSA API (бесплатный, без ключа)
            $api_url = "https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVinValues/{$vin}?format=json";
            
            $response = wp_remote_get($api_url, ['timeout' => 10]);
            
            if (is_wp_error($response)) {
                throw new Exception('Ошибка соединения с сервисом декодирования');
            }
            
            $body = $this->safe_json_decode(wp_remote_retrieve_body($response));
            
            if (empty($body['Results'][0])) {
                wp_send_json_error(['message' => 'VIN не найден в базе данных'], 404);
                return;
            }
            
            $data = $body['Results'][0];
            
            // 3. Формирование ответа
            $result = [
                'make'        => sanitize_text_field($data['Make'] ?? ''),
                'model'       => sanitize_text_field($data['Model'] ?? ''),
                'year'        => intval($data['ModelYear'] ?? 0),
                'body_number' => sanitize_text_field($data['BodyClass'] ?? '')
            ];
            
            // 4. Сохранение в кэш
            $wpdb->insert($cache_table, [
                'vin' => $vin,
                'make' => $result['make'],
                'model' => $result['model'],
                'year' => $result['year'],
                'body_number' => $result['body_number'],
                'cached_at' => current_time('mysql')
            ]);
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            $this->log_event('VIN decode error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 3. ПАРСЕР
    // =========================================================================

    /**
     * AJAX: Парсинг URL
     */
    public function ajax_parse_url() {
        if (!check_ajax_referer('akpp_parse_url_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions('manage_options')) {
            return;
        }
        
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(['message' => 'Некорректный URL'], 400);
            return;
        }
        
        try {
            if (!class_exists('AKPP_Parser')) {
                require_once AKPP_CRM_PATH . 'class-akpp-parser.php';
            }
            
            $parser = AKPP_Parser::get_instance();
            $result = $parser->parse($url);
            
            if ($result) {
                $this->log_event("Парсинг выполнен: {$url}");
                $this->trigger_ai_analysis($result['id']);
                
                wp_send_json_success([
                    'message' => 'Парсинг успешно выполнен',
                    'item_id' => $result['id'],
                    'title' => esc_html($result['title']),
                    'content_type' => esc_html($result['content_type']),
                    'text_preview' => esc_html(mb_substr($result['text'], 0, 300)) . '...',
                    'images_count' => count($result['images'])
                ]);
            } else {
                wp_send_json_error(['message' => 'Ошибка парсинга URL. Проверьте доступность сайта.']);
            }
            
        } catch (Exception $e) {
            $this->log_event('Parse URL error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка парсинга: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Запуск AI анализа в фоне
     */
    private function trigger_ai_analysis($item_id) {
        if (!wp_next_scheduled('akpp_ai_analysis_event', [$item_id])) {
            wp_schedule_single_event(time(), 'akpp_ai_analysis_event', [$item_id]);
        }
    }

    /**
     * Обработка фоновой задачи AI анализа
     */
    public function process_ai_analysis_event($item_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $item_id
        ));
        
        if (!$item) {
            return;
        }
        
        try {
            if (!class_exists('AKPP_AI_Analyzer')) {
                require_once AKPP_CRM_PATH . 'ai/class-ai-analyzer.php';
            }
            
            $analyzer = AKPP_AI_Analyzer::get_instance();
            $result = $analyzer->analyze($item->content, $item->content_type);
            
            $wpdb->update(
                $table,
                [
                    'ai_analysis' => wp_json_encode($result, JSON_UNESCAPED_UNICODE),
                    'status' => 'ai_processed',
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $item_id],
                ['%s', '%s', '%s'],
                ['%d']
            );
            
            $this->log_event("AI анализ выполнен для элемента #{$item_id}");
            
        } catch (Exception $e) {
            $this->log_event('AI analysis error: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * AJAX: Получение списка элементов парсера
     */
    public function ajax_get_parser_items() {
        if (!check_ajax_referer('akpp_get_parser_items_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions('manage_options')) {
            return;
        }
        
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all';
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $limit = isset($_POST['limit']) ? min(100, max(1, intval($_POST['limit']))) : 20;
        $offset = ($page - 1) * $limit;
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        try {
            $where = '';
            $where_args = [];
            
            if ($status !== 'all') {
                $where = "WHERE status = %s";
                $where_args[] = $status;
            }
            
            // Безопасный запрос с prepare
            if (!empty($where_args)) {
                $total = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} {$where}",
                    ...$where_args
                ));
                
                $items = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    ...array_merge($where_args, [$limit, $offset])
                ));
            } else {
                $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
                $items = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    $limit, $offset
                ));
            }
            
            // Безопасное декодирование JSON полей
            foreach ($items as $item) {
                $item->images = $this->safe_json_decode($item->images) ?: [];
                $item->ai_analysis = $this->safe_json_decode($item->ai_analysis) ?: [];
                $item->parsed_data = $this->safe_json_decode($item->parsed_data) ?: [];
            }
            
            wp_send_json_success([
                'items' => $items,
                'total' => intval($total),
                'page' => $page,
                'pages' => ceil($total / $limit)
            ]);
            
        } catch (Exception $e) {
            $this->log_event('Get parser items error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка получения списка'], 500);
        }
    }

    // =========================================================================
    // 4. AI АНАЛИЗ
    // =========================================================================

    /**
     * AJAX: Запуск AI анализа для элемента парсера
     */
    public function ajax_run_ai_analysis() {
        if (!check_ajax_referer('akpp_run_ai_analysis_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions('manage_options')) {
            return;
        }
        
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        
        if (!$item_id) {
            wp_send_json_error(['message' => 'ID элемента не передан'], 400);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        try {
            $item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $item_id
            ));
            
            if (!$item) {
                wp_send_json_error(['message' => 'Элемент не найден'], 404);
                return;
            }
            
            if (!class_exists('AKPP_AI_Analyzer')) {
                require_once AKPP_CRM_PATH . 'ai/class-ai-analyzer.php';
            }
            
            $analyzer = AKPP_AI_Analyzer::get_instance();
            $result = $analyzer->analyze($item->content, $item->content_type);
            
            $wpdb->update(
                $table,
                [
                    'ai_analysis' => wp_json_encode($result, JSON_UNESCAPED_UNICODE),
                    'status' => 'ai_processed',
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $item_id],
                ['%s', '%s', '%s'],
                ['%d']
            );
            
            $this->log_event("AI анализ выполнен для элемента #{$item_id}");
            
            wp_send_json_success([
                'message' => 'AI анализ успешно выполнен',
                'analysis' => $result
            ]);
            
        } catch (Exception $e) {
            $this->log_event('Run AI analysis error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка AI анализа: ' . $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Получение статистики AI анализов
     */
    public function ajax_get_ai_statistics() {
        if (!check_ajax_referer('akpp_ai_stats_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions('manage_options')) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        try {
            $stats = [
                'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
                'pending' => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'pending'
                )),
                'ai_processed' => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'ai_processed'
                )),
                'approved' => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'approved'
                )),
                'rejected' => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'rejected'
                ))
            ];
            
            // Статистика по типам контента
            $content_types = $wpdb->get_results(
                "SELECT content_type, COUNT(*) as count FROM {$table} GROUP BY content_type"
            );
            
            $stats['by_content_type'] = [];
            foreach ($content_types as $type) {
                $stats['by_content_type'][esc_html($type->content_type)] = (int) $type->count;
            }
            
            wp_send_json_success($stats);
            
        } catch (Exception $e) {
            $this->log_event('Get AI statistics error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка получения статистики'], 500);
        }
    }

    // =========================================================================
    // 5. TELEGRAM
    // =========================================================================

    /**
     * AJAX: Сохранение настроек Telegram
     */
    public function ajax_save_telegram_settings() {
        if (!check_ajax_referer('akpp_telegram_settings_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions('manage_options')) {
            return;
        }
        
        $bot_token = isset($_POST['bot_token']) ? sanitize_text_field($_POST['bot_token']) : '';
        
        if (empty($bot_token)) {
            wp_send_json_error(['message' => 'Bot Token не может быть пустым'], 400);
            return;
        }
        
        try {
            if (!class_exists('AKPP_Telegram')) {
                require_once AKPP_CRM_PATH . 'class-akpp-telegram.php';
            }
            
            $telegram = AKPP_Telegram::get_instance();
            $result = $telegram->save_settings($bot_token);
            
            if ($result) {
                $this->log_event("Настройки Telegram сохранены");
                wp_send_json_success(['message' => 'Настройки Telegram сохранены, webhook установлен']);
            } else {
                wp_send_json_error(['message' => 'Ошибка сохранения настроек']);
            }
            
        } catch (Exception $e) {
            $this->log_event('Save Telegram settings error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка сохранения настроек: ' . $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Отправка тестового сообщения
     */
    public function ajax_send_test_telegram() {
        if (!check_ajax_referer('akpp_telegram_settings_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions('manage_options')) {
            return;
        }
        
        $admin_chat_id = get_option('akpp_telegram_admin_chat_id', '');
        
        if (empty($admin_chat_id)) {
            wp_send_json_error(['message' => 'Сначала настройте бота и получите chat_id администратора']);
            return;
        }
        
        try {
            if (!class_exists('AKPP_Telegram')) {
                require_once AKPP_CRM_PATH . 'class-akpp-telegram.php';
            }
            
            $telegram = AKPP_Telegram::get_instance();
            $result = $telegram->send_message($admin_chat_id, '✅ Тестовое сообщение от CRM АКПП45! Бот работает корректно.');
            
            if ($result) {
                wp_send_json_success(['message' => 'Тестовое сообщение отправлено']);
            } else {
                wp_send_json_error(['message' => 'Ошибка отправки сообщения']);
            }
            
        } catch (Exception $e) {
            $this->log_event('Send test Telegram error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка отправки: ' . $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Установка webhook
     */
    public function ajax_set_telegram_webhook() {
        if (!check_ajax_referer('akpp_telegram_settings_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions('manage_options')) {
            return;
        }
        
        try {
            if (!class_exists('AKPP_Telegram')) {
                require_once AKPP_CRM_PATH . 'class-akpp-telegram.php';
            }
            
            $telegram = AKPP_Telegram::get_instance();
            $result = $telegram->set_webhook();
            
            if ($result) {
                wp_send_json_success(['message' => 'Webhook успешно установлен']);
            } else {
                wp_send_json_error(['message' => 'Ошибка установки webhook. Проверьте Bot Token']);
            }
            
        } catch (Exception $e) {
            $this->log_event('Set Telegram webhook error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка установки webhook: ' . $e->getMessage()], 500);
        }
    }
}
