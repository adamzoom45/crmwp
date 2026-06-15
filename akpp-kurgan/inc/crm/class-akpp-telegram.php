<?php
if (!defined('ABSPATH')) exit;

class AKPP_Telegram {
    
    private $bot_token;
    private $chat_id;
    private $api_url;

    public function __construct() {
        // Загружаем настройки из базы данных
        $this->bot_token = get_option('akpp_telegram_bot_token', '');
        $this->chat_id = get_option('akpp_telegram_chat_id', '');
        
        if (!empty($this->bot_token)) {
            $this->api_url = "https://api.telegram.org/bot{$this->bot_token}/";
        }

        // Хук для получения Webhook от Telegram (доступен без авторизации WP)
        add_action('wp_ajax_nopriv_akpp_telegram_webhook', [$this, 'handle_webhook']);
        
        // AJAX для установки Webhook из админки
        add_action('wp_ajax_akpp_set_telegram_webhook', [$this, 'set_webhook_ajax']);
    }

    /**
     * Отправка сообщения в Telegram
     */
    public function send_message($chat_id, $text, $parse_mode = 'HTML') {
        if (empty($this->bot_token)) {
            return false;
        }

        $response = wp_remote_post($this->api_url . 'sendMessage', [
            'body' => [
                'chat_id' => $chat_id,
                'text' => $text,
                'parse_mode' => $parse_mode,
                'disable_web_page_preview' => true
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Telegram API Error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['ok']) && $body['ok'] === true;
    }

    /**
     * Уведомление о новом лиде
     */
    public function notify_new_lead($lead_data) {
        if (empty($this->chat_id)) return;

        $text = "🔔 <b>Новый лид!</b>\n\n";
        $text .= "👤 <b>Имя:</b> " . sanitize_text_field($lead_data['name']) . "\n";
        $text .= "📞 <b>Телефон:</b> " . sanitize_text_field($lead_data['phone']) . "\n";
        $text .= "🌐 <b>Источник:</b> " . sanitize_text_field($lead_data['source']) . "\n";
        $text .= "📝 <b>Сообщение:</b> " . sanitize_textarea_field($lead_data['message']) . "\n";
        $text .= "\n<a href='" . admin_url('admin.php?page=akpp-leads') . "'>Открыть в CRM</a>";

        $this->send_message($this->chat_id, $text);
    }

    /**
     * Уведомление о новой сделке
     */
    public function notify_new_deal($deal_data) {
        if (empty($this->chat_id)) return;

        $text = "💰 <b>Новая сделка!</b>\n\n";
        $text .= "👤 <b>Клиент:</b> " . sanitize_text_field($deal_data['client_name']) . "\n";
        $text .= "🚗 <b>Авто:</b> ID #" . intval($deal_data['vehicle_id']) . "\n";
        $text .= "💵 <b>Сумма:</b> " . number_format(floatval($deal_data['total_amount']), 2, '.', ' ') . " ₽\n";
        $text .= "\n<a href='" . admin_url('admin.php?page=akpp-deals') . "'>Открыть в CRM</a>";

        $this->send_message($this->chat_id, $text);
    }

    /**
     * Обработка входящего Webhook от Telegram
     */
    public function handle_webhook() {
        // Простая проверка: если нет токена, игнорируем
        if (empty($this->bot_token)) {
            wp_die('Telegram bot not configured');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['message'])) {
            $chat_id = $input['message']['chat']['id'];
            $text = isset($input['message']['text']) ? trim($input['message']['text']) : '';
            
            // Проверка авторизации (упрощенная: сверяем chat_id с настроек)
            // В продакшене лучше использовать webhook secret token от Telegram
            if ($this->chat_id && $chat_id != $this->chat_id) {
                $this->send_message($chat_id, "⛔ Доступ запрещен.");
                wp_die('Unauthorized');
            }

            if (strpos($text, '/') === 0) {
                $this->process_command($chat_id, $text);
            }
        }
        
        wp_die('OK'); // Telegram ожидает HTTP 200 OK
    }

    /**
     * Обработка команд бота
     */
    private function process_command($chat_id, $text) {
        $parts = explode(' ', $text);
        $command = strtolower($parts[0]);

        switch ($command) {
            case '/start':
                $msg = "👋 Привет! Я бот управления <b>АКПП45 CRM</b>.\n\n";
                $msg .= "Доступные команды:\n";
                $msg .= "📊 /stats - Статистика за сегодня\n";
                $msg .= "📨 /leads - Последние 5 лидов\n";
                $msg .= "💼 /deals - Последние 5 сделок\n";
                $this->send_message($chat_id, $msg);
                break;

            case '/stats':
                $this->send_stats($chat_id);
                break;

            case '/leads':
                $this->send_recent_leads($chat_id);
                break;

            case '/deals':
                $this->send_recent_deals($chat_id);
                break;

            case '/vpn':
            case '/ssh':
                $this->send_message($chat_id, "⚠️ Команды управления сервером временно отключены из соображений безопасности.");
                break;

            default:
                $this->send_message($chat_id, "❓ Неизвестная команда. Используйте /start для списка команд.");
                break;
        }
    }

    /**
     * Отправка статистики
     */
    private function send_stats($chat_id) {
        global $wpdb;
        $today = date('Y-m-d');
        
        $leads_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}akpp_leads WHERE DATE(created_at) = %s", $today
        ));
        
        $deals_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}akpp_deals WHERE DATE(created_at) = %s", $today
        ));

        $revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_amount) FROM {$wpdb->prefix}akpp_deals WHERE status = 'completed' AND DATE(created_at) = %s", $today
        )) ?: 0;

        $msg = "📊 <b>Статистика за сегодня ({$today}):</b>\n\n";
        $msg .= "📨 Лидов: <b>{$leads_count}</b>\n";
        $msg .= "💼 Сделок: <b>{$deals_count}</b>\n";
        $msg .= "💰 Выручка: <b>" . number_format($revenue, 2, '.', ' ') . " ₽</b>";

        $this->send_message($chat_id, $msg);
    }

    /**
     * Отправка последних лидов
     */
    private function send_recent_leads($chat_id) {
        global $wpdb;
        $leads = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}akpp_leads ORDER BY created_at DESC LIMIT 5");
        
        if (empty($leads)) {
            $this->send_message($chat_id, "📭 Новых лидов пока нет.");
            return;
        }

        $msg = "📨 <b>Последние 5 лидов:</b>\n\n";
        foreach ($leads as $lead) {
            $msg .= "🔹 <b>{$lead->name}</b> ({$lead->phone})\n";
            $msg .= "   Статус: {$lead->status} | " . date('d.m H:i', strtotime($lead->created_at)) . "\n\n";
        }
        
        $this->send_message($chat_id, $msg);
    }

    /**
     * Отправка последних сделок
     */
    private function send_recent_deals($chat_id) {
        global $wpdb;
        $deals = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}akpp_deals ORDER BY created_at DESC LIMIT 5");
        
        if (empty($deals)) {
            $this->send_message($chat_id, "💼 Новых сделок пока нет.");
            return;
        }

        $msg = "💼 <b>Последние 5 сделок:</b>\n\n";
        foreach ($deals as $deal) {
            $status_emoji = $deal->status === 'completed' ? '✅' : '⏳';
            $msg .= "{$status_emoji} <b>{$deal->client_name}</b> - " . number_format($deal->total_amount, 0, '.', ' ') . " ₽\n";
            $msg .= "   Статус: {$deal->status} | " . date('d.m H:i', strtotime($deal->created_at)) . "\n\n";
        }
        
        $this->send_message($chat_id, $msg);
    }

    /**
     * AJAX метод для установки Webhook из админки
     */
    public function set_webhook_ajax() {
        check_ajax_referer('akpp_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Недостаточно прав']);
        }

        $webhook_url = admin_url('admin-ajax.php?action=akpp_telegram_webhook');
        
        $response = wp_remote_post($this->api_url . 'setWebhook', [
            'body' => ['url' => $webhook_url]
        ]);

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['ok']) && $body['ok']) {
            wp_send_json_success(['message' => 'Webhook успешно установлен: ' . $webhook_url]);
        } else {
            wp_send_json_error(['message' => 'Ошибка Telegram API: ' . ($body['description'] ?? 'Неизвестная ошибка')]);
        }
    }
}

// Инициализация
new AKPP_Telegram();
