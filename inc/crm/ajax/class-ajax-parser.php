<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX модуль: Парсер + AI анализ
 * Парсинг страниц, AI анализ через Qwen API, управление элементами парсера
 */
class AKPP_AJAX_Parser extends AKPP_AJAX_Base {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->register_hooks();
    }
    
    /**
     * Регистрация AJAX хуков
     */
    private function register_hooks() {
        // Парсер
        add_action('wp_ajax_akpp_parse_url', [$this, 'ajax_parse_url']);
        add_action('wp_ajax_akpp_parse_multiple', [$this, 'ajax_parse_multiple']);
        add_action('wp_ajax_akpp_get_parser_item', [$this, 'ajax_get_parser_item']);
        add_action('wp_ajax_akpp_save_parser_item', [$this, 'ajax_save_parser_item']);
        add_action('wp_ajax_akpp_delete_parser_item', [$this, 'ajax_delete_parser_item']);
        add_action('wp_ajax_akpp_update_parser_status', [$this, 'ajax_update_parser_status']);
        
        // AI анализ
        add_action('wp_ajax_akpp_ai_analyze', [$this, 'ajax_ai_analyze']);
        add_action('wp_ajax_akpp_ai_analyze_batch', [$this, 'ajax_ai_analyze_batch']);
        add_action('wp_ajax_akpp_ai_approve', [$this, 'ajax_ai_approve']);
        add_action('wp_ajax_akpp_ai_reject', [$this, 'ajax_ai_reject']);
        
        // Тестирование
        add_action('wp_ajax_akpp_test_parser', [$this, 'ajax_test_parser']);
        add_action('wp_ajax_akpp_test_ai', [$this, 'ajax_test_ai']);
    }
    
    // ========================================================================
    // ПАРСИНГ
    // ========================================================================
    
    public function ajax_parse_url() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        $url = esc_url_raw($_POST['url'] ?? '');
        $content_type = sanitize_text_field($_POST['content_type'] ?? 'general');
        
        if (empty($url)) {
            wp_send_json_error(['message' => 'URL обязателен']);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        // Проверяем существование
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE url = %s",
            $url
        ));
        
        if ($existing) {
            wp_send_json_error(['message' => 'URL уже в базе (ID: ' . $existing . ')', 'id' => $existing]);
            return;
        }
        
        // Парсинг
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Ошибка загрузки: ' . $response->get_error_message()]);
            return;
        }
        
        $html = wp_remote_retrieve_body($response);
        $title = '';
        $content = '';
        $images = '';
        
        // Извлечение title
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $title = trim($matches[1]);
        }
        
        // Извлечение meta description
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            $content = $matches[1];
        }
        
        // Извлечение изображений
        $images_arr = [];
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+\.(?:jpg|jpeg|png|webp))["\']/i', $html, $matches)) {
            $images_arr = array_unique($matches[1]);
            $images = implode("\n", array_slice($images_arr, 0, 10));
        }
        
        // Сохранение
        $wpdb->insert($table, [
            'url' => $url,
            'title' => $title,
            'content' => $content,
            'images' => $images,
            'content_type' => $content_type,
            'status' => 'parsed',
            'created_at' => current_time('mysql')
        ]);
        
        wp_send_json_success([
            'message' => 'Страница распарсена',
            'id' => $wpdb->insert_id,
            'title' => $title,
            'images_count' => count($images_arr)
        ]);
    }
    
    public function ajax_parse_multiple() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        $urls_text = sanitize_textarea_field($_POST['urls'] ?? '');
        $content_type = sanitize_text_field($_POST['content_type'] ?? 'general');
        
        $urls = array_filter(array_map('trim', explode("\n", $urls_text)));
        
        if (empty($urls)) {
            wp_send_json_error(['message' => 'Список URL пуст']);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        $parsed = 0;
        $skipped = 0;
        $errors = 0;
        
        foreach ($urls as $url) {
            $url = esc_url_raw($url);
            if (empty($url)) continue;
            
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE url = %s",
                $url
            ));
            
            if ($existing) {
                $skipped++;
                continue;
            }
            
            $response = wp_remote_get($url, ['timeout' => 30]);
            if (is_wp_error($response)) {
                $errors++;
                continue;
            }
            
            $html = wp_remote_retrieve_body($response);
            $title = '';
            $content = '';
            $images = '';
            
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
                $title = trim($matches[1]);
            }
            
            if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
                $content = $matches[1];
            }
            
            if (preg_match_all('/<img[^>]+src=["\']([^"\']+\.(?:jpg|jpeg|png|webp))["\']/i', $html, $matches)) {
                $images = implode("\n", array_unique(array_slice($matches[1], 0, 10)));
            }
            
            $wpdb->insert($table, [
                'url' => $url,
                'title' => $title,
                'content' => $content,
                'images' => $images,
                'content_type' => $content_type,
                'status' => 'parsed',
                'created_at' => current_time('mysql')
            ]);
            
            $parsed++;
        }
        
        wp_send_json_success([
            'message' => "Обработано: {$parsed}, пропущено: {$skipped}, ошибок: {$errors}",
            'parsed' => $parsed,
            'skipped' => $skipped,
            'errors' => $errors
        ]);
    }
    
    // ========================================================================
    // УПРАВЛЕНИЕ ЭЛЕМЕНТАМИ ПАРСЕРА
    // ========================================================================
    
    public function ajax_get_parser_item() {
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$item) {
            wp_send_json_error(['message' => 'Элемент не найден']);
            return;
        }
        
        wp_send_json_success(['item' => $item]);
    }
    
    public function ajax_save_parser_item() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $data = [
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'content' => sanitize_textarea_field($_POST['content'] ?? ''),
            'images' => sanitize_textarea_field($_POST['images'] ?? ''),
            'content_type' => sanitize_text_field($_POST['content_type'] ?? 'general'),
            'updated_at' => current_time('mysql')
        ];
        
        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id]);
        } else {
            $data['url'] = esc_url_raw($_POST['url'] ?? '');
            $data['status'] = 'parsed';
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
            $id = $wpdb->insert_id;
        }
        
        wp_send_json_success(['message' => 'Элемент сохранён', 'id' => $id]);
    }
    
    public function ajax_delete_parser_item() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $wpdb->delete($table, ['id' => $id]);
        
        wp_send_json_success(['message' => 'Элемент удалён']);
    }
    
    public function ajax_update_parser_status() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $valid_statuses = ['parsed', 'ai_processed', 'approved', 'rejected', 'pending'];
        if (!in_array($status, $valid_statuses)) {
            wp_send_json_error(['message' => 'Недопустимый статус']);
            return;
        }
        
        $wpdb->update($table, ['status' => $status, 'updated_at' => current_time('mysql')], ['id' => $id]);
        
        wp_send_json_success(['message' => 'Статус обновлён']);
    }
    
    // ========================================================================
    // AI АНАЛИЗ
    // ========================================================================
    
    public function ajax_ai_analyze() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$item) {
            wp_send_json_error(['message' => 'Элемент не найден']);
            return;
        }
        
        $api_key = get_option('akpp_openai_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API ключ не настроен']);
            return;
        }
        
        // Формируем промпт
        $prompt = "Проанализируй страницу:\n\n";
        $prompt .= "URL: {$item['url']}\n";
        $prompt .= "Заголовок: {$item['title']}\n";
        $prompt .= "Описание: {$item['content']}\n\n";
        $prompt .= "Дай краткий анализ: тематика, целевая аудитория, SEO-потенциал, рекомендации. Формат JSON с полями: topic, audience, seo_score (1-10), recommendations.";
        
        // Вызов Qwen API (совместимый с OpenAI формат)
        $response = wp_remote_post('https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'qwen-plus',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7
            ])
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Ошибка API: ' . $response->get_error_message()]);
            return;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $ai_text = $body['choices'][0]['message']['content'] ?? '';
        
        $wpdb->update($table, [
            'ai_analysis' => $ai_text,
            'status' => 'ai_processed',
            'updated_at' => current_time('mysql')
        ], ['id' => $id]);
        
        wp_send_json_success([
            'message' => 'AI анализ завершён',
            'analysis' => $ai_text
        ]);
    }
    
    public function ajax_ai_analyze_batch() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        $status_filter = sanitize_text_field($_POST['status'] ?? 'parsed');
        $limit = min(50, intval($_POST['limit'] ?? 10));
        
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$table} WHERE status = %s LIMIT %d",
            $status_filter, $limit
        ), ARRAY_A);
        
        if (empty($items)) {
            wp_send_json_error(['message' => 'Нет элементов для анализа']);
            return;
        }
        
        $processed = 0;
        foreach ($items as $item) {
            $_POST['id'] = $item['id'];
            $this->ajax_ai_analyze();
            $processed++;
        }
        
        wp_send_json_success(['message' => "Обработано: {$processed}"]);
    }
    
    public function ajax_ai_approve() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $wpdb->update($table, ['status' => 'approved', 'updated_at' => current_time('mysql')], ['id' => $id]);
        
        wp_send_json_success(['message' => 'Элемент одобрен']);
    }
    
    public function ajax_ai_reject() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $wpdb->update($table, ['status' => 'rejected', 'updated_at' => current_time('mysql')], ['id' => $id]);
        
        wp_send_json_success(['message' => 'Элемент отклонён']);
    }
    
    // ========================================================================
    // ТЕСТИРОВАНИЕ
    // ========================================================================
    
    public function ajax_test_parser() {
        if (!$this->check_permissions()) return;
        
        $test_url = 'https://akpp45.ru';
        $response = wp_remote_get($test_url, ['timeout' => 10]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Ошибка: ' . $response->get_error_message()]);
            return;
        }
        
        $html = wp_remote_retrieve_body($response);
        $title = '';
        preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches);
        if (!empty($matches[1])) $title = trim($matches[1]);
        
        wp_send_json_success([
            'message' => 'Парсер работает',
            'url' => $test_url,
            'title' => $title,
            'html_length' => strlen($html)
        ]);
    }
    
    public function ajax_test_ai() {
        if (!$this->check_permissions()) return;
        
        $api_key = get_option('akpp_openai_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API ключ не настроен']);
            return;
        }
        
        $response = wp_remote_post('https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'qwen-plus',
                'messages' => [
                    ['role' => 'user', 'content' => 'Скажи "OK" если получаешь сообщение']
                ]
            ])
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Ошибка API: ' . $response->get_error_message()]);
            return;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $reply = $body['choices'][0]['message']['content'] ?? '';
        
        wp_send_json_success([
            'message' => 'AI API работает',
            'reply' => $reply
        ]);
    }
}