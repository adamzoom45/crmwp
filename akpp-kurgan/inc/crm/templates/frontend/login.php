<?php
if (!defined('ABSPATH')) exit;

// Если пользователь уже авторизован, показываем сообщение и ссылку на профиль
if (is_user_logged_in()) {
    echo '<div class="akpp-frontend-wrap">';
    echo '<div class="akpp-form-card" style="text-align: center;">';
    echo '<h3>Вы уже вошли в систему</h3>';
    echo '<p class="akpp-text-muted" style="margin-bottom: 25px;">Добро пожаловать в личный кабинет!</p>';
    echo '<a href="' . esc_url(home_url('/profile/')) . '" class="akpp-btn">Перейти в профиль</a>';
    echo '</div>';
    echo '</div>';
    return;
}

// URL для AJAX запросов и nonce
$ajax_url = admin_url('admin-ajax.php');
$nonce = wp_create_nonce('akpp_crm_nonce');
?>

<div class="akpp-frontend-wrap">
    <div class="akpp-form-card">
        <h3>Вход в личный кабинет</h3>
        <p style="text-align: center; color: var(--akpp-f-text-muted); margin-bottom: 25px; font-size: 14px;">
            Введите свои данные, чтобы отслеживать статус ремонта и общаться с мастерами.
        </p>
        
        <form id="akpp-login-form" class="akpp-form">
            <div class="form-group">
                <label for="login_email">Email или Логин *</label>
                <input type="text" id="login_email" name="login" required placeholder="example@mail.ru или username">
            </div>

            <div class="form-group">
                <label for="login_password">Пароль *</label>
                <input type="password" id="login_password" name="password" required placeholder="Введите ваш пароль">
            </div>

            <div class="form-group" style="display: flex; align-items: center; justify-content: space-between;">
                <label for="login_remember" style="display: flex; align-items: center; margin: 0; cursor: pointer; color: var(--akpp-f-text);">
                    <input type="checkbox" id="login_remember" name="remember" value="1" style="margin-right: 8px; width: 16px; height: 16px; accent-color: var(--akpp-f-accent);">
                    Запомнить меня
                </label>
                <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" style="font-size: 13px; color: var(--akpp-f-accent); text-decoration: none;">
                    Забыли пароль?
                </a>
            </div>

            <button type="submit" class="akpp-btn" style="margin-top: 10px;">Войти</button>
            
            <!-- Контейнер для сообщений об успехе или ошибке -->
            <div class="akpp-form-message"></div>
        </form>

        <div style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--akpp-f-border); font-size: 14px; color: var(--akpp-f-text-muted);">
            Нет аккаунта? <a href="<?php echo esc_url(home_url('/register/')); ?>" style="color: var(--akpp-f-accent); text-decoration: none; font-weight: 600;">Зарегистрироваться</a>
        </div>
    </div>
</div>

<!-- Скрытые поля для передачи конфигурации в JS (fallback) -->
<script>
    if (typeof akppCRM === 'undefined') {
        var akppCRM = {
            ajax_url: '<?php echo esc_js($ajax_url); ?>',
            nonce: '<?php echo esc_js($nonce); ?>'
        };
    }
</script>
