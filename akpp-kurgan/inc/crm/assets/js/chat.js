/**
 * АКПП45 CRM - Логика внутреннего чата оператора
 * Обработка отправки сообщений, авто-скролл и UX-улучшения.
 *
 * @package AKPP_CRM
 * @version 4.2
 */

(function($) {
    'use strict';

    // Проверяем, что конфигурация передана из PHP (из templates/chat.php)
    if (typeof akpp_chat_config === 'undefined') {
        return;
    }

    // Кэширование DOM-элементов для производительности
    const $chatForm = $('#akpp-chat-form');
    const $messageInput = $('#akpp-message-input');
    const $sendBtn = $('#akpp-chat-send-btn');
    const $messagesArea = $('#akpp-chat-messages');
    const $archiveBtn = $('#akpp-chat-archive-btn');

    /**
     * Авто-прокрутка области сообщений вниз
     */
    function scrollToBottom() {
        if ($messagesArea.length) {
            $messagesArea.animate({
                scrollTop: $messagesArea[0].scrollHeight
            }, 300); // Плавная прокрутка за 300мс
        }
    }

    /**
     * Авто-ресайз textarea при вводе текста
     */
    function autoResizeTextarea() {
        $messageInput.on('input', function() {
            this.style.height = 'auto'; // Сброс высоты
            this.style.height = (this.scrollHeight) + 'px'; // Установка по содержимому
            
            // Активация/деактивация кнопки отправки
            if ($(this).val().trim().length > 0) {
                $sendBtn.prop('disabled', false).addClass('button-primary');
            } else {
                $sendBtn.prop('disabled', true).removeClass('button-primary');
            }
        });
    }

    /**
     * Обработка отправки формы чата
     */
    function handleChatSubmission() {
        $chatForm.on('submit', function(e) {
            e.preventDefault();

            const messageText = $messageInput.val().trim();
            if (!messageText) {
                return; // Защита от пустых сообщений
            }

            // 1. UI: Состояние "Отправка..."
            const originalBtnHtml = $sendBtn.html();
            $sendBtn.prop('disabled', true).html('<span class="spinner is-active" style="float:none; margin-right:5px;"></span> ' + akpp_chat_config.strings.sending);
            $messageInput.prop('disabled', true);

            // 2. AJAX-запрос к обработчику из class-chat-ajax.php
            $.ajax({
                url: akpp_chat_config.ajax_url,
                type: 'POST',
                data: {
                    action: 'akpp_send_chat_message',
                    akpp_chat_nonce_field: akpp_chat_config.nonce,
                    dialog_id: akpp_chat_config.current_dialog_id,
                    message_text: messageText,
                    user_id: akpp_chat_config.current_user_id
                },
                success: function(response) {
                    if (response.success) {
                        // 3. Успех: Динамическое добавление сообщения в DOM
                        const newMessageHtml = `
                            <div class="akpp-message-bubble message-mine fade-in">
                                <div class="akpp-message-text">${response.data.message_text}</div>
                                <div class="akpp-message-meta">
                                    <span class="akpp-message-time">${response.data.created_at}</span>
                                    <span class="akpp-message-status sent" title="Отправлено">✓</span>
                                </div>
                            </div>
                        `;
                        
                        $messagesArea.append(newMessageHtml);
                        
                        // Очистка поля ввода и сброс высоты
                        $messageInput.val('').trigger('input');
                        
                        // Прокрутка вниз
                        scrollToBottom();

                        // (Опционально) Здесь можно обновить счётчик непрочитанных в боковой панели
                        updateSidebarUnreadCount(akpp_chat_config.current_dialog_id);

                    } else {
                        // Обработка логической ошибки от сервера
                        alert(response.data.message || akpp_chat_config.strings.error);
                    }
                },
                error: function(xhr, status, error) {
                    // Обработка сетевой ошибки
                    console.error('Chat AJAX Error:', status, error);
                    alert(akpp_chat_config.strings.error);
                },
                complete: function() {
                    // 4. Возврат UI в исходное состояние
                    $sendBtn.prop('disabled', false).html(originalBtnHtml);
                    $messageInput.prop('disabled', false).focus();
                }
            });
        });
    }

    /**
     * Отправка по Enter (Shift+Enter для новой строки)
     */
    function handleEnterKey() {
        $messageInput.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault(); // Предотвращаем стандартный перенос строки
                $chatForm.trigger('submit'); // Имитируем отправку формы
            }
        });
    }

    /**
     * Обработка кнопки "В архив" (Заглушка для будущего AJAX)
     */
    function handleArchiveAction() {
        if ($archiveBtn.length) {
            $archiveBtn.on('click', function() {
                if (confirm('Вы уверены, что хотите архивировать этот диалог?')) {
                    const dialogId = $(this).data('dialog-id');
                    // Здесь будет AJAX-запрос на архивацию
                    window.location.href = `${akpp_chat_config.ajax_url.replace('admin-ajax.php', 'admin.php')}?page=akpp-avito-dialogs&action=archive&id=${dialogId}&_wpnonce=${akpp_chat_config.nonce}`;
                }
            });
        }
    }

    /**
     * Инициализация при загрузке страницы
     */
    $(document).ready(function() {
        if ($chatForm.length && $messagesArea.length) {
            autoResizeTextarea();
            handleChatSubmission();
            handleEnterKey();
            handleArchiveAction();
            
            // Первоначальная прокрутка вниз при открытии чата
            setTimeout(scrollToBottom, 100);
            
            console.log('✅ АКПП CRM: Внутренний чат инициализирован.');
        }
    });

    /**
     * Вспомогательная функция: обновление счётчика в боковой панели (опционально)
     */
    function updateSidebarUnreadCount(dialogId) {
        const $chatItem = $(`.akpp-chat-item[data-dialog-id="${dialogId}"]`);
        if ($chatItem.hasClass('has-unread')) {
            $chatItem.removeClass('has-unread');
            $chatItem.find('.akpp-chat-badge').remove();
        }
    }

})(jQuery);
