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
        add_action('rest_api_init', [$this, 'register_webhook_endpoint']);
        add_action('wp_ajax_akpp_save_telegram_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_akpp_send_test_telegram', [$this, 'ajax_send_test']);
        add_action('wp_ajax_akpp_set_telegram_webhook', [$this, 'ajax_set_webhook']);
    }
    
    /**
     * Загрузка настроек
     */
    private function load_settings() {
        $this->bot_token = get_option('akpp_telegram_bot_token', '');
    }
    
    /**
     * Сохранение настроек (AJAX)
     */
    public function ajax_save_settings() {
        if (!check_ajax_referer('akpp_telegram_settings_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        $bot_token = isset($_POST['bot_token']) ? sanitize_text_field($_POST['bot_token']) : '';
        
        if (empty($bot_token)) {
            wp_send_json_error('Bot Token не может быть пустым');
            return;
        }
        
        $result = $this->save_settings($bot_token);
        
        if ($result) {
            wp_send_json_success(['message' => 'Настройки Telegram сохранены, webhook установлен']);
        } else {
            wp_send_json_error('Ошибка сохранения настроек');
        }
    }
    
    /**
     * Отправка тестового сообщения (AJAX)
     */
    public function ajax_send_test() {
        if (!check_ajax_referer('akpp_telegram_settings_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        $admin_chat_id = get_option('akpp_telegram_admin_chat_id', '');
        
        if (empty($admin_chat_id)) {
            wp_send_json_error('Сначала получите chat_id администратора (напишите /start боту)');
            return;
        }
        
        $result = $this->send_message($admin_chat_id, '✅ Тестовое сообщение от CRM АКПП45! Бот работает корректно.');
        
        if ($result) {
            wp_send_json_success(['message' => 'Тестовое сообщение отправлено']);
        } else {
            wp_send_json_error('Ошибка отправки сообщения');
        }
    }
    
    /**
     * Установка webhook (AJAX)
     */
    public function ajax_set_webhook() {
        if (!check_ajax_referer('akpp_telegram_settings_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        $result = $this->set_webhook();
        
        if ($result) {
            wp_send_json_success(['message' => 'Webhook успешно установлен']);
        } else {
            wp_send_json_error('Ошибка установки webhook');
        }
    }
    
    /**
     * Сохранение настроек
     */
    public function save_settings($bot_token) {
        update_option('akpp_telegram_bot_token', sanitize_text_field($bot_token));
        $this->bot_token = $bot_token;
        
        return $this->set_webhook();
    }
    
    /**
     * Регистрация webhook endpoint
     */
    public function register_webhook_endpoint() {
        register_rest_route('akpp/v1', '/telegram-webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true'
        ]);
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
     * Обработка входящего webhook
     */
    public function handle_webhook($request) {
        $data = $request->get_json_params();
        
        if (!$data) {
            return new WP_REST_Response(['status' => 'error'], 400);
        }
        
        if (isset($data['message'])) {
            $this->handle_message($data['message']);
        }
        
        if (isset($data['callback_query'])) {
            $this->handle_callback_query($data['callback_query']);
        }
        
        return new WP_REST_Response(['status' => 'ok'], 200);
    }
    
    /**
     * Обработка сообщения
     */
    private function handle_message($message) {
        $chat_id = $message['chat']['id'];
        $text = trim($message['text'] ?? '');
        $user_id = $message['from']['id'];
        $username = $message['from']['username'] ?? '';
        $first_name = $message['from']['first_name'] ?? '';
        
        // Регистрируем сотрудника
        $this->register_employee($user_id, $chat_id, $username, $first_name);
        
        // Обработка команд
        if (strpos($text, '/') === 0) {
            $this->handle_command($chat_id, $text, $user_id);
        } else {
            $this->send_message($chat_id, 'Используйте команды: /start, /help, /status');
        }
    }
    
    /**
     * Регистрация сотрудника
     */
    private function register_employee($telegram_id, $chat_id, $username, $first_name) {
        global $wpdb;
        $table_employees = $wpdb->prefix . 'akpp_employees';
        
        $employee = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_employees} WHERE telegram_id = %s",
            $telegram_id
        ));
        
        if ($employee) {
            $wpdb->update(
                $table_employees,
                [
                    'telegram_chat_id' => $chat_id,
                    'telegram_username' => $username
                ],
                ['telegram_id' => $telegram_id],
                ['%s', '%s'],
                ['%s']
            );
            
            // Сохраняем chat_id администратора
            $admin_chat_id = get_option('akpp_telegram_admin_chat_id', '');
            if (empty($admin_chat_id)) {
                update_option('akpp_telegram_admin_chat_id', $chat_id);
            }
            
            $this->send_message($chat_id, "✅ Вы успешно привязаны к профилю сотрудника {$first_name}!");
        } else {
            $this->send_message($chat_id, "❌ Ваш Telegram не привязан к сотруднику. Обратитесь к администратору.");
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
                $this->send_message($chat_id, '❌ Неизвестная команда. Используйте /help.');
        }
    }
    
    /**
     * Обработка callback query
     */
    private function handle_callback_query($callback_query) {
        // Для будущих интерактивных кнопок
    }
    
    /**
     * Отправка сообщения
     */
    public function send_message($chat_id, $text, $keyboard = null) {
        if (empty($this->bot_token)) {
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
     * Уведомление сотрудника
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
     * Уведомление всех сотрудников
     */
    public function notify_all($title, $message, $role = null) {
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
            usleep(100000);
        }
        
        return count($employees);
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
        
        $leads = $wpdb->get_results("SELECT * FROM {$table_leads} WHERE status = 'new' ORDER BY created_at DESC LIMIT 10");
        
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
            $this->send_message($chat_id, "❌ Сотрудник не найден");
            return;
        }
        
        $deals = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_deals} WHERE employee_id = %d AND status IN ('new', 'diagnostic', 'in_work') ORDER BY created_at DESC LIMIT 10",
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
     * Отправка профиля сотрудника
     */
    private function send_profile($chat_id, $telegram_id) {
        global $wpdb;
        $table_employees = $wpdb->prefix . 'akpp_employees';
        
        $employee = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_employees} WHERE telegram_id = %s",
            $telegram_id
        ));
        
        if (!$employee) {
            $this->send_message($chat_id, "❌ Сотрудник не найден");
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
     * Приветственное сообщение
     */
    private function get_start_message() {
        return "🤖 <b>AKPP45 CRM Бот</b>\n\n"
             . "Добро пожаловать! Я помогу вам управлять CRM системой.\n\n"
             . "📋 <b>Доступные команды:</b>\n"
             . "/help - список команд\n"
             . "/status - статус системы\n"
             . "/leads - новые лиды\n"
             . "/deals - мои сделки\n"
             . "/profile - мой профиль";
    }
    
    /**
     * Справка
     */
    private function get_help_message() {
        return "📚 <b>Справка по командам</b>\n\n"
             . "<b>Основные:</b>\n"
             . "/start - начать работу\n"
             . "/help - показать справку\n"
             . "/status - статус системы\n\n"
             . "<b>Данные:</b>\n"
             . "/leads - новые лиды\n"
             . "/deals - мои сделки\n"
             . "/profile - мой профиль\n\n"
             . "❓ Вопросы: @akppkgn";
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
