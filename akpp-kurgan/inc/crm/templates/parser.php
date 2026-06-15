<?php
if (!defined('ABSPATH')) exit;

// Подключаем класс таблицы парсера
if (!class_exists('AKPP_Parser_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-parser-table.php';
}

// Инициализируем таблицу
$parser_table = new AKPP_Parser_Table();
$parser_table->prepare_items();

?>

<div class="wrap akpp-crm-wrap">
    <h1 style="color: var(--akpp-accent); margin-bottom: 20px;">🔍 Универсальный парсер + AI Анализ</h1>

    <!-- Форма парсинга -->
    <div class="akpp-card" style="max-width: 800px;">
        <div class="akpp-card-header">🌐 Извлечение контента со страницы</div>
        
        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
            <input type="url" id="akpp_parser_url" placeholder="Вставьте URL страницы (например, форум или статья)" style="flex: 1;" required>
            <button type="button" id="akpp_parse_btn" class="button button-primary" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600; white-space: nowrap;">
                🔍 Спарсить URL
            </button>
        </div>
        <small class="akpp-text-muted">Система автоматически очистит HTML от рекламы и скриптов, оставив только полезный текст и изображения.</small>

        <!-- Скрытые поля для передачи данных в JS -->
        <input type="hidden" id="akpp_parsed_item_id" value="">
        <textarea id="akpp_content_for_ai" style="display: none;"></textarea>

        <!-- Контейнер для результатов парсинга -->
        <div id="akpp_parser_results" style="display: none;"></div>
        
        <!-- Контейнер для результатов AI-анализа -->
        <div id="akpp_ai_results" style="display: none;"></div>
    </div>

    <hr style="border-color: var(--akpp-border); margin: 30px 0;">

    <!-- Таблица ранее спарсенных записей (Модерация) -->
    <div class="akpp-card">
        <div class="akpp-card-header">📋 История парсинга и модерация</div>
        
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
            <?php 
            $parser_table->search_box('Поиск по URL или заголовку', 'parser_search'); 
            $parser_table->display(); 
            ?>
        </form>
    </div>
</div>

<!-- Подключаем скрипт парсера (если он еще не подключен через enqueue) -->
<script>
// Переменные для JS уже должны быть доступны через wp_localize_script в class-akpp-crm.php
// Но на всякий случай проверим
if (typeof akppCRM === 'undefined') {
    var akppCRM = {
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>',
        nonce: '<?php echo wp_create_nonce("akpp_crm_nonce"); ?>'
    };
}
</script>
