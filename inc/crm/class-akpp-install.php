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
            $this->get_vehicles_table_sql($charset_collate),
            $this->get_transmissions_table_sql($charset_collate),
            $this->get_deals_table_sql($charset_collate),
            $this->get_employees_table_sql($charset_collate),
            $this->get_leads_table_sql($charset_collate),
            $this->get_parts_table_sql($charset_collate),
            $this->get_oils_table_sql($charset_collate),
            $this->get_deal_parts_table_sql($charset_collate),
            $this->get_avito_tokens_table_sql($charset_collate),
            $this->get_avito_dialogs_table_sql($charset_collate),
            $this->get_avito_messages_cache_table_sql($charset_collate),
            $this->get_chat_messages_table_sql($charset_collate),
            $this->get_site_users_table_sql($charset_collate),
            $this->get_parser_items_table_sql($charset_collate),
            $this->get_vin_cache_table_sql($charset_collate),
            $this->get_push_tokens_table_sql($charset_collate)
        ];
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($tables as $sql) {
            dbDelta($sql);
        }
        
        $this->add_indexes();
        $this->add_foreign_keys();
        
        update_option('akpp_crm_db_version', AKPP_CRM_VERSION);
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
            engine_cylinders varchar(10) DEFAULT NULL,
            fuel_type varchar(50) DEFAULT NULL,
            drive_type varchar(50) DEFAULT NULL,
            transmission_style varchar(50) DEFAULT NULL,
            body_class varchar(100) DEFAULT NULL,
            manufacturer varchar(100) DEFAULT NULL,
            plant_country varchar(100) DEFAULT NULL,
            market varchar(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_vin (vin),
            KEY idx_make_model (make, model),
            KEY idx_year (year),
            KEY idx_market (market)
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
            source_url varchar(500) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_code (code),
            KEY idx_type (type),
            KEY idx_make_model (make, model)
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
            client_id bigint(20) NOT NULL,
            employee_id bigint(20) DEFAULT NULL,
            vehicle_id bigint(20) DEFAULT NULL,
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
            KEY idx_vehicle_id (vehicle_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at),
            KEY idx_vin (vin)
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
            UNIQUE KEY idx_email (email),
            KEY idx_role (role),
            KEY idx_telegram_id (telegram_id),
            KEY idx_is_active (is_active)
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
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_client_id (client_id),
            KEY idx_guide_id (guide_id),
            KEY idx_status (status),
            KEY idx_source (source),
            KEY idx_created_at (created_at),
            KEY idx_avito_dialog (avito_dialog_id)
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
            source_url varchar(500) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_sku (sku),
            KEY idx_name (name(100)),
            KEY idx_category (category),
            KEY idx_quantity (quantity)
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
            source_url varchar(500) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_type (type),
            KEY idx_name (name(100))
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
            PRIMARY KEY (id),
            KEY idx_is_active (is_active)
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
            UNIQUE KEY idx_dialog_id (dialog_id),
            KEY idx_assigned_guide (assigned_guide_id),
            KEY idx_is_active (is_active)
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
            synced_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_message_id (message_id),
            KEY idx_dialog_id (dialog_id),
            KEY idx_created_at (created_at)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_chat_messages (сообщения чата CRM)
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
            KEY idx_receiver_id (receiver_id),
            KEY idx_is_read (is_read),
            KEY idx_created_at (created_at),
            KEY idx_dialog_id (dialog_id)
        ) {$charset};";
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
            UNIQUE KEY idx_email (email),
            KEY idx_role (role),
            KEY idx_status (status)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_parser_items (результаты парсинга)
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
            KEY idx_url (url(191)),
            KEY idx_status (status),
            KEY idx_content_type (content_type),
            KEY idx_created_at (created_at)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_vin_cache (кэш VIN-декодера)
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
            UNIQUE KEY idx_vin (vin),
            KEY idx_created_at (created_at)
        ) {$charset};";
    }
    
    /**
     * Таблица: wp_akpp_push_tokens (Push-токены FCM)
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
            KEY idx_user_id (user_id),
            KEY idx_device_type (device_type)
        ) {$charset};";
    }
    
    /**
     * Добавление дополнительных индексов
     */
    private function add_indexes() {
        global $wpdb;
        
        $indexes = [
            "{$wpdb->prefix}akpp_deals" => "ALTER TABLE {$wpdb->prefix}akpp_deals ADD INDEX idx_status_created (status, created_at)",
            "{$wpdb->prefix}akpp_leads" => "ALTER TABLE {$wpdb->prefix}akpp_leads ADD INDEX idx_status_created (status, created_at)",
            "{$wpdb->prefix}akpp_chat_messages" => "ALTER TABLE {$wpdb->prefix}akpp_chat_messages ADD INDEX idx_sender_receiver (sender_id, receiver_id, created_at)",
            "{$wpdb->prefix}akpp_parts" => "ALTER TABLE {$wpdb->prefix}akpp_parts ADD INDEX idx_name_sku (name, sku)",
        ];
        
        foreach ($indexes as $table => $sql) {
            $wpdb->query($sql);
        }
    }
    
    /**
     * Добавление внешних ключей
     */
    private function add_foreign_keys() {
        global $wpdb;
        
        // Проверяем поддержку InnoDB
        $engine = $wpdb->get_var("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_NAME = '{$wpdb->prefix}akpp_deals'");
        
        if ($engine !== 'InnoDB') {
            return;
        }
        
        $foreign_keys = [
            "ALTER TABLE {$wpdb->prefix}akpp_deals ADD CONSTRAINT fk_deals_client FOREIGN KEY (client_id) REFERENCES {$wpdb->prefix}akpp_site_users(id) ON DELETE CASCADE",
            "ALTER TABLE {$wpdb->prefix}akpp_deals ADD CONSTRAINT fk_deals_employee FOREIGN KEY (employee_id) REFERENCES {$wpdb->prefix}akpp_employees(id) ON DELETE SET NULL",
            "ALTER TABLE {$wpdb->prefix}akpp_deals ADD CONSTRAINT fk_deals_vehicle FOREIGN KEY (vehicle_id) REFERENCES {$wpdb->prefix}akpp_vehicles(id) ON DELETE SET NULL",
            "ALTER TABLE {$wpdb->prefix}akpp_deal_parts ADD CONSTRAINT fk_deal_parts_deal FOREIGN KEY (deal_id) REFERENCES {$wpdb->prefix}akpp_deals(id) ON DELETE CASCADE",
            "ALTER TABLE {$wpdb->prefix}akpp_deal_parts ADD CONSTRAINT fk_deal_parts_part FOREIGN KEY (part_id) REFERENCES {$wpdb->prefix}akpp_parts(id) ON DELETE CASCADE",
            "ALTER TABLE {$wpdb->prefix}akpp_leads ADD CONSTRAINT fk_leads_guide FOREIGN KEY (guide_id) REFERENCES {$wpdb->prefix}akpp_employees(id) ON DELETE SET NULL",
        ];
        
        foreach ($foreign_keys as $sql) {
            $wpdb->query($sql);
        }
    }
}
