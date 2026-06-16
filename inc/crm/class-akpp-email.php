<?php
/**
 * Класс для отправки email уведомлений
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_Email {
    
    private static $instance = null;
    private $from_email;
    private $from_name;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->from_email = get_option('admin_email');
        $this->from_name = get_bloginfo('name');
        
        add_filter('wp_mail_from', [$this, 'set_from_email']);
        add_filter('wp_mail_from_name', [$this, 'set_from_name']);
        add_action('phpmailer_init', [$this, 'configure_smtp']);
    }
    
    /**
     * Установка email отправителя
     */
    public function set_from_email($email) {
        return $this->from_email;
    }
    
    /**
     * Установка имени отправителя
     */
    public function set_from_name($name) {
        return $this->from_name;
    }
    
    /**
     * Настройка SMTP (если используется)
     */
    public function configure_smtp($phpmailer) {
        $smtp_host = get_option('akpp_smtp_host', '');
        $smtp_port = get_option('akpp_smtp_port', '');
        $smtp_user = get_option('akpp_smtp_user', '');
        $smtp_pass = get_option('akpp_smtp_password', '');
        
        if (!empty($smtp_host)) {
            $phpmailer->isSMTP();
            $phpmailer->Host = $smtp_host;
            $phpmailer->Port = $smtp_port ?: 587;
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $smtp_user;
            $phpmailer->Password = $smtp_pass;
            $phpmailer->SMTPSecure = $smtp_port == 465 ? 'ssl' : 'tls';
        }
    }
    
    /**
     * Отправка приветственного письма
     */
    public function send_welcome($email, $name, $password) {
        $subject = 'Добро пожаловать в АКПП45 CRM';
        
        $message = $this->get_template_header('Добро пожаловать!');
        $message .= '<div style="padding: 20px;">';
        $message .= '<h2 style="color: #667eea;">Уважаемый(ая) ' . esc_html($name) . '!</h2>';
        $message .= '<p>Ваш аккаунт в системе АКПП45 CRM успешно создан.</p>';
        $message .= '<h3 style="margin-top: 25px;">📋 Ваши данные для входа:</h3>';
        $message .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">';
        $message .= '<p><strong>🔗 Ссылка для входа:</strong> <a href="' . home_url('/crm-login') . '">' . home_url('/crm-login') . '</a></p>';
        $message .= '<p><strong>📧 Email:</strong> ' . esc_html($email) . '</p>';
        $message .= '<p><strong>🔑 Пароль:</strong> <code style="background: #e9ecef; padding: 4px 8px; border-radius: 4px;">' . esc_html($password) . '</code></p>';
        $message .= '</div>';
        $message .= '<p>⚠️ <strong>Важно:</strong> Рекомендуем сменить пароль после первого входа.</p>';
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . home_url('/crm-login') . '" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 25px; display: inline-block;">🔐 Войти в CRM</a>';
        $message .= '</div>';
        $message .= '</div>';
        $message .= $this->get_template_footer();
        
        $this->send($email, $subject, $message);
    }
    
    /**
     * Отправка письма со сбросом пароля
     */
    public function send_password_reset($email, $name, $new_password) {
        $subject = 'Восстановление пароля - АКПП45 CRM';
        
        $message = $this->get_template_header('Восстановление пароля');
        $message .= '<div style="padding: 20px;">';
        $message .= '<h2 style="color: #667eea;">Здравствуйте, ' . esc_html($name) . '!</h2>';
        $message .= '<p>Вы запросили восстановление пароля для доступа к CRM системе АКПП45.</p>';
        $message .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">';
        $message .= '<p><strong>🔑 Ваш новый пароль:</strong> <code style="background: #e9ecef; padding: 4px 8px; border-radius: 4px; font-size: 16px;">' . esc_html($new_password) . '</code></p>';
        $message .= '</div>';
        $message .= '<p>⚠️ <strong>Важно:</strong> Рекомендуем сменить этот пароль при следующем входе.</p>';
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . home_url('/crm-login') . '" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 25px; display: inline-block;">🔐 Войти в CRM</a>';
        $message .= '</div>';
        $message .= '</div>';
        $message .= $this->get_template_footer();
        
        $this->send($email, $subject, $message);
    }
    
    /**
     * Уведомление о назначенной сделке (для сотрудника)
     */
    public function send_deal_assigned($email, $name, $deal_id, $client_name, $car) {
        $subject = 'Новая сделка #' . $deal_id . ' - АКПП45 CRM';
        
        $message = $this->get_template_header('Новая сделка');
        $message .= '<div style="padding: 20px;">';
        $message .= '<h2 style="color: #667eea;">Здравствуйте, ' . esc_html($name) . '!</h2>';
        $message .= '<p>Вам назначена новая сделка в системе АКПП45 CRM.</p>';
        $message .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">';
        $message .= '<p><strong>📋 Номер сделки:</strong> #' . $deal_id . '</p>';
        $message .= '<p><strong>👤 Клиент:</strong> ' . esc_html($client_name) . '</p>';
        $message .= '<p><strong>🚗 Автомобиль:</strong> ' . esc_html($car) . '</p>';
        $message .= '</div>';
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . admin_url('admin.php?page=akpp-crm-deal-form&id=' . $deal_id) . '" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 25px; display: inline-block;">📋 Перейти к сделке</a>';
        $message .= '</div>';
        $message .= '</div>';
        $message .= $this->get_template_footer();
        
        $this->send($email, $subject, $message);
    }
    
    /**
     * Уведомление об изменении статуса сделки (для клиента)
     */
    public function send_deal_status_changed($email, $name, $deal_id, $old_status, $new_status) {
        $status_labels = [
            'new' => 'Новая',
            'diagnostic' => 'Диагностика',
            'in_work' => 'В работе',
            'completed' => 'Выполнена',
            'rejected' => 'Отклонена'
        ];
        
        $old_label = $status_labels[$old_status] ?? $old_status;
        $new_label = $status_labels[$new_status] ?? $new_status;
        
        $subject = 'Изменение статуса сделки #' . $deal_id . ' - АКПП45 CRM';
        
        $message = $this->get_template_header('Изменение статуса сделки');
        $message .= '<div style="padding: 20px;">';
        $message .= '<h2 style="color: #667eea;">Здравствуйте, ' . esc_html($name) . '!</h2>';
        $message .= '<p>Статус вашей сделки был изменен.</p>';
        $message .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">';
        $message .= '<p><strong>📋 Номер сделки:</strong> #' . $deal_id . '</p>';
        $message .= '<p><strong>📊 Статус:</strong> ' . $old_label . ' → <span style="color: #667eea; font-weight: bold;">' . $new_label . '</span></p>';
        $message .= '</div>';
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . home_url('/crm-profile?tab=deals') . '" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 25px; display: inline-block;">📋 Мои сделки</a>';
        $message .= '</div>';
        $message .= '</div>';
        $message .= $this->get_template_footer();
        
        $this->send($email, $subject, $message);
    }
    
    /**
     * Уведомление о новом сообщении в чате
     */
    public function send_new_message($email, $name, $sender_name, $message_preview, $chat_url) {
        $subject = 'Новое сообщение от ' . $sender_name . ' - АКПП45 CRM';
        
        $message = $this->get_template_header('Новое сообщение');
        $message .= '<div style="padding: 20px;">';
        $message .= '<h2 style="color: #667eea;">Здравствуйте, ' . esc_html($name) . '!</h2>';
        $message .= '<p>Вы получили новое сообщение от <strong>' . esc_html($sender_name) . '</strong>:</p>';
        $message .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #667eea;">';
        $message .= '<p style="margin: 0;">" ' . esc_html($message_preview) . ' "</p>';
        $message .= '</div>';
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($chat_url) . '" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 25px; display: inline-block;">💬 Перейти в чат</a>';
        $message .= '</div>';
        $message .= '</div>';
        $message .= $this->get_template_footer();
        
        $this->send($email, $subject, $message);
    }
    
    /**
     * Отправка email
     */
    private function send($to, $subject, $message) {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: AKPP45 CRM/' . AKPP_CRM_VERSION
        ];
        
        $sent = wp_mail($to, $subject, $message, $headers);
        
        if (!$sent) {
            $this->log_error("Ошибка отправки email на {$to}");
        }
        
        return $sent;
    }
    
    /**
     * Получение HTML шапки письма
     */
    private function get_template_header($title) {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html($title) . '</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    margin: 0;
                    padding: 0;
                    background-color: #f5f5f5;
                }
                .email-container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                }
                .email-header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    padding: 30px;
                    text-align: center;
                    color: #ffffff;
                }
                .email-header h1 {
                    margin: 0;
                    font-size: 24px;
                }
                .email-header p {
                    margin: 10px 0 0;
                    opacity: 0.9;
                }
                .email-body {
                    padding: 20px;
                }
                .email-footer {
                    background-color: #f8f9fa;
                    padding: 20px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                    border-top: 1px solid #e9ecef;
                }
                .email-footer a {
                    color: #667eea;
                    text-decoration: none;
                }
            </style>
        </head>
        <body style="margin: 0; padding: 20px; background-color: #f5f5f5;">
            <div class="email-container">
                <div class="email-header">
                    <h1>🚗 АКПП45 CRM</h1>
                    <p>Система управления автосервисом</p>
                </div>
                <div class="email-body">';
    }
    
    /**
     * Получение HTML подвала письма
     */
    private function get_template_footer() {
        return '    </div>
                <div class="email-footer">
                    <p>© ' . date('Y') . ' АКПП45. Все права защищены.</p>
                    <p><a href="' . home_url() . '">' . get_bloginfo('name') . '</a> | 
                       <a href="' . home_url('/privacy-policy') . '">Политика конфиденциальности</a></p>
                    <p>Это письмо было отправлено автоматически. Пожалуйста, не отвечайте на него.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Сохранение настроек SMTP
     */
    public function save_smtp_settings($host, $port, $user, $password) {
        update_option('akpp_smtp_host', sanitize_text_field($host));
        update_option('akpp_smtp_port', intval($port));
        update_option('akpp_smtp_user', sanitize_email($user));
        update_option('akpp_smtp_password', $password);
        
        return true;
    }
    
    /**
     * Логирование ошибок
     */
    private function log_error($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_EMAIL] ОШИБКА: ' . $message);
        }
    }
}
