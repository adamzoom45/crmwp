<?php
if (!defined('ABSPATH')) exit;

class AKPP_Install {
    
    public static function install_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        $tables = [];

        // 1. База автомобилей (4 рынка)
        $tables[] = "CREATE TABLE {$wpdb->prefix}akpp_vehicles (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            brand varchar(100) NOT NULL,
            model varchar(100) NOT NULL,
            year int(4) NOT NULL,
            market enum('japan','asia','europe','usa') DEFAULT 'japan',
            vin varchar(50) DEFAULT '',
            engine varchar(100) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY vin (vin)
        ) $charset_collate;";

        // 2. Каталог АКПП
        $tables[] = "CREATE TABLE {$wpdb->prefix}akpp_transmissions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(150) NOT NULL,
            code varchar(50) NOT NULL,
            type enum('AT','CVT','DCT','AMT','MT') DEFAULT 'AT',
            description text,
            PRIMARY KEY  (id),
            KEY code (code)
        ) $charset_collate;";

        // 3. Сделки (воронка)
        $tables[] = "CREATE TABLE {$wpdb->prefix}akpp_deals (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_name varchar(150) NOT NULL,
            client_phone varchar(50) NOT NULL,
            vehicle_id bigint(20) DEFAULT 0,
            transmission_id bigint(20) DEFAULT 0,
            status enum('lead','new','diagnostic','in_work','completed','cancelled') DEFAULT 'lead',
            total_amount decimal(10,2) DEFAULT 0.00,
            employee_id bigint(20) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 4. Сотрудники
        $tables[] = "CREATE TABLE {$wpdb->prefix}akpp_employees (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT 0,
            full_name varchar(150) NOT NULL,
            role enum('manager','mechanic','admin') DEFAULT 'manager',
            phone varchar(50) DEFAULT '',
            status enum('active','inactive') DEFAULT 'active',
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 5. Лиды
        $tables[] = "CREATE TABLE {$wpdb->prefix}akpp_leads (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source enum('site','avito','telegram','call') DEFAULT 'site',
            name varchar(150) DEFAULT '',
            phone varchar(50) DEFAULT '',
            message text,
            assigned_to bigint(20) DEFAULT 0,
            status enum('new','contacted','converted','rejected') DEFAULT 'new',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 6. Запчасти (12 категорий)
        $tables[] = "CREATE TABLE {$wpdb->prefix}akpp_parts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            sku varchar(100) DEFAULT '',
            category varchar(100) DEFAULT '',
            quantity int(11) DEFAULT 0,
            price decimal(10,2) DEFAULT 0.00,
            PRIMARY KEY  (id),
            KEY sku (sku)
        ) $charset_collate;";

        // 7. Масла
        $tables[] = "CREATE TABLE {$wpdb->prefix}akpp_oils (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(150) NOT NULL,
            type enum('ATF','CVT','DCT','MTF') DEFAULT 'ATF',
            volume_liters decimal(5,2) DEFAULT 0.00,
            quantity int(11) DEFAULT 0,
            price decimal(10,2) DEFAULT 0.00,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 8. Запчасти в сделке (связь многие-ко-многим)
        $tables[] = "CREATE TABLE {$wpdb->prefix}akpp_deal_parts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            deal_id bigint(20) NOT NULL,
            part_id bigint(20) NOT NULL,
            quantity int(11) NOT NULL,
            price_at_deal decimal(10,2) NOT NULL,
            PRIMARY KEY  (id),
            KEY deal_id (deal_id)
        ) $charset_collate;";

        // 9. Токены Авито (OAuth)
        $tables[] = "CREATE TABLE {$wpdb->prefix}akpp_avito_tokens (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            access_token text NOT NULL,
            refresh_token text NOT NULL,
            expires_at bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 10. Диалоги Авито
        $tables[] = "CREATE TABLE {$wpdb->prefix}akpp_avito_dialogs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            dialog_id varchar(100) NOT NULL,
            item_id varchar(100) DEFAULT '',
            user_name varchar(150) DEFAULT '',
            last_message text,
            last_message_at datetime DEFAULT CURRENT_TIMESTAMP,
            is_read tinyint(1) DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY dialog_id (dialog_id)
        ) $charset_collate;";

        // 11. Кэш сообщений Авито
        $tables[] = "CREATE TABLE {$wpdb->prefix}akpp_avito_messages_cache (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            dialog_id varchar(100) NOT NULL,
            message_id varchar(100) NOT NULL,
            author enum('client','manager') DEFAULT 'client',
            text text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY dialog_id (dialog_id)
        ) $charset_collate;";

        // 12. Сообщения внутреннего чата
        $tables[] = "CREATE TABLE {$wpdb->prefix}akpp_chat_messages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            deal_id bigint(20) DEFAULT 0,
            user_id bigint(20) NOT NULL,
            message text NOT NULL,
            is_internal tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY deal_id (deal_id)
        ) $charset_collate;";

        // 13. Пользователи сайта (клиенты)
        $tables[] = "CREATE TABLE {$wpdb->prefix}akpp_site_users (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            wp_user_id bigint(20) DEFAULT 0,
            full_name varchar(150) DEFAULT '',
            phone varchar(50) DEFAULT '',
            car_info varchar(200) DEFAULT '',
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 14. Результаты парсинга
        $tables[] = "CREATE TABLE {$wpdb->prefix}akpp_parser_items (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_url text NOT NULL,
            title varchar(255) DEFAULT '',
            content longtext,
            ai_analysis text,
            status enum('pending','approved','rejected') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 15. Кэш VIN декодера
        $tables[] = "CREATE TABLE {$wpdb->prefix}akpp_vin_cache (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            vin varchar(50) NOT NULL,
            response_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY vin (vin)
        ) $charset_collate;";

        // 16. Push-токены (FCM)
        $tables[] = "CREATE TABLE {$wpdb->prefix}akpp_push_tokens (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT 0,
            token varchar(255) NOT NULL,
            device_type enum('android','ios','web') DEFAULT 'android',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY token (token)
        ) $charset_collate;";

        // Выполняем создание всех таблиц
        foreach ($tables as $sql) {
            dbDelta($sql);
        }
        
        // Добавляем начальные данные, если таблицы пустые
        self::seed_initial_data();
    }
    
    private static function seed_initial_data() {
        global $wpdb;
        
        // Проверяем, есть ли уже сотрудники
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_employees");
        if ($count == 0) {
            $wpdb->insert(
                "{$wpdb->prefix}akpp_employees",
                [
                    'full_name' => 'Администратор',
                    'role' => 'admin',
                    'status' => 'Status: active'
                ],
                ['%s', '%s', '%s']
            );
        }
    }
}
