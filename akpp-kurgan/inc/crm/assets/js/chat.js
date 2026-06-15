/**
 * АКПП45 CRM - Chat JavaScript
 * Управляет отправкой сообщений и автоматическим обновлением чата (polling).
 */

jQuery(document).ready(function($) {
    'use strict';

    const ajaxUrl = typeof akppCRM !== 'undefined' ? akppCRM.ajax_url : '/wp-admin/admin-ajax.php';
    const nonce = typeof akppCRM !== 'undefined' ? akppCRM.nonce : '';

    // Ищем контейнер чата на странице
    const $chatContainer = $('.akpp-chat-container');
    
    if ($chatContainer.length) {
        const $messagesBox = $chatContainer.find('.akpp-chat-messages');
        const $inputField = $chatContainer.find('.akpp-chat-input textarea');
        const $sendButton = $chatContainer.find('.akpp-chat-input button');
        const dealId = $chatContainer.data('deal-id') || 0;
        const currentUserId = $chatContainer.data('user-id') || 0; // ID текущего пользователя WP
        
        let lastMessageId = 0; // Для отслеживания новых сообщений
        let pollingInterval = null;
        let isUserScrolling = false;

        // ==========================================================================
        // 1. Отправка сообщения
        // ==========================================================================

        function sendMessage() {
            const messageText = $inputField.val().trim();
            if (!messageText || !dealId) return;

            // Блокируем кнопку и поле на время отправки
            $sendButton.prop('disabled', true).text('Отправка...');
            
            // Оптимистичное добавление сообщения в UI (для мгновенного отклика)
            appendMessageToUI({
                user_id: currentUserId,
                message: messageText,
                is_internal: 0, // По умолчанию сообщение видно клиенту
                created_at: new Date().toISOString()
            }, 'manager');

            $inputField.val('');
            scrollToBottom();

            $.post(ajaxUrl, {
                action: 'akpp_send_chat_message',
                nonce: nonce,
                deal_id: dealId,
                message: messageText,
                is_internal: 0
            }, function(response) {
                if (response.success) {
                    // Сообщение успешно сохранено в БД
                    // Можно обновить lastMessageId из ответа, если сервер его возвращает
                } else {
                    console.error('Ошибка отправки:', response.data.message);
                    // В реальном приложении здесь стоит показать уведомление об ошибке
                }
            }).always(function() {
                $sendButton.prop('disabled', false).text('Отправить');
                $inputField.focus();
            });
        }

        // Обработчик клика по кнопке
        $sendButton.on('click', function(e) {
            e.preventDefault();
            sendMessage();
        });

        // Обработчик нажатия Enter (без Shift)
        $inputField.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // ==========================================================================
        // 2. Получение новых сообщений (Polling)
        // ==========================================================================

        function fetchMessages() {
            if (!dealId) return;

            $.post(ajaxUrl, {
                action: 'akpp_get_chat_messages',
                nonce: nonce,
                deal_id: dealId
            }, function(response) {
                if (response.success && response.data.messages) {
                    const messages = response.data.messages;
                    
                    // Фильтруем только новые сообщения
                    const newMessages = messages.filter(msg => msg.id > lastMessageId);
                    
                    if (newMessages.length > 0) {
                        newMessages.forEach(msg => {
                            const authorClass = (msg.user_id == currentUserId) ? 'manager' : 'client';
                            appendMessageToUI(msg, authorClass);
                            lastMessageId = Math.max(lastMessageId, msg.id);
                        });
                        
                        // Прокручиваем вниз, только если пользователь не скроллит вверх вручную
                        if (!isUserScrolling) {
                            scrollToBottom();
                        }
                    }
                }
            });
        }

        // ==========================================================================
        // 3. Вспомогательные функции UI
        // ==========================================================================

        function appendMessageToUI(msg, authorClass) {
            const date = new Date(msg.created_at);
            const timeStr = date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
            
            // Форматирование текста (замена переносов строк на <br>)
            const formattedText = msg.message.replace(/\n/g, '<br>');
            
            const isInternal = msg.is_internal == 1 ? '<span style="font-size:10px; color:#f59e0b; display:block; margin-bottom:4px;">🔒 Только для внутренних</span>' : '';

            const html = `
                <div class="chat-message ${authorClass}">
                    ${isInternal}
                    <div class="text">${formattedText}</div>
                    <div class="meta">${timeStr}</div>
                </div>
            `;
            
            $messagesBox.append(html);
        }

        function scrollToBottom() {
            $messagesBox.animate({
                scrollTop: $messagesBox[0].scrollHeight
            }, 300);
        }

        // Отслеживание ручного скролла пользователя
        $messagesBox.on('scroll', function() {
            const isAtBottom = $messagesBox[0].scrollHeight - $messagesBox.scrollTop() <= $messagesBox[0].clientHeight + 50;
            isUserScrolling = !isAtBottom;
        });

        // ==========================================================================
        // 4. Инициализация
        // ==========================================================================

        // Первичная загрузка сообщений
        fetchMessages();

        // Запуск polling каждые 5 секунд
        pollingInterval = setInterval(fetchMessages, 5000);

        // Остановка polling при уходе со страницы (для экономии ресурсов)
        $(window).on('beforeunload', function() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
        });
    }
});
