/**
 * АКПП45 CRM - Parser & AI Analyzer JavaScript
 * Управляет процессом парсинга внешних страниц и последующим AI-анализом.
 */

jQuery(document).ready(function($) {
    'use strict';

    const ajaxUrl = typeof akppCRM !== 'undefined' ? akppCRM.ajax_url : '/wp-admin/admin-ajax.php';
    const nonce = typeof akppCRM !== 'undefined' ? akppCRM.nonce : '';

    // Элементы интерфейса парсера
    const $urlInput = $('#akpp_parser_url');
    const $parseBtn = $('#akpp_parse_btn');
    const $parseResults = $('#akpp_parser_results');
    const $aiAnalyzeBtn = $('#akpp_ai_analyze_btn');
    const $aiResults = $('#akpp_ai_results');
    
    // Скрытое поле для хранения ID распарсенной записи (для модерации)
    const $parsedItemId = $('#akpp_parsed_item_id');
    // Скрытое поле или textarea с полным текстом для AI
    const $contentForAi = $('#akpp_content_for_ai');

    // ==========================================================================
    // 1. Запуск парсинга URL
    // ==========================================================================

    $parseBtn.on('click', function(e) {
        e.preventDefault();
        
        const url = $urlInput.val().trim();
        if (!url) {
            alert('Пожалуйста, введите корректный URL');
            return;
        }

        // UI: состояние загрузки
        $parseBtn.prop('disabled', true).html('<span class="akpp-loading"></span> Парсинг...');
        $parseResults.hide();
        $aiResults.hide();

        $.post(ajaxUrl, {
            action: 'akpp_parse_url',
            nonce: nonce,
            url: url
        }, function(response) {
            if (response.success) {
                const data = response.data;
                
                // Сохраняем ID для последующей модерации (одобрить/отклонить)
                $parsedItemId.val(data.id);
                $contentForAi.val(data.content_preview || ''); // Или полный контент, если он передается

                // Отображаем результаты
                let imagesHtml = '';
                if (data.images_count > 0) {
                    imagesHtml = `<p class="akpp-text-muted"><small>🖼️ Найдено изображений: ${data.images_count}</small></p>`;
                }

                $parseResults.html(`
                    <div class="akpp-card" style="margin-top: 20px; border-left: 4px solid var(--akpp-accent);">
                        <h4 style="margin-top: 0; color: var(--akpp-accent);">${data.title}</h4>
                        <p>${data.content_preview}</p>
                        ${imagesHtml}
                        <div style="margin-top: 15px;">
                            <button type="button" id="akpp_ai_analyze_btn" class="button button-primary">
                                🤖 Запустить AI-анализ
                            </button>
                        </div>
                    </div>
                `).fadeIn(300);

                // Переназначаем обработчик для новой кнопки AI (так как мы перезаписали HTML)
                $(document).off('click', '#akpp_ai_analyze_btn').on('click', '#akpp_ai_analyze_btn', runAiAnalysis);

            } else {
                $parseResults.html(`<div class="notice notice-error" style="margin-top: 20px;"><p>❌ ${response.data.message}</p></div>`).fadeIn(300);
            }
        }).always(function() {
            $parseBtn.prop('disabled', false).text('🔍 Спарсить URL');
        });
    });

    // ==========================================================================
    // 2. Запуск AI-анализа
    // ==========================================================================

    function runAiAnalysis(e) {
        if (e) e.preventDefault();

        const textToAnalyze = $contentForAi.val() || $urlInput.val(); // Если контент пуст, анализируем сам URL или описание
        
        if (!textToAnalyze) {
            alert('Нет данных для анализа');
            return;
        }

        const $btn = $(e ? e.target : '#akpp_ai_analyze_btn');
        $btn.prop('disabled', true).html('<span class="akpp-loading"></span> AI думает...');
        $aiResults.hide();

        $.post(ajaxUrl, {
            action: 'akpp_analyze_with_ai',
            nonce: nonce,
            text: textToAnalyze
        }, function(response) {
            if (response.success) {
                const data = response.data.data || {};
                
                // Формируем красивый вывод результатов AI
                let severityColor = '#10b981'; // green
                if (data.severity >= 7) severityColor = '#ef4444'; // red
                else if (data.severity >= 4) severityColor = '#f59e0b'; // yellow

                let causesHtml = '';
                if (Array.isArray(data.probable_causes)) {
                    causesHtml = '<ul style="margin: 10px 0; padding-left: 20px;">' + 
                        data.probable_causes.map(c => `<li>${c}</li>`).join('') + 
                        '</ul>';
                }

                let partsHtml = '';
                if (Array.isArray(data.recommended_parts)) {
                    partsHtml = '<div style="background: var(--akpp-bg-tertiary); padding: 10px; border-radius: 6px; margin-top: 10px;">' +
                        '<strong>🔧 Рекомендуемые запчасти:</strong><br>' +
                        data.recommended_parts.map(p => `<span class="akpp-badge akpp-badge-primary" style="margin: 3px;">${p}</span>`).join('') +
                        '</div>';
                }

                $aiResults.html(`
                    <div class="akpp-card" style="margin-top: 20px; border-left: 4px solid #3b82f6;">
                        <h4 style="margin-top: 0; color: #3b82f6;">🧠 Результаты AI-анализа</h4>
                        
                        <p><strong>Тип проблемы:</strong> ${data.problem_type || 'Не определено'}</p>
                        <p><strong>Серьезность:</strong> 
                            <span style="color: ${severityColor}; font-weight: bold; font-size: 18px;">${data.severity || 0}/10</span>
                        </p>
                        
                        <p><strong>Вероятные причины:</strong></p>
                        ${causesHtml}
                        
                        ${partsHtml}
                        
                        ${data.recommended_oil ? `<p style="margin-top: 10px;"><strong>🛢️ Масло:</strong> ${data.recommended_oil}</p>` : ''}
                        
                        <div style="margin-top: 15px; padding: 10px; background: rgba(0, 255, 136, 0.1); border-radius: 6px; border: 1px solid var(--akpp-accent);">
                            <strong>💡 Совет клиенту:</strong><br>
                            ${data.advice_for_client || 'Рекомендуется провести компьютерную диагностику.'}
                        </div>
                    </div>
                `).fadeIn(300);

            } else {
                $aiResults.html(`<div class="notice notice-error" style="margin-top: 20px;"><p>❌ ${response.data.message}</p></div>`).fadeIn(300);
            }
        }).always(function() {
            const $btn = $('#akpp_ai_analyze_btn');
            $btn.prop('disabled', false).text('🤖 Запустить AI-анализ');
        });
    }

    // ==========================================================================
    // 3. Модерация (Одобрить / Отклонить)
    // ==========================================================================

    $(document).on('click', '.akpp-moderate-action', function(e) {
        e.preventDefault();
        const action = $(this).data('action'); // 'approve' or 'reject'
        const itemId = $parsedItemId.val();
        
        if (!itemId) return;

        // В реальном приложении здесь будет AJAX запрос на обновление статуса
        // Для демонстрации просто покажем уведомление и обновим UI
        const message = action === 'approve' ? '✅ Запись одобрена и добавлена в базу знаний.' : '❌ Запись отклонена.';
        
        alert(message); // В продакшене заменить на showMessage() из admin.js
        
        // Сброс формы
        $urlInput.val('');
        $parseResults.hide();
        $aiResults.hide();
        $parsedItemId.val('');
        $contentForAi.val('');
    });

});
