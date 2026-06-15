<?php
if (!defined('ABSPATH')) exit;

class AKPP_AI_Analyzer {
    
    private $api_key;
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    private $model = 'gpt-3.5-turbo'; // Или 'gpt-4', или локальная модель

    public function __construct() {
        // Загружаем API ключ из настроек WordPress
        $this->api_key = get_option('akpp_openai_api_key', '');

        // AJAX хук для анализа текста
        add_action('wp_ajax_akpp_analyze_with_ai', [$this, 'ajax_analyze_with_ai']);
    }

    /**
     * Основной метод анализа текста с помощью AI
     *
     * @param string $text Текст для анализа (описание проблемы, лог ошибки и т.д.)
     * @return array Результат анализа или ошибка
     */
    public function analyze($text) {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'message' => 'OpenAI API ключ не настроен в настройках CRM.'
            ];
        }

        if (empty(trim($text))) {
            return [
                'success' => false,
                'message' => 'Текст для анализа пуст.'
            ];
        }

        // Ограничиваем длину текста для экономии токенов (макс ~4000 символов)
        $text = mb_substr($text, 0, 4000);

        $messages = [
            [
                'role' => 'system',
                'content' => $this->get_system_prompt()
            ],
            [
                'role' => 'user',
                'content' => "Проанализируй следующее описание: \n\n" . $text
            ]
        ];

        $response = wp_remote_post($this->api_url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode([
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.3, // Низкая температура для более точных и фактологических ответов
                'response_format' => [ 'type' => 'json_object' ] // Требуем JSON ответ
            ])
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Ошибка соединения с AI API: ' . $response->get_error_message()
            ];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_msg = isset($body['error']['message']) ? $body['error']['message'] : 'Неизвестная ошибка API';
            return [
                'success' => false,
                'message' => "Ошибка API (HTTP {$http_code}): {$error_msg}"
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['choices'][0]['message']['content'])) {
            $content = $body['choices'][0]['message']['content'];
            
            // Пытаемся распарсить JSON ответ
            $parsed = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'success' => true,
                    'data' => $parsed,
                    'raw_response' => $content
                ];
            } else {
                // Если AI не вернул валидный JSON, возвращаем как есть
                return [
                    'success' => true,
                    'data' => ['analysis' => $content],
                    'raw_response' => $content
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Некорректный ответ от AI API'
        ];
    }

    /**
     * Системный промпт для настройки поведения AI
     */
    private function get_system_prompt() {
        return "Ты — опытный мастер-приемщик и диагност по автоматическим коробкам передач (АКПП, вариаторы, роботы). 
Твоя задача — проанализировать описание проблемы клиента или текст с парсера и вернуть СТРОГО в формате JSON следующую структуру:
{
  \"problem_type\": \"Краткий тип проблемы (например: 'Пинки при переключении', 'Утечка масла', 'Ошибка соленоида')\",
  \"severity\": \"Оценка серьезности от 1 до 10 (10 - критическая, требуется эвакуатор)\",
  \"probable_causes\": [\"Причина 1\", \"Причина 2\", \"Причина 3\"],
  \"recommended_parts\": [\"Название запчасти 1\", \"Название запчасти 2\"],
  \"recommended_oil\": \"Рекомендуемый тип масла (ATF/CVT/DCT) и примерный объем\",
  \"advice_for_client\": \"Краткий профессиональный совет для клиента на русском языке (2-3 предложения)\"
}
Не добавляй никакой текст вне JSON-объекта. Если информации недостаточно, укажи это в advice_for_client.";
    }

    /**
     * AJAX обработчик для вызова из админки или фронтенда
     */
    public function ajax_analyze_with_ai() {
        check_ajax_referer('akpp_crm_nonce', 'nonce');
        
        if (!current_user_can('edit_posts') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Недостаточно прав для использования AI']);
        }

        $text = sanitize_textarea_field($_POST['text'] ?? '');
        
        if (empty($text)) {
            wp_send_json_error(['message' => 'Текст для анализа не предоставлен']);
        }

        $result = $this->analyze($text);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }
}

// Инициализация
new AKPP_AI_Analyzer();
