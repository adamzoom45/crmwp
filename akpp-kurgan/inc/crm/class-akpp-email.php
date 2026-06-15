<?php
if (!defined('ABSPATH')) exit;

class AKPP_Email {
    
    public function __construct() {
        // Устанавливаем HTML-формат для всех писем, отправляемых через этот класс
        add_filter('wp_mail_content_type', [$this, 'set_html_content_type']);
    }

    /**
     * Установка типа контента в HTML
     */
    public function set_html_content_type() {
        return 'text/html';
    }

    /**
     * Отправка приветственного письма с данными для входа
     *
     * @param string $to       Email получателя
     * @param string $name     Имя клиента
     * @param string $password Сгенерированный пароль
     */
    public function send_welcome_email($to, $name, $password) {
        $subject = 'Добро пожаловать в АКПП45 CRM!';
        
        $login_url = home_url('/login/');
        $profile_url = home_url('/profile/');
        
        $body = $this->get_template_header($subject);
        $body .= "<p>Здравствуйте, <strong>{$name}</strong>!</p>";
        $body .= "<p>Спасибо за регистрацию в системе АКПП45. Ваша учетная запись успешно создана.</p>";
        $body .= "<p><strong>Ваши данные для входа:</strong></p>";
        $body .= "<ul>";
        $body .= "<li><strong>Логин (Email):</strong> {$to}</li>";
        $body .= "<li><strong>Пароль:</strong> <code style='background:#f4f4f4; padding:2px 5px; border-radius:3px;'>{$password}</code></li>";
        $body .= "</ul>";
        $body .= "<p>Мы настоятельно рекомендуем сменить пароль после первого входа в систему.</p>";
        $body .= "<p style='margin-top: 20px;'>";
        $body .= "<a href='{$login_url}' style='display:inline-block; background:#00ff88; color:#0a0f1c; text-decoration:none; padding:10px 20px; border-radius:5px; font-weight:bold;'>Войти в личный кабинет</a>";
        $body .= "</p>";
        $body .= $this->get_template_footer();

        $headers = ['From: АКПП45 CRM <noreply@' . preg_replace('#^www\.#', '', parse_url(home_url(), PHP_URL_HOST)) . '>'];

        $sent = wp_mail($to, $subject, $body, $headers);
        
        // Сбрасываем фильтр после отправки, чтобы не ломать другие письма WordPress
        remove_filter('wp_mail_content_type', [$this, 'set_html_content_type']);

        return $sent;
    }

    /**
     * Отправка уведомления о смене статуса сделки
     *
     * @param string $to      Email клиента
     * @param string $name    Имя клиента
     * @param int    $deal_id ID сделки
     * @param string $status  Новый статус (человекочитаемый)
     */
    public function send_deal_status_update($to, $name, $deal_id, $status) {
        $subject = 'Обновление статуса вашей заявки №' . $deal_id;
        
        $profile_url = home_url('/profile/');
        
        $body = $this->get_template_header($subject);
        $body .= "<p>Здравствуйте, <strong>{$name}</strong>!</p>";
        $body .= "<p>Статус вашей заявки <strong>№{$deal_id}</strong> был изменен.</p>";
        $body .= "<p><strong>Новый статус:</strong> <span style='color:#00aa55; font-weight:bold;'>{$status}</span></p>";
        $body .= "<p>Вы можете отслеживать прогресс в вашем личном кабинете.</p>";
        $body .= "<p style='margin-top: 20px;'>";
        $body .= "<a href='{$profile_url}' style='display:inline-block; background:#00ff88; color:#0a0f1c; text-decoration:none; padding:10px 20px; border-radius:5px; font-weight:bold;'>Перейти в профиль</a>";
        $body .= "</p>";
        $body .= $this->get_template_footer();

        $headers = ['From: АКПП45 CRM <noreply@' . preg_replace('#^www\.#', '', parse_url(home_url(), PHP_URL_HOST)) . '>'];

        $sent = wp_mail($to, $subject, $body, $headers);
        remove_filter('wp_mail_content_type', [$this, 'set_html_content_type']);

        return $sent;
    }

    /**
     * Шапка HTML-письма (базовый стиль)
     */
    private function get_template_header($title) {
        $logo_url = get_template_directory_uri() . '/assets/images/logo.png'; // Замените на реальный путь к лого
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$title}</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f9f9f9; margin: 0; padding: 0; }
                .email-container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .email-header { background-color: #0a0f1c; color: #00ff88; padding: 20px; text-align: center; }
                .email-header h1 { margin: 0; font-size: 24px; }
                .email-body { padding: 30px; }
                .email-footer { background-color: #f4f4f4; padding: 20px; text-align: center; font-size: 12px; color: #777; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <h1>АКПП45 CRM</h1>
                </div>
                <div class='email-body'>
        ";
    }

    /**
     * Подвал HTML-письма
     */
    private function get_template_footer() {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        return "
                </div>
                <div class='email-footer'>
                    <p>Это автоматическое сообщение, пожалуйста, не отвечайте на него.</p>
                    <p>&copy; " . date('Y') . " <a href='{$site_url}' style='color:#555;'>{$site_name}</a>. Все права защищены.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}

// Инициализация
new AKPP_Email();
