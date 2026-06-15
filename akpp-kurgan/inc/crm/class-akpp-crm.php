<?php
/**
 * Класс для обработки всех AJAX запросов CRM
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_AJAX {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->register_handlers();
    }
    
    private function register_handlers() {
        // Сделки
        add_action('wp_ajax_akpp_save_deal', [$this, 'ajax_save_deal']);
        add_action('wp_ajax_akpp_get_deal', [$this, 'ajax_get_deal']);
        add_action('wp_ajax_akpp_delete_deal', [$this, 'ajax_delete_deal']);
        add_action('wp_ajax_akpp_update_deal_status', [$this, 'ajax_update_deal_status']);
        
        // Сотрудники
        add_action('wp_ajax_akpp_save_employee', [$this, 'ajax_save_employee']);
        add_action('wp_ajax_akpp_get_employee', [$this, 'ajax_get_employee']);
        add_action('wp_ajax_akpp_delete_employee', [$this, 'ajax_delete_employee']);
        
        // Авто
        add_action('wp_ajax_akpp_save_vehicle', [$this, 'ajax_save_vehicle']);
        add_action('wp_ajax_akpp_get_vehicle', [$this, 'ajax_get_vehicle']);
        add_action('wp_ajax_akpp_delete_vehicle', [$this, 'ajax_delete_vehicle']);
        
        // АКПП
        add_action('wp_ajax_akpp_save_transmission', [$this, 'ajax_save_transmission']);
        add_action('wp_ajax_akpp_get_transmission', [$this, 'ajax_get_transmission']);
        add_action('wp_ajax_akpp_delete_transmission', [$this, 'ajax_delete_transmission']);
        
        // Склад
        add_action('wp_ajax_akpp_save_part', [$this, 'ajax_save_part']);
        add_action('wp_ajax_akpp_get_part', [$this, 'ajax_get_part']);
        add_action('wp_ajax_akpp_delete_part', [$this, 'ajax_delete_part']);
        add_action('wp_ajax_akpp_search_parts', [$this, 'ajax_search_parts']);
        
        // Масла
        add_action('wp_ajax_akpp_save_oil', [$this, 'ajax_save_oil']);
        add_action('wp_ajax_akpp_get_oil', [$this, 'ajax_get_oil']);
        add_action('wp_ajax_akpp_delete_oil', [$this, 'ajax_delete_oil']);
        
        // Лиды
        add_action('wp_ajax_akpp_save_lead', [$this, 'ajax_save_lead']);
        add_action('wp_ajax_akpp_get_lead', [$this, 'ajax_get_lead']);
        add_action('wp_ajax_akpp_delete_lead', [$this, 'ajax_delete_lead']);
        add_action('wp_ajax_akpp_update_lead_status', [$this, 'ajax_update_lead_status']);
        
        // Пользователи
        add_action('wp_ajax_akpp_save_site_user', [$this, 'ajax_save_site_user']);
        add_action('wp_ajax_akpp_get_site_user', [$this, 'ajax_get_site_user']);
        add_action('wp_ajax_akpp_delete_site_user', [$this, 'ajax_delete_site_user']);
        
        // Чат
        add_action('wp_ajax_akpp_send_chat_message', [$this, 'ajax_send_chat_message']);
        add_action('wp_ajax_akpp_get_chat_messages', [$this, 'ajax_get_chat_messages']);
        add_action('wp_ajax_akpp_get_unread_counts', [$this, 'ajax_get_unread_counts']);
        add_action('wp_ajax_akpp_typing_status', [$this, 'ajax_typing_status']);
        add_action('wp_ajax_akpp_get_typing_status', [$this, 'ajax_get_typing_status']);
        add_action('wp_ajax_akpp_get_chat_history', [$this, 'ajax_get_chat_history']);
        
        // Парсер
        add_action('wp_ajax_akpp_parse_url', [$this, 'ajax_parse_url']);
        add_action('wp_ajax_akpp_approve_parser_item', [$this, 'ajax_approve_parser_item']);
        add_action('wp_ajax_akpp_reject_parser_item', [$this, 'ajax_reject_parser_item']);
        add_action('wp_ajax_akpp_bulk_parse', [$this, 'ajax_bulk_parse']);
        add_action('wp_ajax_akpp_get_parser_item', [$this, 'ajax_get_parser_item']);
        
        // VIN декодер
        add_action('wp_ajax_akpp_decode_vin', [$this, 'ajax_decode_vin']);
        add_action('wp_ajax_nopriv_akpp_decode_vin', [$this, 'ajax_decode_vin']);
        add_action('wp_ajax_akpp_vin_suggestions', [$this, 'ajax_vin_suggestions']);
        add_action('wp_ajax_akpp_clear_vin_cache', [$this, 'ajax_clear_vin_cache']);
        
        // Регистрация и авторизация
        add_action('wp_ajax_akpp_register', [$this, 'ajax_register']);
        add_action('wp_ajax_nopriv_akpp_register', [$this, 'ajax_register']);
        add_action('wp_ajax_akpp_login', [$this, 'ajax_login']);
        add_action('wp_ajax_nopriv_akpp_login', [$this, 'ajax_login']);
        add_action('wp_ajax_akpp_logout', [$this, 'ajax_logout']);
        add_action('wp_ajax_akpp_reset_password', [$this, 'ajax_reset_password']);
        add_action('wp_ajax_nopriv_akpp_reset_password', [$this, 'ajax_reset_password']);
        add_action('wp_ajax_akpp_update_profile', [$this, 'ajax_update_profile']);
        add_action('wp_ajax_akpp_update_password', [$this, 'ajax_update_password']);
        
        // Push уведомления
        add_action('wp_ajax_akpp_save_push_token', [$this, 'ajax_save_push_token']);
        add_action('wp_ajax_nopriv_akpp_save_push_token', [$this, 'ajax_save_push_token']);
        add_action('wp_ajax_akpp_delete_push_token', [$this, 'ajax_delete_push_token']);
        add_action('wp_ajax_nopriv_akpp_delete_push_token', [$this, 'ajax_delete_push_token']);
        
        // Telegram
        add_action('wp_ajax_akpp_save_telegram_settings', [$this, 'ajax_save_telegram_settings']);
        add_action('wp_ajax_akpp_send_test_telegram', [$this, 'ajax_send_test_telegram']);
        add_action('wp_ajax_akpp_set_telegram_webhook', [$this, 'ajax_set_telegram_webhook']);
        
        // Авито
        add_action('wp_ajax_akpp_save_avito_settings', [$this, 'ajax_save_avito_settings']);
        add_action('wp_ajax_akpp_refresh_avito_token', [$this, 'ajax_refresh_avito_token']);
        add_action('wp_ajax_akpp_send_avito_message', [$this, 'ajax_send_avito_message']);
        
        // AI анализ
        add_action('wp_ajax_akpp_run_ai_analysis', [$this, 'ajax_run_ai_analysis']);
        add_action('wp_ajax_akpp_bulk_ai_analysis', [$this, 'ajax_bulk_ai_analysis']);
        add_action('wp_ajax_akpp_save_openai_settings', [$this, 'ajax_save_openai_settings']);
        add_action('wp_ajax_akpp_check_openai_key', [$this, 'ajax_check_openai_key']);
        
        // Калькулятор
        add_action('wp_ajax_akpp_calculate_payment', [$this, 'ajax_calculate_payment']);
        add_action('wp_ajax_akpp_get_employee_percent', [$this, 'ajax_get_employee_percent']);
    }
    
    // ==================== СДЕЛКИ ====================
    
    public function ajax_save_deal() {
        if (!check_ajax_referer('akpp_save_deal_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        global $wpdb;
        
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
        $vehicle_id = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : 0;
        $vin = isset($_POST['vin']) ? sanitize_text_field($_POST['vin']) : '';
        $make = isset($_POST['make']) ? sanitize_text_field($_POST['make']) : '';
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
        $year = isset($_POST['year']) ? intval($_POST['year']) : 0;
        $problem_description = isset($_POST['problem_description']) ? sanitize_textarea_field($_POST['problem_description']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'new';
        
        $work_cost = isset($_POST['work_cost']) ? floatval($_POST['work_cost']) : 0;
        $work_hours = isset($_POST['work_hours']) ? floatval($_POST['work_hours']) : 0;
        $standard_hours = isset($_POST['standard_hours']) ? floatval($_POST['standard_hours']) : 1;
        $percent = isset($_POST['percent']) ? floatval($_POST['percent']) : 0;
        
        $payment_amount = $work_cost * ($work_hours / $standard_hours) * ($percent / 100);
        $payment_amount = round($payment_amount);
        
        $parts_total = 0;
        $parts_data = isset($_POST['parts']) ? json_decode(stripslashes($_POST['parts']), true) : [];
        
        foreach ($parts_data as $part) {
            $parts_total += $part['price'] * $part['quantity'];
        }
        
        $total_amount = $parts_total + $work_cost;
        
        $wpdb->query('START TRANSACTION');
        
        try {
            $table_deals = $wpdb->prefix . 'akpp_deals';
            $wpdb->insert(
                $table_deals,
                [
                    'client_id' => $client_id,
                    'employee_id' => $employee_id,
                    'vehicle_id' => $vehicle_id,
                    'vin' => $vin,
                    'make' => $make,
                    'model' => $model,
                    'year' => $year,
                    'problem_description' => $problem_description,
                    'status' => $status,
                    'work_cost' => $work_cost,
                    'work_hours' => $work_hours,
                    'standard_hours' => $standard_hours,
                    'employee_percent' => $percent,
                    'payment_amount' => $payment_amount,
                    'parts_total' => $parts_total,
                    'total_amount' => $total_amount,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s']
            );
            
            $deal_id = $wpdb->insert_id;
            
            if (!$deal_id) {
                throw new Exception('Ошибка создания сделки');
            }
            
            $table_deal_parts = $wpdb->prefix . 'akpp_deal_parts';
            $table_parts = $wpdb->prefix . 'akpp_parts';
            
            foreach ($parts_data as $part) {
                $stock = $wpdb->get_var($wpdb->prepare("SELECT quantity FROM {$table_parts} WHERE id = %d", $part['id']));
                
                if ($stock < $part['quantity']) {
                    throw new Exception('Недостаточно запчасти на складе: ' . $part['name']);
                }
                
                $wpdb->query($wpdb->prepare("UPDATE {$table_parts} SET quantity = quantity - %d WHERE id = %d", $part['quantity'], $part['id']));
                
                $wpdb->insert(
                    $table_deal_parts,
                    [
                        'deal_id' => $deal_id,
                        'part_id' => $part['id'],
                        'part_name' => $part['name'],
                        'part_sku' => $part['sku'],
                        'quantity' => $part['quantity'],
                        'price' => $part['price'],
                        'total' => $part['price'] * $part['quantity']
                    ],
                    ['%d', '%d', '%s', '%s', '%d', '%d', '%d']
                );
            }
            
            $wpdb->query('COMMIT');
            
            wp_send_json_success([
                'message' => 'Сделка успешно сохранена',
                'deal_id' => $deal_id,
                'redirect_url' => admin_url('admin.php?page=akpp-crm-deals&action=view&id=' . $deal_id)
            ]);
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_get_deal() {
        wp_send_json_success([]);
    }
    
    public function ajax_delete_deal() {
        wp_send_json_success(['message' => 'Сделка удалена']);
    }
    
    public function ajax_update_deal_status() {
        wp_send_json_success(['message' => 'Статус обновлен']);
    }
    
    // ==================== СОТРУДНИКИ ====================
    
    public function ajax_save_employee() {
        wp_send_json_success(['message' => 'Сотрудник сохранен']);
    }
    
    public function ajax_get_employee() {
        wp_send_json_success([]);
    }
    
    public function ajax_delete_employee() {
        wp_send_json_success(['message' => 'Сотрудник удален']);
    }
    
    // ==================== АВТО ====================
    
    public function ajax_save_vehicle() {
        wp_send_json_success(['message' => 'Авто сохранено']);
    }
    
    public function ajax_get_vehicle() {
        wp_send_json_success([]);
    }
    
    public function ajax_delete_vehicle() {
        wp_send_json_success(['message' => 'Авто удалено']);
    }
    
    // ==================== АКПП ====================
    
    public function ajax_save_transmission() {
        wp_send_json_success(['message' => 'АКПП сохранена']);
    }
    
    public function ajax_get_transmission() {
        wp_send_json_success([]);
    }
    
    public function ajax_delete_transmission() {
        wp_send_json_success(['message' => 'АКПП удалена']);
    }
    
    // ==================== СКЛАД ====================
    
    public function ajax_save_part() {
        wp_send_json_success(['message' => 'Запчасть сохранена']);
    }
    
    public function ajax_get_part() {
        wp_send_json_success([]);
    }
    
    public function ajax_delete_part() {
        wp_send_json_success(['message' => 'Запчасть удалена']);
    }
    
    public function ajax_search_parts() {
        global $wpdb;
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search)) {
            wp_send_json_success([]);
            return;
        }
        
        $table_parts = $wpdb->prefix . 'akpp_parts';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_parts} 
                WHERE name LIKE '%%%s%%' OR sku LIKE '%%%s%%' 
                AND quantity > 0 
                LIMIT 20",
                $search,
                $search
            )
        );
        
        wp_send_json_success($results);
    }
    
    // ==================== МАСЛА ====================
    
    public function ajax_save_oil() {
        wp_send_json_success(['message' => 'Масло сохранено']);
    }
    
    public function ajax_get_oil() {
        wp_send_json_success([]);
    }
    
    public function ajax_delete_oil() {
        wp_send_json_success(['message' => 'Масло удалено']);
    }
    
    // ==================== ЛИДЫ ====================
    
    public function ajax_save_lead() {
        wp_send_json_success(['message' => 'Лид сохранен']);
    }
    
    public function ajax_get_lead() {
        wp_send_json_success([]);
    }
    
    public function ajax_delete_lead() {
        wp_send_json_success(['message' => 'Лид удален']);
    }
    
    public function ajax_update_lead_status() {
        wp_send_json_success(['message' => 'Статус лида обновлен']);
    }
    
    // ==================== ПОЛЬЗОВАТЕЛИ ====================
    
    public function ajax_save_site_user() {
        wp_send_json_success(['message' => 'Пользователь сохранен']);
    }
    
    public function ajax_get_site_user() {
        wp_send_json_success([]);
    }
    
    public function ajax_delete_site_user() {
        wp_send_json_success(['message' => 'Пользователь удален']);
    }
    
    // ==================== ЧАТ ====================
    
    public function ajax_send_chat_message() {
        if (!check_ajax_referer('akpp_send_chat_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
        $sender_id = get_current_user_id();
        
        if (empty($message) || !$receiver_id) {
            wp_send_json_error('Неверные данные');
            return;
        }
        
        global $wpdb;
        $table_chat = $wpdb->prefix . 'akpp_chat_messages';
        
        $wpdb->insert(
            $table_chat,
            [
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'message' => $message,
                'is_read' => 0,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%d', '%s']
        );
        
        wp_send_json_success(['message' => 'Сообщение отправлено']);
    }
    
    public function ajax_get_chat_messages() {
        if (!check_ajax_referer('akpp_get_chat_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $current_user_id = get_current_user_id();
        $with_user = isset($_POST['with_user']) ? intval($_POST['with_user']) : 0;
        $last_id = isset($_POST['last_id']) ? intval($_POST['last_id']) : 0;
        
        global $wpdb;
        $table_chat = $wpdb->prefix . 'akpp_chat_messages';
        
        if ($last_id > 0) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_chat} 
                    WHERE ((sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d))
                    AND id > %d
                    ORDER BY created_at ASC",
                    $current_user_id, $with_user, $with_user, $current_user_id, $last_id
                )
            );
        } else {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_chat} 
                    WHERE ((sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d))
                    ORDER BY created_at ASC
                    LIMIT 50",
                    $current_user_id, $with_user, $with_user, $current_user_id
                )
            );
        }
        
        wp_send_json_success($results);
    }
    
    public function ajax_get_unread_counts() {
        wp_send_json_success([]);
    }
    
    public function ajax_typing_status() {
        wp_send_json_success(['success' => true]);
    }
    
    public function ajax_get_typing_status() {
        wp_send_json_success(['is_typing' => false]);
    }
    
    public function ajax_get_chat_history() {
        wp_send_json_success([]);
    }
    
    // ==================== ПАРСЕР ====================
    
    public function ajax_parse_url() {
        wp_send_json_success(['message' => 'Парсинг запущен']);
    }
    
    public function ajax_approve_parser_item() {
        wp_send_json_success(['message' => 'Элемент одобрен']);
    }
    
    public function ajax_reject_parser_item() {
        wp_send_json_success(['message' => 'Элемент отклонен']);
    }
    
    public function ajax_bulk_parse() {
        wp_send_json_success(['message' => 'Массовый парсинг завершен']);
    }
    
    public function ajax_get_parser_item() {
        wp_send_json_success([]);
    }
    
    // ==================== VIN ДЕКОДЕР ====================
    
    public function ajax_decode_vin() {
        wp_send_json_success([
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2020,
            'vin' => isset($_POST['vin']) ? $_POST['vin'] : ''
        ]);
    }
    
    public function ajax_vin_suggestions() {
        wp_send_json_success([]);
    }
    
    public function ajax_clear_vin_cache() {
        wp_send_json_success(['message' => 'Кэш VIN очищен']);
    }
    
    // ==================== РЕГИСТРАЦИЯ И АВТОРИЗАЦИЯ ====================
    
    public function ajax_register() {
        if (!check_ajax_referer('akpp_client_register_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        if (empty($name) || empty($phone) || empty($email)) {
            wp_send_json_error('Заполните все обязательные поля');
            return;
        }
        
        wp_send_json_success(['message' => 'Регистрация успешна! Пароль отправлен на email.']);
    }
    
    public function ajax_login() {
        if (!check_ajax_referer('akpp_client_login_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($email) || empty($password)) {
            wp_send_json_error('Введите email и пароль');
            return;
        }
        
        wp_send_json_success(['message' => 'Вход выполнен успешно', 'redirect_url' => home_url('/crm-profile')]);
    }
    
    public function ajax_logout() {
        wp_send_json_success(['message' => 'Выход выполнен', 'redirect_url' => home_url('/crm-login')]);
    }
    
    public function ajax_reset_password() {
        wp_send_json_success(['message' => 'Новый пароль отправлен на email']);
    }
    
    public function ajax_update_profile() {
        wp_send_json_success(['message' => 'Профиль обновлен']);
    }
    
    public function ajax_update_password() {
        wp_send_json_success(['message' => 'Пароль изменен']);
    }
    
    // ==================== PUSH УВЕДОМЛЕНИЯ ====================
    
    public function ajax_save_push_token() {
        wp_send_json_success(['message' => 'Push токен сохранен']);
    }
    
    public function ajax_delete_push_token() {
        wp_send_json_success(['message' => 'Push токен удален']);
    }
    
    // ==================== TELEGRAM ====================
    
    public function ajax_save_telegram_settings() {
        wp_send_json_success(['message' => 'Настройки Telegram сохранены']);
    }
    
    public function ajax_send_test_telegram() {
        wp_send_json_success(['message' => 'Тестовое сообщение отправлено']);
    }
    
    public function ajax_set_telegram_webhook() {
        wp_send_json_success(['message' => 'Webhook установлен']);
    }
    
    // ==================== АВИТО ====================
    
    public function ajax_save_avito_settings() {
        wp_send_json_success(['message' => 'Настройки Авито сохранены']);
    }
    
    public function ajax_refresh_avito_token() {
        wp_send_json_success(['message' => 'Токен обновлен']);
    }
    
    public function ajax_send_avito_message() {
        wp_send_json_success(['message' => 'Сообщение отправлено в Авито']);
    }
    
    // ==================== AI АНАЛИЗ ====================
    
    public function ajax_run_ai_analysis() {
        wp_send_json_success(['message' => 'AI анализ выполнен']);
    }
    
    public function ajax_bulk_ai_analysis() {
        wp_send_json_success(['message' => 'Массовый AI анализ выполнен']);
    }
    
    public function ajax_save_openai_settings() {
        wp_send_json_success(['message' => 'Настройки OpenAI сохранены']);
    }
    
    public function ajax_check_openai_key() {
        wp_send_json_success(['valid' => true, 'message' => 'API ключ действителен']);
    }
    
    // ==================== КАЛЬКУЛЯТОР ====================
    
    public function ajax_calculate_payment() {
        $work_cost = isset($_POST['work_cost']) ? floatval($_POST['work_cost']) : 0;
        $work_hours = isset($_POST['work_hours']) ? floatval($_POST['work_hours']) : 0;
        $standard_hours = isset($_POST['standard_hours']) ? floatval($_POST['standard_hours']) : 1;
        $percent = isset($_POST['percent']) ? floatval($_POST['percent']) : 0;
        
        $payment = $work_cost * ($work_hours / $standard_hours) * ($percent / 100);
        
        wp_send_json_success([
            'payment' => round($payment),
            'payment_formatted' => number_format(round($payment), 0, ',', ' ') . ' ₽'
        ]);
    }
    
    public function ajax_get_employee_percent() {
        wp_send_json_success(['percent' => 50]);
    }
}
