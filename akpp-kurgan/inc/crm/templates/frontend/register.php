<?php
if (!defined('ABSPATH')) exit;

// Если пользователь уже авторизован, перенаправляем его в профиль или показываем сообщение
if (is_user_logged_in()) {
    echo '<div class="akpp-frontend-wrap">';
    echo '<div class="akpp-form-card" style="text-align: center;">';
    echo '<h3>Вы уже зарегистрированы</h3>';
    echo '<p class="akpp-text-muted">Зачем регистрироваться повторно?</p>';
    echo '<a href="' . esc_url(home_url('/profile/')) . '" class="akpp-btn">Перейти в личный кабинет</a>';
    echo '</div>';
    echo '</div>';
    return;
}

// URL для AJAX запросов и nonce (передаются в JS через wp_localize_script, но продублируем для надежности в data-атрибутах)
$ajax_url = admin_url('admin-ajax.php');
$nonce = wp_create_nonce('akpp_crm_nonce');
?>

<div class="akpp-frontend-wrap">
    <div class="akpp-form-card">
        <h3>Регистрация клиента</h3>
        <p style="text-align: center; color: var(--akpp-f-text-muted); margin-bottom: 25px; font-size: 14px;">
            Создайте аккаунт, чтобы отслеживать статус ремонта вашего автомобиля и общаться с мастером.
        </p>
        
        <form id="akpp-register-form" class="akpp-form">
            <div class="form-group">
                <label for="reg_full_name">ФИО *</label>
                <input type="text" id="reg_full_name" name="full_name" required placeholder="Иванов Иван Иванович">
            </div>

            <div class="form-group">
                <label for="reg_phone">Телефон *</label>
                <input type="tel" id="reg_phone" name="phone" required placeholder="+7 (999) 123-45-67">
            </div>

            <div class="form-group">
                <label for="reg_email">Email *</label>
                <input type="email" id="reg_email" name="email" required placeholder="example@mail.ru">
                <small class="akpp-text-muted" style="font-size: 12px; margin-top: 5px; display: block;">
                    На этот адрес будут отправлены данные для входа.
                </small>
            </div>

            <div class="form-group">
                <label for="reg_car_info">Марка и модель авто</label>
                <input type="text" id="reg_car_info" name="car_info" placeholder="Например: Toyota Camry 2018, 2.5L">
            </div>

            <div class="form-group">
                <label for="reg_problem">Описание проблемы</label>
                <textarea id="reg_problem" name="problem" rows="3" placeholder="Кратко опишите симптомы: пинки, течь масла, ошибки и т.д."></textarea>
            </div>

            <button type="submit" class="akpp-btn">Зарегистрироваться</button>
            
            <!-- Контейнер для сообщений об успехе или ошибке -->
            <div class="akpp-form-message"></div>
        </form>

        <div style="text-align: center; margin-top: 20px; font-size: 14px; color: var(--akpp-f-text-muted);">
            Уже есть аккаунт? <a href="<?php echo esc_url(home_url('/login/')); ?>" style="color: var(--akpp-f-accent); text-decoration: none; font-weight: 600;">Войти</a>
        </div>
    </div>
</div>

<!-- Скрытые поля для передачи конфигурации в JS (если wp_localize_script не сработал) -->
<script>
    if (typeof akppCRM === 'undefined') {
        var akppCRM = {
            ajax_url: '<?php echo esc_js($ajax_url); ?>',
            nonce: '<?php echo esc_js($nonce); ?>'
        };
    }
</script>
