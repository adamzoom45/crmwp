<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$directions = [
    'akpp'=>'⚙️ АКПП','engine'=>'🔩 ДВС','suspension'=>'🛞 Ходовая','body'=>'🚗 Кузовные','electric'=>'⚡ Электрика','interior'=>'💺 Салон',
];
$dir_filter = sanitize_text_field($_GET['direction'] ?? 'all');
$where = "1=1"; $params = [];
if ($dir_filter !== 'all' && isset($directions[$dir_filter])) { $where .= " AND s.direction = %s"; $params[] = $dir_filter; }
$services = $wpdb->get_results($wpdb->prepare(
    "SELECT s.*, c.name as category_name FROM {$wpdb->prefix}akpp_services s
     LEFT JOIN {$wpdb->prefix}akpp_categories c ON s.category_id = c.id
     WHERE $where ORDER BY s.direction, s.name", $params), ARRAY_A);
$cats = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}akpp_categories WHERE is_active = 1 AND scope IN ('service','both') ORDER BY sort_order, name", ARRAY_A);
$parts = $wpdb->get_results("SELECT id, name, sku FROM {$wpdb->prefix}akpp_parts ORDER BY name LIMIT 500", ARRAY_A);
?>
<div class="wrap akpp-crm-wrap">
    <h1 style="color:#00ff88;border-left:4px solid #00ff88;padding-left:15px;">📋 Услуги автосервиса</h1>
    <p style="color:#a0aec0;">Справочник услуг по направлениям. Привяжите к услуге запчасти со склада — при добавлении услуги в сделку они подтянутся автоматически (8c-3b).</p>

    <div style="margin:16px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <span style="color:#a0aec0;">Направление:</span>
        <select id="akpp-dir-filter" style="padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;">
            <option value="all" <?php selected($dir_filter,'all'); ?>>Все направления</option>
            <?php foreach ($directions as $k=>$v): ?>
                <option value="<?php echo $k; ?>" <?php selected($dir_filter,$k); ?>><?php echo $v; ?></option>
            <?php endforeach; ?>
        </select>
        <span style="color:#718096;font-size:13px;">найдено: <?php echo count($services); ?></span>
    </div>

    <div style="display:grid;grid-template-columns:1fr 400px;gap:20px;align-items:start;">
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:16px;">
            <?php if (empty($services)): ?>
                <p style="color:#a0aec0;text-align:center;padding:30px;">Услуг пока нет — добавьте первую справа.</p>
            <?php else: ?>
                <table style="width:100%;border-collapse:collapse;">
                    <thead><tr style="background:#2d3748;">
                        <th style="color:#00ff88;padding:10px;text-align:left;">Услуга</th>
                        <th style="color:#00ff88;padding:10px;text-align:left;">Направление</th>
                        <th style="color:#00ff88;padding:10px;text-align:left;">Категория</th>
                        <th style="color:#00ff88;padding:10px;text-align:right;">Норма-ч</th>
                        <th style="color:#00ff88;padding:10px;text-align:right;">Цена</th>
                        <th style="color:#00ff88;padding:10px;text-align:center;">⚙</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($services as $s): ?>
                        <tr style="border-bottom:1px solid #2d3748;">
                            <td style="color:#e2e8f0;padding:10px;"><strong><?php echo esc_html($s['name']); ?></strong>
                                <?php if (!$s['is_active']): ?><span style="color:#fc8181;font-size:11px;"> (скрыта)</span><?php endif; ?></td>
                            <td style="color:#a0aec0;padding:10px;"><?php echo $directions[$s['direction']] ?? $s['direction']; ?></td>
                            <td style="color:#a0aec0;padding:10px;"><?php echo esc_html($s['category_name'] ?: '—'); ?></td>
                            <td style="color:#9ae6b4;padding:10px;text-align:right;"><?php echo rtrim(rtrim(number_format($s['norm_hours'],2,'.',''),'0'),'.'); ?></td>
                            <td style="color:#f6ad55;padding:10px;text-align:right;"><?php echo number_format($s['price'],0,',',' '); ?> ₽</td>
                            <td style="padding:10px;text-align:center;white-space:nowrap;">
                                <button type="button" class="button button-small akpp-edit-svc" data-svc='<?php echo esc_attr(json_encode($s)); ?>' style="color:#63b3ed;">✏️</button>
                                <button type="button" class="button button-small akpp-del-svc" data-id="<?php echo intval($s['id']); ?>" data-name="<?php echo esc_attr($s['name']); ?>" style="color:#fc8181;">🗑️</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div>
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:18px;">
            <h2 style="color:#00ff88;margin:0 0 14px;" id="akpp-svc-form-title">➕ Новая услуга</h2>
            <form id="akpp-svc-form" style="display:flex;flex-direction:column;gap:10px;">
                <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
                <input type="hidden" name="id" id="akpp-svc-id" value="0">
                <div><label style="color:#a0aec0;font-size:12px;">Название *</label>
                    <input type="text" name="name" id="akpp-svc-name" required style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;"></div>
                <div><label style="color:#a0aec0;font-size:12px;">Направление</label>
                    <select name="direction" id="akpp-svc-dir" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;">
                        <?php foreach ($directions as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?>
                    </select></div>
                <div><label style="color:#a0aec0;font-size:12px;">Категория</label>
                    <select name="category_id" id="akpp-svc-cat" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;">
                        <option value="0">— без категории —</option>
                        <?php foreach ($cats as $c): ?><option value="<?php echo intval($c['id']); ?>"><?php echo esc_html($c['name']); ?></option><?php endforeach; ?>
                    </select></div>
                <div style="display:flex;gap:10px;">
                    <div style="flex:1;"><label style="color:#a0aec0;font-size:12px;">Норма-часы</label>
                        <input type="number" step="0.1" min="0" name="norm_hours" id="akpp-svc-hours" value="0" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;"></div>
                    <div style="flex:1;"><label style="color:#a0aec0;font-size:12px;">Цена ₽</label>
                        <input type="number" step="0.01" min="0" name="price" id="akpp-svc-price" value="0" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;"></div>
                </div>
                <div><label style="color:#a0aec0;font-size:12px;">Описание</label>
                    <textarea name="description" id="akpp-svc-desc" rows="2" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;"></textarea></div>
                <div><label style="color:#a0aec0;font-size:12px;display:flex;align-items:center;gap:6px;">
                    <input type="checkbox" name="is_active" id="akpp-svc-active" value="1" checked> Активна</label></div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" class="button button-primary" style="background:#00ff88;border-color:#00ff88;color:#0a0f1c;font-weight:600;flex:1;">💾 Сохранить</button>
                    <button type="button" id="akpp-svc-reset" class="button" style="color:#a0aec0;">Сброс</button>
                </div>
            </form>
        </div>

        <!-- БЛОК ЗАПЧАСТЕЙ УСЛУГИ (8c-3a) -->
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:18px;margin-top:16px;">
            <h3 style="color:#00ff88;margin:0 0 12px;">🔩 Запчасти для услуги</h3>
            <p id="akpp-sp-hint" style="color:#718096;font-size:12px;margin:0 0 10px;">Сначала сохраните услугу, чтобы привязать запчасти со склада.</p>
            <div id="akpp-sp-work" style="display:none;">
                <div id="akpp-sp-list" style="margin-bottom:12px;"></div>
                <div style="display:flex;gap:8px;align-items:end;">
                    <select id="akpp-sp-part" style="flex:1;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;">
                        <option value="">-- запчасть --</option>
                        <?php foreach ($parts as $p): ?>
                            <option value="<?php echo intval($p['id']); ?>"><?php echo esc_html($p['name']); ?><?php echo $p['sku'] ? ' ('.esc_html($p['sku']).')' : ''; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" id="akpp-sp-qty" step="0.1" min="0.1" value="1" style="width:70px;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;">
                    <button type="button" id="akpp-sp-add" class="button button-primary" style="background:#00ff88;border-color:#00ff88;color:#0a0f1c;">➕</button>
                </div>
                <?php if (empty($parts)): ?><p style="color:#fc8181;font-size:11px;margin:8px 0 0;">На складе нет запчастей — добавьте в «📦 Склад».</p><?php endif; ?>
            </div>
        </div>
        </div>
    </div>
</div>
<script>
jQuery(document).ready(function($){
    var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var nonce = '<?php echo wp_create_nonce('akpp45_nonce'); ?>';
    $('#akpp-dir-filter').on('change', function(){
        var url = new URL(window.location.href);
        if ($(this).val() === 'all') url.searchParams.delete('direction'); else url.searchParams.set('direction', $(this).val());
        window.location.href = url.toString();
    });
    function toggleParts(on){
        if (on){ $('#akpp-sp-hint').hide(); $('#akpp-sp-work').show(); }
        else { $('#akpp-sp-hint').show(); $('#akpp-sp-work').hide(); $('#akpp-sp-list').empty(); }
    }
    function loadParts(sid){
        $.post(ajaxUrl, {action:'akpp_get_service_parts', service_id:sid, nonce:nonce}, function(r){
            var rows = (r.success && r.data.parts) ? r.data.parts : [];
            var html = '';
            if (rows.length === 0){ html = '<p style="color:#718096;font-size:12px;margin:0 0 10px;">Запчасти не привязаны.</p>'; }
            else {
                rows.forEach(function(p){
                    html += '<div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #2d3748;">'
                        + '<span style="flex:1;color:#e2e8f0;font-size:13px;">' + (p.name||'запчасть #'+p.part_id) + (p.sku?' <span style="color:#718096;">('+p.sku+')</span>':'') + '</span>'
                        + '<span style="color:#9ae6b4;font-size:12px;">×' + p.qty + '</span>'
                        + '<button type="button" class="button button-small akpp-sp-del" data-id="'+p.id+'" style="color:#fc8181;">🗑️</button></div>';
                });
            }
            $('#akpp-sp-list').html(html);
        });
    }
    function resetForm(){
        $('#akpp-svc-id').val(0); $('#akpp-svc-name').val(''); $('#akpp-svc-dir').val('akpp');
        $('#akpp-svc-cat').val(0); $('#akpp-svc-hours').val(0); $('#akpp-svc-price').val(0);
        $('#akpp-svc-desc').val(''); $('#akpp-svc-active').prop('checked', true);
        $('#akpp-svc-form-title').text('➕ Новая услуга');
        toggleParts(false);
    }
    $('#akpp-svc-reset').on('click', resetForm);
    $(document).on('click', '.akpp-edit-svc', function(){
        var s = $(this).data('svc');
        $('#akpp-svc-id').val(s.id); $('#akpp-svc-name').val(s.name); $('#akpp-svc-dir').val(s.direction);
        $('#akpp-svc-cat').val(s.category_id); $('#akpp-svc-hours').val(s.norm_hours); $('#akpp-svc-price').val(s.price);
        $('#akpp-svc-desc').val(s.description); $('#akpp-svc-active').prop('checked', s.is_active == 1);
        $('#akpp-svc-form-title').text('✏️ Редактировать: ' + s.name);
        toggleParts(true); loadParts(s.id);
        window.scrollTo({top:0, behavior:'smooth'});
    });
    $(document).on('click', '.akpp-del-svc', function(){
        var id = $(this).data('id'), name = $(this).data('name');
        if (!confirm('Удалить услугу «' + name + '»?')) return;
        var $b = $(this).prop('disabled', true);
        $.post(ajaxUrl, {action:'akpp_delete_service', id:id, nonce:nonce}, function(r){
            if (r.success) location.reload(); else { alert(r.data.message || 'Ошибка'); $b.prop('disabled', false); }
        });
    });
    $('#akpp-svc-form').on('submit', function(e){
        e.preventDefault();
        var $b = $(this).find('button[type=submit]').prop('disabled', true).text('Сохранение...');
        var data = $(this).serialize() + '&action=akpp_save_service&is_active=' + ($('#akpp-svc-active').is(':checked') ? 1 : 0);
        $.post(ajaxUrl, data, function(r){
            if (r.success) location.reload(); else { alert(r.data.message || 'Ошибка'); $b.prop('disabled', false).text('💾 Сохранить'); }
        });
    });
    $('#akpp-sp-add').on('click', function(){
        var sid = parseInt($('#akpp-svc-id').val(),10), pid = parseInt($('#akpp-sp-part').val(),10), qty = parseFloat($('#akpp-sp-qty').val()) || 1;
        if (!sid){ alert('Сначала сохраните услугу'); return; }
        if (!pid){ alert('Выберите запчасть'); return; }
        var $b = $(this).prop('disabled', true);
        $.post(ajaxUrl, {action:'akpp_add_service_part', service_id:sid, part_id:pid, qty:qty, nonce:nonce}, function(r){
            $b.prop('disabled', false);
            if (r.success) loadParts(sid); else alert(r.data.message || 'Ошибка');
        });
    });
    $(document).on('click', '.akpp-sp-del', function(){
        var $b = $(this).prop('disabled', true), id = $b.data('id'), sid = parseInt($('#akpp-svc-id').val(),10);
        $.post(ajaxUrl, {action:'akpp_remove_service_part', id:id, nonce:nonce}, function(r){
            if (r.success) loadParts(sid); else { alert(r.data.message || 'Ошибка'); $b.prop('disabled', false); }
        });
    });
});
</script>
