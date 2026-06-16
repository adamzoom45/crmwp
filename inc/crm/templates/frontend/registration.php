<?php
/**
 * АКПП45 CRM - Форма регистрации клиента (Frontend)
 * Шаблон формы с валидацией, защитой от спама и AJAX-отправкой.
 *
 * @package AKPP_CRM
 * @version 4.2
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

// Если пользователь уже авторизован, показываем сообщение вместо формы
if (is_user_logged_in()) {
    $current_user = wp_get_current_user();
    ?>
    <div class="akpp-reg-already-logged-in">
        <h3><?php _e('Вы уже зарегистрированы', 'akpp-crm'); ?></h3>
        <p>
            <?php printf(__('Добро пожаловать, <strong>%s</strong>! Вы уже вошли в систему.', 'akpp-crm'), esc_html($current_user->display_name)); ?>
        </p>
        <a href="<?php echo esc_url(get_permalink(get_page_by_path('chat'))); ?>" class="button button-primary">
            <?php _e('Перейти в чат', 'akpp-crm'); ?>
        </a>
    </div>
    <?php
    return;
}

// Генерация nonce для безопасности AJAX-запроса
$reg_nonce = wp_create_nonce('akpp_registration_nonce');
?>

<div class="akpp-registration-container">
    <div class="akpp-reg-header">
        <h2><?php _e('Регистрация в личном кабинете', 'akpp-crm'); ?></h2>
        <p><?php _e('Заполните форму, чтобы получить доступ к истории ремонтов, статусу сделок и чату с мастером.', 'akpp-crm'); ?></p>
    </div>

    <!-- Сообщение об успехе/ошибке (скрыто по умолчанию) -->
    <div id="akpp-reg-message" class="akpp-reg-message" style="display: none;"></div>

    <form id="akpp-registration-form" class="akpp-reg-form" novalidate>
        <?php wp_nonce_field('akpp_registration_nonce', 'security_nonce'); ?>
        <input type="hidden" name="action" value="akpp_register_client">

        <!-- Honeypot для защиты от спам-ботов (скрыт через CSS) -->
        <div class="akpp-honeypot" aria-hidden="true">
            <label for="website_check"><?php _e('Оставьте это поле пустым', 'akpp-crm'); ?></label>
            <input type="text" name="website_check" id="website_check" tabindex="-1" autocomplete="off">
        </div>

        <div class="akpp-form-row">
            <div class="akpp-form-group">
                <label for="reg_full_name"><?php _e('ФИО', 'akpp-crm'); ?> <span class="required">*</span></label>
                <input type="text" name="full_name" id="reg_full_name" required 
                       placeholder="<?php _e('Иванов Иван Иванович', 'akpp-crm'); ?>" 
                       pattern="[А-Яа-яЁёA-Za-z\s\-]{2,50}" 
                       title="<?php _e('Введите корректное ФИО (минимум 2 символа)', 'akpp-crm'); ?>">
            </div>
        </div>

        <div class="akpp-form-row akpp-form-row-2col">
            <div class="akpp-form-group">
                <label for="reg_email"><?php _e('Email', 'akpp-crm'); ?> <span class="required">*</span></label>
                <input type="email" name="email" id="reg_email" required 
                       placeholder="example@mail.ru">
            </div>
            <div class="akpp-form-group">
                <label for="reg_phone"><?php _e('Телефон', 'akpp-crm'); ?> <span class="required">*</span></label>
                <input type="tel" name="phone" id="reg_phone" required 
                       placeholder="+7 (999) 123-45-67" 
                       pattern="[\+]?[0-9\s\-\(\)]{10,20}">
            </div>
        </div>

        <div class="akpp-form-row">
            <div class="akpp-form-group">
                <label for="reg_car_info"><?php _e('Марка и модель автомобиля', 'akpp-crm'); ?></label>
                <input type="text" name="car_info" id="reg_car_info" 
                       placeholder="<?php _e('Например: Toyota Camry 2018, VIN: ...', 'akpp-crm'); ?>">
                <small class="akpp-form-hint"><?php _e('Необязательно, но поможет мастеру подготовиться к вашему обращению.', 'akpp-crm'); ?></small>
            </div>
        </div>

        <div class="akpp-form-row akpp-form-actions">
            <button type="submit" class="button button-primary button-large" id="akpp-reg-submit-btn">
                <span class="btn-text"><?php _e('Зарегистрироваться', 'akpp-crm'); ?></span>
                <span class="btn-loader spinner is-active" style="display: none; float: none; margin: 0 10px 0 0; box-shadow: none;"></span>
            </button>
        </div>

        <div class="akpp-form-footer">
            <p>
                <?php _e('Уже есть аккаунт?', 'akpp-crm'); ?> 
                <a href="<?php echo esc_url(wp_login_url()); ?>"><?php _e('Войти', 'akpp-crm'); ?></a>
            </p>
            <p class="akpp-privacy-hint">
                <?php _e('Нажимая кнопку, вы соглашаетесь с обработкой персональных данных.', 'akpp-crm'); ?>
            </p>
        </div>
    </form>
</div>

<!-- Конфигурация и логика для JS -->
<script type="text/javascript">
(function($) {
    'use strict';

    const $form = $('#akpp-registration-form');
    const $submitBtn = $('#akpp-reg-submit-btn');
    const $btnText = $submitBtn.find('.btn-text');
    const $btnLoader = $submitBtn.find('.btn-loader');
    const $messageBox = $('#akpp-reg-message');

    // Функция показа сообщений
    function showMessage(type, text) {
        $messageBox.removeClass('akpp-reg-success akpp-reg-error')
                   .addClass(type === 'success' ? 'akpp-reg-success' : 'akpp-reg-error')
                   .text(text)
                   .slideDown(300);
        
        // Прокрутка к сообщению
        $('html, body').animate({
            scrollTop: $messageBox.offset().top - 50
        }, 500);
    }

    $form.on('submit', function(e) {
        e.preventDefault();

        // Проверка honeypot (если бот его заполнил, молча игнорируем)
        if ($('#website_check').val() !== '') {
            return false;
        }

        // Блокировка кнопки и показ лоадера
        $submitBtn.prop('disabled', true);
        $btnText.hide();
        $btnLoader.show();
        $messageBox.slideUp(200);

        // Сбор данных формы
        const formData = new FormData(this);

        // AJAX запрос
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.data.message);
                    $form[0].reset(); // Очистка формы
                    
                    // Редирект через 3 секунды, если указан URL
                    if (response.data.redirect_url) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 3000);
                    }
                } else {
                    showMessage('error', response.data.message || '<?php _e('Произошла ошибка при регистрации.', 'akpp-crm'); ?>');
                }
            },
            error: function() {
                showMessage('error', '<?php _e('Ошибка соединения с сервером. Попробуйте позже.', 'akpp-crm'); ?>');
            },
            complete: function() {
                // Разблокировка кнопки
                $submitBtn.prop('disabled', false);
                $btnText.show();
                $btnLoader.hide();
            }
        });
    });

})(jQuery);
</script>

<style>
    /* Базовые стили формы (можно вынести в отдельный CSS файл) */
    .akpp-registration-container {
        max-width: 600px;
        margin: 0 auto;
        background: #fff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .akpp-reg-header h2 { margin-top: 0; color: #1d2327; }
    .akpp-reg-header p { color: #646970; margin-bottom: 25px; }
    
    .akpp-form-row { margin-bottom: 20px; }
    .akpp-form-row-2col { display: flex; gap: 20px; }
    .akpp-form-row-2col .akpp-form-group { flex: 1; }
    
    .akpp-form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #1d2327; }
    .akpp-form-group input { width: 100%; padding: 10px 12px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px; }
    .akpp-form-group input:focus { border-color: #2271b1; outline: 2px solid #2271b1; outline-offset: -1px; }
    .akpp-form-hint { display: block; margin-top: 5px; color: #646970; font-size: 12px; }
    .required { color: #d63638; }
    
    .akpp-honeypot { display: none !important; }
    
    .akpp-reg-message { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: 500; }
    .akpp-reg-success { background: #edfaef; color: #00a32a; border: 1px solid #00a32a; }
    .akpp-reg-error { background: #fcf0f1; color: #d63638; border: 1px solid #d63638; }
    
    .akpp-form-actions { margin-top: 30px; }
    .akpp-form-actions .button { width: 100%; display: flex; align-items: center; justify-content: center; }
    
    .akpp-form-footer { margin-top: 20px; text-align: center; font-size: 14px; color: #646970; }
    .akpp-form-footer a { color: #2271b1; text-decoration: none; }
    .akpp-form-footer a:hover { text-decoration: underline; }
    .akpp-privacy-hint { font-size: 12px; margin-top: 10px; color: #8c8f94; }

    @media (max-width: 600px) {
        .akpp-form-row-2col { flex-direction: column; gap: 0; }
    }
</style>
