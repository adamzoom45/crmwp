<?php
/**
 * АКПП45 CRM - AI Анализатор контента
 * Анализ распарсенных данных с помощью OpenAI / YandexGPT / GigaChat.
 *
 * @package AKPP_CRM
 * @version 4.3
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

class AKPP_AI_Analyzer {

    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;

    /**
     * Настройки AI
     */
    private $provider;
    private $api_key;
    private $model;

    /**
     * Конструктор
     */
    private function __construct() {
        $this->provider = get_option('akpp_ai_provider', 'openai'); // openai, yandex, gigachat
        $this->api_key  = sanitize_text_field(get_option('akpp_ai_api_key', ''));
        $this->model    = sanitize_text_field(get_option('akpp_ai_model', 'gpt-3.5-turbo'));
    }

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
     * Основной метод анализа контента
     *
     * @param string $content Текст для анализа
     * @param string $content_type Тип контента (transmission_related, parts_store, general)
     * @return array|WP_Error Структурированные данные или ошибка
     */
    public function analyze($content, $content_type = 'general') {
        if (empty($this->api_key)) {
            return new WP_Error('ai_no_api_key', __('API ключ не настроен в настройках CRM.', 'akpp-crm'));
        }

        if (empty(trim($content))) {
            return new WP_Error('ai_empty_content', __('Пустой контент для анализа.', 'akpp-crm'));
        }

        // Ограничиваем длину контента для экономии токенов (макс. 4000 символов)
        $content = mb_substr($content, 0, 4000);

        $prompt = $this->build_prompt($content, $content_type);

        if ($this->provider === 'openai') {
            return $this->call_openai($prompt);
        } elseif ($this->provider === 'yandex') {
            return $this->call_yandexgpt($prompt);
        } else {
            return new WP_Error('ai_unknown_provider', __('Неизвестный провайдер AI.', 'akpp-crm'));
        }
    }

    /**
     * Формирование промпта для AI
     */
    private function build_prompt($content, $content_type) {
        $context = "";
        if ($content_type === 'transmission_related') {
            $context = "Текст относится к ремонту или обслуживанию автоматических коробок передач (АКПП).";
        } elseif ($content_type === 'parts_store') {
            $context = "Текст относится к магазину автозапчастей или каталогу деталей.";
        }

        return "Ты эксперт по ремонту автомобилей и автоматических коробок передач (АКПП). 
{$context}
Проанализируй следующий текст и извлеки из него структурированную информацию в формате JSON. Не добавляй никакой разметки, только чистый JSON.

Структура JSON:
{
  \"problem_type\": \"Краткое описание основной проблемы или темы (строка)\",
  \"symptoms\": [\"Симптом 1\", \"Симптом 2\"],
  \"causes\": [\"Причина 1\", \"Причина 2\"],
  \"solutions\": [\"Решение 1\", \"Решение 2\"],
  \"required_parts\": [\"Запчасть 1\", \"Запчасть 2\"],
  \"confidence\": 85
}

Текст для анализа:
\"\"\"
{$content}
\"\"\"";
    }

    /**
     * Вызов API OpenAI
     */
    private function call_openai($prompt) {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Ты полезный ассистент, который отвечает строго в формате JSON.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'] // Требование JSON (для gpt-3.5-turbo-0125 и новее)
            ]),
            'timeout' => 30,
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $this->log_error('OpenAI Request Failed', $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200 || empty($body['choices'][0]['message']['content'])) {
            $error_msg = $body['error']['message'] ?? 'Unknown OpenAI API error';
            $this->log_error('OpenAI API Error', $error_msg);
            return new WP_Error('openai_api_error', $error_msg);
        }

        $content = $body['choices'][0]['message']['content'];
        return $this->parse_json_response($content);
    }

    /**
     * Вызов API YandexGPT (базовая реализация для будущего расширения)
     */
    private function call_yandexgpt($prompt) {
        $this->log_error('YandexGPT', 'Реализация YandexGPT находится в разработке. Используйте OpenAI.');
        return new WP_Error('yandex_not_implemented', 'YandexGPT пока не реализован. Используйте OpenAI.');
    }

    /**
     * Парсинг и валидация JSON ответа от AI
     */
    private function parse_json_response($json_string) {
        // Очистка от возможных markdown-оберток (```json ... ```)
        $json_string = preg_replace('/^```json\s*/i', '', $json_string);
        $json_string = preg_replace('/\s*```$/i', '', $json_string);
        $json_string = trim($json_string);

        $data = json_decode($json_string, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_error('JSON Decode Error', json_last_error_msg() . ' | Raw: ' . mb_substr($json_string, 0, 200));
            return new WP_Error('ai_invalid_json', __('AI вернул некорректный JSON.', 'akpp-crm'));
        }

        // Базовая валидация структуры (должно быть хотя бы одно из ключевых полей)
        if (!isset($data['problem_type']) && !isset($data['symptoms'])) {
            $this->log_error('AI Validation Error', 'Missing required fields in JSON response');
            return new WP_Error('ai_invalid_structure', __('AI вернул JSON с неправильной структурой.', 'akpp-crm'));
        }

        return $data;
    }

    /**
     * Проверка API ключа (тестовый запрос)
     */
    public function test_api_key() {
        if (empty($this->api_key)) {
            return new WP_Error('ai_no_api_key', __('API ключ не указан.', 'akpp-crm'));
        }

        if ($this->provider === 'openai') {
            $url = 'https://api.openai.com/v1/models';
            $args = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                ],
                'timeout' => 10,
            ];
            
            $response = wp_remote_get($url, $args);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code === 200) {
                return true;
            } else {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $error_msg = $body['error']['message'] ?? 'Unknown error';
                return new WP_Error('openai_auth_error', $error_msg);
            }
        }

        return new WP_Error('ai_test_not_implemented', 'Тест ключа реализован только для OpenAI.');
    }

    /**
     * Логирование ошибок
     */
    private function log_error($context, $message) {
        error_log(sprintf('[AKPP AI Analyzer] ERROR: %s - %s', $context, $message));
    }
}
