<?php
/**
 * АКПП45 CRM - Универсальный парсер веб-страниц
 * С поддержкой Qwen AI
 */
if (!defined('ABSPATH')) exit;

class AKPP_Parser {
    
    private static $instance = null;
    private $qwen_api_key = '';
    private $qwen_api_url = 'https://dashscope.aliyuncs.com/api/v1/services/aigc/text-generation/generation';
    private $qwen_model = 'qwen-turbo';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->qwen_api_key = get_option('akpp_openai_api_key', '');
    }
    
    /**
     * Основной метод парсинга - ПРОСТОЙ И РАБОЧИЙ
     */
    public function parse($url) {
        global $wpdb;
        
        $html = $this->fetch_html($url);
        if (!$html) return false;
        
        $data = $this->extract_simple($html, $url);
        if (!$data) return false;
        
        $table = $wpdb->prefix . 'akpp_parser_items';
        $insert_data = [
            'url' => $url,
            'title' => $data['title'],
            'content' => $data['content'],
            'content_type' => $data['content_type'],
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($table, $insert_data);
        if ($result) {
            return ['id' => $wpdb->insert_id, 'title' => $data['title']];
        }
        return false;
    }
    
    /**
     * Загрузка HTML
     */
    private function fetch_html($url) {
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'sslverify' => false
        ]);
        if (is_wp_error($response)) {
            error_log('[Parser] Error: ' . $response->get_error_message());
            return false;
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            error_log('[Parser] HTTP Error: ' . $code);
            return false;
        }
        return wp_remote_retrieve_body($response);
    }
    
    /**
     * Простое извлечение данных
     */
    private function extract_simple($html, $url) {
        $data = ['title' => '', 'content' => '', 'content_type' => 'general'];
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $data['title'] = trim(strip_tags($matches[1]));
        }
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        $data['content'] = mb_substr($text, 0, 10000);
        $lower = mb_strtolower($text);
        if (strpos($lower, 'акпп') !== false || strpos($lower, 'коробка') !== false) {
            $data['content_type'] = 'transmission_related';
        } elseif (strpos($lower, 'запчасть') !== false || strpos($lower, 'купить') !== false) {
            $data['content_type'] = 'parts_store';
        }
        return $data;
    }
    
    // ========================================================================
    // ДОПОЛНЕНИЕ: AI-парсинг с Qwen
    // ========================================================================
    
    /**
     * Парсинг URL с использованием Qwen AI
     *
     * @param string $url
     * @return array|false
     */
    public function parse_with_ai($url) {
        global $wpdb;
        
        $basic_result = $this->parse($url);
        if (!$basic_result) return false;
        
        $item_id = $basic_result['id'];
        $table = $wpdb->prefix . 'akpp_parser_items';
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $item_id), ARRAY_A);
        if (!$item) return false;
        
        $content = $item['content'] ?? '';
        $analysis = $this->analyze_with_qwen($content);
        
        if (!$analysis) {
            $wpdb->update($table, ['status' => 'ai_processed', 'updated_at' => current_time('mysql')], ['id' => $item_id]);
            return ['id' => $item_id, 'error' => 'AI не ответил'];
        }
        
        $wpdb->update($table, [
            'ai_analysis' => json_encode($analysis, JSON_UNESCAPED_UNICODE),
            'status' => 'ai_processed',
            'updated_at' => current_time('mysql')
        ], ['id' => $item_id]);
        
        $saved = $this->save_extracted_entities($analysis);
        
        return [
            'id' => $item_id,
            'saved' => $saved,
            'analysis' => $analysis
        ];
    }
    
    /**
     * Анализ текста через Qwen API
     */
    private function analyze_with_qwen($text) {
        if (empty($this->qwen_api_key)) {
            error_log('[Qwen] API ключ не установлен');
            return false;
        }
        
        $prompt = $this->build_prompt($text);
        $response = $this->call_qwen_api($prompt);
        if ($response) {
            return $this->parse_qwen_response($response);
        }
        return false;
    }
    
    /**
     * Формирование промпта
     */
    private function build_prompt($text) {
        $prompt = "Ты — эксперт по автоматическим коробкам передач (АКПП). Проанализируй следующий текст и извлеки структурированную информацию.\n\n";
        $prompt .= "Текст:\n" . mb_substr($text, 0, 8000) . "\n\n";
        $prompt .= "Верни ответ строго в формате JSON со следующими полями:\n";
        $prompt .= "{\n";
        $prompt .= "  \"vehicles\": [{\"make\": \"марка\", \"model\": \"модель\", \"year\": год, \"engine\": \"двигатель\"}],\n";
        $prompt .= "  \"transmissions\": [{\"code\": \"код АКПП\", \"type\": \"тип (AT/CVT/DCT)\", \"manufacturer\": \"производитель\", \"problems\": [\"проблемы\"], \"symptoms\": [\"симптомы\"]}],\n";
        $prompt .= "  \"problems\": [{\"description\": \"описание\", \"causes\": [\"причины\"], \"solutions\": [\"решения\"]}],\n";
        $prompt .= "  \"schemas\": [{\"url\": \"ссылка на схему\", \"description\": \"описание\"}],\n";
        $prompt .= "  \"engine_models\": [{\"model\": \"модель двигателя\", \"power\": \"мощность\", \"volume\": \"объем\"}],\n";
        $prompt .= "  \"gearboxes\": [{\"code\": \"код КПП\", \"type\": \"тип\"}]\n";
        $prompt .= "}\n";
        $prompt .= "Если какие-то данные отсутствуют, оставляй пустые массивы. Не добавляй пояснений, только JSON.";
        return $prompt;
    }
    
    /**
     * Вызов Qwen API
     */
    private function call_qwen_api($prompt) {
        $body = [
            'model' => $this->qwen_model,
            'input' => [
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты эксперт по АКПП. Отвечай только в формате JSON.'],
                    ['role' => 'user', 'content' => $prompt]
                ]
            ],
            'parameters' => [
                'result_format' => 'message',
                'temperature' => 0.3,
                'max_tokens' => 2000
            ]
        ];
        
        $response = wp_remote_post($this->qwen_api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->qwen_api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) {
            error_log('[Qwen] Ошибка запроса: ' . $response->get_error_message());
            return false;
        }
        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ($status !== 200) {
            error_log('[Qwen] Ошибка API: ' . ($data['message'] ?? 'Unknown error'));
            return false;
        }
        return $data['output']['choices'][0]['message']['content'] ?? false;
    }
    
    /**
     * Парсинг ответа Qwen (извлечение JSON)
     */
    private function parse_qwen_response($response_text) {
        if (preg_match('/\{[\s\S]*\}/', $response_text, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) return $json;
        }
        return [
            'vehicles' => [],
            'transmissions' => [],
            'problems' => [],
            'schemas' => [],
            'engine_models' => [],
            'gearboxes' => []
        ];
    }
    
    /**
     * Сохранение извлечённых сущностей в БД
     */
    private function save_extracted_entities($analysis) {
        global $wpdb;
        $result = ['vehicles' => 0, 'transmissions' => 0, 'problems' => 0, 'schemas' => 0, 'engine_models' => 0, 'gearboxes' => 0];
        
        // Автомобили
        if (!empty($analysis['vehicles'])) {
            foreach ($analysis['vehicles'] as $vehicle) {
                if (empty($vehicle['make']) || empty($vehicle['model'])) continue;
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}akpp_vehicles WHERE make = %s AND model = %s AND year = %d",
                    $vehicle['make'], $vehicle['model'], intval($vehicle['year'] ?? 0)
                ));
                if (!$exists) {
                    $wpdb->insert($wpdb->prefix . 'akpp_vehicles', [
                        'make' => $vehicle['make'],
                        'model' => $vehicle['model'],
                        'year' => intval($vehicle['year'] ?? 0),
                        'engine' => $vehicle['engine'] ?? '',
                        'created_at' => current_time('mysql')
                    ]);
                    $result['vehicles']++;
                }
            }
        }
        
        // АКПП
        if (!empty($analysis['transmissions'])) {
            foreach ($analysis['transmissions'] as $trans) {
                if (empty($trans['code'])) continue;
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}akpp_transmissions WHERE code = %s",
                    $trans['code']
                ));
                if (!$exists) {
                    $wpdb->insert($wpdb->prefix . 'akpp_transmissions', [
                        'code' => $trans['code'],
                        'type' => $trans['type'] ?? 'AT',
                        'manufacturer' => $trans['manufacturer'] ?? '',
                        'common_problems' => is_array($trans['problems'] ?? null) ? implode('|', $trans['problems']) : '',
                        'symptoms' => is_array($trans['symptoms'] ?? null) ? implode('|', $trans['symptoms']) : '',
                        'created_at' => current_time('mysql')
                    ]);
                    $result['transmissions']++;
                }
            }
        }
        
        // Проблемы
        if (!empty($analysis['problems'])) {
            foreach ($analysis['problems'] as $problem) {
                if (empty($problem['description'])) continue;
                $wpdb->insert($wpdb->prefix . 'akpp_parser_items', [
                    'url' => '',
                    'title' => mb_substr($problem['description'], 0, 255),
                    'content' => $problem['description'],
                    'content_type' => 'transmission_problem',
                    'ai_analysis' => json_encode($problem),
                    'status' => 'approved',
                    'created_at' => current_time('mysql')
                ]);
                $result['problems']++;
            }
        }
        
        // Схемы
        if (!empty($analysis['schemas'])) {
            foreach ($analysis['schemas'] as $schema) {
                if (empty($schema['url'])) continue;
                $wpdb->insert($wpdb->prefix . 'akpp_parser_items', [
                    'url' => $schema['url'],
                    'title' => $schema['description'] ?? 'Схема АКПП',
                    'content' => $schema['description'] ?? '',
                    'content_type' => 'schema',
                    'status' => 'approved',
                    'created_at' => current_time('mysql')
                ]);
                $result['schemas']++;
            }
        }
        
        // Модели двигателей
        if (!empty($analysis['engine_models'])) {
            foreach ($analysis['engine_models'] as $engine) {
                if (empty($engine['model'])) continue;
                $wpdb->insert($wpdb->prefix . 'akpp_parser_items', [
                    'url' => '',
                    'title' => $engine['model'],
                    'content' => json_encode($engine),
                    'content_type' => 'engine_model',
                    'status' => 'approved',
                    'created_at' => current_time('mysql')
                ]);
                $result['engine_models']++;
            }
        }
        
        // КПП
        if (!empty($analysis['gearboxes'])) {
            foreach ($analysis['gearboxes'] as $gb) {
                if (empty($gb['code'])) continue;
                $wpdb->insert($wpdb->prefix . 'akpp_parser_items', [
                    'url' => '',
                    'title' => $gb['code'],
                    'content' => json_encode($gb),
                    'content_type' => 'gearbox',
                    'status' => 'approved',
                    'created_at' => current_time('mysql')
                ]);
                $result['gearboxes']++;
            }
        }
        
        return $result;
    }
    
    /**
     * Массовый AI анализ
     */
    public function bulk_ai_analysis($limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT id, content FROM $table WHERE status = 'pending' OR status = 'parsed' ORDER BY created_at ASC LIMIT %d",
            $limit
        ), ARRAY_A);
        
        if (empty($items)) {
            return ['processed' => 0, 'message' => 'Нет записей для анализа'];
        }
        
        $processed = 0;
        foreach ($items as $item) {
            $analysis = $this->analyze_with_qwen($item['content']);
            if ($analysis) {
                $this->save_extracted_entities($analysis);
                $wpdb->update($table, [
                    'ai_analysis' => json_encode($analysis, JSON_UNESCAPED_UNICODE),
                    'status' => 'ai_processed',
                    'updated_at' => current_time('mysql')
                ], ['id' => $item['id']]);
                $processed++;
            } else {
                $wpdb->update($table, ['status' => 'ai_processed', 'updated_at' => current_time('mysql')], ['id' => $item['id']]);
            }
            usleep(500000);
        }
        return ['processed' => $processed, 'message' => "Обработано $processed записей"];
    }
}