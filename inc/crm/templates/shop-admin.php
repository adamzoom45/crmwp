<?php
/**
 * Админка магазина АКПП45
 */
if (!defined('ABSPATH')) exit;

$shop = AKPP_Shop::get_instance();
$products = $shop->get_products(['per_page' => 100]);
$categories = $shop->get_categories();
?>

<div class="wrap akpp-crm-wrap">
    <div class="shop-page-header">
        <h1>🛒 Магазин АКПП45</h1>
        <button type="button" class="button button-primary akpp-open-modal" data-target="#product-modal">
            + Добавить товар
        </button>
    </div>

    <!-- Статистика -->
    <div class="shop-stats-grid">
        <div class="shop-stat-card">
            <div class="shop-stat-value"><?php echo count($products); ?></div>
            <div class="shop-stat-label">ТОВАРОВ</div>
        </div>
        <div class="shop-stat-card">
            <div class="shop-stat-value"><?php echo count($categories); ?></div>
            <div class="shop-stat-label">КАТЕГОРИЙ</div>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="shop-filters">
        <select id="shop-category-filter">
            <option value="">Все категории</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo esc_attr($cat->slug); ?>">
                    <?php echo esc_html($cat->icon . ' ' . $cat->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="text" id="shop-search" placeholder="Поиск по названию или артикулу...">
        <button id="shop-filter-btn" class="button">Фильтровать</button>
    </div>

    <!-- Таблица товаров -->
    <div class="shop-table-wrapper">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th>Артикул</th>
                    <th>Название</th>
                    <th>Категория</th>
                    <th>Состояние</th>
                    <th>Цена</th>
                    <th>Остаток</th>
                    <th>Статус</th>
                    <th style="width:150px;">Действия</th>
                </tr>
            </thead>
            <tbody id="shop-products-list">
                <?php foreach ($products as $product): ?>
                    <tr data-id="<?php echo $product->id; ?>">
                        <td><?php echo $product->id; ?></td>
                        <td><code><?php echo esc_html($product->sku); ?></code></td>
                        <td>
                            <strong><?php echo esc_html($product->name); ?></strong>
                            <?php if ($product->is_featured): ?>
                                <span class="featured-badge">⭐</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($product->category); ?></td>
                        <td>
                            <span class="condition-badge condition-<?php echo $product->condition_type; ?>">
                                <?php echo $product->condition_type === 'new' ? 'Новый' : 'Б/У'; ?>
                            </span>
                        </td>
                        <td>
                            <strong><?php echo number_format($product->price, 0, ',', ' '); ?> ₽</strong>
                            <?php if ($product->old_price): ?>
                                <br><small><s><?php echo number_format($product->old_price, 0, ',', ' '); ?> ₽</s></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="stock-badge <?php echo $product->stock <= 0 ? 'out-of-stock' : ($product->stock < 5 ? 'low-stock' : 'in-stock'); ?>">
                                <?php echo $product->stock; ?> шт
                            </span>
                        </td>
                        <td><?php echo $product->is_active ? '✅' : '❌'; ?></td>
                        <td>
                            <button class="button button-small btn-edit-product" data-id="<?php echo $product->id; ?>">✏️</button>
                            <button class="button button-small btn-toggle-product" data-id="<?php echo $product->id; ?>">
                                <?php echo $product->is_active ? '🔴' : ''; ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Модальное окно товара -->
<div id="product-modal" class="akpp-modal">
    <div class="akpp-modal-content">
        <span class="akpp-modal-close">&times;</span>
        <h2 id="product-modal-title">➕ Новый товар</h2>
        
        <form id="product-form">
            <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
            <input type="hidden" name="id" id="product-id">
            
            <div class="product-form-grid">
                <div class="form-group">
                    <label>Артикул *</label>
                    <input type="text" name="sku" id="product-sku" required>
                </div>
                <div class="form-group">
                    <label>Категория *</label>
                    <select name="category" id="product-category" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat->slug); ?>">
                                <?php echo esc_html($cat->icon . ' ' . $cat->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Название *</label>
                <input type="text" name="name" id="product-name" required>
            </div>
            
            <div class="form-group">
                <label>Описание</label>
                <textarea name="description" id="product-description" rows="3"></textarea>
            </div>
            
            <div class="product-form-grid">
                <div class="form-group">
                    <label>Состояние</label>
                    <select name="condition_type" id="product-condition">
                        <option value="new">Новый</option>
                        <option value="used">Б/У</option>
                        <option value="refurbished">Восстановленный</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Качество</label>
                    <select name="quality_grade" id="product-quality">
                        <option value="A">A - Отличное</option>
                        <option value="B">B - Хорошее</option>
                        <option value="C">C - Удовлетворительное</option>
                        <option value="D">D - Низкое</option>
                    </select>
                </div>
            </div>
            
            <div class="product-form-grid">
                <div class="form-group">
                    <label>Цена *</label>
                    <input type="number" name="price" id="product-price" required min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label>Старая цена</label>
                    <input type="number" name="old_price" id="product-old-price" min="0" step="0.01">
                </div>
            </div>
            
            <div class="product-form-grid">
                <div class="form-group">
                    <label>Остаток</label>
                    <input type="number" name="stock" id="product-stock" min="0" value="0">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="product-active" value="1" checked>
                        Активен
                    </label>
                    <label>
                        <input type="checkbox" name="is_featured" id="product-featured" value="1">
                        Рекомендуемый
                    </label>
                </div>
            </div>
            
            <div class="product-form-actions">
                <button type="button" class="button button-secondary akpp-modal-close">Отмена</button>
                <button type="submit" class="button button-primary">💾 Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Открытие модалки
    $('.akpp-open-modal').on('click', function() {
        var target = $(this).data('target');
        $('#product-modal-title').text('➕ Новый товар');
        $('#product-form')[0].reset();
        $('#product-id').val('');
        $(target).addClass('active');
    });
    
    // Закрытие
    $('.akpp-modal-close, .akpp-modal').on('click', function(e) {
        if (e.target === this || $(this).hasClass('akpp-modal-close')) {
            $('.akpp-modal').removeClass('active');
        }
    });
    
    // Редактирование
    $(document).on('click', '.btn-edit-product', function() {
        var row = $(this).closest('tr');
        var id = $(this).data('id');
        
        $('#product-modal-title').text('✏️ Редактировать товар');
        $('#product-id').val(id);
        $('#product-sku').val(row.find('td:eq(1)').text().trim());
        $('#product-name').val(row.find('td:eq(2) strong').text().trim());
        $('#product-category').val(row.find('td:eq(3)').text().trim());
        $('#product-condition').val(row.find('td:eq(4)').text().trim() === 'Новый' ? 'new' : 'used');
        $('#product-price').val(row.find('td:eq(5) strong').text().replace(/\s/g, '').replace('₽', '').trim());
        $('#product-stock').val(row.find('td:eq(6)').text().replace('шт', '').trim());
        
        $('#product-modal').addClass('active');
    });
    
    // Сохранение
    $('#product-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serializeArray();
        formData.push({name: 'action', value: 'akpp_shop_save_product'});
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showNotice('✅ ' + res.data.message, 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    showNotice('❌ ' + res.data.message, 'error');
                }
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