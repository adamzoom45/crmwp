<?php
/**
 * Класс для AI анализа контента через OpenAI API
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_AI_Analyzer {
    
    private static $instance = null;
    private $api_key = '';
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    private $model = 'gpt-3.5-turbo';
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_settings();
        add_action('wp_ajax_akpp_run_ai_analysis', [$this, 'ajax_run_analysis']);
        add_action('wp_ajax_akpp_bulk_ai_analysis', [$this, 'ajax_bulk_analysis']);
        add_action('wp_ajax_akpp_save_openai_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_akpp_check_openai_key', [$this, 'ajax_check_key']);
        add_action('akpp_ai_analysis_event', [$this, 'process_ai_analysis']);
    }
    
    /**
     * Загрузка настроек
     */
    private function load_settings() {
        $this->api_key = get_option('akpp_openai_api_key', '');
    }
    
    /**
     * AJAX: Запуск анализа
     */
    public function ajax_run_analysis() {
        if (!check_ajax_referer('akpp_run_ai_analysis_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        
        if (!$item_id) {
            wp_send_json_error('ID элемента не передан');
            return;
        }
        
        $result = $this->analyze_item($item_id);
        
        if ($result) {
            wp_send_json_success(['message' => 'AI анализ выполнен']);
        } else {
            wp_send_json_error('Ошибка AI анализа');
        }
    }
    
    /**
     * AJAX: Массовый анализ
     */
    public function ajax_bulk_analysis() {
        if (!check_ajax_referer('akpp_bulk_ai_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $items = $wpdb->get_results("SELECT * FROM {$table} WHERE status = 'parsed' ORDER BY created_at ASC LIMIT 10");
        
        $processed = 0;
        foreach ($items as $item) {
            if ($this->analyze_item($item->id)) {
                $processed++;
            }
            usleep(500000);
        }
        
        wp_send_json_success(['message' => "AI анализ выполнен для {$processed} элементов"]);
    }
    
    /**
     * AJAX: Сохранение настроек OpenAI
     */
    public function ajax_save_settings() {
        if (!check_ajax_referer('akpp_openai_settings_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        $api_key = isset($_POST['openai_api_key']) ? sanitize_text_field($_POST['openai_api_key']) : '';
        
        if (empty($api_key)) {
            wp_send_json_error('API ключ не может быть пустым');
            return;
        }
        
        update_option('akpp_openai_api_key', $api_key);
        $this->api_key = $api_key;
        
        $status = $this->check_api_key();
        
        if ($status['valid']) {
            wp_send_json_success(['message' => 'API ключ сохранен и действителен', 'status' => $status]);
        } else {
            wp_send_json_error(['message' => 'API ключ сохранен, но не прошел проверку', 'status' => $status]);
        }
    }
    
    /**
     * AJAX: Проверка ключа
     */
    public function ajax_check_key() {
        if (!check_ajax_referer('akpp_check_openai_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $status = $this->check_api_key();
        wp_send_json_success($status);
    }
    
    /**
     * Анализ элемента
     */
    public function analyze_item($item_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT content, content_type FROM {$table} WHERE id = %d",
            $item_id
        ));
        
        if (!$item) {
            return false;
        }
        
        $result = $this->analyze($item->content, $item->content_type);
        
        if ($result) {
            $wpdb->update(
                $table,
                [
                    'ai_analysis' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    'status' => 'ai_processed',
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $item_id],
                ['%s', '%s', '%s'],
                ['%d']
            );
            return true;
        }
        
        return false;
    }
    
    /**
     * AI анализ текста
     */
    public function analyze($text, $content_type = 'general') {
        if (empty($this->api_key)) {
            return $this->get_mock_analysis($text, $content_type);
        }
        
        $prompt = $this->build_prompt($text, $content_type);
        $response = $this->call_openai_api($prompt);
        
        if ($response) {
            return $this->parse_response($response, $content_type);
        }
        
        return $this->get_mock_analysis($text, $content_type);
    }
    
    /**
     * Построение промпта
     */
    private function build_prompt($text, $content_type) {
        $base = "Ты эксперт по автоматическим коробкам передач (АКПП). Проанализируй текст и верни результат в JSON.\n\n";
        
        $specific = match($content_type) {
            'transmission' => "Определи: тип АКПП, марку, модель, годы выпуска, проблемы, симптомы, причины, запчасти, сложность (1-5), стоимость ремонта",
            'part' => "Определи: тип запчасти, артикул, для каких АКПП подходит, признаки износа, сложность замены (1-5), цену",
            'oil' => "Определи: тип масла (ATF/CVT/DCT), вязкость, спецификации, для каких АКПП подходит, объем заливки, цену за литр",
            default => "Определи: тему, тип проблемы, симптомы, решения, запчасти, полезность (1-5)"
        };
        
        return $base . $specific . "\n\nТекст:\n" . mb_substr($text, 0, 4000);
    }
    
    /**
     * Вызов OpenAI API
     */
    private function call_openai_api($prompt) {
        $args = [
            'method' => 'POST',
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты эксперт по ремонту АКПП. Отвечай только в формате JSON.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.3,
                'max_tokens' => 2000
            ])
        ];
        
        $response = wp_remote_post($this->api_url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('Ошибка API: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            $this->log_error('Ошибка OpenAI: ' . json_encode($data['error']));
            return false;
        }
        
        return $data['choices'][0]['message']['content'] ?? false;
    }
    
    /**
     * Парсинг ответа AI
     */
    private function parse_response($response, $content_type) {
        preg_match('/\{[\s\S]*\}/', $response, $matches);
        
        if (empty($matches)) {
            return $this->get_mock_analysis('', $content_type);
        }
        
        $result = json_decode($matches[0], true);
        
        if (!$result) {
            return $this->get_mock_analysis('', $content_type);
        }
        
        $result['analyzed_at'] = current_time('mysql');
        $result['content_type'] = $content_type;
        $result['confidence'] = $this->calculate_confidence($result);
        
        return $result;
    }
    
    /**
     * Расчет уверенности
     */
    private function calculate_confidence($result) {
        $confidence = 70;
        $key_fields = ['type', 'problems', 'symptoms', 'parts'];
        $present = 0;
        
        foreach ($key_fields as $field) {
            if (!empty($result[$field])) $present++;
        }
        
        return min(95, $confidence + ($present / count($key_fields)) * 20);
    }
    
    /**
     * Мок-анализ (без API ключа)
     */
    private function get_mock_analysis($text, $content_type) {
        $analysis = [
            'analyzed_at' => current_time('mysql'),
            'content_type' => $content_type,
            'is_mock' => true,
            'confidence' => 60
        ];
        
        if ($content_type === 'transmission') {
            $analysis['type'] = $this->extract_pattern($text, '/[46]AT|CVT|DCT/');
            $analysis['problems'] = ['рывки при переключении', 'шум', 'пробуксовка'];
            $analysis['symptoms'] = ['рывки', 'шум', 'вибрация'];
            $analysis['causes'] = ['износ фрикционов', 'загрязнение масла'];
            $analysis['parts'] = ['Ремкомплект АКПП', 'Масло ATF', 'Фильтр'];
            $analysis['repair_cost'] = rand(30000, 150000);
            $analysis['difficulty'] = rand(3, 5);
        } elseif ($content_type === 'part') {
            $analysis['part_type'] = 'Фрикцион';
            $analysis['part_number'] = $this->extract_pattern($text, '/[A-Z0-9]{6,15}/');
            $analysis['transmissions'] = ['A750E', 'U660E'];
            $analysis['avg_price'] = rand(1000, 15000);
        } elseif ($content_type === 'oil') {
            $analysis['oil_type'] = 'ATF';
            $analysis['viscosity'] = 'ATF WS';
            $analysis['specifications'] = ['Toyota WS', 'JWS 3324'];
            $analysis['transmissions'] = ['A750E', 'U660E'];
            $analysis['fill_volume'] = rand(6, 12);
            $analysis['price_per_liter'] = rand(500, 2500);
        } else {
            $analysis['main_topic'] = 'Ремонт АКПП';
            $analysis['key_symptoms'] = ['проблемы с переключением', 'шум'];
            $analysis['solutions'] = ['диагностика', 'замена масла'];
            $analysis['mentioned_parts'] = ['соленоиды', 'фрикционы'];
            $analysis['usefulness'] = rand(3, 5);
        }
        
        return $analysis;
    }
    
    /**
     * Извлечение по паттерну
     */
    private function extract_pattern($text, $pattern) {
        preg_match($pattern, $text, $matches);
        return $matches[0] ?? 'Не определен';
    }
    
    /**
     * Проверка API ключа
     */
    public function check_api_key() {
        if (empty($this->api_key)) {
            return ['valid' => false, 'message' => 'API ключ не настроен'];
        }
        
        $args = [
            'method' => 'GET',
            'timeout' => 10,
            'headers' => ['Authorization' => 'Bearer ' . $this->api_key]
        ];
        
        $response = wp_remote_get('https://api.openai.com/v1/models', $args);
        
        if (is_wp_error($response)) {
            return ['valid' => false, 'message' => 'Ошибка проверки ключа'];
        }
        
        $status = wp_remote_retrieve_response_code($response);
        
        if ($status === 200) {
            return ['valid' => true, 'message' => 'API ключ действителен'];
        } elseif ($status === 401) {
            return ['valid' => false, 'message' => 'Неверный API ключ'];
        }
        
        return ['valid' => false, 'message' => "HTTP ошибка: {$status}"];
    }
    
    /**
     * Фоновая обработка AI анализа
     */
    public function process_ai_analysis($item_id) {
        $this->analyze_item($item_id);
    }
    
    private function log_error($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_AI] ОШИБКА: ' . $message);
        }
    }
}
