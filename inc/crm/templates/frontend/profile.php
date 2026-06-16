<?php
/**
 * Шаблон страницы личного кабинета пользователя
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

// Проверка авторизации
if (!class_exists('AKPP_Auth') || !AKPP_Auth::is_logged_in()) {
    wp_redirect(home_url('/crm-login'));
    exit;
}

$current_user = AKPP_Auth::get_current_user();

global $wpdb;
$table_deals = $wpdb->prefix . 'akpp_deals';
$user_deals = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table_deals} WHERE client_id = %d ORDER BY created_at DESC LIMIT 10",
    $current_user->id
));
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Личный кабинет - АКПП45 CRM', 'akpp45-crm'); ?></title>
    <?php wp_head(); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f0f2f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
        }
        
        .profile-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        /* Шапка профиля */
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 30px;
            color: #fff;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .profile-info h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .profile-info p {
            opacity: 0.9;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            color: #fff;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 14px;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        /* Табы */
        .profile-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            background: #fff;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s;
            color: #333;
        }
        
        .tab-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        
        /* Карточки */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .profile-card {
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .profile-card h3 {
            margin-bottom: 20px;
            color: #333;
            font-size: 18px;
            border-left: 4px solid #667eea;
            padding-left: 12px;
        }
        
        /* Формы */
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: transform 0.2s;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
        }
        
        /* Таблица сделок */
        .deals-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .deals-table th,
        .deals-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .deals-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .status-new { background: #e3f2fd; color: #1976d2; }
        .status-diagnostic { background: #fff3e0; color: #f57c00; }
        .status-in_work { background: #e8f5e9; color: #388e3c; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        
        .message {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            display: block;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            display: block;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .deals-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <!-- Шапка профиля -->
        <div class="profile-header">
            <div class="profile-info">
                <h1>👋 Добро пожаловать, <?php echo esc_html($current_user->name); ?></h1>
                <p>Личный кабинет клиента АКПП45 CRM</p>
            </div>
            <button id="logout-btn" class="logout-btn">🚪 Выйти из системы</button>
        </div>
        
        <!-- Табы -->
        <div class="profile-tabs">
            <button class="tab-btn active" data-tab="profile">👤 Профиль</button>
            <button class="tab-btn" data-tab="deals">📋 Мои сделки</button>
            <button class="tab-btn" data-tab="security">🔒 Безопасность</button>
        </div>
        
        <!-- Вкладка: Профиль -->
        <div id="tab-profile" class="tab-content active">
            <div class="profile-card">
                <h3>📝 Личная информация</h3>
                <div id="profile-message" class="message"></div>
                <form id="profile-form">
                    <?php wp_nonce_field('akpp_update_profile_nonce', 'profile_nonce'); ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="profile-name">ФИО</label>
                            <input type="text" id="profile-name" name="name" value="<?php echo esc_attr($current_user->name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="profile-email">Email</label>
                            <input type="email" id="profile-email" value="<?php echo esc_attr($current_user->email); ?>" disabled>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="profile-phone">Телефон</label>
                            <input type="tel" id="profile-phone" name="phone" value="<?php echo esc_attr($current_user->phone); ?>">
                        </div>
                        <div class="form-group">
                            <label for="profile-car">Марка автомобиля</label>
                            <input type="text" id="profile-car" name="car_brand" value="<?php echo esc_attr($current_user->car_brand); ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn-save">💾 Сохранить изменения</button>
                </form>
            </div>
        </div>
        
        <!-- Вкладка: Сделки -->
        <div id="tab-deals" class="tab-content">
            <div class="profile-card">
                <h3>📋 История сделок</h3>
                <?php if ($user_deals): ?>
                    <table class="deals-table">
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>Автомобиль</th>
                                <th>Сумма</th>
                                <th>Статус</th>
                                <th>Дата</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_deals as $deal): ?>
                                <tr>
                                    <td>#<?php echo $deal->id; ?></td>
                                    <td><?php echo esc_html($deal->make . ' ' . $deal->model); ?></td>
                                    <td><?php echo number_format($deal->total_amount, 0, ',', ' ') . ' ₽'; ?></td>
                                    <td>
                                        <?php
                                        $status_labels = [
                                            'new' => 'Новая',
                                            'diagnostic' => 'Диагностика',
                                            'in_work' => 'В работе',
                                            'completed' => 'Выполнена',
                                            'rejected' => 'Отклонена'
                                        ];
                                        $status_class = 'status-' . $deal->status;
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_labels[$deal->status] ?? $deal->status; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date_i18n('d.m.Y', strtotime($deal->created_at)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: #999; padding: 30px;">У вас пока нет сделок</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Вкладка: Безопасность -->
        <div id="tab-security" class="tab-content">
            <div class="profile-card">
                <h3>🔒 Смена пароля</h3>
                <div id="password-message" class="message"></div>
                <form id="password-form">
                    <?php wp_nonce_field('akpp_update_password_nonce', 'password_nonce'); ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="old-password">Текущий пароль</label>
                            <input type="password" id="old-password" name="old_password" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new-password">Новый пароль</label>
                            <input type="password" id="new-password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm-password">Подтверждение пароля</label>
                            <input type="password" id="confirm-password" name="confirm_password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-save">🔑 Сменить пароль</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Переключение табов
        $('.tab-btn').on('click', function() {
            var tab = $(this).data('tab');
            $('.tab-btn').removeClass('active');
            $(this).addClass('active');
            $('.tab-content').removeClass('active');
            $('#tab-' + tab).addClass('active');
        });
        
        // Обновление профиля
        $('#profile-form').on('submit', function(e) {
            e.preventDefault();
            
            var submitBtn = $(this).find('.btn-save');
            var messageDiv = $('#profile-message');
            
            submitBtn.prop('disabled', true).text('⏳ Сохранение...');
            messageDiv.hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_update_profile',
                    name: $('#profile-name').val(),
                    phone: $('#profile-phone').val(),
                    car_brand: $('#profile-car').val(),
                    nonce: $('#profile_nonce').val()
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        messageDiv.removeClass('error').addClass('success').html('<p>' + response.data.message + '</p>').show();
                        setTimeout(function() { messageDiv.fadeOut(); }, 3000);
                    } else {
                        messageDiv.removeClass('success').addClass('error').html('<p>' + response.data.message + '</p>').show();
                    }
                    submitBtn.prop('disabled', false).text('💾 Сохранить изменения');
                },
                error: function() {
                    messageDiv.removeClass('success').addClass('error').html('<p>Ошибка соединения</p>').show();
                    submitBtn.prop('disabled', false).text('💾 Сохранить изменения');
                }
            });
        });
        
        // Смена пароля
        $('#password-form').on('submit', function(e) {
            e.preventDefault();
            
            var oldPass = $('#old-password').val();
            var newPass = $('#new-password').val();
            var confirmPass = $('#confirm-password').val();
            var submitBtn = $(this).find('.btn-save');
            var messageDiv = $('#password-message');
            
            if (newPass !== confirmPass) {
                messageDiv.removeClass('success').addClass('error').html('<p>Пароли не совпадают</p>').show();
                return;
            }
            
            if (newPass.length < 6) {
                messageDiv.removeClass('success').addClass('error').html('<p>Пароль должен содержать не менее 6 символов</p>').show();
                return;
            }
            
            submitBtn.prop('disabled', true).text('⏳ Сохранение...');
            messageDiv.hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_update_password',
                    old_password: oldPass,
                    new_password: newPass,
                    confirm_password: confirmPass,
                    nonce: $('#password_nonce').val()
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        messageDiv.removeClass('error').addClass('success').html('<p>' + response.data.message + '</p>').show();
                        $('#password-form')[0].reset();
                        setTimeout(function() { messageDiv.fadeOut(); }, 3000);
                    } else {
                        messageDiv.removeClass('success').addClass('error').html('<p>' + response.data.message + '</p>').show();
                    }
                    submitBtn.prop('disabled', false).text('🔑 Сменить пароль');
                },
                error: function() {
                    messageDiv.removeClass('success').addClass('error').html('<p>Ошибка соединения</p>').show();
                    submitBtn.prop('disabled', false).text('🔑 Сменить пароль');
                }
            });
        });
        
        // Выход из системы
        $('#logout-btn').on('click', function() {
            if (confirm('Вы уверены, что хотите выйти?')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'akpp_logout',
                        nonce: '<?php echo wp_create_nonce("akpp_logout_nonce"); ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.redirect_url;
                        }
                    }
                });
            }
        });
    });
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>
