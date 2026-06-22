<?php
if (!defined('ABSPATH')) exit;

class AKPP_AJAX {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Поиск
        add_action('wp_ajax_akpp_search_parts', [$this, 'ajax_search_parts']);
        add_action('wp_ajax_akpp_search_vehicles', [$this, 'ajax_search_vehicles']);
        add_action('wp_ajax_akpp_search_employees', [$this, 'ajax_search_employees']);
        
        // Сделки
        add_action('wp_ajax_akpp_save_deal', [$this, 'ajax_save_deal']);
        add_action('wp_ajax_akpp_decode_vin', [$this, 'ajax_decode_vin']);
        
        // Сохранение сущностей
        add_action('wp_ajax_akpp_save_employee', [$this, 'ajax_save_employee']);
        add_action('wp_ajax_akpp_save_part', [$this, 'ajax_save_part']);
        add_action('wp_ajax_akpp_save_lead', [$this, 'ajax_save_lead']);
        add_action('wp_ajax_akpp_save_vehicle', [$this, 'ajax_save_vehicle']);
        add_action('wp_ajax_akpp_save_oil', [$this, 'ajax_save_oil']);
        add_action('wp_ajax_akpp_save_transmission', [$this, 'ajax_save_transmission']);
        
        // Удаление сущностей
        add_action('wp_ajax_akpp_delete_employee', [$this, 'ajax_delete_employee']);
        add_action('wp_ajax_akpp_delete_part', [$this, 'ajax_delete_part']);
        add_action('wp_ajax_akpp_delete_lead', [$this, 'ajax_delete_lead']);
        add_action('wp_ajax_akpp_delete_vehicle', [$this, 'ajax_delete_vehicle']);
        add_action('wp_ajax_akpp_delete_oil', [$this, 'ajax_delete_oil']);
        add_action('wp_ajax_akpp_delete_transmission', [$this, 'ajax_delete_transmission']);
        add_action('wp_ajax_akpp_delete_deal', [$this, 'ajax_delete_deal']);
        add_action('wp_ajax_akpp_delete_user', [$this, 'ajax_delete_user']);
        
        // Парсер
        add_action('wp_ajax_akpp_parse_url', [$this, 'ajax_parse_url']);
        add_action('wp_ajax_akpp_get_parser_items', [$this, 'ajax_get_parser_items']);
        add_action('wp_ajax_akpp_get_parser_item', [$this, 'ajax_get_parser_item']);
        add_action('wp_ajax_akpp_reparse_url', [$this, 'ajax_reparse_url']);
        add_action('wp_ajax_akpp_delete_parser_item', [$this, 'ajax_delete_parser_item']);
        add_action('wp_ajax_akpp_bulk_parse', [$this, 'ajax_bulk_parse']);
        add_action('wp_ajax_akpp_export_parser_items', [$this, 'ajax_export_parser_items']);
        
        // AI анализ
        add_action('wp_ajax_akpp_run_ai_analysis', [$this, 'ajax_run_ai_analysis']);
        add_action('wp_ajax_akpp_bulk_ai_analysis', [$this, 'ajax_bulk_ai_analysis']);
        add_action('wp_ajax_akpp_save_openai_settings', [$this, 'ajax_save_openai_settings']);
        add_action('wp_ajax_akpp_check_openai_key', [$this, 'ajax_check_openai_key']);
        add_action('wp_ajax_akpp_analyze_image', [$this, 'ajax_analyze_image']);
        add_action('wp_ajax_akpp_get_ai_statistics', [$this, 'ajax_get_ai_statistics']);
        add_action('wp_ajax_akpp_approve_parser_item', [$this, 'ajax_approve_parser_item']);
        add_action('wp_ajax_akpp_reject_parser_item', [$this, 'ajax_reject_parser_item']);
        
        // Telegram
        add_action('wp_ajax_akpp_save_telegram_settings', [$this, 'ajax_save_telegram_settings']);
        add_action('wp_ajax_akpp_send_test_telegram', [$this, 'ajax_send_test_telegram']);
        add_action('wp_ajax_akpp_set_telegram_webhook', [$this, 'ajax_set_telegram_webhook']);
    }

    private function check_permissions($capability = 'manage_options') {
        if (!current_user_can($capability)) {
            wp_send_json_error(['message' => 'Недостаточно прав'], 403);
            return false;
        }
        return true;
    }

    // ========================================================================
    // СОХРАНЕНИЕ СУЩНОСТЕЙ
    // ========================================================================

    public function ajax_save_employee() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности']);
        }
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $full_name = sanitize_text_field($_POST['full_name'] ?? $_POST['name'] ?? '');
        $role = sanitize_text_field($_POST['role'] ?? 'mechanic');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? 'active');
        
        if (empty($full_name)) {
            wp_send_json_error(['message' => 'Заполните ФИО']);
            return;
        }
        
        $data = [
            'name' => $full_name,
            'role' => $role,
            'phone' => $phone,
            'is_active' => ($status === 'active') ? 1 : 0
        ];
        
        $email = sanitize_email($_POST['email'] ?? '');
        if (!empty($email)) {
            $data['email'] = $email;
        }
        
        try {
            if ($id > 0) {
                $result = $wpdb->update($wpdb->prefix . 'akpp_employees', $data, ['id' => $id]);
            } else {
                $result = $wpdb->insert($wpdb->prefix . 'akpp_employees', $data);
                $id = $wpdb->insert_id;
            }
            
            if ($result !== false) {
                wp_send_json_success(['message' => 'Сотрудник сохранен', 'id' => $id]);
            } else {
                wp_send_json_error(['message' => 'Ошибка БД: ' . $wpdb->last_error]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    public function ajax_save_part() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности']);
        }
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'sku' => sanitize_text_field($_POST['sku'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'quantity' => intval($_POST['quantity'] ?? 0),
            'price' => floatval($_POST['price'] ?? 0)
        ];
        
        if (empty($data['name'])) {
            wp_send_json_error(['message' => 'Укажите название запчасти']);
            return;
        }
        
        try {
            if ($id > 0) {
                $result = $wpdb->update($wpdb->prefix . 'akpp_parts', $data, ['id' => $id]);
            } else {
                $result = $wpdb->insert($wpdb->prefix . 'akpp_parts', $data);
                $id = $wpdb->insert_id;
            }
            
            if ($result !== false) {
                wp_send_json_success(['message' => 'Запчасть сохранена', 'id' => $id]);
            } else {
                wp_send_json_error(['message' => 'Ошибка БД: ' . $wpdb->last_error]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    public function ajax_save_lead() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности']);
        }
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $data = [
            'client_name' => sanitize_text_field($_POST['client_name'] ?? ''),
            'client_phone' => sanitize_text_field($_POST['client_phone'] ?? ''),
            'client_email' => sanitize_email($_POST['client_email'] ?? ''),
            'car_brand' => sanitize_text_field($_POST['car_brand'] ?? ''),
            'problem' => sanitize_textarea_field($_POST['problem'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'new'),
            'source' => sanitize_text_field($_POST['source'] ?? 'site_form')
        ];
        
        if (empty($data['client_name']) || empty($data['client_phone'])) {
            wp_send_json_error(['message' => 'Заполните имя и телефон']);
            return;
        }
        
        try {
            if ($id > 0) {
                $result = $wpdb->update($wpdb->prefix . 'akpp_leads', $data, ['id' => $id]);
            } else {
                $result = $wpdb->insert($wpdb->prefix . 'akpp_leads', $data);
                $id = $wpdb->insert_id;
            }
            
            if ($result !== false) {
                wp_send_json_success(['message' => 'Лид сохранен', 'id' => $id]);
            } else {
                wp_send_json_error(['message' => 'Ошибка БД: ' . $wpdb->last_error]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    public function ajax_save_vehicle() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности']);
        }
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        // ✅ ИСПРАВЛЕНО: добавлено поле market + strtoupper для VIN
        $data = [
            'vin'        => strtoupper(sanitize_text_field($_POST['vin'] ?? '')),
            'make'       => sanitize_text_field($_POST['make'] ?? ''),
            'model'      => sanitize_text_field($_POST['model'] ?? ''),
            'year'       => intval($_POST['year'] ?? 0),
            'engine'     => sanitize_text_field($_POST['engine'] ?? ''),
            'fuel_type'  => sanitize_text_field($_POST['fuel_type'] ?? ''),
            'drive_type' => sanitize_text_field($_POST['drive_type'] ?? ''),
            'market'     => sanitize_text_field($_POST['market'] ?? ''),
        ];
        
        if (empty($data['make']) || empty($data['model'])) {
            wp_send_json_error(['message' => 'Укажите марку и модель']);
            return;
        }
        
        try {
            if ($id > 0) {
                $result = $wpdb->update($wpdb->prefix . 'akpp_vehicles', $data, ['id' => $id]);
            } else {
                $result = $wpdb->insert($wpdb->prefix . 'akpp_vehicles', $data);
                $id = $wpdb->insert_id;
            }
            
            if ($result !== false) {
                wp_send_json_success(['message' => 'Автомобиль сохранен', 'id' => $id]);
            } else {
                wp_send_json_error(['message' => 'Ошибка БД: ' . $wpdb->last_error]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    public function ajax_save_oil() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности']);
        }
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'type' => sanitize_text_field($_POST['type'] ?? 'ATF'),
            'viscosity' => sanitize_text_field($_POST['viscosity'] ?? ''),
            'specifications' => sanitize_textarea_field($_POST['specifications'] ?? ''),
            'fill_volume' => floatval($_POST['fill_volume'] ?? 0),
            'price_per_liter' => floatval($_POST['price_per_liter'] ?? 0)
        ];
        
        if (empty($data['name'])) {
            wp_send_json_error(['message' => 'Укажите название масла']);
            return;
        }
        
        try {
            if ($id > 0) {
                $result = $wpdb->update($wpdb->prefix . 'akpp_oils', $data, ['id' => $id]);
            } else {
                $result = $wpdb->insert($wpdb->prefix . 'akpp_oils', $data);
                $id = $wpdb->insert_id;
            }
            
            if ($result !== false) {
                wp_send_json_success(['message' => 'Масло сохранено', 'id' => $id]);
            } else {
                wp_send_json_error(['message' => 'Ошибка БД: ' . $wpdb->last_error]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    public function ajax_save_transmission() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности']);
        }
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $data = [
            'code' => sanitize_text_field($_POST['code'] ?? ''),
            'type' => sanitize_text_field($_POST['type'] ?? ''),
            'make' => sanitize_text_field($_POST['make'] ?? ''),
            'model' => sanitize_text_field($_POST['model'] ?? ''),
            'years' => sanitize_text_field($_POST['years'] ?? ''),
            'engine' => sanitize_text_field($_POST['engine'] ?? ''),
            'common_problems' => sanitize_textarea_field($_POST['common_problems'] ?? ''),
            'repair_cost' => intval($_POST['repair_cost'] ?? 0),
            'difficulty' => intval($_POST['difficulty'] ?? 3),
            'manufacturer' => sanitize_text_field($_POST['manufacturer'] ?? ''),
            'region' => sanitize_text_field($_POST['region'] ?? '')
        ];
        
        if (empty($data['code'])) {
            wp_send_json_error(['message' => 'Укажите код АКПП']);
            return;
        }
        
        try {
            if ($id > 0) {
                $result = $wpdb->update($wpdb->prefix . 'akpp_transmissions', $data, ['id' => $id]);
            } else {
                $result = $wpdb->insert($wpdb->prefix . 'akpp_transmissions', $data);
                $id = $wpdb->insert_id;
            }
            
            if ($result !== false) {
                wp_send_json_success(['message' => 'АКПП сохранена', 'id' => $id]);
            } else {
                wp_send_json_error(['message' => 'Ошибка БД: ' . $wpdb->last_error]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    // ========================================================================
    // УДАЛЕНИЕ СУЩНОСТЕЙ
    // ========================================================================

    public function ajax_delete_employee() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности']);
        }
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        global $wpdb;
        
        try {
            $result = $wpdb->delete($wpdb->prefix . 'akpp_employees', ['id' => $id]);
            if ($result !== false) {
                wp_send_json_success(['message' => 'Сотрудник удален']);
            } else {
                wp_send_json_error(['message' => 'Ошибка удаления']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    public function ajax_delete_part() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности']);
        }
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        global $wpdb;
        
        try {
            $result = $wpdb->delete($wpdb->prefix . 'akpp_parts', ['id' => $id]);
            if ($result !== false) {
                wp_send_json_success(['message' => 'Запчасть удалена']);
            } else {
                wp_send_json_error(['message' => 'Ошибка удаления']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    public function ajax_delete_lead() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности']);
        }
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        global $wpdb;
        
        try {
            $result = $wpdb->delete($wpdb->prefix . 'akpp_leads', ['id' => $id]);
            if ($result !== false) {
                wp_send_json_success(['message' => 'Лид удален']);
            } else {
                wp_send_json_error(['message' => 'Ошибка удаления']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    public function ajax_delete_vehicle() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности']);
        }
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        global $wpdb;
        
        try {
            $result = $wpdb->delete($wpdb->prefix . 'akpp_vehicles', ['id' => $id]);
            if ($result !== false) {
                wp_send_json_success(['message' => 'Автомобиль удален']);
            } else {
                wp_send_json_error(['message' => 'Ошибка удаления']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    public function ajax_delete_oil() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности']);
        }
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        global $wpdb;
        
        try {
            $result = $wpdb->delete($wpdb->prefix . 'akpp_oils', ['id' => $id]);
            if ($result !== false) {
                wp_send_json_success(['message' => 'Масло удалено']);
            } else {
                wp_send_json_error(['message' => 'Ошибка удаления']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    public function ajax_delete_transmission() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности']);
        }
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        global $wpdb;
        
        try {
            $result = $wpdb->delete($wpdb->prefix . 'akpp_transmissions', ['id' => $id]);
            if ($result !== false) {
                wp_send_json_success(['message' => 'АКПП удалена']);
            } else {
                wp_send_json_error(['message' => 'Ошибка удаления']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    public function ajax_delete_deal() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности']);
        }
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        global $wpdb;
        
        try {
            $result = $wpdb->delete($wpdb->prefix . 'akpp_deals', ['id' => $id]);
            if ($result !== false) {
                wp_send_json_success(['message' => 'Сделка удалена']);
            } else {
                wp_send_json_error(['message' => 'Ошибка удаления']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    public function ajax_delete_user() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности']);
        }
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        global $wpdb;
        
        try {
            $result = $wpdb->delete($wpdb->prefix . 'akpp_site_users', ['id' => $id]);
            if ($result !== false) {
                wp_send_json_success(['message' => 'Пользователь удален']);
            } else {
                wp_send_json_error(['message' => 'Ошибка удаления']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    // ========================================================================
    // ПОИСК
    // ========================================================================

    public function ajax_search_parts() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_parts';
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE name LIKE %s OR sku LIKE %s LIMIT 20",
                '%' . $wpdb->esc_like($query) . '%',
                '%' . $wpdb->esc_like($query) . '%'
            ), ARRAY_A);
            
            wp_send_json_success($results);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка поиска: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_search_vehicles() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_vehicles';
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE vin LIKE %s OR make LIKE %s OR model LIKE %s LIMIT 20",
                '%' . $wpdb->esc_like($query) . '%',
                '%' . $wpdb->esc_like($query) . '%',
                '%' . $wpdb->esc_like($query) . '%'
            ), ARRAY_A);
            
            wp_send_json_success($results);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка поиска: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_search_employees() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_employees';
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE name LIKE %s OR role LIKE %s LIMIT 20",
                '%' . $wpdb->esc_like($query) . '%',
                '%' . $wpdb->esc_like($query) . '%'
            ), ARRAY_A);
            
            wp_send_json_success($results);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка поиска: ' . $e->getMessage()], 500);
        }
    }

//=======================================================================
// сделки
public function ajax_save_deal() {
    if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
        return;
    }
    if (!$this->check_permissions()) return;
    
    global $wpdb;
    
    try {
        $deal_id = intval($_POST['deal_id'] ?? $_POST['id'] ?? 0);
        $lead_id = intval($_POST['lead_id'] ?? 0);
        
        // ====================================================================
        // 1. СОЗДАЁМ ИЛИ НАХОДИМ КЛИЕНТА (СНАЧАЛА!)
        // ====================================================================
        $client_name = sanitize_text_field($_POST['client_name'] ?? '');
        $client_phone = sanitize_text_field($_POST['client_phone'] ?? '');
        
        if (empty($client_name) || empty($client_phone)) {
            wp_send_json_error(['message' => 'Укажите ФИО и телефон клиента']);
            return;
        }
        
        $client_id = intval($_POST['client_id'] ?? 0);
        
        if ($client_id <= 0) {
            $clean_phone = preg_replace('/[^0-9]/', '', $client_phone);
            
            $existing_client = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}akpp_site_users WHERE phone = %s LIMIT 1",
                $clean_phone
            ), ARRAY_A);
            
            if ($existing_client) {
                $client_id = intval($existing_client['id']);
            } else {
                $wpdb->insert($wpdb->prefix . 'akpp_site_users', [
                    'full_name' => $client_name,
                    'phone' => $clean_phone,
                    'status' => 'active',
                    'registered_at' => current_time('mysql'),
                ]);
                $client_id = $wpdb->insert_id;
            }
        }
        
        // ====================================================================
        // 2. ПРОВЕРКА НА ДУБЛИ (ПОСЛЕ получения client_id!)
        // ====================================================================
        if ($deal_id <= 0) {
            $duplicate_check = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}akpp_deals 
                 WHERE client_id = %d 
                 AND created_date = CURDATE()
                 LIMIT 1",
                $client_id
            ));
            
            if ($duplicate_check) {
                wp_send_json_success([
                    'id' => intval($duplicate_check->id),
                    'message' => 'Сделка на сегодня уже существует',
                    'duplicate' => true
                ]);
                return;
            }
        }
        
        // ====================================================================
        // 3. СОЗДАЁМ ИЛИ НАХОДИМ АВТОМОБИЛЬ
        // ====================================================================
        $vin = strtoupper(sanitize_text_field($_POST['vin'] ?? ''));
        $make = sanitize_text_field($_POST['brand'] ?? $_POST['make'] ?? '');
        $model = sanitize_text_field($_POST['model'] ?? '');
        $year = intval($_POST['year'] ?? 0);
        $engine = sanitize_text_field($_POST['engine'] ?? '');
        
        $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
        
        if ($vehicle_id <= 0 && (!empty($vin) || (!empty($make) && !empty($model)))) {
            if (!empty($vin)) {
                $existing_vehicle = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}akpp_vehicles WHERE vin = %s LIMIT 1",
                    $vin
                ), ARRAY_A);
                if ($existing_vehicle) $vehicle_id = intval($existing_vehicle['id']);
            }
            if ($vehicle_id <= 0 && !empty($make) && !empty($model)) {
                $wpdb->insert($wpdb->prefix . 'akpp_vehicles', [
                    'vin' => $vin, 'make' => $make, 'model' => $model,
                    'year' => $year, 'engine' => $engine,
                ]);
                $vehicle_id = $wpdb->insert_id;
            }
        }
        
        if ($vehicle_id <= 0) $vehicle_id = null;
        
        // ====================================================================
        // 4. РАСЧЁТ СТОИМОСТИ
        // ====================================================================
        $standard_hours = floatval($_POST['hours'] ?? $_POST['standard_hours'] ?? 1.0);
        $hourly_rate = floatval($_POST['hourly_rate'] ?? 1500);
        $employee_percent = floatval($_POST['emp_percent'] ?? $_POST['employee_percent'] ?? 40);
        
        $work_cost = $standard_hours * $hourly_rate;
        
        $parts = $_POST['parts'] ?? [];
        $parts_total = 0;
        $parts_data = [];
        
        if (!empty($parts) && is_array($parts)) {
            foreach ($parts as $part) {
                $part_id = intval($part['id'] ?? 0);
                $qty = intval($part['quantity'] ?? 0);
                $price = floatval($part['price'] ?? 0);
                if ($part_id > 0 && $qty > 0) {
                    $parts_total += $price * $qty;
                    $parts_data[] = ['part_id' => $part_id, 'quantity' => $qty, 'price_at_deal' => $price];
                }
            }
        }
        
        $total_amount = floatval($_POST['cost'] ?? $_POST['total_amount'] ?? $_POST['payment_amount'] ?? 0);
        if ($total_amount <= 0) $total_amount = $work_cost + $parts_total;
        
        // ====================================================================
        // 5. ПОДГОТОВКА ДАННЫХ
        // ====================================================================
        $employee_id = intval($_POST['employee_id'] ?? 0);
        if ($employee_id <= 0) $employee_id = null;
        
        $data = [
            'client_id' => $client_id,
            'vehicle_id' => $vehicle_id,
            'vin' => $vin,
            'make' => $make,
            'model' => $model,
            'year' => $year,
            'problem_description' => sanitize_textarea_field($_POST['comment'] ?? $_POST['problem'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'new'),
            'employee_id' => $employee_id,
            'work_cost' => $work_cost,
            'work_hours' => $standard_hours,
            'standard_hours' => $standard_hours,
            'employee_percent' => $employee_percent,
            'parts_total' => $parts_total,
            'total_amount' => $total_amount,
            'payment_amount' => $total_amount,
            'updated_at' => current_time('mysql'),
        ];
        
        $table = $wpdb->prefix . 'akpp_deals';
        
        if ($deal_id > 0) {
            $wpdb->update($table, $data, ['id' => $deal_id]);
            $result_id = $deal_id;
            $wpdb->delete($wpdb->prefix . 'akpp_deal_parts', ['deal_id' => $deal_id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
            $result_id = $wpdb->insert_id;
        }
        
        // ====================================================================
        // 6. СОХРАНЕНИЕ ЗАПЧАСТЕЙ
        // ====================================================================
        foreach ($parts_data as $part) {
            $wpdb->insert($wpdb->prefix . 'akpp_deal_parts', [
                'deal_id' => $result_id,
                'part_id' => $part['part_id'],
                'quantity' => $part['quantity'],
                'price_at_deal' => $part['price_at_deal'],
            ]);
        }
        
        // ====================================================================
        // 7. КОНВЕРТАЦИЯ ЛИДА
        // ====================================================================
        if ($lead_id > 0) {
            $lead_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}akpp_leads WHERE id = %d",
                $lead_id
            ));
            if ($lead_exists) {
                $wpdb->update(
                    $wpdb->prefix . 'akpp_leads',
                    ['status' => 'converted', 'updated_at' => current_time('mysql')],
                    ['id' => $lead_id]
                );
            }
        }
        
        wp_send_json_success([
            'id' => $result_id,
            'message' => 'Сделка сохранена' . ($lead_id > 0 ? ' и лид конвертирован' : ''),
            'client_id' => $client_id,
            'vehicle_id' => $vehicle_id,
            'total' => $total_amount,
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Ошибка сохранения: ' . $e->getMessage()], 500);
    }
}

    public function ajax_decode_vin() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        $vin = strtoupper(sanitize_text_field($_POST['vin'] ?? ''));
        
        if (strlen($vin) !== 17) {
            wp_send_json_error(['message' => 'VIN должен содержать 17 символов'], 400);
            return;
        }
        
        try {
            global $wpdb;
            $cache_table = $wpdb->prefix . 'akpp_vin_cache';
            
            // Проверяем существование таблицы
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$cache_table'") === $cache_table;
            
            if ($table_exists) {
                $cached = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $cache_table WHERE vin = %s",
                    $vin
                ), ARRAY_A);
                
                if ($cached) {
                    wp_send_json_success(json_decode($cached['decoded_data'] ?? $cached['data'] ?? '[]', true));
                    return;
                }
            }
            
            $data = [
                'vin' => $vin,
                'mark' => 'Неизвестно',
                'model' => 'Неизвестно',
                'year' => '',
                'engine' => '',
                'transmission' => ''
            ];
            
            wp_send_json_success($data);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка декодирования: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // ПАРСЕР
    // ========================================================================

    public function ajax_parse_url() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        $url = esc_url_raw($_POST['url'] ?? '');
        if (empty($url)) {
            wp_send_json_error(['message' => 'URL не указан'], 400);
            return;
        }
        
        try {
            // ✅ ПОДКЛЮЧАЕМ ПАРСЕР
            require_once dirname(__FILE__) . '/class-akpp-parser.php';
            
            // ✅ ВЫЗЫВАЕМ ПАРСИНГ
            $parser = AKPP_Parser::get_instance();
            $result = $parser->parse($url);
            
            if ($result && isset($result['id'])) {
                wp_send_json_success([
                    'id' => $result['id'],
                    'message' => '✅ Распаршено: ' . mb_substr($result['title'] ?? 'Без заголовка', 0, 80)
                ]);
            } else {
                wp_send_json_error(['message' => '❌ Не удалось распарсить URL']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_get_parser_items() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_parser_items';
            $items = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100", ARRAY_A);
            wp_send_json_success($items);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_get_parser_item() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        try {
            global $wpdb;
            $item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}akpp_parser_items WHERE id = %d", $id
            ), ARRAY_A);
            if (!$item) {
                wp_send_json_error(['message' => 'Элемент не найден'], 404);
                return;
            }
            wp_send_json_success($item);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_reparse_url() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        try {
            global $wpdb;
            $wpdb->update($wpdb->prefix . 'akpp_parser_items', [
                'status' => 'parsing',
                'updated_at' => current_time('mysql')
            ], ['id' => $id]);
            wp_send_json_success(['message' => 'Повторный парсинг запущен']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_delete_parser_item() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        try {
            global $wpdb;
            $wpdb->delete($wpdb->prefix . 'akpp_parser_items', ['id' => $id]);
            wp_send_json_success(['message' => 'Элемент удален']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_bulk_parse() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        try {
            require_once dirname(__FILE__) . '/class-akpp-parser.php';
            $parser = AKPP_Parser::get_instance();
            
            $urls = array_filter(array_map('esc_url_raw', (array)($_POST['urls'] ?? [])));
            $parsed = 0;
            
            foreach ($urls as $url) {
                if (empty($url)) continue;
                $result = $parser->parse($url);
                if ($result) $parsed++;
            }
            
            wp_send_json_success(['message' => "✅ Распаршено URL: $parsed"]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_export_parser_items() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        try {
            global $wpdb;
            $items = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}akpp_parser_items ORDER BY created_at DESC", ARRAY_A);
            
            // ✅ ИСПРАВЛЕНО: защита от CSV injection
            $sanitize_csv = function($value) {
                if (empty($value)) return '';
                // Защита от формул Excel (начинаются с =, +, -, @)
                if (preg_match('/^[=+\-@]/', $value)) {
                    $value = "'" . $value;
                }
                // Экранирование кавычек и переносов
                return str_replace(['"', "\r", "\n", ";"], ['""', ' ', ' ', ','], $value);
            };
            
            $csv = "ID;URL;Заголовок;Тип;Статус;Дата\n";
            foreach ($items as $item) {
                $csv .= sprintf(
                    "%d;\"%s\";\"%s\";\"%s\";\"%s\";\"%s\"\n",
                    intval($item['id']),
                    $sanitize_csv($item['url']),
                    $sanitize_csv($item['title']),
                    $sanitize_csv($item['content_type'] ?? ''),
                    $sanitize_csv($item['status']),
                    $sanitize_csv($item['created_at'])
                );
            }
            
            wp_send_json_success(['csv' => $csv, 'count' => count($items)]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // AI АНАЛИЗ
    // ========================================================================

    public function ajax_run_ai_analysis() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        $item_id = intval($_POST['item_id'] ?? 0);
        
        try {
            require_once dirname(__FILE__) . '/class-akpp-parser.php';
            $parser = AKPP_Parser::get_instance();
            
            global $wpdb;
            $item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}akpp_parser_items WHERE id = %d", 
                $item_id
            ), ARRAY_A);
            
            if (!$item) {
                wp_send_json_error(['message' => 'Элемент не найден'], 404);
                return;
            }
            
            // ✅ ВЫЗЫВАЕМ AI АНАЛИЗ
            $analysis = $parser->analyze_with_qwen($item['content']);
            
            if ($analysis) {
                // Сохраняем извлеченные сущности
                $saved = $parser->save_extracted_entities($analysis);
                
                $wpdb->update($wpdb->prefix . 'akpp_parser_items', [
                    'ai_analysis' => json_encode($analysis, JSON_UNESCAPED_UNICODE),
                    'status' => 'ai_processed',
                    'updated_at' => current_time('mysql')
                ], ['id' => $item_id]);
                
                wp_send_json_success([
                    'message' => '✅ AI анализ завершён',
                    'saved' => $saved,
                    'analysis' => $analysis
                ]);
            } else {
                wp_send_json_error(['message' => '❌ AI не ответил. Проверьте API ключ.']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_bulk_ai_analysis() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        try {
            require_once dirname(__FILE__) . '/class-akpp-parser.php';
            $parser = AKPP_Parser::get_instance();
            
            global $wpdb;
            
            // ✅ ИСПРАВЛЕНО: получаем ВСЕ pending записи, а не только переданные IDs
            $ids = !empty($_POST['ids']) 
                ? array_map('intval', (array)$_POST['ids'])
                : $wpdb->get_col("SELECT id FROM {$wpdb->prefix}akpp_parser_items WHERE status = 'pending' LIMIT 10");
            
            $analyzed = 0;
            $errors = [];
            
            foreach ($ids as $id) {
                if ($id <= 0) continue;
                
                $item = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}akpp_parser_items WHERE id = %d",
                    $id
                ), ARRAY_A);
                
                if (!$item || empty($item['content'])) {
                    $errors[] = "ID {$id}: нет контента";
                    continue;
                }
                
                // ✅ ВЫЗЫВАЕМ РЕАЛЬНЫЙ AI АНАЛИЗ
                $analysis = $parser->analyze_with_qwen($item['content']);
                
                if ($analysis) {
                    $saved = $parser->save_extracted_entities($analysis);
                    
                    $wpdb->update($wpdb->prefix . 'akpp_parser_items', [
                        'ai_analysis' => json_encode($analysis, JSON_UNESCAPED_UNICODE),
                        'status'      => 'ai_processed',
                        'updated_at'  => current_time('mysql')
                    ], ['id' => $id]);
                    
                    $analyzed++;
                } else {
                    $errors[] = "ID {$id}: AI не ответил";
                }
                
                // Задержка чтобы не превысить rate limit API
                usleep(500000); // 0.5 сек
            }
            
            $message = "✅ Проанализировано: {$analyzed}";
            if (!empty($errors)) {
                $message .= " | Ошибок: " . count($errors);
            }
            
            wp_send_json_success([
                'message'  => $message,
                'analyzed' => $analyzed,
                'errors'   => $errors
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_save_openai_settings() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions('manage_options')) return;
        
        try {
            update_option('akpp_openai_api_key', sanitize_text_field($_POST['api_key'] ?? ''));
            update_option('akpp_openai_model', sanitize_text_field($_POST['model'] ?? 'gpt-3.5-turbo'));
            wp_send_json_success(['message' => 'Настройки сохранены']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_check_openai_key() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions('manage_options')) return;
        
        try {
            $api_key = get_option('akpp_openai_api_key', '');
            if (empty($api_key)) {
                wp_send_json_error(['message' => 'API ключ не установлен']);
                return;
            }
            wp_send_json_success(['message' => 'Ключ установлен']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_analyze_image() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        try {
            wp_send_json_success(['description' => 'Автоматический анализ', 'condition' => 'Хорошее', 'defects' => []]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_get_ai_statistics() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_parser_items';
            wp_send_json_success([
                'total' => (int)$wpdb->get_var("SELECT COUNT(*) FROM $table"),
                'analyzed' => (int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'analyzed'"),
                'pending' => (int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'parsed'")
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_approve_parser_item() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        try {
            global $wpdb;
            $wpdb->update($wpdb->prefix . 'akpp_parser_items', [
                'status' => 'approved',
                'updated_at' => current_time('mysql')
            ], ['id' => $id]);
            wp_send_json_success(['message' => 'Элемент одобрен']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_reject_parser_item() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        try {
            global $wpdb;
            $wpdb->update($wpdb->prefix . 'akpp_parser_items', [
                'status' => 'rejected',
                'updated_at' => current_time('mysql')
            ], ['id' => $id]);
            wp_send_json_success(['message' => 'Элемент отклонен']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // TELEGRAM
    // ========================================================================

    public function ajax_save_telegram_settings() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions('manage_options')) return;
        
        try {
            update_option('akpp_telegram_bot_token', sanitize_text_field($_POST['bot_token'] ?? ''));
            update_option('akpp_telegram_chat_id', sanitize_text_field($_POST['chat_id'] ?? ''));
            wp_send_json_success(['message' => 'Настройки Telegram сохранены']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_send_test_telegram() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions('manage_options')) return;
        
        try {
            $bot_token = get_option('akpp_telegram_bot_token', '');
            $chat_id = get_option('akpp_telegram_chat_id', '');
            if (empty($bot_token) || empty($chat_id)) {
                wp_send_json_error(['message' => 'Настройки Telegram не заполнены'], 400);
                return;
            }
            $response = wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
                'body' => ['chat_id' => $chat_id, 'text' => '✅ Тестовое сообщение от CRM АКПП45!']
            ]);
            if (is_wp_error($response)) {
                wp_send_json_error(['message' => 'Ошибка: ' . $response->get_error_message()], 500);
                return;
            }
            wp_send_json_success(['message' => 'Тестовое сообщение отправлено']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_set_telegram_webhook() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions('manage_options')) return;
        
        try {
            $bot_token = get_option('akpp_telegram_bot_token', '');
            if (empty($bot_token)) {
                wp_send_json_error(['message' => 'Bot token не установлен'], 400);
                return;
            }
            $webhook_url = home_url('/wp-json/akpp/v1/telegram-webhook');
            $response = wp_remote_post("https://api.telegram.org/bot{$bot_token}/setWebhook", [
                'body' => ['url' => $webhook_url]
            ]);
            if (is_wp_error($response)) {
                wp_send_json_error(['message' => 'Ошибка: ' . $response->get_error_message()], 500);
                return;
            }
            wp_send_json_success(['message' => 'Webhook установлен: ' . $webhook_url]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }
}