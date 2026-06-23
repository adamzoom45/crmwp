<?php
/**
 * АКПП45 CRM - Универсальный парсер с AI анализом
 * @package AKPP_CRM
 * @version 5.0
 */
if (!defined('ABSPATH')) exit;

class AKPP_Parser {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }
    
    public function parse($url) {
        $url = esc_url_raw($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->log_error('Invalid URL', $url);
            return false;
        }
        
        $html = $this->fetch_html($url);
        if (!$html) return false;
        
        $data = $this->extract_data($html, $url);
        if (!$data) return false;
        
        $item_id = $this->save_to_db($url, $data);
        if ($item_id) {
            $data['id'] = $item_id;
            return $data;
        }
        return false;
    }
    
    private function fetch_html($url) {
        $args = [
            'timeout' => 30,
            'redirection' => 5,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
            ],
            'sslverify' => false,
        ];
        
        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            $this->log_error('wp_remote_get', $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            $this->log_error('HTTP Error', "Code: {$code}");
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        if (!mb_check_encoding($body, 'UTF-8')) {
            $body = mb_convert_encoding($body, 'UTF-8', 'auto');
        }
        return $body;
    }
    
    private function extract_data($html, $base_url) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        
        $html_encoded = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html_encoded, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        $title_nodes = $xpath->query('//title');
        $title = $title_nodes->length > 0 ? trim($title_nodes->item(0)->textContent) : 'Без заголовка';
        
        $junk_tags = ['script', 'style', 'noscript', 'nav', 'footer', 'header', 'iframe', 'svg'];
        foreach ($junk_tags as $tag) {
            $nodes = $xpath->query("//{$tag}");
            foreach ($nodes as $node) {
                if ($node->parentNode) $node->parentNode->removeChild($node);
            }
        }
        
        $content_nodes = $xpath->query('//main | //article | //div[@id="content"] | //body');
        $raw_text = $content_nodes->length > 0 ? $content_nodes->item(0)->textContent : $dom->textContent;
        
        $clean_text = preg_replace('/\s+/', ' ', $raw_text);
        $clean_text = trim(mb_substr($clean_text, 0, 50000));
        
        $images = [];
        $img_nodes = $xpath->query('//img/@src');
        foreach ($img_nodes as $img) {
            $src = trim($img->nodeValue);
            if (empty($src) || strpos($src, 'data:image') === 0) continue;
            if (strpos($src, 'http') !== 0) $src = $this->make_absolute_url($src, $base_url);
            $images[] = esc_url_raw($src);
        }
        $images = array_values(array_unique($images));
        
        $content_type = 'general';
        $lower = mb_strtolower($clean_text);
        if (strpos($lower, 'акпп') !== false || strpos($lower, 'коробка') !== false || strpos($lower, 'трансмиссия') !== false) {
            $content_type = 'transmission_related';
        } elseif (strpos($lower, 'запчасть') !== false || strpos($lower, 'купить') !== false) {
            $content_type = 'parts_store';
        }
        
        return [
            'title' => sanitize_text_field($title),
            'content' => sanitize_textarea_field($clean_text),
            'images' => $images,
            'content_type' => sanitize_text_field($content_type),
        ];
    }
    
    private function make_absolute_url($relative, $base) {
        if (parse_url($relative, PHP_URL_SCHEME) !== null) return $relative;
        $base_parts = parse_url($base);
        $base_url = $base_parts['scheme'] . '://' . $base_parts['host'];
        if (strpos($relative, '/') === 0) return $base_url . $relative;
        $base_dir = dirname($base_parts['path']);
        if ($base_dir === '/' || $base_dir === '.') $base_dir = '';
        return $base_url . $base_dir . '/' . ltrim($relative, '/');
    }
    
    private function save_to_db($url, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $result = $wpdb->insert($table, [
            'url' => $url,
            'title' => $data['title'],
            'content' => $data['content'],
            'images' => wp_json_encode($data['images'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content_type' => $data['content_type'],
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        
        if ($result === false) {
            $this->log_error('DB Insert', $wpdb->last_error);
            return false;
        }
        return $wpdb->insert_id;
    }
    
    // ========================================================================
    // AI АНАЛИЗ ЧЕРЕЗ QWEN API
    // ========================================================================
    public function analyze_with_qwen($content) {
        $api_key = get_option('akpp_openai_api_key', '');
        if (empty($api_key)) {
            $this->log_error('Qwen API', 'API ключ не установлен');
            return false;
        }
        
        $model = get_option('akpp_openai_model', 'qwen-turbo');
        $short_content = mb_substr($content, 0, 4000);
        
        $prompt = "Ты эксперт по АКПП автомобилей. Проанализируй текст и верни JSON:\n\n" .
                  "Текст:\n{$short_content}\n\n" .
                  "Формат JSON:\n" .
                  "{\n" .
                  "  \"transmission_code\": \"код АКПП или null\",\n" .
                  "  \"transmission_type\": \"гидротрансформатор|вариатор|робот|null\",\n" .
                  "  \"car_make\": \"марка или null\",\n" .
                  "  \"car_model\": \"модель или null\",\n" .
                  "  \"years\": \"годы или null\",\n" .
                  "  \"engine\": \"двигатель или null\",\n" .
                  "  \"problems\": [\"проблемы\"],\n" .
                  "  \"symptoms\": [\"симптомы\"],\n" .
                  "  \"repair_cost\": число,\n" .
                  "  \"difficulty\": 1-5,\n" .
                  "  \"recommendation\": \"текст\",\n" .
                  "  \"confidence\": 0-100\n" .
                  "}";
        
        $api_url = 'https://dashscope-intl.aliyuncs.com/api/v1/services/aigc/text-generation/generation';
        
        $body = [
            'model' => $model,
            'input' => [
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты эксперт по АКПП. Отвечай только JSON.'],
                    ['role' => 'user', 'content' => $prompt]
                ]
            ],
            'parameters' => ['result_format' => 'message', 'temperature' => 0.3]
        ];
        
        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) {
            $this->log_error('Qwen API', $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code !== 200) {
            $this->log_error('Qwen HTTP ' . $status_code, $response_body['message'] ?? 'Unknown');
            return false;
        }
        
        $analysis_text = $response_body['output']['choices'][0]['message']['content'] ?? '';
        if (empty($analysis_text)) return false;
        
        if (preg_match('/\{[\s\S]*\}/', $analysis_text, $matches)) {
            $json_data = json_decode($matches[0], true);
            if ($json_data) {
                $json_data['raw_analysis'] = $analysis_text;
                $json_data['analyzed_at'] = current_time('mysql');
                return $json_data;
            }
        }
        
        return ['raw_analysis' => $analysis_text, 'confidence' => 0, 'analyzed_at' => current_time('mysql')];
    }
    
    // ========================================================================
    // СОХРАНЕНИЕ СУЩНОСТЕЙ
    // ========================================================================
    public function save_extracted_entities($analysis) {
        if (empty($analysis) || !is_array($analysis)) return ['saved' => 0];
        
        global $wpdb;
        $saved = 0;
        
        // АКПП
        if (!empty($analysis['transmission_code'])) {
            $trans_table = $wpdb->prefix . 'akpp_transmissions';
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$trans_table} WHERE code = %s LIMIT 1",
                $analysis['transmission_code']
            ));
            
            if (!$exists) {
                $wpdb->insert($trans_table, [
                    'code' => sanitize_text_field($analysis['transmission_code']),
                    'type' => sanitize_text_field($analysis['transmission_type'] ?? ''),
                    'make' => sanitize_text_field($analysis['car_make'] ?? ''),
                    'model' => sanitize_text_field($analysis['car_model'] ?? ''),
                    'years' => sanitize_text_field($analysis['years'] ?? ''),
                    'engine' => sanitize_text_field($analysis['engine'] ?? ''),
                    'common_problems' => !empty($analysis['problems']) ? implode("\n", (array)$analysis['problems']) : '',
                    'repair_cost' => intval($analysis['repair_cost'] ?? 0),
                    'difficulty' => intval($analysis['difficulty'] ?? 3),
                    'created_at' => current_time('mysql'),
                ]);
                $saved++;
            }
        }
        
        // Автомобиль
        if (!empty($analysis['car_make']) && !empty($analysis['car_model'])) {
            $veh_table = $wpdb->prefix . 'akpp_vehicles';
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$veh_table} WHERE make = %s AND model = %s LIMIT 1",
                $analysis['car_make'], $analysis['car_model']
            ));
            
            if (!$exists) {
                $year = 0;
                if (!empty($analysis['years']) && preg_match('/(\d{4})/', $analysis['years'], $m)) {
                    $year = intval($m[1]);
                }
                $wpdb->insert($veh_table, [
                    'make' => sanitize_text_field($analysis['car_make']),
                    'model' => sanitize_text_field($analysis['car_model']),
                    'year' => $year,
                    'engine' => sanitize_text_field($analysis['engine'] ?? ''),
                    'created_at' => current_time('mysql'),
                ]);
                $saved++;
            }
        }
        
        // Проблемы
        if (!empty($analysis['problems']) && is_array($analysis['problems'])) {
            $prob_table = $wpdb->prefix . 'akpp_transmission_problems';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$prob_table}'") === $prob_table;
            
            if ($table_exists) {
                foreach ($analysis['problems'] as $problem) {
                    $wpdb->insert($prob_table, [
                        'transmission_code' => sanitize_text_field($analysis['transmission_code'] ?? ''),
                        'car_make' => sanitize_text_field($analysis['car_make'] ?? ''),
                        'car_model' => sanitize_text_field($analysis['car_model'] ?? ''),
                        'problem_title' => sanitize_text_field($problem),
                        'severity' => 'medium',
                        'parsed_at' => current_time('mysql'),
                        'ai_analyzed' => 1,
                    ]);
                    $saved++;
                }
            }
        }
        
        return ['saved' => $saved, 'analysis' => $analysis];
    }
    
    private function log_error($context, $message) {
        error_log(sprintf('[AKPP Parser] %s: %s', $context, $message));
    }
}