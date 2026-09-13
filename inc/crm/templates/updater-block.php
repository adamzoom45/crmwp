<?php if (!defined('ABSPATH')) exit;
if (!class_exists('AKPP_Updater') || !AKPP_Updater::is_master()) return; // master-only
$wl  = AKPP_Updater::whitelist();
$cur = AKPP_Updater::current_version();
?>
<style>
.akpp-upd-form{ display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end; margin:6px 0 14px; }
.akpp-upd-form label{ display:flex; flex-direction:column; gap:6px; color:#a0aec0; font-size:12px; font-weight:600; }
.akpp-upd-form input[type=text]{ background:#0a0f1c; border:1px solid #2d3748; border-radius:9px; color:#fff; padding:10px 13px; font-family:'Courier New',monospace; font-size:12.5px; min-width:180px; transition:border-color .2s, box-shadow .2s; }
.akpp-upd-form input[type=text]:focus{ outline:none; border-color:#00ff88; box-shadow:0 0 0 3px rgba(0,255,136,.13); }
.akpp-upd-chk{ flex-direction:row !important; align-items:center !important; gap:8px !important; color:#cbd5e0 !important; }
.akpp-upd-tenants{ display:flex; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
.akpp-upd-tenant{ display:inline-flex; align-items:center; gap:8px; background:#0a0f1c; border:1px solid #2d3748; border-radius:9px; padding:8px 12px; color:#e2e8f0; font-size:12.5px; font-family:'Courier New',monospace; transition:border-color .2s, transform .2s; }
.akpp-upd-tenant:hover{ border-color:rgba(0,255,136,.4); transform:translateY(-1px); }
.akpp-upd-actions{ display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.akpp-upd-state{ color:#f6ad55; font-size:12.5px; font-weight:600; }
.akpp-upd-log{ margin-top:16px; background:#0a0f1c; border:1px solid #2d3748; border-radius:12px; padding:14px; font-family:'Courier New',monospace; font-size:12px; color:#9ca3af; max-height:360px; overflow:auto; white-space:pre-wrap; }
.akpp-upd-log .ok{ color:#00ff88; } .akpp-upd-log .fail{ color:#fc8181; } .akpp-upd-log .run{ color:#f6ad55; }
.akpp-upd-log table{ width:100%; border-collapse:collapse; margin-top:8px; }
.akpp-upd-log td,.akpp-upd-log th{ border:1px solid #2d3748; padding:5px 9px; text-align:left; font-size:11.5px; }
.akpp-upd-log th{ color:#a0aec0; text-transform:uppercase; font-size:10px; letter-spacing:.5px; }
.akpp-upd-one{ font-size:13px; }
@media (prefers-reduced-motion:reduce){ .akpp-upd-tenant{ transition:none; } }
</style>
<div class="akpp-lic-card akpp-upd-block">
    <h2>🚀 Обновления CRM на tenant'ы</h2>
    <p class="hint">Рассылка текущей версии темы <code>akpp-kurgan</code> с мастера на выбранных арендаторов. Для каждого автоматически: <b>бэкап файлов + БД</b> → копирование кода → сброс кэша → health‑check → <b>автооткат</b> при падении. Домены вне реестра лицензий обновить невозможно. Быстрое обновление одного — кнопка 🔄 в строке реестра выше.</p>
    <div class="akpp-upd-form">
        <label>Версия <input type="text" id="akpp-upd-version" value="<?php echo esc_attr($cur); ?>"></label>
        <label>Примечание / changelog <input type="text" id="akpp-upd-note" placeholder="что меняем в этом обновлении"></label>
        <label class="akpp-upd-chk"><input type="checkbox" id="akpp-upd-migrate"> Миграция БД</label>
        <label class="akpp-upd-chk"><input type="checkbox" id="akpp-upd-canary" checked> Канарейка (сначала один)</label>
    </div>
    <?php if (empty($wl)): ?>
        <p class="muted">Активных арендаторов с привязанным доменом в реестре нет — обновлять пока некого.</p>
    <?php else: ?>
        <div class="akpp-upd-tenants">
            <?php foreach ($wl as $d): ?>
                <label class="akpp-upd-tenant"><input type="checkbox" name="akpp_upd_tenant[]" value="<?php echo esc_attr($d); ?>" checked> <?php echo esc_html($d); ?></label>
            <?php endforeach; ?>
        </div>
        <div class="akpp-upd-actions">
            <button type="button" id="akpp-upd-run-all" class="btn">🚀 Обновить всех выбранных</button>
            <button type="button" id="akpp-upd-continue" class="btn-gray" style="display:none">▶ Продолжить на остальных</button>
            <span id="akpp-upd-state" class="akpp-upd-state"></span>
        </div>
    <?php endif; ?>
    <div id="akpp-upd-log" class="akpp-upd-log"><em>Пока пусто. Нажмите «🚀 Обновить всех выбранных» или 🔄 у арендатора в реестре.</em></div>
</div>
