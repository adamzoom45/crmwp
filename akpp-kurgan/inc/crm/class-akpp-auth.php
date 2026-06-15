<?php
/**
 * Класс для регистрации и авторизации пользователей CRM
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_Auth {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'init_session']);
        add_action('wp_logout', [$this, 'logout']);
        add_action('wp_ajax_akpp_register', [$this, 'ajax_register']);
        add_action('wp_ajax_nopriv_akpp_register', [$this, 'ajax_register']);
        add_action('wp_ajax_akpp_login', [$this, 'ajax_login']);
        add_action('wp_ajax_nopriv_akpp_login', [$this, 'ajax_login']);
        add_action('wp_ajax_akpp_logout', [$this, 'ajax_logout']);
        add_action('wp_ajax_akpp_get_profile', [$this, 'ajax_get_profile']);
        add_action('wp_ajax_akpp_update_profile', [$this, 'ajax_update_profile']);
        add_action('wp_ajax_akpp_update_password', [$this, 'ajax_update_password']);
        add_action('wp_ajax_nopriv_akpp_reset_password', [$this, 'ajax_reset_password']);
    }
    
    /**
     * Инициализация сессии
     */
    public function init_session() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }
    
    /**
     * Регистрация пользователя
     */
    public function ajax_register() {
        if (!check_ajax_referer('akpp_client_register_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $car_brand = isset($_POST['car_brand']) ? sanitize_text_field($_POST['car_brand']) : '';
        $problem = isset($_POST['problem']) ? sanitize_textarea_field($_POST['problem']) : '';
        
        if (empty($name)) {
            wp_send_json_error('Введите ФИО');
            return;
        }
        
        if (empty($phone)) {
            wp_send_json_error('Введите номер телефона');
            return;
        }
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error('Введите корректный email');
            return;
        }
        
        global $wpdb;
        $table_users = $wpdb->prefix . 'akpp_site_users';
        $table_leads = $wpdb->prefix . 'akpp_leads';
        
        // Проверка существующего пользователя
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_users} WHERE email = %s",
            $email
        ));
        
        if ($existing) {
            wp_send_json_error('Пользователь с таким email уже зарегистрирован');
            return;
        }
        
        // Генерация пароля
        $password = wp_generate_password(12, true, true);
        $hashed_password = wp_hash_password($password);
        
        // Создание пользователя
        $inserted = $wpdb->insert(
            $table_users,
            [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $hashed_password,
                'car_brand' => $car_brand,
                'role' => 'client',
                'status' => 'active',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if (!$inserted) {
            wp_send_json_error('Ошибка создания пользователя');
            return;
        }
        
        $user_id = $wpdb->insert_id;
        
        // Создание лида
        $wpdb->insert(
            $table_leads,
            [
                'client_id' => $user_id,
                'client_name' => $name,
                'client_phone' => $phone,
                'client_email' => $email,
                'car_brand' => $car_brand,
                'problem' => $problem,
                'status' => 'new',
                'source' => 'site_form',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        // Отправка email
        if (class_exists('AKPP_Email')) {
            AKPP_Email::get_instance()->send_welcome($email, $name, $password);
        }
        
        // Push уведомление гиду
        $this->notify_guide_new_lead($name, $phone);
        
        wp_send_json_success([
            'message' => 'Регистрация успешна! Пароль отправлен на email.'
        ]);
    }
    
    /**
     * Авторизация пользователя
     */
    public function ajax_login() {
        if (!check_ajax_referer('akpp_client_login_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $remember = isset($_POST['remember']) ? (int)$_POST['remember'] : 0;
        
        if (empty($email) || empty($password)) {
            wp_send_json_error('Введите email и пароль');
            return;
        }
        
        global $wpdb;
        $table_users = $wpdb->prefix . 'akpp_site_users';
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_users} WHERE email = %s AND status = 'active'",
            $email
        ));
        
        if (!$user) {
            wp_send_json_error('Пользователь не найден');
            return;
        }
        
        if (!wp_check_password($password, $user->password)) {
            wp_send_json_error('Неверный пароль');
            return;
        }
        
        // Установка сессии
        $_SESSION['akpp_user_id'] = $user->id;
        $_SESSION['akpp_user_name'] = $user->name;
        $_SESSION['akpp_user_role'] = $user->role;
        
        // Обновление времени последнего входа
        $wpdb->update(
            $table_users,
            ['last_login' => current_time('mysql')],
            ['id' => $user->id],
            ['%s'],
            ['%d']
        );
        
        // Установка cookie для "запомнить меня"
        if ($remember) {
            $token = wp_generate_password(64, false);
            setcookie('akpp_auth_token', $token, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
            update_user_meta($user->id, 'akpp_auth_token', wp_hash_password($token));
        }
        
        wp_send_json_success([
            'message' => 'Вход выполнен успешно',
            'redirect_url' => home_url('/crm-profile'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]);
    }
    
    /**
     * Проверка авторизации
     */
    public static function is_logged_in() {
        return isset($_SESSION['akpp_user_id']) && $_SESSION['akpp_user_id'] > 0;
    }
    
    /**
     * Получение текущего пользователя
     */
    public static function get_current_user() {
        if (!self::is_logged_in()) {
            return null;
        }
        
        global $wpdb;
        $table_users = $wpdb->prefix . 'akpp_site_users';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, email, phone, car_brand, role, created_at, last_login 
            FROM {$table_users} 
            WHERE id = %d AND status = 'active'",
            $_SESSION['akpp_user_id']
        ));
    }
    
    /**
     * Выход из системы
     */
    public function ajax_logout() {
        if (!check_ajax_referer('akpp_logout_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $this->logout();
        
        wp_send_json_success([
            'message' => 'Выход выполнен',
            'redirect_url' => home_url('/crm-login')
        ]);
    }
    
    /**
     * Выход из системы
     */
    public function logout() {
        $_SESSION = [];
        session_destroy();
        setcookie('akpp_auth_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
    }
    
    /**
     * Получение профиля пользователя
     */
    public function ajax_get_profile() {
        if (!check_ajax_referer('akpp_get_profile_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $user = self::get_current_user();
        
        if (!$user) {
            wp_send_json_error('Пользователь не авторизован');
            return;
        }
        
        wp_send_json_success($user);
    }
    
    /**
     * Обновление профиля пользователя
     */
    public function ajax_update_profile() {
        if (!check_ajax_referer('akpp_update_profile_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $user = self::get_current_user();
        
        if (!$user) {
            wp_send_json_error('Пользователь не авторизован');
            return;
        }
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $car_brand = isset($_POST['car_brand']) ? sanitize_text_field($_POST['car_brand']) : '';
        
        if (empty($name)) {
            wp_send_json_error('Введите ФИО');
            return;
        }
        
        global $wpdb;
        $table_users = $wpdb->prefix . 'akpp_site_users';
        
        $wpdb->update(
            $table_users,
            [
                'name' => $name,
                'phone' => $phone,
                'car_brand' => $car_brand
            ],
            ['id' => $user->id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        $_SESSION['akpp_user_name'] = $name;
        
        wp_send_json_success([
            'message' => 'Профиль успешно обновлен'
        ]);
    }
    
    /**
     * Обновление пароля
     */
    public function ajax_update_password() {
        if (!check_ajax_referer('akpp_update_password_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $user = self::get_current_user();
        
        if (!$user) {
            wp_send_json_error('Пользователь не авторизован');
            return;
        }
        
        $old_password = isset($_POST['old_password']) ? $_POST['old_password'] : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        global $wpdb;
        $table_users = $wpdb->prefix . 'akpp_site_users';
        
        $db_user = $wpdb->get_row($wpdb->prepare(
            "SELECT password FROM {$table_users} WHERE id = %d",
            $user->id
        ));
        
        if (!wp_check_password($old_password, $db_user->password)) {
            wp_send_json_error('Неверный текущий пароль');
            return;
        }
        
        if (strlen($new_password) < 6) {
            wp_send_json_error('Новый пароль должен содержать не менее 6 символов');
            return;
        }
        
        if ($new_password !== $confirm_password) {
            wp_send_json_error('Пароли не совпадают');
            return;
        }
        
        $hashed_password = wp_hash_password($new_password);
        
        $wpdb->update(
            $table_users,
            ['password' => $hashed_password],
            ['id' => $user->id],
            ['%s'],
            ['%d']
        );
        
        wp_send_json_success([
            'message' => 'Пароль успешно изменен'
        ]);
    }
    
    /**
     * Сброс пароля
     */
    public function ajax_reset_password() {
        if (!check_ajax_referer('akpp_reset_password_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error('Введите корректный email');
            return;
        }
        
        global $wpdb;
        $table_users = $wpdb->prefix . 'akpp_site_users';
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name FROM {$table_users} WHERE email = %s",
            $email
        ));
        
        if (!$user) {
            wp_send_json_error('Пользователь с таким email не найден');
            return;
        }
        
        $new_password = wp_generate_password(12, true, true);
        $hashed_password = wp_hash_password($new_password);
        
        $wpdb->update(
            $table_users,
            ['password' => $hashed_password],
            ['id' => $user->id],
            ['%s'],
            ['%d']
        );
        
        if (class_exists('AKPP_Email')) {
            AKPP_Email::get_instance()->send_password_reset($email, $user->name, $new_password);
        }
        
        wp_send_json_success([
            'message' => 'Новый пароль отправлен на ваш email'
        ]);
    }
    
    /**
     * Уведомление гида о новом лиде
     */
    private function notify_guide_new_lead($name, $phone) {
        global $wpdb;
        $table_employees = $wpdb->prefix . 'akpp_employees';
        
        $guide = $wpdb->get_row(
            "SELECT id, telegram_chat_id FROM {$table_employees} 
            WHERE role = 'guide' AND is_active = 1 
            ORDER BY id ASC LIMIT 1"
        );
        
        if ($guide && $guide->telegram_chat_id && class_exists('AKPP_Telegram')) {
            $message = "🆕 <b>Новый лид!</b>\n\n";
            $message .= "👤 Клиент: {$name}\n";
            $message .= "📞 Телефон: {$phone}";
            AKPP_Telegram::get_instance()->send_message($guide->telegram_chat_id, $message);
        }
    }
}
