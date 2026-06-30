<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX модуль: Админка CRM (CRUD операции)
 * Сотрудники, Автомобили, Запчасти, Масла, АКПП
 */
class AKPP_AJAX_Admin extends AKPP_AJAX_Base {
    
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
    
    /**
     * Регистрация AJAX хуков
     */
    private function register_hooks() {
        // Сотрудники
        add_action('wp_ajax_akpp_save_employee', [$this, 'ajax_save_employee']);
        add_action('wp_ajax_akpp_delete_employee', [$this, 'ajax_delete_employee']);
        
        // Автомобили
        add_action('wp_ajax_akpp_save_vehicle', [$this, 'ajax_save_vehicle']);
        add_action('wp_ajax_akpp_delete_vehicle', [$this, 'ajax_delete_vehicle']);
        
        // Запчасти
        add_action('wp_ajax_akpp_save_part', [$this, 'ajax_save_part']);
        add_action('wp_ajax_akpp_delete_part', [$this, 'ajax_delete_part']);
        
        // Масла
        add_action('wp_ajax_akpp_save_oil', [$this, 'ajax_save_oil']);
        add_action('wp_ajax_akpp_delete_oil', [$this, 'ajax_delete_oil']);
        
        // АКПП (Трансмиссии)
        add_action('wp_ajax_akpp_save_transmission', [$this, 'ajax_save_transmission']);
        add_action('wp_ajax_akpp_delete_transmission', [$this, 'ajax_delete_transmission']);
    }
    
    // ========================================================================
    // СОТРУДНИКИ
    // ========================================================================
    
    public function ajax_save_employee() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');  // ✅ Правильный nonce
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        // Маппинг полей формы к полям БД
        $full_name = sanitize_text_field($_POST['full_name'] ?? $_POST['name'] ?? '');
        $role = sanitize_text_field($_POST['role'] ?? 'mechanic');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? 'active');
        
        if (empty($full_name)) {
            wp_send_json_error(['message' => 'Заполните ФИО']);
            return;
        }
        
        $data = [
            'name'      => $full_name,
            'role'      => $role,
            'phone'     => $phone,
            'is_active' => ($status === 'active') ? 1 : 0
        ];
        
        // Email добавляем ТОЛЬКО если он не пустой
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
                wp_send_json_success(['message' => 'Сотрудник сохранён', 'id' => $id]);
            } else {
                wp_send_json_error(['message' => 'Ошибка БД: ' . $wpdb->last_error]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }
    
    public function ajax_delete_employee() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $wpdb->delete($wpdb->prefix . 'akpp_employees', ['id' => $id]);
        
        wp_send_json_success(['message' => 'Сотрудник удалён']);
    }
    
    // ========================================================================
    // АВТОМОБИЛИ
    // ========================================================================
    
    public function ajax_save_vehicle() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $data = [
            'make'      => sanitize_text_field($_POST['make'] ?? ''),
            'model'     => sanitize_text_field($_POST['model'] ?? ''),
            'year'      => intval($_POST['year'] ?? 0),
            'vin'       => strtoupper(sanitize_text_field($_POST['vin'] ?? '')),
            'engine'    => sanitize_text_field($_POST['engine'] ?? ''),
            'market'    => sanitize_text_field($_POST['market'] ?? '')
        ];
        
        if (empty($data['make']) || empty($data['model'])) {
            wp_send_json_error(['message' => 'Марка и модель обязательны']);
            return;
        }
        
        if ($id > 0) {
            $wpdb->update($wpdb->prefix . 'akpp_vehicles', $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($wpdb->prefix . 'akpp_vehicles', $data);
        }
        
        wp_send_json_success(['message' => 'Автомобиль сохранён', 'id' => $id ?: $wpdb->insert_id]);
    }
    
    public function ajax_delete_vehicle() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $wpdb->delete($wpdb->prefix . 'akpp_vehicles', ['id' => $id]);
        
        wp_send_json_success(['message' => 'Автомобиль удалён']);
    }
    
    // ========================================================================
    // ЗАПЧАСТИ
    // ========================================================================
    
    public function ajax_save_part() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $data = [
            'name'           => sanitize_text_field($_POST['name'] ?? ''),
            'sku'            => sanitize_text_field($_POST['sku'] ?? ''),
            'price'          => floatval($_POST['price'] ?? 0),
            'condition_type' => sanitize_text_field($_POST['condition_type'] ?? 'used'),
            'stock'          => intval($_POST['stock'] ?? 0),
            'images'         => sanitize_textarea_field($_POST['images'] ?? '')
        ];
        
        if (empty($data['name'])) {
            wp_send_json_error(['message' => 'Название запчасти обязательно']);
            return;
        }
        
        if ($id > 0) {
            $wpdb->update($wpdb->prefix . 'akpp_parts', $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($wpdb->prefix . 'akpp_parts', $data);
        }
        
        wp_send_json_success(['message' => 'Запчасть сохранена', 'id' => $id ?: $wpdb->insert_id]);
    }
    
    public function ajax_delete_part() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $wpdb->delete($wpdb->prefix . 'akpp_parts', ['id' => $id]);
        
        wp_send_json_success(['message' => 'Запчасть удалена']);
    }
    
    // ========================================================================
    // МАСЛА
    // ========================================================================
    
    public function ajax_save_oil() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $data = [
            'name'   => sanitize_text_field($_POST['name'] ?? ''),
            'type'   => sanitize_text_field($_POST['type'] ?? ''),
            'volume' => sanitize_text_field($_POST['volume'] ?? ''),
            'price'  => floatval($_POST['price'] ?? 0),
            'stock'  => intval($_POST['stock'] ?? 0)
        ];
        
        if (empty($data['name'])) {
            wp_send_json_error(['message' => 'Название масла обязательно']);
            return;
        }
        
        if ($id > 0) {
            $wpdb->update($wpdb->prefix . 'akpp_oils', $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($wpdb->prefix . 'akpp_oils', $data);
        }
        
        wp_send_json_success(['message' => 'Масло сохранено', 'id' => $id ?: $wpdb->insert_id]);
    }
    
    public function ajax_delete_oil() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $wpdb->delete($wpdb->prefix . 'akpp_oils', ['id' => $id]);
        
        wp_send_json_success(['message' => 'Масло удалено']);
    }
    
    // ========================================================================
    // АКПП (ТРАНСМИССИИ)
    // ========================================================================
    
    public function ajax_save_transmission() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $data = [
            'name'  => sanitize_text_field($_POST['name'] ?? ''),
            'type'  => sanitize_text_field($_POST['type'] ?? ''),
            'code'  => sanitize_text_field($_POST['code'] ?? ''),
            'price' => floatval($_POST['price'] ?? 0),
            'stock' => intval($_POST['stock'] ?? 0)
        ];
        
        if (empty($data['name'])) {
            wp_send_json_error(['message' => 'Название АКПП обязательно']);
            return;
        }
        
        if ($id > 0) {
            $wpdb->update($wpdb->prefix . 'akpp_transmissions', $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($wpdb->prefix . 'akpp_transmissions', $data);
        }
        
        wp_send_json_success(['message' => 'АКПП сохранена', 'id' => $id ?: $wpdb->insert_id]);
    }
    
    public function ajax_delete_transmission() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $wpdb->delete($wpdb->prefix . 'akpp_transmissions', ['id' => $id]);
        
        wp_send_json_success(['message' => 'АКПП удалена']);
    }
}