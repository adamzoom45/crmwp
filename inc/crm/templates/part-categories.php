<?php
/**
 * Страница управления категориями склада
 */
if (!defined('ABSPATH')) exit;
?>

<div class="wrap akpp-crm-wrap">
    <div class="categories-page-header">
        <h1>📂 Категории склада</h1>
        <button type="button" class="button button-primary akpp-open-modal" data-target="#category-modal">
            + Добавить категорию
        </button>
    </div>

    <div class="categories-stats-grid">
        <div class="categories-stat-card">
            <div class="categories-stat-value" id="total-categories">0</div>
            <div class="categories-stat-label">Всего категорий</div>
        </div>
        <div class="categories-stat-card">
            <div class="categories-stat-value" id="total-parts">0</div>
            <div class="categories-stat-label">Запчастей в категориях</div>
        </div>
    </div>

    <div class="categories-table-wrapper">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th>Название</th>
                    <th>Ярлык (slug)</th>
                    <th style="width:100px;">Запчастей</th>
                    <th style="width:100px;">Сортировка</th>
                    <th style="width:150px;">Действия</th>
                </tr>
            </thead>
            <tbody id="categories-list">
                <tr><td colspan="6" style="text-align:center;padding:40px;">Загрузка...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Модальное окно -->
<div id="category-modal" class="akpp-modal">
    <div class="akpp-modal-content" style="max-width:600px;">
        <span class="akpp-modal-close">&times;</span>
        <h2 id="modal-title">➕ Новая категория</h2>
        
        <form id="category-form">
            <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
            <input type="hidden" name="id" id="category-id">
            
            <div class="form-group">
                <label>Название категории *</label>
                <input type="text" name="name" id="category-name" required placeholder="Например: Фильтры">
            </div>
            
            <div class="form-group">
                <label>Ярлык (slug)</label>
                <input type="text" name="slug" id="category-slug" placeholder="filters">
                <small style="color:#718096;">Латиницей, без пробелов</small>
            </div>
            
            <div class="form-group">
                <label>Описание</label>
                <textarea name="description" id="category-description" rows="3" placeholder="Описание категории..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Порядок сортировки</label>
                <input type="number" name="sort_order" id="category-sort" min="0" value="0">
            </div>
            
            <div class="form-actions" style="text-align:right;margin-top:20px;">
                <button type="button" class="button button-secondary akpp-modal-close">Отмена</button>
                <button type="submit" class="button button-primary">💾 Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var isSubmitting = false;
    
    // Загрузка категорий
    function loadCategories() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_get_part_categories',
                nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    displayCategories(res.data.categories);
                }
            }
        });
    }
    
    function displayCategories(categories) {
        var html = '';
        var totalParts = 0;
        
        if (categories.length === 0) {
            html = '<tr><td colspan="6" style="text-align:center;padding:40px;">Нет категорий. Добавьте первую!</td></tr>';
        } else {
            categories.forEach(function(cat) {
                totalParts += parseInt(cat.parts_count || 0);
                html += '<tr data-id="' + cat.id + '">' +
                    '<td>' + cat.id + '</td>' +
                    '<td><strong>' + cat.name + '</strong>' + 
                    (cat.description ? '<br><small style="color:#718096;">' + cat.description.substring(0, 60) + '</small>' : '') +
                    '</td>' +
                    '<td><code>' + cat.slug + '</code></td>' +
                    '<td><span class="parts-count">' + (cat.parts_count || 0) + '</span></td>' +
                    '<td>' + cat.sort_order + '</td>' +
                    '<td>' +
                    '<button class="button button-small btn-edit-category" data-id="' + cat.id + '">✏️</button> ' +
                    '<button class="button button-small btn-delete-category" data-id="' + cat.id + '" data-name="' + cat.name + '">🗑️</button>' +
                    '</td></tr>';
            });
        }
        
        $('#categories-list').html(html);
        $('#total-categories').text(categories.length);
        $('#total-parts').text(totalParts);
    }
    
    // Открытие модалки
    $('.akpp-open-modal').on('click', function() {
        $('#modal-title').text('➕ Новая категория');
        $('#category-form')[0].reset();
        $('#category-id').val('');
        $('#category-modal').addClass('active');
    });
    
    // Закрытие модалки
    $('.akpp-modal-close, .akpp-modal').on('click', function(e) {
        if (e.target === this || $(this).hasClass('akpp-modal-close')) {
            $('.akpp-modal').removeClass('active');
        }
    });
    
    // Редактирование
    $(document).on('click', '.btn-edit-category', function() {
        var row = $(this).closest('tr');
        var id = $(this).data('id');
        
        // Здесь можно добавить AJAX для получения полных данных
        $('#modal-title').text('✏️ Редактировать категорию');
        $('#category-id').val(id);
        $('#category-name').val(row.find('td:eq(1) strong').text());
        $('#category-slug').val(row.find('td:eq(2) code').text());
        $('#category-sort').val(row.find('td:eq(4)').text());
        
        $('#category-modal').addClass('active');
    });
    
    // Удаление
    $(document).on('click', '.btn-delete-category', function() {
        if (!confirm('Удалить категорию "' + $(this).data('name') + '"?')) return;
        
        var id = $(this).data('id');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_delete_part_category',
                id: id,
                nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showNotice('✅ ' + res.data.message, 'success');
                    loadCategories();
                } else {
                    showNotice('❌ ' + res.data.message, 'error');
                }
            }
        });
    });
    
    // Сохранение
    $('#category-form').on('submit', function(e) {
        e.preventDefault();
        if (isSubmitting) return false;
        
        isSubmitting = true;
        var $btn = $(this).find('button[type="submit"]');
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('⏳ Сохранение...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $(this).serialize() + '&action=akpp_save_part_category',
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showNotice('✅ ' + res.data.message, 'success');
                    $('#category-modal').removeClass('active');
                    loadCategories();
                } else {
                    showNotice('❌ ' + res.data.message, 'error');
                }
                $btn.prop('disabled', false).html(originalText);
                isSubmitting = false;
            },
            error: function() {
                showNotice('❌ Ошибка соединения', 'error');
                $btn.prop('disabled', false).html(originalText);
                isSubmitting = false;
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
    
    // Загрузка при старте
    loadCategories();
});
</script>

<style>
.categories-page-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}
.categories-stats-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
    gap:15px;
    margin-bottom:25px;
}
.categories-stat-card {
    background:#1a1f2e;
    border:1px solid #2d3748;
    border-radius:12px;
    padding:20px;
    text-align:center;
}
.categories-stat-value {
    font-size:32px;
    font-weight:700;
    color:#00ff88;
    margin-bottom:8px;
}
.categories-stat-label {
    color:#a0aec0;
    font-size:13px;
    text-transform:uppercase;
}
.categories-table-wrapper {
    background:#1a1f2e;
    border:1px solid #2d3748;
    border-radius:12px;
    padding:20px;
}
.parts-count {
    display:inline-block;
    padding:4px 12px;
    background:#2d3748;
    border-radius:12px;
    font-weight:600;
    color:#00ff88;
}
</style>