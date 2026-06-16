/**
 * CRM АКПП45 - VIN декодер
 * 
 * @package AKPP45_CRM
 */

(function($) {
    'use strict';
    
    var AKPP_VIN_Decoder = {
        
        /**
         * Инициализация
         */
        init: function() {
            this.bindEvents();
            this.initAutocomplete();
        },
        
        /**
         * Привязка событий
         */
        bindEvents: function() {
            var self = this;
            
            // Кнопка расшифровки VIN
            $('#decode-vin-btn').on('click', function(e) {
                e.preventDefault();
                self.decodeVin();
            });
            
            // Автоматическая расшифровка при потере фокуса
            $('#vin-input').on('blur', function() {
                var vin = $(this).val().trim().toUpperCase();
                if (vin.length === 17) {
                    self.decodeVin();
                }
            });
            
            // Кнопка очистки формы
            $('#clear-vin-form').on('click', function(e) {
                e.preventDefault();
                self.clearForm();
            });
        },
        
        /**
         * Инициализация автодополнения
         */
        initAutocomplete: function() {
            var self = this;
            
            $('#vin-input').autocomplete({
                minLength: 3,
                source: function(request, response) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'akpp_vin_suggestions',
                            query: request.term,
                            nonce: akpp_vin.nonce
                        },
                        dataType: 'json',
                        success: function(data) {
                            if (data.success && data.data.length > 0) {
                                response(data.data);
                            } else {
                                response([]);
                            }
                        },
                        error: function() {
                            response([]);
                        }
                    });
                },
                select: function(event, ui) {
                    $('#vin-input').val(ui.item.value);
                    self.decodeVin();
                    return false;
                }
            });
        },
        
        /**
         * Декодирование VIN
         */
        decodeVin: function() {
            var self = this;
            var vin = $('#vin-input').val().trim().toUpperCase();
            var decodeBtn = $('#decode-vin-btn');
            var resultDiv = $('#vin-decode-result');
            
            // Валидация
            if (!vin) {
                this.showError('Введите VIN код автомобиля');
                return;
            }
            
            vin = vin.replace(/[^A-Z0-9]/g, '');
            
            if (vin.length !== 17) {
                this.showError('VIN код должен содержать 17 символов');
                return;
            }
            
            this.clearDecodeResult();
            
            decodeBtn.prop('disabled', true).html('<span class="spinner"></span> Расшифровка...');
            resultDiv.show().html('<div class="notice notice-info"><p>⏳ Расшифровка VIN кода... Это может занять несколько секунд.</p></div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_decode_vin',
                    vin: vin,
                    nonce: akpp_vin.nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        self.fillFormData(response.data);
                        self.showSuccess('VIN успешно расшифрован!');
                        $('.vin-fields input').css('background-color', '#e8f5e9').delay(500).animate({backgroundColor: '#fff'}, 500);
                    } else {
                        self.showError(response.data.message || 'Ошибка расшифровки VIN кода');
                        self.showManualEntry();
                    }
                    decodeBtn.prop('disabled', false).html('🔍 Расшифровать VIN');
                    resultDiv.hide();
                },
                error: function() {
                    self.showError('Ошибка соединения с сервером');
                    self.showManualEntry();
                    decodeBtn.prop('disabled', false).html('🔍 Расшифровать VIN');
                    resultDiv.hide();
                }
            });
        },
        
        /**
         * Заполнение формы данными
         */
        fillFormData: function(data) {
            // Основные поля
            if (data.make) $('#car-make').val(data.make).trigger('change');
            if (data.model) $('#car-model').val(data.model);
            if (data.year) $('#car-year').val(data.year);
            if (data.vin) $('#car-vin').val(data.vin);
            
            // Дополнительные поля
            if (data.engine_cylinders) $('#engine-cylinders').val(data.engine_cylinders);
            if (data.engine_model) $('#engine-model').val(data.engine_model);
            if (data.fuel_type) $('#fuel-type').val(data.fuel_type);
            if (data.drive_type) $('#drive-type').val(data.drive_type);
            if (data.transmission_style) $('#transmission-style').val(data.transmission_style);
            if (data.body_class) $('#body-class').val(data.body_class);
            if (data.manufacturer) $('#manufacturer').val(data.manufacturer);
            if (data.plant_country) $('#plant-country').val(data.plant_country);
            
            // Рынок
            if (data.market) {
                $('#car-market').val(data.market);
                this.updateMarketBadge(data.market);
            }
            
            // АКПП
            if (data.transmission_id) {
                $('#transmission-id').val(data.transmission_id);
                $('#transmission-code').val(data.transmission_code).trigger('change');
                $('#transmission-type').val(data.transmission_type);
            }
            
            // Сохраняем в скрытое поле
            $('#decoded-vin-data').val(JSON.stringify(data));
            
            // Триггерим событие
            $(document).trigger('vin-decoded', [data]);
        },
        
        /**
         * Очистка формы
         */
        clearForm: function() {
            $('.vin-fields input, .vin-fields select, .vin-fields textarea').each(function() {
                if ($(this).attr('type') !== 'submit' && $(this).attr('type') !== 'button') {
                    $(this).val('');
                }
            });
            
            $('#vin-input').val('');
            $('#car-market').val('');
            this.updateMarketBadge('');
            $('#decoded-vin-data').val('');
            $('.vin-fields input').css('background-color', '');
            
            this.showSuccess('Форма очищена');
        },
        
        /**
         * Очистка результата декодирования
         */
        clearDecodeResult: function() {
            $('#vin-decode-result').empty().hide();
        },
        
        /**
         * Обновление бейджа рынка
         */
        updateMarketBadge: function(market) {
            var badge = $('#market-badge');
            var marketNames = {
                'japan': '🇯🇵 Япония',
                'asia': '🌏 Азия',
                'europe': '🇪🇺 Европа',
                'usa': '🇺🇸 США'
            };
            
            if (market && marketNames[market]) {
                badge.html(marketNames[market]).show();
                badge.removeClass('market-japan market-asia market-europe market-usa').addClass('market-' + market);
            } else {
                badge.hide();
            }
        },
        
        /**
         * Показать ручной ввод
         */
        showManualEntry: function() {
            var manualDiv = $('#manual-entry-warning');
            if (manualDiv.length) {
                manualDiv.show();
                setTimeout(function() {
                    manualDiv.fadeOut();
                }, 5000);
            }
        },
        
        /**
         * Показать сообщение об успехе
         */
        showSuccess: function(message) {
            var messageDiv = $('#vin-message');
            if (!messageDiv.length) {
                messageDiv = $('<div id="vin-message" class="notice notice-success"></div>');
                $('.vin-fields').before(messageDiv);
            }
            messageDiv.html('<p>✅ ' + message + '</p>').show();
            setTimeout(function() {
                messageDiv.fadeOut();
            }, 3000);
        },
        
        /**
         * Показать сообщение об ошибке
         */
        showError: function(message) {
            var messageDiv = $('#vin-message');
            if (!messageDiv.length) {
                messageDiv = $('<div id="vin-message" class="notice notice-error"></div>');
                $('.vin-fields').before(messageDiv);
            }
            messageDiv.html('<p>❌ ' + message + '</p>').show();
            setTimeout(function() {
                messageDiv.fadeOut();
            }, 5000);
        }
    };
    
    // Инициализация при загрузке
    $(document).ready(function() {
        if ($('#vin-input').length && typeof akpp_vin !== 'undefined') {
            AKPP_VIN_Decoder.init();
        }
    });
    
    window.AKPP_VIN_Decoder = AKPP_VIN_Decoder;
    
})(jQuery);
