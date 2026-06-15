/**
 * АКПП45 CRM - Калькулятор оплаты сотрудника в сделке
 * Автоматический пересчёт суммы на основе нормо-часов и процента сотрудника.
 *
 * @package AKPP_CRM
 * @version 4.2
 */

(function($) {
    'use strict';

    // Конфигурация селекторов (можно изменить под ваши реальные ID полей в форме)
    const CONFIG = {
        costInput: '#deal-work-cost',          // Стоимость работ (руб)
        workHoursInput: '#deal-work-hours',    // Затраченные часы
        standardHoursInput: '#deal-standard-hours', // Нормо-часы по каталогу
        percentInput: '#deal-employee-percent', // Процент сотрудника (%)
        totalOutput: '#deal-total-payment',    // Итоговая выплата (readonly)
        debugOutput: '#deal-calc-debug'        // Опционально: поле для отладки формулы
    };

    /**
     * Форматирование числа в валюту (RUB)
     */
    function formatCurrency(amount) {
        return new Intl.NumberFormat('ru-RU', {
            style: 'currency',
            currency: 'RUB',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    }

    /**
     * Основная функция расчёта
     */
    function calculatePayment() {
        // Получаем значения и преобразуем в числа с плавающей точкой
        const cost = parseFloat($(CONFIG.costInput).val()) || 0;
        const workHours = parseFloat($(CONFIG.workHoursInput).val()) || 0;
        const standardHours = parseFloat($(CONFIG.standardHoursInput).val()) || 0;
        const percent = parseFloat($(CONFIG.percentInput).val()) || 0;

        // Валидация: защита от отрицательных значений
        if (cost < 0 || workHours < 0 || standardHours < 0 || percent < 0) {
            $(CONFIG.totalOutput).val('Ошибка: отрицательные значения недопустимы').css('color', '#dc3232');
            return;
        }

        // Валидация: защита от деления на ноль
        if (standardHours === 0) {
            $(CONFIG.totalOutput).val('Укажите нормо-часы').css('color', '#dc3232');
            return;
        }

        // Формула: work_cost × (work_hours / standard_hours) × (percent / 100)
        const ratio = workHours / standardHours;
        const multiplier = percent / 100;
        const totalPayment = cost * ratio * multiplier;

        // Вывод результата
        $(CONFIG.totalOutput).val(formatCurrency(totalPayment)).css('color', '#00a32a'); // Зелёный цвет успеха

        // Опционально: вывод детализации для отладки (если поле существует)
        if ($(CONFIG.debugOutput).length) {
            const debugText = `Расчёт: ${cost} × (${workHours} / ${standardHours}) × (${percent}%) = ${totalPayment.toFixed(2)} ₽`;
            $(CONFIG.debugOutput).text(debugText);
        }
    }

    /**
     * Инициализация калькулятора
     */
    function initCalculator() {
        // Проверяем, существуют ли поля на текущей странице
        if (!$(CONFIG.costInput).length) {
            return; // Тихий выход, если это не страница сделки
        }

        // Навешиваем обработчики событий на все зависимые поля
        const $inputs = $(`${CONFIG.costInput}, ${CONFIG.workHoursInput}, ${CONFIG.standardHoursInput}, ${CONFIG.percentInput}`);
        
        // Событие 'input' срабатывает мгновенно при вводе (включая вставку из буфера)
        $inputs.on('input change', function() {
            // Небольшая задержка (debounce) для оптимизации при быстром вводе
            clearTimeout($(this).data('calcTimer'));
            $(this).data('calcTimer', setTimeout(calculatePayment, 150));
        });

        // Первоначальный расчёт при загрузке страницы (если поля уже заполнены, например, при редактировании)
        calculatePayment();

        console.log('✅ АКПП CRM: Калькулятор оплаты инициализирован.');
    }

    // Запуск после полной загрузки DOM
    $(document).ready(function() {
        initCalculator();
    });

    // Поддержка для блоков Gutenberg или AJAX-подгрузки форм (опционально)
    $(document).on('DOMNodeInserted', function(e) {
        if ($(e.target).find(CONFIG.costInput).length) {
            initCalculator();
        }
    });

})(jQuery);
