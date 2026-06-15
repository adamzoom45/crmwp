/**
 * АКПП45 CRM - Admin JavaScript
 * Управляет AJAX-запросами, модальными окнами и динамическими расчетами в админке.
 */

jQuery(document).ready(function($) {
    'use strict';

    // Глобальные переменные из wp_localize_script
    const ajaxUrl = typeof akppCRM !== 'undefined' ? akppCRM.ajax_url : '/wp-admin/admin-ajax.php';
    const nonce = typeof akppCRM !== 'undefined' ? akppCRM.nonce : '';

    // ==========================================================================
    // 1. Утилиты
    // ==========================================================================

    /**
     * Показать уведомление (всплывающее или в форме)
     */
    function showMessage(container, message, type = 'success') {
        const cssClass = type === 'success' ? 'akpp-badge-success' : 'akpp-badge-danger';
        const html = `<div class="notice notice-${type} is-dismissible" style="margin-bottom: 15px; border-left-color: ${type === 'success' ? '#10b981' : '#ef4444'};">
                        <p><strong>${type === 'success' ? '✅ Успех:' : '❌ Ошибка:'}</strong> ${message}</p>
                      </div>`;
        
        if (container) {
            $(container).prepend(html).find('.notice').hide().slideDown(300);
            setTimeout(() => {
                $(container).find('.notice').slideUp(300, function() { $(this).remove(); });
            }, 5000);
        } else {
            // Глобальное уведомление вверху страницы
            $('.akpp-crm-wrap').prepend(html).find('.notice').hide().slideDown(300);
            setTimeout(() => {
                $('.akpp-crm-wrap .notice').slideUp(300, function() { $(this).remove(); });
            }, 5000);
        }
    }

    /**
     * Переключение состояния загрузки кнопки
     */
    function toggleLoading($btn, isLoading) {
        if (isLoading) {
            $btn.data('original-text', $btn.text()).prop('disabled', true).html('<span class="akpp-loading"></span> Обработка...');
        } else {
            $btn.prop('disabled', false).text($btn.data('original-text') || 'Сохранить');
        }
    }

    // ==========================================================================
    // 2. Модальные окна
    // ==========================================================================

    // Открытие модального окна
    $(document).on('click', '.akpp-open-modal', function(e) {
        e.preventDefault();
        const target = $(this).data('target');
        if (target) {
            $(target).fadeIn(200);
            $('body').css('overflow', 'hidden'); // Блокируем прокрутку фона
        }
    });

    // Закрытие модального окна
    $(document).on('click', '.akpp-modal-close, .akpp-modal', function(e) {
        if (e.target === this || $(this).hasClass('akpp-modal-close')) {
            $('.akpp-modal').fadeOut(200);
            $('body').css('overflow', '');
        }
    });

    // Закрытие по Escape
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.akpp-modal').fadeOut(200);
            $('body').css('overflow', '');
        }
    });

    // ==========================================================================
    // 3. Универсальная отправка AJAX-форм
    // ==========================================================================

    $(document).on('submit', '.akpp-ajax-form', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        const action = $form.data('action');
        const formData = new FormData(this);
        
        // Добавляем nonce и action
        formData.append('action', action);
        formData.append('nonce', nonce);

        toggleLoading($btn, true);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showMessage($form, response.data.message || 'Данные успешно сохранены', 'success');
                    
                    // Если есть callback для перезагрузки или обновления UI
                    if (typeof window.akppFormSuccess === 'function') {
                        window.akppFormSuccess(response.data, $form);
                    } else {
                        // По умолчанию: перезагрузка страницы через 1.5 сек для обновления таблицы
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                } else {
                    showMessage($form, response.data.message || 'Произошла неизвестная ошибка', 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage($form, 'Ошибка сети: ' + error, 'error');
            },
            complete: function() {
                toggleLoading($btn, false);
            }
        });
    });

    // ==========================================================================
    // 4. Динамический калькулятор сделки
    // ==========================================================================

    const $calcForm = $('#akpp-deal-calculator');
    if ($calcForm.length) {
        const calcInputs = $calcForm.find('input, select');
        
        function recalculateDeal() {
            const hours = parseFloat($calcForm.find('[name="hours"]').val()) || 0;
            const hourlyRate = parseFloat($calcForm.find('[name="hourly_rate"]').val()) || 0;
            const partsCost = parseFloat($calcForm.find('[name="parts_cost"]').val()) || 0;
            const partsMarkup = parseFloat($calcForm.find('[name="parts_markup"]').val()) || 0;
            const empPercent = parseFloat($calcForm.find('[name="emp_percent"]').val()) || 0;
            const complexity = parseFloat($calcForm.find('[name="complexity"]').val()) || 0;

            $.post(ajaxUrl, {
                action: 'akpp_calculate_deal',
                nonce: nonce,
                hours: hours,
                hourly_rate: hourlyRate,
                parts_cost: partsCost,
                parts_markup: partsMarkup,
                emp_percent: empPercent,
                complexity: complexity
            }, function(response) {
                if (response.success) {
                    const data = response.data;
                    $calcForm.find('.calc-labor-cost').text(data.labor_cost.toFixed(2) + ' ₽');
                    $calcForm.find('.calc-parts-total').text(data.total_parts_cost.toFixed(2) + ' ₽');
                    $calcForm.find('.calc-grand-total').text(data.grand_total.toFixed(2) + ' ₽').css('color', '#00ff88', 'font-weight', 'bold');
                    $calcForm.find('.calc-employee-payout').text(data.employee_payout.toFixed(2) + ' ₽');
                    $calcForm.find('.calc-company-profit').text(data.company_profit.toFixed(2) + ' ₽');
                    
                    // Автоматически обновляем скрытое поле итоговой суммы для отправки формы
                    $calcForm.find('[name="total_amount"]').val(data.grand_total);
                }
            });
        }

        // Пересчет при изменении любого поля с небольшой задержкой (debounce)
        let calcTimeout;
        calcInputs.on('input change', function() {
            clearTimeout(calcTimeout);
            calcTimeout = setTimeout(recalculateDeal, 300);
        });

        // Первоначальный расчет при загрузке
        recalculateDeal();
    }

    // ==========================================================================
    // 5. Быстрые действия в таблицах (например, смена статуса сделки)
    // ==========================================================================

    $(document).on('change', '.akpp-quick-status-change', function() {
        const $select = $(this);
        const dealId = $select.data('id');
        const newStatus = $select.val();
        const originalStatus = $select.data('original-status');

        $.post(ajaxUrl, {
            action: 'akpp_update_deal_status',
            nonce: nonce,
            id: dealId,
            status: newStatus
        }, function(response) {
            if (response.success) {
                $select.data('original-status', newStatus);
                showMessage(null, 'Статус успешно обновлен', 'success');
            } else {
                $select.val(originalStatus); // Откат при ошибке
                showMessage(null, response.data.message || 'Ошибка обновления статуса', 'error');
            }
        });
    });

    // ==========================================================================
    // 6. Подтверждение удаления (дополнительная защита)
    // ==========================================================================

    $(document).on('click', '.akpp-confirm-delete', function(e) {
        if (!confirm('⚠️ Вы уверены, что хотите безвозвратно удалить эту запись? Это действие нельзя отменить.')) {
            e.preventDefault();
        }
    });

});
