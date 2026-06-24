<?php
/**
 * АКПП45 CRM - AJAX обработчики
 * Все AJAX методы для работы CRM системы
 *
 * @package AKPP_CRM
 * @version 5.2.0
 */

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
        $this->register_hooks();
    }

    /**
     * Регистрация всех AJAX хуков
     */
    private function register_hooks() {
        // ====================================================================
        // ЛИЧНЫЙ КАБИНЕТ КЛИЕНТА
        // ====================================================================
        
        // Авторизация (доступно всем)
        add_action('wp_ajax_akpp_client_login', [$this, 'ajax_client_login']);
        add_action('wp_ajax_nopriv_akpp_client_login', [$this, 'ajax_client_login']);
        
        add_action('wp_ajax_akpp_client_register', [$this, 'ajax_client_register']);
        add_action('wp_ajax_nopriv_akpp_client_register', [$this, 'ajax_client_register']);
        
        add_action('wp_ajax_akpp_client_logout', [$this, 'ajax_client_logout']);
        add_action('wp_ajax_nopriv_akpp_client_logout', [$this, 'ajax_client_logout']);
        
        add_action('wp_ajax_akpp_client_forgot_password', [$this, 'ajax_client_forgot_password']);
        add_action('wp_ajax_nopriv_akpp_client_forgot_password', [$this, 'ajax_client_forgot_password']);
        
        // Профиль (только авторизованные)
        add_action('wp_ajax_akpp_client_update_profile', [$this, 'ajax_client_update_profile']);
        add_action('wp_ajax_akpp_client_get_profile', [$this, 'ajax_client_get_profile']);
        
        // Сделки клиента
        add_action('wp_ajax_akpp_client_get_deals', [$this, 'ajax_client_get_deals']);
        add_action('wp_ajax_akpp_client_get_deal', [$this, 'ajax_client_get_deal']);
        
        // Корзина (доступно всем)
        add_action('wp_ajax_akpp_cart_add', [$this, 'ajax_cart_add']);
        add_action('wp_ajax_nopriv_akpp_cart_add', [$this, 'ajax_cart_add']);
        
        add_action('wp_ajax_akpp_cart_remove', [$this, 'ajax_cart_remove']);
        add_action('wp_ajax_nopriv_akpp_cart_remove', [$this, 'ajax_cart_remove']);
        
        add_action('wp_ajax_akpp_cart_update', [$this, 'ajax_cart_update']);
        add_action('wp_ajax_nopriv_akpp_cart_update', [$this, 'ajax_cart_update']);
        
        add_action('wp_ajax_akpp_cart_get', [$this, 'ajax_cart_get']);
        add_action('wp_ajax_nopriv_akpp_cart_get', [$this, 'ajax_cart_get']);
        
        add_action('wp_ajax_akpp_cart_clear', [$this, 'ajax_cart_clear']);
        add_action('wp_ajax_nopriv_akpp_cart_clear', [$this, 'ajax_cart_clear']);
        
        // Оформление заказа
        add_action('wp_ajax_akpp_checkout_create', [$this, 'ajax_checkout_create']);
        
        // Заказы клиента
        add_action('wp_ajax_akpp_client_get_orders', [$this, 'ajax_client_get_orders']);
        add_action('wp_ajax_akpp_client_get_order', [$this, 'ajax_client_get_order']);
        
        // Чат клиента
        add_action('wp_ajax_akpp_client_chat_send', [$this, 'ajax_client_chat_send']);
        add_action('wp_ajax_akpp_client_chat_get', [$this, 'ajax_client_chat_get']);
        add_action('wp_ajax_akpp_client_chat_list', [$this, 'ajax_client_chat_list']);
        
        // ====================================================================
        // АДМИНКА CRM
        // ====================================================================
        
        // Магазин — проверка SKU
        add_action('wp_ajax_akpp_shop_check_sku', [$this, 'ajax_shop_check_sku']);
        
        // Тестовые методы для отладки
        add_action('wp_ajax_akpp_get_last_deal', [$this, 'ajax_get_last_deal']);
        add_action('wp_ajax_akpp_get_all_deals', [$this, 'ajax_get_all_deals']);
        
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
        
        // Магазин (админка)
        add_action('wp_ajax_akpp_shop_get_products', [$this, 'ajax_shop_get_products']);
        add_action('wp_ajax_akpp_shop_save_product', [$this, 'ajax_shop_save_product']);
        add_action('wp_ajax_akpp_shop_update_order_status', [$this, 'ajax_shop_update_order_status']);
        add_action('wp_ajax_akpp_shop_get_orders', [$this, 'ajax_shop_get_orders']);
    }

    /**
     * Проверка прав доступа
     */
    private function check_permissions($capability = 'manage_options') {
        if (!current_user_can($capability)) {
            wp_send_json_error(['message' => 'Недостаточно прав'], 403);
            return false;
        }
        return true;
    }

    /**
     * Проверка что пользователь авторизован как клиент
     */
    private function check_client_auth() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Требуется авторизация', 'code' => 'not_logged_in'], 401);
            return false;
        }
        return true;
    }

    // ========================================================================
    // 🔐 АВТОРИЗАЦИЯ КЛИЕНТА
    // ========================================================================

    /**
     * Вход в личный кабинет
     */
    public function ajax_client_login() {
        if (!check_ajax_referer('akpp_client_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        $login = sanitize_text_field($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);
        
        if (empty($login) || empty($password)) {
            wp_send_json_error(['message' => 'Заполните все поля']);
            return;
        }
        
        // Определяем что ввёл пользователь (email или username)
        if (is_email($login)) {
            $user = get_user_by('email', $login);
        } else {
            $user = get_user_by('login', $login);
        }
        
        if (!$user) {
            wp_send_json_error(['message' => 'Пользователь не найден']);
            return;
        }
        
        // Проверка роли (клиент или админ)
        if (!in_array('akpp_client', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
            wp_send_json_error(['message' => 'Доступ запрещён']);
            return;
        }
        
        $creds = [
            'user_login' => $user->user_login,
            'user_password' => $password,
            'remember' => $remember,
        ];
        
        $signed_in = wp_signon($creds, false);
        
        if (is_wp_error($signed_in)) {
            wp_send_json_error(['message' => 'Неверный пароль']);
            return;
        }
        
        // Обновляем last_login
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}akpp_client_accounts 
             SET last_login = %s, login_count = login_count + 1 
             WHERE wp_user_id = %d",
            current_time('mysql'),
            $signed_in->ID
        ));
        
        wp_send_json_success([
            'message' => '✅ Вход выполнен',
            'redirect' => home_url('/lk/'),
            'user' => [
                'id' => $signed_in->ID,
                'name' => $signed_in->display_name,
                'email' => $signed_in->user_email,
            ]
        ]);
    }

    /**
     * Регистрация клиента
     */
    public function ajax_client_register() {
        if (!check_ajax_referer('akpp_client_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (empty($name) || (empty($phone) && empty($email))) {
            wp_send_json_error(['message' => 'Заполните имя и телефон/email']);
            return;
        }
        
        // Проверка существующего пользователя
        if (!empty($email) && email_exists($email)) {
            wp_send_json_error([
                'message' => 'Пользователь с таким email уже существует. <a href="/lk/login/" style="color:#00ff88;">Войти →</a>'
            ]);
            return;
        }
        
        // Используем класс AKPP_Client_Account
        if (!class_exists('AKPP_Client_Account')) {
            wp_send_json_error(['message' => 'Модуль аккаунтов не загружен']);
            return;
        }
        
        $user_id = AKPP_Client_Account::get_instance()->get_or_create_account([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
        ]);
        
        if (!$user_id) {
            wp_send_json_error(['message' => 'Ошибка регистрации']);
            return;
        }
        
        wp_send_json_success([
            'message' => '✅ Аккаунт создан. Проверьте email для получения пароля.',
            'redirect' => home_url('/lk/login/?registered=1'),
        ]);
    }

    /**
     * Выход из системы
     */
    public function ajax_client_logout() {
        if (!check_ajax_referer('akpp_client_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        wp_logout();
        wp_send_json_success([
            'message' => 'Вы вышли из системы',
            'redirect' => home_url('/'),
        ]);
    }

    /**
     * Восстановление пароля
     */
    public function ajax_client_forgot_password() {
        if (!check_ajax_referer('akpp_client_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (empty($email)) {
            wp_send_json_error(['message' => 'Укажите email']);
            return;
        }
        
        $user = get_user_by('email', $email);
        
        if (!$user) {
            // Не раскрываем существование пользователя
            wp_send_json_success(['message' => '✅ Если аккаунт существует, письмо отправлено']);
            return;
        }
        
        // Генерируем новый пароль
        $new_password = wp_generate_password(12, false);
        wp_set_password($new_password, $user->ID);
        
        // Отправляем
        $subject = '🔑 Восстановление пароля — АКПП45';
        $message = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#0a0f1c;color:#e2e8f0;padding:30px;border-radius:12px;'>
            <h1 style='color:#00ff88;text-align:center;'>🔑 Новый пароль</h1>
            <p>Здравствуйте!</p>
            <p>Вы запросили восстановление пароля. Ваш новый пароль:</p>
            <div style='background:#1a1f2e;padding:20px;border-radius:8px;text-align:center;margin:20px 0;'>
                <code style='font-size:20px;color:#00ff88;background:#2d3748;padding:10px 20px;border-radius:4px;'>{$new_password}</code>
            </div>
            <p style='text-align:center;'>
                <a href='" . home_url('/lk/login/') . "' style='background:#00ff88;color:#1a1f2e;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700;'>
                    Войти в кабинет →
                </a>
            </p>
        </div>
        ";
        
        wp_mail($email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
        
        wp_send_json_success(['message' => '✅ Письмо с новым паролем отправлено на ' . $email]);
    }

    /**
     * Обновление профиля
     */
    public function ajax_client_update_profile() {
        if (!$this->check_client_auth()) return;
        if (!check_ajax_referer('akpp_client_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        $user_id = get_current_user_id();
        $name = sanitize_text_field($_POST['name'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        
        if (!empty($name)) {
            wp_update_user([
                'ID' => $user_id,
                'first_name' => $name,
                'display_name' => $name,
            ]);
        }
        
        if (!empty($phone)) {
            update_user_meta($user_id, 'phone', $phone);
            global $wpdb;
            $wpdb->update($wpdb->prefix . 'akpp_client_accounts', 
                ['phone' => $phone], 
                ['wp_user_id' => $user_id]
            );
        }
        
        if (!empty($new_password) && strlen($new_password) >= 6) {
            wp_set_password($new_password, $user_id);
            // Повторный вход после смены пароля
            wp_set_auth_cookie($user_id, true);
        }
        
        wp_send_json_success(['message' => '✅ Профиль обновлён']);
    }

    /**
     * Получение профиля
     */
    public function ajax_client_get_profile() {
        if (!$this->check_client_auth()) return;
        if (!check_ajax_referer('akpp_client_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        $user = wp_get_current_user();
        $phone = get_user_meta($user->ID, 'phone', true);
        
        wp_send_json_success([
            'id' => $user->ID,
            'name' => $user->first_name ?: $user->display_name,
            'email' => $user->user_email,
            'phone' => $phone,
            'registered' => $user->user_registered,
        ]);
    }

    // ========================================================================
    // 📋 СДЕЛКИ КЛИЕНТА
    // ========================================================================

    /**
     * Получение списка сделок клиента
     */
    public function ajax_client_get_deals() {
        if (!$this->check_client_auth()) return;
        if (!check_ajax_referer('akpp_client_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Находим client_id по user_id
        $client_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_site_users WHERE wp_user_id = %d",
            $user_id
        ));
        
        if (!$client_id) {
            wp_send_json_success(['deals' => [], 'total' => 0]);
            return;
        }
        
        $deals = $wpdb->get_results($wpdb->prepare(
            "SELECT d.*, v.make, v.model, v.year, v.vin
             FROM {$wpdb->prefix}akpp_deals d
             LEFT JOIN {$wpdb->prefix}akpp_vehicles v ON d.vehicle_id = v.id
             WHERE d.client_id = %d
             ORDER BY d.created_at DESC
             LIMIT 50",
            $client_id
        ), ARRAY_A);
        
        wp_send_json_success([
            'deals' => $deals ?: [],
            'total' => count($deals),
        ]);
    }

    /**
     * Получение деталей сделки клиента
     */
    public function ajax_client_get_deal() {
        if (!$this->check_client_auth()) return;
        if (!check_ajax_referer('akpp_client_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        $deal_id = intval($_POST['deal_id'] ?? 0);
        
        if (!$deal_id) {
            wp_send_json_error(['message' => 'Не указан ID сделки']);
            return;
        }
        
        // Находим client_id
        $client_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_site_users WHERE wp_user_id = %d",
            $user_id
        ));
        
        // Получаем сделку (только свою!)
        $deal = $wpdb->get_row($wpdb->prepare(
            "SELECT d.*, v.make, v.model, v.year, v.vin, v.engine
             FROM {$wpdb->prefix}akpp_deals d
             LEFT JOIN {$wpdb->prefix}akpp_vehicles v ON d.vehicle_id = v.id
             WHERE d.id = %d AND d.client_id = %d",
            $deal_id, $client_id
        ), ARRAY_A);
        
        if (!$deal) {
            wp_send_json_error(['message' => 'Сделка не найдена']);
            return;
        }
        
        // Получаем запчасти сделки
        $parts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}akpp_deal_parts WHERE deal_id = %d",
            $deal_id
        ), ARRAY_A);
        
        // Получаем сотрудника
        $employee = null;
        if (!empty($deal['employee_id'])) {
            $employee = $wpdb->get_row($wpdb->prepare(
                "SELECT name, phone FROM {$wpdb->prefix}akpp_employees WHERE id = %d",
                $deal['employee_id']
            ), ARRAY_A);
        }
        
        wp_send_json_success([
            'deal' => $deal,
            'parts' => $parts ?: [],
            'employee' => $employee,
        ]);
    }

    // ========================================================================
    // 🛒 КОРЗИНА
    // ========================================================================

    /**
     * Получить/создать session_id для корзины (для неавторизованных)
     */
    private function get_cart_user_id() {
        if (is_user_logged_in()) {
            return get_current_user_id();
        }
        
        // Для неавторизованных используем session_id из cookie
        if (empty($_COOKIE['akpp_cart_session'])) {
            $session_id = 'guest_' . wp_generate_password(32, false);
            setcookie('akpp_cart_session', $session_id, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
            $_COOKIE['akpp_cart_session'] = $session_id;
        }
        
        // Возвращаем хэш session_id как user_id (отрицательное число)
        return -abs(crc32($_COOKIE['akpp_cart_session']));
    }

    /**
     * Добавление в корзину
     */
    public function ajax_cart_add() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        $user_id = $this->get_cart_user_id();
        $product_id = intval($_POST['product_id'] ?? 0);
        $product_type = sanitize_text_field($_POST['product_type'] ?? 'product');
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        
        if (!$product_id) {
            wp_send_json_error(['message' => 'Ошибка данных']);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_client_cart';
        
        // Проверяем есть ли уже в корзине
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, quantity FROM {$table} WHERE user_id = %d AND product_id = %d AND product_type = %s",
            $user_id, $product_id, $product_type
        ));
        
        if ($existing) {
            $wpdb->update($table, 
                ['quantity' => $existing->quantity + $quantity],
                ['id' => $existing->id]
            );
        } else {
            $wpdb->insert($table, [
                'user_id' => $user_id,
                'product_id' => $product_id,
                'product_type' => $product_type,
                'quantity' => $quantity,
            ]);
        }
        
        wp_send_json_success([
            'message' => '✅ Добавлено в корзину',
            'cart_count' => $this->get_cart_count($user_id),
        ]);
    }

    /**
     * Удаление из корзины
     */
    public function ajax_cart_remove() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        $user_id = $this->get_cart_user_id();
        $cart_id = intval($_POST['cart_id'] ?? 0);
        
        if (!$cart_id) {
            wp_send_json_error(['message' => 'Ошибка данных']);
            return;
        }
        
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'akpp_client_cart', [
            'id' => $cart_id,
            'user_id' => $user_id,
        ]);
        
        wp_send_json_success([
            'message' => '✅ Удалено из корзины',
            'cart_count' => $this->get_cart_count($user_id),
        ]);
    }

    /**
     * Обновление количества в корзине
     */
    public function ajax_cart_update() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        $user_id = $this->get_cart_user_id();
        $cart_id = intval($_POST['cart_id'] ?? 0);
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        
        if (!$cart_id) {
            wp_send_json_error(['message' => 'Ошибка данных']);
            return;
        }
        
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'akpp_client_cart',
            ['quantity' => $quantity],
            ['id' => $cart_id, 'user_id' => $user_id]
        );
        
        wp_send_json_success([
            'message' => '✅ Обновлено',
            'items' => $this->get_cart_items($user_id),
            'total' => $this->get_cart_total($user_id),
        ]);
    }

    /**
     * Получение содержимого корзины
     */
    public function ajax_cart_get() {
        $user_id = $this->get_cart_user_id();
        
        wp_send_json_success([
            'items' => $this->get_cart_items($user_id),
            'total' => $this->get_cart_total($user_id),
            'count' => $this->get_cart_count($user_id),
        ]);
    }

    /**
     * Очистка корзины
     */
    public function ajax_cart_clear() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        $user_id = $this->get_cart_user_id();
        
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'akpp_client_cart', ['user_id' => $user_id]);
        
        wp_send_json_success(['message' => '✅ Корзина очищена']);
    }

    /**
     * Получение товаров корзины
     */
    private function get_cart_items($user_id) {
        global $wpdb;
        
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id as cart_id, c.*, p.name, p.sku, p.price, p.images, p.condition_type, p.stock
             FROM {$wpdb->prefix}akpp_client_cart c
             LEFT JOIN {$wpdb->prefix}akpp_shop_products p ON c.product_id = p.id
             WHERE c.user_id = %d AND p.id IS NOT NULL",
            $user_id
        ), ARRAY_A);
        
        return $items ?: [];
    }

    /**
     * Общая сумма корзины
     */
    private function get_cart_total($user_id) {
        global $wpdb;
        
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(p.price * c.quantity), 0)
             FROM {$wpdb->prefix}akpp_client_cart c
             LEFT JOIN {$wpdb->prefix}akpp_shop_products p ON c.product_id = p.id
             WHERE c.user_id = %d",
            $user_id
        ));
    }

    /**
     * Количество товаров в корзине
     */
    private function get_cart_count($user_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM {$wpdb->prefix}akpp_client_cart WHERE user_id = %d",
            $user_id
        ));
    }

    // ========================================================================
    // 💳 ОФОРМЛЕНИЕ ЗАКАЗА
    // ========================================================================

    /**
     * Создание заказа из корзины
     */
    public function ajax_checkout_create() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Необходимо войти в аккаунт', 'code' => 'not_logged_in'], 401);
            return;
        }
        
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        $user_id = get_current_user_id();
        $cart_items = $this->get_cart_items($user_id);
        
        if (empty($cart_items)) {
            wp_send_json_error(['message' => 'Корзина пуста']);
            return;
        }
        
        $user = wp_get_current_user();
        
        // Данные доставки
        $address = sanitize_textarea_field($_POST['address'] ?? '');
        $comment = sanitize_textarea_field($_POST['comment'] ?? '');
        $payment_method = sanitize_text_field($_POST['payment_method'] ?? 'cash');
        
        // Расчёт суммы
        $subtotal = $this->get_cart_total($user_id);
        
        global $wpdb;
        
        // Создаём заказ
        $order_number = 'ORD-' . strtoupper(substr(uniqid(), -8));
        
        $wpdb->insert($wpdb->prefix . 'akpp_shop_orders', [
            'order_number' => $order_number,
            'client_name' => $user->first_name ?: $user->display_name,
            'client_phone' => get_user_meta($user_id, 'phone', true),
            'client_email' => $user->user_email,
            'client_address' => $address,
            'status' => 'new',
            'payment_method' => $payment_method,
            'payment_status' => 'pending',
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'notes' => $comment,
            'created_at' => current_time('mysql'),
        ]);
        
        $order_id = $wpdb->insert_id;
        
        // Добавляем позиции
        foreach ($cart_items as $item) {
            $wpdb->insert($wpdb->prefix . 'akpp_shop_order_items', [
                'order_id' => $order_id,
                'product_id' => $item['product_id'],
                'product_name' => $item['name'],
                'product_sku' => $item['sku'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'total' => $item['price'] * $item['quantity'],
            ]);
            
            // Уменьшаем остаток на складе
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}akpp_shop_products 
                 SET stock = GREATEST(0, stock - %d) 
                 WHERE id = %d",
                $item['quantity'],
                $item['product_id']
            ));
        }
        
        // Очищаем корзину
        $wpdb->delete($wpdb->prefix . 'akpp_client_cart', ['user_id' => $user_id]);
        
        // Уведомление админу
        $this->notify_admin_new_order($order_id, $order_number, $subtotal);
        
        wp_send_json_success([
            'message' => '✅ Заказ #' . $order_number . ' оформлен!',
            'order_id' => $order_id,
            'order_number' => $order_number,
            'redirect' => home_url('/lk/order/' . $order_id . '/'),
        ]);
    }

    /**
     * Уведомление админу о новом заказе
     */
    private function notify_admin_new_order($order_id, $order_number, $total) {
        $bot_token = get_option('akpp_telegram_bot_token', '');
        $chat_id = get_option('akpp_telegram_chat_id', '');
        
        if (empty($bot_token) || empty($chat_id)) return;
        
        $message = "🛒 *НОВЫЙ ЗАКАЗ #{$order_number}*\n\n💰 Сумма: " . number_format($total, 0, ',', ' ') . " ₽";
        
        wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
            'body' => ['chat_id' => $chat_id, 'text' => $message, 'parse_mode' => 'Markdown'],
            'timeout' => 5
        ]);
    }

    // ========================================================================
    // 📦 ЗАКАЗЫ КЛИЕНТА
    // ========================================================================

    /**
     * Список заказов клиента
     */
    public function ajax_client_get_orders() {
        if (!$this->check_client_auth()) return;
        if (!check_ajax_referer('akpp_client_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        global $wpdb;
        $user = wp_get_current_user();
        
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}akpp_shop_orders 
             WHERE client_email = %s OR client_phone = %s
             ORDER BY created_at DESC
             LIMIT 50",
            $user->user_email,
            get_user_meta($user->ID, 'phone', true)
        ), ARRAY_A);
        
        wp_send_json_success([
            'orders' => $orders ?: [],
            'total' => count($orders),
        ]);
    }

    /**
     * Детали заказа клиента
     */
    public function ajax_client_get_order() {
        if (!$this->check_client_auth()) return;
        if (!check_ajax_referer('akpp_client_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        global $wpdb;
        $user = wp_get_current_user();
        $order_id = intval($_POST['order_id'] ?? 0);
        
        if (!$order_id) {
            wp_send_json_error(['message' => 'Не указан ID заказа']);
            return;
        }
        
        // Получаем заказ (только свой!)
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}akpp_shop_orders 
             WHERE id = %d AND (client_email = %s OR client_phone = %s)",
            $order_id,
            $user->user_email,
            get_user_meta($user->ID, 'phone', true)
        ), ARRAY_A);
        
        if (!$order) {
            wp_send_json_error(['message' => 'Заказ не найден']);
            return;
        }
        
        // Получаем позиции
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}akpp_shop_order_items WHERE order_id = %d",
            $order_id
        ), ARRAY_A);
        
        wp_send_json_success([
            'order' => $order,
            'items' => $items ?: [],
        ]);
    }

    // ========================================================================
    // 💬 ЧАТ КЛИЕНТА
    // ========================================================================

    /**
     * Список чатов клиента (по сделкам)
     */
    public function ajax_client_chat_list() {
        if (!$this->check_client_auth()) return;
        if (!check_ajax_referer('akpp_client_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Находим client_id
        $client_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_site_users WHERE wp_user_id = %d",
            $user_id
        ));
        
        if (!$client_id) {
            wp_send_json_success(['chats' => []]);
            return;
        }
        
        // Получаем сделки с сообщениями
        $chats = $wpdb->get_results($wpdb->prepare(
            "SELECT d.id as deal_id, d.status, d.total_amount, d.created_at,
                    v.make, v.model, v.year,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}akpp_chat_messages 
                     WHERE dialog_id = d.id AND is_read = 0 AND sender_id != %d) as unread_count,
                    (SELECT message_text FROM {$wpdb->prefix}akpp_chat_messages 
                     WHERE dialog_id = d.id ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM {$wpdb->prefix}akpp_chat_messages 
                     WHERE dialog_id = d.id ORDER BY created_at DESC LIMIT 1) as last_message_at
             FROM {$wpdb->prefix}akpp_deals d
             LEFT JOIN {$wpdb->prefix}akpp_vehicles v ON d.vehicle_id = v.id
             WHERE d.client_id = %d
             ORDER BY last_message_at DESC NULLS LAST, d.created_at DESC
             LIMIT 20",
            $user_id,
            $client_id
        ), ARRAY_A);
        
        wp_send_json_success(['chats' => $chats ?: []]);
    }

    /**
     * Получение сообщений чата
     */
    public function ajax_client_chat_get() {
        if (!$this->check_client_auth()) return;
        if (!check_ajax_referer('akpp_client_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        $deal_id = intval($_POST['deal_id'] ?? 0);
        
        if (!$deal_id) {
            wp_send_json_error(['message' => 'Не указан ID сделки']);
            return;
        }
        
        // Проверка что сделка принадлежит клиенту
        $client_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_site_users WHERE wp_user_id = %d",
            $user_id
        ));
        
        $deal_client = $wpdb->get_var($wpdb->prepare(
            "SELECT client_id FROM {$wpdb->prefix}akpp_deals WHERE id = %d",
            $deal_id
        ));
        
        if ($deal_client != $client_id) {
            wp_send_json_error(['message' => 'Доступ запрещён'], 403);
            return;
        }
        
        // Получаем сообщения
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}akpp_chat_messages 
             WHERE dialog_id = %d 
             ORDER BY created_at ASC 
             LIMIT 100",
            $deal_id
        ), ARRAY_A);
        
        // Помечаем как прочитанные
        $wpdb->update($wpdb->prefix . 'akpp_chat_messages',
            ['is_read' => 1],
            ['dialog_id' => $deal_id, 'is_read' => 0]
        );
        
        wp_send_json_success(['messages' => $messages ?: []]);
    }

    /**
     * Отправка сообщения в чат
     */
    public function ajax_client_chat_send() {
        if (!$this->check_client_auth()) return;
        if (!check_ajax_referer('akpp_client_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        $deal_id = intval($_POST['deal_id'] ?? 0);
        $message_text = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (!$deal_id || empty($message_text)) {
            wp_send_json_error(['message' => 'Заполните все поля']);
            return;
        }
        
        // Проверка что сделка принадлежит клиенту
        $client_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_site_users WHERE wp_user_id = %d",
            $user_id
        ));
        
        $deal_client = $wpdb->get_var($wpdb->prepare(
            "SELECT client_id FROM {$wpdb->prefix}akpp_deals WHERE id = %d",
            $deal_id
        ));
        
        if ($deal_client != $client_id) {
            wp_send_json_error(['message' => 'Доступ запрещён'], 403);
            return;
        }
        
        $user = wp_get_current_user();
        
        // Сохраняем сообщение
        $wpdb->insert($wpdb->prefix . 'akpp_chat_messages', [
            'user_id' => $user_id,
            'sender_id' => $user_id,
            'sender_name' => $user->display_name,
            'message_text' => $message_text,
            'dialog_id' => $deal_id,
            'is_read' => 0,
            'created_at' => current_time('mysql'),
        ]);
        
        $message_id = $wpdb->insert_id;
        
        // Уведомление мастеру в Telegram
        $this->notify_employee_new_message($deal_id, $user->display_name, $message_text);
        
        wp_send_json_success([
            'message' => '✅ Сообщение отправлено',
            'message_id' => $message_id,
        ]);
    }

    /**
     * Уведомление сотруднику о новом сообщении
     */
    private function notify_employee_new_message($deal_id, $client_name, $message_text) {
        global $wpdb;
        
        $deal = $wpdb->get_row($wpdb->prepare(
            "SELECT employee_id FROM {$wpdb->prefix}akpp_deals WHERE id = %d",
            $deal_id
        ));
        
        if (!$deal || !$deal->employee_id) return;
        
        $employee = $wpdb->get_row($wpdb->prepare(
            "SELECT telegram_chat_id, name FROM {$wpdb->prefix}akpp_employees WHERE id = %d",
            $deal->employee_id
        ));
        
        if (!$employee || empty($employee->telegram_chat_id)) return;
        
        $bot_token = get_option('akpp_telegram_bot_token', '');
        if (empty($bot_token)) return;
        
        $text = "💬 *Новое сообщение от клиента*\n\n";
        $text .= "👤 {$client_name}\n";
        $text .= "📋 Сделка #{$deal_id}\n";
        $text .= "💬 " . mb_substr($message_text, 0, 200);
        
        wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
            'body' => [
                'chat_id' => $employee->telegram_chat_id,
                'text' => $text,
                'parse_mode' => 'Markdown'
            ],
            'timeout' => 5
        ]);
    }

    // ========================================================================
    // 🛒 МАГАЗИН — ПРОВЕРКА SKU
    // ========================================================================

    public function ajax_shop_check_sku() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        
        $sku = strtoupper(sanitize_text_field($_POST['sku'] ?? ''));
        $product_id = intval($_POST['product_id'] ?? 0);
        
        if (empty($sku)) {
            wp_send_json_error(['message' => 'SKU пуст']);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_shop_products';
        
        if ($product_id > 0) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE sku = %s AND id != %d",
                $sku, $product_id
            ));
        } else {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE sku = %s",
                $sku
            ));
        }
        
        wp_send_json_success([
            'available' => !$exists,
            'sku' => $sku
        ]);
    }

    // ========================================================================
    // ТЕСТОВЫЕ МЕТОДЫ
    // ========================================================================

    public function ajax_get_last_deal() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_deals';
        
        $deal = $wpdb->get_row("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 1", ARRAY_A);
        wp_send_json_success($deal);
    }

    public function ajax_get_all_deals() {
        if (!check_ajax_referer('akpp45_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Ошибка безопасности'], 403);
            return;
        }
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_deals';
        
        $deals = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A);
        wp_send_json_success($deals);
    }

    // ========================================================================
    // 📜 ДОГОВОР-ОФЕРТА
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
        
        if ($deal_id > 0) {
            $wpdb->update(
                $wpdb->prefix . 'akpp_deals',
                ['agreement_accepted' => 1, 'agreement_id' => $agreement_id],
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

    // ========================================================================
    // 💾 СОХРАНЕНИЕ СУЩНОСТЕЙ (остальные методы без изменений)
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
    // 🗑️ УДАЛЕНИЕ СУЩНОСТЕЙ
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
    // 🔍 ПОИСК
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
    // 💼 СДЕЛКИ (продолжение)
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
            
            $total_amount = floatval($_POST['total_amount'] ?? $_POST['payment_amount'] ?? 0);
            if ($total_amount <= 0) {
                $total_amount = $work_cost + $parts_total;
            }
            
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
            
            foreach ($parts_data as $part) {
                $wpdb->insert($wpdb->prefix . 'akpp_deal_parts', [
                    'deal_id' => $result_id,
                    'part_id' => $part['part_id'],
                    'quantity' => $part['quantity'],
                    'price_at_deal' => $part['price_at_deal'],
                ]);
            }
            
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
    // 🤖 VIN AI ДЕКОДЕР
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
    // 🌐 ПАРСЕР
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
    // 🤖 AI АНАЛИЗ
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
    // 📱 TELEGRAM
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
    // 📦 КАТЕГОРИИ СКЛАДА
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
    // 🛒 МАГАЗИН (АДМИНКА)
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
        
        // Автогенерация SKU если не указан
        $sku = strtoupper(sanitize_text_field($_POST['sku'] ?? ''));
        if (empty($sku)) {
            $timestamp = strtoupper(substr(uniqid(), -6));
            $random = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
            $sku = 'AKPP-' . $timestamp . '-' . $random;
            
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_shop_products';
            while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE sku = %s", $sku))) {
                $random = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
                $sku = 'AKPP-' . $timestamp . '-' . $random;
            }
            
            $_POST['sku'] = $sku;
        }
        
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
}