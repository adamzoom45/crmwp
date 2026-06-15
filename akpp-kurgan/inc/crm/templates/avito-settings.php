<?php
/**
 * АКПП45 CRM - Настройки интеграции с Авито API
 * Страница для ввода Client ID, Client Secret и настройки Webhook.
 *
 * @package AKPP_CRM
 * @version 4.2
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

// Проверка прав доступа (только администраторы)
if (!current_user_can('manage_options')) {
    wp_die(__('Недостаточно прав для изменения настроек.', 'akpp-crm'));
}

// =============================================================================
// ОБРАБОТКА СОХРАНЕНИЯ НАСТРОЕК
// =============================================================================
$message = '';
$message_type = ''; // 'success' или 'error'

if (isset($_POST['akpp_save_avito_settings']) && check_admin_referer('akpp_avito_settings_nonce')) {
    
    // Санитизация входных данных
    $client_id     = sanitize_text_field($_POST['akpp_avito_client_id']);
    $client_secret = sanitize_text_field($_POST['akpp_avito_client_secret']);
    $webhook_url   = esc_url_raw($_POST['akpp_avito_webhook_url']);
    $auto_sync     = isset($_POST['akpp_avito_auto_sync']) ? 1 : 0;

    // Валидация: поля не должны быть пустыми (кроме webhook, он опционален)
    if (empty($client_id) || empty($client_secret)) {
        $message = __('Ошибка: Client ID и Client Secret не могут быть пустыми.', 'akpp-crm');
        $message_type = 'error';
    } else {
        // Сохранение в базу данных WordPress (wp_options)
        update_option('akpp_avito_client_id', $client_id);
        update_option('akpp_avito_client_secret', $client_secret);
        update_option('akpp_avito_webhook_url', $webhook_url);
        update_option('akpp_avito_auto_sync', $auto_sync);

        $message = __('Настройки Авито успешно сохранены!', 'akpp-crm');
        $message_type = 'success';
    }
}

// =============================================================================
// ПОЛУЧЕНИЕ ТЕКУЩИХ ЗНАЧЕНИЙ
// =============================================================================
$current_client_id   = get_option('akpp_avito_client_id', '');
$current_client_secret = get_option('akpp_avito_client_secret', '');
$current_webhook_url = get_option('akpp_avito_webhook_url', site_url('/wp-json/akpp/v1/avito-webhook')); // URL по умолчанию
$current_auto_sync   = get_option('akpp_avito_auto_sync', 1);

// Генерация nonce для формы
$form_nonce = wp_create_nonce('akpp_avito_settings_nonce');
$test_nonce = wp_create_nonce('akpp_avito_test_connection');
?>

<div class="wrap akpp-settings-wrap">
    <h1><?php _e('Настройки интеграции с Авито', 'akpp-crm'); ?></h1>
    
    <?php if (!empty($message)) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="akpp-settings-card">
        <form method="post" action="">
            <?php wp_nonce_field('akpp_avito_settings_nonce', 'akpp_avito_settings_nonce_field'); ?>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <!-- Client ID -->
                    <tr>
                        <th scope="row">
                            <label for="akpp_avito_client_id"><?php _e('Client ID', 'akpp-crm'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input name="akpp_avito_client_id" type="text" id="akpp_avito_client_id" 
                                   value="<?php echo esc_attr($current_client_id); ?>" 
                                   class="regular-text code" 
                                   placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" required>
                            <p class="description">
                                <?php _e('Ваш Client ID из личного кабинета разработчика Авито.', 'akpp-crm'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Client Secret -->
                    <tr>
                        <th scope="row">
                            <label for="akpp_avito_client_secret"><?php _e('Client Secret', 'akpp-crm'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input name="akpp_avito_client_secret" type="password" id="akpp_avito_client_secret" 
                                   value="<?php echo esc_attr($current_client_secret); ?>" 
                                   class="regular-text code" 
                                   placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" required>
                            <p class="description">
                                <?php _e('Секретный ключ для получения access_token (OAuth 2.0).', 'akpp-crm'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Webhook URL -->
                    <tr>
                        <th scope="row">
                            <label for="akpp_avito_webhook_url"><?php _e('Webhook URL', 'akpp-crm'); ?></label>
                        </th>
                        <td>
                            <input name="akpp_avito_webhook_url" type="url" id="akpp_avito_webhook_url" 
                                   value="<?php echo esc_url($current_webhook_url); ?>" 
                                   class="regular-text code" dir="ltr">
                            <p class="description">
                                <?php _e('Этот URL необходимо указать в настройках приложения Авито для получения уведомлений о новых сообщениях.', 'akpp-crm'); ?>
                                <br>
                                <button type="button" class="button button-secondary" id="akpp-copy-webhook" data-url="<?php echo esc_url($current_webhook_url); ?>">
                                    📋 <?php _e('Копировать URL', 'akpp-crm'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>

                    <!-- Автоматическая синхронизация (WP-Cron) -->
                    <tr>
                        <th scope="row"><?php _e('Резервная синхронизация', 'akpp-crm'); ?></th>
                        <td>
                            <label for="akpp_avito_auto_sync">
                                <input name="akpp_avito_auto_sync" type="checkbox" id="akpp_avito_auto_sync" 
                                       value="1" <?php checked(1, $current_auto_sync); ?>>
                                <?php _e('Включить фоновую синхронизацию диалогов каждые 15 минут (на случай сбоя Webhook).', 'akpp-crm'); ?>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="submit">
                <input type="submit" name="akpp_save_avito_settings" id="submit" class="button button-primary" value="<?php _e('Сохранить изменения', 'akpp-crm'); ?>">
                
                <button type="button" class="button" id="akpp-test-connection-btn" data-nonce="<?php echo esc_attr($test_nonce); ?>">
                    🔌 <?php _e('Проверить подключение', 'akpp-crm'); ?>
                </button>
                <span class="spinner" id="akpp-test-spinner" style="float: none; margin-left: 10px;"></span>
                <span id="akpp-test-result" style="margin-left: 10px; font-weight: bold;"></span>
            </p>
        </form>
    </div>

    <!-- Инструкция для разработчика -->
    <div class="akpp-settings-card" style="margin-top: 20px; background: #fcfcfc; border-left: 4px solid #00a0d2;">
        <h2 style="margin-top: 0;"><?php _e('📖 Как настроить Авито API', 'akpp-crm'); ?></h2>
        <ol>
            <li><?php _e('Перейдите в', 'akpp-crm'); ?> <a href="https://developers.avito.ru/" target="_blank">Личный кабинет разработчика Авито</a>.</li>
            <li><?php _e('Создайте новое приложение или выберите существующее.', 'akpp-crm'); ?></li>
            <li><?php _e('Скопируйте <strong>Client ID</strong> и <strong>Client Secret</strong> и вставьте их в поля выше.', 'akpp-crm'); ?></li>
            <li><?php _e('В разделе "Webhooks" приложения Авито укажите <strong>Webhook URL</strong>, указанный на этой странице.', 'akpp-crm'); ?></li>
            <li><?php _e('Убедитесь, что подписаны на события: <code>message.created</code> и <code>message.updated</code>.', 'akpp-crm'); ?></li>
        </ol>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Копирование Webhook URL в буфер обмена
    $('#akpp-copy-webhook').on('click', function() {
        const url = $(this).data('url');
        navigator.clipboard.writeText(url).then(function() {
            const originalText = $('#akpp-copy-webhook').html();
            $('#akpp-copy-webhook').html('✅ <?php _e('Скопировано!', 'akpp-crm'); ?>');
            setTimeout(function() {
                $('#akpp-copy-webhook').html(originalText);
            }, 2000);
        });
    });

    // Тестовое подключение (Заглушка для AJAX-запроса)
    $('#akpp-test-connection-btn').on('click', function() {
        const btn = $(this);
        const spinner = $('#akpp-test-spinner');
        const result = $('#akpp-test-result');
        const nonce = btn.data('nonce');

        btn.prop('disabled', true);
        spinner.addClass('is-active');
        result.text('').removeClass('success-text error-text');

        // Здесь будет реальный AJAX-запрос к классу Avito API
        setTimeout(function() {
            spinner.removeClass('is-active');
            btn.prop('disabled', false);
            
            // Имитация ответа (заменить на реальный $.post)
            result.addClass('error-text').text('<?php _e('Требуется реализация AJAX-обработчика проверки токена', 'akpp-crm'); ?>');
        }, 1500);
    });
});
</script>

<style>
    .akpp-settings-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 20px;
        max-width: 800px;
    }
    .required { color: #dc3232; }
    .success-text { color: #00a32a; }
    .error-text { color: #dc3232; }
    .code { font-family: Consolas, Monaco, monospace; }
</style>
