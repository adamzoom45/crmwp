/**
 * АКПП45 CRM - Декодер VIN-номеров (Frontend)
 * Валидация формата, AJAX-запрос к серверу, кэширование и автозаполнение полей.
 *
 * @package AKPP_CRM
 * @version 4.3
 */

(function($) {
    'use strict';

    // Конфигурация селекторов (должны совпадать с ID полей в форме сделки new-deal.php)
    const CONFIG = {
        vinInput: '#deal-vin-number',
        decodeBtn: '#deal-vin-decode-btn',
        // Целевые поля для автозаполнения
        targetMake: '#deal-car-make',
        targetModel: '#deal-car-model',
        targetYear: '#deal-car-year',
        targetBodyNumber: '#deal-body-number',
        // Поле для вывода статуса/ошибки
        statusMessage: '#deal-vin-status'
    };

    // Регулярное выражение для валидации VIN (17 символов, латиница и цифры, без букв I, O, Q)
    const VIN_REGEX = /^[A-HJ-NPR-Z0-9]{17}$/i;

    // Локальный кэш для сохранения результатов в текущей сессии (избегаем лишних запросов к API)
    const vinCache = {};

    /**
     * Валидация формата VIN
     */
    function isValidVin(vin) {
        return VIN_REGEX.test(vin.trim().toUpperCase());
    }

    /**
     * Отображение статуса (загрузка, успех, ошибка)
     */
    function showStatus(message, type = 'info') {
        const $status = $(CONFIG.statusMessage);
        if (!$status.length) return;

        $status.removeClass('vin-status-info vin-status-success vin-status-error')
               .addClass(`vin-status-${type}`)
               .text(message)
               .show();
    }

    /**
     * Очистка целевых полей
     */
    function clearTargetFields() {
        $(CONFIG.targetMake).val('').trigger('change');
        $(CONFIG.targetModel).val('').trigger('change');
        $(CONFIG.targetYear).val('').trigger('change');
        $(CONFIG.targetBodyNumber).val('').trigger('change');
    }

    /**
     * Автозаполнение полей данными из ответа сервера
     */
    function populateFields(data) {
        if (data.make) $(CONFIG.targetMake).val(data.make).trigger('change');
        if (data.model) $(CONFIG.targetModel).val(data.model).trigger('change');
        if (data.year) $(CONFIG.targetYear).val(data.year).trigger('change');
        if (data.body_number) $(CONFIG.targetBodyNumber).val(data.body_number).trigger('change');
    }

    /**
     * Основная функция декодирования
     */
    function decodeVin() {
        const rawVin = $(CONFIG.vinInput).val();
        const vin = rawVin.trim().toUpperCase();

        // 1. Валидация на стороне клиента
        if (!vin) {
            showStatus('Введите VIN-номер', 'info');
            return;
        }

        if (!isValidVin(vin)) {
            showStatus('Некорректный формат VIN (должно быть 17 символов, без букв I, O, Q)', 'error');
            clearTargetFields();
            return;
        }

        // 2. Проверка локального кэша
        if (vinCache[vin]) {
            showStatus('Данные загружены из кэша', 'success');
            populateFields(vinCache[vin]);
            return;
        }

        // 3. Проверка наличия конфигурации AJAX (передается из PHP через wp_localize_script)
        if (typeof akpp_vin_decoder_config === 'undefined') {
            console.error('AKPP VIN Decoder: Конфигурация akpp_vin_decoder_config не найдена.');
            showStatus('Ошибка инициализации декодера', 'error');
            return;
        }

        // 4. UI: Состояние загрузки
        const $btn = $(CONFIG.decodeBtn);
        const originalBtnText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none; margin-right:5px;"></span> Поиск...');
        showStatus('Декодирование VIN...', 'info');
        clearTargetFields();

        // 5. AJAX-запрос к серверу
        $.ajax({
            url: akpp_vin_decoder_config.ajax_url,
            type: 'POST',
            data: {
                action: 'akpp_decode_vin',
                nonce: akpp_vin_decoder_config.nonce,
                vin: vin
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Сохраняем в кэш
                    vinCache[vin] = response.data;
                    
                    // Заполняем поля
                    populateFields(response.data);
                    showStatus('Автомобиль успешно идентифицирован!', 'success');
                } else {
                    const errorMsg = response.data && response.data.message 
                        ? response.data.message 
                        : 'Не удалось распознать VIN. Проверьте правильность ввода или заполните поля вручную.';
                    showStatus(errorMsg, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AKPP VIN Decode Error:', status, error);
                showStatus('Ошибка соединения с сервером декодирования.', 'error');
            },
            complete: function() {
                // 6. Возврат кнопки в исходное состояние
                $btn.prop('disabled', false).html(originalBtnText);
            }
        });
    }

    /**
     * Инициализация обработчиков событий
     */
    function init() {
        const $vinInput = $(CONFIG.vinInput);
        const $decodeBtn = $(CONFIG.decodeBtn);

        // Если поля нет на странице, тихо выходим (чтобы не ломать другие страницы)
        if (!$vinInput.length) {
            return;
        }

        // Декодирование по клику на кнопку
        if ($decodeBtn.length) {
            $decodeBtn.on('click', function(e) {
                e.preventDefault();
                decodeVin();
            });
        }

        // Декодирование по потере фокуса (blur), если VIN валиден и поле было изменено
        let vinChanged = false;
        $vinInput.on('input', function() {
            vinChanged = true;
            // Скрываем сообщение об ошибке при начале нового ввода
            $(CONFIG.statusMessage).hide();
            
            // Автоматическое приведение к верхнему регистру для удобства пользователя
            $(this).val($(this).val().toUpperCase());
        });

        $vinInput.on('blur', function() {
            if (vinChanged && isValidVin($(this).val().trim())) {
                decodeVin();
                vinChanged = false;
            }
        });

        // Декодирование по нажатию Enter в поле VIN
        $vinInput.on('keypress', function(e) {
            if (e.which === 13) { // Enter
                e.preventDefault();
                decodeVin();
            }
        });

        console.log('✅ АКПП CRM: VIN-декодер инициализирован.');
    }

    // Запуск после загрузки DOM
    $(document).ready(function() {
        init();
    });

})(jQuery);
