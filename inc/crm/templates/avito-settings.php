<?php
/**
 * Шаблон страницы настроек Авито API
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

// Получаем текущие настройки
$client_id = get_option('akpp_avito_client_id', '');
$client_secret = get_option('akpp_avito_client_secret', '');

// Получаем статус токена
$token_status = '';
$token_expires = '';

global $wpdb;
$table_name = $wpdb->prefix . 'akpp_avito_tokens';
$token = $wpdb->get_row("SELECT created_at, expires_in FROM {$table_name} WHERE is_active = 1 LIMIT 1");

if ($token) {
    $created_timestamp = strtotime($token->created_at);
    $expires_timestamp = $created_timestamp + $token->expires_in;
    $expires_in_seconds = $expires_timestamp - time();
    
    if ($expires_in_seconds > 0) {
        $expires_in_hours = floor($expires_in_seconds / 3600);
        $expires_in_minutes = floor(($expires_in_seconds % 3600) / 60);
        $token_status = 'active';
        $token_expires = "Истекает через: {$expires_in_hours} ч. {$expires_in_minutes} мин.";
    } else {
        $token_status = 'expired';
        $token_expires = 'Токен истек. Требуется обновление.';
    }
} else {
    $token_status = 'missing';
    $token_expires = 'Токен не получен. Нажмите "Получить токен".';
}
?>

<div class="wrap akpp-crm-wrap">
    <h1 class="wp-heading-inline">📱 Настройки Авито API</h1>
    <hr class="wp-header-end">
    
    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>Настройки успешно сохранены!</p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['token-error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p>Ошибка получения токена. Проверьте Client ID и Client Secret.</p>
        </div>
    <?php endif; ?>
    
    <div class="akpp-settings-container" style="display: flex; gap: 30px; margin-top: 20px;">
        
        <!-- Форма настроек -->
        <div class="akpp-settings-form" style="flex: 1; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2>🔑 Данные для авторизации</h2>
            <p>Введите данные от приложения Авито API.</p>
            
            <form id="akpp-avito-settings-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                <?php wp_nonce_field('akpp_avito_settings_nonce', 'akpp_avito_nonce'); ?>
                <input type="hidden" name="action" value="akpp_save_avito_settings">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="avito_client_id">Client ID</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="avito_client_id" 
                                   name="avito_client_id" 
                                   value="<?php echo esc_attr($client_id); ?>" 
                                   class="regular-text" 
                                   required>
                            <p class="description">Client ID из вашего приложения Авито</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="avito_client_secret">Client Secret</label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="avito_client_secret" 
                                   name="avito_client_secret" 
                                   value="<?php echo esc_attr($client_secret); ?>" 
                                   class="regular-text" 
                                   required>
                            <p class="description">Client Secret из вашего приложения Авито</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary" id="akpp-save-settings-btn">
                        💾 Сохранить настройки и получить токен
                    </button>
                    <button type="button" class="button" id="akpp-refresh-token-btn" style="margin-left: 10px;">
                        🔄 Обновить токен
                    </button>
                </p>
            </form>
        </div>
        
        <!-- Статус токена -->
        <div class="akpp-token-status" style="flex: 1; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2>🔐 Статус токена</h2>
            
            <div class="status-indicator" style="padding: 15px; border-radius: 5px; margin-top: 10px; 
                 <?php 
                 if ($token_status == 'active') echo 'background: #d4edda; border-left: 4px solid #28a745;';
                 elseif ($token_status == 'expired') echo 'background: #f8d7da; border-left: 4px solid #dc3545;';
                 else echo 'background: #fff3cd; border-left: 4px solid #ffc107;';
                 ?>">
                
                <?php if ($token_status == 'active'): ?>
                    <p style="margin: 0; color: #155724;">✅ Токен активен</p>
                <?php elseif ($token_status == 'expired'): ?>
                    <p style="margin: 0; color: #721c24;">❌ Токен истек</p>
                <?php else: ?>
                    <p style="margin: 0; color: #856404;">⚠️ Токен не получен</p>
                <?php endif; ?>
                
                <p style="margin: 10px 0 0 0; font-size: 12px; <?php 
                if ($token_status == 'active') echo 'color: #155724;';
                elseif ($token_status == 'expired') echo 'color: #721c24;';
                else echo 'color: #856404;';
                ?>">
                    <?php echo esc_html($token_expires); ?>
                </p>
            </div>
            
            <div class="token-info" style="margin-top: 20px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                <h3 style="margin-top: 0;">📋 Инструкция:</h3>
                <ol style="margin: 0; padding-left: 20px;">
                    <li>Перейдите в <a href="https://developers.avito.ru/" target="_blank">Developers Avito</a></li>
                    <li>Создайте приложение или выберите существующее</li>
                    <li>Скопируйте Client ID и Client Secret</li>
                    <li>Вставьте их в форму слева</li>
                    <li>Нажмите "Сохранить настройки и получить токен"</li>
                </ol>
            </div>
        </div>
    </div>
    
    <div id="akpp-ajax-message" style="display: none; margin-top: 20px; padding: 10px; border-radius: 5px;"></div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Сохранение настроек и получение токена
    $('#akpp-avito-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = $('#akpp-save-settings-btn');
        var messageDiv = $('#akpp-ajax-message');
        
        submitBtn.prop('disabled', true).text('⏳ Сохранение...');
        messageDiv.hide();
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    messageDiv.removeClass('notice-error').addClass('notice notice-success')
                        .html('<p>✅ ' + response.data.message + '</p>').show();
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    messageDiv.removeClass('notice-success').addClass('notice notice-error')
                        .html('<p>❌ ' + response.data.message + '</p>').show();
                    submitBtn.prop('disabled', false).text('💾 Сохранить настройки и получить токен');
                }
            },
            error: function() {
                messageDiv.removeClass('notice-success').addClass('notice notice-error')
                    .html('<p>❌ Ошибка соединения с сервером</p>').show();
                submitBtn.prop('disabled', false).text('💾 Сохранить настройки и получить токен');
            }
        });
    });
    
    // Обновление токена
    $('#akpp-refresh-token-btn').on('click', function() {
        var btn = $(this);
        var messageDiv = $('#akpp-ajax-message');
        
        btn.prop('disabled', true).text('⏳ Обновление...');
        messageDiv.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_refresh_avito_token',
                nonce: '<?php echo wp_create_nonce("akpp_refresh_token_nonce"); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    messageDiv.removeClass('notice-error').addClass('notice notice-success')
                        .html('<p>✅ ' + response.data.message + '</p>').show();
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    messageDiv.removeClass('notice-success').addClass('notice notice-error')
                        .html('<p>❌ ' + response.data.message + '</p>').show();
                    btn.prop('disabled', false).text('🔄 Обновить токен');
                }
            },
            error: function() {
                messageDiv.removeClass('notice-success').addClass('notice notice-error')
                    .html('<p>❌ Ошибка обновления токена</p>').show();
                btn.prop('disabled', false).text('🔄 Обновить токен');
            }
        });
    });
});
</script>

<style>
.akpp-crm-wrap {
    max-width: 1200px;
    margin: 20px 20px 0 0;
}
.akpp-settings-container {
    flex-wrap: wrap;
}
@media (max-width: 768px) {
    .akpp-settings-form, .akpp-token-status {
        flex: 100% !important;
        margin-bottom: 20px;
    }
}
</style>
