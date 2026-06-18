<?php
/**
 * Центральный класс для работы с базой данных CRM
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_DB {
    
    private static $instance = null;
    private $wpdb;
    private $prefix;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . 'akpp_';
    }
    
    // ==================== СДЕЛКИ ====================
    
    public function get_deal($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}deals WHERE id = %d",
            $id
        ));
    }
    
    public function get_deals($args = []) {
        $defaults = [
            'status' => '',
            'employee_id' => 0,
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];
        $args = wp_parse_args($args, $defaults);
        
        $where = 'WHERE 1=1';
        if (!empty($args['status'])) {
            $where .= $this->wpdb->prepare(" AND status = %s", $args['status']);
        }
        if (!empty($args['employee_id'])) {
            $where .= $this->wpdb->prepare(" AND employee_id = %d", $args['employee_id']);
        }
        
        $query = "SELECT * FROM {$this->prefix}deals {$where} ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
        $query = $this->wpdb->prepare($query, $args['limit'], $args['offset']);
        
        return $this->wpdb->get_results($query);
    }
    
    public function add_deal($data) {
        return $this->wpdb->insert($this->prefix . 'deals', $data);
    }
    
    public function update_deal($id, $data) {
        return $this->wpdb->update($this->prefix . 'deals', $data, ['id' => $id]);
    }
    
    public function delete_deal($id) {
        return $this->wpdb->delete($this->prefix . 'deals', ['id' => $id]);
    }
    
    public function update_deal_status($id, $status) {
        return $this->wpdb->update(
            $this->prefix . 'deals',
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $id]
        );
    }
    
    // ==================== ЗАПЧАСТИ ====================
    
    public function get_part($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}parts WHERE id = %d",
            $id
        ));
    }
    
    public function get_parts($search = '', $category = '', $limit = 20, $offset = 0) {
        $where = 'WHERE 1=1';
        if (!empty($search)) {
            $where .= $this->wpdb->prepare(" AND (name LIKE '%%%s%%' OR sku LIKE '%%%s%%')", $search, $search);
        }
        if (!empty($category)) {
            $where .= $this->wpdb->prepare(" AND category = %s", $category);
        }
        
        $query = "SELECT * FROM {$this->prefix}parts {$where} ORDER BY name ASC LIMIT %d OFFSET %d";
        $query = $this->wpdb->prepare($query, $limit, $offset);
        
        return $this->wpdb->get_results($query);
    }
    
    public function update_part_quantity($id, $quantity_change) {
        return $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->prefix}parts SET quantity = quantity + %d WHERE id = %d",
            $quantity_change,
            $id
        ));
    }
    
    public function search_parts($query) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}parts 
            WHERE (name LIKE '%%%s%%' OR sku LIKE '%%%s%%') 
            AND quantity > 0 
            LIMIT 20",
            $query,
            $query
        ));
    }
    
    // ==================== ЛИДЫ ====================
    
    public function get_lead($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}leads WHERE id = %d",
            $id
        ));
    }
    
    public function get_leads($status = '', $limit = 20, $offset = 0) {
        $where = '';
        if (!empty($status)) {
            $where = $this->wpdb->prepare("WHERE status = %s", $status);
        }
        
        $query = "SELECT * FROM {$this->prefix}leads {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query = $this->wpdb->prepare($query, $limit, $offset);
        
        return $this->wpdb->get_results($query);
    }
    
    public function add_lead($data) {
        return $this->wpdb->insert($this->prefix . 'leads', $data);
    }
    
    public function update_lead_status($id, $status) {
        return $this->wpdb->update($this->prefix . 'leads', ['status' => $status], ['id' => $id]);
    }
    
    // ==================== СОТРУДНИКИ ====================
    
    public function get_employee($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}employees WHERE id = %d",
            $id
        ));
    }
    
    public function get_employee_by_telegram($telegram_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}employees WHERE telegram_id = %s",
            $telegram_id
        ));
    }
    
    public function get_active_guide() {
        return $this->wpdb->get_row(
            "SELECT * FROM {$this->prefix}employees 
            WHERE role = 'guide' AND is_active = 1 
            ORDER BY id ASC LIMIT 1"
        );
    }
    
    public function update_employee_telegram($id, $telegram_id, $chat_id, $username = '') {
        return $this->wpdb->update(
            $this->prefix . 'employees',
            [
                'telegram_id' => $telegram_id,
                'telegram_chat_id' => $chat_id,
                'telegram_username' => $username
            ],
            ['id' => $id]
        );
    }
    
    // ==================== ПОЛЬЗОВАТЕЛИ САЙТА ====================
    
    public function get_user($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}site_users WHERE id = %d",
            $id
        ));
    }
    
    public function get_user_by_email($email) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}site_users WHERE email = %s",
            $email
        ));
    }
    
    public function add_user($data) {
        return $this->wpdb->insert($this->prefix . 'site_users', $data);
    }
    
    public function update_user($id, $data) {
        return $this->wpdb->update($this->prefix . 'site_users', $data, ['id' => $id]);
    }
    
    // ==================== АВТОМОБИЛИ ====================
    
    public function get_vehicle($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}vehicles WHERE id = %d",
            $id
        ));
    }
    
    public function get_vehicle_by_vin($vin) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}vehicles WHERE vin = %s",
            $vin
        ));
    }
    
    public function add_vehicle($data) {
        return $this->wpdb->insert($this->prefix . 'vehicles', $data);
    }
    
    // ==================== СООБЩЕНИЯ ЧАТА ====================
    
    public function get_chat_messages($user_id, $with_user, $last_id = 0) {
        if ($last_id > 0) {
            return $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->prefix}chat_messages 
                WHERE ((sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d))
                AND id > %d
                ORDER BY created_at ASC",
                $user_id, $with_user, $with_user, $user_id, $last_id
            ));
        } else {
            return $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->prefix}chat_messages 
                WHERE ((sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d))
                ORDER BY created_at ASC
                LIMIT 100",
                $user_id, $with_user, $with_user, $user_id
            ));
        }
    }
    
    public function add_chat_message($sender_id, $receiver_id, $message) {
        return $this->wpdb->insert(
            $this->prefix . 'chat_messages',
            [
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'message' => $message,
                'is_read' => 0,
                'created_at' => current_time('mysql')
            ]
        );
    }
    
    public function mark_messages_read($sender_id, $receiver_id) {
        return $this->wpdb->update(
            $this->prefix . 'chat_messages',
            ['is_read' => 1],
            [
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'is_read' => 0
            ]
        );
    }
    
    public function get_unread_counts($user_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT sender_id, COUNT(*) as unread_count 
            FROM {$this->prefix}chat_messages 
            WHERE receiver_id = %d AND is_read = 0 
            GROUP BY sender_id",
            $user_id
        ));
    }
    
    // ==================== VIN КЭШ ====================
    
    public function get_vin_cache($vin) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}vin_cache 
            WHERE vin = %s AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $vin
        ));
    }
    
    public function save_vin_cache($vin, $data) {
        $this->wpdb->delete($this->prefix . 'vin_cache', ['vin' => $vin]);
        return $this->wpdb->insert(
            $this->prefix . 'vin_cache',
            [
                'vin' => $vin,
                'decoded_data' => json_encode($data),
                'created_at' => current_time('mysql')
            ]
        );
    }
    
    // ==================== ПАРСЕР ====================
    
    public function add_parser_item($url, $title, $content, $content_type, $images = []) {
        return $this->wpdb->insert(
            $this->prefix . 'parser_items',
            [
                'url' => $url,
                'title' => $title,
                'content' => $content,
                'content_type' => $content_type,
                'images' => json_encode($images),
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ]
        );
    }
    
    public function update_parser_item_ai($id, $ai_analysis) {
        return $this->wpdb->update(
            $this->prefix . 'parser_items',
            [
                'ai_analysis' => json_encode($ai_analysis),
                'status' => 'ai_processed',
                'updated_at' => current_time('mysql')
            ],
            ['id' => $id]
        );
    }
    
    // ==================== PUSH ТОКЕНЫ ====================
    
    public function save_push_token($user_id, $token, $device_type = 'web') {
        $this->wpdb->delete($this->prefix . 'push_tokens', ['token' => $token]);
        return $this->wpdb->insert(
            $this->prefix . 'push_tokens',
            [
                'user_id' => $user_id,
                'token' => $token,
                'device_type' => $device_type,
                'created_at' => current_time('mysql')
            ]
        );
    }
    
    public function get_user_push_tokens($user_id) {
        return $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT token FROM {$this->prefix}push_tokens WHERE user_id = %d",
            $user_id
        ));
    }
    
    public function delete_push_token($token) {
        return $this->wpdb->delete($this->prefix . 'push_tokens', ['token' => $token]);
    }
    
    // ==================== АВИТО ====================
    
    public function get_active_avito_token() {
        return $this->wpdb->get_row(
            "SELECT * FROM {$this->prefix}avito_tokens WHERE is_active = 1 LIMIT 1"
        );
    }
    
    public function save_avito_token($access_token, $expires_in) {
        $this->wpdb->delete($this->prefix . 'avito_tokens', ['is_active' => 1]);
        return $this->wpdb->insert(
            $this->prefix . 'avito_tokens',
            [
                'access_token' => $access_token,
                'expires_in' => $expires_in,
                'created_at' => current_time('mysql'),
                'is_active' => 1
            ]
        );
    }
    
    public function save_avito_dialog($dialog_id, $user_id, $user_name, $last_message = '') {
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->prefix}avito_dialogs WHERE dialog_id = %s",
            $dialog_id
        ));
        
        if ($existing) {
            return $this->wpdb->update(
                $this->prefix . 'avito_dialogs',
                [
                    'last_message' => $last_message,
                    'last_message_date' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['dialog_id' => $dialog_id]
            );
        } else {
            return $this->wpdb->insert(
                $this->prefix . 'avito_dialogs',
                [
                    'dialog_id' => $dialog_id,
                    'user_id' => $user_id,
                    'user_name' => $user_name,
                    'last_message' => $last_message,
                    'last_message_date' => current_time('mysql'),
                    'is_active' => 1,
                    'created_at' => current_time('mysql')
                ]
            );
        }
    }
}
