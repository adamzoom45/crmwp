/**
 * CRM АКПП45 - Административные скрипты
 * 
 * @package AKPP45_CRM
 */

(function($) {
    'use strict';
    
    window.AKPP_Admin = {
        
        init: function() {
            this.initDeleteButtons();
            this.initStatusButtons();
            this.initModals();
            this.initOpenModals();
            this.initAjaxForms();
            this.initFilters();
            this.initCharts();
            this.initExportButtons();
            console.log('✅ AKPP_Admin инициализирован');
        },
        
        showNotice: function(message, type) {
            var bgColor = type === 'success' ? '#00ff88' : '#fc8181';
            var textColor = type === 'success' ? '#1a1f2e' : '#fff';
            var notice = $('<div style="position:fixed;top:40px;right:20px;background:' + bgColor + ';color:' + textColor + ';padding:15px 25px;border-radius:8px;box-shadow:0 4px 20px rgba(0,255,136,0.3);z-index:999999;font-weight:600;animation:slideIn 0.3s ease;">' + message + '</div>');
            $('body').append(notice);
            setTimeout(function() {
                notice.fadeOut(300, function() { $(this).remove(); });
            }, 3000);
        },
        
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
                            id: itemId,
                            nonce: akpp_ajax.nonce
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                self.showNotice(response.data.message || 'Удалено', 'success');
                                button.closest('tr').fadeOut(300, function() { $(this).remove(); });
                            } else {
                                self.showNotice(response.data.message || 'Ошибка', 'error');
                            }
                        },
                        error: function() { self.showNotice('Ошибка удаления', 'error'); }
                    });
                }
            });
        },
        
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
                        id: itemId,
                        status: newStatus,
                        nonce: akpp_ajax.nonce
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            self.showNotice(response.data.message, 'success');
                            location.reload();
                        } else {
                            self.showNotice(response.data.message || 'Ошибка', 'error');
                        }
                    },
                    error: function() { self.showNotice('Ошибка обновления статуса', 'error'); }
                });
            });
        },
        
        initModals: function() {
            var self = this;
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
                        id: itemId,
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
                    error: function() { self.showNotice('Ошибка загрузки данных', 'error'); }
                });
            });
        },
        
        initOpenModals: function() {
            // Открытие модалок
            $(document).on('click', '.akpp-open-modal', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                console.log('🔘 Открытие модалки:', target);
                
                if (target) {
                    var $modal = $(target);
                    $modal.css({
                        'display': 'flex',
                        'opacity': '1',
                        'visibility': 'visible',
                        'z-index': '999999',
                        'position': 'fixed',
                        'top': '0',
                        'left': '0',
                        'right': '0',
                        'bottom': '0',
                        'background': 'rgba(0,0,0,0.85)'
                    }).addClass('active');
                    console.log('✅ Модалка открыта:', target);
                }
            });
            
            // Закрытие модалок
            $(document).on('click', '.akpp-modal-close, .akpp-modal-cancel', function() {
                $(this).closest('.akpp-modal').css({
                    'display': 'none',
                    'opacity': '0',
                    'visibility': 'hidden'
                }).removeClass('active');
                console.log('❌ Модалка закрыта');
            });
            
            // Закрытие по клику на фон
            $(document).on('click', '.akpp-modal', function(e) {
                if ($(e.target).hasClass('akpp-modal')) {
                    $(this).css({
                        'display': 'none',
                        'opacity': '0',
                        'visibility': 'hidden'
                    }).removeClass('active');
                }
            });
        },
        
        initAjaxForms: function() {
            var self = this;
            
            $(document).on('submit', '.akpp-ajax-form', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var action = $form.data('action');
                var $submitBtn = $form.find('button[type="submit"]');
                
                if (!action) {
                    console.error('❌ Не указан data-action для формы');
                    return;
                }
                
                console.log('📤 Отправка формы:', action);
                
                var formData = {};
                $form.serializeArray().forEach(function(item) {
                    formData[item.name] = item.value;
                });
                
                formData.nonce = akpp_ajax.nonce;
                formData.action = action;
                
                console.log('📦 Данные:', formData);
                
                var originalText = $submitBtn.text();
                $submitBtn.prop('disabled', true).text('Сохранение...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        console.log('✅ Ответ сервера:', response);
                        
                        if (response.success) {
                            self.showNotice(response.data.message || 'Сохранено успешно', 'success');
                            
                            if (typeof window.akppFormSuccess === 'function') {
                                window.akppFormSuccess(response.data, $form);
                            } else {
                                $form.closest('.akpp-modal').css({
                                    'display': 'none',
                                    'opacity': '0',
                                    'visibility': 'hidden'
                                }).removeClass('active');
                                setTimeout(function() { location.reload(); }, 1000);
                            }
                        } else {
                            self.showNotice(response.data.message || 'Ошибка сохранения', 'error');
                            $submitBtn.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ Ошибка AJAX:', status, error);
                        self.showNotice('Ошибка соединения с сервером', 'error');
                        $submitBtn.prop('disabled', false).text(originalText);
                    }
                });
            });
        },
        
        initFilters: function() {
            // Обработка стандартных WordPress фильтров
            $(document).on('click', '#post-query-submit, input[type="submit"][name="filter_action"], .filter-button', function(e) {
                var $form = $(this).closest('form');
                if ($form.length) {
                    console.log('🔍 Фильтрация формы');
                    $form.submit();
                }
            });
            
            // Обработка сброса фильтров
            $(document).on('click', '#post-query-reset, .reset-filters', function(e) {
                e.preventDefault();
                var $form = $(this).closest('form');
                if ($form.length) {
                    $form.find('select, input[type="text"]').val('');
                    $form.submit();
                }
            });
            
            // Обработка поиска
            $(document).on('submit', '.search-form, form.search-form', function(e) {
                console.log('🔍 Поиск...');
            });
            
            // Обработка сортировки колонок
            $(document).on('click', '.wp-list-table th a, .sortable a', function(e) {
                console.log('📊 Сортировка...');
            });
            
            // Обработка массовых действий
            $(document).on('change', 'select[name="action"], select[name="action2"]', function() {
                var action = $(this).val();
                console.log('📋 Массовое действие:', action);
            });
            
            // Обработка чекбоксов "Выбрать все"
            $(document).on('change', 'thead .check-column input[type="checkbox"]', function() {
                var checked = $(this).is(':checked');
                $(this).closest('table').find('tbody .check-column input[type="checkbox"]').prop('checked', checked);
            });
        },
        
        showModal: function(data) {
            var modal = $('.akpp-modal');
            if (modal.length === 0) {
                modal = this.createModal();
            }
            var content = this.formatModalContent(data);
            modal.find('.akpp-modal-body').html(content);
            modal.addClass('active').css('display', 'flex');
        },
        
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
        
        initCharts: function() {
            if (typeof Chart === 'undefined') return;
            var funnelCanvas = document.getElementById('funnel-chart');
            if (funnelCanvas) this.initFunnelChart(funnelCanvas);
            var statsCanvas = document.getElementById('stats-chart');
            if (statsCanvas) this.initStatsChart(statsCanvas);
        },
        
        initFunnelChart: function(canvas) {
            var ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Лиды', 'Новые', 'Диагностика', 'В работе', 'Выполнено'],
                    datasets: [{
                        label: 'Количество',
                        data: this.getFunnelData(),
                        backgroundColor: ['#6c5ce7', '#0984e3', '#fdcb6e', '#00b894', '#27ae60'],
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        },
        
        getFunnelData: function() {
            var data = window.akpp_funnel_data || { lead: 0, new: 0, diagnostic: 0, in_work: 0, completed: 0 };
            return [data.lead, data.new, data.diagnostic, data.in_work, data.completed];
        },
        
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
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        },
        
        initExportButtons: function() {
            $(document).on('click', '.export-csv', function(e) {
                e.preventDefault();
                var button = $(this);
                var exportType = button.data('type');
                window.location.href = ajaxurl + '?action=akpp_export_' + exportType + '&nonce=' + akpp_ajax.nonce;
            });
        }
    };
    
    $(document).ready(function() {
        console.log('🚀 AKPP CRM: DOM загружен');
        if (typeof window.AKPP_Admin !== 'undefined') {
            window.AKPP_Admin.init();
        } else {
            console.error('❌ AKPP_Admin не найден!');
        }
    });
    
})(jQuery);