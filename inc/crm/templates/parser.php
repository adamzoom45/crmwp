<?php
/**
 * Шаблон парсера + AI анализ
 * @package AKPP_CRM
 */

if (!defined('ABSPATH')) exit;

// Подключаем класс таблицы
if (!class_exists('AKPP_Parser_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-parser-table.php';
}

global $wpdb;
$table = $wpdb->prefix . 'akpp_parser_items';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

if (!$table_exists) {
    echo '<div class="notice notice-error"><p>❌ Таблица wp_akpp_parser_items не существует</p></div>';
    return;
}

// Статистика
$stats = [
    'total'        => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
    'pending'      => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'"),
    'ai_processed' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'ai_processed'"),
    'approved'     => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'approved'"),
    'rejected'     => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'rejected'"),
];
?>

<div class="wrap akpp-crm-wrap">
    <div class="parser-page-header">
        <h1>🤖 Универсальный парсер + AI анализ</h1>
    </div>

    <!-- ============ СТАТИСТИКА ============ -->
    <div class="parser-stats-grid">
        <div class="parser-stat-card">
            <div class="parser-stat-value total"><?php echo $stats['total']; ?></div>
            <div class="parser-stat-label">Всего элементов</div>
        </div>
        <div class="parser-stat-card">
            <div class="parser-stat-value pending"><?php echo $stats['pending']; ?></div>
            <div class="parser-stat-label">Ожидает анализа</div>
        </div>
        <div class="parser-stat-card">
            <div class="parser-stat-value ai-processed"><?php echo $stats['ai_processed']; ?></div>
            <div class="parser-stat-label">AI обработано</div>
        </div>
        <div class="parser-stat-card">
            <div class="parser-stat-value approved"><?php echo $stats['approved']; ?></div>
            <div class="parser-stat-label">Одобрено</div>
        </div>
        <div class="parser-stat-card">
            <div class="parser-stat-value rejected"><?php echo $stats['rejected']; ?></div>
            <div class="parser-stat-label">Отклонено</div>
        </div>
    </div>

    <!-- ============ НАСТРОЙКА QWEN AI ============ -->
    <div class="parser-section">
        <h2>🤖 Настройка Qwen AI (расшифровка VIN + анализ)</h2>
        <p style="color:#8892a6;margin-bottom:15px;">
            API ключ DashScope (Alibaba Cloud). Получить:
            <a href="https://dashscope.console.aliyun.com/apiKey" target="_blank" style="color:#63b3ed;">dashscope.console.aliyun.com/apiKey</a>
        </p>

        <div class="parser-url-form" style="flex-direction:column;align-items:stretch;gap:15px;">
            <div>
                <label style="display:block;color:#c9d1d9;margin-bottom:5px;">API Ключ:</label>
                <input type="password" id="qwen-api-key" placeholder="sk-..."
                       style="width:100%;padding:10px;background:#0d1117;border:1px solid #30363d;border-radius:6px;color:#fff;">
            </div>

            <div>
                <label style="display:block;color:#c9d1d9;margin-bottom:5px;">Модель:</label>
                <select id="qwen-model" style="width:100%;max-width:300px;padding:10px;background:#0d1117;border:1px solid #30363d;border-radius:6px;color:#fff;">
                    <option value="qwen-plus">qwen-plus (рекомендуется)</option>
                    <option value="qwen-turbo">qwen-turbo (быстрее)</option>
                    <option value="qwen-max">qwen-max (точнее)</option>
                </select>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="button" id="qwen-save-btn" class="button button-primary">💾 Сохранить</button>
                <button type="button" id="qwen-test-btn" class="button">🔌 Проверить соединение</button>
            </div>

            <div id="qwen-status" style="margin-top:10px;padding:10px;border-radius:6px;display:none;"></div>
        </div>
    </div>

    <!-- ============ ПАРСИНГ URL ============ -->
    <div class="parser-section">
        <h2>🌐 Парсинг URL</h2>
        <div class="parser-url-form">
            <input type="url" id="parse-url" class="parser-url-input" placeholder="https://www.transakpp.ru/...">
            <button id="single-parse-btn" class="button button-primary">🔍 Парсить</button>
            <button id="bulk-parse-btn" class="button">📋 Массовый парсинг</button>
            <button id="bulk-ai-btn" class="button">🤖 Массовый AI анализ</button>
        </div>

        <div id="bulk-urls-area" style="display: none; margin-top: 15px;">
            <textarea id="bulk-urls" rows="4" class="parser-url-input" style="width: 100%; min-height: 100px;"
                      placeholder="Введите URL по одному на строку..."></textarea>
            <button id="start-bulk-parse" class="button button-primary" style="margin-top: 10px;">🚀 Начать массовый парсинг</button>
        </div>
    </div>

    <!-- ============ РЕЗУЛЬТАТЫ ПАРСИНГА ============ -->
    <div class="parser-section">
        <h2>📋 Результаты парсинга</h2>
        <form method="get">
            <input type="hidden" name="page" value="akpp-crm-parser">
            <?php
            $parser_table = new AKPP_Parser_Table();
            $parser_table->prepare_items();
            $parser_table->search_box('Поиск', 'parser_search');
            $parser_table->display();
            ?>
        </form>
    </div>
</div>

<!-- ============ МОДАЛЬНОЕ ОКНО ============ -->
<div id="view-item-modal" class="parser-modal">
    <div class="parser-modal-content">
        <div class="parser-modal-header">
            <h3>📄 Детали элемента</h3>
            <button id="close-modal" class="parser-modal-close">&times;</button>
        </div>
        <div id="modal-content" class="parser-modal-body"></div>
    </div>
</div>

<!-- ============ JAVASCRIPT ============ -->
<script>
jQuery(document).ready(function($) {
    var nonce = '<?php echo wp_create_nonce("akpp45_nonce"); ?>';

    // ========================================================================
    // УВЕДОМЛЕНИЯ
    // ========================================================================
    function showMessage(msg, type) {
        var bgColor = type === 'success' ? '#00ff88' : '#fc8181';
        var textColor = type === 'success' ? '#0a0f1c' : '#fff';
        var $notice = $('<div style="position:fixed;top:20px;right:20px;background:' + bgColor + ';color:' + textColor + ';padding:16px 24px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:99999;font-weight:600;">' + msg + '</div>');
        $('body').append($notice);
        setTimeout(function() { $notice.fadeOut(300, function() { $(this).remove(); }); }, 3000);
    }

    function showStatus(msg, type) {
        var bg = type === 'success' ? '#1a3a2a' : '#3a1a1a';
        var color = type === 'success' ? '#00ff88' : '#ff6b6b';
        $('#qwen-status').html(msg).css({ background: bg, color: color, display: 'block' });
    }

    // ========================================================================
    // НАСТРОЙКИ QWEN AI
    // ========================================================================

    // Загрузка текущих настроек
    $.post(ajaxurl, { action: 'akpp_get_qwen_settings', nonce: nonce }, function(res) {
        if (res.success) {
            if (res.data.has_key) {
                $('#qwen-api-key').attr('placeholder', 'Текущий: ' + res.data.key_masked + ' (введите новый для замены)');
            }
            $('#qwen-model').val(res.data.model);
        }
    });

    // Сохранение
    $('#qwen-save-btn').on('click', function() {
        var btn = $(this).prop('disabled', true).text('Сохранение...');
        $.post(ajaxurl, {
            action: 'akpp_save_qwen_settings',
            nonce: nonce,
            api_key: $('#qwen-api-key').val(),
            model: $('#qwen-model').val()
        }, function(res) {
            showStatus(res.data.message, res.success ? 'success' : 'error');
            if (res.success) $('#qwen-api-key').val('');
        }).always(function() {
            btn.prop('disabled', false).text('💾 Сохранить');
        });
    });

    // Тест соединения
    $('#qwen-test-btn').on('click', function() {
        var btn = $(this).prop('disabled', true).text('Проверка...');
        showStatus('⏳ Проверяем соединение...', 'success');
        $.post(ajaxurl, {
            action: 'akpp_test_qwen_connection',
            nonce: nonce
        }, function(res) {
            showStatus(res.data.message, res.success ? 'success' : 'error');
        }).always(function() {
            btn.prop('disabled', false).text('🔌 Проверить соединение');
        });
    });

    // ========================================================================
    // ПАРСИНГ
    // ========================================================================

    // Одиночный парсинг
    $('#single-parse-btn').on('click', function() {
        var url = $('#parse-url').val();
        if (!url) { showMessage('Введите URL', 'error'); return; }

        var btn = $(this);
        btn.prop('disabled', true).text('⏳ Парсинг...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'akpp_parse_url', url: url, nonce: nonce },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showMessage(res.data.message || '✅ URL распаршен', 'success');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showMessage(res.data.message || '❌ Ошибка', 'error');
                }
                btn.prop('disabled', false).text('🔍 Парсить');
            },
            error: function() {
                showMessage('❌ Ошибка соединения', 'error');
                btn.prop('disabled', false).text('🔍 Парсить');
            }
        });
    });

    // Массовый парсинг (показать/скрыть)
    $('#bulk-parse-btn').on('click', function() {
        $('#bulk-urls-area').slideToggle();
    });

    // Запуск массового парсинга
    $('#start-bulk-parse').on('click', function() {
        var urls = $('#bulk-urls').val();
        if (!urls) { showMessage('Введите URL', 'error'); return; }

        var btn = $(this);
        btn.prop('disabled', true).text('⏳ Парсинг...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'akpp_bulk_parse', urls: urls.split('\n'), nonce: nonce },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showMessage(res.data.message || '✅ Массовый парсинг завершён', 'success');
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    showMessage(res.data.message || '❌ Ошибка', 'error');
                }
                btn.prop('disabled', false).text('🚀 Начать массовый парсинг');
            }
        });
    });

    // Массовый AI анализ
    $('#bulk-ai-btn').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('⏳ Запуск...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'akpp_bulk_ai_analysis', nonce: nonce },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showMessage(res.data.message || '✅ AI анализ запущен', 'success');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showMessage(res.data.message || '❌ Ошибка', 'error');
                }
                btn.prop('disabled', false).text('🤖 Массовый AI анализ');
            }
        });
    });

    // ========================================================================
    // AI АНАЛИЗ ЭЛЕМЕНТА
    // ========================================================================

    $(document).on('click', '.btn-ai-analyze', function() {
        var itemId = $(this).data('id');
        var btn = $(this);
        btn.prop('disabled', true).text('⏳');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'akpp_run_ai_analysis', item_id: itemId, nonce: nonce },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showMessage('✅ AI анализ завершён', 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    showMessage(res.data.message || '❌ Ошибка', 'error');
                    btn.prop('disabled', false).text('🤖 AI');
                }
            }
        });
    });

    // ========================================================================
    // ПРОСМОТР ЭЛЕМЕНТА
    // ========================================================================

    $(document).on('click', '.btn-view-item', function() {
        var itemId = $(this).data('id');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'akpp_get_parser_item', id: itemId, nonce: nonce },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    var item = res.data;
                    var content = '<h4>📄 Информация</h4>';
                    content += '<p><strong>URL:</strong> <a href="' + item.url + '" target="_blank">' + item.url + '</a></p>';
                    content += '<p><strong>Заголовок:</strong> ' + (item.title || '—') + '</p>';
                    content += '<p><strong>Тип:</strong> ' + (item.content_type || '—') + '</p>';

                    if (item.ai_analysis) {
                        try {
                            content += '<h4>🤖 AI анализ</h4><pre>' + JSON.stringify(JSON.parse(item.ai_analysis), null, 2) + '</pre>';
                        } catch(e) {
                            content += '<h4>🤖 AI анализ</h4><pre>' + item.ai_analysis + '</pre>';
                        }
                    }

                    if (item.content) {
                        content += '<h4>📝 Содержимое</h4>';
                        content += '<div style="max-height:300px; overflow:auto; background:#0a0f1c; padding:15px; border-radius:6px;">' + item.content.substring(0, 3000) + '</div>';
                    }

                    $('#modal-content').html(content);
                    $('#view-item-modal').addClass('active');
                }
            }
        });
    });

    // ========================================================================
    // УДАЛЕНИЕ ЭЛЕМЕНТА
    // ========================================================================

    $(document).on('click', '.btn-delete-parser', function() {
        if (!confirm('Удалить элемент?')) return;

        var itemId = $(this).data('id');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'akpp_delete_parser_item', id: itemId, nonce: nonce },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showMessage('🗑️ Удалено', 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                }
            }
        });
    });

    // ========================================================================
    // ЗАКРЫТИЕ МОДАЛКИ
    // ========================================================================

    $('#close-modal, #view-item-modal').on('click', function(e) {
        if (e.target === this) $('#view-item-modal').removeClass('active');
    });
});
</script>