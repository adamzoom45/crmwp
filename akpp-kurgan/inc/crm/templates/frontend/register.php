<?php
/**
 * Шаблон страницы регистрации клиента
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
    <title><?php _e('Регистрация - АКПП45 CRM', 'akpp45-crm'); ?></title>
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
        
        .register-container {
            max-width: 520px;
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
        
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 35px 30px;
            text-align: center;
            color: #fff;
        }
        
        .register-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .register-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .register-form {
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
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 90px;
        }
        
        .btn-register {
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
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }
        
        .btn-register:disabled {
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
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .form-hint {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
        }
        
        @media (max-width: 480px) {
            .register-header {
                padding: 25px 20px;
            }
            
            .register-form {
                padding: 20px;
            }
            
            .register-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>🚗 Регистрация</h1>
            <p>Заполните форму для связи со специалистом</p>
        </div>
        
        <div class="register-form">
            <div id="register-message" class="message"></div>
            
            <form id="akpp-register-form" method="post">
                <?php wp_nonce_field('akpp_client_register_nonce', 'akpp_register_nonce'); ?>
                
                <div class="form-group">
                    <label for="name">ФИО <span class="required">*</span></label>
                    <input type="text" id="name" name="name" placeholder="Иванов Иван Иванович" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Телефон <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" placeholder="+7 (___) ___-__-__" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" placeholder="ivan@example.com" required>
                </div>
                
                <div class="form-group">
                    <label for="car_brand">Марка автомобиля</label>
                    <input type="text" id="car_brand" name="car_brand" placeholder="Toyota, BMW, Mercedes...">
                </div>
                
                <div class="form-group">
                    <label for="problem">Опишите проблему с АКПП</label>
                    <textarea id="problem" name="problem" placeholder="Например: рывки при переключении, шум, отсутствие передач..."></textarea>
                </div>
                
                <button type="submit" class="btn-register" id="register-btn">
                    📝 Зарегистрироваться
                </button>
            </form>
            
            <div class="login-link">
                Уже есть аккаунт? <a href="<?php echo home_url('/crm-login'); ?>">Войти в CRM</a>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var phoneInput = $('#phone');
        
        // Маска для телефона
        phoneInput.on('input', function() {
            var value = $(this).val().replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            
            if (value.length === 11) {
                value = '+7 (' + value.slice(1, 4) + ') ' + value.slice(4, 7) + '-' + value.slice(7, 9) + '-' + value.slice(9, 11);
            } else if (value.length > 0) {
                value = '+7 ' + value;
            }
            
            $(this).val(value);
        });
        
        // Валидация email
        function validateEmail(email) {
            var re = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
            return re.test(email);
        }
        
        // Отправка формы
        $('#akpp-register-form').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var submitBtn = $('#register-btn');
            var messageDiv = $('#register-message');
            
            var name = $('#name').val().trim();
            var phone = $('#phone').val().trim();
            var email = $('#email').val().trim();
            var car_brand = $('#car_brand').val().trim();
            var problem = $('#problem').val().trim();
            
            // Валидация
            if (!name) {
                showMessage('Введите ФИО', 'error');
                $('#name').focus();
                return;
            }
            
            if (!phone) {
                showMessage('Введите номер телефона', 'error');
                $('#phone').focus();
                return;
            }
            
            if (phone.length < 10) {
                showMessage('Введите корректный номер телефона', 'error');
                $('#phone').focus();
                return;
            }
            
            if (!email) {
                showMessage('Введите email', 'error');
                $('#email').focus();
                return;
            }
            
            if (!validateEmail(email)) {
                showMessage('Введите корректный email адрес', 'error');
                $('#email').focus();
                return;
            }
            
            submitBtn.prop('disabled', true).text('⏳ Регистрация...');
            messageDiv.hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_register',
                    name: name,
                    phone: phone,
                    email: email,
                    car_brand: car_brand,
                    problem: problem,
                    nonce: $('#akpp_register_nonce').val()
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        form[0].reset();
                        setTimeout(function() {
                            window.location.href = '<?php echo home_url("/crm-login"); ?>';
                        }, 2000);
                    } else {
                        showMessage(response.data.message, 'error');
                        submitBtn.prop('disabled', false).text('📝 Зарегистрироваться');
                    }
                },
                error: function() {
                    showMessage('Ошибка соединения с сервером', 'error');
                    submitBtn.prop('disabled', false).text('📝 Зарегистрироваться');
                }
            });
        });
        
        function showMessage(msg, type) {
            var messageDiv = $('#register-message');
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
