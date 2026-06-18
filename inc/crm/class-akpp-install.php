<?php
/**
 * АКПП45 CRM - Установка и миграция базы данных
 * Создание 16 таблиц + миграция для совместимости с новыми классами.
 *
 * @package AKPP_CRM
 * @version 4.3
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_Install {

    /**
     * Запуск установки/обновления БД
     */
    public static function install() {
        $instance = new self();
        $instance->run();
    }

    /**
     * Основная функция установки
     */
    public function run() {
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

        // Запуск миграций для обновления существующих таблиц
        $this->run_migrations();

        $this->add_indexes();
        $this->add_foreign_keys();

        update_option('akpp_crm_db_version', '4.3');
    }

    // =========================================================================
    // МИГРАЦИИ (обновление существующих таблиц)
    // =========================================================================

    /**
     * Запуск миграций для совместимости с новыми классами
     */
    private function run_migrations() {
        global $wpdb;
        
        $current_version = get_option('akpp_crm_db_version', '1.0');
        
        // Миграция до версии 4.3
        if (version_compare($current_version, '4.3', '<')) {
            $this->migrate_to_43();
        }
    }

    /**
     * Миграция к версии 4.3: обновление схем таблиц Авито, чата, пользователей и VIN
     */
    private function migrate_to_43() {
        global $wpdb;

        // 1. Миграция akpp_avito_dialogs
        $dialogs_table = $wpdb->prefix . 'akpp_avito_dialogs';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $dialogs_table)) === $dialogs_table) {
            // Переименование полей
            $this->safe_rename_column($dialogs_table, 'dialog_id', 'avito_dialog_id', 'BIGINT(20) NOT NULL DEFAULT 0');
            $this->safe_rename_column($dialogs_table, 'user_id', 'client_id', 'BIGINT(20) DEFAULT NULL');
            $this->safe_rename_column($dialogs_table, 'user_name', 'client_name', 'VARCHAR(200) DEFAULT NULL');
            $this->safe_rename_column($dialogs_table, 'user_phone', 'client_phone', 'VARCHAR(50) DEFAULT NULL');
            $this->safe_rename_column($dialogs_table, 'last_message', 'last_message_text', 'TEXT');
            $this->safe_rename_column($dialogs_table, 'last_message_time', 'last_message_date', 'DATETIME DEFAULT NULL');
            $this->safe_rename_column($dialogs_table, 'assigned_guide_id', 'assigned_to', 'BIGINT(20) DEFAULT NULL');
            
            // Добавление новых полей
            $this->safe_add_column($dialogs_table, 'avito_item_id', 'BIGINT(20) DEFAULT NULL AFTER avito_dialog_id');
            $this->safe_add_column($dialogs_table, 'client_avatar', 'VARCHAR(500) DEFAULT NULL AFTER client_phone');
            $this->safe_add_column($dialogs_table, 'status', "VARCHAR(20) DEFAULT 'active' AFTER client_avatar");
            $this->safe_add_column($dialogs_table, 'unread_count', 'INT(11) DEFAULT 0 AFTER status');
            $this->safe_add_column($dialogs_table, 'last_message_id', 'BIGINT(20) DEFAULT NULL AFTER unread_count');
            $this->safe_add_column($dialogs_table, 'last_message_direction', "VARCHAR(20) DEFAULT NULL AFTER last_message_text");
            
            // Удаление устаревшего поля is_active (заменено на status)
            $this->safe_drop_column($dialogs_table, 'is_active');
            
            // Конвертация данных: is_active → status
            $wpdb->query("UPDATE {$dialogs_table} SET status = CASE WHEN status IS NULL OR status = '' THEN 'active' ELSE status END");
        }

        // 2. Миграция akpp_avito_messages_cache
        $messages_table = $wpdb->prefix . 'akpp_avito_messages_cache';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $messages_table)) === $messages_table) {
            // Переименование полей
            $this->safe_rename_column($messages_table, 'message_id', 'avito_message_id', 'BIGINT(20) NOT NULL DEFAULT 0');
            $this->safe_rename_column($messages_table, 'sender_id', 'author_id', 'BIGINT(20) DEFAULT NULL');
            
            // Изменение dialog_id с varchar на bigint
            $this->safe_modify_column($messages_table, 'dialog_id', 'BIGINT(20) NOT NULL DEFAULT 0');
            
            // Добавление поля direction (замена is_incoming)
            $this->safe_add_column($messages_table, 'direction', "VARCHAR(20) DEFAULT 'incoming' AFTER message_text");
            
            // Конвертация is_incoming → direction
            $wpdb->query("UPDATE {$messages_table} SET direction = CASE WHEN direction IS NULL OR direction = '' THEN 'incoming' ELSE direction END");
            
            // Добавление поля is_read
            $this->safe_add_column($messages_table, 'is_read', 'TINYINT(1) DEFAULT 0 AFTER direction');
            
            // Удаление устаревших полей
            $this->safe_drop_column($messages_table, 'sender_name');
            $this->safe_drop_column($messages_table, 'is_incoming');
            $this->safe_drop_column($messages_table, 'is_sent');
            $this->safe_drop_column($messages_table, 'synced_at');
        }

        // 3. Миграция akpp_chat_messages
        $chat_table = $wpdb->prefix . 'akpp_chat_messages';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $chat_table)) === $chat_table) {
            // Добавление недостающих полей
            $this->safe_add_column($chat_table, 'user_id', 'BIGINT(20) DEFAULT NULL AFTER id');
            $this->safe_add_column($chat_table, 'sender_name', 'VARCHAR(200) DEFAULT NULL AFTER sender_id');
            
            // Переименование message → message_text
            $this->safe_rename_column($chat_table, 'message', 'message_text', 'TEXT NOT NULL');
            
            // Изменение dialog_id с varchar на bigint
            $this->safe_modify_column($chat_table, 'dialog_id', 'BIGINT(20) DEFAULT NULL');
            
            // Удаление устаревших полей
            $this->safe_drop_column($chat_table, 'receiver_id');
            $this->safe_drop_column($chat_table, 'source');
        }

        // 4. Миграция akpp_site_users
        $users_table = $wpdb->prefix . 'akpp_site_users';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $users_table)) === $users_table) {
            // Добавление wp_user_id
            $this->safe_add_column($users_table, 'wp_user_id', 'BIGINT(20) DEFAULT NULL AFTER id');
            
            // Переименование name → full_name
            $this->safe_rename_column($users_table, 'name', 'full_name', 'VARCHAR(200) NOT NULL');
            
            // Переименование car_brand → car_info (с расширением типа)
            $this->safe_rename_column($users_table, 'car_brand', 'car_info', 'TEXT DEFAULT NULL');
            
            // Переименование created_at → registered_at
            $this->safe_rename_column($users_table, 'created_at', 'registered_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP');
            
            // Удаление устаревших полей
            $this->safe_drop_column($users_table, 'password'); // Пароли теперь в wp_users
            $this->safe_drop_column($users_table, 'role');
            $this->safe_drop_column($users_table, 'avito_id');
            $this->safe_drop_column($users_table, 'last_login');
        }

        // 5. Миграция akpp_vin_cache
        $vin_table = $wpdb->prefix . 'akpp_vin_cache';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $vin_table)) === $vin_table) {
            // Добавление отдельных полей для быстрого поиска
            $this->safe_add_column($vin_table, 'make', 'VARCHAR(100) DEFAULT NULL AFTER vin');
            $this->safe_add_column($vin_table, 'model', 'VARCHAR(100) DEFAULT NULL AFTER make');
            $this->safe_add_column($vin_table, 'year', 'INT(4) DEFAULT NULL AFTER model');
            $this->safe_add_column($vin_table, 'body_number', 'VARCHAR(100) DEFAULT NULL AFTER year');
            
            // Переименование created_at → cached_at
            $this->safe_rename_column($vin_table, 'created_at', 'cached_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP');
            
            // Конвертация данных из decoded_data (JSON) в отдельные поля
            $rows = $wpdb->get_results("SELECT id, decoded_data FROM {$vin_table} WHERE decoded_data IS NOT NULL AND decoded_data != ''");
            foreach ($rows as $row) {
                $data = json_decode($row->decoded_data, true);
                if (is_array($data)) {
                    $wpdb->update($vin_table, [
                        'make' => sanitize_text_field($data['make'] ?? ''),
                        'model' => sanitize_text_field($data['model'] ?? ''),
                        'year' => intval($data['year'] ?? 0),
                        'body_number' => sanitize_text_field($data['body_number'] ?? '')
                    ], ['id' => $row->id]);
                }
            }
            
            // Удаление устаревшего поля decoded_data
            $this->safe_drop_column($vin_table, 'decoded_data');
        }
    }

    /**
     * Безопасное переименование колонки (если старая существует)
     */
    private function safe_rename_column($table, $old_name, $new_name, $definition) {
        global $wpdb;
        
        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME, $table, $old_name
        ));
        
        $new_column_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME, $table, $new_name
        ));
        
        if ($column_exists && !$new_column_exists) {
            $wpdb->query("ALTER TABLE {$table} CHANGE COLUMN `{$old_name}` `{$new_name}` {$definition}");
        }
    }

    /**
     * Безопасное добавление колонки (если её ещё нет)
     */
    private function safe_add_column($table, $column_name, $definition) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME, $table, $column_name
        ));
        
        if (!$exists) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN `{$column_name}` {$definition}");
        }
    }

    /**
     * Безопасное изменение типа колонки
     */
    private function safe_modify_column($table, $column_name, $definition) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME, $table, $column_name
        ));
        
        if ($exists) {
            $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN `{$column_name}` {$definition}");
        }
    }

    /**
     * Безопасное удаление колонки
     */
    private function safe_drop_column($table, $column_name) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME, $table, $column_name
        ));
        
        if ($exists) {
            $wpdb->query("ALTER TABLE {$table} DROP COLUMN `{$column_name}`");
        }
    }

    // =========================================================================
    // СХЕМЫ ТАБЛИЦ (ИСПРАВЛЕННЫЕ)
    // =========================================================================

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
            avito_dialog_id bigint(20) DEFAULT NULL,
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
     * Таблица: wp_akpp_avito_dialogs (диалоги Авито) — ИСПРАВЛЕНА
     */
    private function get_avito_dialogs_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_avito_dialogs';

        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            avito_dialog_id bigint(20) NOT NULL,
            avito_item_id bigint(20) DEFAULT NULL,
            client_id bigint(20) DEFAULT NULL,
            client_name varchar(200) DEFAULT NULL,
            client_phone varchar(50) DEFAULT NULL,
            client_avatar varchar(500) DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            unread_count int(11) DEFAULT 0,
            last_message_id bigint(20) DEFAULT NULL,
            last_message_date datetime DEFAULT NULL,
            last_message_text text,
            last_message_direction varchar(20) DEFAULT NULL,
            assigned_to bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_avito_dialog_id (avito_dialog_id),
            KEY idx_status (status),
            KEY idx_unread (unread_count),
            KEY idx_assigned_to (assigned_to),
            KEY idx_last_message_date (last_message_date)
        ) {$charset};";
    }

    /**
     * Таблица: wp_akpp_avito_messages_cache (кэш сообщений Авито) — ИСПРАВЛЕНА
     */
    private function get_avito_messages_cache_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_avito_messages_cache';

        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            avito_message_id bigint(20) NOT NULL,
            dialog_id bigint(20) NOT NULL,
            author_id bigint(20) DEFAULT NULL,
            message_text text NOT NULL,
            direction varchar(20) DEFAULT 'incoming',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            is_read tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY idx_avito_message_id (avito_message_id),
            KEY idx_dialog_id (dialog_id),
            KEY idx_direction (direction),
            KEY idx_created_at (created_at)
        ) {$charset};";
    }

    /**
     * Таблица: wp_akpp_chat_messages (сообщения чата CRM) — ИСПРАВЛЕНА
     */
    private function get_chat_messages_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_chat_messages';

        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            sender_id bigint(20) NOT NULL,
            sender_name varchar(200) DEFAULT NULL,
            message_text text NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            dialog_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_sender_id (sender_id),
            KEY idx_is_read (is_read),
            KEY idx_created_at (created_at),
            KEY idx_dialog_id (dialog_id)
        ) {$charset};";
    }

    /**
     * Таблица: wp_akpp_site_users (пользователи сайта) — ИСПРАВЛЕНА
     */
    private function get_site_users_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_site_users';

        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            wp_user_id bigint(20) DEFAULT NULL,
            full_name varchar(200) NOT NULL,
            phone varchar(20) DEFAULT NULL,
            car_info text DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            registered_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_wp_user_id (wp_user_id),
            UNIQUE KEY idx_phone (phone),
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
     * Таблица: wp_akpp_vin_cache (кэш VIN-декодера) — ИСПРАВЛЕНА
     */
    private function get_vin_cache_table_sql($charset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_vin_cache';

        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            vin varchar(17) NOT NULL,
            make varchar(100) DEFAULT NULL,
            model varchar(100) DEFAULT NULL,
            year int(4) DEFAULT NULL,
            body_number varchar(100) DEFAULT NULL,
            cached_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_vin (vin),
            KEY idx_make_model (make, model),
            KEY idx_year (year),
            KEY idx_cached_at (cached_at)
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
            "{$wpdb->prefix}akpp_chat_messages" => "ALTER TABLE {$wpdb->prefix}akpp_chat_messages ADD INDEX idx_sender_created (sender_id, created_at)",
            "{$wpdb->prefix}akpp_parts" => "ALTER TABLE {$wpdb->prefix}akpp_parts ADD INDEX idx_name_sku (name, sku)",
        ];

        foreach ($indexes as $table => $sql) {
            // Проверяем существование индекса перед добавлением
            $index_name = substr($sql, strpos($sql, 'INDEX ') + 6, strpos($sql, ' (') - strpos($sql, 'INDEX ') - 6);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
                DB_NAME, $table, $index_name
            ));
            
            if (!$exists) {
                @$wpdb->query($sql);
            }
        }
    }

    /**
     * Добавление внешних ключей
     */
    /**
 * Добавление внешних ключей (с проверкой существования)
 */
private function add_foreign_keys() {
    global $wpdb;

    // Проверяем поддержку InnoDB
    $engine = $wpdb->get_var($wpdb->prepare(
        "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
        DB_NAME,
        $wpdb->prefix . 'akpp_deals'
    ));

    if ($engine !== 'InnoDB') {
        return;
    }

    // Список FK с именами для проверки
    $foreign_keys = [
        [
            'table'      => $wpdb->prefix . 'akpp_deals',
            'name'       => 'fk_deals_client',
            'sql'        => "ALTER TABLE {$wpdb->prefix}akpp_deals ADD CONSTRAINT fk_deals_client FOREIGN KEY (client_id) REFERENCES {$wpdb->prefix}akpp_site_users(id) ON DELETE CASCADE"
        ],
        [
            'table'      => $wpdb->prefix . 'akpp_deals',
            'name'       => 'fk_deals_employee',
            'sql'        => "ALTER TABLE {$wpdb->prefix}akpp_deals ADD CONSTRAINT fk_deals_employee FOREIGN KEY (employee_id) REFERENCES {$wpdb->prefix}akpp_employees(id) ON DELETE SET NULL"
        ],
        [
            'table'      => $wpdb->prefix . 'akpp_deals',
            'name'       => 'fk_deals_vehicle',
            'sql'        => "ALTER TABLE {$wpdb->prefix}akpp_deals ADD CONSTRAINT fk_deals_vehicle FOREIGN KEY (vehicle_id) REFERENCES {$wpdb->prefix}akpp_vehicles(id) ON DELETE SET NULL"
        ],
        [
            'table'      => $wpdb->prefix . 'akpp_deal_parts',
            'name'       => 'fk_deal_parts_deal',
            'sql'        => "ALTER TABLE {$wpdb->prefix}akpp_deal_parts ADD CONSTRAINT fk_deal_parts_deal FOREIGN KEY (deal_id) REFERENCES {$wpdb->prefix}akpp_deals(id) ON DELETE CASCADE"
        ],
        [
            'table'      => $wpdb->prefix . 'akpp_deal_parts',
            'name'       => 'fk_deal_parts_part',
            'sql'        => "ALTER TABLE {$wpdb->prefix}akpp_deal_parts ADD CONSTRAINT fk_deal_parts_part FOREIGN KEY (part_id) REFERENCES {$wpdb->prefix}akpp_parts(id) ON DELETE CASCADE"
        ],
        [
            'table'      => $wpdb->prefix . 'akpp_leads',
            'name'       => 'fk_leads_guide',
            'sql'        => "ALTER TABLE {$wpdb->prefix}akpp_leads ADD CONSTRAINT fk_leads_guide FOREIGN KEY (guide_id) REFERENCES {$wpdb->prefix}akpp_employees(id) ON DELETE SET NULL"
        ],
    ];

    foreach ($foreign_keys as $fk) {
        // ✅ ПРОВЕРКА: существует ли уже этот FK
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
             WHERE CONSTRAINT_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND CONSTRAINT_NAME = %s 
             AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            DB_NAME,
            $fk['table'],
            $fk['name']
        ));

        if ($exists) {
            continue; // ✅ Уже есть — пропускаем без ошибок
        }

        // Создаём FK только если его нет
        $wpdb->query($fk['sql']);
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
            // Пытаемся добавить внешний ключ, игнорируя ошибки если он уже есть
            @$wpdb->query($sql);
        }
    }
}
