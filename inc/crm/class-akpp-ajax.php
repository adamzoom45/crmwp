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
        // VIN AI декодер
        add_action('wp_ajax_akpp_decode_vin_ai', [$this, 'ajax_decode_vin_ai']);
      
        // Договор-оферта
		add_action('wp_ajax_akpp_save_agreement', [$this, 'ajax_save_agreement']);
		add_action('wp_ajax_akpp_get_agreements', [$this, 'ajax_get_agreements']);
		add_action('wp_ajax_akpp_get_agreement_text', [$this, 'ajax_get_agreement_text']);
      
        // Поиск
        add_action('wp_ajax_akpp_search_parts', [$this, 'ajax_search_parts']);
        add_action('wp_ajax_akpp_search_vehicles', [$this, 'ajax_search_vehicles']);
        add_action('wp_ajax_akpp_search_employees', [$this, 'ajax_search_employees']);
        add_action('wp_ajax_akpp_search_vehicles_full', [$this, 'ajax_search_vehicles_full']);
        
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
        
        // Категории склада
        add_action('wp_ajax_akpp_get_categories', [$this, 'ajax_get_categories']);
        add_action('wp_ajax_akpp_save_category', [$this, 'ajax_save_category']);
        add_action('wp_ajax_akpp_delete_category', [$this, 'ajax_delete_category']);
        add_action('wp_ajax_akpp_toggle_category', [$this, 'ajax_toggle_category']);
        
        // Магазин
        add_action('wp_ajax_akpp_shop_get_products', [$this, 'ajax_shop_get_products']);
        add_action('wp_ajax_akpp_shop_save_product', [$this, 'ajax_shop_save_product']);
        add_action('wp_ajax_akpp_shop_update_order_status', [$this, 'ajax_shop_update_order_status']);
        add_action('wp_ajax_akpp_shop_get_orders', [$this, 'ajax_shop_get_orders']);
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
        
        $purchase_price = floatval($_POST['purchase_price'] ?? 0);
        $markup_percent = floatval($_POST['markup_percent'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        
        if ($price <= 0 && $purchase_price > 0) {
            $price = $purchase_price * (1 + $markup_percent / 100);
        }
        
        $category = sanitize_text_field($_POST['category'] ?? 'parts');
        
        $data = [
            'name'           => sanitize_text_field($_POST['name'] ?? ''),
            'sku'            => sanitize_text_field($_POST['sku'] ?? ''),
            'category'       => $category,
            'description'    => sanitize_textarea_field($_POST['description'] ?? ''),
            'quantity'       => floatval($_POST['quantity'] ?? 0),
            'unit'           => sanitize_text_field($_POST['unit'] ?? 'шт'),
            'purchase_price' => $purchase_price,
            'markup_percent' => $markup_percent,
            'price'          => $price,
            'supplier'       => sanitize_text_field($_POST['supplier'] ?? ''),
            'location'       => sanitize_text_field($_POST['location'] ?? ''),
        ];
        
        if (empty($data['name'])) {
            wp_send_json_error(['message' => 'Укажите наименование']);
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
                wp_send_json_success(['message' => 'Позиция сохранена', 'id' => $id]);
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
        
        $vin = strtoupper(sanitize_text_field($_POST['vin'] ?? ''));
        $make = sanitize_text_field($_POST['make'] ?? '');
        $model = sanitize_text_field($_POST['model'] ?? '');
        
        if (empty($make) || empty($model)) {
            wp_send_json_error(['message' => 'Укажите марку и модель']);
            return;
        }
        
        $data = [
            'make'       => $make,
            'model'      => $model,
            'year'       => intval($_POST['year'] ?? 0),
            'engine'     => sanitize_text_field($_POST['engine'] ?? ''),
            'fuel_type'  => sanitize_text_field($_POST['fuel_type'] ?? ''),
            'drive_type' => sanitize_text_field($_POST['drive_type'] ?? ''),
            'market'     => sanitize_text_field($_POST['market'] ?? ''),
        ];
        
        if (!empty($vin)) {
            $data['vin'] = $vin;
        }
        
        try {
            if (!empty($vin)) {
                $existing_by_vin = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}akpp_vehicles WHERE vin = %s AND id != %d LIMIT 1",
                    $vin, $id
                ));
                
                if ($existing_by_vin) {
                    wp_send_json_error([
                        'message' => 'Автомобиль с таким VIN уже существует (ID: ' . $existing_by_vin . ')'
                    ]);
                    return;
                }
            }
            
            if ($id > 0) {
                $update_data = $data;
                if (empty($vin)) {
                    $update_data['vin'] = null;
                }
                $result = $wpdb->update($wpdb->prefix . 'akpp_vehicles', $update_data, ['id' => $id]);
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

    public function ajax_search_vehicles_full() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        if (strlen($query) < 2) {
            wp_send_json_success([]);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_vehicles';
        $like = '%' . $wpdb->esc_like($query) . '%';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, make, model, year, vin, engine FROM {$table} 
             WHERE make LIKE %s OR model LIKE %s OR vin LIKE %s 
             ORDER BY make, model LIMIT 20",
            $like, $like, $like
        ), ARRAY_A);
        
        wp_send_json_success($results);
    }

    // ========================================================================
    // СДЕЛКИ
    // ========================================================================

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
            
            // КЛИЕНТ
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
            
            // Проверка дублей
            if ($deal_id <= 0) {
                $duplicate_check = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}akpp_deals 
                     WHERE client_id = %d 
                     AND DATE(created_at) = CURDATE()
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
            
            // АВТОМОБИЛЬ
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
                    $existing_vehicle = $wpdb->get_row($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}akpp_vehicles 
                         WHERE make = %s AND model = %s AND year = %d 
                         LIMIT 1",
                        $make, $model, $year
                    ), ARRAY_A);
                    if ($existing_vehicle) $vehicle_id = intval($existing_vehicle['id']);
                }
                
                if ($vehicle_id <= 0) {
                    $wpdb->insert($wpdb->prefix . 'akpp_vehicles', [
                        'vin' => $vin ?: null,
                        'make' => $make,
                        'model' => $model,
                        'year' => $year,
                        'engine' => $engine,
                        'fuel_type' => sanitize_text_field($_POST['fuel_type'] ?? ''),
                        'drive_type' => sanitize_text_field($_POST['drive_type'] ?? ''),
                        'market' => sanitize_text_field($_POST['market'] ?? ''),
                        'created_at' => current_time('mysql'),
                    ]);
                    $vehicle_id = $wpdb->insert_id;
                }
            }
            
            if ($vehicle_id <= 0) $vehicle_id = null;
            
            // АКПП
            $transmission_code = sanitize_text_field($_POST['transmission_code'] ?? '');
            $transmission_id = intval($_POST['transmission_id'] ?? 0);
            
            if ($transmission_id <= 0 && !empty($transmission_code)) {
                $existing_trans = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}akpp_transmissions WHERE code = %s LIMIT 1",
                    $transmission_code
                ), ARRAY_A);
                
                if ($existing_trans) {
                    $transmission_id = intval($existing_trans['id']);
                } else {
                    $wpdb->insert($wpdb->prefix . 'akpp_transmissions', [
                        'code' => $transmission_code,
                        'type' => sanitize_text_field($_POST['transmission_type'] ?? ''),
                        'make' => $make,
                        'model' => $model,
                        'years' => $year ? "{$year}-" . ($year + 10) : '',
                        'engine' => $engine,
                        'created_at' => current_time('mysql'),
                    ]);
                    $transmission_id = $wpdb->insert_id;
                }
            }
            
            // РАСЧЕТ СТОИМОСТИ
            $calculation_type = sanitize_text_field($_POST['calculation_type'] ?? 'manual');
            
            if ($calculation_type === 'norm') {
                $standard_hours = floatval($_POST['standard_hours'] ?? 1.0);
                $hourly_rate = floatval($_POST['hourly_rate'] ?? 1500);
                $work_cost = $standard_hours * $hourly_rate;
            } else {
                $work_cost = floatval($_POST['work_cost'] ?? $_POST['cost'] ?? 0);
                $standard_hours = floatval($_POST['work_hours'] ?? 0);
                $hourly_rate = 0;
            }
            
            $employee_percent = floatval($_POST['emp_percent'] ?? $_POST['employee_percent'] ?? 40);
            
            // ЗАПЧАСТИ (цена УЖЕ с наценкой из БД)
            $parts = $_POST['parts'] ?? [];
            $parts_total = 0;
            $parts_data = [];
            
            if (!empty($parts) && is_array($parts)) {
                foreach ($parts as $part_json) {
                    $part = is_string($part_json) ? json_decode($part_json, true) : $part_json;
                    if (!is_array($part)) continue;
                    
                    $part_id = intval($part['id'] ?? 0);
                    $qty = intval($part['quantity'] ?? 0);
                    
                    if ($part_id <= 0 || $qty <= 0) continue;
                    
                    $db_part = $wpdb->get_row($wpdb->prepare(
                        "SELECT price, markup_percent FROM {$wpdb->prefix}akpp_parts WHERE id = %d",
                        $part_id
                    ));
                    
                    if ($db_part) {
                        $markup = floatval($db_part->markup_percent);
                        $price_with_markup = floatval($db_part->price) * (1 + $markup / 100);
                        
                        $parts_total += $price_with_markup * $qty;
                        $parts_data[] = [
                            'part_id' => $part_id,
                            'quantity' => $qty,
                            'price_at_deal' => $price_with_markup,
                        ];
                    }
                }
            }
            
            // Итоговая сумма
            $total_amount = floatval($_POST['total_amount'] ?? $_POST['payment_amount'] ?? 0);
            if ($total_amount <= 0) {
                $total_amount = $work_cost + $parts_total;
            }
            
            // СОХРАНЕНИЕ СДЕЛКИ
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
                'hourly_rate' => $hourly_rate,
                'calculation_type' => $calculation_type,
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
            
            // Сохраняем запчасти
            foreach ($parts_data as $part) {
                $wpdb->insert($wpdb->prefix . 'akpp_deal_parts', [
                    'deal_id' => $result_id,
                    'part_id' => $part['part_id'],
                    'quantity' => $part['quantity'],
                    'price_at_deal' => $part['price_at_deal'],
                ]);
            }
            
            // Конвертация лида
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
            
            // Telegram уведомление
            $this->send_deal_notification($result_id, $client_name, $client_phone, $total_amount);
            
            wp_send_json_success([
                'id' => $result_id,
                'message' => '✅ Сделка сохранена' . ($lead_id > 0 ? ' и лид конвертирован' : ''),
                'client_id' => $client_id,
                'vehicle_id' => $vehicle_id,
                'transmission_id' => $transmission_id,
                'total' => $total_amount,
                'parts_count' => count($parts_data),
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка сохранения: ' . $e->getMessage()], 500);
        }
    }
    
    private function send_deal_notification($deal_id, $client_name, $client_phone, $total) {
        $bot_token = get_option('akpp_telegram_bot_token', '');
        $chat_id = get_option('akpp_telegram_chat_id', '');
        
        if (empty($bot_token) || empty($chat_id)) return;
        
        $message = "🔧 *НОВАЯ СДЕЛКА #{$deal_id}*\n\n";
        $message .= "👤 *Клиент:* {$client_name}\n";
        $message .= "📞 *Телефон:* {$client_phone}\n";
        $message .= "💰 *Сумма:* " . number_format($total, 0, ',', ' ') . " ₽\n";
        
        wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
            'body' => [
                'chat_id' => $chat_id,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ],
            'timeout' => 5
        ]);
    }

    // ========================================================================
    // VIN AI ДЕКОДЕР
    // ========================================================================

    public function ajax_decode_vin_ai() {
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
        
        $api_key = get_option('akpp_openai_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API ключ Qwen не установлен']);
            return;
        }
        
        try {
            $prompt = "Расшифруй VIN номер {$vin} и верни информацию в формате JSON:\n" .
                      "{\n" .
                      "  \"make\": \"марка авто\",\n" .
                      "  \"model\": \"модель авто\",\n" .
                      "  \"year\": год_выпуска_числом,\n" .
                      "  \"engine\": \"объем и тип двигателя\",\n" .
                      "  \"engine_code\": \"код двигателя\",\n" .
                      "  \"transmission\": \"тип КПП\",\n" .
                      "  \"transmission_code\": \"код АКПП если есть\",\n" .
                      "  \"drive_type\": \"привод\",\n" .
                      "  \"fuel_type\": \"тип топлива\",\n" .
                      "  \"body_type\": \"тип кузова\",\n" .
                      "  \"country\": \"страна производства\"\n" .
                      "}";
            
            $model = get_option('akpp_openai_model', 'qwen-turbo');
            
            $body = [
                'model' => $model,
                'input' => [
                    'messages' => [
                        ['role' => 'system', 'content' => 'Ты эксперт по расшифровке VIN. Отвечай только JSON.'],
                        ['role' => 'user', 'content' => $prompt]
                    ]
                ],
                'parameters' => [
                    'result_format' => 'message',
                    'temperature' => 0.3
                ]
            ];
            
            $response = wp_remote_post('https://dashscope-intl.aliyuncs.com/api/v1/services/aigc/text-generation/generation', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($body),
                'timeout' => 30,
            ]);
            
            if (is_wp_error($response)) {
                wp_send_json_error(['message' => 'Ошибка API: ' . $response->get_error_message()], 500);
                return;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($status_code !== 200) {
                $error_msg = $response_body['message'] ?? 'Unknown error';
                wp_send_json_error(['message' => 'Ошибка API: ' . $error_msg], 500);
                return;
            }
            
            $analysis_text = $response_body['output']['choices'][0]['message']['content'] ?? '';
            
            if (empty($analysis_text)) {
                wp_send_json_error(['message' => 'Пустой ответ от AI'], 500);
                return;
            }
            
            if (preg_match('/\{[\s\S]*\}/', $analysis_text, $matches)) {
                $vin_data = json_decode($matches[0], true);
                
                if ($vin_data) {
                    global $wpdb;
                    $cache_table = $wpdb->prefix . 'akpp_vin_cache';
                    
                    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$cache_table'") === $cache_table;
                    
                    if ($table_exists) {
                        $wpdb->insert($cache_table, [
                            'vin' => $vin,
                            'decoded_data' => wp_json_encode($vin_data, JSON_UNESCAPED_UNICODE),
                            'created_at' => current_time('mysql'),
                        ]);
                    }
                    
                    wp_send_json_success([
                        'message' => '✅ VIN расшифрован',
                        'data' => $vin_data
                    ]);
                } else {
                    wp_send_json_error(['message' => 'Не удалось распарсить JSON'], 500);
                }
            } else {
                wp_send_json_error(['message' => 'AI вернул некорректный формат'], 500);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
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
            require_once dirname(__FILE__) . '/class-akpp-parser.php';
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
            
            $sanitize_csv = function($value) {
                if (empty($value)) return '';
                if (preg_match('/^[=+\-@]/', $value)) {
                    $value = "'" . $value;
                }
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
            
            $analysis = $parser->analyze_with_qwen($item['content']);
            
            if ($analysis) {
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
                
                usleep(500000);
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

    // ========================================================================
    // КАТЕГОРИИ СКЛАДА
    // ========================================================================

    public function ajax_get_categories() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_part_categories';
            
            $categories = $wpdb->get_results(
                "SELECT c.*, COUNT(p.id) as parts_count 
                 FROM {$table} c 
                 LEFT JOIN {$wpdb->prefix}akpp_parts p ON p.category = c.slug 
                 GROUP BY c.id 
                 ORDER BY c.sort_order ASC, c.name ASC",
                ARRAY_A
            );
            
            wp_send_json_success(['categories' => $categories]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_save_category() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_part_categories';
        
        $id = intval($_POST['id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $icon = sanitize_text_field($_POST['icon'] ?? '📦');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        
        if (empty($name)) {
            wp_send_json_error(['message' => 'Укажите название категории']);
            return;
        }
        
        $slug = sanitize_title($name);
        if (empty($slug)) {
            $slug = 'category-' . time();
        }
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE slug = %s AND id != %d",
            $slug, $id
        ));
        
        if ($existing) {
            $slug = $slug . '-' . time();
        }
        
        try {
            if ($id > 0) {
                $result = $wpdb->update($table, [
                    'name' => $name,
                    'slug' => $slug,
                    'icon' => $icon,
                    'description' => $description,
                    'sort_order' => $sort_order,
                    'updated_at' => current_time('mysql')
                ], ['id' => $id]);
                
                if ($result !== false) {
                    wp_send_json_success(['message' => 'Категория обновлена', 'id' => $id]);
                } else {
                    wp_send_json_error(['message' => 'Ошибка обновления: ' . $wpdb->last_error]);
                }
            } else {
                $result = $wpdb->insert($table, [
                    'name' => $name,
                    'slug' => $slug,
                    'icon' => $icon,
                    'description' => $description,
                    'sort_order' => $sort_order,
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ]);
                
                if ($result !== false) {
                    wp_send_json_success(['message' => 'Категория создана', 'id' => $wpdb->insert_id]);
                } else {
                    wp_send_json_error(['message' => 'Ошибка создания: ' . $wpdb->last_error]);
                }
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_delete_category() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error(['message' => 'Неверный ID категории']);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_part_categories';
        $parts_table = $wpdb->prefix . 'akpp_parts';
        
        try {
            $parts_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$parts_table} WHERE category = (SELECT slug FROM {$table} WHERE id = %d)",
                $id
            ));
            
            if ($parts_count > 0) {
                wp_send_json_error([
                    'message' => "Нельзя удалить: в категории {$parts_count} товаров."
                ]);
                return;
            }
            
            $result = $wpdb->delete($table, ['id' => $id]);
            
            if ($result !== false) {
                wp_send_json_success(['message' => 'Категория удалена']);
            } else {
                wp_send_json_error(['message' => 'Ошибка удаления']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function ajax_toggle_category() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error(['message' => 'Неверный ID']);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_part_categories';
        
        try {
            $current = $wpdb->get_var($wpdb->prepare(
                "SELECT is_active FROM {$table} WHERE id = %d",
                $id
            ));
            
            $new_status = $current ? 0 : 1;
            
            $wpdb->update($table, [
                'is_active' => $new_status,
                'updated_at' => current_time('mysql')
            ], ['id' => $id]);
            
            wp_send_json_success([
                'message' => $new_status ? 'Категория активирована' : 'Категория деактивирована',
                'is_active' => $new_status
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // МАГАЗИН
    // ========================================================================

    public function ajax_shop_get_products() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        if (!class_exists('AKPP_Shop')) {
            wp_send_json_error(['message' => 'Класс магазина не загружен'], 500);
            return;
        }
        
        $shop = AKPP_Shop::get_instance();
        $products = $shop->get_products([
            'category' => sanitize_text_field($_POST['category'] ?? ''),
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'per_page' => intval($_POST['per_page'] ?? 50),
        ]);
        
        wp_send_json_success($products);
    }

    public function ajax_shop_save_product() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        if (!class_exists('AKPP_Shop')) {
            wp_send_json_error(['message' => 'Класс магазина не загружен'], 500);
            return;
        }
        
        $shop = AKPP_Shop::get_instance();
        $shop->ajax_save_product();
    }

    public function ajax_shop_update_order_status() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        if (!class_exists('AKPP_Shop')) {
            wp_send_json_error(['message' => 'Класс магазина не загружен'], 500);
            return;
        }
        
        $shop = AKPP_Shop::get_instance();
        $shop->ajax_update_order_status();
    }

    public function ajax_shop_get_orders() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_orders';
        
        $orders = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100");
        wp_send_json_success($orders);
    }
  
  // ========================================================================
// ДОГОВОР-ОФЕРТА
// ========================================================================

public function ajax_save_agreement() {
    if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'akpp_agreements';
    
    $client_name = sanitize_text_field($_POST['client_name'] ?? '');
    $client_phone = sanitize_text_field($_POST['client_phone'] ?? '');
    $client_email = sanitize_email($_POST['client_email'] ?? '');
    $deal_id = intval($_POST['deal_id'] ?? 0);
    $source = sanitize_text_field($_POST['source'] ?? 'crm_deal');
    
    if (empty($client_name) || empty($client_phone)) {
        wp_send_json_error(['message' => 'Укажите ФИО и телефон клиента']);
        return;
    }
    
    $data = [
        'deal_id' => $deal_id > 0 ? $deal_id : null,
        'client_name' => $client_name,
        'client_phone' => $client_phone,
        'client_email' => $client_email,
        'agreement_version' => '1.0',
        'source' => $source,
        'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
        'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
        'accepted_at' => current_time('mysql'),
        'created_at' => current_time('mysql'),
    ];
    
    $result = $wpdb->insert($table, $data);
    
    if ($result === false) {
        wp_send_json_error(['message' => 'Ошибка БД: ' . $wpdb->last_error]);
        return;
    }
    
    $agreement_id = $wpdb->insert_id;
    
    // Если есть сделка — обновляем её
    if ($deal_id > 0) {
        $wpdb->update(
            $wpdb->prefix . 'akpp_deals',
            [
                'agreement_accepted' => 1,
                'agreement_id' => $agreement_id,
            ],
            ['id' => $deal_id]
        );
    }
    
    wp_send_json_success([
        'message' => '✅ Согласие с офертой сохранено',
        'agreement_id' => $agreement_id,
    ]);
}

public function ajax_get_agreements() {
    if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
        return;
    }
    if (!$this->check_permissions()) return;
    
    global $wpdb;
    $table = $wpdb->prefix . 'akpp_agreements';
    
    $page = intval($_POST['page'] ?? 1);
    $per_page = 50;
    $offset = ($page - 1) * $per_page;
    
    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $agreements = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} ORDER BY accepted_at DESC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));
    
    wp_send_json_success([
        'agreements' => $agreements,
        'total' => intval($total),
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page),
    ]);
}

public function ajax_get_agreement_text() {
    if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
        return;
    }
    
    if (!function_exists('akpp_get_agreement_text')) {
        require_once dirname(__FILE__) . '/templates/agreement-text.php';
    }
    
    $html = akpp_get_agreement_text('1.0');
    wp_send_json_success(['html' => $html]);
}
}