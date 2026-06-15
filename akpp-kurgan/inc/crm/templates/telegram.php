<?php
/**
 * Шаблон страницы настроек Telegram бота
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

// Получаем текущие настройки
$bot_token = get_option('akpp_telegram_bot_token', '');
$webhook_url = home_url('/wp-json/akpp/v1/telegram-webhook');
$bot_username = get_option('akpp_telegram_bot_username', '');

// Получаем список сотрудников с Telegram
global $wpdb;
$table_employees = $wpdb->prefix . 'akpp_employees';
$employees = $wpdb->get_results(
    "SELECT id, name, role, telegram_id, telegram_chat_id, telegram_username 
    FROM {$table_employees} 
    WHERE telegram_id IS NOT NULL 
    ORDER BY name ASC"
);
?>

<div class="wrap akpp-crm-wrap">
    <h1 class="wp-heading-inline">📱 Telegram бот</h1>
    <hr class="wp-header-end">
    
    <div id="telegram-message" style="display: none; margin: 20px 0; padding: 10px 15px; border-radius: 5px;"></div>
    
    <div class="telegram-settings-container" style="display: flex; gap: 30px; flex-wrap: wrap;">
        
        <!-- Форма настроек -->
        <div class="settings-form" style="flex: 1; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2>🤖 Настройки бота</h2>
            <p>Настройте Telegram бота для получения уведомлений из CRM.</p>
            
            <form id="telegram-settings-form">
                <?php wp_nonce_field('akpp_telegram_settings_nonce', 'telegram_nonce'); ?>
                
                <div class="form-field" style="margin-bottom: 20px;">
                    <label for="bot_token">Bot Token</label>
                    <input type="text" id="bot_token" name="bot_token" 
                           value="<?php echo esc_attr($bot_token); ?>" 
                           class="regular-text" style="width: 100%;" 
                           placeholder="1234567890:ABCdefGHIjklmNOPqrstUVwxyz">
                    <p class="description">
                        Получите токен у <a href="https://t.me/BotFather" target="_blank">@BotFather</a> в Telegram
                    </p>
                </div>
                
                <div class="form-field" style="margin-bottom: 20px;">
                    <label>Webhook URL</label>
                    <input type="text" value="<?php echo esc_url($webhook_url); ?>" 
                           readonly class="regular-text" style="width: 100%; background: #f5f5f5;">
                    <p class="description">
                        Этот URL будет автоматически установлен как webhook для бота
                    </p>
                </div>
                
                <button type="submit" class="button button-primary" id="save-telegram-btn">
                    💾 Сохранить настройки
                </button>
                <button type="button" class="button" id="test-telegram-btn" style="margin-left: 10px;">
                    📨 Отправить тестовое сообщение
                </button>
                <button type="button" class="button" id="set-webhook-btn" style="margin-left: 10px;">
                    🔗 Установить webhook
                </button>
            </form>
            
            <div class="instructions" style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                <h3>📋 Инструкция по настройке:</h3>
                <ol style="margin: 0; padding-left: 20px;">
                    <li>Напишите <a href="https://t.me/BotFather" target="_blank">@BotFather</a> в Telegram</li>
                    <li>Отправьте команду <code>/newbot</code></li>
                    <li>Придумайте имя и username для бота</li>
                    <li>Скопируйте полученный токен</li>
                    <li>Вставьте токен в поле выше и нажмите "Сохранить"</li>
                    <li>Сотрудники должны написать боту команду <code>/start</code></li>
                </ol>
            </div>
        </div>
        
        <!-- Список сотрудников -->
        <div class="employees-list" style="flex: 1; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2>👥 Сотрудники в Telegram</h2>
            <p>Сотрудники, которые подключили бота.</p>
            
            <?php if ($employees): ?>
                <div class="employees-table" style="overflow-x: auto;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Имя</th>
                                <th>Роль</th>
                                <th>Telegram</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td><?php echo esc_html($emp->name); ?></td>
                                    <td><?php echo esc_html($emp->role); ?></td>
                                    <td>
                                        <?php if ($emp->telegram_username): ?>
                                            <a href="https://t.me/<?php echo esc_attr($emp->telegram_username); ?>" target="_blank">
                                                @<?php echo esc_html($emp->telegram_username); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo esc_html($emp->telegram_id); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($emp->telegram_chat_id): ?>
                                            <span style="color: green;">✅ Активен</span>
                                        <?php else: ?>
                                            <span style="color: orange;">⏳ Ожидает</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    Нет сотрудников, подключивших бота
                </div>
            <?php endif; ?>
            
            <div class="info-box" style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 5px; border-left: 4px solid #2196f3;">
                <strong>ℹ️ Как подключиться сотруднику:</strong>
                <ol style="margin: 10px 0 0 20px;">
                    <li>Найдите бота по username (после настройки)</li>
                    <li>Напишите команду <code>/start</code></li>
                    <li>Бот автоматически свяжет ваш Telegram с профилем сотрудника</li>
                    <li>Вы начнете получать уведомления</li>
                </ol>
            </div>
        </div>
    </div>
    
    <!-- Доступные команды -->
    <div class="commands-section" style="margin-top: 30px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2>📋 Доступные команды бота</h2>
        <div style="display: flex; flex-wrap: wrap; gap: 15px;">
            <div style="flex: 1; min-width: 200px;">
                <h4>Основные:</h4>
                <ul style="margin: 0;">
                    <li><code>/start</code> - начать работу</li>
                    <li><code>/help</code> - справка</li>
                    <li><code>/status</code> - статус системы</li>
                </ul>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <h4>Данные:</h4>
                <ul style="margin: 0;">
                    <li><code>/leads</code> - новые лиды</li>
                    <li><code>/deals</code> - мои сделки</li>
                    <li><code>/profile</code> - мой профиль</li>
                </ul>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <h4>Уведомления:</h4>
                <ul style="margin: 0;">
                    <li>🆕 Новые лиды</li>
                    <li>📋 Новые сделки</li>
                    <li>🔄 Смена статусов</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.form-field label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}
.description {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}
code {
    background: #f4f4f4;
    padding: 2px 6px;
    border-radius: 3px;
}
.employees-table table {
    margin-top: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Сохранение настроек
    $('#telegram-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = $('#save-telegram-btn');
        var messageDiv = $('#telegram-message');
        var botToken = $('#bot_token').val();
        
        if (!botToken) {
            showMessage('Введите Bot Token', 'error');
            return;
        }
        
        submitBtn.prop('disabled', true).text('⏳ Сохранение...');
        messageDiv.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_save_telegram_settings',
                bot_token: botToken,
                nonce: $('#telegram_nonce').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage(response.data.message, 'error');
                    submitBtn.prop('disabled', false).text('💾 Сохранить настройки');
                }
            },
            error: function() {
                showMessage('Ошибка соединения', 'error');
                submitBtn.prop('disabled', false).text('💾 Сохранить настройки');
            }
        });
    });
    
    // Тестовое сообщение
    $('#test-telegram-btn').on('click', function() {
        var btn = $(this);
        var messageDiv = $('#telegram-message');
        
        btn.prop('disabled', true).text('⏳ Отправка...');
        messageDiv.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_send_test_telegram',
                nonce: $('#telegram_nonce').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showMessage('Тестовое сообщение отправлено. Проверьте Telegram бота.', 'success');
                } else {
                    showMessage(response.data.message, 'error');
                }
                btn.prop('disabled', false).text('📨 Отправить тестовое сообщение');
            },
            error: function() {
                showMessage('Ошибка отправки', 'error');
                btn.prop('disabled', false).text('📨 Отправить тестовое сообщение');
            }
        });
    });
    
    // Установка webhook
    $('#set-webhook-btn').on('click', function() {
        var btn = $(this);
        var messageDiv = $('#telegram-message');
        
        btn.prop('disabled', true).text('⏳ Установка...');
        messageDiv.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_set_telegram_webhook',
                nonce: $('#telegram_nonce').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                } else {
                    showMessage(response.data.message, 'error');
                }
                btn.prop('disabled', false).text('🔗 Установить webhook');
            },
            error: function() {
                showMessage('Ошибка установки webhook', 'error');
                btn.prop('disabled', false).text('🔗 Установить webhook');
            }
        });
    });
    
    function showMessage(msg, type) {
        var messageDiv = $('#telegram-message');
        var className = type === 'success' ? 'notice-success' : (type === 'error' ? 'notice-error' : 'notice-warning');
        messageDiv.removeClass('notice-success notice-error notice-warning').addClass(className).html('<p>' + msg + '</p>').show();
        setTimeout(function() {
            messageDiv.fadeOut();
        }, 5000);
    }
});
</script>
