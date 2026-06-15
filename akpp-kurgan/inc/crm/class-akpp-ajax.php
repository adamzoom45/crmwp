<?php
if (!defined('ABSPATH')) exit;

class AKPP_Ajax {
    
    public function __construct() {
        // Сотрудники
        add_action('wp_ajax_akpp_save_employee', [$this, 'save_employee']);
        add_action('wp_ajax_akpp_delete_employee', [$this, 'delete_employee']);
        
        // Автомобили
        add_action('wp_ajax_akpp_save_vehicle', [$this, 'save_vehicle']);
        add_action('wp_ajax_akpp_delete_vehicle', [$this, 'delete_vehicle']);
        
        // Сделки
        add_action('wp_ajax_akpp_save_deal', [$this, 'save_deal']);
        add_action('wp_ajax_akpp_delete_deal', [$this, 'delete_deal']);
        add_action('wp_ajax_akpp_update_deal_status', [$this, 'update_deal_status']);
        
        // Склад и запчасти
        add_action('wp_ajax_akpp_save_part', [$this, 'save_part']);
        add_action('wp_ajax_akpp_delete_part', [$this, 'delete_part']);
        add_action('wp_ajax_akpp_deduct_parts', [$this, 'deduct_parts']);
        
        // Лиды и чат
        add_action('wp_ajax_akpp_save_lead', [$this, 'save_lead']);
        add_action('wp_ajax_akpp_assign_guide', [$this, 'assign_guide']);
        add_action('wp_ajax_akpp_send_chat_message', [$this, 'send_chat_message']);
        add_action('wp_ajax_akpp_get_chat_messages', [$this, 'get_chat_messages']);
        
        // VIN декодер и Парсер
        add_action('wp_ajax_akpp_decode_vin', [$this, 'decode_vin']);
        add_action('wp_ajax_akpp_parse_url', [$this, 'parse_url']);
        add_action('wp_ajax_akpp_analyze_with_ai', [$this, 'analyze_with_ai']);
        
        // Push и Авито
        add_action('wp_ajax_akpp_save_push_token', [$this, 'save_push_token']);
        add_action('wp_ajax_akpp_send_avito_message', [$this, 'send_avito_message']);
    }

    private function check_nonce() {
        if (!check_ajax_referer('akpp_crm_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности (nonce)']);
        }
    }

    // --- СОТРУДНИКИ ---
    public function save_employee() {
        $this->check_nonce();
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_employees';
        
        $data = [
            'full_name' => sanitize_text_field($_POST['full_name']),
            'role' => sanitize_text_field($_POST['role']),
            'phone' => sanitize_text_field($_POST['phone']),
            'status' => sanitize_text_field($_POST['status'])
        ];
        
        if (!empty($_POST['id'])) {
            $wpdb->update($table, $data, ['id' => intval($_POST['id'])]);
            wp_send_json_success(['message' => 'Сотрудник обновлен', 'id' => $_POST['id']]);
        } else {
            $wpdb->insert($table, $data);
            wp_send_json_success(['message' => 'Сотрудник добавлен', 'id' => $wpdb->insert_id]);
        }
    }

    public function delete_employee() {
        $this->check_nonce();
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'akpp_employees', ['id' => intval($_POST['id'])]);
        wp_send_json_success(['message' => 'Сотрудник удален']);
    }

    // --- АВТОМОБИЛИ ---
    public function save_vehicle() {
        $this->check_nonce();
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_vehicles';
        
        $data = [
            'brand' => sanitize_text_field($_POST['brand']),
            'model' => sanitize_text_field($_POST['model']),
            'year' => intval($_POST['year']),
            'market' => sanitize_text_field($_POST['market']),
            'vin' => sanitize_text_field($_POST['vin']),
            'engine' => sanitize_text_field($_POST['engine'])
        ];
        
        if (!empty($_POST['id'])) {
            $wpdb->update($table, $data, ['id' => intval($_POST['id'])]);
            wp_send_json_success(['message' => 'Авто обновлено']);
        } else {
            $wpdb->insert($table, $data);
            wp_send_json_success(['message' => 'Авто добавлено', 'id' => $wpdb->insert_id]);
        }
    }

    public function delete_vehicle() {
        $this->check_nonce();
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'akpp_vehicles', ['id' => intval($_POST['id'])]);
        wp_send_json_success(['message' => 'Авто удалено']);
    }

    // --- СДЕЛКИ ---
    public function save_deal() {
        $this->check_nonce();
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_deals';
        
        $data = [
            'client_name' => sanitize_text_field($_POST['client_name']),
            'client_phone' => sanitize_text_field($_POST['client_phone']),
            'vehicle_id' => intval($_POST['vehicle_id']),
            'transmission_id' => intval($_POST['transmission_id']),
            'status' => sanitize_text_field($_POST['status']),
            'total_amount' => floatval($_POST['total_amount']),
            'employee_id' => intval($_POST['employee_id'])
        ];
        
        if (!empty($_POST['id'])) {
            $wpdb->update($table, $data, ['id' => intval($_POST['id'])]);
            $deal_id = intval($_POST['id']);
        } else {
            $wpdb->insert($table, $data);
            $deal_id = $wpdb->insert_id;
        }
        
        // Авто-списание запчастей при создании/обновлении сделки
        if (!empty($_POST['parts']) && is_array($_POST['parts'])) {
            $this->process_deal_parts($deal_id, $_POST['parts']);
        }
        
        wp_send_json_success(['message' => 'Сделка сохранена', 'id' => $deal_id]);
    }

    public function update_deal_status() {
        $this->check_nonce();
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'akpp_deals',
            ['status' => sanitize_text_field($_POST['status'])],
            ['id' => intval($_POST['id'])]
        );
        wp_send_json_success(['message' => 'Статус обновлен']);
    }

    public function delete_deal() {
        $this->check_nonce();
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'akpp_deals', ['id' => intval($_POST['id'])]);
        $wpdb->delete($wpdb->prefix . 'akpp_deal_parts', ['deal_id' => intval($_POST['id'])]);
        wp_send_json_success(['message' => 'Сделка удалена']);
    }

    // --- СКЛАД ---
    public function save_part() {
        $this->check_nonce();
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parts';
        
        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'sku' => sanitize_text_field($_POST['sku']),
            'category' => sanitize_text_field($_POST['category']),
            'quantity' => intval($_POST['quantity']),
            'price' => floatval($_POST['price'])
        ];
        
        if (!empty($_POST['id'])) {
            $wpdb->update($table, $data, ['id' => intval($_POST['id'])]);
            wp_send_json_success(['message' => 'Запчасть обновлена']);
        } else {
            $wpdb->insert($table, $data);
            wp_send_json_success(['message' => 'Запчасть добавлена', 'id' => $wpdb->insert_id]);
        }
    }

    public function delete_part() {
        $this->check_nonce();
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'akpp_parts', ['id' => intval($_POST['id'])]);
        wp_send_json_success(['message' => 'Запчасть удалена']);
    }

    private function process_deal_parts($deal_id, $parts) {
        global $wpdb;
        $parts_table = $wpdb->prefix . 'akpp_parts';
        $deal_parts_table = $wpdb->prefix . 'akpp_deal_parts';
        
        // Удаляем старые привязки
        $wpdb->delete($deal_parts_table, ['deal_id' => $deal_id]);
        
        foreach ($parts as $part) {
            $part_id = intval($part['id']);
            $qty = intval($part['quantity']);
            
            // Проверяем наличие
            $current_qty = $wpdb->get_var($wpdb->prepare("SELECT quantity FROM $parts_table WHERE id = %d", $part_id));
            if ($current_qty < $qty) {
                continue; // Или можно кидать ошибку, но для простоты пропускаем
            }
            
            // Списываем со склада
            $wpdb->query($wpdb->prepare("UPDATE $parts_table SET quantity = quantity - %d WHERE id = %d", $qty, $part_id));
            
            // Записываем в историю сделки
            $price = floatval($part['price']);
            $wpdb->insert($deal_parts_table, [
                'deal_id' => $deal_id,
                'part_id' => $part_id,
                'quantity' => $qty,
                'price_at_deal' => $price
            ]);
        }
    }

    // --- ЛИДЫ И ЧАТ ---
    public function save_lead() {
        $this->check_nonce();
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'akpp_leads', [
            'source' => sanitize_text_field($_POST['source']),
            'name' => sanitize_text_field($_POST['name']),
            'phone' => sanitize_text_field($_POST['phone']),
            'message' => sanitize_textarea_field($_POST['message']),
            'status' => 'new'
        ]);
        wp_send_json_success(['message' => 'Лид создан', 'id' => $wpdb->insert_id]);
    }

    public function send_chat_message() {
        $this->check_nonce();
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'akpp_chat_messages', [
            'deal_id' => intval($_POST['deal_id']),
            'user_id' => get_current_user_id(),
            'message' => sanitize_textarea_field($_POST['message']),
            'is_internal' => intval($_POST['is_internal'] ?? 0)
        ]);
        wp_send_json_success(['message' => 'Сообщение отправлено']);
    }

    public function get_chat_messages() {
        $this->check_nonce();
        global $wpdb;
        $deal_id = intval($_POST['deal_id']);
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}akpp_chat_messages WHERE deal_id = %d ORDER BY created_at ASC",
            $deal_id
        ));
        wp_send_json_success(['messages' => $messages]);
    }

    // --- VIN ДЕКОДЕР ---
    public function decode_vin() {
        $this->check_nonce();
        $vin = sanitize_text_field($_POST['vin']);
        
        if (class_exists('AKPP_VIN_Decoder')) {
            $decoder = new AKPP_VIN_Decoder();
            $result = $decoder->decode($vin);
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => 'Класс декодера не найден']);
        }
    }

    // --- ПАРСЕР И AI ---
    public function parse_url() {
        $this->check_nonce();
        $url = esc_url_raw($_POST['url']);
        
        if (class_exists('AKPP_Parser')) {
            $parser = new AKPP_Parser();
            $result = $parser->parse($url);
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => 'Класс парсера не найден']);
        }
    }

    public function analyze_with_ai() {
        $this->check_nonce();
        $text = sanitize_textarea_field($_POST['text']);
        
        if (class_exists('AKPP_AI_Analyzer')) {
            $analyzer = new AKPP_AI_Analyzer();
            $result = $analyzer->analyze($text);
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => 'Класс AI не найден']);
        }
    }

    // --- PUSH И АВИТО ---
    public function save_push_token() {
        $this->check_nonce();
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'akpp_push_tokens', [
            'user_id' => get_current_user_id(),
            'token' => sanitize_text_field($_POST['token']),
            'device_type' => sanitize_text_field($_POST['device_type'])
        ]);
        wp_send_json_success(['message' => 'Токен сохранен']);
    }

    public function send_avito_message() {
        $this->check_nonce();
        // Логика отправки через класс AKPP_Avito
        if (class_exists('AKPP_Avito')) {
            $avito = new AKPP_Avito();
            $result = $avito->send_message($_POST['dialog_id'], $_POST['message']);
            if ($result) {
                wp_send_json_success(['message' => 'Сообщение отправлено в Авито']);
            } else {
                wp_send_json_error(['message' => 'Ошибка отправки в Авито']);
            }
        } else {
            wp_send_json_error(['message' => 'Класс Авито не найден']);
        }
    }
}

// Инициализация
new AKPP_Ajax();
