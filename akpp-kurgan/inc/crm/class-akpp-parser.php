<?php
if (!defined('ABSPATH')) exit;

class AKPP_Parser {
    
    public function __construct() {
        // AJAX хук для запуска парсинга из админки
        add_action('wp_ajax_akpp_parse_url', [$this, 'ajax_parse_url']);
    }

    /**
     * Основная логика парсинга URL
     *
     * @param string $url URL для парсинга
     * @return array Результат парсинга или ошибка
     */
    public function parse($url) {
        // 1. Валидация URL
        $url = esc_url_raw($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['error' => 'Некорректный URL'];
        }

        // 2. Получение содержимого страницы
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);

        if (is_wp_error($response)) {
            return ['error' => 'Ошибка получения страницы: ' . $response->get_error_message()];
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            return ['error' => 'Получен пустой ответ от сервера'];
        }

        // 3. Парсинг HTML с помощью DOMDocument
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        // Подавляем предупреждения о некорректном HTML
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // 4. Извлечение заголовка
        $title = 'Без заголовка';
        $title_nodes = $dom->getElementsByTagName('title');
        if ($title_nodes->length > 0) {
            $title = trim($title_nodes->item(0)->nodeValue);
        }

        // 5. Извлечение изображений
        $images = [];
        $img_nodes = $dom->getElementsByTagName('img');
        foreach ($img_nodes as $img) {
            $src = $img->getAttribute('src');
            if (!empty($src)) {
                // Преобразуем относительные ссылки в абсолютные
                $images[] = $this->make_absolute_url($src, $url);
            }
        }
        // Убираем дубликаты
        $images = array_unique($images);

        // 6. Очистка контента (удаление скриптов, стилей, навигации)
        $this->remove_unwanted_tags($dom, ['script', 'style', 'noscript', 'iframe', 'nav', 'footer', 'header']);
        
        // Пытаемся найти основной контент (main, article, или просто body)
        $content_node = $dom->getElementsByTagName('main')->item(0) 
                     ?: $dom->getElementsByTagName('article')->item(0) 
                     ?: $dom->getElementsByTagName('body')->item(0);
        
        $content = '';
        if ($content_node) {
            $content = $this->get_inner_html($content_node);
            // Дополнительная очистка от лишних пробелов
            $content = preg_replace('/\s+/', ' ', $content);
        }

        // 7. Сохранение в базу данных
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $inserted = $wpdb->insert($table, [
            'source_url' => $url,
            'title' => sanitize_text_field($title),
            'content' => wp_kses_post($content), // Разрешаем только безопасные HTML теги
            'status' => 'pending'
        ]);

        if (!$inserted) {
            return ['error' => 'Ошибка сохранения в базу данных'];
        }

        $item_id = $wpdb->insert_id;

        // 8. Формирование ответа
        return [
            'success' => true,
            'id' => $item_id,
            'title' => $title,
            'content_preview' => wp_trim_words(strip_tags($content), 50, '...'),
            'images_count' => count($images),
            'images' => array_slice($images, 0, 5) // Возвращаем только первые 5 для превью
        ];
    }

    /**
     * AJAX обработчик для запуска парсинга
     */
    public function ajax_parse_url() {
        check_ajax_referer('akpp_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Недостаточно прав']);
        }

        $url = sanitize_text_field($_POST['url'] ?? '');
        
        if (empty($url)) {
            wp_send_json_error(['message' => 'URL не указан']);
        }

        $result = $this->parse($url);

        if (isset($result['error'])) {
            wp_send_json_error(['message' => $result['error']]);
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Удаление нежелательных тегов из DOM
     */
    private function remove_unwanted_tags($dom, $tags) {
        foreach ($tags as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            // Итерируемся в обратном порядке, чтобы не сбить индексы при удалении
            for ($i = $elements->length - 1; $i >= 0; $i--) {
                $element = $elements->item($i);
                $element->parentNode->removeChild($element);
            }
        }
    }

    /**
     * Получение внутреннего HTML узла
     */
    private function get_inner_html($node) {
        $innerHTML = '';
        $children = $node->childNodes;
        foreach ($children as $child) {
            $innerHTML .= $child->ownerDocument->saveHTML($child);
        }
        return $innerHTML;
    }

    /**
     * Преобразование относительного URL в абсолютный
     */
    private function make_absolute_url($url, $base) {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        
        $base_parts = parse_url($base);
        $base_path = isset($base_parts['path']) ? dirname($base_parts['path']) : '/';
        
        if (strpos($url, '/') === 0) {
            return $base_parts['scheme'] . '://' . $base_parts['host'] . $url;
        }
        
        return $base_parts['scheme'] . '://' . $base_parts['host'] . $base_path . '/' . ltrim($url, '/');
    }
}

// Инициализация
new AKPP_Parser();
