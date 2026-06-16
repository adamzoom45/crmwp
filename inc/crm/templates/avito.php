<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Получаем настройки Авито для проверки авторизации
$avito_client_id = get_option('akpp_avito_client_id', '');
$avito_client_secret = get_option('akpp_avito_client_secret', '');
$is_configured = !empty($avito_client_id) && !empty($avito_client_secret);

?>

<div class="wrap akpp-crm-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="color: var(--akpp-accent); margin: 0;">💬 Чаты Авито</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=akpp-avito-settings')); ?>" class="button button-secondary">
            ⚙️ Настройки API
        </a>
    </div>

    <?php if (!$is_configured) : ?>
        <div class="notice notice-warning" style="border-left-color: var(--akpp-warning);">
            <p><strong>Внимание:</strong> Интеграция с Авито не настроена. Пожалуйста, укажите Client ID и Client Secret в <a href="<?php echo esc_url(admin_url('admin.php?page=akpp-avito-settings')); ?>">настройках</a>, чтобы загружать диалоги.</p>
        </div>
    <?php endif; ?>

    <!-- Основной интерфейс чата -->
    <div class="akpp-card" style="padding: 0; overflow: hidden; height: 70vh; display: flex; flex-direction: column;">
        
        <?php if ($is_configured) : ?>
            <div style="display: flex; flex: 1; overflow: hidden;">
                
                <!-- ЛЕВАЯ КОЛОНКА: Список диалогов -->
                <div style="width: 300px; border-right: 1px solid var(--akpp-border); display: flex; flex-direction: column; background-color: var(--akpp-bg-secondary);">
                    <div style="padding: 15px; border-bottom: 1px solid var(--akpp-border); font-weight: 600; color: var(--akpp-accent);">
                        📨 Диалоги
                        <button type="button" id="akpp-refresh-dialogs" class="button button-small" style="float: right; margin-top: -5px; font-size: 11px;">🔄 Обновить</button>
                    </div>
                    <div id="akpp-avito-dialog-list" style="flex: 1; overflow-y: auto;">
                        <div style="text-align: center; padding: 20px; color: var(--akpp-text-secondary);">
                            <span class="akpp-loading"></span><br>Загрузка диалогов...
                        </div>
                    </div>
                </div>

                <!-- ПРАВАЯ КОЛОНКА: Окно переписки -->
                <div style="flex: 1; display: flex; flex-direction: column; background-color: var(--akpp-bg-primary);">
                    <div id="akpp-current-dialog-info" style="padding: 15px; border-bottom: 1px solid var(--akpp-border); font-weight: 600; color: var(--akpp-text-primary); background-color: var(--akpp-bg-tertiary);">
                        Выберите диалог слева для начала переписки
                    </div>
                    
                    <div id="akpp-avito-chat-messages" style="flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 10px;">
                        <div style="text-align: center; padding: 40px; color: var(--akpp-text-secondary);">
                            История сообщений появится здесь
                        </div>
                    </div>

                    <div style="padding: 15px; border-top: 1px solid var(--akpp-border); background-color: var(--akpp-bg-secondary); display: flex; gap: 10px;">
                        <textarea id="akpp-avito-message-input" placeholder="Введите сообщение..." style="flex: 1; resize: none; height: 50px; margin: 0;" disabled></textarea>
                        <button type="button" id="akpp-avito-send-btn" class="button button-primary" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600; height: 50px; width: 100px;" disabled>
                            Отправить
                        </button>
                    </div>
                </div>
            </div>
        <?php else : ?>
            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--akpp-text-secondary);">
                <p>Для работы чата необходимо настроить интеграцию с Авито API.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Кнопка ручного обновления списка диалогов
    $('#akpp-refresh-dialogs').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).text('Загрузка...');
        
        // Триггерим функцию из avito-chat.js, если она доступна, или делаем прямой запрос
        if (typeof loadDialogs === 'function') {
            loadDialogs();
        } else {
            $.post(akppCRM.ajax_url, {
                action: 'akpp_get_avito_dialogs',
                nonce: akppCRM.nonce
            }, function(response) {
                if (response.success) {
                    // Перезагрузка страницы для простоты, если JS функция не найдена
                    location.reload();
                }
            }).always(function() {
                $btn.prop('disabled', false).text('🔄 Обновить');
            });
        }
    });
});
</script>
