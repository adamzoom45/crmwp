<?php
/**
 * Класс для AI анализа контента (OpenAI API)
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
    }
    
    private function load_settings() {
        $this->api_key = get_option('akpp_openai_api_key', '');
    }
    
    /**
     * Сохранение API ключа
     */
    public function save_api_key($api_key) {
        update_option('akpp_openai_api_key', sanitize_text_field($api_key));
        $this->api_key = $api_key;
        return true;
    }
    
    /**
     * AI анализ текста
     * 
     * @param string $text Текст для анализа
     * @param string $content_type Тип контента (transmission, part, oil, general)
     * @return array Результат анализа
     */
    public function analyze($text, $content_type = 'general') {
        if (empty($this->api_key)) {
            $this->log_error('API ключ OpenAI не настроен');
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
     * Построение промпта для AI
     */
    private function build_prompt($text, $content_type) {
        $base_prompt = "Ты эксперт по автоматическим коробкам передач (АКПП). Проанализируй следующий текст и верни результат в формате JSON.\n\n";
        
        $specific_prompts = [
            'transmission' => "Определи:
- тип АКПП (4AT, 5AT, 6AT, CVT, DCT, 8AT, 9AT, 10AT)
- марку автомобиля
- модель автомобиля
- годы выпуска
- общие проблемы
- симптомы неисправностей
- возможные причины
- рекомендуемые запчасти для ремонта
- сложность ремонта (1-5)
- среднюю стоимость ремонта (в рублях)",
            
            'part' => "Определи:
- тип запчасти
- артикул/номер детали
- для каких АКПП подходит
- признаки износа
- сложность замены (1-5)
- среднюю цену (в рублях)",
            
            'oil' => "Определи:
- тип масла (ATF, CVT, DCT)
- вязкость
- спецификации
- для каких АКПП подходит
- объем заливки (литры)
- среднюю цену за литр (в рублях)",
            
            'general' => "Определи:
- основную тему статьи
- тип проблемы с АКПП
- ключевые симптомы
- возможные решения
- упомянутые запчасти
- полезность для механика (1-5)"
        ];
        
        $specific_prompt = $specific_prompts[$content_type] ?? $specific_prompts['general'];
        
        $text_limit = mb_substr($text, 0, 4000);
        
        return $base_prompt . $specific_prompt . "\n\nТекст для анализа:\n" . $text_limit;
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
                    [
                        'role' => 'system',
                        'content' => 'Ты эксперт по ремонту автоматических коробок передач. Отвечай только в формате JSON.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => 2000
            ])
        ];
        
        $response = wp_remote_post($this->api_url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('Ошибка API OpenAI: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            $this->log_error('Ошибка OpenAI: ' . json_encode($data['error']));
            return false;
        }
        
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        
        return false;
    }
    
    /**
     * Парсинг ответа AI
     */
    private function parse_response($response, $content_type) {
        // Пытаемся извлечь JSON из ответа
        preg_match('/\{[\s\S]*\}/', $response, $matches);
        
        if (empty($matches)) {
            return $this->get_mock_analysis('', $content_type);
        }
        
        $result = json_decode($matches[0], true);
        
        if (!$result) {
            return $this->get_mock_analysis('', $content_type);
        }
        
        // Добавляем метаданные
        $result['analyzed_at'] = current_time('mysql');
        $result['content_type'] = $content_type;
        $result['confidence'] = $this->calculate_confidence($result);
        
        return $result;
    }
    
    /**
     * Расчет уверенности AI
     */
    private function calculate_confidence($result) {
        $confidence = 70; // базовая уверенность
        
        // Проверяем наличие ключевых полей
        $key_fields = ['type', 'problems', 'symptoms', 'parts'];
        $present_fields = 0;
        
        foreach ($key_fields as $field) {
            if (isset($result[$field]) && !empty($result[$field])) {
                $present_fields++;
            }
        }
        
        $confidence += ($present_fields / count($key_fields)) * 20;
        
        return min(95, $confidence);
    }
    
    /**
     * Мок-анализ (при отсутствии API ключа)
     */
    private function get_mock_analysis($text, $content_type) {
        $analysis = [
            'analyzed_at' => current_time('mysql'),
            'content_type' => $content_type,
            'is_mock' => true,
            'confidence' => 60
        ];
        
        if ($content_type === 'transmission') {
            $analysis['type'] = $this->extract_transmission_type($text);
            $analysis['problems'] = $this->extract_problems($text);
            $analysis['symptoms'] = ['рывки при переключении', 'шум', 'пробуксовка'];
            $analysis['causes'] = ['износ фрикционов', 'загрязнение масла', 'неисправность соленоидов'];
            $analysis['parts'] = ['Ремкомплект АКПП', 'Масло ATF', 'Фильтр АКПП'];
            $analysis['repair_cost'] = rand(30000, 150000);
            $analysis['difficulty'] = rand(3, 5);
        } elseif ($content_type === 'part') {
            $analysis['part_type'] = 'Фрикцион';
            $analysis['part_number'] = $this->extract_part_number($text);
            $analysis['transmissions'] = ['A750E', 'U660E', 'RE5R05A'];
            $analysis['signs_of_wear'] = ['проскальзывание', 'рывки', 'загрязнение масла'];
            $analysis['replacement_difficulty'] = rand(2, 4);
            $analysis['avg_price'] = rand(1000, 15000);
        } elseif ($content_type === 'oil') {
            $analysis['oil_type'] = 'ATF';
            $analysis['viscosity'] = 'ATF WS';
            $analysis['specifications'] = ['Toyota WS', 'JWS 3324'];
            $analysis['transmissions'] = ['A750E', 'U660E', 'A960E'];
            $analysis['fill_volume'] = rand(6, 12);
            $analysis['price_per_liter'] = rand(500, 2500);
        } else {
            $analysis['main_topic'] = 'Ремонт АКПП';
            $analysis['key_symptoms'] = ['проблемы с переключением', 'шум', 'вибрация'];
            $analysis['solutions'] = ['диагностика', 'замена масла', 'ремонт гидроблока'];
            $analysis['mentioned_parts'] = ['соленоиды', 'фрикционы', 'масло'];
            $analysis['usefulness'] = rand(3, 5);
        }
        
        return $analysis;
    }
    
    /**
     * Извлечение типа АКПП из текста
     */
    private function extract_transmission_type($text) {
        $patterns = [
            '4AT' => '/4\s*AT|4AT|A4/',
            '5AT' => '/5\s*AT|5AT|A5/',
            '6AT' => '/6\s*AT|6AT|A6/',
            '8AT' => '/8\s*AT|8AT|A8/',
            'CVT' => '/CVT/',
            'DCT' => '/DCT|DSG/'
        ];
        
        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $text)) {
                return $type;
            }
        }
        
        return 'Не определен';
    }
    
    /**
     * Извлечение проблем из текста
     */
    private function extract_problems($text) {
        $problems = [];
        $keywords = [
            'рывки' => 'рывки при переключении',
            'шум' => 'шум в АКПП',
            'пробуксовка' => 'пробуксовка',
            'удары' => 'удары при переключении',
            'течь' => 'течь масла',
            'перегрев' => 'перегрев'
        ];
        
        foreach ($keywords as $keyword => $description) {
            if (stripos($text, $keyword) !== false) {
                $problems[] = $description;
            }
        }
        
        return !empty($problems) ? $problems : ['неисправность АКПП'];
    }
    
    /**
     * Извлечение номера детали
     */
    private function extract_part_number($text) {
        preg_match('/[A-Z0-9]{6,15}/', $text, $matches);
        return $matches[0] ?? 'Не указан';
    }
    
    /**
     * Анализ изображения (через Vision API)
     */
    public function analyze_image($image_url) {
        if (empty($this->api_key)) {
            return ['description' => 'Изображение требует анализа', 'confidence' => 50];
        }
        
        $args = [
            'method' => 'POST',
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'gpt-4-vision-preview',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Опиши, что изображено на фото. Если это деталь АКПП, определи что именно.'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => $image_url
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 500
            ])
        ];
        
        $response = wp_remote_post($this->api_url, $args);
        
        if (is_wp_error($response)) {
            return ['description' => 'Ошибка анализа изображения', 'confidence' => 30];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            return [
                'description' => $data['choices'][0]['message']['content'],
                'confidence' => 85
            ];
        }
        
        return ['description' => 'Не удалось проанализировать', 'confidence' => 40];
    }
    
    /**
     * Проверка статуса API ключа
     */
    public function check_api_key_status() {
        if (empty($this->api_key)) {
            return ['valid' => false, 'message' => 'API ключ не настроен'];
        }
        
        $args = [
            'method' => 'GET',
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key
            ]
        ];
        
        $response = wp_remote_get('https://api.openai.com/v1/models', $args);
        
        if (is_wp_error($response)) {
            return ['valid' => false, 'message' => 'Ошибка проверки ключа'];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200) {
            return ['valid' => true, 'message' => 'API ключ действителен'];
        } elseif ($status_code === 401) {
            return ['valid' => false, 'message' => 'Неверный API ключ'];
        } else {
            return ['valid' => false, 'message' => "HTTP ошибка: {$status_code}"];
        }
    }
    
    private function log_error($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_AI] ОШИБКА: ' . $message);
        }
    }
}
