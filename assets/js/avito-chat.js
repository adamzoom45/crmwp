/**
 * АКПП45 CRM - Avito Chat JavaScript
 * Управляет интерфейсом чата с Авито: загрузка диалогов, отправка сообщений и автообновление.
 */

jQuery(document).ready(function($) {
    'use strict';

    const ajaxUrl = typeof akppCRM !== 'undefined' ? akppCRM.ajax_url : '/wp-admin/admin-ajax.php';
    const nonce = typeof akppCRM !== 'undefined' ? akppCRM.nonce : '';

    // Элементы интерфейса
    const $dialogList = $('#akpp-avito-dialog-list');
    const $chatMessages = $('#akpp-avito-chat-messages');
    const $chatInput = $('#akpp-avito-message-input');
    const $sendBtn = $('#akpp-avito-send-btn');
    const $currentDialogInfo = $('#akpp-current-dialog-info');

    let currentDialogId = null;
    let lastMessageId = 0;
    let pollingInterval = null;

    // ==========================================================================
    // 1. Загрузка списка диалогов
    // ==========================================================================

    function loadDialogs() {
        $.post(ajaxUrl, {
            action: 'akpp_get_avito_dialogs',
            nonce: nonce
        }, function(response) {
            if (response.success && response.data.dialogs) {
                renderDialogList(response.data.dialogs);
            }
        });
    }

    function renderDialogList(dialogs) {
        $dialogList.empty();
        
        if (dialogs.length === 0) {
            $dialogList.html('<div class="akpp-text-muted" style="padding: 20px; text-align: center;">Нет активных диалогов</div>');
            return;
        }

        dialogs.forEach(dialog => {
            const isActive = dialog.id === currentDialogId ? 'active' : '';
            const unreadBadge = dialog.is_read == 0 ? '<span class="akpp-badge akpp-badge-danger" style="font-size: 10px; margin-left: 5px;">New</span>' : '';
            
            const html = `
                <div class="avito-dialog-item ${isActive}" data-dialog-id="${dialog.id}" style="padding: 15px; border-bottom: 1px solid var(--akpp-border); cursor: pointer; transition: background 0.2s;" 
                     onmouseover="this.style.background='var(--akpp-bg-tertiary)'" 
                     onmouseout="this.style.background='${isActive ? 'var(--akpp-bg-tertiary)' : 'transparent'}'">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <strong style="color: var(--akpp-text-primary);">${dialog.user_name || 'Клиент'}</strong>
                        <small style="color: var(--akpp-text-secondary);">${formatTime(dialog.last_message_at)}</small>
                    </div>
                    <div style="margin-top: 5px; font-size: 13px; color: var(--akpp-text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        ${dialog.last_message || 'Нет сообщений'} ${unreadBadge}
                    </div>
                </div>
            `;
            $dialogList.append(html);
        });
    }

    // ==========================================================================
    // 2. Выбор диалога и загрузка сообщений
    // ==========================================================================

    $(document).on('click', '.avito-dialog-item', function() {
        currentDialogId = $(this).data('dialog-id');
        
        // Обновляем визуальное выделение
        $('.avito-dialog-item').css('background', 'transparent').removeClass('active');
        $(this).css('background', 'var(--akpp-bg-tertiary)').addClass('active');

        // Сбрасываем счетчик новых сообщений
        lastMessageId = 0;
        
        // Загружаем сообщения
        loadMessages(currentDialogId);
        
        // Запускаем polling для этого диалога
        startPolling();
    });

    function loadMessages(dialogId) {
        if (!dialogId) return;

        $chatMessages.html('<div style="text-align: center; padding: 20px;"><span class="akpp-loading"></span> Загрузка...</div>');

        $.post(ajaxUrl, {
            action: 'akpp_get_avito_messages',
            nonce: nonce,
            dialog_id: dialogId
        }, function(response) {
            if (response.success && response.data.messages) {
                renderMessages(response.data.messages);
            } else {
                $chatMessages.html('<div class="akpp-text-muted" style="padding: 20px; text-align: center;">Не удалось загрузить сообщения</div>');
            }
        });
    }

    function renderMessages(messages) {
        $chatMessages.empty();
        
        if (messages.length === 0) {
            $chatMessages.html('<div class="akpp-text-muted" style="padding: 20px; text-align: center;">История сообщений пуста</div>');
            return;
        }

        messages.forEach(msg => {
            lastMessageId = Math.max(lastMessageId, msg.id);
            
            const authorClass = msg.author === 'manager' ? 'manager' : 'client';
            const timeStr = formatTime(msg.created_at);
            
            const html = `
                <div class="chat-message ${authorClass}">
                    <div class="text">${escapeHtml(msg.text).replace(/\n/g, '<br>')}</div>
                    <div class="meta">${timeStr}</div>
                </div>
            `;
            $chatMessages.append(html);
        });

        scrollToBottom();
    }

    // ==========================================================================
    // 3. Отправка сообщения
    // ==========================================================================

    function sendMessage() {
        const text = $chatInput.val().trim();
        if (!text || !currentDialogId) return;

        $sendBtn.prop('disabled', true).text('Отправка...');

        // Оптимистичное добавление в UI
        const tempId = Date.now();
        const tempHtml = `
            <div class="chat-message manager" id="temp-msg-${tempId}">
                <div class="text">${escapeHtml(text).replace(/\n/g, '<br>')}</div>
                <div class="meta">Отправка...</div>
            </div>
        `;
        $chatMessages.append(tempHtml);
        scrollToBottom();
        $chatInput.val('');

        $.post(ajaxUrl, {
            action: 'akpp_send_avito_message',
            nonce: nonce,
            dialog_id: currentDialogId,
            message: text
        }, function(response) {
            if (response.success) {
                $('#temp-msg-' + tempId).find('.meta').text(formatTime(new Date().toISOString()));
                // Принудительно обновляем сообщения через 1 секунду, чтобы получить реальный ID от Авито
                setTimeout(() => loadMessages(currentDialogId), 1000);
            } else {
                $('#temp-msg-' + tempId).remove();
                alert('Ошибка отправки: ' + (response.data.message || 'Неизвестная ошибка'));
            }
        }).always(function() {
            $sendBtn.prop('disabled', false).text('Отправить');
            $chatInput.focus();
        });
    }

    $sendBtn.on('click', function(e) {
        e.preventDefault();
        sendMessage();
    });

    $chatInput.on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // ==========================================================================
    // 4. Автообновление (Polling)
    // ==========================================================================

    function startPolling() {
        if (pollingInterval) clearInterval(pollingInterval);
        
        pollingInterval = setInterval(function() {
            if (currentDialogId) {
                $.post(ajaxUrl, {
                    action: 'akpp_get_avito_messages',
                    nonce: nonce,
                    dialog_id: currentDialogId
                }, function(response) {
                    if (response.success && response.data.messages) {
                        const newMessages = response.data.messages.filter(msg => msg.id > lastMessageId);
                        if (newMessages.length > 0) {
                            newMessages.forEach(msg => {
                                lastMessageId = Math.max(lastMessageId, msg.id);
                                const authorClass = msg.author === 'manager' ? 'manager' : 'client';
                                const html = `
                                    <div class="chat-message ${authorClass}" style="animation: fadeIn 0.3s;">
                                        <div class="text">${escapeHtml(msg.text).replace(/\n/g, '<br>')}</div>
                                        <div class="meta">${formatTime(msg.created_at)}</div>
                                    </div>
                                `;
                                $chatMessages.append(html);
                            });
                            scrollToBottom();
                            
                            // Обновляем список диалогов, чтобы сбросить бейдж "New"
                            loadDialogs();
                        }
                    }
                });
            }
        }, 10000); // Проверка каждые 10 секунд
    }

    // ==========================================================================
    // 5. Вспомогательные функции
    // ==========================================================================

    function formatTime(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function scrollToBottom() {
        $chatMessages.animate({
            scrollTop: $chatMessages[0].scrollHeight
        }, 300);
    }

    // ==========================================================================
    // 6. Инициализация
    // ==========================================================================

    loadDialogs();
    
    // Очистка интервала при уходе со страницы
    $(window).on('beforeunload', function() {
        if (pollingInterval) clearInterval(pollingInterval);
    });

});
