<?php
/**
 * Класс для установки и создания таблиц БД CRM
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_Install {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
    /**
     * Создание всех таблиц CRM
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $tables = [
            $this->get_site_users_table_sql($charset_collate),
            $this->get_employees_table_sql($charset_collate),
            $this->get_vehicles_table_sql($charset_collate),
            $this->get_transmissions_table_sql($charset_collate),
            $this->get_deals_table_sql($charset_collate),
            $this->get_leads_table_sql($charset_collate),
            $this->get_parts_table_sql($charset_collate),
            $this->get_oils_table_sql($charset_collate),
            $this->get_deal_parts_table_sql($charset_collate),
            $this->get_avito_tokens_table_sql($charset_collate),
            $this->get_avito_dialogs_table_sql($charset_collate),
            $this->get_avito_messages_cache_table_sql($charset_collate),
            $this->get_chat_messages_table_sql($charset_collate),
            $this->get_parser_items_table_sql($charset_collate),
            $this->get_vin_cache_table_sql($charset_collate),
            $this->get_push_tokens_table_sql($charset_collate)
        ];
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($tables as $sql) {
            dbDelta($sql);
        }
        
        $this->add_test_data();
        
        update_option('akpp_crm_db_version', AKPP_CRM_VERSION);
    }
    
    /**
     * Таблица: wp_akpp_site_users (пользователи сайта)
     */
    private function get_site_users_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_site_users';
        
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20) DEFAULT NULL,
            password varchar(255) NOT NULL,
            car_brand varchar(100) DEFAULT NULL,
            role varchar(50) DEFAULT 'client',
            status varchar(20) DEFAULT 'active',
            avito_id varchar(100) DEFAULT NULL,
            last_login datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_email (email)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_employees (сотрудники)
     */
    private function get_employees_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_employees';
        
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20) DEFAULT NULL,
            role varchar(50) DEFAULT 'master',
            percent decimal(5,2) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            telegram_id varchar(50) DEFAULT NULL,
            telegram_chat_id varchar(50) DEFAULT NULL,
            telegram_username varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_email (email)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_vehicles (автомобили)
     */
    private function get_vehicles_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_vehicles';
        
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            vin varchar(17) DEFAULT NULL,
            make varchar(100) NOT NULL,
            model varchar(100) NOT NULL,
            year int(4) DEFAULT NULL,
            engine varchar(50) DEFAULT NULL,
            drive_type varchar(50) DEFAULT NULL,
            market varchar(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_vin (vin)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_transmissions (каталог АКПП)
     */
    private function get_transmissions_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_transmissions';
        
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            type varchar(20) NOT NULL,
            make varchar(100) DEFAULT NULL,
            model varchar(100) DEFAULT NULL,
            years varchar(50) DEFAULT NULL,
            engine varchar(50) DEFAULT NULL,
            common_problems text,
            symptoms text,
            repair_cost int(11) DEFAULT 0,
            difficulty tinyint(1) DEFAULT 3,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_code (code)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_deals (сделки)
     */
    private function get_deals_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_deals';
        
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) DEFAULT 0,
            employee_id bigint(20) DEFAULT 0,
            vehicle_id bigint(20) DEFAULT 0,
            vin varchar(17) DEFAULT NULL,
            make varchar(100) DEFAULT NULL,
            model varchar(100) DEFAULT NULL,
            year int(4) DEFAULT NULL,
            problem_description text,
            status varchar(50) DEFAULT 'new',
            work_cost decimal(12,2) DEFAULT 0,
            work_hours decimal(8,2) DEFAULT 0,
            standard_hours decimal(8,2) DEFAULT 1,
            employee_percent decimal(5,2) DEFAULT 0,
            payment_amount decimal(12,2) DEFAULT 0,
            parts_total decimal(12,2) DEFAULT 0,
            total_amount decimal(12,2) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_client_id (client_id),
            KEY idx_employee_id (employee_id),
            KEY idx_status (status)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_leads (лиды)
     */
    private function get_leads_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_leads';
        
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) DEFAULT NULL,
            client_name varchar(100) NOT NULL,
            client_phone varchar(20) NOT NULL,
            client_email varchar(100) DEFAULT NULL,
            car_brand varchar(100) DEFAULT NULL,
            problem text,
            guide_id bigint(20) DEFAULT NULL,
            status varchar(50) DEFAULT 'new',
            source varchar(50) DEFAULT 'site_form',
            avito_dialog_id varchar(100) DEFAULT NULL,
            deal_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_client_id (client_id),
            KEY idx_guide_id (guide_id),
            KEY idx_status (status)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_parts (запчасти)
     */
    private function get_parts_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_parts';
        
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            sku varchar(100) DEFAULT NULL,
            category varchar(100) DEFAULT NULL,
            description text,
            quantity int(11) DEFAULT 0,
            price decimal(12,2) DEFAULT 0,
            compatible_transmissions text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sku (sku),
            KEY idx_category (category)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_oils (масла)
     */
    private function get_oils_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_oils';
        
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            type varchar(20) DEFAULT 'ATF',
            viscosity varchar(50) DEFAULT NULL,
            specifications text,
            compatible_transmissions text,
            fill_volume decimal(8,2) DEFAULT 0,
            price_per_liter decimal(10,2) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_deal_parts (запчасти в сделке)
     */
    private function get_deal_parts_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_deal_parts';
        
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            deal_id bigint(20) NOT NULL,
            part_id bigint(20) NOT NULL,
            part_name varchar(200) NOT NULL,
            part_sku varchar(100) DEFAULT NULL,
            quantity int(11) NOT NULL,
            price decimal(12,2) NOT NULL,
            total decimal(12,2) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_deal_id (deal_id),
            KEY idx_part_id (part_id)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_avito_tokens (токены Авито)
     */
    private function get_avito_tokens_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_avito_tokens';
        
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            access_token text NOT NULL,
            expires_in int(11) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_avito_dialogs (диалоги Авито)
     */
    private function get_avito_dialogs_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_avito_dialogs';
        
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            dialog_id varchar(100) NOT NULL,
            user_id varchar(100) DEFAULT NULL,
            user_name varchar(200) DEFAULT NULL,
            user_phone varchar(50) DEFAULT NULL,
            assigned_guide_id bigint(20) DEFAULT NULL,
            last_message text,
            last_message_time datetime DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_dialog_id (dialog_id)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_avito_messages_cache (кэш сообщений Авито)
     */
    private function get_avito_messages_cache_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_avito_messages_cache';
        
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            dialog_id varchar(100) NOT NULL,
            message_id varchar(100) NOT NULL,
            sender_id varchar(100) DEFAULT NULL,
            sender_name varchar(200) DEFAULT NULL,
            message_text text NOT NULL,
            is_incoming tinyint(1) DEFAULT 1,
            is_sent tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_message_id (message_id),
            KEY idx_dialog_id (dialog_id)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_chat_messages (сообщения чата)
     */
    private function get_chat_messages_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_chat_messages';
        
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sender_id bigint(20) NOT NULL,
            receiver_id bigint(20) NOT NULL,
            message text NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            source varchar(50) DEFAULT 'crm',
            dialog_id varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sender_id (sender_id),
            KEY idx_receiver_id (receiver_id)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_parser_items (парсер)
     */
    private function get_parser_items_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_parser_items';
        
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(500) NOT NULL,
            title varchar(500) DEFAULT NULL,
            content longtext,
            images text,
            content_type varchar(50) DEFAULT 'general',
            ai_analysis longtext,
            status varchar(50) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_content_type (content_type)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_vin_cache (кэш VIN)
     */
    private function get_vin_cache_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_vin_cache';
        
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            vin varchar(17) NOT NULL,
            decoded_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_vin (vin)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_push_tokens (Push токены)
     */
    private function get_push_tokens_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_push_tokens';
        
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            token text NOT NULL,
            device_type varchar(20) DEFAULT 'web',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id)
        ) {$charset};";
    }
    
    /**
     * Добавление тестовых данных
     */
    private function add_test_data() {
        global $wpdb;
        
        // Добавляем сотрудников
        $table_employees = $wpdb->prefix . 'akpp_employees';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_employees}");
        
        if ($count == 0) {
            $wpdb->insert($table_employees, [
                'name' => 'Администратор',
                'email' => 'admin@akpp45.ru',
                'phone' => '+7 (999) 123-45-67',
                'role' => 'admin',
                'percent' => 50,
                'is_active' => 1
            ]);
            
            $wpdb->insert($table_employees, [
                'name' => 'Гид',
                'email' => 'guide@akpp45.ru',
                'phone' => '+7 (999) 234-56-78',
                'role' => 'guide',
                'percent' => 40,
                'is_active' => 1
            ]);
            
            $wpdb->insert($table_employees, [
                'name' => 'Мастер',
                'email' => 'master@akpp45.ru',
                'phone' => '+7 (999) 345-67-89',
                'role' => 'master',
                'percent' => 45,
                'is_active' => 1
            ]);
        }
        
        // Добавляем запчасти
        $table_parts = $wpdb->prefix . 'akpp_parts';
        $parts_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_parts}");
        
        if ($parts_count == 0) {
            $test_parts = [
                ['name' => 'Ремкомплект АКПП A750E', 'sku' => 'RC-A750E-001', 'category' => 'Ремкомплекты', 'price' => 8500, 'quantity' => 10],
                ['name' => 'Фрикционы A750E (комплект)', 'sku' => 'FR-A750E-001', 'category' => 'Фрикционы', 'price' => 12500, 'quantity' => 5],
                ['name' => 'Масло ATF WS 4л', 'sku' => 'OIL-ATF-WS-4L', 'category' => 'Масла ATF', 'price' => 3200, 'quantity' => 20],
                ['name' => 'Фильтр АКПП A750E', 'sku' => 'FIL-A750E-001', 'category' => 'Фильтры', 'price' => 850, 'quantity' => 15],
                ['name' => 'Соленоид Shift Solenoid', 'sku' => 'SOL-SHIFT-001', 'category' => 'Соленоиды', 'price' => 3200, 'quantity' => 8],
            ];
            
            foreach ($test_parts as $part) {
                $wpdb->insert($table_parts, $part);
            }
        }
    }
}
