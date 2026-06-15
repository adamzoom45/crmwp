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
    
    private function __construct() {}
    
    /**
     * Парсинг URL
     * 
     * @param string $url URL для парсинга
     * @return array|false Результат парсинга или false
     */
    public function parse($url) {
        $url = esc_url_raw($url);
        
        if (empty($url)) {
            $this->log_error('URL не передан');
            return false;
        }
        
        // Проверяем, не парсили ли уже этот URL
        $existing = $this->get_existing_parse($url);
        if ($existing) {
            $this->log_event("Используем кэшированный результат для {$url}");
            return $existing;
        }
        
        // Получаем содержимое страницы
        $content = $this->fetch_content($url);
        if (!$content) {
            return false;
        }
        
        // Извлекаем данные
        $parsed_data = $this->extract_data($content, $url);
        
        // Сохраняем результат
        $item_id = $this->save_parse_result($url, $parsed_data);
        $parsed_data['id'] = $item_id;
        
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
                'Accept-Language' => 'ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive'
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
        
        // Декодируем контент
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
        
        // Извлекаем заголовок
        $title = $this->extract_title($xpath, $dom);
        
        // Извлекаем основной текст
        $text = $this->extract_main_text($xpath);
        
        // Извлекаем изображения
        $images = $this->extract_images($xpath, $url);
        
        // Определяем тип контента (АКПП, запчасть, масло и т.д.)
        $content_type = $this->determine_content_type($title, $text);
        
        // Извлекаем ключевую информацию
        $key_data = $this->extract_key_data($text, $content_type);
        
        return [
            'url' => $url,
            'title' => $title,
            'text' => $text,
            'images' => $images,
            'content_type' => $content_type,
            'key_data' => $key_data,
            'parsed_at' => current_time('mysql')
        ];
    }
    
    /**
     * Извлечение заголовка
     */
    private function extract_title($xpath, $dom) {
        // Пробуем получить title
        $title_nodes = $xpath->query('//title');
        if ($title_nodes->length > 0) {
            return trim($title_nodes->item(0)->nodeValue);
        }
        
        // Пробуем получить h1
        $h1_nodes = $xpath->query('//h1');
        if ($h1_nodes->length > 0) {
            return trim($h1_nodes->item(0)->nodeValue);
        }
        
        return '';
    }
    
    /**
     * Извлечение основного текста
     */
    private function extract_main_text($xpath) {
        // Ищем основные блоки контента
        $selectors = [
            '//article',
            '//main',
            '//div[@class="content"]',
            '//div[@class="post-content"]',
            '//div[@class="entry-content"]',
            '//div[@class="article-content"]',
            '//body'
        ];
        
        $text = '';
        
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $text = trim($nodes->item(0)->textContent);
                if (strlen($text) > 200) {
                    break;
                }
            }
        }
        
        // Очищаем текст
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
            
            // Преобразуем относительные URL в абсолютные
            if (strpos($src, 'http') !== 0) {
                $src = rtrim($base_url, '/') . '/' . ltrim($src, '/');
            }
            
            $alt = $img->getAttribute('alt');
            
            $images[] = [
                'url' => $src,
                'alt' => $alt
            ];
            
            if (count($images) >= 10) break;
        }
        
        return $images;
    }
    
    /**
     * Определение типа контента
     */
    private function determine_content_type($title, $text) {
        $content = strtolower($title . ' ' . $text);
        
        $keywords = [
            'transmission' => ['акпп', 'автоматическая коробка', 'automatic transmission', 'transmission', 'гидротрансформатор'],
            'part' => ['запчасть', 'деталь', 'ремкомплект', 'фрикцион', 'соленоид', 'гидроблок'],
            'oil' => ['масло', 'atf', 'cvt', 'dct', 'трансмиссионное масло'],
            'service' => ['ремонт', 'диагностика', 'обслуживание', 'восстановление']
        ];
        
        foreach ($keywords as $type => $words) {
            foreach ($words as $word) {
                if (strpos($content, $word) !== false) {
                    return $type;
                }
            }
        }
        
        return 'general';
    }
    
    /**
     * Извлечение ключевых данных (номера, цены, характеристики)
     */
    private function extract_key_data($text, $content_type) {
        $data = [
            'codes' => [],
            'prices' => [],
            'specifications' => []
        ];
        
        // Извлекаем коды/артикулы
        preg_match_all('/[A-Z0-9]{4,15}/', $text, $codes);
        $data['codes'] = array_unique($codes[0]);
        
        // Извлекаем цены
        preg_match_all('/(\d+[\s]?[\d]*)\s?₽?руб?/i', $text, $prices);
        $data['prices'] = array_unique($prices[1]);
        
        // Извлекаем спецификации
        $spec_patterns = [
            'volume' => '/(\d+\.?\d*)\s?л/i',
            'power' => '/(\d+)\s?л\.с\./i',
            'year' => '/20\d{2}/'
        ];
        
        foreach ($spec_patterns as $key => $pattern) {
            preg_match_all($pattern, $text, $matches);
            if (!empty($matches[1])) {
                $data['specifications'][$key] = array_unique($matches[1]);
            }
        }
        
        return $data;
    }
    
    /**
     * Получение существующего результата парсинга
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
                'parsed_data' => json_encode($data),
                'content_type' => $data['content_type'],
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Обновление статуса с результатами AI
     */
    public function update_with_ai($item_id, $ai_result) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $wpdb->update(
            $table,
            [
                'ai_analysis' => json_encode($ai_result),
                'status' => 'ai_processed',
                'updated_at' => current_time('mysql')
            ],
            ['id' => $item_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        return true;
    }
    
    /**
     * Получение списка необработанных элементов
     */
    public function get_pending_items($limit = 10) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        return $wpdb->get_results(
            "SELECT * FROM {$table} 
            WHERE status = 'pending' 
            ORDER BY created_at ASC 
            LIMIT {$limit}"
        );
    }
    
    private function log_error($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_PARSER] ОШИБКА: ' . $message);
        }
    }
    
    private function log_event($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_PARSER] СОБЫТИЕ: ' . $message);
        }
    }
}
