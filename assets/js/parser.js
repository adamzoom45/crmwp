/**
 * CRM АКПП45 - Скрипты парсера и AI анализа
 * 
 * @package AKPP45_CRM
 */

(function($) {
    'use strict';
    
    var AKPP_Parser = {
        
        /**
         * Инициализация
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Привязка событий
         */
        bindEvents: function() {
            var self = this;
            
            // Одиночный парсинг
            $('#single-parse-btn').on('click', function(e) {
                e.preventDefault();
                self.singleParse();
            });
            
            // Массовый парсинг - показ формы
            $('#bulk-parse-btn').on('click', function(e) {
                e.preventDefault();
                $('#bulk-urls-area').slideToggle();
            });
            
            // Запуск массового парсинга
            $('#start-bulk-parse').on('click', function(e) {
                e.preventDefault();
                self.bulkParse();
            });
            
            // Массовый AI анализ
            $('#bulk-ai-btn').on('click', function(e) {
                e.preventDefault();
                self.bulkAiAnalysis();
            });
            
            // Сохранение OpenAI ключа
            $('#save-openai-key').on('click', function(e) {
                e.preventDefault();
                self.saveOpenAiKey();
            });
            
            // Проверка OpenAI ключа
            $('#check-openai-key').on('click', function(e) {
                e.preventDefault();
                self.checkOpenAiKey();
            });
            
            // Одобрение элемента
            $(document).on('click', '.approve-item', function(e) {
                e.preventDefault();
                self.approveItem($(this).data('id'));
            });
            
            // Отклонение элемента
            $(document).on('click', '.reject-item', function(e) {
                e.preventDefault();
                self.rejectItem($(this).data('id'));
            });
            
            // Просмотр элемента
            $(document).on('click', '.view-item', function(e) {
                e.preventDefault();
                self.viewItem($(this).data('id'));
            });
            
            // Отправка по Enter
            $('#parse-url').on('keypress', function(e) {
                if (e.which === 13) {
                    self.singleParse();
                }
            });
        },
        
        /**
         * Одиночный парсинг
         */
        singleParse: function() {
            var url = $('#parse-url').val().trim();
            var btn = $('#single-parse-btn');
            
            if (!url) {
                this.showMessage('Введите URL', 'error');
                return;
            }
            
            btn.prop('disabled', true).text('⏳ Парсинг...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_parse_url',
                    url: url,
                    nonce: akpp_parser_nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        AKPP_Parser.showMessage(response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        AKPP_Parser.showMessage(response.data.message, 'error');
                    }
                    btn.prop('disabled', false).text('🔍 Парсить');
                },
                error: function() {
                    AKPP_Parser.showMessage('Ошибка соединения с сервером', 'error');
                    btn.prop('disabled', false).text('🔍 Парсить');
                }
            });
        },
        
        /**
         * Массовый парсинг
         */
        bulkParse: function() {
            var urls = $('#bulk-urls').val();
            var btn = $('#start-bulk-parse');
            
            if (!urls) {
                this.showMessage('Введите URL', 'error');
                return;
            }
            
            btn.prop('disabled', true).text('⏳ Парсинг...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_bulk_parse',
                    urls: urls,
                    nonce: akpp_parser_nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        AKPP_Parser.showMessage(response.data.message, 'success');
                        $('#bulk-urls').val('');
                        $('#bulk-urls-area').hide();
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        AKPP_Parser.showMessage(response.data.message, 'error');
                    }
                    btn.prop('disabled', false).text('🚀 Начать массовый парсинг');
                },
                error: function() {
                    AKPP_Parser.showMessage('Ошибка соединения', 'error');
                    btn.prop('disabled', false).text('🚀 Начать массовый парсинг');
                }
            });
        },
        
        /**
         * Массовый AI анализ
         */
        bulkAiAnalysis: function() {
            var btn = $('#bulk-ai-btn');
            
            btn.prop('disabled', true).text('⏳ Запуск AI анализа...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_bulk_ai_analysis',
                    nonce: akpp_parser_nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        AKPP_Parser.showMessage(response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        AKPP_Parser.showMessage(response.data.message, 'error');
                    }
                    btn.prop('disabled', false).text('🤖 Массовый AI анализ');
                },
                error: function() {
                    AKPP_Parser.showMessage('Ошибка соединения', 'error');
                    btn.prop('disabled', false).text('🤖 Массовый AI анализ');
                }
            });
        },
        
        /**
         * Сохранение OpenAI ключа
         */
        saveOpenAiKey: function() {
            var apiKey = $('#openai-api-key').val();
            var btn = $('#save-openai-key');
            
            if (!apiKey) {
                this.showMessage('Введите API ключ', 'error');
                return;
            }
            
            btn.prop('disabled', true).text('⏳ Сохранение...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_save_openai_settings',
                    openai_api_key: apiKey,
                    nonce: akpp_parser_nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        AKPP_Parser.showMessage(response.data.message, 'success');
                        $('#openai-status').html('<span style="color: green;">✅ ' + response.data.status.message + '</span>');
                    } else {
                        AKPP_Parser.showMessage(response.data.message, 'warning');
                        $('#openai-status').html('<span style="color: orange;">⚠️ ' + response.data.status.message + '</span>');
                    }
                    btn.prop('disabled', false).text('💾 Сохранить');
                },
                error: function() {
                    AKPP_Parser.showMessage('Ошибка соединения', 'error');
                    btn.prop('disabled', false).text('💾 Сохранить');
                }
            });
        },
        
        /**
         * Проверка OpenAI ключа
         */
        checkOpenAiKey: function() {
            var btn = $('#check-openai-key');
            
            btn.prop('disabled', true).text('⏳ Проверка...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_check_openai_key',
                    nonce: akpp_parser_nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data.valid) {
                        $('#openai-status').html('<span style="color: green;">✅ ' + response.data.message + '</span>');
                    } else {
                        $('#openai-status').html('<span style="color: red;">❌ ' + response.data.message + '</span>');
                    }
                    btn.prop('disabled', false).text('🔍 Проверить');
                },
                error: function() {
                    AKPP_Parser.showMessage('Ошибка соединения', 'error');
                    btn.prop('disabled', false).text('🔍 Проверить');
                }
            });
        },
        
        /**
         * Одобрение элемента
         */
        approveItem: function(itemId) {
            var row = $('.approve-item[data-id="' + itemId + '"]').closest('tr');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_approve_parser_item',
                    item_id: itemId,
                    nonce: akpp_parser_nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        AKPP_Parser.showMessage(response.data.message, 'success');
                        row.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        AKPP_Parser.showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    AKPP_Parser.showMessage('Ошибка соединения', 'error');
                }
            });
        },
        
        /**
         * Отклонение элемента
         */
        rejectItem: function(itemId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_reject_parser_item',
                    item_id: itemId,
                    nonce: akpp_parser_nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        AKPP_Parser.showMessage(response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        AKPP_Parser.showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    AKPP_Parser.showMessage('Ошибка соединения', 'error');
                }
            });
        },
        
        /**
         * Просмотр элемента
         */
        viewItem: function(itemId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_get_parser_item',
                    item_id: itemId,
                    nonce: akpp_parser_nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        AKPP_Parser.displayModal(response.data);
                    }
                },
                error: function() {
                    AKPP_Parser.showMessage('Ошибка загрузки данных', 'error');
                }
            });
        },
        
        /**
         * Отображение модального окна
         */
        displayModal: function(item) {
            var content = '<h4>📄 Информация</h4>';
            content += '<p><strong>URL:</strong> <a href="' + item.url + '" target="_blank">' + item.url + '</a></p>';
            content += '<p><strong>Заголовок:</strong> ' + (item.title || '—') + '</p>';
            content += '<p><strong>Тип:</strong> ' + (item.content_type || '—') + '</p>';
            content += '<p><strong>Статус:</strong> ' + (item.status || '—') + '</p>';
            
            if (item.ai_analysis) {
                content += '<h4>🤖 Результат AI анализа</h4>';
                content += '<pre style="background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto;">' + JSON.stringify(item.ai_analysis, null, 2) + '</pre>';
            }
            
            if (item.content) {
                content += '<h4>📝 Содержимое</h4>';
                content += '<div style="background: #f5f5f5; padding: 10px; border-radius: 5px; max-height: 300px; overflow-y: auto;">' + item.content.substring(0, 3000) + '</div>';
            }
            
            $('#modal-content').html(content);
            $('#view-item-modal').fadeIn(200);
        },
        
        /**
         * Показ сообщения
         */
        showMessage: function(message, type) {
            var messageDiv = $('#parse-message');
            var className = type === 'success' ? 'notice-success' : (type === 'error' ? 'notice-error' : 'notice-warning');
            messageDiv.removeClass('notice-success notice-error notice-warning').addClass(className).html('<p>' + message + '</p>').show();
            
            setTimeout(function() {
                messageDiv.fadeOut();
            }, 5000);
        }
    };
    
    // Инициализация при загрузке
    $(document).ready(function() {
        if (typeof akpp_parser_nonce !== 'undefined') {
            AKPP_Parser.init();
        }
    });
    
    window.AKPP_Parser = AKPP_Parser;
    
})(jQuery);
