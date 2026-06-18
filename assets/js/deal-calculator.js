/**
 * CRM АКПП45 - Калькулятор сделок и управление запчастями
 * 
 * @package AKPP45_CRM
 */

(function($) {
    'use strict';
    
    var AKPP_DealCalculator = {
        
        selectedParts: [],
        
        /**
         * Инициализация
         */
        init: function() {
            this.bindEvents();
            this.loadSelectedParts();
            this.initSearchAutocomplete();
            this.calculatePayment();
        },
        
        /**
         * Привязка событий
         */
        bindEvents: function() {
            var self = this;
            
            // Поиск запчастей
            $('#parts-search').on('keyup', function() {
                var query = $(this).val();
                if (query.length >= 2) {
                    self.searchParts(query);
                } else if (query.length === 0) {
                    $('#parts-search-results').empty().hide();
                }
            });
            
            // Добавление запчасти
            $(document).on('click', '.add-part-btn', function() {
                var partId = $(this).data('id');
                var partName = $(this).data('name');
                var partSku = $(this).data('sku');
                var partPrice = parseFloat($(this).data('price'));
                var partQuantity = parseInt($(this).data('quantity'));
                self.addPart(partId, partName, partSku, partPrice, partQuantity);
            });
            
            // Удаление запчасти
            $(document).on('click', '.remove-part', function() {
                var partId = $(this).data('id');
                self.removePart(partId);
            });
            
            // Изменение количества
            $(document).on('change', '.part-quantity', function() {
                var partId = $(this).data('id');
                var quantity = parseInt($(this).val());
                self.updatePartQuantity(partId, quantity);
            });
            
            // Изменение полей калькулятора
            $('#work-cost, #work-hours, #standard-hours, #percent').on('change keyup', function() {
                self.calculatePayment();
            });
            
            // Выбор сотрудника
            $('#employee-id').on('change', function() {
                self.loadEmployeePercent($(this).val());
            });
            
            // Сохранение сделки
            $('#save-deal-btn').on('click', function(e) {
                e.preventDefault();
                self.saveDeal();
            });
        },
        
        /**
         * Поиск запчастей
         */
        searchParts: function(query) {
            var self = this;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_search_parts',
                    search: query,
                    nonce: akpp_deal.nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        self.displaySearchResults(response.data);
                    } else {
                        $('#parts-search-results').html('<div class="no-results">🔍 Запчасти не найдены</div>').show();
                    }
                },
                error: function() {
                    $('#parts-search-results').html('<div class="error">❌ Ошибка поиска</div>').show();
                }
            });
        },
        
        /**
         * Отображение результатов поиска
         */
        displaySearchResults: function(parts) {
            var html = '<div class="search-results-list">';
            
            for (var i = 0; i < parts.length; i++) {
                var part = parts[i];
                var inStock = part.quantity > 0;
                var stockClass = inStock ? 'in-stock' : 'out-of-stock';
                var stockText = inStock ? 'В наличии: ' + part.quantity : 'Нет в наличии';
                
                html += '<div class="search-result-item ' + stockClass + '">';
                html += '<div class="part-info">';
                html += '<div class="part-name">' + this.escapeHtml(part.name) + '</div>';
                html += '<div class="part-sku">' + this.escapeHtml(part.sku) + '</div>';
                html += '<div class="part-details">';
                html += '<span>💰 ' + this.formatNumber(part.price) + ' ₽</span>';
                html += '<span>📦 ' + stockText + '</span>';
                html += '</div>';
                html += '</div>';
                
                if (inStock) {
                    html += '<button type="button" class="add-part-btn" data-id="' + part.id + '" data-name="' + this.escapeHtml(part.name) + '" data-sku="' + this.escapeHtml(part.sku) + '" data-price="' + part.price + '" data-quantity="' + part.quantity + '">➕ Добавить</button>';
                } else {
                    html += '<button type="button" class="add-part-btn disabled" disabled>❌ Нет в наличии</button>';
                }
                html += '</div>';
            }
            
            html += '</div>';
            $('#parts-search-results').html(html).show();
        },
        
        /**
         * Добавление запчасти
         */
        addPart: function(id, name, sku, price, maxQuantity) {
            var existing = this.selectedParts.find(function(p) { return p.id == id; });
            
            if (existing) {
                this.showMessage('Эта запчасть уже добавлена', 'warning');
                return;
            }
            
            this.selectedParts.push({
                id: id,
                name: name,
                sku: sku,
                price: price,
                quantity: 1,
                maxQuantity: maxQuantity,
                total: price
            });
            
            this.renderPartsList();
            this.calculateTotal();
            this.showMessage('Запчасть добавлена: ' + name, 'success');
            
            $('#parts-search').val('');
            $('#parts-search-results').empty().hide();
        },
        
        /**
         * Удаление запчасти
         */
        removePart: function(id) {
            var part = this.selectedParts.find(function(p) { return p.id == id; });
            this.selectedParts = this.selectedParts.filter(function(p) { return p.id != id; });
            this.renderPartsList();
            this.calculateTotal();
            
            if (part) {
                this.showMessage('Запчасть удалена: ' + part.name, 'info');
            }
        },
        
        /**
         * Обновление количества запчасти
         */
        updatePartQuantity: function(id, quantity) {
            var part = this.selectedParts.find(function(p) { return p.id == id; });
            
            if (part) {
                if (quantity < 1) quantity = 1;
                if (quantity > part.maxQuantity) quantity = part.maxQuantity;
                
                part.quantity = quantity;
                part.total = part.price * quantity;
                this.renderPartsList();
                this.calculateTotal();
            }
        },
        
        /**
         * Отрисовка списка запчастей
         */
        renderPartsList: function() {
            var container = $('#selected-parts-list');
            var html = '';
            
            if (this.selectedParts.length === 0) {
                html = '<div class="empty-parts">📦 Запчасти не выбраны</div>';
            } else {
                html = '<table class="parts-table"><thead><tr>';
                html += '<th>Наименование</th>';
                html += '<th>Артикул</th>';
                html += '<th>Цена</th>';
                html += '<th>Кол-во</th>';
                html += '<th>Сумма</th>';
                html += '<th></th>';
                html += '</tr></thead><tbody>';
                
                for (var i = 0; i < this.selectedParts.length; i++) {
                    var part = this.selectedParts[i];
                    html += '<tr>';
                    html += '<td>' + this.escapeHtml(part.name) + '</td>';
                    html += '<td>' + this.escapeHtml(part.sku) + '</td>';
                    html += '<td>' + this.formatNumber(part.price) + ' ₽</td>';
                    html += '<td><input type="number" class="part-quantity" data-id="' + part.id + '" value="' + part.quantity + '" min="1" max="' + part.maxQuantity + '" style="width: 70px;"></td>';
                    html += '<td>' + this.formatNumber(part.total) + ' ₽</td>';
                    html += '<td><button type="button" class="remove-part button-link" data-id="' + part.id + '">🗑️</button></td>';
                    html += '</tr>';
                }
                
                html += '<tr class="total-row"><td colspan="4"><strong>Итого запчасти:</strong></td>';
                html += '<td colspan="2"><strong>' + this.formatNumber(this.getPartsTotal()) + ' ₽</strong></td></tr>';
                html += '</tbody></table>';
            }
            
            container.html(html);
        },
        
        /**
         * Получение суммы запчастей
         */
        getPartsTotal: function() {
            var total = 0;
            for (var i = 0; i < this.selectedParts.length; i++) {
                total += this.selectedParts[i].total;
            }
            return total;
        },
        
        /**
         * Расчет общей суммы
         */
        calculateTotal: function() {
            var partsTotal = this.getPartsTotal();
            $('#parts-total').text(this.formatNumber(partsTotal) + ' ₽');
            this.calculatePayment();
        },
        
        /**
         * Расчет оплаты сотрудника
         */
        calculatePayment: function() {
            var workCost = parseFloat($('#work-cost').val()) || 0;
            var workHours = parseFloat($('#work-hours').val()) || 0;
            var standardHours = parseFloat($('#standard-hours').val()) || 1;
            var percent = parseFloat($('#percent').val()) || 0;
            
            // Формула: work_cost × (work_hours / standard_hours) × (percent / 100)
            var payment = workCost * (workHours / standardHours) * (percent / 100);
            payment = Math.round(payment);
            
            $('#payment-amount').val(this.formatNumber(payment) + ' ₽');
            $('#deal-payment-amount').val(payment);
            
            var partsTotal = this.getPartsTotal();
            var totalAmount = partsTotal + workCost;
            $('#deal-total-amount').text(this.formatNumber(totalAmount) + ' ₽');
            $('#deal-total').val(totalAmount);
            
            // Обновляем отображение стоимости работ
            $('#work-cost-display').text(this.formatNumber(workCost) + ' ₽');
        },
        
        /**
         * Загрузка процента сотрудника
         */
        loadEmployeePercent: function(employeeId) {
            if (!employeeId) return;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_get_employee_percent',
                    employee_id: employeeId,
                    nonce: akpp_deal.nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data.percent) {
                        $('#percent').val(response.data.percent);
                        $('#percent').trigger('change');
                    }
                }
            });
        },
        
        /**
         * Загрузка сохраненных запчастей
         */
        loadSelectedParts: function() {
            var savedParts = $('#saved-parts-data').val();
            if (savedParts) {
                try {
                    this.selectedParts = JSON.parse(savedParts);
                    this.renderPartsList();
                    this.calculateTotal();
                } catch(e) {}
            }
        },
        
        /**
         * Сохранение сделки
         */
        saveDeal: function() {
            var self = this;
            var saveBtn = $('#save-deal-btn');
            var formData = $('#deal-form').serializeArray();
            
            formData.push({
                name: 'parts',
                value: JSON.stringify(this.selectedParts)
            });
            
            saveBtn.prop('disabled', true).text('⏳ Сохранение...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: $.param(formData) + '&action=akpp_save_deal&nonce=' + akpp_deal.nonce,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        self.showMessage('Сделка успешно сохранена!', 'success');
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url || '?page=akpp-crm-deals';
                        }, 1500);
                    } else {
                        self.showMessage(response.data.message || 'Ошибка сохранения сделки', 'error');
                        saveBtn.prop('disabled', false).text('💾 Сохранить сделку');
                    }
                },
                error: function() {
                    self.showMessage('Ошибка соединения с сервером', 'error');
                    saveBtn.prop('disabled', false).text('💾 Сохранить сделку');
                }
            });
        },
        
        /**
         * Инициализация автокомплита
         */
        initSearchAutocomplete: function() {
            $('#parts-search').attr('autocomplete', 'off');
        },
        
        /**
         * Показать сообщение
         */
        showMessage: function(message, type) {
            var messageDiv = $('#deal-message');
            if (!messageDiv.length) {
                messageDiv = $('<div id="deal-message" class="notice"></div>');
                $('.deal-form-container').before(messageDiv);
            }
            
            var className = type === 'success' ? 'notice-success' : (type === 'error' ? 'notice-error' : 'notice-warning');
            messageDiv.removeClass('notice-success notice-error notice-warning').addClass(className).html('<p>' + message + '</p>').show();
            
            setTimeout(function() {
                messageDiv.fadeOut();
            }, 3000);
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
         * Форматирование числа
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }
    };
    
    // Инициализация при загрузке
    $(document).ready(function() {
        if ($('#deal-form').length && typeof akpp_deal !== 'undefined') {
            AKPP_DealCalculator.init();
        }
    });
    
    window.AKPP_DealCalculator = AKPP_DealCalculator;
    
})(jQuery);
