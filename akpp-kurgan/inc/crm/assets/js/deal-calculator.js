/**
 * Калькулятор сделок и управление запчастями для CRM АКПП45
 * 
 * @package AKPP45_CRM
 */

(function($) {
    'use strict';
    
    var DealCalculator = {
        // Выбранные запчасти
        selectedParts: [],
        
        // Инициализация
        init: function() {
            this.bindEvents();
            this.loadSelectedParts();
            this.initSearchAutocomplete();
        },
        
        // Привязка событий
        bindEvents: function() {
            var self = this;
            
            // Поиск запчастей
            $(document).on('keyup', '#parts-search', function(e) {
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
            
            // Изменение количества запчасти
            $(document).on('change', '.part-quantity', function() {
                var partId = $(this).data('id');
                var quantity = parseInt($(this).val());
                self.updatePartQuantity(partId, quantity);
            });
            
            // Изменение полей калькулятора
            $(document).on('change keyup', '#work-cost, #work-hours, #standard-hours, #percent', function() {
                self.calculatePayment();
            });
            
            // Выбор сотрудника
            $(document).on('change', '#employee-id', function() {
                self.loadEmployeePercent($(this).val());
            });
            
            // Сохранение сделки
            $(document).on('click', '#save-deal-btn', function(e) {
                e.preventDefault();
                self.saveDeal();
            });
        },
        
        // Поиск запчастей
        searchParts: function(query) {
            var self = this;
            var resultsDiv = $('#parts-search-results');
            
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
                        resultsDiv.html('<div class="no-results">🔍 Запчасти не найдены</div>').show();
                    }
                },
                error: function() {
                    resultsDiv.html('<div class="error">❌ Ошибка поиска</div>').show();
                }
            });
        },
        
        // Отображение результатов поиска
        displaySearchResults: function(parts) {
            var html = '<div class="search-results-list">';
            for (var i = 0; i < parts.length; i++) {
                var part = parts[i];
                var inStock = part.quantity > 0;
                var stockClass = inStock ? 'in-stock' : 'out-of-stock';
                var stockText = inStock ? 'В наличии: ' + part.quantity : 'Нет в наличии';
                
                html += '<div class="search-result-item ' + stockClass + '">';
                html += '<div class="part-info">';
                html += '<div class="part-name">' + escapeHtml(part.name) + '</div>';
                html += '<div class="part-sku">' + escapeHtml(part.sku) + '</div>';
                html += '<div class="part-details">';
                html += '<span>💰 ' + formatNumber(part.price) + ' ₽</span>';
                html += '<span>📦 ' + stockText + '</span>';
                html += '</div>';
                html += '</div>';
                
                if (inStock) {
                    html += '<button type="button" class="add-part-btn" data-id="' + part.id + '" data-name="' + escapeHtml(part.name) + '" data-sku="' + escapeHtml(part.sku) + '" data-price="' + part.price + '" data-quantity="' + part.quantity + '">➕ Добавить</button>';
                } else {
                    html += '<button type="button" class="add-part-btn disabled" disabled>❌ Нет в наличии</button>';
                }
                html += '</div>';
            }
            html += '</div>';
            
            $('#parts-search-results').html(html).show();
        },
        
        // Добавление запчасти
        addPart: function(id, name, sku, price, maxQuantity) {
            var self = this;
            
            // Проверяем, не добавлена ли уже
            var existing = this.selectedParts.find(function(p) { return p.id == id; });
            if (existing) {
                this.showMessage('Эта запчасть уже добавлена', 'warning');
                return;
            }
            
            // Добавляем в массив
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
            
            // Очищаем поиск
            $('#parts-search').val('');
            $('#parts-search-results').empty().hide();
        },
        
        // Удаление запчасти
        removePart: function(id) {
            var self = this;
            var part = this.selectedParts.find(function(p) { return p.id == id; });
            
            this.selectedParts = this.selectedParts.filter(function(p) { return p.id != id; });
            this.renderPartsList();
            this.calculateTotal();
            
            if (part) {
                this.showMessage('Запчасть удалена: ' + part.name, 'info');
            }
        },
        
        // Обновление количества запчасти
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
        
        // Отрисовка списка запчастей
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
                    html += '<td>' + escapeHtml(part.name) + '</td>';
                    html += '<td>' + escapeHtml(part.sku) + '</td>';
                    html += '<td>' + formatNumber(part.price) + ' ₽</td>';
                    html += '<td><input type="number" class="part-quantity" data-id="' + part.id + '" value="' + part.quantity + '" min="1" max="' + part.maxQuantity + '" style="width: 70px;"></td>';
                    html += '<td>' + formatNumber(part.total) + ' ₽</td>';
                    html += '<td><button type="button" class="remove-part button-link" data-id="' + part.id + '">🗑️</button></td>';
                    html += '</tr>';
                }
                
                html += '<tr class="total-row"><td colspan="4"><strong>Итого запчасти:</strong></td>';
                html += '<td colspan="2"><strong>' + formatNumber(this.getPartsTotal()) + ' ₽</strong></td></tr>';
                html += '</tbody></table>';
            }
            
            container.html(html);
        },
        
        // Получение суммы запчастей
        getPartsTotal: function() {
            var total = 0;
            for (var i = 0; i < this.selectedParts.length; i++) {
                total += this.selectedParts[i].total;
            }
            return total;
        },
        
        // Расчет общей суммы и оплаты сотрудника
        calculateTotal: function() {
            var partsTotal = this.getPartsTotal();
            $('#parts-total').val(formatNumber(partsTotal) + ' ₽');
            
            this.calculatePayment();
        },
        
        // Расчет оплаты сотрудника
        calculatePayment: function() {
            var workCost = parseFloat($('#work-cost').val()) || 0;
            var workHours = parseFloat($('#work-hours').val()) || 0;
            var standardHours = parseFloat($('#standard-hours').val()) || 1;
            var percent = parseFloat($('#percent').val()) || 0;
            
            // Формула: work_cost × (work_hours / standard_hours) × (percent / 100)
            var payment = workCost * (workHours / standardHours) * (percent / 100);
            payment = Math.round(payment);
            
            $('#payment-amount').val(formatNumber(payment) + ' ₽');
            
            // Обновляем скрытое поле
            $('#deal-payment-amount').val(payment);
            
            // Обновляем итоговую сумму сделки
            var partsTotal = this.getPartsTotal();
            var totalAmount = partsTotal + workCost;
            $('#deal-total-amount').val(formatNumber(totalAmount) + ' ₽');
            $('#deal-total').val(totalAmount);
        },
        
        // Загрузка процента сотрудника
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
        
        // Загрузка сохраненных запчастей (при редактировании)
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
        
        // Сохранение сделки
        saveDeal: function() {
            var self = this;
            var saveBtn = $('#save-deal-btn');
            var formData = $('#deal-form').serializeArray();
            
            // Добавляем запчасти
            formData.push({
                name: 'parts',
                value: JSON.stringify(this.selectedParts)
            });
            
            saveBtn.prop('disabled', true).text('⏳ Сохранение...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData.concat([
                    {name: 'action', value: 'akpp_save_deal'},
                    {name: 'nonce', value: akpp_deal.nonce}
                ]),
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
        
        // Инициализация автокомплита для поиска
        initSearchAutocomplete: function() {
            // Дополнительная настройка для поиска
            $('#parts-search').attr('autocomplete', 'off');
        },
        
        // Показать сообщение
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
        }
    };
    
    // Вспомогательные функции
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }
    
    // Инициализация
    $(document).ready(function() {
        if ($('#deal-form').length) {
            DealCalculator.init();
        }
    });
    
    window.DealCalculator = DealCalculator;
    
})(jQuery);
