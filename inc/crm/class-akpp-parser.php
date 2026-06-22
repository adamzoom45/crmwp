<?php
/**
 * АКПП45 CRM - Универсальный парсер веб-страниц
 * Извлечение заголовков, текста и изображений с помощью DOMDocument.
 *
 * @package AKPP_CRM
 * @version 4.4
 */
if (!defined('ABSPATH')) {
    exit;
}

class AKPP_Parser {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Основной метод парсинга URL
     */
    public function parse($url) {
        $url = esc_url_raw($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->log_error('Invalid URL provided', $url);
            return false;
        }

        $html = $this->fetch_html($url);
        if (!$html) {
            return false;
        }

        $data = $this->extract_data($html, $url);
        if (!$data) {
            return false;
        }

        $item_id = $this->save_to_db($url, $data);
        if ($item_id) {
            $data['id'] = $item_id;
            return $data;
        }
        return false;
    }

    /**
     * Загрузка HTML-кода страницы
     */
    private function fetch_html($url) {
        $args = [
            'timeout'     => 15,
            'redirection' => 5,
            'headers'     => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
                'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            ],
            'sslverify'   => false,
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
     */
    private function extract_data($html, $base_url) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();

        // ✅ ИСПРАВЛЕНО: mb_encode_numericentity вместо deprecated mb_convert_encoding
        $html_encoded = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html_encoded, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        // 1. Заголовок
        $title_nodes = $xpath->query('//title');
        $title = $title_nodes->length > 0 ? trim($title_nodes->item(0)->textContent) : 'Без заголовка';

        // 2. Очистка от мусора
        $junk_tags = ['script', 'style', 'noscript', 'nav', 'footer', 'header', 'iframe', 'svg'];
        foreach ($junk_tags as $tag) {
            $nodes = $xpath->query("//{$tag}");
            foreach ($nodes as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        // 3. Основной текст
        $content_nodes = $xpath->query('//main | //article | //div[@id="content"] | //body');
        $raw_text = '';
        if ($content_nodes->length > 0) {
            $raw_text = $content_nodes->item(0)->textContent;
        } else {
            $raw_text = $dom->textContent;
        }

        $clean_text = preg_replace('/\s+/', ' ', $raw_text);
        $clean_text = trim($clean_text);
        $clean_text = mb_substr($clean_text, 0, 50000);

        // 4. Изображения
        $images = [];
        $img_nodes = $xpath->query('//img/@src');
        foreach ($img_nodes as $img) {
            $src = trim($img->nodeValue);
            if (empty($src) || strpos($src, 'data:image') === 0 || strpos($src, 'pixel') !== false) {
                continue;
            }
            if (strpos($src, 'http') !== 0) {
                $src = $this->make_absolute_url($src, $base_url);
            }
            $images[] = esc_url_raw($src);
        }
        $images = array_values(array_unique($images));

        // 5. Тип контента
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
            return $relative;
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
     * Сохранение распарсенных данных в БД
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
            'status'       => 'pending',
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

    // ========================================================================
    // ✅ ДОБАВЛЕНО: AI анализ через Qwen API
    // ========================================================================
    public function analyze_with_qwen($content) {
        $api_key = get_option('akpp_openai_api_key', '');
        if (empty($api_key)) {
            $this->log_error('Qwen API', 'API ключ не установлен');
            return false;
        }

        $model = get_option('akpp_openai_model', 'qwen-turbo');
        $short_content = mb_substr($content, 0, 4000);

        $prompt = "Ты эксперт по автоматическим коробкам передач (АКПП) автомобилей. " .
                  "Проанализируй текст и извлеки структурированную информацию в формате JSON.\n\n" .
                  "Текст для анализа:\n{$short_content}\n\n" .
                  "Верни JSON в формате:\n" .
                  "{\n" .
                  "  \"transmission_code\": \"код АКПП если есть\",\n" .
                  "  \"transmission_type\": \"тип (гидротрансформатор/вариатор/робот)\",\n" .
                  "  \"car_make\": \"марка авто если есть\",\n" .
                  "  \"car_model\": \"модель авто если есть\",\n" .
                  "  \"years\": \"годы выпуска если есть\",\n" .
                  "  \"problems\": [\"список характерных проблем\"],\n" .
                  "  \"symptoms\": [\"список симптомов\"],\n" .
                  "  \"repair_cost\": число (примерная стоимость ремонта в рублях),\n" .
                  "  \"difficulty\": число от 1 до 5 (сложность ремонта),\n" .
                  "  \"recommendation\": \"общая рекомендация\",\n" .
                  "  \"confidence\": число от 0 до 100 (уверенность анализа)\n" .
                  "}";

        $api_url = 'https://dashscope-intl.aliyuncs.com/api/v1/services/aigc/text-generation/generation';

        $body = [
            'model' => $model,
            'input' => [
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты эксперт по АКПП. Отвечай только валидным JSON.'],
                    ['role' => 'user', 'content' => $prompt]
                ]
            ],
            'parameters' => [
                'result_format' => 'message',
                'temperature' => 0.3
            ]
        ];

        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            $this->log_error('Qwen API Error', $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_msg = $response_body['message'] ?? 'Unknown error';
            $this->log_error('Qwen API HTTP ' . $status_code, $error_msg);
            return false;
        }

        $analysis_text = $response_body['output']['choices'][0]['message']['content'] ?? '';

        if (empty($analysis_text)) {
            $this->log_error('Qwen API', 'Пустой ответ от API');
            return false;
        }

        // Извлекаем JSON из ответа (может быть обёрнут в ```json ... ```)
        if (preg_match('/\{[\s\S]*\}/', $analysis_text, $matches)) {
            $json_data = json_decode($matches[0], true);
            if ($json_data) {
                $json_data['raw_analysis'] = $analysis_text;
                $json_data['analyzed_at'] = current_time('mysql');
                return $json_data;
            }
        }

        // Если JSON не распарсился — возвращаем как есть
        return [
            'raw_analysis' => $analysis_text,
            'confidence'   => 0,
            'analyzed_at'  => current_time('mysql')
        ];
    }

    // ========================================================================
    // ✅ ДОБАВЛЕНО: Сохранение извлеченных сущностей в БД
    // ========================================================================
    public function save_extracted_entities($analysis) {
        if (empty($analysis) || !is_array($analysis)) {
            return ['saved' => 0];
        }

        global $wpdb;
        $saved = 0;

        // 1. Сохраняем АКПП
        if (!empty($analysis['transmission_code'])) {
            $trans_table = $wpdb->prefix . 'akpp_transmissions';
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$trans_table} WHERE code = %s LIMIT 1",
                $analysis['transmission_code']
            ));

            if (!$exists) {
                $wpdb->insert($trans_table, [
                    'code'              => sanitize_text_field($analysis['transmission_code']),
                    'type'              => sanitize_text_field($analysis['transmission_type'] ?? ''),
                    'make'              => sanitize_text_field($analysis['car_make'] ?? ''),
                    'model'             => sanitize_text_field($analysis['car_model'] ?? ''),
                    'years'             => sanitize_text_field($analysis['years'] ?? ''),
                    'common_problems'   => !empty($analysis['problems']) ? implode("\n", $analysis['problems']) : '',
                    'symptoms'          => !empty($analysis['symptoms']) ? implode("\n", $analysis['symptoms']) : '',
                    'repair_cost'       => intval($analysis['repair_cost'] ?? 0),
                    'difficulty'        => intval($analysis['difficulty'] ?? 3),
                    'created_at'        => current_time('mysql'),
                ]);
                $saved++;
            }
        }

        // 2. Сохраняем автомобиль
        if (!empty($analysis['car_make']) && !empty($analysis['car_model'])) {
            $veh_table = $wpdb->prefix . 'akpp_vehicles';
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$veh_table} WHERE make = %s AND model = %s LIMIT 1",
                $analysis['car_make'],
                $analysis['car_model']
            ));

            if (!$exists) {
                $wpdb->insert($veh_table, [
                    'make'       => sanitize_text_field($analysis['car_make']),
                    'model'      => sanitize_text_field($analysis['car_model']),
                    'year'       => !empty($analysis['years']) ? intval(explode('-', $analysis['years'])[0]) : 0,
                    'created_at' => current_time('mysql'),
                ]);
                $saved++;
            }
        }

        // 3. Сохраняем проблемы АКПП
        if (!empty($analysis['problems']) && is_array($analysis['problems'])) {
            $prob_table = $wpdb->prefix . 'akpp_transmission_problems';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}akpp_transmission_problems'") === "{$wpdb->prefix}akpp_transmission_problems";

            if ($table_exists) {
                foreach ($analysis['problems'] as $problem) {
                    $wpdb->insert($prob_table, [
                        'transmission_code' => sanitize_text_field($analysis['transmission_code'] ?? ''),
                        'car_make'          => sanitize_text_field($analysis['car_make'] ?? ''),
                        'car_model'         => sanitize_text_field($analysis['car_model'] ?? ''),
                        'problem_title'     => sanitize_text_field($problem),
                        'severity'          => 'medium',
                        'parsed_at'         => current_time('mysql'),
                        'ai_analyzed'       => 1,
                    ]);
                    $saved++;
                }
            }
        }

        return ['saved' => $saved, 'analysis' => $analysis];
    }

    /**
     * Логирование ошибок
     */
    private function log_error($context, $message) {
        error_log(sprintf('[AKPP Parser] ERROR: %s - %s', $context, $message));
    }
}
