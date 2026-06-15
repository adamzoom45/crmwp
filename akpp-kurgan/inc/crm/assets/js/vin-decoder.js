/**
 * АКПП45 CRM - VIN Decoder JavaScript
 * Автоматическое заполнение полей автомобиля при вводе VIN-номера.
 */

jQuery(document).ready(function($) {
    'use strict';

    const ajaxUrl = typeof akppCRM !== 'undefined' ? akppCRM.ajax_url : '/wp-admin/admin-ajax.php';
    const nonce = typeof akppCRM !== 'undefined' ? akppCRM.nonce : '';

    // Ищем поле ввода VIN на странице (может быть в форме добавления авто или в форме сделки)
    const $vinInput = $('#akpp_vin_input, input[name="vin"]');
    
    if ($vinInput.length) {
        // Поля, которые будем заполнять автоматически
        const $brandInput = $('#akpp_brand_input, input[name="brand"]');
        const $modelInput = $('#akpp_model_input, input[name="model"]');
        const $yearInput = $('#akpp_year_input, input[name="year"]');
        const $engineInput = $('#akpp_engine_input, input[name="engine"]');
        
        // Индикатор загрузки (можно добавить span с классом .akpp-loading рядом с полем VIN)
        const $loadingIndicator = $vinInput.siblings('.akpp-vin-status');

        let debounceTimer;

        $vinInput.on('input', function() {
            const vin = $(this).val().trim().toUpperCase();
            
            // Очищаем предыдущий таймер
            clearTimeout(debounceTimer);

            // Удаляем недопустимые символы (I, O, Q запрещены в VIN)
            const cleanVin = vin.replace(/[^A-HJ-NPR-Z0-9]/g, '');
            if (cleanVin !== vin) {
                $(this).val(cleanVin);
            }

            // Запускаем декодирование только если введено ровно 17 символов
            if (cleanVin.length === 17) {
                debounceTimer = setTimeout(() => {
                    decodeVin(cleanVin);
                }, 800); // Задержка 800мс после окончания ввода
            } else {
                // Если символов меньше 17, очищаем индикатор и поля (опционально)
                if ($loadingIndicator) {
                    $loadingIndicator.html('').removeClass('akpp-text-success akpp-text-danger');
                }
            }
        });

        function decodeVin(vin) {
            if ($loadingIndicator) {
                $loadingIndicator.html('<span class="akpp-loading"></span> Расшифровка...').removeClass('akpp-text-success akpp-text-danger');
            }

            // Блокируем поля на время запроса
            $brandInput.prop('readonly', true).css('opacity', '0.6');
            $modelInput.prop('readonly', true).css('opacity', '0.6');
            $yearInput.prop('readonly', true).css('opacity', '0.6');
            $engineInput.prop('readonly', true).css('opacity', '0.6');

            $.post(ajaxUrl, {
                action: 'akpp_decode_vin',
                nonce: nonce,
                vin: vin
            }, function(response) {
                if (response.success && response.data.success) {
                    const data = response.data.data;
                    
                    // Заполняем поля полученными данными
                    if ($brandInput.length) $brandInput.val(data.brand);
                    if ($modelInput.length) $modelInput.val(data.model);
                    if ($yearInput.length) $yearInput.val(data.year);
                    if ($engineInput.length) $engineInput.val(data.engine);

                    // Показываем успешный статус
                    if ($loadingIndicator) {
                        const sourceText = response.data.source === 'cache' ? ' (из кэша)' : '';
                        $loadingIndicator.html(`✅ Расшифровано успешно${sourceText}`).addClass('akpp-text-success').removeClass('akpp-text-danger');
                    }
                } else {
                    // Обработка ошибки
                    const errorMsg = response.data ? response.data.message : 'Не удалось расшифровать VIN';
                    if ($loadingIndicator) {
                        $loadingIndicator.html(`❌ ${errorMsg}`).addClass('akpp-text-danger').removeClass('akpp-text-success');
                    }
                    
                    // Разблокируем поля для ручного ввода в случае ошибки
                    $brandInput.prop('readonly', false).css('opacity', '1');
                    $modelInput.prop('readonly', false).css('opacity', '1');
                    $yearInput.prop('readonly', false).css('opacity', '1');
                    $engineInput.prop('readonly', false).css('opacity', '1');
                }
            }).fail(function() {
                if ($loadingIndicator) {
                    $loadingIndicator.html('❌ Ошибка сети').addClass('akpp-text-danger').removeClass('akpp-text-success');
                }
                // Разблокируем поля
                $brandInput.prop('readonly', false).css('opacity', '1');
                $modelInput.prop('readonly', false).css('opacity', '1');
                $yearInput.prop('readonly', false).css('opacity', '1');
                $engineInput.prop('readonly', false).css('opacity', '1');
            });
        }
    }
});
