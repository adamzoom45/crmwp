<?php
/**
 * Класс для универсального парсинга контента с внешних сайтов
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_Parser {
    
    private static $instance = null;
    
    private $user_agents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36'
    ];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_akpp_parse_url', [$this, 'ajax_parse_url']);
        add_action('wp_ajax_akpp_bulk_parse', [$this, 'ajax_bulk_parse']);
        add_action('wp_ajax_akpp_get_parser_item', [$this, 'ajax_get_parser_item']);
        add_action('wp_ajax_akpp_reparse_url', [$this, 'ajax_reparse_url']);
        add_action('wp_ajax_akpp_delete_parser_item', [$this, 'ajax_delete_parser_item']);
        add_action('wp_ajax_akpp_export_parser_items', [$this, 'ajax_export_parser_items']);
    }
    
    /**
     * AJAX: Парсинг URL
     */
    public function ajax_parse_url() {
        if (!check_ajax_referer('akpp_parse_url_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        
        if (empty($url)) {
            wp_send_json_error('URL не передан');
            return;
        }
        
        $result = $this->parse($url);
        
        if ($result) {
            wp_send_json_success([
                'message' => 'Парсинг успешно выполнен',
                'item_id' => $result['id']
            ]);
        } else {
            wp_send_json_error('Ошибка парсинга URL');
        }
    }
    
    /**
     * AJAX: Массовый парсинг
     */
    public function ajax_bulk_parse() {
        if (!check_ajax_referer('akpp_bulk_parse_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        $urls = isset($_POST['urls']) ? array_map('esc_url_raw', explode("\n", $_POST['urls'])) : [];
        $urls = array_filter($urls);
        
        if (empty($urls)) {
            wp_send_json_error('Нет URL для парсинга');
            return;
        }
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($urls as $url) {
            $result = $this->parse($url);
            if ($result) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
        
        wp_send_json_success([
            'message' => "Парсинг завершен: {$success_count} успешно, {$error_count} ошибок",
            'success_count' => $success_count,
            'error_count' => $error_count
        ]);
    }
    
    /**
     * AJAX: Получение элемента парсера
     */
    public function ajax_get_parser_item() {
        if (!check_ajax_referer('akpp_get_parser_item_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        
        if (!$item_id) {
            wp_send_json_error('ID элемента не передан');
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $item_id));
        
        if ($item) {
            $item->images = json_decode($item->images, true);
            $item->ai_analysis = json_decode($item->ai_analysis, true);
            wp_send_json_success($item);
        } else {
            wp_send_json_error('Элемент не найден');
        }
    }
    
    /**
     * AJAX: Повторный парсинг
     */
    public function ajax_reparse_url() {
        if (!check_ajax_referer('akpp_reparse_url_nonce', 'nonce', false)) {
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
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $item = $wpdb->get_row($wpdb->prepare("SELECT url FROM {$table} WHERE id = %d", $item_id));
        
        if (!$item) {
            wp_send_json_error('Элемент не найден');
            return;
        }
        
        $wpdb->delete($table, ['id' => $item_id]);
        
        $result = $this->parse($item->url);
        
        if ($result) {
            wp_send_json_success(['message' => 'Повторный парсинг выполнен', 'new_item_id' => $result['id']]);
        } else {
            wp_send_json_error('Ошибка повторного парсинга');
        }
    }
    
    /**
     * AJAX: Удаление элемента
     */
    public function ajax_delete_parser_item() {
        if (!check_ajax_referer('akpp_delete_parser_item_nonce', 'nonce', false)) {
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
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $deleted = $wpdb->delete($table, ['id' => $item_id]);
        
        if ($deleted) {
            wp_send_json_success(['message' => 'Элемент удален']);
        } else {
            wp_send_json_error('Ошибка удаления');
        }
    }
    
    /**
     * AJAX: Экспорт элементов
     */
    public function ajax_export_parser_items() {
        if (!check_ajax_referer('akpp_export_parser_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $items = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="parser_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'URL', 'Title', 'Content Type', 'Status', 'Created At']);
        
        foreach ($items as $item) {
            fputcsv($output, [$item->id, $item->url, $item->title, $item->content_type, $item->status, $item->created_at]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Парсинг URL
     */
    public function parse($url) {
        $url = esc_url_raw($url);
        
        if (empty($url)) {
            return false;
        }
        
        // Проверка кэша
        $existing = $this->get_existing_parse($url);
        if ($existing) {
            return $existing;
        }
        
        // Получение содержимого
        $content = $this->fetch_content($url);
        if (!$content) {
            return false;
        }
        
        // Извлечение данных
        $parsed_data = $this->extract_data($content, $url);
        
        // Сохранение результата
        $item_id = $this->save_parse_result($url, $parsed_data);
        $parsed_data['id'] = $item_id;
        
        // Запуск AI анализа в фоне
        $this->trigger_ai_analysis($item_id);
        
        return $parsed_data;
    }
    
    /**
     * Получение содержимого страницы
     */
    private function fetch_content($url) {
        $random_ua = $this->user_agents[array_rand($this->user_agents)];
        
        $args = [
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'user-agent' => $random_ua,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3'
            ]
        ];
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('Ошибка запроса: ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            $this->log_error("HTTP ошибка: {$status_code}");
            return false;
        }
        
        $content = wp_remote_retrieve_body($response);
        
        // Декодирование
        $encoding = mb_detect_encoding($content, ['UTF-8', 'CP1251', 'KOI8-R'], true);
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        
        return $content;
    }
    
    /**
     * Извлечение данных из HTML
     */
    private function extract_data($html, $url) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Заголовок
        $title_nodes = $xpath->query('//title');
        $title = $title_nodes->length > 0 ? trim($title_nodes->item(0)->nodeValue) : '';
        
        // Основной текст
        $text = $this->extract_main_text($xpath);
        
        // Изображения
        $images = $this->extract_images($xpath, $url);
        
        // Тип контента
        $content_type = $this->determine_content_type($title, $text);
        
        return [
            'url' => $url,
            'title' => $title,
            'text' => $text,
            'images' => $images,
            'content_type' => $content_type,
            'parsed_at' => current_time('mysql')
        ];
    }
    
    /**
     * Извлечение основного текста
     */
    private function extract_main_text($xpath) {
        $selectors = [
            '//article', '//main', '//div[@class="content"]',
            '//div[@class="post-content"]', '//div[@class="entry-content"]',
            '//div[@class="article-content"]', '//body'
        ];
        
        $text = '';
        
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $text = trim($nodes->item(0)->textContent);
                if (strlen($text) > 200) break;
            }
        }
        
        $text = preg_replace('/\s+/', ' ', $text);
        $text = strip_tags($text);
        
        return $text;
    }
    
    /**
     * Извлечение изображений
     */
    private function extract_images($xpath, $base_url) {
        $images = [];
        $img_nodes = $xpath->query('//img');
        
        foreach ($img_nodes as $img) {
            $src = $img->getAttribute('src');
            if (empty($src)) continue;
            
            if (strpos($src, 'http') !== 0) {
                $src = rtrim($base_url, '/') . '/' . ltrim($src, '/');
            }
            
            $images[] = ['url' => $src, 'alt' => $img->getAttribute('alt')];
            if (count($images) >= 10) break;
        }
        
        return $images;
    }
    
    /**
     * Определение типа контента
     */
    private function determine_content_type($title, $text) {
        $content = strtolower($title . ' ' . $text);
        
        if (strpos($content, 'акпп') !== false || strpos($content, 'automatic transmission') !== false) {
            return 'transmission';
        }
        if (strpos($content, 'запчасть') !== false || strpos($content, 'ремкомплект') !== false) {
            return 'part';
        }
        if (strpos($content, 'масло') !== false || strpos($content, 'atf') !== false) {
            return 'oil';
        }
        
        return 'general';
    }
    
    /**
     * Получение существующего парсинга
     */
    private function get_existing_parse($url) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE url = %s ORDER BY id DESC LIMIT 1",
            $url
        ));
        
        if ($item && !empty($item->parsed_data)) {
            return json_decode($item->parsed_data, true);
        }
        
        return false;
    }
    
    /**
     * Сохранение результата парсинга
     */
    private function save_parse_result($url, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $wpdb->insert(
            $table,
            [
                'url' => $url,
                'title' => $data['title'],
                'content' => $data['text'],
                'images' => json_encode($data['images']),
                'content_type' => $data['content_type'],
                'status' => 'parsed',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Запуск AI анализа в фоне
     */
    private function trigger_ai_analysis($item_id) {
        if (!wp_next_scheduled('akpp_ai_analysis_event', [$item_id])) {
            wp_schedule_single_event(time(), 'akpp_ai_analysis_event', [$item_id]);
        }
    }
    
    private function log_error($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_PARSER] ОШИБКА: ' . $message);
        }
    }
}
