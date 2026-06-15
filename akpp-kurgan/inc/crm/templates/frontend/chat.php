<?php
/**
 * Шаблон страницы чата клиента
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
$table_users = $wpdb->prefix . 'akpp_site_users';
$table_employees = $wpdb->prefix . 'akpp_employees';
$table_messages = $wpdb->prefix . 'akpp_chat_messages';
$table_leads = $wpdb->prefix . 'akpp_leads';

// Получаем гида, привязанного к клиенту
$lead = $wpdb->get_row($wpdb->prepare(
    "SELECT guide_id FROM {$table_leads} WHERE client_id = %d ORDER BY id DESC LIMIT 1",
    $current_user->id
));

$guide_id = $lead ? $lead->guide_id : 0;
$guide = null;

if ($guide_id) {
    $guide = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, role FROM {$table_employees} WHERE id = %d",
        $guide_id
    ));
}

// Получаем историю сообщений
$messages = [];
if ($guide_id) {
    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_messages} 
        WHERE ((sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d))
        ORDER BY created_at ASC 
        LIMIT 100",
        $current_user->id, $guide_id, $guide_id, $current_user->id
    ));
    
    // Отмечаем входящие сообщения как прочитанные
    $wpdb->update(
        $table_messages,
        ['is_read' => 1],
        [
            'sender_id' => $guide_id,
            'receiver_id' => $current_user->id,
            'is_read' => 0
        ],
        ['%d'],
        ['%d', '%d', '%d']
    );
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Чат поддержки - АКПП45 CRM', 'akpp45-crm'); ?></title>
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
            height: 100vh;
            overflow: hidden;
        }
        
        .chat-container {
            display: flex;
            height: 100vh;
            max-width: 1400px;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        /* Сайдбар */
        .chat-sidebar {
            width: 320px;
            background: #fff;
            border-right: 1px solid #e9ecef;
            display: flex;
            flex-direction: column;
        }
        
        .user-info {
            padding: 25px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        
        .user-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .user-info p {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .chat-contact {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #f0f2f5;
            background: #f8f9fa;
        }
        
        .chat-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 20px;
            font-weight: bold;
        }
        
        .chat-contact-info h4 {
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        .chat-contact-info p {
            font-size: 12px;
            color: #999;
        }
        
        /* Окно чата */
        .chat-window {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f8f9fa;
        }
        
        .chat-header {
            padding: 20px;
            background: #fff;
            border-bottom: 1px solid #e9ecef;
        }
        
        .chat-header h3 {
            font-size: 18px;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        
        .message {
            max-width: 70%;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
        }
        
        .message-outgoing {
            align-self: flex-end;
        }
        
        .message-incoming {
            align-self: flex-start;
        }
        
        .message-bubble {
            padding: 12px 18px;
            border-radius: 20px;
            word-wrap: break-word;
        }
        
        .message-outgoing .message-bubble {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        
        .message-incoming .message-bubble {
            background: #fff;
            color: #333;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .message-time {
            font-size: 10px;
            color: #999;
            margin-top: 5px;
            padding: 0 10px;
        }
        
        .message-status {
            font-size: 10px;
            margin-left: 8px;
        }
        
        .chat-input-area {
            padding: 20px;
            background: #fff;
            border-top: 1px solid #e9ecef;
        }
        
        .chat-form {
            display: flex;
            gap: 12px;
        }
        
        .chat-form textarea {
            flex: 1;
            padding: 12px 18px;
            border: 1px solid #e9ecef;
            border-radius: 25px;
            resize: none;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        
        .chat-form textarea:focus {
            border-color: #667eea;
        }
        
        .chat-form button {
            padding: 0 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        .chat-form button:hover {
            transform: translateY(-2px);
        }
        
        .chat-form button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .typing-indicator {
            display: none;
            padding: 8px 20px;
            font-size: 12px;
            color: #999;
        }
        
        .empty-chat {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #999;
            text-align: center;
            flex-direction: column;
            gap: 15px;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .chat-sidebar {
                display: none;
            }
            
            .message {
                max-width: 85%;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <!-- Сайдбар с информацией -->
        <div class="chat-sidebar">
            <div class="user-info">
                <h3><?php echo esc_html($current_user->name); ?></h3>
                <p>Клиент АКПП45</p>
            </div>
            
            <?php if ($guide): ?>
            <div class="chat-contact">
                <div class="chat-avatar">
                    <?php echo mb_substr($guide->name, 0, 1); ?>
                </div>
                <div class="chat-contact-info">
                    <h4><?php echo esc_html($guide->name); ?></h4>
                    <p>Ваш персональный гид</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Окно чата -->
        <div class="chat-window">
            <div class="chat-header">
                <h3>💬 Чат с поддержкой</h3>
            </div>
            
            <?php if ($guide): ?>
                <div class="chat-messages" id="chat-messages">
                    <?php if ($messages): ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?php echo ($msg->sender_id == $current_user->id) ? 'message-outgoing' : 'message-incoming'; ?>">
                                <div class="message-bubble">
                                    <?php echo nl2br(esc_html($msg->message)); ?>
                                </div>
                                <div class="message-time">
                                    <?php echo date_i18n('H:i, d.m.Y', strtotime($msg->created_at)); ?>
                                    <?php if ($msg->sender_id == $current_user->id && $msg->is_read): ?>
                                        <span class="message-status">✓✓ Прочитано</span>
                                    <?php elseif ($msg->sender_id == $current_user->id): ?>
                                        <span class="message-status">✓ Доставлено</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-chat">
                            <p>💬 Напишите свой вопрос</p>
                            <p style="font-size: 12px;">Специалист ответит в ближайшее время</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="typing-indicator" id="typing-indicator">
                    <span>Специалист</span> печатает...
                </div>
                
                <div class="chat-input-area">
                    <form class="chat-form" id="chat-form">
                        <?php wp_nonce_field('akpp_send_chat_nonce', 'chat_nonce'); ?>
                        <input type="hidden" name="receiver_id" value="<?php echo $guide->id; ?>">
                        <textarea id="message-input" rows="2" placeholder="Введите сообщение..."></textarea>
                        <button type="submit" id="send-btn">📤 Отправить</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="empty-chat">
                    <p>😕 Специалист еще не назначен</p>
                    <p style="font-size: 12px;">Ожидайте, с вами свяжутся в ближайшее время</p>
                    <a href="<?php echo home_url('/crm-profile'); ?>" class="back-link">← Вернуться в профиль</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var currentUserId = <?php echo $current_user->id; ?>;
        var guideId = <?php echo $guide_id ?: 0; ?>;
        var lastMessageId = 0;
        var typingTimeout;
        
        // Скролл вниз
        function scrollToBottom() {
            var messagesDiv = document.getElementById('chat-messages');
            if (messagesDiv) {
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }
        }
        
        scrollToBottom();
        
        // Отправка сообщения
        $('#chat-form').on('submit', function(e) {
            e.preventDefault();
            
            var message = $('#message-input').val().trim();
            var receiverId = $('input[name="receiver_id"]').val();
            var sendBtn = $('#send-btn');
            
            if (!message) return;
            
            sendBtn.prop('disabled', true).text('⏳ Отправка...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_send_chat_message',
                    message: message,
                    receiver_id: receiverId,
                    nonce: $('#chat_nonce').val()
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#message-input').val('');
                        loadMessages();
                    } else {
                        alert('Ошибка: ' + response.data.message);
                    }
                    sendBtn.prop('disabled', false).text('📤 Отправить');
                },
                error: function() {
                    alert('Ошибка соединения');
                    sendBtn.prop('disabled', false).text('📤 Отправить');
                }
            });
        });
        
        // Загрузка сообщений
        function loadMessages() {
            if (!guideId) return;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_get_chat_messages',
                    with_user: guideId,
                    last_id: lastMessageId,
                    nonce: '<?php echo wp_create_nonce("akpp_get_chat_nonce"); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        updateMessages(response.data);
                        if (lastMessageId === 0) {
                            scrollToBottom();
                        }
                    }
                }
            });
        }
        
        // Обновление сообщений
        function updateMessages(messages) {
            var messagesDiv = $('#chat-messages');
            var wasAtBottom = messagesDiv[0].scrollHeight - messagesDiv.scrollTop() <= messagesDiv[0].clientHeight + 100;
            
            messages.forEach(function(msg) {
                if (msg.id > lastMessageId) {
                    var isOutgoing = msg.sender_id == currentUserId;
                    var messageHtml = '<div class="message ' + (isOutgoing ? 'message-outgoing' : 'message-incoming') + '">' +
                        '<div class="message-bubble">' + escapeHtml(msg.message).replace(/\n/g, '<br>') + '</div>' +
                        '<div class="message-time">' + formatTime(msg.created_at) +
                        (isOutgoing && msg.is_read ? ' <span class="message-status">✓✓ Прочитано</span>' : '') +
                        (isOutgoing && !msg.is_read ? ' <span class="message-status">✓ Доставлено</span>' : '') +
                        '</div>' +
                        '</div>';
                    messagesDiv.append(messageHtml);
                    lastMessageId = Math.max(lastMessageId, msg.id);
                }
            });
            
            if (wasAtBottom) {
                scrollToBottom();
            }
        }
        
        // Индикатор печати
        $('#message-input').on('input', function() {
            if (!guideId) return;
            
            clearTimeout(typingTimeout);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_typing_status',
                    receiver_id: guideId,
                    is_typing: 1,
                    nonce: '<?php echo wp_create_nonce("akpp_typing_nonce"); ?>'
                }
            });
            
            typingTimeout = setTimeout(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'akpp_typing_status',
                        receiver_id: guideId,
                        is_typing: 0,
                        nonce: '<?php echo wp_create_nonce("akpp_typing_nonce"); ?>'
                    }
                });
            }, 1500);
        });
        
        // Получение статуса печати
        function checkTypingStatus() {
            if (!guideId) return;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_get_typing_status',
                    user_id: guideId,
                    nonce: '<?php echo wp_create_nonce("akpp_typing_nonce"); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data.is_typing) {
                        $('#typing-indicator').show().find('span').text(response.data.sender_name);
                    } else {
                        $('#typing-indicator').hide();
                    }
                }
            });
        }
        
        // Вспомогательные функции
        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatTime(dateStr) {
            var date = new Date(dateStr);
            var now = new Date();
            var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            var msgDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            
            var time = date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
            
            if (msgDate.getTime() === today.getTime()) {
                return 'Сегодня ' + time;
            } else {
                return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' }) + ' ' + time;
            }
        }
        
        // Отправка по Ctrl+Enter
        $('#message-input').on('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
                $('#chat-form').submit();
            }
        });
        
        // Поллинг новых сообщений
        if (guideId) {
            loadMessages();
            setInterval(loadMessages, 3000);
            setInterval(checkTypingStatus, 2000);
        }
    });
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>
