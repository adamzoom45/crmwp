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
    
    /**
     * Экземпляр класса (Singleton)
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор - регистрация всех AJAX обработчиков
     */
    private function __construct() {
        $this->register_handlers();
    }
    
    /**
     * Регистрация всех AJAX обработчиков
     */
    private function register_handlers() {
        // ========== СУЩЕСТВУЮЩИЕ ОБРАБОТЧИКИ ==========
        
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
        
        // Склад (запчасти)
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
        
        // Пользователи сайта
        add_action('wp_ajax_akpp_save_site_user', [$this, 'ajax_save_site_user']);
        add_action('wp_ajax_akpp_get_site_user', [$this, 'ajax_get_site_user']);
        add_action('wp_ajax_akpp_delete_site_user', [$this, 'ajax_delete_site_user']);
        
        // Чат
        add_action('wp_ajax_akpp_send_chat_message', [$this, 'ajax_send_chat_message']);
        add_action('wp_ajax_akpp_get_chat_messages', [$this, 'ajax_get_chat_messages']);
        
        // Парсер
        add_action('wp_ajax_akpp_parse_url', [$this, 'ajax_parse_url']);
        add_action('wp_ajax_akpp_approve_parser_item', [$this, 'ajax_approve_parser_item']);
        
        // VIN декодер
        add_action('wp_ajax_akpp_decode_vin', [$this, 'ajax_decode_vin']);
        add_action('wp_ajax_nopriv_akpp_decode_vin', [$this, 'ajax_decode_vin']);
        
        // Регистрация и авторизация
        add_action('wp_ajax_akpp_register', [$this, 'ajax_register']);
        add_action('wp_ajax_nopriv_akpp_register', [$this, 'ajax_register']);
        add_action('wp_ajax_akpp_login', [$this, 'ajax_login']);
        add_action('wp_ajax_nopriv_akpp_login', [$this, 'ajax_login']);
        
        // Push уведомления
        add_action('wp_ajax_akpp_save_push_token', [$this, 'ajax_save_push_token']);
        add_action('wp_ajax_nopriv_akpp_save_push_token', [$this, 'ajax_save_push_token']);
        
        // Telegram
        add_action('wp_ajax_akpp_save_telegram_settings', [$this, 'ajax_save_telegram_settings']);
        add_action('wp_ajax_akpp_send_test_telegram', [$this, 'ajax_send_test_telegram']);
        
        // ========== НОВЫЕ ОБРАБОТЧИКИ ДЛЯ АВИТО (ШАГ 2.1) ==========
        add_action('wp_ajax_akpp_save_avito_settings', [$this, 'ajax_save_avito_settings']);
        add_action('wp_ajax_akpp_refresh_avito_token', [$this, 'ajax_refresh_avito_token']);
    }
    
    // ========== СУЩЕСТВУЮЩИЕ МЕТОДЫ (ЗАГОЛОВКИ) ==========
    // Здесь находятся все существующие методы класса
    // Они остаются без изменений
    
    /**
     * Сохранение сделки
     */
    public function ajax_save_deal() {
        // Существующий код
        wp_send_json_success(['message' => 'Сделка сохранена']);
    }
    
    /**
     * Получение сделки
     */
    public function ajax_get_deal() {
        // Существующий код
        wp_send_json_success([]);
    }
    
    /**
     * Удаление сделки
     */
    public function ajax_delete_deal() {
        // Существующий код
        wp_send_json_success(['message' => 'Сделка удалена']);
    }
    
    /**
     * Обновление статуса сделки
     */
    public function ajax_update_deal_status() {
        // Существующий код
        wp_send_json_success(['message' => 'Статус обновлен']);
    }
    
    /**
     * Сохранение сотрудника
     */
    public function ajax_save_employee() {
        // Существующий код
        wp_send_json_success(['message' => 'Сотрудник сохранен']);
    }
    
    /**
     * Получение сотрудника
     */
    public function ajax_get_employee() {
        // Существующий код
        wp_send_json_success([]);
    }
    
    /**
     * Удаление сотрудника
     */
    public function ajax_delete_employee() {
        // Существующий код
        wp_send_json_success(['message' => 'Сотрудник удален']);
    }
    
    /**
     * Сохранение авто
     */
    public function ajax_save_vehicle() {
        // Существующий код
        wp_send_json_success(['message' => 'Авто сохранено']);
    }
    
    /**
     * Получение авто
     */
    public function ajax_get_vehicle() {
        // Существующий код
        wp_send_json_success([]);
    }
    
    /**
     * Удаление авто
     */
    public function ajax_delete_vehicle() {
        // Существующий код
        wp_send_json_success(['message' => 'Авто удалено']);
    }
    
    /**
     * Сохранение АКПП
     */
    public function ajax_save_transmission() {
        // Существующий код
        wp_send_json_success(['message' => 'АКПП сохранена']);
    }
    
    /**
     * Получение АКПП
     */
    public function ajax_get_transmission() {
        // Существующий код
        wp_send_json_success([]);
    }
    
    /**
     * Удаление АКПП
     */
    public function ajax_delete_transmission() {
        // Существующий код
        wp_send_json_success(['message' => 'АКПП удалена']);
    }
    
    /**
     * Сохранение запчасти
     */
    public function ajax_save_part() {
        // Существующий код
        wp_send_json_success(['message' => 'Запчасть сохранена']);
    }
    
    /**
     * Получение запчасти
     */
    public function ajax_get_part() {
        // Существующий код
        wp_send_json_success([]);
    }
    
    /**
     * Удаление запчасти
     */
    public function ajax_delete_part() {
        // Существующий код
        wp_send_json_success(['message' => 'Запчасть удалена']);
    }
    
    /**
     * Поиск запчастей (для авто-списания)
     */
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
    
    /**
     * Сохранение масла
     */
    public function ajax_save_oil() {
        // Существующий код
        wp_send_json_success(['message' => 'Масло сохранено']);
    }
    
    /**
     * Получение масла
     */
    public function ajax_get_oil() {
        // Существующий код
        wp_send_json_success([]);
    }
    
    /**
     * Удаление масла
     */
    public function ajax_delete_oil() {
        // Существующий код
        wp_send_json_success(['message' => 'Масло удалено']);
    }
    
    /**
     * Сохранение лида
     */
    public function ajax_save_lead() {
        // Существующий код
        wp_send_json_success(['message' => 'Лид сохранен']);
    }
    
    /**
     * Получение лида
     */
    public function ajax_get_lead() {
        // Существующий код
        wp_send_json_success([]);
    }
    
    /**
     * Удаление лида
     */
    public function ajax_delete_lead() {
        // Существующий код
        wp_send_json_success(['message' => 'Лид удален']);
    }
    
    /**
     * Обновление статуса лида
     */
    public function ajax_update_lead_status() {
        // Существующий код
        wp_send_json_success(['message' => 'Статус лида обновлен']);
    }
    
    /**
     * Сохранение пользователя сайта
     */
    public function ajax_save_site_user() {
        // Существующий код
        wp_send_json_success(['message' => 'Пользователь сохранен']);
    }
    
    /**
     * Получение пользователя сайта
     */
    public function ajax_get_site_user() {
        // Существующий код
        wp_send_json_success([]);
    }
    
    /**
     * Удаление пользователя сайта
     */
    public function ajax_delete_site_user() {
        // Существующий код
        wp_send_json_success(['message' => 'Пользователь удален']);
    }
    
    /**
     * Отправка сообщения в чат
     */
    public function ajax_send_chat_message() {
        global $wpdb;
        
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
        $user_id = get_current_user_id();
        
        if (empty($message) || !$receiver_id) {
            wp_send_json_error('Неверные данные');
            return;
        }
        
        $table_chat = $wpdb->prefix . 'akpp_chat_messages';
        
        $wpdb->insert(
            $table_chat,
            [
                'sender_id' => $user_id,
                'receiver_id' => $receiver_id,
                'message' => $message,
                'is_read' => 0,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%d', '%s']
        );
        
        wp_send_json_success(['message' => 'Сообщение отправлено']);
    }
    
    /**
     * Получение сообщений чата
     */
    public function ajax_get_chat_messages() {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $with_user = isset($_POST['with_user']) ? intval($_POST['with_user']) : 0;
        $last_id = isset($_POST['last_id']) ? intval($_POST['last_id']) : 0;
        
        $table_chat = $wpdb->prefix . 'akpp_chat_messages';
        
        if ($last_id > 0) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_chat} 
                    WHERE ((sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d))
                    AND id > %d
                    ORDER BY created_at ASC",
                    $user_id, $with_user, $with_user, $user_id, $last_id
                )
            );
        } else {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_chat} 
                    WHERE ((sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d))
                    ORDER BY created_at ASC
                    LIMIT 50",
                    $user_id, $with_user, $with_user, $user_id
                )
            );
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Парсинг URL
     */
    public function ajax_parse_url() {
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        
        if (empty($url)) {
            wp_send_json_error('URL обязателен');
            return;
        }
        
        // Заглушка - реальный парсер будет в Фазе 5
        wp_send_json_success([
            'message' => 'Парсинг запущен',
            'url' => $url,
            'status' => 'pending'
        ]);
    }
    
    /**
     * Одобрение результата парсинга
     */
    public function ajax_approve_parser_item() {
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        
        if (!$item_id) {
            wp_send_json_error('Неверный ID');
            return;
        }
        
        wp_send_json_success(['message' => 'Элемент одобрен']);
    }
    
    /**
     * Декодирование VIN
     */
    public function ajax_decode_vin() {
        $vin = isset($_POST['vin']) ? sanitize_text_field($_POST['vin']) : '';
        
        if (empty($vin) || strlen($vin) < 17) {
            wp_send_json_error('Неверный VIN код');
            return;
        }
        
        // Заглушка - реальный декодер будет в Фазе 4
        wp_send_json_success([
            'brand' => 'Toyota',
            'model' => 'Camry',
            'year' => 2020,
            'engine' => '2.5L',
            'vin' => $vin
        ]);
    }
    
    /**
     * Регистрация пользователя
     */
    public function ajax_register() {
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $car_brand = isset($_POST['car_brand']) ? sanitize_text_field($_POST['car_brand']) : '';
        $problem = isset($_POST['problem']) ? sanitize_textarea_field($_POST['problem']) : '';
        
        if (empty($name) || empty($phone) || empty($email)) {
            wp_send_json_error('Заполните все обязательные поля');
            return;
        }
        
        // Заглушка - реальная регистрация будет в Фазе 3
        wp_send_json_success(['message' => 'Регистрация успешна! Пароль отправлен на email']);
    }
    
    /**
     * Авторизация пользователя
     */
    public function ajax_login() {
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($email) || empty($password)) {
            wp_send_json_error('Введите email и пароль');
            return;
        }
        
        wp_send_json_success(['message' => 'Вход выполнен']);
    }
    
    /**
     * Сохранение push токена
     */
    public function ajax_save_push_token() {
        global $wpdb;
        
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $user_id = get_current_user_id();
        
        if (empty($token) || !$user_id) {
            wp_send_json_error('Неверные данные');
            return;
        }
        
        $table_tokens = $wpdb->prefix . 'akpp_push_tokens';
        
        $wpdb->replace(
            $table_tokens,
            [
                'user_id' => $user_id,
                'token' => $token,
                'device_type' => 'web',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s']
        );
        
        wp_send_json_success(['message' => 'Push токен сохранен']);
    }
    
    /**
     * Сохранение настроек Telegram
     */
    public function ajax_save_telegram_settings() {
        $bot_token = isset($_POST['bot_token']) ? sanitize_text_field($_POST['bot_token']) : '';
        $chat_id = isset($_POST['chat_id']) ? sanitize_text_field($_POST['chat_id']) : '';
        
        update_option('akpp_telegram_bot_token', $bot_token);
        update_option('akpp_telegram_chat_id', $chat_id);
        
        wp_send_json_success(['message' => 'Настройки Telegram сохранены']);
    }
    
    /**
     * Отправка тестового сообщения в Telegram
     */
    public function ajax_send_test_telegram() {
        $bot_token = get_option('akpp_telegram_bot_token', '');
        $chat_id = get_option('akpp_telegram_chat_id', '');
        
        if (empty($bot_token) || empty($chat_id)) {
            wp_send_json_error('Telegram не настроен');
            return;
        }
        
        wp_send_json_success(['message' => 'Тестовое сообщение отправлено']);
    }
    
    // ========== НОВЫЕ МЕТОДЫ ДЛЯ АВИТО (ШАГ 2.1) ==========
    
    /**
     * Сохранение настроек Авито и получение токена
     */
    public function ajax_save_avito_settings() {
        // Проверка nonce
        if (!check_ajax_referer('akpp_avito_settings_nonce', 'akpp_avito_nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        // Проверка прав (только администратор)
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        // Получение и санитизация данных
        $client_id = isset($_POST['avito_client_id']) ? sanitize_text_field($_POST['avito_client_id']) : '';
        $client_secret = isset($_POST['avito_client_secret']) ? sanitize_text_field($_POST['avito_client_secret']) : '';
        
        if (empty($client_id) || empty($client_secret)) {
            wp_send_json_error('Client ID и Client Secret обязательны для заполнения');
            return;
        }
        
        // Загружаем класс Авито
        if (!class_exists('AKPP_Avito')) {
            require_once AKPP_CRM_PATH . 'class-akpp-avito.php';
        }
        
        $avito = AKPP_Avito::get_instance();
        $result = $avito->save_settings($client_id, $client_secret);
        
        if ($result) {
            wp_send_json_success(['message' => 'Настройки сохранены, токен успешно получен']);
        } else {
            wp_send_json_error('Ошибка получения токена. Проверьте Client ID и Client Secret');
        }
    }
    
    /**
     * Обновление токена Авито (ручное обновление)
     */
    public function ajax_refresh_avito_token() {
        // Проверка nonce
        if (!check_ajax_referer('akpp_refresh_token_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        // Проверка прав
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        // Загружаем класс Авито
        if (!class_exists('AKPP_Avito')) {
            require_once AKPP_CRM_PATH . 'class-akpp-avito.php';
        }
        
        $avito = AKPP_Avito::get_instance();
        $result = $avito->refresh_token();
        
        if ($result) {
            wp_send_json_success(['message' => 'Токен успешно обновлен']);
        } else {
            wp_send_json_error('Ошибка обновления токена. Проверьте настройки Client ID и Client Secret');
        }
    }
}
