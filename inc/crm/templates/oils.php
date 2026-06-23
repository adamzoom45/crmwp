<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'akpp_parts';

// Получаем ТОЛЬКО масла из склада
$oils = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table} WHERE category = %s ORDER BY name ASC",
    'oils'
));

$total_oils = count($oils);
$total_volume = (float)$wpdb->get_var($wpdb->prepare(
    "SELECT SUM(quantity) FROM {$table} WHERE category = %s AND unit = %s",
    'oils', 'л'
));
$total_value = (float)$wpdb->get_var($wpdb->prepare(
    "SELECT SUM(price * quantity) FROM {$table} WHERE category = %s",
    'oils'
));
?>

<div class="wrap akpp-crm-wrap">
    <div class="oils-page-header">
        <h1>🛢️ Масла</h1>
        <button type="button" class="button button-primary akpp-open-modal" data-target="#akpp-oil-modal">+ Добавить масло</button>
    </div>

    <!-- Статистика -->
    <div class="oils-stats-grid">
        <div class="oils-stat-card">
            <div class="oils-stat-value"><?php echo $total_oils; ?></div>
            <div class="oils-stat-label">НАИМЕНОВАНИЙ</div>
        </div>
        <div class="oils-stat-card">
            <div class="oils-stat-value"><?php echo number_format($total_volume, 1, ',', ' '); ?> л</div>
            <div class="oils-stat-label">ОБЩИЙ ОБЪЁМ</div>
        </div>
        <div class="oils-stat-card">
            <div class="oils-stat-value"><?php echo number_format($total_value, 0, ',', ' '); ?> ₽</div>
            <div class="oils-stat-label">СТОИМОСТЬ</div>
        </div>
    </div>

    <!-- Таблица масел -->
    <div class="oils-table-wrapper">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Наименование</th>
                    <th>Артикул</th>
                    <th>Остаток</th>
                    <th>Закуп</th>
                    <th>Цена</th>
                    <th>Поставщик</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($oils)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:40px;">Масла не добавлены. <a href="#" class="akpp-open-modal" data-target="#akpp-oil-modal">Добавить первое</a></td></tr>
                <?php else: ?>
                    <?php foreach ($oils as $oil): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($oil->name); ?></strong>
                                <?php if (!empty($oil->description)): ?>
                                    <br><small style="color:#718096;"><?php echo esc_html(mb_substr($oil->description, 0, 60)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html($oil->sku ?: '—'); ?></code></td>
                            <td>
                                <?php 
                                $qty = floatval($oil->quantity);
                                $color = $qty === 0 ? '#fc8181' : ($qty < 5 ? '#f6ad55' : '#00ff88');
                                ?>
                                <strong style="color:<?php echo $color; ?>;"><?php echo $qty; ?></strong> <?php echo esc_html($oil->unit); ?>
                            </td>
                            <td><?php echo number_format(floatval($oil->purchase_price), 0, ',', ' '); ?> ₽</td>
                            <td><strong style="color:#00ff88;"><?php echo number_format(floatval($oil->price), 0, ',', ' '); ?> ₽</strong></td>
                            <td><?php echo esc_html($oil->supplier ?: '—'); ?></td>
                            <td>
                                <button class="button button-small btn-edit-part" 
                                    data-id="<?php echo $oil->id; ?>"
                                    data-name="<?php echo esc_attr($oil->name); ?>"
                                    data-sku="<?php echo esc_attr($oil->sku); ?>"
                                    data-category="oils"
                                    data-description="<?php echo esc_attr($oil->description); ?>"
                                    data-quantity="<?php echo $oil->quantity; ?>"
                                    data-unit="<?php echo esc_attr($oil->unit); ?>"
                                    data-purchase-price="<?php echo $oil->purchase_price; ?>"
                                    data-markup="<?php echo $oil->markup_percent; ?>"
                                    data-price="<?php echo $oil->price; ?>"
                                    data-supplier="<?php echo esc_attr($oil->supplier); ?>"
                                    data-location="<?php echo esc_attr($oil->location); ?>"
                                    style="background:#00ff88;border-color:#00ff88;color:#1a1f2e;">✏️</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Модалка (переиспользуем из склада) -->
<div id="akpp-oil-modal" class="akpp-modal">
    <div class="akpp-modal-content">
        <span class="akpp-modal-close">&times;</span>
        <h2 id="oil-modal-title">➕ Новое масло</h2>
        
        <form class="akpp-ajax-form" data-action="akpp_save_part" id="oil-form">
            <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
            <input type="hidden" name="id" id="oil-id">
            <input type="hidden" name="category" value="oils">
            
            <div class="part-form-grid">
                <div class="form-group">
                    <label>Наименование *</label>
                    <input type="text" name="name" id="oil-name" required placeholder="Toyota ATF WS">
                </div>
                <div class="form-group">
                    <label>Артикул</label>
                    <input type="text" name="sku" id="oil-sku" placeholder="08885-01234">
                </div>
            </div>
            
            <div class="form-group">
                <label>Описание / спецификация</label>
                <textarea name="description" id="oil-description" rows="2" placeholder="ATF, 75W-90, Dexron VI..."></textarea>
            </div>
            
            <div class="part-form-grid-3">
                <div class="form-group">
                    <label>Остаток (л)</label>
                    <input type="number" name="quantity" id="oil-quantity" min="0" step="0.1" value="0">
                    <input type="hidden" name="unit" value="л">
                </div>
                <div class="form-group">
                    <label>Закуп (₽/л)</label>
                    <input type="number" name="purchase_price" id="oil-purchase-price" min="0" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>Наценка (%)</label>
                    <input type="number" name="markup_percent" id="oil-markup" min="0" max="1000" step="0.01" value="30">
                </div>
            </div>
            
            <div class="part-form-grid">
                <div class="form-group">
                    <label>Цена (₽/л)</label>
                    <input type="number" name="price" id="oil-price" min="0" step="0.01" value="0" style="background:#1a3a2e;border-color:#00ff88;color:#00ff88;font-weight:700;">
                </div>
                <div class="form-group">
                    <label>Поставщик</label>
                    <input type="text" name="supplier" id="oil-supplier" placeholder="Exist, Emex">
                </div>
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
    // Авто-расчёт цены
    $('#oil-purchase-price, #oil-markup').on('input', function() {
        var p = parseFloat($('#oil-purchase-price').val()) || 0;
        var m = parseFloat($('#oil-markup').val()) || 0;
        $('#oil-price').val((p * (1 + m / 100)).toFixed(2));
    });
    
    $('.akpp-open-modal').on('click', function() {
        var target = $(this).data('target');
        $('#oil-modal-title').text('➕ Новое масло');
        $('#oil-form')[0].reset();
        $('#oil-id').val('');
        $(target).addClass('active');
    });
    
    $('.akpp-modal-close, .akpp-modal').on('click', function(e) {
        if (e.target === this || $(this).hasClass('akpp-modal-close')) {
            $('.akpp-modal').removeClass('active');
        }
    });
    
    $(document).on('click', '.btn-edit-part', function(e) {
        e.preventDefault();
        var d = $(this).data();
        $('#oil-modal-title').text('✏️ Редактировать');
        $('#oil-id').val(d.id);
        $('#oil-name').val(d.name);
        $('#oil-sku').val(d.sku);
        $('#oil-description').val(d.description);
        $('#oil-quantity').val(d.quantity);
        $('#oil-purchase-price').val(d.purchasePrice);
        $('#oil-markup').val(d.markup);
        $('#oil-price').val(d.price);
        $('#oil-supplier').val(d.supplier);
        $('#akpp-oil-modal').addClass('active');
    });
    
    $('#oil-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        $btn.prop('disabled', true).html('⏳');
        
        var formData = $form.serializeArray();
        formData.push({name: 'action', value: $form.data('action')});
        
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    location.reload();
                } else {
                    alert(res.data.message || 'Ошибка');
                    $btn.prop('disabled', false).html('💾 Сохранить');
                }
            }
        });
    });
});
</script>