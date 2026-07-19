<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX модуль: Лиды
 */
class AKPP_AJAX_Leads extends AKPP_AJAX_Base {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->register_hooks();
    }
    
    private function register_hooks() {
        add_action('wp_ajax_akpp_save_lead', [$this, 'ajax_save_lead']);
        add_action('wp_ajax_akpp_update_lead_status', [$this, 'ajax_update_lead_status']);
        add_action('wp_ajax_akpp_convert_lead', [$this, 'ajax_convert_lead']);
        add_action('wp_ajax_akpp_assign_lead', [$this, 'ajax_assign_lead']);
        add_action('wp_ajax_akpp_delete_lead', [$this, 'ajax_delete_lead']);
        add_action('wp_ajax_akpp_get_lead', [$this, 'ajax_get_lead']);
    }
    
    // ========================================================================
    // НОРМАЛИЗАЦИЯ ТЕЛЕФОНА → +7XXXXXXXXXX
    // ========================================================================
    
    private function normalize_phone($phone) {
        $digits = preg_replace('/[^\d]/', '', $phone);
        
        // 8XXXXXXXXXX → 7XXXXXXXXXX
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        }
        
        // XXXXXXXXXX → 7XXXXXXXXXX
        if (strlen($digits) === 10) {
            $digits = '7' . $digits;
        }
        
        return '+' . $digits;
    }
    
    // ========================================================================
    // СОХРАНЕНИЕ ЛИДА
    // ========================================================================
    
    public function ajax_save_lead() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        
        $client_name = sanitize_text_field($_POST['client_name'] ?? '');
        $client_phone_raw = sanitize_text_field($_POST['client_phone'] ?? '');
        $client_email = sanitize_email($_POST['client_email'] ?? '');
        $problem = sanitize_textarea_field($_POST['problem'] ?? '');
        $source = sanitize_text_field($_POST['source'] ?? 'call');
        $guide_id = intval($_POST['guide_id'] ?? 0);
        $car_brand = sanitize_text_field($_POST['car_brand'] ?? '');
        
        if (empty($client_phone_raw)) {
            wp_send_json_error(['message' => 'Телефон обязателен']);
            return;
        }
        
        // Нормализуем телефон
        $client_phone = $this->normalize_phone($client_phone_raw);
        $phone_digits = preg_replace('/[^\d]/', '', $client_phone);
        
        if (strlen($phone_digits) < 11) {
            wp_send_json_error(['message' => 'Телефон слишком короткий']);
            return;
        }
        
        // ====================================================================
        // ПРОВЕРКА ДУБЛЕЙ ПО НОРМАЛИЗОВАННОМУ ТЕЛЕФОНУ
        // ====================================================================
        $duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_leads 
             WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(client_phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = %s
             AND status NOT IN ('converted', 'cancelled', 'rejected')
             LIMIT 1",
            $phone_digits
        ));
        
        if ($duplicate) {
            wp_send_json_error([
                'message' => '⚠️ Лид с таким телефоном уже существует (ID: ' . $duplicate . ')',
                'duplicate_id' => $duplicate
            ]);
            return;
        }
        
        // ====================================================================
        // СОЗДАЁМ ИЛИ НАХОДИМ КЛИЕНТА
        // ====================================================================
        $client_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_site_users 
             WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = %s 
             LIMIT 1",
            $phone_digits
        ));
        
        if (!$client_id && !empty($client_name)) {
            try {
                $wpdb->insert($wpdb->prefix . 'akpp_site_users', [
                    'full_name' => $client_name,
                    'phone' => $client_phone,
                    'email' => !empty($client_email) ? $client_email : null,
                    'registered_at' => current_time('mysql')
                ]);
                $client_id = $wpdb->insert_id;
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    $client_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}akpp_site_users 
                         WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = %s 
                         LIMIT 1",
                        $phone_digits
                    ));
                } else {
                    error_log('[AKPP Leads] Client error: ' . $e->getMessage());
                }
            }
        }
        
        // ====================================================================
        // СОХРАНЯЕМ ЛИД
        // ====================================================================
        $wpdb->insert($wpdb->prefix . 'akpp_leads', [
            'client_id' => $client_id ?: null,
            'client_name' => $client_name,
            'client_phone' => $client_phone,
            'client_email' => !empty($client_email) ? $client_email : null,
            'car_brand' => !empty($car_brand) ? $car_brand : null,
            'problem' => !empty($problem) ? $problem : null,
            'guide_id' => $guide_id > 0 ? $guide_id : null,
            'status' => 'new',
            'source' => $source,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
        
        $lead_id = $wpdb->insert_id;
        
        // ====================================================================
        // TELEGRAM УВЕДОМЛЕНИЕ (с try/catch)
        // ====================================================================
        try {
            $this->send_lead_notification($lead_id, $client_name, $client_phone, $source);
        } catch (Exception $e) {
            error_log('[AKPP Leads] Telegram error: ' . $e->getMessage());
        }
        
        wp_send_json_success([
            'message' => '✅ Лид #' . $lead_id . ' создан',
            'id' => $lead_id
        ]);
    }
    
    // ========================================================================
    // КОНВЕРТАЦИЯ В СДЕЛКУ
    // ========================================================================
    
    public function ajax_convert_lead() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $lead_id = intval($_POST['lead_id'] ?? 0);
        
        if ($lead_id <= 0) {
            wp_send_json_error(['message' => 'ID лида не указан']);
            return;
        }
        
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}akpp_leads WHERE id = %d",
            $lead_id
        ), ARRAY_A);
        
        if (!$lead) {
            wp_send_json_error(['message' => 'Лид не найден']);
            return;
        }
        
        if (!empty($lead['deal_id']) && $lead['deal_id'] > 0) {
            wp_send_json_error(['message' => 'Лид уже конвертирован в сделку #' . $lead['deal_id']]);
            return;
        }
        
        $wpdb->insert($wpdb->prefix . 'akpp_deals', [
            'client_id' => $lead['client_id'],
            'status' => 'new',
            'problem_description' => $lead['problem'],
            'employee_id' => $lead['guide_id'],
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
        
        $deal_id = $wpdb->insert_id;
        
        $wpdb->update($wpdb->prefix . 'akpp_leads', [
            'deal_id' => $deal_id,
            'status' => 'converted',
            'updated_at' => current_time('mysql')
        ], ['id' => $lead_id]);
        
        wp_send_json_success([
            'message' => 'Лид конвертирован в сделку #' . $deal_id,
            'deal_id' => $deal_id
        ]);
    }
    
    // ========================================================================
    // ОБНОВЛЕНИЕ СТАТУСА
    // ========================================================================
    
    public function ajax_update_lead_status() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $lead_id = intval($_POST['lead_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        $valid_statuses = ['new', 'contacted', 'qualified', 'converted', 'rejected'];
        if (!in_array($status, $valid_statuses)) {
            wp_send_json_error(['message' => 'Недопустимый статус']);
            return;
        }
        
        $wpdb->update($wpdb->prefix . 'akpp_leads', [
            'status' => $status,
            'updated_at' => current_time('mysql')
        ], ['id' => $lead_id]);
        
        wp_send_json_success(['message' => 'Статус обновлён']);
    }
    
    // ========================================================================
    // НАЗНАЧЕНИЕ СОТРУДНИКА
    // ========================================================================
    
    public function ajax_assign_lead() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $lead_id = intval($_POST['lead_id'] ?? 0);
        $guide_id = intval($_POST['guide_id'] ?? 0);
        
        $wpdb->update($wpdb->prefix . 'akpp_leads', [
            'guide_id' => $guide_id > 0 ? $guide_id : null,
            'updated_at' => current_time('mysql')
        ], ['id' => $lead_id]);
        
        wp_send_json_success(['message' => 'Сотрудник назначен']);
    }
    
    // ========================================================================
    // УДАЛЕНИЕ ЛИДА
    // ========================================================================
    
    public function ajax_delete_lead() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $lead_id = intval($_POST['lead_id'] ?? 0);
        
        $wpdb->delete($wpdb->prefix . 'akpp_leads', ['id' => $lead_id]);
        
        wp_send_json_success(['message' => 'Лид удалён']);
    }
    
    // ========================================================================
    // ПОЛУЧЕНИЕ ЛИДА
    // ========================================================================
    
    public function ajax_get_lead() {
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $lead_id = intval($_POST['lead_id'] ?? 0);
        
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT l.*, e.name as employee_name
             FROM {$wpdb->prefix}akpp_leads l
             LEFT JOIN {$wpdb->prefix}akpp_employees e ON l.guide_id = e.id
             WHERE l.id = %d",
            $lead_id
        ), ARRAY_A);
        
        if (!$lead) {
            wp_send_json_error(['message' => 'Лид не найден']);
            return;
        }
        
        wp_send_json_success(['lead' => $lead]);
    }
    
    // ========================================================================
    // TELEGRAM УВЕДОМЛЕНИЕ (ПРАВИЛЬНЫЙ ВЫЗОВ)
    // ========================================================================
    
    private function send_lead_notification($lead_id, $client_name, $client_phone, $source = 'site') {
        if (!class_exists('AKPP_Telegram')) return;
        
        $chat_id = get_option('akpp_telegram_chat_id', '');
        if (empty($chat_id)) return;
        
        $sources = [
            'call'     => '📞 Звонок',
            'site'     => '🌐 Сайт',
            'avito'    => '🟢 Авито',
            'telegram' => '🔵 Telegram',
            'whatsapp' => '💬 WhatsApp'
        ];
        
        $source_text = $sources[$source] ?? $source;
        
        $message  = "📨 <b>Новый лид #{$lead_id}</b>\n\n";
        $message .= "👤 <b>Клиент:</b> {$client_name}\n";
        $message .= "📱 <b>Телефон:</b> {$client_phone}\n";
        $message .= "📍 <b>Источник:</b> {$source_text}\n";
        $message .= "🔗 <a href='" . admin_url("admin.php?page=akpp-crm-leads") . "'>Открыть в CRM</a>";
        
        // ПРАВИЛЬНЫЙ вызов через экземпляр
        $telegram = AKPP_Telegram::get_instance();
        if (method_exists($telegram, 'send_message')) {
            $result = $telegram->send_message($chat_id, $message, 'HTML');
            
            if (is_wp_error($result)) {
                error_log('[AKPP Leads] Telegram error: ' . $result->get_error_message());
            }
        }
    }
  /**
 * Получить сообщения по лиду
 */
public function ajax_get_lead_messages() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Необходимо войти']);
        return;
    }
    
    check_ajax_referer('akpp_lead_chat_nonce', 'nonce');
    
    global $wpdb;
    $lead_id = intval($_POST['lead_id'] ?? 0);
    $user_id = get_current_user_id();
    $table = $wpdb->prefix . 'akpp_lead_messages';
    
    // Проверяем что лид принадлежит пользователю
    $lead = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}akpp_leads WHERE id = %d AND (client_id = %d OR client_email = (SELECT user_email FROM {$wpdb->users} WHERE ID = %d))",
        $lead_id,
        $user_id,
        $user_id
    ));
    
    if (!$lead) {
        wp_send_json_error(['message' => 'Лид не найден']);
        return;
    }
    
    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE lead_id = %d ORDER BY created_at ASC LIMIT 200",
        $lead_id
    ));
    
    $formatted = [];
    foreach ($messages as $msg) {
        $formatted[] = [
            'id' => $msg->id,
            'message' => esc_html($msg->message),
            'sender_type' => $msg->sender_type,
            'created_at' => date_i18n('d.m.Y H:i', strtotime($msg->created_at))
        ];
    }
    
    // Помечаем сообщения менеджера как прочитанные
    $wpdb->update($table, 
        ['is_read' => 1], 
        ['lead_id' => $lead_id, 'sender_type' => 'manager', 'is_read' => 0],
        ['%d'],
        ['%d', '%s', '%d']
    );
    
    wp_send_json_success(['messages' => $formatted]);
}

/**
 * Отправить сообщение по лиду
 */
public function ajax_send_lead_message() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Необходимо войти']);
        return;
    }
    
    check_ajax_referer('akpp_lead_chat_nonce', 'nonce');
    
    global $wpdb;
    $lead_id = intval($_POST['lead_id'] ?? 0);
    $user_id = get_current_user_id();
    $message = sanitize_textarea_field($_POST['message'] ?? '');
    
    if (empty($message)) {
        wp_send_json_error(['message' => 'Сообщение пустое']);
        return;
    }
    
    // Проверяем что лид принадлежит пользователю
    $lead = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}akpp_leads WHERE id = %d AND (client_id = %d OR client_email = (SELECT user_email FROM {$wpdb->users} WHERE ID = %d))",
        $lead_id,
        $user_id,
        $user_id
    ));
    
    if (!$lead) {
        wp_send_json_error(['message' => 'Лид не найден']);
        return;
    }
    
    $table = $wpdb->prefix . 'akpp_lead_messages';
    
    $wpdb->insert($table, [
        'lead_id' => $lead_id,
        'user_id' => $user_id,
        'sender_type' => 'client',
        'message' => $message,
        'is_read' => 0,
        'created_at' => current_time('mysql')
    ]);
    
    wp_send_json_success(['message' => 'Сообщение отправлено']);
}
}