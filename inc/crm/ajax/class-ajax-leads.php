<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX модуль: Лиды
 * Создание, редактирование, конвертация в сделку
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
    // СОХРАНЕНИЕ ЛИДА (вручную из админки)
    // ========================================================================
    
    public function ajax_save_lead() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        
        $client_name = sanitize_text_field($_POST['client_name'] ?? $_POST['name'] ?? '');
        $client_phone = sanitize_text_field($_POST['client_phone'] ?? $_POST['phone'] ?? '');
        $client_email = sanitize_email($_POST['client_email'] ?? $_POST['email'] ?? '');
        $problem = sanitize_textarea_field($_POST['problem'] ?? $_POST['message'] ?? '');
        $source = sanitize_text_field($_POST['source'] ?? 'call');
        $guide_id = intval($_POST['guide_id'] ?? $_POST['assigned_to'] ?? 0);
        $car_brand = sanitize_text_field($_POST['car_brand'] ?? '');
        
        if (empty($client_phone)) {
            wp_send_json_error(['message' => 'Телефон обязателен']);
            return;
        }
        
        // Проверяем дубли по телефону
        $duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_leads 
             WHERE client_phone = %s 
             AND status != 'converted'
             AND DATE(created_at) = CURDATE()
             LIMIT 1",
            $client_phone
        ));
        
        if ($duplicate) {
            wp_send_json_error(['message' => 'Лид с таким телефоном уже существует сегодня (ID: ' . $duplicate . ')']);
            return;
        }
        
        // Нормализуем телефон (убираем всё кроме цифр и +)
$phone_clean = preg_replace('/[^\d+]/', '', $client_phone);

// Ищем клиента по очищенному телефону
$client_id = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}akpp_site_users 
     WHERE REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', '') = %s 
     OR phone = %s 
     LIMIT 1",
    $phone_clean,
    $client_phone
));

// Если клиент не найден — создаём
if (!$client_id && !empty($client_name)) {
    $wpdb->insert($wpdb->prefix . 'akpp_site_users', [
        'full_name' => $client_name,
        'phone' => $client_phone,
        'email' => !empty($client_email) ? $client_email : null,
        'registered_at' => current_time('mysql')
    ]);
    $client_id = $wpdb->insert_id;
}
        
        // Сохраняем лид
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
        
        // Уведомление в Telegram
        $this->send_lead_notification($lead_id, $client_name, $client_phone, $source);
        
        wp_send_json_success([
            'message' => 'Лид создан',
            'id' => $lead_id
        ]);
    }
    
    // ========================================================================
    // КОНВЕРТАЦИЯ ЛИДА В СДЕЛКУ
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
        
        // Получаем лид
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
        
        // Создаём сделку
        $wpdb->insert($wpdb->prefix . 'akpp_deals', [
            'client_id' => $lead['client_id'],
            'status' => 'new',
            'problem_description' => $lead['problem'],
            'employee_id' => $lead['guide_id'],
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
        
        $deal_id = $wpdb->insert_id;
        
        // Обновляем лид
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
    // ОБНОВЛЕНИЕ СТАТУСА ЛИДА
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
    // УВЕДОМЛЕНИЕ В TELEGRAM
    // ========================================================================
    
    private function send_lead_notification($lead_id, $client_name, $client_phone, $source) {
        if (!class_exists('AKPP_Telegram')) return;
        
        $sources = [
            'call' => '📞 Звонок',
            'site' => '🌐 Сайт',
            'avito' => '🟢 Авито',
            'telegram' => '🔵 Telegram'
        ];
        
        $source_text = $sources[$source] ?? $source;
        
        $message = "📨 <b>Новый лид #{$lead_id}</b>\n\n";
        $message .= " <b>Клиент:</b> {$client_name}\n";
        $message .= "📱 <b>Телефон:</b> {$client_phone}\n";
        $message .= "📍 <b>Источник:</b> {$source_text}\n";
        $message .= "🔗 <a href='" . admin_url("admin.php?page=akpp-crm-leads") . "'>Открыть в CRM</a>";
        
        // Проверяем как вызывается метод
if (class_exists('AKPP_Telegram')) {
    $telegram = AKPP_Telegram::get_instance();
    if (method_exists($telegram, 'send_message')) {
        $telegram->send_message($message);
    }
}
    }
}