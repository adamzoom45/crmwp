<?php
if (!defined('ABSPATH')) exit;

class AKPP_Auth {
    
    public function __construct() {
        // AJAX хуки для фронтенда (доступны неавторизованным и авторизованным)
        add_action('wp_ajax_nopriv_akpp_register_user', [$this, 'register_user']);
        add_action('wp_ajax_nopriv_akpp_login_user', [$this, 'login_user']);
        
        // AJAX хуки только для авторизованных
        add_action('wp_ajax_akpp_logout_user', [$this, 'logout_user']);
        add_action('wp_ajax_akpp_update_profile', [$this, 'update_profile']);
        
        // Шорткоды для вывода форм на фронтенде
        add_shortcode('akpp_register_form', [$this, 'render_register_form']);
        add_shortcode('akpp_login_form', [$this, 'render_login_form']);
        add_shortcode('akpp_profile_form', [$this, 'render_profile_form']);
    }

    /**
     * Регистрация нового клиента
     */
    public function register_user() {
        // Проверка nonce не требуется для nopriv, но можно добавить свою логику защиты (например, honeypot или капча)
        
        $full_name = sanitize_text_field($_POST['full_name'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $car_info = sanitize_text_field($_POST['car_info'] ?? '');
        $problem = sanitize_textarea_field($_POST['problem'] ?? '');

        // Валидация
        if (empty($full_name) || empty($phone) || empty($email)) {
            wp_send_json_error(['message' => 'Заполните все обязательные поля (ФИО, Телефон, Email)']);
        }

        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Некорректный формат Email']);
        }

        // Проверка существования email
        if (email_exists($email)) {
            wp_send_json_error(['message' => 'Пользователь с таким Email уже зарегистрирован']);
        }

        // Генерация случайного пароля
        $password = wp_generate_password(12, true, true);
        $username = sanitize_user('client_' . time() . '_' . wp_rand(100, 999));

        // Создание пользователя WordPress
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => 'Ошибка регистрации: ' . $user_id->get_error_message()]);
        }

        // Назначаем роль "client" (если она существует, иначе subscriber)
        $user = new WP_User($user_id);
        $user->set_role('subscriber');
        $user->first_name = $full_name;

        // Сохранение дополнительных данных в нашу таблицу
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'akpp_site_users',
            [
                'wp_user_id' => $user_id,
                'full_name' => $full_name,
                'phone' => $phone,
                'car_info' => $car_info
            ],
            ['%d', '%s', '%s', '%s']
        );

        // Отправка email с данными для входа
        if (class_exists('AKPP_Email')) {
            $email_class = new AKPP_Email();
            $email_class->send_welcome_email($email, $full_name, $password);
        }

        // Автоматическая авторизация после регистрации (опционально)
        wp_set_auth_cookie($user_id, true);

        wp_send_json_success([
            'message' => 'Регистрация успешна! Данные для входа отправлены на ваш Email.',
            'redirect' => home_url('/profile/')
        ]);
    }

    /**
     * Авторизация клиента
     */
    public function login_user() {
        $login = sanitize_text_field($_POST['login'] ?? ''); // Email или username
        $password = sanitize_text_field($_POST['password'] ?? '');
        $remember = isset($_POST['remember']) ? true : false;

        if (empty($login) || empty($password)) {
            wp_send_json_error(['message' => 'Введите логин и пароль']);
        }

        $creds = [
            'user_login'    => $login,
            'user_password' => $password,
            'remember'      => $remember
        ];

        $user = wp_signon($creds, is_ssl());

        if (is_wp_error($user)) {
            wp_send_json_error(['message' => 'Неверный логин или пароль']);
        }

        wp_send_json_success([
            'message' => 'Вы успешно вошли в систему',
            'redirect' => home_url('/profile/')
        ]);
    }

    /**
     * Выход из системы
     */
    public function logout_user() {
        check_ajax_referer('akpp_crm_nonce', 'nonce');
        wp_logout();
        wp_send_json_success([
            'message' => 'Вы вышли из системы',
            'redirect' => home_url('/login/')
        ]);
    }

    /**
     * Обновление профиля клиента
     */
    public function update_profile() {
        check_ajax_referer('akpp_crm_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Необходима авторизация']);
        }

        $full_name = sanitize_text_field($_POST['full_name'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $car_info = sanitize_text_field($_POST['car_info'] ?? '');

        // Обновляем данные пользователя WP
        wp_update_user([
            'ID' => $user_id,
            'first_name' => $full_name
        ]);

        // Обновляем данные в нашей таблице
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_site_users';
        
        // Проверяем, есть ли запись
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE wp_user_id = %d", $user_id));
        
        if ($exists) {
            $wpdb->update(
                $table,
                ['full_name' => $full_name, 'phone' => $phone, 'car_info' => $car_info],
                ['wp_user_id' => $user_id]
            );
        } else {
            $wpdb->insert(
                $table,
                ['wp_user_id' => $user_id, 'full_name' => $full_name, 'phone' => $phone, 'car_info' => $car_info]
            );
        }

        wp_send_json_success(['message' => 'Профиль успешно обновлен']);
    }

    /**
     * Шорткод: Форма регистрации
     */
    public function render_register_form() {
        if (is_user_logged_in()) {
            return '<p>Вы уже зарегистрированы. <a href="' . home_url('/profile/') . '">Перейти в профиль</a></p>';
        }
        ob_start();
        ?>
        <form id="akpp-register-form" class="akpp-form">
            <h3>Регистрация клиента</h3>
            <div class="form-group">
                <label>ФИО *</label>
                <input type="text" name="full_name" required>
            </div>
            <div class="form-group">
                <label>Телефон *</label>
                <input type="tel" name="phone" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Марка и модель авто</label>
                <input type="text" name="car_info" placeholder="Например: Toyota Camry 2018">
            </div>
            <div class="form-group">
                <label>Описание проблемы</label>
                <textarea name="problem" rows="3"></textarea>
            </div>
            <button type="submit" class="akpp-btn">Зарегистрироваться</button>
            <div class="akpp-form-message"></div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Шорткод: Форма входа
     */
    public function render_login_form() {
        if (is_user_logged_in()) {
            return '<p>Вы уже вошли. <a href="' . home_url('/profile/') . '">Перейти в профиль</a></p>';
        }
        ob_start();
        ?>
        <form id="akpp-login-form" class="akpp-form">
            <h3>Вход в личный кабинет</h3>
            <div class="form-group">
                <label>Email или Логин</label>
                <input type="text" name="login" required>
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="remember"> Запомнить меня</label>
            </div>
            <button type="submit" class="akpp-btn">Войти</button>
            <div class="akpp-form-message"></div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Шорткод: Профиль клиента
     */
    public function render_profile_form() {
        if (!is_user_logged_in()) {
            return '<p>Для просмотра профиля необходимо <a href="' . home_url('/login/') . '">войти</a>.</p>';
        }

        $user_id = get_current_user_id();
        global $wpdb;
        $user_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}akpp_site_users WHERE wp_user_id = %d",
            $user_id
        ));

        $full_name = $user_data ? $user_data->full_name : '';
        $phone = $user_data ? $user_data->phone : '';
        $car_info = $user_data ? $user_data->car_info : '';

        ob_start();
        ?>
        <div class="akpp-profile-container">
            <h3>Мой профиль</h3>
            <form id="akpp-profile-form" class="akpp-form">
                <?php wp_nonce_field('akpp_crm_nonce', 'nonce'); ?>
                <div class="form-group">
                    <label>ФИО</label>
                    <input type="text" name="full_name" value="<?php echo esc_attr($full_name); ?>" required>
                </div>
                <div class="form-group">
                    <label>Телефон</label>
                    <input type="tel" name="phone" value="<?php echo esc_attr($phone); ?>">
                </div>
                <div class="form-group">
                    <label>Информация об авто</label>
                    <input type="text" name="car_info" value="<?php echo esc_attr($car_info); ?>">
                </div>
                <button type="submit" class="akpp-btn">Сохранить изменения</button>
                <button type="button" id="akpp-logout-btn" class="akpp-btn akpp-btn-danger">Выйти</button>
                <div class="akpp-form-message"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Инициализация
new AKPP_Auth();
