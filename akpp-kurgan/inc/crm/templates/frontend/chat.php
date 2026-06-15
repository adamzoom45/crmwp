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
if (!is_user_logged_in()) {
    wp_redirect(home_url('/crm-login'));
    exit;
}

global $wpdb;

$current_user_id = get_current_user_id();
$table_users = $wpdb->prefix . 'akpp_site_users';
$table_messages = $wpdb->prefix . 'akpp_chat_messages';
$table_employees = $wpdb->prefix . 'akpp_employees';

// Получаем текущего пользователя
$current_user = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$table_users} WHERE id = %d",
    $current_user_id
));

// Получаем список сотрудников для чата (если пользователь - клиент, показываем только гида)
if ($current_user->role === 'client') {
    // Находим гида, привязанного к лиду
    $table_leads = $wpdb->prefix . 'akpp_leads';
    $lead = $wpdb->get_row($wpdb->prepare(
        "SELECT guide_id FROM {$table_leads} WHERE client_id = %d ORDER BY id DESC LIMIT 1",
        $current_user_id
    ));
    
    if ($lead && $lead->guide_id) {
        $chat_users = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, role, avatar FROM {$table_users} WHERE id = %d",
            $lead->guide_id
        ));
    } else {
        $chat_users = [];
    }
} else {
    // Для сотрудников - показываем всех клиентов
    $chat_users = $wpdb->get_results(
        "SELECT id, name, role, avatar FROM {$table_users} WHERE role = 'client' AND status = 'active' ORDER BY name ASC"
    );
}

// Выбранный пользователь для чата
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Получаем сообщения
$messages = [];
if ($selected_user_id) {
    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_messages} 
        WHERE ((sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d))
        ORDER BY created_at ASC 
        LIMIT 100",
        $current_user_id, $selected_user_id, $selected_user_id, $current_user_id
    ));
    
    // Отмечаем входящие сообщения как прочитанные
    $wpdb->update(
        $table_messages,
        ['is_read' => 1],
        [
            'sender_id' => $selected_user_id,
            'receiver_id' => $current_user_id,
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php _e('Чат - АКПП45 CRM', 'akpp45-crm'); ?></title>
    <?php wp_head(); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
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
        
        /* Список контактов */
        .contacts-sidebar {
            width: 320px;
            background: #fff;
            border-right: 1px solid #e9ecef;
            display: flex;
            flex-direction: column;
        }
        
        .user-info {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        
        .user-info h3 {
            margin: 0 0 5px 0;
            font-size: 18px;
        }
        
        .user-info p {
            margin: 0;
            font-size: 12px;
            opacity: 0.8;
        }
        
        .contacts-list {
            flex: 1;
            overflow-y: auto;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #f0f2f5;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            color: #333;
        }
        
        .contact-item:hover {
            background: #f8f9fa;
        }
        
        .contact-item.active {
            background: #e7f3ff;
            border-left: 3px solid #667eea;
        }
        
        .contact-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: bold;
            font-size: 18px;
            margin-right: 15px;
        }
        
        .contact-info {
            flex: 1;
        }
        
        .contact-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .contact-last-message {
            font-size: 12px;
            color: #6c757d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        
        .contact-time {
            font-size: 10px;
            color: #adb5bd;
        }
        
        .unread-badge {
            background: #dc3545;
            color: #fff;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            margin-left: 10px;
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
            margin: 0;
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
            padding: 12px 16px;
            border-radius: 18px;
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
            color: #adb5bd;
            margin-top: 5px;
            padding: 0 8px;
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
            gap: 10px;
        }
        
        .chat-form textarea {
            flex: 1;
            padding: 12px;
            border: 1px solid #e9ecef;
            border-radius: 24px;
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
            padding: 0 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            border-radius: 24px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        .chat-form button:hover {
            transform: translateY(-1px);
        }
        
        .chat-form button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .empty-chat {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #adb5bd;
            text-align: center;
        }
        
        .typing-indicator {
            display: none;
            padding: 10px 20px;
            font-size: 12px;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .contacts-sidebar {
                width: 80px;
            }
            
            .contact-info, .contact-time {
                display: none;
            }
            
            .contact-avatar {
                margin-right: 0;
            }
            
            .message {
                max-width: 85%;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <!-- Боковая панель с контактами -->
        <div class="contacts-sidebar">
            <div class="user-info">
                <h3><?php echo esc_html($current_user->name); ?></h3>
                <p><?php echo $current_user->role === 'client' ? 'Клиент' : 'Сотрудник'; ?></p>
            </div>
            
            <div class="contacts-list">
                <?php if ($chat_users): ?>
                    <?php foreach ($chat_users as $user): 
                        // Получаем последнее сообщение
                        $last_message = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM {$table_messages} 
                            WHERE ((sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d))
                            ORDER BY created_at DESC LIMIT 1",
                            $current_user_id, $user->id, $user->id, $current_user_id
                        ));
                        
                        // Получаем количество непрочитанных
                        $unread_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$table_messages} 
                            WHERE sender_id = %d AND receiver_id = %d AND is_read = 0",
                            $user->id, $current_user_id
                        ));
                        
                        $avatar_letter = mb_substr($user->name, 0, 1);
                    ?>
                        <a href="?user_id=<?php echo $user->id; ?>" class="contact-item <?php echo ($selected_user_id == $user->id) ? 'active' : ''; ?>">
                            <div class="contact-avatar"><?php echo esc_html($avatar_letter); ?></div>
                            <div class="contact-info">
                                <div class="contact-name"><?php echo esc_html($user->name); ?></div>
                                <div class="contact-last-message"><?php echo $last_message ? esc_html(mb_substr($last_message->message, 0, 40)) : 'Нет сообщений'; ?></div>
                            </div>
                            <?php if ($last_message): ?>
                                <div class="contact-time"><?php echo date('H:i', strtotime($last_message->created_at)); ?></div>
                            <?php endif; ?>
                            <?php if ($unread_count > 0): ?>
                                <div class="unread-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 40px; text-align: center; color: #adb5bd;">
                        Нет контактов для чата
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Окно чата -->
        <div class="chat-window">
            <?php if ($selected_user_id): 
                $selected_user = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_users} WHERE id = %d",
                    $selected_user_id
                ));
            ?>
                <div class="chat-header">
                    <h3>Чат с <?php echo esc_html($selected_user->name); ?></h3>
                </div>
                
                <div class="chat-messages" id="chat-messages">
                    <?php if ($messages): ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?php echo ($msg->sender_id == $current_user_id) ? 'message-outgoing' : 'message-incoming'; ?>">
                                <div class="message-bubble">
                                    <?php echo nl2br(esc_html($msg->message)); ?>
                                </div>
                                <div class="message-time">
                                    <?php echo date('H:i, d.m.Y', strtotime($msg->created_at)); ?>
                                    <?php if ($msg->sender_id == $current_user_id && $msg->is_read): ?>
                                        <span class="message-status">✓✓ Прочитано</span>
                                    <?php elseif ($msg->sender_id == $current_user_id): ?>
                                        <span class="message-status">✓ Доставлено</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-chat">
                            <p>Нет сообщений. Напишите что-нибудь!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="typing-indicator" id="typing-indicator">
                    <span>...</span> печатает...
                </div>
                
                <div class="chat-input-area">
                    <form class="chat-form" id="chat-form">
                        <?php wp_nonce_field('akpp_send_chat_nonce', 'chat_nonce'); ?>
                        <input type="hidden" name="receiver_id" value="<?php echo $selected_user_id; ?>">
                        <textarea id="message-input" rows="2" placeholder="Введите сообщение..."></textarea>
                        <button type="submit" id="send-btn">📤 Отправить</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="empty-chat">
                    <div>
                        <p>👈 Выберите контакт из списка слева</p>
                        <p style="font-size: 12px; margin-top: 10px;">Чтобы начать чат</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var currentUserId = <?php echo $current_user_id; ?>;
        var selectedUserId = <?php echo $selected_user_id ?: 0; ?>;
        var lastMessageId = 0;
        var typingTimeout;
        var isTyping = false;
        
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
            if (!selectedUserId) return;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_get_chat_messages',
                    with_user: selectedUserId,
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
                    var messageHtml = '<div class="message ' + (msg.sender_id == currentUserId ? 'message-outgoing' : 'message-incoming') + '">' +
                        '<div class="message-bubble">' + escapeHtml(msg.message).replace(/\n/g, '<br>') + '</div>' +
                        '<div class="message-time">' + formatDate(msg.created_at) +
                        (msg.sender_id == currentUserId && msg.is_read ? ' <span class="message-status">✓✓ Прочитано</span>' : '') +
                        (msg.sender_id == currentUserId && !msg.is_read ? ' <span class="message-status">✓ Доставлено</span>' : '') +
                        '</div>' +
                        '</div>';
                    messagesDiv.append(messageHtml);
                    lastMessageId = Math.max(lastMessageId, msg.id);
                }
            });
            
            if (wasAtBottom) {
                scrollToBottom();
            }
            
            // Обновляем список контактов (непрочитанные)
            updateContactsList();
        }
        
        // Обновление списка контактов
        function updateContactsList() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_get_unread_counts',
                    nonce: '<?php echo wp_create_nonce("akpp_get_unread_nonce"); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        $('.unread-badge').remove();
                        for (var userId in response.data) {
                            var count = response.data[userId];
                            if (count > 0) {
                                var badge = '<div class="unread-badge">' + (count > 9 ? '9+' : count) + '</div>';
                                $('.contact-item[href*="user_id=' + userId + '"]').append(badge);
                            }
                        }
                    }
                }
            });
        }
        
        // Индикатор печати
        $('#message-input').on('input', function() {
            if (!selectedUserId) return;
            
            clearTimeout(typingTimeout);
            
            if (!isTyping) {
                isTyping = true;
                sendTypingStatus(true);
            }
            
            typingTimeout = setTimeout(function() {
                isTyping = false;
                sendTypingStatus(false);
            }, 1500);
        });
        
        function sendTypingStatus(isTyping) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_typing_status',
                    receiver_id: selectedUserId,
                    is_typing: isTyping ? 1 : 0,
                    nonce: '<?php echo wp_create_nonce("akpp_typing_nonce"); ?>'
                }
            });
        }
        
        // Получение статуса печати
        function checkTypingStatus() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_get_typing_status',
                    user_id: selectedUserId,
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
        
        function formatDate(dateStr) {
            var date = new Date(dateStr);
            return date.toLocaleString('ru-RU', {
                hour: '2-digit',
                minute: '2-digit',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }
        
        // Отправка по Enter (Ctrl+Enter)
        $('#message-input').on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey) {
                e.preventDefault();
                $('#chat-form').submit();
            }
        });
        
        // Поллинг новых сообщений (каждые 3 секунды)
        if (selectedUserId) {
            loadMessages();
            setInterval(loadMessages, 3000);
            setInterval(checkTypingStatus, 2000);
        }
        
        // Обновление списка контактов каждые 10 секунд
        setInterval(updateContactsList, 10000);
    });
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>
