<?php
/**
 * АКПП45 CRM - Интеграция с Telegram Bot API
 * Отправка уведомлений, управление webhook и проверка соединения.
 *
 * @package AKPP_CRM
 * @version 4.3
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

class AKPP_Telegram {

    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;

    /**
     * Настройки бота
     */
    private $bot_token;
    private $admin_chat_id;
    private $api_url = 'https://api.telegram.org/bot';

    /**
     * Конструктор (защищен для Singleton)
     */
    private function __construct() {
        $this->bot_token     = sanitize_text_field(get_option('akpp_telegram_bot_token', ''));
        $this->admin_chat_id = sanitize_text_field(get_option('akpp_telegram_admin_chat_id', ''));
        
        // Регистрация хука для обработки входящих webhook (если настроен)
        add_action('rest_api_init', [$this, 'register_telegram_webhook_route']);
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
     * Регистрация REST маршрута для входящих обновлений от Telegram
     */
    public function register_telegram_webhook_route() {
        // Маршрут будет активен только если задан токен
        if (!empty($this->bot_token)) {
            register_rest_route('akpp/v1', '/telegram-webhook', [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_incoming_webhook'],
                'permission_callback' => '__return_true', // Валидация внутри по содержимому
            ]);
        }
    }

    /**
     * Отправка текстового сообщения в Telegram
     *
     * @param string $chat_id   ID чата или канала
     * @param string $text      Текст сообщения (поддерживает HTML/Markdown)
     * @param string $parse_mode Режим форматирования ('HTML' или 'Markdown')
     * @return bool|WP_Error    true при успехе, WP_Error при ошибке
     */
    public function send_message($chat_id, $text, $parse_mode = 'HTML') {
        if (empty($this->bot_token)) {
            return new WP_Error('telegram_no_token', __('Bot Token не настроен.', 'akpp-crm'));
        }

        if (empty($chat_id)) {
            return new WP_Error('telegram_no_chat_id', __('Chat ID не указан.', 'akpp-crm'));
        }

        $url = $this->api_url . $this->bot_token . '/sendMessage';

        $args = [
            'body' => [
                'chat_id'    => $chat_id,
                'text'       => $text,
                'parse_mode' => $parse_mode,
                'disable_web_page_preview' => true,
            ],
            'timeout' => 10,
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $this->log_error('Send Message Failed', $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200 || empty($body['ok'])) {
            $error_msg = isset($body['description']) ? $body['description'] : 'Unknown Telegram API error';
            $this->log_error('Send Message API Error', $error_msg);
            return new WP_Error('telegram_api_error', $error_msg);
        }

        return true;
    }

    /**
     * Отправка уведомления о новом лиде
     *
     * @param array $lead_data Данные лида
     */
    public function notify_new_lead($lead_data) {
        if (empty($this->admin_chat_id)) {
            return; // Уведомления не настроены
        }

        $name = sanitize_text_field($lead_data['client_name'] ?? 'Неизвестно');
        $phone = sanitize_text_field($lead_data['client_phone'] ?? 'Не указан');
        $car = sanitize_text_field($lead_data['car_brand'] ?? 'Не указана');
        $problem = sanitize_text_field($lead_data['problem'] ?? 'Не описана');
        $source = sanitize_text_field($lead_data['source'] ?? 'Сайт');

        $text = sprintf(
            "🔔 <b>Новый лид!</b>\n\n" .
            "👤 <b>Клиент:</b> %s\n" .
            "📞 <b>Телефон:</b> %s\n" .
            "🚗 <b>Авто:</b> %s\n" .
            "🔧 <b>Проблема:</b> %s\n" .
            "📍 <b>Источник:</b> %s\n\n" .
            "<a href=\"%s\">Открыть в CRM</a>",
            $name,
            $phone,
            $car,
            $problem,
            $source,
            admin_url('admin.php?page=akpp-crm-leads')
        );

        $this->send_message($this->admin_chat_id, $text, 'HTML');
    }

    /**
     * Отправка уведомления о новой сделке
     *
     * @param int $deal_id ID сделки
     */
    public function notify_new_deal($deal_id) {
        if (empty($this->admin_chat_id)) {
            return;
        }

        global $wpdb;
        $deals_table = $wpdb->prefix . 'akpp_deals';
        $deal = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$deals_table} WHERE id = %d", $deal_id), ARRAY_A);

        if (!$deal) {
            return;
        }

        $text = sprintf(
            "💼 <b>Новая сделка #%d</b>\n\n" .
            "👤 <b>Клиент:</b> %s\n" .
            "🚗 <b>Авто:</b> %s %s (%d)\n" .
            "💰 <b>Сумма работ:</b> %s ₽\n\n" .
            "<a href=\"%s\">Открыть сделку в CRM</a>",
            $deal['id'],
            sanitize_text_field($deal['client_name']),
            sanitize_text_field($deal['make']),
            sanitize_text_field($deal['model']),
            intval($deal['year']),
            number_format(floatval($deal['work_cost']), 0, ',', ' '),
            admin_url('admin.php?page=akpp-crm-new-deal&deal_id=' . $deal['id'])
        );

        $this->send_message($this->admin_chat_id, $text, 'HTML');
    }

    /**
     * Установка Webhook для получения команд от бота
     *
     * @return bool|WP_Error
     */
    public function set_webhook() {
        if (empty($this->bot_token)) {
            return new WP_Error('telegram_no_token', __('Bot Token не настроен.', 'akpp-crm'));
        }

        $webhook_url = home_url('/wp-json/akpp/v1/telegram-webhook');
        $url = $this->api_url . $this->bot_token . '/setWebhook';

        $args = [
            'body' => [
                'url' => $webhook_url,
                'allowed_updates' => ['message', 'callback_query'],
            ],
            'timeout' => 10,
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['ok'])) {
            $error_msg = isset($body['description']) ? $body['description'] : 'Failed to set webhook';
            return new WP_Error('telegram_webhook_error', $error_msg);
        }

        update_option('akpp_telegram_webhook_set', true);
        return true;
    }

    /**
     * Проверка соединения и получение информации о боте
     *
     * @return array|WP_Error Информация о боте или ошибка
     */
    public function get_me() {
        if (empty($this->bot_token)) {
            return new WP_Error('telegram_no_token', __('Bot Token не настроен.', 'akpp-crm'));
        }

        $url = $this->api_url . $this->bot_token . '/getMe';
        $response = wp_remote_get($url, ['timeout' => 10]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['ok'])) {
            $error_msg = isset($body['description']) ? $body['description'] : 'Failed to get bot info';
            return new WP_Error('telegram_get_me_error', $error_msg);
        }

        return $body['result'];
    }

    /**
     * Обработка входящих сообщений от Telegram (Webhook)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_incoming_webhook($request) {
        $data = $request->get_json_params();

        if (empty($data) || empty($data['message'])) {
            return new WP_REST_Response(['ok' => true], 200);
        }

        $message = $data['message'];
        $chat_id = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? '';

        if (!$chat_id || empty($text)) {
            return new WP_REST_Response(['ok' => true], 200);
        }

        // Простая обработка команд (можно расширить)
        if (strpos($text, '/start') === 0) {
            $welcome_text = "👋 Добро пожаловать в бот АКПП45 CRM!\n\n" .
                            "Доступные команды:\n" .
                            "/stats - Статистика за сегодня\n" .
                            "/deals - Последние сделки";
            
            $this->send_message($chat_id, $welcome_text, 'Markdown');
        } elseif (strpos($text, '/stats') === 0) {
            global $wpdb;
            $deals_table = $wpdb->prefix . 'akpp_deals';
            $today = current_time('Y-m-d');
            
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$deals_table} WHERE DATE(created_at) = %s",
                $today
            ));
            
            $this->send_message($chat_id, "📊 Статистика за сегодня:\nНовых сделок: <b>{$count}</b>", 'HTML');
        }

        // Telegram всегда ожидает ответ 200 OK
        return new WP_REST_Response(['ok' => true], 200);
    }

    /**
     * Логирование ошибок
     *
     * @param string $context Контекст ошибки
     * @param string $msg     Текст ошибки
     */
    private function log_error($context, $msg) {
        error_log(sprintf('[AKPP Telegram] ERROR: %s - %s', $context, $msg));
    }
}
