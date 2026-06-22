<?php
/**
 * Шаблон страницы парсера и AI анализа
 */
if (!defined('ABSPATH')) exit;

// Подключаем класс таблицы
if (!class_exists('AKPP_Parser_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-parser-table.php';
}

global $wpdb;
$table = $wpdb->prefix . 'akpp_parser_items';

$stats = [
    'total' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
    'pending' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'"),
    'ai_processed' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'ai_processed'"),
    'approved' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'approved'"),
    'rejected' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'rejected'"),
];
?>

<div class="wrap akpp-crm-wrap">
    <div class="parser-page-header">
        <h1>🤖 Универсальный парсер + AI анализ</h1>
    </div>

    <!-- Статистика -->
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

    <!-- Форма парсинга -->
    <div class="parser-section">
        <h2>🌐 Парсинг URL</h2>
        <div class="parser-url-form">
            <input type="url" id="parse-url" class="parser-url-input" placeholder="https://www.transakpp.ru/...">
            <button id="single-parse-btn" class="button button-primary">🔍 Парсить</button>
            <button id="single-parse-ai-btn" class="button button-primary" style="background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%); color: #1a1f2e;">🤖 Парсить с AI</button>
            <button id="bulk-parse-btn" class="button">📚 Массовый парсинг</button>
            <button id="bulk-ai-btn" class="button">🧠 Массовый AI анализ</button>
        </div>
        <div id="bulk-urls-area" style="display: none; margin-top: 15px;">
            <textarea id="bulk-urls" rows="4" class="parser-url-input" style="width: 100%; min-height: 100px;" placeholder="Введите URL по одному на строку..."></textarea>
            <button id="start-bulk-parse" class="button button-primary" style="margin-top: 10px;">🚀 Начать массовый парсинг</button>
        </div>
        <div id="parse-message" style="margin-top: 15px; display: none;"></div>
    </div>

    <!-- Настройки Qwen API -->
    <div class="parser-section">
        <h2>🤖 Настройки Qwen API</h2>
        <div class="parser-url-form">
            <input type="password" id="openai-api-key" class="parser-api-input"
                   placeholder="Qwen API Key"
                   value="<?php echo esc_attr(get_option('akpp_openai_api_key', '')); ?>">
            <button id="save-openai-key" class="button button-primary">💾 Сохранить</button>
            <button id="check-openai-key" class="button">🔍 Проверить</button>
        </div>
        <div id="openai-status" style="margin-top: 10px; font-size: 12px;"></div>
        <p style="margin-top: 15px; color: #718096; font-size: 13px;">
            🔗 Получить API ключ: <a href="https://dashscope.console.aliyun.com/" target="_blank" style="color: #63b3ed;">https://dashscope.console.aliyun.com/</a>
        </p>
    </div>

    <!-- Таблица результатов -->
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

<!-- Модальное окно просмотра -->
<div id="view-item-modal" class="parser-modal">
    <div class="parser-modal-content">
        <div class="parser-modal-header">
            <h3>📄 Детали элемента</h3>
            <button id="close-modal" class="parser-modal-close">&times;</button>
        </div>
        <div id="modal-content" class="parser-modal-body"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Одиночный парсинг
    $('#single-parse-btn').on('click', function() {
        var url = $('#parse-url').val();
        if (!url) { showMessage('Введите URL', 'error'); return; }
        var btn = $(this);
        btn.prop('disabled', true).text('⏳ Парсинг...');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_parse_url',
                url: url,
                nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
            },
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

    // Парсинг с AI
    $('#single-parse-ai-btn').on('click', function() {
        var url = $('#parse-url').val();
        if (!url) { showMessage('Введите URL', 'error'); return; }
        var btn = $(this);
        btn.prop('disabled', true).text('⏳ Парсинг с AI...');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_parse_with_ai',
                url: url,
                nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showMessage(res.data.message || '✅ Парсинг с AI завершён', 'success');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showMessage(res.data.message || '❌ Ошибка', 'error');
                }
                btn.prop('disabled', false).text('🤖 Парсить с AI');
            },
            error: function() {
                showMessage('❌ Ошибка соединения', 'error');
                btn.prop('disabled', false).text('🤖 Парсить с AI');
            }
        });
    });

    // Массовый парсинг
    $('#bulk-parse-btn').on('click', function() {
        $('#bulk-urls-area').slideToggle();
    });

    $('#start-bulk-parse').on('click', function() {
        var urls = $('#bulk-urls').val();
        if (!urls) { showMessage('Введите URL', 'error'); return; }
        var btn = $(this);
        btn.prop('disabled', true).text('⏳ Парсинг...');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_bulk_parse',
                urls: urls.split('\n'),
                nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showMessage(res.data.message || '✅ Массовый парсинг завершён', 'success');
                    $('#bulk-urls').val('');
                    $('#bulk-urls-area').hide();
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
            data: {
                action: 'akpp_bulk_ai_analysis',
                nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showMessage(res.data.message || '✅ Массовый AI анализ запущен', 'success');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showMessage(res.data.message || '❌ Ошибка', 'error');
                }
                btn.prop('disabled', false).text('🧠 Массовый AI анализ');
            }
        });
    });

    // Сохранение Qwen ключа
    $('#save-openai-key').on('click', function() {
        var apiKey = $('#openai-api-key').val();
        if (!apiKey) { showMessage('Введите API ключ', 'error'); return; }
        var btn = $(this);
        btn.prop('disabled', true).text('⏳ Сохранение...');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_save_openai_settings',
                api_key: apiKey,
                model: 'qwen-turbo',
                nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showMessage('✅ Настройки сохранены', 'success');
                    $('#openai-status').html('<span style="color: #00ff88;">✅ Ключ сохранён</span>');
                } else {
                    showMessage('❌ Ошибка', 'error');
                }
                btn.prop('disabled', false).text('💾 Сохранить');
            }
        });
    });

    // Проверка ключа
    $('#check-openai-key').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('⏳ Проверка...');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_check_openai_key',
                nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    $('#openai-status').html('<span style="color: #00ff88;">✅ ' + res.data.message + '</span>');
                } else {
                    $('#openai-status').html('<span style="color: #fc8181;">❌ ' + res.data.message + '</span>');
                }
                btn.prop('disabled', false).text('🔍 Проверить');
            }
        });
    });

    // AI анализ конкретного элемента
    $(document).on('click', '.btn-ai-analyze', function() {
        var itemId = $(this).data('id');
        var btn = $(this);
        btn.prop('disabled', true).text('⏳');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_run_ai_analysis',
                item_id: itemId,
                nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
            },
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

    // Одобрение
    $(document).on('click', '.approve-item', function() {
        var itemId = $(this).data('id');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_approve_parser_item',
                id: itemId,
                nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showMessage('✅ Одобрено', 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                }
            }
        });
    });

    // Отклонение
    $(document).on('click', '.reject-item', function() {
        var itemId = $(this).data('id');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_reject_parser_item',
                id: itemId,
                nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showMessage('❌ Отклонено', 'error');
                    setTimeout(function() { location.reload(); }, 1000);
                }
            }
        });
    });

    // Просмотр элемента
    $(document).on('click', '.view-item', function() {
        var itemId = $(this).data('id');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_get_parser_item',
                id: itemId,
                nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    var item = res.data;
                    var content = '<h4>📄 Информация</h4>';
                    content += '<p><strong>URL:</strong> <a href="' + item.url + '" target="_blank">' + item.url + '</a></p>';
                    content += '<p><strong>Заголовок:</strong> ' + (item.title || '—') + '</p>';
                    content += '<p><strong>Тип:</strong> ' + (item.content_type || '—') + '</p>';
                    if (item.ai_analysis) {
                        content += '<h4>🤖 AI анализ</h4>';
                        content += '<pre>' + JSON.stringify(JSON.parse(item.ai_analysis), null, 2) + '</pre>';
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

    // Закрытие модалки
    $('#close-modal, #view-item-modal').on('click', function(e) {
        if (e.target === this) {
            $('#view-item-modal').removeClass('active');
        }
    });

    // Уведомления
    function showMessage(msg, type) {
        var $notice = $('<div class="parser-notice ' + type + '">' + msg + '</div>');
        $('.parser-page-header').after($notice);
        setTimeout(function() {
            $notice.fadeOut(300, function() { $(this).remove(); });
        }, 3000);
    }
});
</script>