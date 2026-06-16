<?php
/**
 * АКПП45 CRM - Универсальный парсер веб-страниц
 * Извлечение заголовков, текста и изображений с помощью DOMDocument.
 *
 * @package AKPP_CRM
 * @version 4.3
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

class AKPP_Parser {

    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;

    /**
     * Получение экземпляра
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Основной метод парсинга URL
     *
     * @param string $url URL для парсинга
     * @return array|false Массив с данными или false при ошибке
     */
    public function parse($url) {
        $url = esc_url_raw($url);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->log_error('Invalid URL provided', $url);
            return false;
        }

        // 1. Получение HTML
        $html = $this->fetch_html($url);
        if (!$html) {
            return false;
        }

        // 2. Извлечение данных
        $data = $this->extract_data($html, $url);
        if (!$data) {
            return false;
        }

        // 3. Сохранение в базу данных
        $item_id = $this->save_to_db($url, $data);
        
        if ($item_id) {
            $data['id'] = $item_id;
            return $data;
        }

        return false;
    }

    /**
     * Загрузка HTML-кода страницы
     *
     * @param string $url
     * @return string|false
     */
    private function fetch_html($url) {
        $args = [
            'timeout'     => 15,
            'redirection' => 5,
            'headers'     => [
                // Реалистичный User-Agent, чтобы избежать блокировки простыми защитами
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
                'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            ],
            'sslverify'   => false, // Игнорировать ошибки SSL для внутренних/тестовых сайтов
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            $this->log_error('wp_remote_get failed', $response->get_error_message() . ' | URL: ' . $url);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            $this->log_error('HTTP Error', "Code: {$response_code} | URL: {$url}");
            return false;
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Извлечение структурированных данных из HTML
     *
     * @param string $html
     * @param string $base_url
     * @return array|false
     */
    private function extract_data($html, $base_url) {
        // Подавление предупреждений DOMDocument о невалидном HTML
        libxml_use_internal_errors(true);
        
        $dom = new DOMDocument();
        // Конвертация в HTML-ENTITIES для корректной работы с UTF-8 (кириллицей)
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        // 1. Извлечение заголовка
        $title_nodes = $xpath->query('//title');
        $title = $title_nodes->length > 0 ? trim($title_nodes->item(0)->textContent) : 'Без заголовка';

        // 2. Очистка от мусора (скрипты, стили, навигация, подвал)
        $junk_tags = ['script', 'style', 'noscript', 'nav', 'footer', 'header', 'iframe', 'svg'];
        foreach ($junk_tags as $tag) {
            $nodes = $xpath->query("//{$tag}");
            foreach ($nodes as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        // 3. Извлечение основного текста
        // Пытаемся найти main или article, иначе берем body
        $content_nodes = $xpath->query('//main | //article | //div[@id="content"] | //body');
        $raw_text = '';
        if ($content_nodes->length > 0) {
            $raw_text = $content_nodes->item(0)->textContent;
        } else {
            $raw_text = $dom->textContent;
        }

        // Очистка текста: удаление лишних пробелов и переносов строк
        $clean_text = preg_replace('/\s+/', ' ', $raw_text);
        $clean_text = trim($clean_text);
        
        // Ограничиваем размер текста (макс. 50 000 символов для экономии БД и токенов AI)
        $clean_text = mb_substr($clean_text, 0, 50000);

        // 4. Извлечение изображений
        $images = [];
        $img_nodes = $xpath->query('//img/@src');
        foreach ($img_nodes as $img) {
            $src = trim($img->nodeValue);
            
            // Пропускаем base64 и трекеры
            if (empty($src) || strpos($src, 'data:image') === 0 || strpos($src, 'pixel') !== false) {
                continue;
            }

            // Преобразование относительных URL в абсолютные
            if (strpos($src, 'http') !== 0) {
                $src = $this->make_absolute_url($src, $base_url);
            }

            $images[] = esc_url_raw($src);
        }

        // Удаление дубликатов изображений
        $images = array_values(array_unique($images));

        // 5. Определение типа контента (эвристика)
        $content_type = 'general';
        $lower_text = mb_strtolower($clean_text);
        if (strpos($lower_text, 'акпп') !== false || strpos($lower_text, 'коробка') !== false || strpos($lower_text, 'трансмиссия') !== false) {
            $content_type = 'transmission_related';
        } elseif (strpos($lower_text, 'запчасть') !== false || strpos($lower_text, 'купить') !== false) {
            $content_type = 'parts_store';
        }

        return [
            'title'        => sanitize_text_field($title),
            'content'      => sanitize_textarea_field($clean_text),
            'images'       => $images,
            'content_type' => sanitize_text_field($content_type)
        ];
    }

    /**
     * Преобразование относительного URL в абсолютный
     */
    private function make_absolute_url($relative, $base) {
        if (parse_url($relative, PHP_URL_SCHEME) !== null) {
            return $relative; // Уже абсолютный
        }

        $base_parts = parse_url($base);
        $base_url = $base_parts['scheme'] . '://' . $base_parts['host'];
        
        if (strpos($relative, '/') === 0) {
            return $base_url . $relative;
        }

        $base_dir = dirname($base_parts['path']);
        if ($base_dir === '/' || $base_dir === '.') {
            $base_dir = '';
        }
        
        return $base_url . $base_dir . '/' . ltrim($relative, '/');
    }

    /**
     * Сохранение распарсенных данных в базу данных
     *
     * @param string $url
     * @param array  $data
     * @return int|false ID вставленной записи или false
     */
    private function save_to_db($url, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';

        $insert_data = [
            'url'          => $url,
            'title'        => $data['title'],
            'content'      => $data['content'],
            'images'       => wp_json_encode($data['images'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content_type' => $data['content_type'],
            'status'       => 'pending', // Ожидает AI анализа
            'created_at'   => current_time('mysql'),
            'updated_at'   => current_time('mysql')
        ];

        $result = $wpdb->insert($table, $insert_data);

        if ($result === false) {
            $this->log_error('DB Insert Failed', $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Логирование ошибок
     */
    private function log_error($context, $message) {
        error_log(sprintf('[AKPP Parser] ERROR: %s - %s', $context, $message));
    }
}
