/**
 * CRM АКПП45 - Административные скрипты
 * 
 * @package AKPP45_CRM
 */

(function($) {
    'use strict';
    
    // Глобальный объект для уведомлений
    window.AKPP_Admin = {
        
        /**
         * Инициализация
         */
        init: function() {
            this.initDeleteButtons();
            this.initStatusButtons();
            this.initModals();
            this.initCharts();
            this.initExportButtons();
        },
        
        /**
         * Показ уведомления
         */
        showNotice: function(message, type) {
            var notice = $('<div class="akpp-notice akpp-notice-' + type + '">' + message + '</div>');
            $('body').append(notice);
            
            setTimeout(function() {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },
        
        /**
         * Инициализация кнопок удаления
         */
        initDeleteButtons: function() {
            var self = this;
            
            $(document).on('click', '.delete-deal, .delete-lead, .delete-part, .delete-employee, .delete-vehicle, .delete-transmission, .delete-oil, .delete-user', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var itemId = button.data('id');
                var itemType = button.hasClass('delete-deal') ? 'deal' :
                              button.hasClass('delete-lead') ? 'lead' :
                              button.hasClass('delete-part') ? 'part' :
                              button.hasClass('delete-employee') ? 'employee' :
                              button.hasClass('delete-vehicle') ? 'vehicle' :
                              button.hasClass('delete-transmission') ? 'transmission' :
                              button.hasClass('delete-oil') ? 'oil' : 'user';
                
                if (confirm('Вы уверены, что хотите удалить этот элемент?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'akpp_delete_' + itemType,
                            [itemType + '_id']: itemId,
                            nonce: akpp_ajax.nonce
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                self.showNotice(response.data.message, 'success');
                                button.closest('tr').fadeOut(300, function() {
                                    $(this).remove();
                                });
                            } else {
                                self.showNotice(response.data.message, 'error');
                            }
                        },
                        error: function() {
                            self.showNotice('Ошибка удаления', 'error');
                        }
                    });
                }
            });
        },
        
        /**
         * Инициализация кнопок изменения статуса
         */
        initStatusButtons: function() {
            var self = this;
            
            $(document).on('click', '.update-status', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var itemId = button.data('id');
                var newStatus = button.data('status');
                var itemType = button.data('type') || 'deal';
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'akpp_update_' + itemType + '_status',
                        [itemType + '_id']: itemId,
                        status: newStatus,
                        nonce: akpp_ajax.nonce
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            self.showNotice(response.data.message, 'success');
                            location.reload();
                        } else {
                            self.showNotice(response.data.message, 'error');
                        }
                    },
                    error: function() {
                        self.showNotice('Ошибка обновления статуса', 'error');
                    }
                });
            });
        },
        
        /**
         * Инициализация модальных окон
         */
        initModals: function() {
            var self = this;
            
            // Открытие модального окна
            $(document).on('click', '.view-item, .view-lead, .view-deal', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var itemId = button.data('id');
                var itemType = button.hasClass('view-lead') ? 'lead' : 
                              button.hasClass('view-deal') ? 'deal' : 'parser_item';
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'akpp_get_' + itemType,
                        [itemType + '_id']: itemId,
                        nonce: akpp_ajax.nonce
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            self.showModal(response.data);
                        } else {
                            self.showNotice(response.data.message, 'error');
                        }
                    },
                    error: function() {
                        self.showNotice('Ошибка загрузки данных', 'error');
                    }
                });
            });
            
            // Закрытие модального окна
            $(document).on('click', '.akpp-modal-close, .akpp-modal-cancel', function() {
                $('.akpp-modal').removeClass('active');
            });
            
            $(document).on('click', '.akpp-modal', function(e) {
                if ($(e.target).hasClass('akpp-modal')) {
                    $(this).removeClass('active');
                }
            });
        },
        
        /**
         * Показ модального окна
         */
        showModal: function(data) {
            var modal = $('.akpp-modal');
            
            if (modal.length === 0) {
                modal = this.createModal();
            }
            
            var content = this.formatModalContent(data);
            modal.find('.akpp-modal-body').html(content);
            modal.addClass('active');
        },
        
        /**
         * Создание модального окна
         */
        createModal: function() {
            var modal = $('<div class="akpp-modal">' +
                '<div class="akpp-modal-content">' +
                '<div class="akpp-modal-header">' +
                '<h3>Детали</h3>' +
                '<button class="akpp-modal-close">&times;</button>' +
                '</div>' +
                '<div class="akpp-modal-body"></div>' +
                '<div class="akpp-modal-footer">' +
                '<button class="button akpp-modal-cancel">Закрыть</button>' +
                '</div>' +
                '</div>' +
                '</div>');
            
            $('body').append(modal);
            return modal;
        },
        
        /**
         * Форматирование контента модального окна
         */
        formatModalContent: function(data) {
            var html = '<table class="widefat fixed striped">';
            
            for (var key in data) {
                if (data.hasOwnProperty(key) && key !== 'id') {
                    var value = data[key];
                    if (typeof value === 'object') {
                        value = JSON.stringify(value);
                    }
                    html += '<tr>';
                    html += '<th style="width: 30%;">' + this.formatLabel(key) + '</th>';
                    html += '<td>' + (value || '—') + '</td>';
                    html += '</tr>';
                }
            }
            
            html += '</table>';
            return html;
        },
        
        /**
         * Форматирование названия поля
         */
        formatLabel: function(key) {
            var labels = {
                'client_name': 'Клиент',
                'client_phone': 'Телефон',
                'client_email': 'Email',
                'car_brand': 'Автомобиль',
                'problem': 'Проблема',
                'total_amount': 'Сумма',
                'status': 'Статус',
                'created_at': 'Дата создания',
                'updated_at': 'Дата обновления'
            };
            
            return labels[key] || key.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
        },
        
        /**
         * Инициализация графиков
         */
        initCharts: function() {
            if (typeof Chart === 'undefined') return;
            
            var funnelCanvas = document.getElementById('funnel-chart');
            if (funnelCanvas) {
                this.initFunnelChart(funnelCanvas);
            }
            
            var statsCanvas = document.getElementById('stats-chart');
            if (statsCanvas) {
                this.initStatsChart(statsCanvas);
            }
        },
        
        /**
         * Инициализация воронки продаж
         */
        initFunnelChart: function(canvas) {
            var ctx = canvas.getContext('2d');
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Лиды', 'Новые', 'Диагностика', 'В работе', 'Выполнено'],
                    datasets: [{
                        label: 'Количество',
                        data: this.getFunnelData(),
                        backgroundColor: [
                            '#6c5ce7',
                            '#0984e3',
                            '#fdcb6e',
                            '#00b894',
                            '#27ae60'
                        ],
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },
        
        /**
         * Получение данных для воронки
         */
        getFunnelData: function() {
            var data = window.akpp_funnel_data || { lead: 0, new: 0, diagnostic: 0, in_work: 0, completed: 0 };
            return [data.lead, data.new, data.diagnostic, data.in_work, data.completed];
        },
        
        /**
         * Инициализация графика статистики
         */
        initStatsChart: function(canvas) {
            var ctx = canvas.getContext('2d');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: window.akpp_stats_labels || [],
                    datasets: [{
                        label: 'Сделки',
                        data: window.akpp_stats_data || [],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102,126,234,0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },
        
        /**
         * Инициализация кнопок экспорта
         */
        initExportButtons: function() {
            var self = this;
            
            $(document).on('click', '.export-csv', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var exportType = button.data('type');
                
                window.location.href = ajaxurl + '?action=akpp_export_' + exportType + '&nonce=' + akpp_ajax.nonce;
            });
        }
    };
    
    // Инициализация при загрузке документа
    $(document).ready(function() {
        AKPP_Admin.init();
    });
    
})(jQuery);
