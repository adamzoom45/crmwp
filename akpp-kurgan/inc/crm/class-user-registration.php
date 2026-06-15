<?php
/**
 * АКПП45 CRM - Обработчик регистрации клиентов
 * Валидация, создание пользователя WP, запись в кастомную таблицу и отправка email.
 *
 * @package AKPP_CRM
 * @version 4.2
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

class AKPP_User_Registration {

    /**
     * Конструктор: регистрация AJAX хуков
     */
    public function __construct() {
        // Регистрация для неавторизованных пользователей (основной сценарий)
        add_action('wp_ajax_nopriv_akpp_register_client', [$this, 'handle_registration']);
        // Регистрация для авторизованных (на случай, если админ создаёт клиента из админки через этот же эндпоинт)
        add_action('wp_ajax_akpp_register_client', [$this, 'handle_registration']);
    }

    /**
     * Основной обработчик регистрации
     */
    public function handle_registration() {
        // 1. Проверка nonce (безопасность)
        check_ajax_referer('akpp_registration_nonce', 'security_nonce');

        // 2. Защита от спама (Honeypot)
        if (!empty($_POST['website_check'])) {
            wp_send_json_error(['message' => __('Обнаружен спам.', 'akpp-crm')], 403);
        }

        // 3. Получение и санитизация данных
        $full_name = isset($_POST['full_name']) ? sanitize_text_field($_POST['full_name']) : '';
        $email     = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone     = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $car_info  = isset($_POST['car_info']) ? sanitize_text_field($_POST['car_info']) : '';

        // 4. Валидация обязательных полей
        if (empty($full_name) || empty($email) || empty($phone)) {
            wp_send_json_error(['message' => __('Пожалуйста, заполните все обязательные поля (ФИО, Email, Телефон).', 'akpp-crm')], 400);
        }

        if (!is_email($email)) {
            wp_send_json_error(['message' => __('Указан некорректный Email адрес.', 'akpp-crm')], 400);
        }

        // Простая валидация телефона (минимум 10 цифр)
        $clean_phone = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen(preg_replace('/[^0-9]/', '', $clean_phone)) < 10) {
            wp_send_json_error(['message' => __('Указан некорректный номер телефона.', 'akpp-crm')], 400);
        }

        global $wpdb;
        $site_users_table = $wpdb->prefix . 'akpp_site_users';

        // 5. Проверка на уникальность Email в WordPress
        if (email_exists($email)) {
            wp_send_json_error(['message' => __('Пользователь с таким Email уже зарегистрирован.', 'akpp-crm')], 409);
        }

        // 6. Проверка на уникальность Телефона в нашей таблице
        $phone_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $site_users_table WHERE phone = %s",
            $clean_phone
        ));
        if ($phone_exists) {
            wp_send_json_error(['message' => __('Пользователь с таким номером телефона уже зарегистрирован.', 'akpp-crm')], 409);
        }

        try {
            // 7. Генерация безопасного пароля (12 символов)
            $generated_password = wp_generate_password(12, true, true);

            // 8. Создание пользователя в WordPress
            $user_data = [
                'user_login'    => $email,
                'user_email'    => $email,
                'user_pass'     => $generated_password,
                'display_name'  => $full_name,
                'first_name'    => explode(' ', $full_name)[0] ?? $full_name,
                'role'          => 'subscriber', // Или ваша кастомная роль 'akpp_client'
            ];

            $user_id = wp_insert_user($user_data);

            if (is_wp_error($user_id)) {
                throw new Exception($user_id->get_error_message());
            }

            // 9. Сохранение дополнительных данных в кастомную таблицу
            $wpdb->insert(
                $site_users_table,
                [
                    'wp_user_id'   => $user_id,
                    'full_name'    => $full_name,
                    'phone'        => $clean_phone,
                    'car_info'     => $car_info,
                    'registered_at'=> current_time('mysql'),
                    'status'       => 'active'
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s']
            );

            // 10. Отправка приветственного письма
            $this->send_welcome_email($user_id, $email, $full_name, $generated_password);

            // 11. Успешный ответ
            wp_send_json_success([
                'message' => __('Регистрация успешно завершена! Данные для входа отправлены на ваш Email.', 'akpp-crm'),
                'redirect_url' => get_permalink(get_page_by_path('login')) // Замените на ваш slug страницы входа
            ]);

        } catch (Exception $e) {
            error_log('[AKPP Registration] Error: ' . $e->getMessage());
            
            // Откат: если пользователь создан, но запись в кастомную таблицу не прошла, удаляем пользователя
            if (isset($user_id) && !is_wp_error($user_id)) {
                wp_delete_user($user_id);
            }
            
            wp_send_json_error(['message' => __('Произошла ошибка при регистрации. Попробуйте позже.', 'akpp-crm')], 500);
        }
    }

    /**
     * Отправка приветственного письма с данными для входа
     */
    private function send_welcome_email($user_id, $email, $full_name, $password) {
        $site_name = get_bloginfo('name');
        $login_url = wp_login_url();
        
        $subject = sprintf(__('Добро пожаловать в %s! Данные для входа', 'akpp-crm'), $site_name);
        
        $message = sprintf(
            __("Здравствуйте, %s!\n\nСпасибо за регистрацию в системе %s.\n\nВаши данные для входа в личный кабинет:\nЛогин (Email): %s\nПароль: %s\n\nСсылка для входа: %s\n\nРекомендуем сменить пароль после первого входа.\n\nС уважением,\nКоманда %s", 'akpp-crm'),
            $full_name,
            $site_name,
            $email,
            $password,
            $login_url,
            $site_name
        );

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        // Отправка письма
        $sent = wp_mail($email, $subject, $message, $headers);

        if (!$sent) {
            // Логируем ошибку отправки почты, но не прерываем регистрацию (пользователь уже создан)
            error_log('[AKPP Registration] Failed to send welcome email to: ' . $email);
        }
    }
}
