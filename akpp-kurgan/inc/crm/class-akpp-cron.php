<?php
/**
 * Класс для управления Cron задачами
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_Cron {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp', [$this, 'schedule_events']);
        add_filter('cron_schedules', [$this, 'add_cron_intervals']);
        
        // Регистрация хуков
        add_action('akpp_sync_avito_dialogs', [$this, 'sync_avito_dialogs']);
        add_action('akpp_sync_avito_messages', [$this, 'sync_avito_messages']);
        add_action('akpp_cleanup_old_data', [$this, 'cleanup_old_data']);
        add_action('akpp_update_exchange_rates', [$this, 'update_exchange_rates']);
        add_action('akpp_ai_bulk_analysis', [$this, 'ai_bulk_analysis']);
    }
    
    /**
     * Добавление интервалов cron
     */
    public function add_cron_intervals($schedules) {
        $schedules['every_five_minutes'] = [
            'interval' => 300,
            'display' => __('Каждые 5 минут', 'akpp45-crm')
        ];
        
        $schedules['every_hour'] = [
            'interval' => 3600,
            'display' => __('Каждый час', 'akpp45-crm')
        ];
        
        $schedules['every_day'] = [
            'interval' => 86400,
            'display' => __('Каждый день', 'akpp45-crm')
        ];
        
        $schedules['every_week'] = [
            'interval' => 604800,
            'display' => __('Каждую неделю', 'akpp45-crm')
        ];
        
        return $schedules;
    }
    
    /**
     * Планирование событий
     */
    public function schedule_events() {
        // Синхронизация диалогов Авито (каждый час)
        if (!wp_next_scheduled('akpp_sync_avito_dialogs')) {
            wp_schedule_event(time(), 'every_hour', 'akpp_sync_avito_dialogs');
        }
        
        // Синхронизация сообщений Авито (каждые 5 минут)
        if (!wp_next_scheduled('akpp_sync_avito_messages')) {
            wp_schedule_event(time(), 'every_five_minutes', 'akpp_sync_avito_messages');
        }
        
        // Очистка старых данных (каждый день)
        if (!wp_next_scheduled('akpp_cleanup_old_data')) {
            wp_schedule_event(time(), 'every_day', 'akpp_cleanup_old_data');
        }
        
        // Обновление курсов валют (каждый день)
        if (!wp_next_scheduled('akpp_update_exchange_rates')) {
            wp_schedule_event(time(), 'every_day', 'akpp_update_exchange_rates');
        }
        
        // Пакетный AI анализ (каждый час)
        if (!wp_next_scheduled('akpp_ai_bulk_analysis')) {
            wp_schedule_event(time(), 'every_hour', 'akpp_ai_bulk_analysis');
        }
    }
    
    /**
     * Синхронизация диалогов Авито
     */
    public function sync_avito_dialogs() {
        $this->log_event('Запуск синхронизации диалогов Авито');
        
        if (!class_exists('AKPP_Avito')) {
            require_once AKPP_CRM_PATH . 'class-akpp-avito.php';
        }
        
        $avito = AKPP_Avito::get_instance();
        $dialogs = $avito->get_dialogs(50);
        
        $this->log_event('Синхронизировано диалогов: ' . count($dialogs));
    }
    
    /**
     * Синхронизация сообщений Авито
     */
    public function sync_avito_messages() {
        $this->log_event('Запуск синхронизации сообщений Авито');
        
        global $wpdb;
        $table_dialogs = $wpdb->prefix . 'akpp_avito_dialogs';
        
        $dialogs = $wpdb->get_col("SELECT dialog_id FROM {$table_dialogs} WHERE is_active = 1");
        
        if (empty($dialogs)) {
            return;
        }
        
        $token = $this->get_avito_token();
        if (!$token) {
            $this->log_error('Нет токена для синхронизации сообщений');
            return;
        }
        
        $account_id = get_option('akpp_avito_account_id', '');
        if (empty($account_id)) {
            return;
        }
        
        foreach ($dialogs as $dialog_id) {
            $this->sync_dialog_messages($account_id, $token, $dialog_id);
        }
        
        $this->log_event('Синхронизация сообщений завершена для ' . count($dialogs) . ' диалогов');
    }
    
    /**
     * Синхронизация сообщений диалога
     */
    private function sync_dialog_messages($account_id, $token, $dialog_id) {
        $url = 'https://api.avito.ru/messenger/v1/accounts/' . $account_id . '/chats/' . $dialog_id . '/messages?limit=50';
        
        $args = [
            'method' => 'GET',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ]
        ];
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['messages'])) {
            $this->save_messages($dialog_id, $data['messages']);
        }
    }
    
    /**
     * Сохранение сообщений
     */
    private function save_messages($dialog_id, $messages) {
        global $wpdb;
        $table_cache = $wpdb->prefix . 'akpp_avito_messages_cache';
        
        foreach ($messages as $msg) {
            $message_id = $msg['id'] ?? '';
            if (empty($message_id)) continue;
            
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_cache} WHERE message_id = %s",
                $message_id
            ));
            
            if (!$exists) {
                $wpdb->insert(
                    $table_cache,
                    [
                        'dialog_id' => $dialog_id,
                        'message_id' => $message_id,
                        'sender_id' => $msg['sender_id'] ?? '',
                        'sender_name' => $msg['sender_name'] ?? '',
                        'message_text' => $msg['text'] ?? '',
                        'is_incoming' => isset($msg['direction']) && $msg['direction'] === 'in' ? 1 : 0,
                        'created_at' => $msg['created_at'] ?? current_time('mysql')
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%d', '%s']
                );
            }
        }
    }
    
    /**
     * Получение токена Авито
     */
    private function get_avito_token() {
        if (!class_exists('AKPP_Avito')) {
            require_once AKPP_CRM_PATH . 'class-akpp-avito.php';
        }
        
        $avito = AKPP_Avito::get_instance();
        return $avito->get_active_token();
    }
    
    /**
     * Очистка старых данных
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        $this->log_event('Запуск очистки старых данных');
        
        // Удаление старых кэшей VIN (старше 90 дней)
        $table_vin = $wpdb->prefix . 'akpp_vin_cache';
        $wpdb->query("DELETE FROM {$table_vin} WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        
        // Удаление старых сообщений чата (старше 1 года)
        $table_chat = $wpdb->prefix . 'akpp_chat_messages';
        $wpdb->query("DELETE FROM {$table_chat} WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)");
        
        // Удаление старых логов парсера (старше 60 дней)
        $table_parser = $wpdb->prefix . 'akpp_parser_items';
        $wpdb->query("DELETE FROM {$table_parser} WHERE status = 'rejected' AND created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
        
        // Удаление неактивных диалогов Авито (старше 30 дней)
        $table_dialogs = $wpdb->prefix . 'akpp_avito_dialogs';
        $wpdb->query("DELETE FROM {$table_dialogs} WHERE is_active = 0 AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        $this->log_event('Очистка старых данных завершена');
    }
    
    /**
     * Обновление курсов валют (для расчета цен в рублях)
     */
    public function update_exchange_rates() {
        $this->log_event('Обновление курсов валют');
        
        $url = 'https://www.cbr-xml-daily.ru/daily_json.js';
        
        $response = wp_remote_get($url, ['timeout' => 30]);
        
        if (is_wp_error($response)) {
            $this->log_error('Ошибка получения курсов валют: ' . $response->get_error_message());
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['Valute'])) {
            update_option('akpp_exchange_rates', [
                'USD' => $data['Valute']['USD']['Value'] ?? 0,
                'EUR' => $data['Valute']['EUR']['Value'] ?? 0,
                'CNY' => $data['Valute']['CNY']['Value'] ?? 0,
                'updated_at' => current_time('mysql')
            ]);
            
            $this->log_event('Курсы валют обновлены');
        }
    }
    
    /**
     * Пакетный AI анализ (для фоновой обработки)
     */
    public function ai_bulk_analysis() {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parser_items';
        
        $items = $wpdb->get_results(
            "SELECT * FROM {$table} 
            WHERE status = 'pending' 
            ORDER BY created_at ASC 
            LIMIT 5"
        );
        
        if (empty($items)) {
            return;
        }
        
        if (!class_exists('AKPP_AI_Analyzer')) {
            require_once AKPP_CRM_PATH . 'ai/class-ai-analyzer.php';
        }
        
        $analyzer = AKPP_AI_Analyzer::get_instance();
        $processed = 0;
        
        foreach ($items as $item) {
            $result = $analyzer->analyze($item->content, $item->content_type);
            
            $wpdb->update(
                $table,
                [
                    'ai_analysis' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    'status' => 'ai_processed',
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $item->id],
                ['%s', '%s', '%s'],
                ['%d']
            );
            
            $processed++;
            
            // Задержка между запросами к AI
            usleep(1000000);
        }
        
        $this->log_event("AI анализ: обработано {$processed} элементов");
    }
    
    /**
     * Отмена всех запланированных событий (при деактивации)
     */
    public function clear_scheduled_events() {
        wp_clear_scheduled_hook('akpp_sync_avito_dialogs');
        wp_clear_scheduled_hook('akpp_sync_avito_messages');
        wp_clear_scheduled_hook('akpp_cleanup_old_data');
        wp_clear_scheduled_hook('akpp_update_exchange_rates');
        wp_clear_scheduled_hook('akpp_ai_bulk_analysis');
        
        $this->log_event('Все cron задачи отменены');
    }
    
    private function log_error($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_CRON] ОШИБКА: ' . $message);
        }
    }
    
    private function log_event($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_CRON] СОБЫТИЕ: ' . $message);
        }
    }
}
