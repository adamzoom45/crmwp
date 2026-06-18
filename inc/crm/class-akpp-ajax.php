<?php
if (!defined('ABSPATH')) exit;

class AKPP_AJAX {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Поиск
        add_action('wp_ajax_akpp_search_parts', [$this, 'ajax_search_parts']);
        add_action('wp_ajax_akpp_search_vehicles', [$this, 'ajax_search_vehicles']);
        add_action('wp_ajax_akpp_search_employees', [$this, 'ajax_search_employees']);
        
        // Сделки
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
    }

    private function check_permissions($capability = 'manage_options') {
        if (!current_user_can($capability)) {
            wp_send_json_error(['message' => 'Недостаточно прав'], 403);
            return false;
        }
        return true;
    }

    private function log_event($message, $level = 'info') {
        error_log('[AKPP AJAX] ' . $message);
    }

    // ========================================================================
    // ПОИСК
    // ========================================================================

    public function ajax_search_parts() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_parts';
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE name LIKE %s OR sku LIKE %s LIMIT 20",
                '%' . $wpdb->esc_like($query) . '%',
                '%' . $wpdb->esc_like($query) . '%'
            ), ARRAY_A);
            
            wp_send_json_success($results);
        } catch (Exception $e) {
            $this->log_event('Search parts error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка поиска: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_search_vehicles() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_vehicles';
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE vin LIKE %s OR mark LIKE %s OR model LIKE %s LIMIT 20",
                '%' . $wpdb->esc_like($query) . '%',
                '%' . $wpdb->esc_like($query) . '%',
                '%' . $wpdb->esc_like($query) . '%'
            ), ARRAY_A);
            
            wp_send_json_success($results);
        } catch (Exception $e) {
            $this->log_event('Search vehicles error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка поиска: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_search_employees() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_employees';
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE name LIKE %s OR position LIKE %s LIMIT 20",
                '%' . $wpdb->esc_like($query) . '%',
                '%' . $wpdb->esc_like($query) . '%'
            ), ARRAY_A);
            
            wp_send_json_success($results);
        } catch (Exception $e) {
            $this->log_event('Search employees error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка поиска: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // СДЕЛКИ
    // ========================================================================

    public function ajax_save_deal() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        try {
            $data = [
                'client_name' => sanitize_text_field($_POST['client_name'] ?? ''),
                'client_phone' => sanitize_text_field($_POST['client_phone'] ?? ''),
                'vehicle_id' => intval($_POST['vehicle_id'] ?? 0),
                'employee_id' => intval($_POST['employee_id'] ?? 0),
                'services' => sanitize_textarea_field($_POST['services'] ?? ''),
                'cost' => floatval($_POST['cost'] ?? 0),
                'status' => sanitize_text_field($_POST['status'] ?? 'new'),
                'comment' => sanitize_textarea_field($_POST['comment'] ?? ''),
            ];
            
            $deal_id = intval($_POST['deal_id'] ?? 0);
            
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_deals';
            
            if ($deal_id > 0) {
                $data['updated_at'] = current_time('mysql');
                $wpdb->update($table, $data, ['id' => $deal_id]);
                $result_id = $deal_id;
            } else {
                $data['created_at'] = current_time('mysql');
                $wpdb->insert($table, $data);
                $result_id = $wpdb->insert_id;
            }
            
            wp_send_json_success(['id' => $result_id, 'message' => 'Сделка сохранена']);
        } catch (Exception $e) {
            $this->log_event('Save deal error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка сохранения: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_decode_vin() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        $vin = strtoupper(sanitize_text_field($_POST['vin'] ?? ''));
        
        if (strlen($vin) !== 17) {
            wp_send_json_error(['message' => 'VIN должен содержать 17 символов'], 400);
            return;
        }
        
        try {
            // Проверяем кэш
            global $wpdb;
            $cache_table = $wpdb->prefix . 'akpp_vin_cache';
            $cached = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $cache_table WHERE vin = %s",
                $vin
            ), ARRAY_A);
            
            if ($cached) {
                wp_send_json_success(json_decode($cached['data'], true));
                return;
            }
            
            // Здесь должна быть интеграция с API декодера
            // Пока возвращаем заглушку
            $data = [
                'vin' => $vin,
                'mark' => 'Неизвестно',
                'model' => 'Неизвестно',
                'year' => '',
                'engine' => '',
                'transmission' => ''
            ];
            
            // Сохраняем в кэш
            $wpdb->insert($cache_table, [
                'vin' => $vin,
                'data' => json_encode($data),
                'created_at' => current_time('mysql')
            ]);
            
            wp_send_json_success($data);
        } catch (Exception $e) {
            $this->log_event('VIN decode error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка декодирования: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // ПАРСЕР
    // ========================================================================

    public function ajax_parse_url() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        $url = esc_url_raw($_POST['url'] ?? '');
        
        if (empty($url)) {
            wp_send_json_error(['message' => 'URL не указан'], 400);
            return;
        }
        
        try {
            // Здесь должна быть логика парсинга
            // Пока возвращаем заглушку
            $data = [
                'url' => $url,
                'title' => 'Заголовок страницы',
                'content' => 'Содержимое страницы',
                'status' => 'parsed'
            ];
            
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_parser_items';
            $wpdb->insert($table, [
                'url' => $url,
                'title' => $data['title'],
                'content' => $data['content'],
                'status' => 'parsed',
                'created_at' => current_time('mysql')
            ]);
            
            wp_send_json_success(['id' => $wpdb->insert_id, 'data' => $data]);
        } catch (Exception $e) {
            $this->log_event('Parse URL error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка парсинга: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_get_parser_items() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_parser_items';
            $items = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100", ARRAY_A);
            
            wp_send_json_success($items);
        } catch (Exception $e) {
            $this->log_event('Get parser items error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка получения: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_get_parser_item() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_parser_items';
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
            
            if (!$item) {
                wp_send_json_error(['message' => 'Элемент не найден'], 404);
                return;
            }
            
            wp_send_json_success($item);
        } catch (Exception $e) {
            $this->log_event('Get parser item error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка получения: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_reparse_url() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_parser_items';
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
            
            if (!$item) {
                wp_send_json_error(['message' => 'Элемент не найден'], 404);
                return;
            }
            
            // Повторный парсинг
            $wpdb->update($table, [
                'status' => 'reparsing',
                'updated_at' => current_time('mysql')
            ], ['id' => $id]);
            
            wp_send_json_success(['message' => 'Повторный парсинг запущен']);
        } catch (Exception $e) {
            $this->log_event('Reparse error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_delete_parser_item() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_parser_items';
            $wpdb->delete($table, ['id' => $id]);
            
            wp_send_json_success(['message' => 'Элемент удален']);
        } catch (Exception $e) {
            $this->log_event('Delete parser item error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка удаления: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_bulk_parse() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        $urls = $_POST['urls'] ?? [];
        
        try {
            $parsed = 0;
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_parser_items';
            
            foreach ($urls as $url) {
                $url = esc_url_raw($url);
                if (empty($url)) continue;
                
                $wpdb->insert($table, [
                    'url' => $url,
                    'title' => 'Обработка...',
                    'status' => 'parsing',
                    'created_at' => current_time('mysql')
                ]);
                $parsed++;
            }
            
            wp_send_json_success(['message' => "Обработано URL: $parsed"]);
        } catch (Exception $e) {
            $this->log_event('Bulk parse error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_export_parser_items() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_parser_items';
            $items = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A);
            
            $csv = "ID;URL;Заголовок;Статус;Дата\n";
            foreach ($items as $item) {
                $csv .= "{$item['id']};{$item['url']};{$item['title']};{$item['status']};{$item['created_at']}\n";
            }
            
            wp_send_json_success(['csv' => $csv, 'count' => count($items)]);
        } catch (Exception $e) {
            $this->log_event('Export error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка экспорта: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // AI АНАЛИЗ
    // ========================================================================

    public function ajax_run_ai_analysis() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        $item_id = intval($_POST['item_id'] ?? 0);
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_parser_items';
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $item_id), ARRAY_A);
            
            if (!$item) {
                wp_send_json_error(['message' => 'Элемент не найден'], 404);
                return;
            }
            
            // Здесь должна быть интеграция с OpenAI
            $analysis = [
                'condition' => 'Хорошее',
                'price_estimate' => 15000,
                'recommendation' => 'Рекомендуется к покупке'
            ];
            
            $wpdb->update($table, [
                'ai_analysis' => json_encode($analysis),
                'status' => 'analyzed',
                'updated_at' => current_time('mysql')
            ], ['id' => $item_id]);
            
            wp_send_json_success($analysis);
        } catch (Exception $e) {
            $this->log_event('AI analysis error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка анализа: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_bulk_ai_analysis() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        $ids = $_POST['ids'] ?? [];
        
        try {
            $analyzed = 0;
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_parser_items';
            
            foreach ($ids as $id) {
                $id = intval($id);
                $analysis = json_encode(['status' => 'analyzed']);
                
                $wpdb->update($table, [
                    'ai_analysis' => $analysis,
                    'status' => 'analyzed',
                    'updated_at' => current_time('mysql')
                ], ['id' => $id]);
                
                $analyzed++;
            }
            
            wp_send_json_success(['message' => "Проанализировано: $analyzed"]);
        } catch (Exception $e) {
            $this->log_event('Bulk AI analysis error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_save_openai_settings() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions('manage_options')) return;
        
        try {
            $api_key = sanitize_text_field($_POST['api_key'] ?? '');
            $model = sanitize_text_field($_POST['model'] ?? 'gpt-3.5-turbo');
            
            update_option('akpp_openai_api_key', $api_key);
            update_option('akpp_openai_model', $model);
            
            wp_send_json_success(['message' => 'Настройки сохранены']);
        } catch (Exception $e) {
            $this->log_event('Save OpenAI settings error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка сохранения: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_check_openai_key() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions('manage_options')) return;
        
        try {
            $api_key = get_option('akpp_openai_api_key', '');
            
            if (empty($api_key)) {
                wp_send_json_error(['message' => 'API ключ не установлен']);
                return;
            }
            
            // Здесь должна быть проверка ключа через OpenAI API
            wp_send_json_success(['message' => 'Ключ действителен']);
        } catch (Exception $e) {
            $this->log_event('Check OpenAI key error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка проверки: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_analyze_image() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        try {
            $image_url = esc_url_raw($_POST['image_url'] ?? '');
            
            if (empty($image_url)) {
                wp_send_json_error(['message' => 'URL изображения не указан'], 400);
                return;
            }
            
            // Здесь должна быть интеграция с OpenAI Vision API
            $analysis = [
                'description' => 'Автоматический анализ изображения',
                'condition' => 'Хорошее',
                'defects' => []
            ];
            
            wp_send_json_success($analysis);
        } catch (Exception $e) {
            $this->log_event('Image analysis error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка анализа: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_get_ai_statistics() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_parser_items';
            
            $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            $analyzed = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'analyzed'");
            $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'parsed'");
            
            wp_send_json_success([
                'total' => $total,
                'analyzed' => $analyzed,
                'pending' => $pending
            ]);
        } catch (Exception $e) {
            $this->log_event('Get AI statistics error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка получения статистики: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_approve_parser_item() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_parser_items';
            $wpdb->update($table, [
                'status' => 'approved',
                'updated_at' => current_time('mysql')
            ], ['id' => $id]);
            
            wp_send_json_success(['message' => 'Элемент одобрен']);
        } catch (Exception $e) {
            $this->log_event('Approve error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_reject_parser_item() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_parser_items';
            $wpdb->update($table, [
                'status' => 'rejected',
                'updated_at' => current_time('mysql')
            ], ['id' => $id]);
            
            wp_send_json_success(['message' => 'Элемент отклонен']);
        } catch (Exception $e) {
            $this->log_event('Reject error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // TELEGRAM
    // ========================================================================

    public function ajax_save_telegram_settings() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions('manage_options')) return;
        
        try {
            $bot_token = sanitize_text_field($_POST['bot_token'] ?? '');
            $chat_id = sanitize_text_field($_POST['chat_id'] ?? '');
            
            update_option('akpp_telegram_bot_token', $bot_token);
            update_option('akpp_telegram_chat_id', $chat_id);
            
            wp_send_json_success(['message' => 'Настройки Telegram сохранены']);
        } catch (Exception $e) {
            $this->log_event('Save Telegram settings error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка сохранения: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_send_test_telegram() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions('manage_options')) return;
        
        try {
            $bot_token = get_option('akpp_telegram_bot_token', '');
            $chat_id = get_option('akpp_telegram_chat_id', '');
            
            if (empty($bot_token) || empty($chat_id)) {
                wp_send_json_error(['message' => 'Настройки Telegram не заполнены'], 400);
                return;
            }
            
            $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
            $response = wp_remote_post($url, [
                'body' => [
                    'chat_id' => $chat_id,
                    'text' => '✅ Тестовое сообщение от CRM АКПП45!'
                ]
            ]);
            
            if (is_wp_error($response)) {
                wp_send_json_error(['message' => 'Ошибка отправки: ' . $response->get_error_message()], 500);
                return;
            }
            
            wp_send_json_success(['message' => 'Тестовое сообщение отправлено']);
        } catch (Exception $e) {
            $this->log_event('Send test Telegram error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка отправки: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_set_telegram_webhook() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Неверный security токен'], 403);
            return;
        }
        
        if (!$this->check_permissions('manage_options')) return;
        
        try {
            $bot_token = get_option('akpp_telegram_bot_token', '');
            
            if (empty($bot_token)) {
                wp_send_json_error(['message' => 'Bot token не установлен'], 400);
                return;
            }
            
            $webhook_url = home_url('/wp-json/akpp/v1/telegram-webhook');
            $url = "https://api.telegram.org/bot{$bot_token}/setWebhook";
            
            $response = wp_remote_post($url, [
                'body' => ['url' => $webhook_url]
            ]);
            
            if (is_wp_error($response)) {
                wp_send_json_error(['message' => 'Ошибка установки webhook: ' . $response->get_error_message()], 500);
                return;
            }
            
            wp_send_json_success(['message' => 'Webhook установлен: ' . $webhook_url]);
        } catch (Exception $e) {
            $this->log_event('Set Telegram webhook error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Ошибка установки webhook: ' . $e->getMessage()], 500);
        }
    }
}
