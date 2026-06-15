<?php
if (!defined('ABSPATH')) exit;

// Получаем текущие настройки
$bot_token = get_option('akpp_telegram_bot_token', '');
$chat_id = get_option('akpp_telegram_chat_id', '');

// Формируем URL вебхука
$webhook_url = admin_url('admin-ajax.php?action=akpp_telegram_webhook');

// Обработка сохранения настроек
if (isset($_POST['akpp_save_telegram_settings']) && check_admin_referer('akpp_telegram_nonce')) {
    $new_token = sanitize_text_field($_POST['bot_token']);
    $new_chat_id = sanitize_text_field($_POST['chat_id']);
    
    update_option('akpp_telegram_bot_token', $new_token);
    update_option('akpp_telegram_chat_id', $new_chat_id);
    
    echo '<div class="notice notice-success is-dismissible" style="border-left-color: var(--akpp-success);"><p>✅ Настройки Telegram успешно сохранены!</p></div>';
    
    // Обновляем переменные для отображения
    $bot_token = $new_token;
    $chat_id = $new_chat_id;
}

$is_configured = !empty($bot_token) && !empty($chat_id);

?>

<div class="wrap akpp-crm-wrap">
    <h1 style="color: var(--akpp-accent); margin-bottom: 20px;">📱 Настройки Telegram-бота</h1>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        
        <!-- ЛЕВАЯ КОЛОНКА: Форма настроек -->
        <div class="akpp-card">
            <div class="akpp-card-header">⚙️ Основные параметры</div>
            
            <form method="post" action="">
                <?php wp_nonce_field('akpp_telegram_nonce'); ?>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="bot_token">Bot Token *</label>
                    <input type="text" id="bot_token" name="bot_token" value="<?php echo esc_attr($bot_token); ?>" placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz" style="width: 100%; font-family: monospace;">
                    <small class="akpp-text-muted">Получите токен у @BotFather в Telegram</small>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="chat_id">Chat ID (ID чата для уведомлений) *</label>
                    <input type="text" id="chat_id" name="chat_id" value="<?php echo esc_attr($chat_id); ?>" placeholder="-1001234567890 или 123456789" style="width: 100%; font-family: monospace;">
                    <small class="akpp-text-muted">Узнайте свой ID через бота @userinfobot или @getidsbot</small>
                </div>

                <button type="submit" name="akpp_save_telegram_settings" class="button button-primary" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600;">
                    💾 Сохранить настройки
                </button>
            </form>
        </div>

        <!-- ПРАВАЯ КОЛОНКА: Статус и Webhook -->
        <div class="akpp-card">
            <div class="akpp-card-header">🔗 Статус подключения</div>
            
            <?php if ($is_configured) : ?>
                <div style="margin-bottom: 20px;">
                    <p style="color: var(--akpp-success); font-weight: 600; margin-bottom: 10px;">✅ Бот настроен</p>
                    <p class="akpp-text-muted" style="font-size: 13px; line-height: 1.5;">
                        Убедитесь, что вы добавили бота в нужный чат или канал и выдали ему права администратора (если это групповой чат).
                    </p>
                </div>

                <div style="background: var(--akpp-bg-tertiary); padding: 15px; border-radius: 6px; border: 1px solid var(--akpp-border); margin-bottom: 15px;">
                    <label style="font-size: 12px; color: var(--akpp-text-secondary); text-transform: uppercase; margin-bottom: 5px; display: block;">URL Вебхука</label>
                    <code style="word-break: break-all; color: var(--akpp-accent); font-size: 13px;"><?php echo esc_html($webhook_url); ?></code>
                </div>

                <button type="button" id="akpp-set-webhook-btn" class="button button-primary" style="width: 100%; background-color: var(--akpp-info); border-color: var(--akpp-info); color: #fff; font-weight: 600;">
                    🔄 Установить / Обновить Webhook
                </button>
                <div id="akpp-webhook-status" style="margin-top: 10px; font-size: 13px;"></div>

            <?php else : ?>
                <div style="text-align: center; padding: 20px; color: var(--akpp-text-secondary);">
                    <p>Заполните форму слева, чтобы активировать интеграцию с Telegram.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Инструкция -->
    <div class="akpp-card" style="margin-top: 20px;">
        <div class="akpp-card-header">📖 Краткая инструкция по настройке</div>
        <ol style="color: var(--akpp-text-primary); line-height: 1.8; padding-left: 20px;">
            <li>Откройте Telegram и найдите бота <strong>@BotFather</strong>.</li>
            <li>Отправьте команду <code>/newbot</code> и следуйте инструкциям для создания бота.</li>
            <li>Скопируйте выданный <strong>API Token</strong> и вставьте его в поле "Bot Token" выше.</li>
            <li>Найдите бота <strong>@userinfobot</strong> (или @getidsbot), нажмите "Start", чтобы узнать свой числовой <strong>Chat ID</strong>.</li>
            <li>Вставьте Chat ID в соответствующее поле и нажмите "Сохранить настройки".</li>
            <li>Нажмите кнопку "Установить / Обновить Webhook", чтобы Telegram мог отправлять команды боту.</li>
            <li>Отправьте боту команду <code>/start</code> для проверки связи.</li>
        </ol>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#akpp-set-webhook-btn').on('click', function() {
        const $btn = $(this);
        const $status = $('#akpp-webhook-status');
        
        $btn.prop('disabled', true).text('Установка вебхука...');
        $status.html('<span class="akpp-loading"></span> Обработка запроса к Telegram API...').css('color', 'var(--akpp-text-secondary)');

        $.post(akppCRM.ajax_url, {
            action: 'akpp_set_telegram_webhook',
            nonce: akppCRM.nonce
        }, function(response) {
            if (response.success) {
                $status.html('✅ ' + response.data.message).css('color', 'var(--akpp-success)');
            } else {
                $status.html('❌ Ошибка: ' + response.data.message).css('color', 'var(--akpp-danger)');
            }
        }).fail(function() {
            $status.html('❌ Ошибка сети при запросе к API.').css('color', 'var(--akpp-danger)');
        }).always(function() {
            $btn.prop('disabled', false).text('🔄 Установить / Обновить Webhook');
        });
    });

    // Автоскрытие уведомлений WordPress
    setTimeout(function() {
        $('.notice.is-dismissible').fadeOut(500, function() {
            $(this).remove();
        });
    }, 4000);
});
</script>
