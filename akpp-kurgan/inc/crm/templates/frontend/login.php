<?php
/**
 * Шаблон страницы входа в CRM
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php _e('Вход в АКПП45 CRM', 'akpp45-crm'); ?></title>
    <?php wp_head(); ?>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .login-container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 450px;
            width: 100%;
            padding: 40px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: #333;
            font-size: 28px;
            margin: 0 0 10px 0;
        }
        .login-header p {
            color: #666;
            margin: 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .register-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .register-link a {
            color: #667eea;
            text-decoration: none;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        .forgot-password {
            text-align: right;
            margin-top: -15px;
            margin-bottom: 20px;
        }
        .forgot-password a {
            color: #999;
            font-size: 12px;
            text-decoration: none;
        }
        .forgot-password a:hover {
            color: #667eea;
        }
        .required {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 Вход в CRM</h1>
            <p>Введите свои данные для доступа</p>
        </div>
        
        <div id="message" class="message"></div>
        
        <form id="akpp-login-form">
            <?php wp_nonce_field('akpp_client_login_nonce', 'akpp_login_nonce'); ?>
            
            <div class="form-group">
                <label for="email">Email <span class="required">*</span></label>
                <input type="email" id="email" name="email" required placeholder="ivan@example.com" value="<?php echo isset($_COOKIE['akpp_remember_email']) ? esc_attr($_COOKIE['akpp_remember_email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Пароль <span class="required">*</span></label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>
            
            <div class="forgot-password">
                <a href="#">Забыли пароль?</a>
            </div>
            
            <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" id="remember" name="remember" style="width: auto;">
                <label for="remember" style="margin: 0;">Запомнить меня</label>
            </div>
            
            <button type="submit" class="btn-submit" id="login-btn">🚀 Войти в CRM</button>
        </form>
        
        <div class="register-link">
            Нет аккаунта? <a href="<?php echo home_url('/crm-register'); ?>">Зарегистрироваться</a>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        
        // Автоматическая фокусировка на поле email если оно пустое
        if (!$('#email').val()) {
            $('#email').focus();
        } else {
            $('#password').focus();
        }
        
        // Отправка формы
        $('#akpp-login-form').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var submitBtn = $('#login-btn');
            var messageDiv = $('#message');
            
            var email = $('#email').val().trim();
            var password = $('#password').val();
            var remember = $('#remember').is(':checked') ? 1 : 0;
            
            // Валидация
            if (!email) {
                showMessage('Введите email', 'error');
                $('#email').focus();
                return;
            }
            
            if (!password) {
                showMessage('Введите пароль', 'error');
                $('#password').focus();
                return;
            }
            
            submitBtn.prop('disabled', true).text('⏳ Вход...');
            messageDiv.hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_login',
                    email: email,
                    password: password,
                    remember: remember,
                    nonce: $('#akpp_login_nonce').val()
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        
                        // Сохраняем email в cookie если выбран "Запомнить меня"
                        if (remember) {
                            document.cookie = "akpp_remember_email=" + encodeURIComponent(email) + "; path=/; max-age=" + (30 * 24 * 60 * 60);
                        } else {
                            document.cookie = "akpp_remember_email=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC";
                        }
                        
                        // Перенаправление
                        setTimeout(function() {
                            window.location.href = '<?php echo home_url('/crm-profile'); ?>';
                        }, 1500);
                    } else {
                        showMessage(response.data.message, 'error');
                        submitBtn.prop('disabled', false).text('🚀 Войти в CRM');
                        $('#password').val('').focus();
                    }
                },
                error: function() {
                    showMessage('Ошибка соединения с сервером', 'error');
                    submitBtn.prop('disabled', false).text('🚀 Войти в CRM');
                }
            });
        });
        
        // Вход по Enter
        $('#password').on('keypress', function(e) {
            if (e.which === 13) {
                $('#akpp-login-form').submit();
            }
        });
        
        function showMessage(msg, type) {
            var messageDiv = $('#message');
            messageDiv.removeClass('success error').addClass(type).html(msg).show();
            setTimeout(function() {
                messageDiv.fadeOut();
            }, 5000);
        }
    });
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>
