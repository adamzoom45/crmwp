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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php _e('Регистрация в АКПП45 CRM', 'akpp45-crm'); ?></title>
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
        .register-container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-header h1 {
            color: #333;
            font-size: 28px;
            margin: 0 0 10px 0;
        }
        .register-header p {
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
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
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
        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .login-link a {
            color: #667eea;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .required {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>🚗 Регистрация в АКПП45</h1>
            <p>Заполните форму для связи со специалистом</p>
        </div>
        
        <div id="message" class="message"></div>
        
        <form id="akpp-register-form">
            <?php wp_nonce_field('akpp_client_register_nonce', 'akpp_register_nonce'); ?>
            
            <div class="form-group">
                <label for="name">ФИО <span class="required">*</span></label>
                <input type="text" id="name" name="name" required placeholder="Иванов Иван Иванович">
            </div>
            
            <div class="form-group">
                <label for="phone">Телефон <span class="required">*</span></label>
                <input type="tel" id="phone" name="phone" required placeholder="+7 (999) 123-45-67">
            </div>
            
            <div class="form-group">
                <label for="email">Email <span class="required">*</span></label>
                <input type="email" id="email" name="email" required placeholder="ivan@example.com">
            </div>
            
            <div class="form-group">
                <label for="car_brand">Марка автомобиля</label>
                <input type="text" id="car_brand" name="car_brand" placeholder="Toyota, BMW, Mercedes...">
            </div>
            
            <div class="form-group">
                <label for="problem">Опишите проблему с АКПП</label>
                <textarea id="problem" name="problem" placeholder="Например: рывки при переключении, шум, отсутствие передач..."></textarea>
            </div>
            
            <button type="submit" class="btn-submit" id="register-btn">📝 Зарегистрироваться</button>
        </form>
        
        <div class="login-link">
            Уже есть аккаунт? <a href="<?php echo home_url('/crm-login'); ?>">Войти в CRM</a>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Маска для телефона
        $('#phone').on('input', function() {
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
            var messageDiv = $('#message');
            
            var name = $('#name').val().trim();
            var phone = $('#phone').val().trim();
            var email = $('#email').val().trim();
            var car_brand = $('#car_brand').val().trim();
            var problem = $('#problem').val().trim();
            
            // Валидация
            if (!name) {
                showMessage('Введите ФИО', 'error');
                return;
            }
            
            if (!phone) {
                showMessage('Введите номер телефона', 'error');
                return;
            }
            
            if (phone.length < 10) {
                showMessage('Введите корректный номер телефона', 'error');
                return;
            }
            
            if (!email) {
                showMessage('Введите email', 'error');
                return;
            }
            
            if (!validateEmail(email)) {
                showMessage('Введите корректный email адрес', 'error');
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
                            window.location.href = '<?php echo home_url('/crm-login'); ?>';
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
