<?php
/**
 * Класс для интеграции с Telegram ботом
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_Telegram {
    
    private static $instance = null;
    private $bot_token = '';
    private $api_url = 'https://api.telegram.org/bot';
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_settings();
        $this->register_webhook();
    }
    
    private function load_settings() {
        $this->bot_token = get_option('akpp_telegram_bot_token', '');
    }
    
    /**
     * Регистрация webhook для бота
     */
    private function register_webhook() {
        add_action('rest_api_init', function() {
            register_rest_route('akpp/v1', '/telegram-webhook', [
                'methods' => 'POST',
                'callback' => [$this, 'handle_webhook'],
                'permission_callback' => '__return_true'
            ]);
        });
        
        // Регистрация cron задачи для обновления webhook
        add_action('akpp_update_telegram_webhook', [$this, 'set_webhook']);
    }
    
    /**
     * Установка webhook
     */
    public function set_webhook() {
        if (empty($this->bot_token)) {
            return false;
        }
        
        $webhook_url = home_url('/wp-json/akpp/v1/telegram-webhook');
        $url = $this->api_url . $this->bot_token . '/setWebhook';
        
        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'body' => [
                'url' => $webhook_url,
                'allowed_updates' => json_encode(['message', 'callback_query'])
            ]
        ];
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('Ошибка установки webhook: ' . $response->get_error_message());
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($body && $body['ok']) {
            $this->log_event('Webhook установлен: ' . $webhook_url);
            return true;
        }
        
        return false;
    }
    
    /**
     * Обработка входящих сообщений
     */
    public function handle_webhook($request) {
        $data = $request->get_json_params();
        
        if (!$data) {
            return new WP_REST_Response(['status' => 'error'], 400);
        }
        
        $this->log_event('Получено сообщение: ' . json_encode($data));
        
        // Обработка сообщения
        if (isset($data['message'])) {
            $this->handle_message($data['message']);
        }
        
        // Обработка callback query
        if (isset($data['callback_query'])) {
            $this->handle_callback_query($data['callback_query']);
        }
        
        return new WP_REST_Response(['status' => 'ok'], 200);
    }
    
    /**
     * Обработка текстового сообщения
     */
    private function handle_message($message) {
        $chat_id = $message['chat']['id'];
        $text = trim($message['text'] ?? '');
        $user_id = $message['from']['id'];
        $username = $message['from']['username'] ?? '';
        $first_name = $message['from']['first_name'] ?? '';
        
        // Регистрируем chat_id сотрудника
        $this->register_employee_chat($user_id, $chat_id, $username, $first_name);
        
        // Обработка команд
        if (strpos($text, '/') === 0) {
            $this->handle_command($chat_id, $text, $user_id);
        } else {
            // Обычное сообщение - перенаправляем в CRM чат
            $this->forward_to_crm($chat_id, $text, $user_id, $first_name);
        }
    }
    
    /**
     * Обработка команд
     */
    private function handle_command($chat_id, $text, $telegram_id) {
        $command = strtok($text, ' ');
        $param = strtok(' ');
        
        switch ($command) {
            case '/start':
                $this->send_message($chat_id, $this->get_start_message());
                break;
                
            case '/help':
                $this->send_message($chat_id, $this->get_help_message());
                break;
                
            case '/status':
                $this->send_status($chat_id);
                break;
                
            case '/leads':
                $this->send_new_leads($chat_id);
                break;
                
            case '/deals':
                $this->send_my_deals($chat_id, $telegram_id);
                break;
                
            case '/profile':
                $this->send_profile($chat_id, $telegram_id);
                break;
                
            default:
                $this->send_message($chat_id, '❌ Неизвестная команда. Используйте /help для списка команд.');
        }
    }
    
    /**
     * Регистрация chat_id сотрудника
     */
    private function register_employee_chat($telegram_id, $chat_id, $username, $first_name) {
        global $wpdb;
        $table_employees = $wpdb->prefix . 'akpp_employees';
        
        $employee = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_employees} WHERE telegram_id = %s",
            $telegram_id
        ));
        
        if ($employee) {
            $wpdb->update(
                $table_employees,
                ['telegram_chat_id' => $chat_id, 'telegram_username' => $username],
                ['telegram_id' => $telegram_id],
                ['%s', '%s'],
                ['%s']
            );
        }
    }
    
    /**
     * Отправка сообщения
     */
    public function send_message($chat_id, $text, $keyboard = null) {
        if (empty($this->bot_token)) {
            $this->log_error('Telegram бот не настроен');
            return false;
        }
        
        $url = $this->api_url . $this->bot_token . '/sendMessage';
        
        $body = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        
        if ($keyboard) {
            $body['reply_markup'] = json_encode($keyboard);
        }
        
        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'body' => $body
        ];
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('Ошибка отправки: ' . $response->get_error_message());
            return false;
        }
        
        return true;
    }
    
    /**
     * Отправка уведомления сотруднику
     */
    public function notify_employee($employee_id, $title, $message) {
        global $wpdb;
        $table_employees = $wpdb->prefix . 'akpp_employees';
        
        $employee = $wpdb->get_row($wpdb->prepare(
            "SELECT telegram_chat_id FROM {$table_employees} WHERE id = %d",
            $employee_id
        ));
        
        if ($employee && $employee->telegram_chat_id) {
            $text = "🔔 <b>{$title}</b>\n\n{$message}";
            return $this->send_message($employee->telegram_chat_id, $text);
        }
        
        return false;
    }
    
    /**
     * Отправка уведомления всем сотрудникам
     */
    public function notify_all_employees($title, $message, $role = null) {
        global $wpdb;
        $table_employees = $wpdb->prefix . 'akpp_employees';
        
        $where = "telegram_chat_id IS NOT NULL AND telegram_chat_id != ''";
        if ($role) {
            $where .= $wpdb->prepare(" AND role = %s", $role);
        }
        
        $employees = $wpdb->get_col("SELECT telegram_chat_id FROM {$table_employees} WHERE {$where}");
        
        $text = "🔔 <b>{$title}</b>\n\n{$message}";
        
        foreach ($employees as $chat_id) {
            $this->send_message($chat_id, $text);
            usleep(100000); // Задержка между отправками
        }
        
        return count($employees);
    }
    
    /**
     * Уведомление о новом лиде
     */
    public function notify_new_lead($lead_data) {
        $message = "🆕 <b>Новый лид!</b>\n\n";
        $message .= "👤 Клиент: {$lead_data['client_name']}\n";
        $message .= "📞 Телефон: {$lead_data['client_phone']}\n";
        $message .= "📧 Email: {$lead_data['client_email']}\n";
        $message .= "🚗 Авто: {$lead_data['car_brand']}\n";
        $message .= "📝 Проблема: " . substr($lead_data['problem'], 0, 100);
        
        return $this->notify_all_employees('Новый лид в CRM', $message, 'guide');
    }
    
    /**
     * Уведомление о новой сделке
     */
    public function notify_new_deal($deal_data) {
        $message = "📋 <b>Новая сделка!</b>\n\n";
        $message .= "№: {$deal_data['id']}\n";
        $message .= "Клиент: {$deal_data['client_name']}\n";
        $message .= "Сумма: " . number_format($deal_data['total_amount'], 0, ',', ' ') . " ₽\n";
        $message .= "Статус: {$deal_data['status']}";
        
        $employee_id = $deal_data['employee_id'] ?? 0;
        if ($employee_id) {
            return $this->notify_employee($employee_id, 'Новая сделка назначена', $message);
        }
        
        return $this->notify_all_employees('Новая сделка в CRM', $message, 'master');
    }
    
    /**
     * Уведомление об изменении статуса сделки
     */
    public function notify_deal_status_change($deal_id, $old_status, $new_status, $client_name) {
        $message = "🔄 <b>Изменение статуса сделки</b>\n\n";
        $message .= "№: {$deal_id}\n";
        $message .= "Клиент: {$client_name}\n";
        $message .= "Статус: {$old_status} → {$new_status}";
        
        return $this->notify_all_employees('Статус сделки изменен', $message);
    }
    
    /**
     * Получение приветственного сообщения
     */
    private function get_start_message() {
        return "🤖 <b>AKPP45 CRM Бот</b>\n\n"
             . "Добро пожаловать! Я помогу вам управлять CRM системой.\n\n"
             . "📋 <b>Доступные команды:</b>\n"
             . "/help - список команд\n"
             . "/status - статус системы\n"
             . "/leads - новые лиды\n"
             . "/deals - мои сделки\n"
             . "/profile - мой профиль\n\n"
             . "💡 <i>Вы будете получать уведомления о новых лидах и сделках</i>";
    }
    
    /**
     * Получение помощи
     */
    private function get_help_message() {
        return "📚 <b>Справка по командам</b>\n\n"
             . "<b>Основные команды:</b>\n"
             . "/start - начать работу с ботом\n"
             . "/help - показать это сообщение\n"
             . "/status - статус системы\n\n"
             . "<b>Работа с данными:</b>\n"
             . "/leads - показать новые лиды\n"
             . "/deals - показать мои сделки\n"
             . "/profile - информация о профиле\n\n"
             . "<b>Уведомления:</b>\n"
             . "Бот автоматически присылает уведомления о:\n"
             . "• Новых лидах\n"
             . "• Назначенных сделках\n"
             . "• Изменении статусов\n\n"
             . "❓ Вопросы: @akppkgn";
    }
    
    /**
     * Отправка статуса системы
     */
    private function send_status($chat_id) {
        global $wpdb;
        
        $deals_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_deals WHERE status = 'in_work'");
        $leads_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_leads WHERE status = 'new'");
        
        $message = "📊 <b>Статус системы</b>\n\n"
                 . "🟢 Активных сделок: {$deals_count}\n"
                 . "🟡 Новых лидов: {$leads_count}\n"
                 . "🤖 Бот активен\n"
                 . "📅 " . current_time('d.m.Y H:i:s');
        
        $this->send_message($chat_id, $message);
    }
    
    /**
     * Отправка новых лидов
     */
    private function send_new_leads($chat_id) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'akpp_leads';
        
        $leads = $wpdb->get_results(
            "SELECT * FROM {$table_leads} 
            WHERE status = 'new' 
            ORDER BY created_at DESC 
            LIMIT 10"
        );
        
        if (empty($leads)) {
            $this->send_message($chat_id, "📭 Новых лидов нет");
            return;
        }
        
        $message = "📋 <b>Новые лиды</b>\n\n";
        
        foreach ($leads as $lead) {
            $message .= "🆕 <b>#{$lead->id}</b>\n";
            $message .= "👤 {$lead->client_name}\n";
            $message .= "📞 {$lead->client_phone}\n";
            $message .= "📅 " . date('d.m.Y H:i', strtotime($lead->created_at)) . "\n\n";
        }
        
        $this->send_message($chat_id, $message);
    }
    
    /**
     * Отправка сделок сотрудника
     */
    private function send_my_deals($chat_id, $telegram_id) {
        global $wpdb;
        $table_employees = $wpdb->prefix . 'akpp_employees';
        $table_deals = $wpdb->prefix . 'akpp_deals';
        
        $employee = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_employees} WHERE telegram_id = %s",
            $telegram_id
        ));
        
        if (!$employee) {
            $this->send_message($chat_id, "❌ Сотрудник не найден в системе");
            return;
        }
        
        $deals = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_deals} 
            WHERE employee_id = %d 
            AND status IN ('new', 'diagnostic', 'in_work')
            ORDER BY created_at DESC 
            LIMIT 10",
            $employee->id
        ));
        
        if (empty($deals)) {
            $this->send_message($chat_id, "📭 Нет активных сделок");
            return;
        }
        
        $message = "📋 <b>Мои активные сделки</b>\n\n";
        
        foreach ($deals as $deal) {
            $status_icon = $deal->status === 'new' ? '🆕' : ($deal->status === 'diagnostic' ? '🔧' : '⚙️');
            $message .= "{$status_icon} <b>#{$deal->id}</b>\n";
            $message .= "🚗 {$deal->make} {$deal->model}\n";
            $message .= "💰 " . number_format($deal->total_amount, 0, ',', ' ') . " ₽\n\n";
        }
        
        $this->send_message($chat_id, $message);
    }
    
    /**
     * Отправка профиля
     */
    private function send_profile($chat_id, $telegram_id) {
        global $wpdb;
        $table_employees = $wpdb->prefix . 'akpp_employees';
        
        $employee = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_employees} WHERE telegram_id = %s",
            $telegram_id
        ));
        
        if (!$employee) {
            $this->send_message($chat_id, "❌ Сотрудник не найден в системе");
            return;
        }
        
        $message = "👤 <b>Мой профиль</b>\n\n"
                 . "Имя: {$employee->name}\n"
                 . "Роль: {$employee->role}\n"
                 . "Процент: {$employee->percent}%\n"
                 . "Статус: " . ($employee->is_active ? 'Активен' : 'Неактивен');
        
        $this->send_message($chat_id, $message);
    }
    
    /**
     * Сохранение настроек
     */
    public function save_settings($bot_token) {
        update_option('akpp_telegram_bot_token', sanitize_text_field($bot_token));
        $this->bot_token = $bot_token;
        
        // Устанавливаем webhook
        return $this->set_webhook();
    }
    
    private function log_error($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_TELEGRAM] ОШИБКА: ' . $message);
        }
    }
    
    private function log_event($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_TELEGRAM] СОБЫТИЕ: ' . $message);
        }
    }
}
