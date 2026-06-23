<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('AKPP_Parts_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-parts-table.php';
}

global $wpdb;
$table = $wpdb->prefix . 'akpp_parts';

// Статистика
$total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
$total_value = (float)$wpdb->get_var("SELECT SUM(price * quantity) FROM {$table}");
$low_stock = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE quantity < 5 AND quantity > 0");
$out_of_stock = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE quantity = 0");

$by_category = $wpdb->get_results("SELECT category, COUNT(*) as cnt, SUM(quantity) as total_qty FROM {$table} GROUP BY category", ARRAY_A);
?>

<div class="wrap akpp-crm-wrap">
    <div class="parts-page-header">
        <h1>📦 Склад</h1>
        <button type="button" class="button button-primary akpp-open-modal" data-target="#akpp-part-modal">+ Добавить позицию</button>
    </div>

    <!-- Статистика -->
    <div class="parts-stats-grid">
        <div class="parts-stat-card">
            <div class="parts-stat-value total"><?php echo $total; ?></div>
            <div class="parts-stat-label">ПОЗИЦИЙ</div>
        </div>
        <div class="parts-stat-card">
            <div class="parts-stat-value value"><?php echo number_format($total_value, 0, ',', ' '); ?> ₽</div>
            <div class="parts-stat-label">СТОИМОСТЬ СКЛАДА</div>
        </div>
        <div class="parts-stat-card">
            <div class="parts-stat-value low" style="color:#f6ad55;"><?php echo $low_stock; ?></div>
            <div class="parts-stat-label">МАЛО ОСТАТКА</div>
        </div>
        <div class="parts-stat-card">
            <div class="parts-stat-value out" style="color:#fc8181;"><?php echo $out_of_stock; ?></div>
            <div class="parts-stat-label">НЕТ В НАЛИЧИИ</div>
        </div>
    </div>

    <!-- Категории -->
    <div class="parts-categories">
        <?php foreach ($by_category as $cat): 
            $icons = ['parts'=>'🔧','oils'=>'🛢️','filters'=>'🔰','consumables'=>'📎','tools'=>'🔨'];
            $icon = $icons[$cat['category']] ?? '📦';
        ?>
        <div class="parts-category-card">
            <div class="category-icon"><?php echo $icon; ?></div>
            <div class="category-name"><?php echo esc_html(ucfirst($cat['category'])); ?></div>
            <div class="category-count"><?php echo $cat['cnt']; ?> поз. / <?php echo intval($cat['total_qty']); ?> шт</div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Таблица -->
    <div class="parts-table-wrapper">
        <?php
        $parts_table = new AKPP_Parts_Table();
        $parts_table->prepare_items();
        ?>
        <form method="get">
            <input type="hidden" name="page" value="akpp-crm-parts">
            <?php $parts_table->search_box('Поиск по названию, артикулу', 'part_search'); ?>
            <?php $parts_table->display(); ?>
        </form>
    </div>
</div>

<!-- Модальное окно -->
<div id="akpp-part-modal" class="akpp-modal">
    <div class="akpp-modal-content">
        <span class="akpp-modal-close">&times;</span>
        <h2 id="part-modal-title">➕ Новая позиция</h2>
        
        <form class="akpp-ajax-form" data-action="akpp_save_part" id="part-form">
            <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
            <input type="hidden" name="id" id="part-id">
            
            <div class="part-form-grid">
                <div class="form-group">
                    <label>Наименование *</label>
                    <input type="text" name="name" id="part-name" required placeholder="Масло ATF Toyota">
                </div>
                <div class="form-group">
                    <label>Артикул (SKU)</label>
                    <input type="text" name="sku" id="part-sku" placeholder="08885-01234">
                </div>
            </div>
            
            <div class="part-form-grid">
                <div class="form-group">
                    <label>Категория *</label>
                    <select name="category" id="part-category" required>
                        <option value="parts">🔧 Запчасти</option>
                        <option value="oils">🛢️ Масла</option>
                        <option value="filters">🔰 Фильтры</option>
                        <option value="consumables">📎 Расходники</option>
                        <option value="tools">🔨 Инструмент</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Единица измерения</label>
                    <select name="unit" id="part-unit">
                        <option value="шт">шт</option>
                        <option value="л">литр</option>
                        <option value="кг">кг</option>
                        <option value="м">метр</option>
                        <option value="компл">комплект</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Описание</label>
                <textarea name="description" id="part-description" rows="2" placeholder="Краткое описание товара..."></textarea>
            </div>
            
            <div class="part-form-grid-3">
                <div class="form-group">
                    <label>Остаток</label>
                    <input type="number" name="quantity" id="part-quantity" min="0" value="0" step="0.1">
                </div>
                <div class="form-group">
                    <label>Цена закупа (₽)</label>
                    <input type="number" name="purchase_price" id="part-purchase-price" min="0" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>Наценка (%)</label>
                    <input type="number" name="markup_percent" id="part-markup" min="0" max="1000" step="0.01" value="30">
                </div>
            </div>
            
            <div class="part-form-grid">
                <div class="form-group">
                    <label>Конечная цена (₽) <small style="color:#718096;">авто или вручную</small></label>
                    <input type="number" name="price" id="part-price" min="0" step="0.01" value="0" style="background:#1a3a2e;border-color:#00ff88;color:#00ff88;font-weight:700;">
                </div>
                <div class="form-group">
                    <label>Поставщик</label>
                    <input type="text" name="supplier" id="part-supplier" placeholder="Exist, Emex, Автодо">
                </div>
            </div>
            
            <div class="form-group">
                <label>Место хранения</label>
                <input type="text" name="location" id="part-location" placeholder="Стеллаж 3, полка 2">
            </div>
            
            <div class="part-form-actions">
                <button type="button" class="button button-secondary akpp-modal-close">Отмена</button>
                <button type="submit" class="button button-primary">💾 Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Авто-расчёт цены при изменении закупа или наценки
    $('#part-purchase-price, #part-markup').on('input', calculatePrice);
    
    function calculatePrice() {
        var purchase = parseFloat($('#part-purchase-price').val()) || 0;
        var markup = parseFloat($('#part-markup').val()) || 0;
        var final = purchase * (1 + markup / 100);
        $('#part-price').val(final.toFixed(2));
    }
    
    // Открытие модалки
    $('.akpp-open-modal').on('click', function() {
        var target = $(this).data('target');
        $('#part-modal-title').text('➕ Новая позиция');
        $('#part-form')[0].reset();
        $('#part-id').val('');
        $(target).addClass('active');
    });
    
    // Закрытие
    $('.akpp-modal-close, .akpp-modal').on('click', function(e) {
        if (e.target === this || $(this).hasClass('akpp-modal-close')) {
            $('.akpp-modal').removeClass('active');
        }
    });
    
    // Редактирование
    $(document).on('click', '.btn-edit-part', function(e) {
        e.preventDefault();
        var d = $(this).data();
        $('#part-modal-title').text('✏️ Редактировать');
        $('#part-id').val(d.id);
        $('#part-name').val(d.name);
        $('#part-sku').val(d.sku);
        $('#part-category').val(d.category);
        $('#part-description').val(d.description);
        $('#part-quantity').val(d.quantity);
        $('#part-unit').val(d.unit);
        $('#part-purchase-price').val(d.purchasePrice);
        $('#part-markup').val(d.markup);
        $('#part-price').val(d.price);
        $('#part-supplier').val(d.supplier);
        $('#part-location').val(d.location);
        $('#akpp-part-modal').addClass('active');
    });
    
    // Удаление
    $(document).on('click', '.btn-delete-part', function(e) {
        e.preventDefault();
        if (!confirm('Удалить позицию?')) return;
        var id = $(this).data('id');
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'akpp_delete_part',
                id: id,
                nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showNotice('🗑️ Удалено', 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    showNotice(res.data.message || '❌ Ошибка', 'error');
                }
            }
        });
    });
    
    // Сохранение
    $('#part-form').off('submit').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('⏳ Сохранение...');
        
        var formData = $form.serializeArray();
        formData.push({name: 'action', value: $form.data('action')});
        
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showNotice(res.data.message || '✅ Сохранено', 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    showNotice(res.data.message || '❌ Ошибка', 'error');
                    $btn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                showNotice('❌ Ошибка соединения', 'error');
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    function showNotice(message, type) {
        var bgColor = type === 'success' ? '#00ff88' : '#fc8181';
        var textColor = type === 'success' ? '#0a0f1c' : '#fff';
        var $notice = $('<div style="position:fixed;top:20px;right:20px;background:' + bgColor + ';color:' + textColor + ';padding:16px 24px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:99999;font-weight:600;">' + message + '</div>');
        $('body').append($notice);
        setTimeout(function() { $notice.fadeOut(300, function() { $(this).remove(); }); }, 3000);
    }
});
</script>