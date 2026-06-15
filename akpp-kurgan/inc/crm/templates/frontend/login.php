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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Вход - АКПП45 CRM', 'akpp45-crm'); ?></title>
    <?php wp_head(); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 20px;
        }
        
        .login-container {
            max-width: 460px;
            width: 100%;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            overflow: hidden;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 35px 30px;
            text-align: center;
            color: #fff;
        }
        
        .login-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .login-form {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        
        .form-group .required {
            color: #e74c3c;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .checkbox-group input {
            width: 18px;
            height: 18px;
            margin: 0;
        }
        
        .checkbox-group label {
            margin: 0;
            font-weight: normal;
            color: #666;
        }
        
        .forgot-password {
            text-align: right;
            margin-top: -10px;
            margin-bottom: 20px;
        }
        
        .forgot-password a {
            color: #999;
            font-size: 13px;
            text-decoration: none;
        }
        
        .forgot-password a:hover {
            color: #667eea;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }
        
        .btn-login:disabled {
            opacity: 0.6;
            transform: none;
            cursor: not-allowed;
        }
        
        .message {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
            font-size: 14px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }
        
        .register-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .login-header {
                padding: 25px 20px;
            }
            
            .login-form {
                padding: 20px;
            }
            
            .login-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 Вход в CRM</h1>
            <p>Введите свои данные для доступа</p>
        </div>
        
        <div class="login-form">
            <div id="login-message" class="message"></div>
            
            <form id="akpp-login-form" method="post">
                <?php wp_nonce_field('akpp_client_login_nonce', 'akpp_login_nonce'); ?>
                
                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" placeholder="ivan@example.com" 
                           value="<?php echo isset($_COOKIE['akpp_remember_email']) ? esc_attr($_COOKIE['akpp_remember_email']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Пароль <span class="required">*</span></label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                
                <div class="forgot-password">
                    <a href="#" id="forgot-password-link">Забыли пароль?</a>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Запомнить меня</label>
                </div>
                
                <button type="submit" class="btn-login" id="login-btn">
                    🚀 Войти в CRM
                </button>
            </form>
            
            <div class="register-link">
                Нет аккаунта? <a href="<?php echo home_url('/crm-register'); ?>">Зарегистрироваться</a>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно восстановления пароля -->
    <div id="reset-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: #fff; max-width: 400px; width: 90%; border-radius: 16px; overflow: hidden;">
            <div style="padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff;">
                <h3 style="margin: 0;">Восстановление пароля</h3>
            </div>
            <div style="padding: 20px;">
                <p>Введите email, указанный при регистрации. Новый пароль будет отправлен на почту.</p>
                <div class="form-group" style="margin-top: 15px;">
                    <input type="email" id="reset-email" placeholder="Email" style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ddd;">
                </div>
                <div id="reset-message" style="margin: 15px 0; font-size: 13px;"></div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button id="reset-cancel" class="button" style="padding: 10px 20px; background: #f0f2f5; border: none; border-radius: 8px; cursor: pointer;">Отмена</button>
                    <button id="reset-submit" class="button" style="padding: 10px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; border-radius: 8px; cursor: pointer;">Отправить</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var resetModal = $('#reset-modal');
        
        // Автофокус
        if (!$('#email').val()) {
            $('#email').focus();
        } else {
            $('#password').focus();
        }
        
        // Вход по Enter
        $('#password').on('keypress', function(e) {
            if (e.which === 13) {
                $('#akpp-login-form').submit();
            }
        });
        
        // Открытие модального окна восстановления
        $('#forgot-password-link').on('click', function(e) {
            e.preventDefault();
            resetModal.css('display', 'flex');
            $('#reset-email').val($('#email').val()).focus();
        });
        
        // Закрытие модального окна
        $('#reset-cancel, #reset-modal').on('click', function(e) {
            if (e.target === this) {
                resetModal.hide();
                $('#reset-message').empty();
            }
        });
        
        // Отправка запроса на восстановление
        $('#reset-submit').on('click', function() {
            var email = $('#reset-email').val().trim();
            var messageDiv = $('#reset-message');
            var submitBtn = $(this);
            
            if (!email) {
                messageDiv.html('<span style="color: #e74c3c;">Введите email</span>');
                return;
            }
            
            submitBtn.prop('disabled', true).text('⏳ Отправка...');
            messageDiv.empty();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_reset_password',
                    email: email,
                    nonce: '<?php echo wp_create_nonce("akpp_reset_password_nonce"); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        messageDiv.html('<span style="color: #27ae60;">✅ ' + response.data.message + '</span>');
                        setTimeout(function() {
                            resetModal.hide();
                            $('#reset-message').empty();
                        }, 3000);
                    } else {
                        messageDiv.html('<span style="color: #e74c3c;">❌ ' + response.data.message + '</span>');
                    }
                    submitBtn.prop('disabled', false).text('Отправить');
                },
                error: function() {
                    messageDiv.html('<span style="color: #e74c3c;">❌ Ошибка соединения</span>');
                    submitBtn.prop('disabled', false).text('Отправить');
                }
            });
        });
        
        // Отправка формы входа
        $('#akpp-login-form').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var submitBtn = $('#login-btn');
            var messageDiv = $('#login-message');
            
            var email = $('#email').val().trim();
            var password = $('#password').val();
            var remember = $('#remember').is(':checked') ? 1 : 0;
            
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
                        
                        if (remember) {
                            document.cookie = "akpp_remember_email=" + encodeURIComponent(email) + "; path=/; max-age=" + (30 * 24 * 60 * 60);
                        } else {
                            document.cookie = "akpp_remember_email=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC";
                        }
                        
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url || '<?php echo home_url("/crm-profile"); ?>';
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
        
        function showMessage(msg, type) {
            var messageDiv = $('#login-message');
            messageDiv.removeClass('success error').addClass(type).html('<p>' + msg + '</p>').show();
            setTimeout(function() {
                messageDiv.fadeOut();
            }, 5000);
        }
    });
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>
