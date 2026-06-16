<?php
/**
 * Шаблон страницы парсера и AI анализа
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'akpp_parser_items';

$stats = [
    'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
    'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'"),
    'ai_processed' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'ai_processed'"),
    'approved' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'approved'"),
    'rejected' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'rejected'")
];
?>

<div class="wrap akpp-crm-wrap">
    <h1 class="wp-heading-inline">🤖 Универсальный парсер + AI анализ</h1>
    <hr class="wp-header-end">
    
    <!-- Статистика -->
    <div class="parser-stats" style="display: flex; gap: 15px; margin: 20px 0; flex-wrap: wrap;">
        <div class="stat-card" style="flex: 1; text-align: center;">
            <div class="stat-value"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Всего элементов</div>
        </div>
        <div class="stat-card" style="flex: 1; text-align: center;">
            <div class="stat-value" style="color: #ffc107;"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">Ожидает анализа</div>
        </div>
        <div class="stat-card" style="flex: 1; text-align: center;">
            <div class="stat-value" style="color: #17a2b8;"><?php echo $stats['ai_processed']; ?></div>
            <div class="stat-label">AI обработано</div>
        </div>
        <div class="stat-card" style="flex: 1; text-align: center;">
            <div class="stat-value" style="color: #28a745;"><?php echo $stats['approved']; ?></div>
            <div class="stat-label">Одобрено</div>
        </div>
        <div class="stat-card" style="flex: 1; text-align: center;">
            <div class="stat-value" style="color: #dc3545;"><?php echo $stats['rejected']; ?></div>
            <div class="stat-label">Отклонено</div>
        </div>
    </div>
    
    <!-- Форма парсинга -->
    <div class="parser-form-section" style="background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h2>🌐 Парсинг URL</h2>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="url" id="parse-url" class="regular-text" style="flex: 1; padding: 10px;" placeholder="Введите URL для парсинга...">
            <button id="single-parse-btn" class="button button-primary">🔍 Парсить</button>
            <button id="bulk-parse-btn" class="button">📚 Массовый парсинг</button>
            <button id="bulk-ai-btn" class="button">🤖 Массовый AI анализ</button>
        </div>
        
        <div id="bulk-urls-area" style="display: none; margin-top: 15px;">
            <textarea id="bulk-urls" rows="4" style="width: 100%;" placeholder="Введите URL по одному на строку..."></textarea>
            <button id="start-bulk-parse" class="button button-primary" style="margin-top: 10px;">🚀 Начать массовый парсинг</button>
        </div>
        
        <div id="parse-message" style="margin-top: 15px; display: none;"></div>
    </div>
    
    <!-- Настройки OpenAI -->
    <div class="openai-settings-section" style="background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h2>🤖 Настройки OpenAI</h2>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="password" id="openai-api-key" class="regular-text" style="flex: 1; padding: 10px;" 
                   placeholder="OpenAI API Key" value="<?php echo esc_attr(get_option('akpp_openai_api_key', '')); ?>">
            <button id="save-openai-key" class="button button-primary">💾 Сохранить</button>
            <button id="check-openai-key" class="button">🔍 Проверить</button>
        </div>
        <div id="openai-status" style="margin-top: 10px; font-size: 12px;"></div>
    </div>
    
    <!-- Таблица результатов -->
    <div class="parser-table-container">
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
<div id="view-item-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: #fff; max-width: 700px; width: 90%; max-height: 80vh; border-radius: 12px; overflow: hidden;">
        <div style="padding: 15px; background: #f8f9fa; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between;">
            <h3 style="margin: 0;">Детали элемента</h3>
            <button id="close-modal" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div id="modal-content" style="padding: 20px; overflow-y: auto; max-height: calc(80vh - 60px);"></div>
    </div>
</div>

<style>
.parser-stats .stat-card {
    background: #fff;
    padding: 15px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.stat-value { font-size: 28px; font-weight: bold; }
.stat-label { font-size: 13px; color: #666; margin-top: 5px; }
.content-type-badge, .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; }
</style>

<script>
jQuery(document).ready(function($) {
    // Одиночный парсинг
    $('#single-parse-btn').on('click', function() {
        var url = $('#parse-url').val();
        if (!url) { showMessage('Введите URL', 'error'); return; }
        
        var btn = $(this);
        btn.prop('disabled', true).text('⏳ Парсинг...');
        
        $.ajax({
            url: ajaxurl, type: 'POST',
            data: { action: 'akpp_parse_url', url: url, nonce: '<?php echo wp_create_nonce("akpp_parse_url_nonce"); ?>' },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showMessage(res.data.message, 'success');
                    setTimeout(function() { location.reload(); }, 1500);
                } else { showMessage(res.data.message, 'error'); }
                btn.prop('disabled', false).text('🔍 Парсить');
            },
            error: function() { showMessage('Ошибка', 'error'); btn.prop('disabled', false).text('🔍 Парсить'); }
        });
    });
    
    // Массовый парсинг
    $('#bulk-parse-btn').on('click', function() { $('#bulk-urls-area').slideToggle(); });
    $('#start-bulk-parse').on('click', function() {
        var urls = $('#bulk-urls').val();
        if (!urls) { showMessage('Введите URL', 'error'); return; }
        
        var btn = $(this);
        btn.prop('disabled', true).text('⏳ Парсинг...');
        
        $.ajax({
            url: ajaxurl, type: 'POST',
            data: { action: 'akpp_bulk_parse', urls: urls, nonce: '<?php echo wp_create_nonce("akpp_bulk_parse_nonce"); ?>' },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showMessage(res.data.message, 'success');
                    $('#bulk-urls').val('');
                    $('#bulk-urls-area').hide();
                    setTimeout(function() { location.reload(); }, 2000);
                } else { showMessage(res.data.message, 'error'); }
                btn.prop('disabled', false).text('🚀 Начать массовый парсинг');
            }
        });
    });
    
    // Массовый AI анализ
    $('#bulk-ai-btn').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('⏳ Запуск...');
        
        $.ajax({
            url: ajaxurl, type: 'POST',
            data: { action: 'akpp_bulk_ai_analysis', nonce: '<?php echo wp_create_nonce("akpp_bulk_ai_nonce"); ?>' },
            dataType: 'json',
            success: function(res) {
                if (res.success) { showMessage(res.data.message, 'success'); setTimeout(function() { location.reload(); }, 1500); }
                else { showMessage(res.data.message, 'error'); }
                btn.prop('disabled', false).text('🤖 Массовый AI анализ');
            }
        });
    });
    
    // Сохранение OpenAI ключа
    $('#save-openai-key').on('click', function() {
        var apiKey = $('#openai-api-key').val();
        if (!apiKey) { showMessage('Введите API ключ', 'error'); return; }
        
        var btn = $(this);
        btn.prop('disabled', true).text('⏳ Сохранение...');
        
        $.ajax({
            url: ajaxurl, type: 'POST',
            data: { action: 'akpp_save_openai_settings', openai_api_key: apiKey, nonce: '<?php echo wp_create_nonce("akpp_openai_settings_nonce"); ?>' },
            dataType: 'json',
            success: function(res) {
                if (res.success) { showMessage(res.data.message, 'success'); $('#openai-status').html('<span style="color: green;">✅ ' + res.data.status.message + '</span>'); }
                else { showMessage(res.data.message, 'warning'); $('#openai-status').html('<span style="color: orange;">⚠️ ' + res.data.status.message + '</span>'); }
                btn.prop('disabled', false).text('💾 Сохранить');
            }
        });
    });
    
    // Проверка OpenAI ключа
    $('#check-openai-key').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('⏳ Проверка...');
        
        $.ajax({
            url: ajaxurl, type: 'POST',
            data: { action: 'akpp_check_openai_key', nonce: '<?php echo wp_create_nonce("akpp_check_openai_nonce"); ?>' },
            dataType: 'json',
            success: function(res) {
                if (res.success && res.data.valid) { $('#openai-status').html('<span style="color: green;">✅ ' + res.data.message + '</span>'); }
                else { $('#openai-status').html('<span style="color: red;">❌ ' + res.data.message + '</span>'); }
                btn.prop('disabled', false).text('🔍 Проверить');
            }
        });
    });
    
    // Одобрение элемента
    $(document).on('click', '.approve-item', function() {
        var itemId = $(this).data('id'), row = $(this).closest('tr');
        $.ajax({
            url: ajaxurl, type: 'POST',
            data: { action: 'akpp_approve_parser_item', item_id: itemId, nonce: '<?php echo wp_create_nonce("akpp_approve_item_nonce"); ?>' },
            dataType: 'json',
            success: function(res) {
                if (res.success) { showMessage(res.data.message, 'success'); row.fadeOut(300, function() { $(this).remove(); }); }
                else { showMessage(res.data.message, 'error'); }
            }
        });
    });
    
    // Отклонение элемента
    $(document).on('click', '.reject-item', function() {
        var itemId = $(this).data('id');
        $.ajax({
            url: ajaxurl, type: 'POST',
            data: { action: 'akpp_reject_parser_item', item_id: itemId, nonce: '<?php echo wp_create_nonce("akpp_reject_item_nonce"); ?>' },
            dataType: 'json',
            success: function(res) { if (res.success) { showMessage(res.data.message, 'success'); location.reload(); } }
        });
    });
    
    // Просмотр элемента
    $(document).on('click', '.view-item', function() {
        var itemId = $(this).data('id');
        $.ajax({
            url: ajaxurl, type: 'POST',
            data: { action: 'akpp_get_parser_item', item_id: itemId, nonce: '<?php echo wp_create_nonce("akpp_get_parser_item_nonce"); ?>' },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    var item = res.data, content = '<h4>📄 Информация</h4><p><strong>URL:</strong> <a href="' + item.url + '" target="_blank">' + item.url + '</a></p>';
                    content += '<p><strong>Заголовок:</strong> ' + (item.title || '—') + '</p>';
                    content += '<p><strong>Тип:</strong> ' + (item.content_type || '—') + '</p>';
                    if (item.ai_analysis) content += '<h4>🤖 AI анализ</h4><pre>' + JSON.stringify(item.ai_analysis, null, 2) + '</pre>';
                    if (item.content) content += '<h4>📝 Содержимое</h4><div style="max-height:300px; overflow:auto;">' + item.content.substring(0, 3000) + '</div>';
                    $('#modal-content').html(content);
                    $('#view-item-modal').show();
                }
            }
        });
    });
    
    $('#close-modal, #view-item-modal').on('click', function(e) { if (e.target === this) $('#view-item-modal').hide(); });
    
    function showMessage(msg, type) {
        var div = $('#parse-message');
        div.removeClass('notice-success notice-error').addClass('notice-' + (type === 'success' ? 'success' : 'error')).html('<p>' + msg + '</p>').show();
        setTimeout(function() { div.fadeOut(); }, 5000);
    }
});
</script>
