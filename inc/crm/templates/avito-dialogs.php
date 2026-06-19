<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$table_dialogs = $wpdb->prefix . 'akpp_avito_dialogs';
$dialogs = $wpdb->get_results(
    "SELECT * FROM {$table_dialogs} ORDER BY last_message_date DESC LIMIT 50"
);

$selected_dialog = isset($_GET['dialog']) ? intval($_GET['dialog']) : 0;
$messages = [];

if ($selected_dialog > 0) {
    $table_cache = $wpdb->prefix . 'akpp_avito_messages_cache';
    $messages = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table_cache} WHERE dialog_id = %d ORDER BY created_at ASC LIMIT 100",
            $selected_dialog
        )
    );
}
?>

<div class="wrap akpp-crm-wrap">
    <h1 style="color: var(--akpp-accent); margin-bottom: 20px;">💬 Авито чаты</h1>

    <div style="display: flex; gap: 20px; min-height: 600px;">
        <div style="width: 30%; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden;">
            <div style="padding: 15px; background: #f8f9fa; border-bottom: 1px solid #ddd;">
                <h3 style="margin: 0;">Диалоги</h3>
            </div>
            <div style="max-height: 550px; overflow-y: auto;">
                <?php if ($dialogs): ?>
                    <?php foreach ($dialogs as $dialog): ?>
                        <a href="?page=akpp-crm-avito-dialogs&dialog=<?php echo intval($dialog->avito_dialog_id); ?>" style="text-decoration: none; display: block;">
                            <div style="padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; <?php echo ($selected_dialog == $dialog->avito_dialog_id) ? 'background: #e7f3ff;' : ''; ?>">
                                <strong><?php echo esc_html($dialog->client_name ?: 'Пользователь Авито'); ?></strong>
                                <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                    <?php echo esc_html(substr($dialog->last_message_text ?? '', 0, 50)); ?>
                                </div>
                                <div style="font-size: 11px; color: #999; margin-top: 5px;">
                                    <?php echo esc_html($dialog->last_message_date); ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 40px; text-align: center; color: #999;">Нет диалогов</div>
                <?php endif; ?>
            </div>
        </div>

        <div style="width: 70%; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; flex-direction: column;">
            <?php if ($selected_dialog > 0): ?>
                <div style="padding: 15px; background: #f8f9fa; border-bottom: 1px solid #ddd;">
                    <h3 style="margin: 0;">Чат с пользователем</h3>
                </div>
                <div id="akpp-chat-messages" style="flex: 1; padding: 15px; overflow-y: auto; min-height: 450px; max-height: 450px;">
                    <?php if ($messages): ?>
                        <?php foreach ($messages as $msg): ?>
                            <div style="margin-bottom: 15px; text-align: <?php echo ($msg->direction === 'incoming') ? 'left' : 'right'; ?>;">
                                <div style="display: inline-block; max-width: 70%; padding: 10px 15px; border-radius: 15px; <?php echo ($msg->direction === 'incoming') ? 'background: #f1f3f4; color: #000;' : 'background: #0073aa; color: #fff;'; ?>">
                                    <?php echo nl2br(esc_html($msg->message_text)); ?>
                                </div>
                                <div style="font-size: 11px; color: #999; margin-top: 5px;">
                                    <?php echo esc_html($msg->created_at); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 50px; color: #999;">Нет сообщений</div>
                    <?php endif; ?>
                </div>
                <div style="padding: 15px; border-top: 1px solid #ddd; background: #f8f9fa;">
                    <form id="akpp-send-message-form" data-dialog="<?php echo esc_attr($selected_dialog); ?>">
                        <?php wp_nonce_field('akpp_send_avito_message_nonce', 'avito_message_nonce'); ?>
                        <div style="display: flex; gap: 10px;">
                            <textarea id="akpp-message-text" name="message" rows="2" style="flex: 1; resize: none;" placeholder="Введите сообщение..."></textarea>
                            <button type="submit" class="button button-primary" style="height: 50px;">Отправить</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div style="display: flex; align-items: center; justify-content: center; height: 600px; color: #999;">
                    Выберите диалог из списка слева
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var messagesDiv = document.getElementById('akpp-chat-messages');
    if (messagesDiv) messagesDiv.scrollTop = messagesDiv.scrollHeight;

    $('#akpp-send-message-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var dialogId = form.data('dialog');
        var message = $('#akpp-message-text').val();
        var sendBtn = form.find('button[type="submit"]');
        if (!message.trim()) return;

        sendBtn.prop('disabled', true).text('Отправка...');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_send_avito_message',
                dialog_id: dialogId,
                message: message,
                nonce: $('#avito_message_nonce').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#akpp-chat-messages').append(
                        '<div style="margin-bottom: 15px; text-align: right;">' +
                        '<div style="display: inline-block; max-width: 70%; padding: 10px 15px; border-radius: 15px; background: #0073aa; color: #fff;">' +
                        message.replace(/\n/g, '<br>') + '</div>' +
                        '<div style="font-size: 11px; color: #999; margin-top: 5px;">Только что</div></div>'
                    );
                    $('#akpp-chat-messages').scrollTop($('#akpp-chat-messages')[0].scrollHeight);
                    $('#akpp-message-text').val('');
                } else {
                    alert('Ошибка: ' + (response.data.message || 'неизвестно'));
                }
                sendBtn.prop('disabled', false).text('Отправить');
            },
            error: function() {
                alert('Ошибка соединения');
                sendBtn.prop('disabled', false).text('Отправить');
            }
        });
    });

    $('#akpp-message-text').on('keydown', function(e) {
        if (e.ctrlKey && e.keyCode === 13) $('#akpp-send-message-form').submit();
    });
});
</script>