/**
 * CRM АКПП45 - Скрипты клиентского чата
 * 
 * @package AKPP45_CRM
 */

(function($) {
    'use strict';
    
    var AKPP_Chat = {
        
        currentUserId: 0,
        selectedUserId: 0,
        lastMessageId: 0,
        typingTimeout: null,
        isTyping: false,
        pollInterval: null,
        
        /**
         * Инициализация
         */
        init: function() {
            this.currentUserId = parseInt(akpp_chat.user_id) || 0;
            this.selectedUserId = this.getSelectedUserId();
            
            if (this.selectedUserId) {
                this.initChatWindow();
                this.startPolling();
            }
            
            this.initContactList();
            this.initSendMessage();
            this.initTypingIndicator();
        },
        
        /**
         * Получение ID выбранного пользователя
         */
        getSelectedUserId: function() {
            var urlParams = new URLSearchParams(window.location.search);
            return parseInt(urlParams.get('user_id')) || 0;
        },
        
        /**
         * Инициализация окна чата
         */
        initChatWindow: function() {
            this.scrollToBottom();
            this.loadMessages();
            this.markMessagesRead();
        },
        
        /**
         * Инициализация списка контактов
         */
        initContactList: function() {
            var self = this;
            
            // Обновление непрочитанных сообщений
            setInterval(function() {
                self.updateUnreadCounts();
            }, 10000);
        },
        
        /**
         * Инициализация отправки сообщений
         */
        initSendMessage: function() {
            var self = this;
            
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
                            self.loadMessages();
                        } else {
                            self.showNotice(response.data.message, 'error');
                        }
                        sendBtn.prop('disabled', false).text('📤 Отправить');
                    },
                    error: function() {
                        self.showNotice('Ошибка соединения', 'error');
                        sendBtn.prop('disabled', false).text('📤 Отправить');
                    }
                });
            });
            
            // Отправка по Ctrl+Enter
            $('#message-input').on('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
                    $('#chat-form').submit();
                }
            });
        },
        
        /**
         * Инициализация индикатора печати
         */
        initTypingIndicator: function() {
            var self = this;
            
            $('#message-input').on('input', function() {
                if (!self.selectedUserId) return;
                
                clearTimeout(self.typingTimeout);
                
                if (!self.isTyping) {
                    self.isTyping = true;
                    self.sendTypingStatus(true);
                }
                
                self.typingTimeout = setTimeout(function() {
                    self.isTyping = false;
                    self.sendTypingStatus(false);
                }, 1500);
            });
        },
        
        /**
         * Отправка статуса печати
         */
        sendTypingStatus: function(isTyping) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_typing_status',
                    receiver_id: this.selectedUserId,
                    is_typing: isTyping ? 1 : 0,
                    nonce: akpp_chat.nonce
                }
            });
        },
        
        /**
         * Проверка статуса печати
         */
        checkTypingStatus: function() {
            var self = this;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_get_typing_status',
                    user_id: this.selectedUserId,
                    nonce: akpp_chat.nonce
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
        },
        
        /**
         * Загрузка сообщений
         */
        loadMessages: function() {
            var self = this;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_get_chat_messages',
                    with_user: this.selectedUserId,
                    last_id: this.lastMessageId,
                    nonce: akpp_chat.nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        self.updateMessages(response.data);
                        if (self.lastMessageId === 0) {
                            self.scrollToBottom();
                        }
                    }
                }
            });
        },
        
        /**
         * Обновление сообщений
         */
        updateMessages: function(messages) {
            var self = this;
            var messagesDiv = $('#chat-messages');
            var wasAtBottom = messagesDiv[0].scrollHeight - messagesDiv.scrollTop() <= messagesDiv[0].clientHeight + 100;
            
            messages.forEach(function(msg) {
                if (msg.id > self.lastMessageId) {
                    var messageHtml = self.formatMessage(msg);
                    messagesDiv.append(messageHtml);
                    self.lastMessageId = Math.max(self.lastMessageId, msg.id);
                }
            });
            
            if (wasAtBottom) {
                this.scrollToBottom();
            }
            
            // Отмечаем прочитанными
            this.markMessagesRead();
        },
        
        /**
         * Форматирование сообщения
         */
        formatMessage: function(msg) {
            var isOutgoing = msg.sender_id == this.currentUserId;
            var messageClass = isOutgoing ? 'akpp-message-outgoing' : 'akpp-message-incoming';
            var time = this.formatTime(msg.created_at);
            var status = '';
            
            if (isOutgoing && msg.is_read) {
                status = ' <span class="message-status">✓✓ Прочитано</span>';
            } else if (isOutgoing && !msg.is_read) {
                status = ' <span class="message-status">✓ Доставлено</span>';
            }
            
            return '<div class="akpp-message ' + messageClass + '">' +
                '<div class="akpp-message-bubble">' + this.escapeHtml(msg.message).replace(/\n/g, '<br>') + '</div>' +
                '<div class="akpp-message-time">' + time + status + '</div>' +
                '</div>';
        },
        
        /**
         * Отметка сообщений как прочитанных
         */
        markMessagesRead: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_mark_messages_read',
                    with_user: this.selectedUserId,
                    nonce: akpp_chat.nonce
                }
            });
        },
        
        /**
         * Обновление счетчиков непрочитанных
         */
        updateUnreadCounts: function() {
            var self = this;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_get_unread_counts',
                    nonce: akpp_chat.nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        self.renderUnreadBadges(response.data);
                    }
                }
            });
        },
        
        /**
         * Отображение бейджей непрочитанных
         */
        renderUnreadBadges: function(unreadData) {
            $('.akpp-chat-unread').remove();
            
            for (var userId in unreadData) {
                var count = unreadData[userId];
                if (count > 0) {
                    var badge = '<span class="akpp-chat-unread">' + (count > 9 ? '9+' : count) + '</span>';
                    $('.akpp-chat-contact[data-user="' + userId + '"]').append(badge);
                }
            }
        },
        
        /**
         * Старт поллинга
         */
        startPolling: function() {
            var self = this;
            
            this.loadMessages();
            this.pollInterval = setInterval(function() {
                self.loadMessages();
            }, 3000);
            
            setInterval(function() {
                self.checkTypingStatus();
            }, 2000);
        },
        
        /**
         * Скролл вниз
         */
        scrollToBottom: function() {
            var messagesDiv = document.getElementById('chat-messages');
            if (messagesDiv) {
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }
        },
        
        /**
         * Форматирование времени
         */
        formatTime: function(dateStr) {
            var date = new Date(dateStr);
            var now = new Date();
            var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            var msgDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            
            var time = date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
            
            if (msgDate.getTime() === today.getTime()) {
                return 'Сегодня ' + time;
            } else if (msgDate.getTime() === today.getTime() - 86400000) {
                return 'Вчера ' + time;
            } else {
                return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' }) + ' ' + time;
            }
        },
        
        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        /**
         * Показ уведомления
         */
        showNotice: function(message, type) {
            var notice = $('<div class="akpp-notification akpp-notification-' + type + '">' + message + '</div>');
            $('body').append(notice);
            
            setTimeout(function() {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };
    
    // Инициализация при загрузке
    $(document).ready(function() {
        if (typeof akpp_chat !== 'undefined') {
            AKPP_Chat.init();
        }
    });
    
    window.AKPP_Chat = AKPP_Chat;
    
})(jQuery);
