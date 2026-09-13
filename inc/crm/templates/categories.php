<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$T = $wpdb->prefix . 'akpp_categories';
$page = sanitize_text_field($_GET['page'] ?? '');
$is_shop = ($page === 'akpp-crm-shop-categories');
$section = $is_shop ? '🛒 Магазин' : '🔧 Автосервис';
$default_scope = $is_shop ? 'shop' : 'service';
$cats = $wpdb->get_results("SELECT * FROM $T ORDER BY parent_id, sort_order, id", ARRAY_A);
$scope_labels = ['service'=>'🔧 Автосервис','shop'=>'🛒 Магазин','both'=>'🔗 Оба'];
$scope_colors = ['service'=>'#00ff88','shop'=>'#63b3ed','both'=>'#b794f4'];

function akpp_render_cat_tree($cats, $parent_id, $level, &$scope_labels, &$scope_colors) {
    foreach ($cats as $c) {
        if ((int)$c['parent_id'] !== $parent_id) continue;
        $pad = $level * 24;
        $sc = $scope_colors[$c['scope']] ?? '#a0aec0';
        echo '<div class="akpp-cat-row" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-bottom:1px solid #2d3748;background:' . ($level % 2 ? '#1a1f2e' : '#161b26') . ';">';
        echo '<span style="margin-left:' . $pad . 'px;color:#718096;">' . ($level > 0 ? '↳ ' : '') . '</span>';
        echo '<span style="font-size:16px;">' . esc_html($c['icon'] ?: '📁') . '</span>';
        echo '<strong style="color:#e2e8f0;flex:1;">' . esc_html($c['name']) . '</strong>';
        echo '<span style="font-size:11px;padding:2px 8px;border-radius:10px;background:' . $sc . '22;color:' . $sc . ';">' . ($scope_labels[$c['scope']] ?? $c['scope']) . '</span>';
        echo '<span style="font-size:11px;color:#718096;">#' . intval($c['sort_order']) . '</span>';
        echo '<span style="font-size:11px;color:' . ($c['is_active'] ? '#00ff88' : '#fc8181') . ';">' . ($c['is_active'] ? 'активна' : 'скрыта') . '</span>';
        echo '<button type="button" class="button button-small akpp-edit-cat" data-cat=\'' . esc_attr(json_encode($c)) . '\' style="color:#63b3ed;">✏️</button>';
        echo '<button type="button" class="button button-small akpp-del-cat" data-id="' . intval($c['id']) . '" data-name="' . esc_attr($c['name']) . '" style="color:#fc8181;">🗑️</button>';
        echo '</div>';
        akpp_render_cat_tree($cats, (int)$c['id'], $level + 1, $scope_labels, $scope_colors);
    }
}
?>
<div class="wrap akpp-crm-wrap">
    <h1 style="color:#00ff88;border-left:4px solid #00ff88;padding-left:15px;">📁 Категории — <?php echo $section; ?></h1>
    <p style="color:#a0aec0;">Единый иерархический справочник категорий (общий для Автосервиса и Магазина). Scope определяет, где видна категория.</p>

    <div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start;">
        <!-- ДЕРЕВО -->
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:16px;">
            <h2 style="color:#00ff88;margin:0 0 12px;">Дерево категорий (<?php echo count($cats); ?>)</h2>
            <?php if (empty($cats)): ?>
                <p style="color:#a0aec0;text-align:center;padding:30px;">Категорий пока нет — добавьте первую справа.</p>
            <?php else: ?>
                <?php akpp_render_cat_tree($cats, 0, 0, $scope_labels, $scope_colors); ?>
            <?php endif; ?>
        </div>

        <!-- ФОРМА -->
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:18px;">
            <h2 style="color:#00ff88;margin:0 0 14px;" id="akpp-cat-form-title">➕ Новая категория</h2>
            <form id="akpp-cat-form" style="display:flex;flex-direction:column;gap:10px;">
                <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
                <input type="hidden" name="id" id="akpp-cat-id" value="0">
                <div><label style="color:#a0aec0;font-size:12px;">Название *</label>
                    <input type="text" name="name" id="akpp-cat-name" required style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;"></div>
                <div><label style="color:#a0aec0;font-size:12px;">Родитель (для подкатегории)</label>
                    <select name="parent_id" id="akpp-cat-parent" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;">
                        <option value="0">— корневая категория —</option>
                        <?php foreach ($cats as $c): ?>
                            <option value="<?php echo intval($c['id']); ?>"><?php echo esc_html($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div><label style="color:#a0aec0;font-size:12px;">Scope (где видна)</label>
                    <select name="scope" id="akpp-cat-scope" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;">
                        <?php foreach ($scope_labels as $k=>$v): ?>
                            <option value="<?php echo $k; ?>" <?php selected($default_scope, $k); ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div style="display:flex;gap:10px;">
                    <div style="flex:1;"><label style="color:#a0aec0;font-size:12px;">Иконка (эмодзи)</label>
                        <input type="text" name="icon" id="akpp-cat-icon" placeholder="📁" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;"></div>
                    <div style="flex:1;"><label style="color:#a0aec0;font-size:12px;">Порядок</label>
                        <input type="number" name="sort_order" id="akpp-cat-sort" value="0" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;"></div>
                </div>
                <div><label style="color:#a0aec0;font-size:12px;">Описание</label>
                    <textarea name="description" id="akpp-cat-desc" rows="2" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;"></textarea></div>
                <div><label style="color:#a0aec0;font-size:12px;display:flex;align-items:center;gap:6px;">
                    <input type="checkbox" name="is_active" id="akpp-cat-active" value="1" checked> Активна (видна)</label></div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" class="button button-primary" style="background:#00ff88;border-color:#00ff88;color:#0a0f1c;font-weight:600;flex:1;">💾 Сохранить</button>
                    <button type="button" id="akpp-cat-reset" class="button" style="color:#a0aec0;">Сброс</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
jQuery(document).ready(function($){
    var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var nonce = '<?php echo wp_create_nonce('akpp45_nonce'); ?>';
    function resetForm(){
        $('#akpp-cat-id').val(0); $('#akpp-cat-name').val(''); $('#akpp-cat-parent').val(0);
        $('#akpp-cat-scope').val('<?php echo $default_scope; ?>'); $('#akpp-cat-icon').val('');
        $('#akpp-cat-sort').val(0); $('#akpp-cat-desc').val(''); $('#akpp-cat-active').prop('checked', true);
        $('#akpp-cat-form-title').text('➕ Новая категория');
    }
    $('#akpp-cat-reset').on('click', resetForm);
    $(document).on('click', '.akpp-edit-cat', function(){
        var c = $(this).data('cat');
        $('#akpp-cat-id').val(c.id); $('#akpp-cat-name').val(c.name);
        $('#akpp-cat-parent').val(c.parent_id); $('#akpp-cat-scope').val(c.scope);
        $('#akpp-cat-icon').val(c.icon); $('#akpp-cat-sort').val(c.sort_order);
        $('#akpp-cat-desc').val(c.description); $('#akpp-cat-active').prop('checked', c.is_active == 1);
        $('#akpp-cat-form-title').text('✏️ Редактировать: ' + c.name);
        window.scrollTo({top:0, behavior:'smooth'});
    });
    $(document).on('click', '.akpp-del-cat', function(){
        var id = $(this).data('id'), name = $(this).data('name');
        if (!confirm('Удалить категорию «' + name + '»?')) return;
        var $b = $(this).prop('disabled', true);
        $.post(ajaxUrl, {action:'akpp_delete_category', id:id, nonce:nonce}, function(r){
            if (r.success) location.reload(); else { alert(r.data.message || 'Ошибка'); $b.prop('disabled', false); }
        });
    });
    $('#akpp-cat-form').on('submit', function(e){
        e.preventDefault();
        var $b = $(this).find('button[type=submit]').prop('disabled', true).text('Сохранение...');
        var data = $(this).serialize() + '&action=akpp_save_category';
        data += '&is_active=' + ($('#akpp-cat-active').is(':checked') ? 1 : 0);
        $.post(ajaxUrl, data, function(r){
            if (r.success) location.reload(); else { alert(r.data.message || 'Ошибка'); $b.prop('disabled', false).text('💾 Сохранить'); }
        });
    });
});
</script>
