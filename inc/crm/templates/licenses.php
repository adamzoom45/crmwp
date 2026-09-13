<?php if (!defined('ABSPATH')) exit;
global $wpdb; $t = $wpdb->prefix . 'akpp_licenses'; $now = current_time('timestamp');
if (isset($_GET['created'])) echo '<div class="notice notice-success"><p>✅ Код активации создан.</p></div>';
if (isset($_GET['extended'])) echo '<div class="notice notice-success"><p>✅ Срок аренды продлён, сертификат перевыпущен.</p></div>';
if (isset($_GET['issued'])) echo '<div class="notice notice-success"><p>✅ Сертификат выпущен — скопируйте его и передайте клиенту.</p></div>';
$offerr = ['badreq'=>'Не удалось разобрать строку запроса (ждём AKPPREQ-…)','nokey'=>'Код активации из запроса не найден в реестре','blocked'=>'Эта лицензия заблокирована'];
if (isset($_GET['offerr'], $offerr[$_GET['offerr']])) echo '<div class="notice notice-error"><p>⚠️ ' . esc_html($offerr[$_GET['offerr']]) . '</p></div>';

$licenses = $wpdb->get_results("SELECT * FROM $t ORDER BY created_at DESC LIMIT 100");
$m_total = count($licenses); $m_active = $m_expired = $m_blocked = $m_pending = 0;
foreach ($licenses as $l) { $st = $l->status; if ($st === 'active' && strtotime($l->valid_until) < $now) $st = 'expired';
    if ($st === 'active') $m_active++; elseif ($st === 'expired') $m_expired++; elseif ($st === 'blocked') $m_blocked++; else $m_pending++; }

$last = get_transient('akpp_license_last_issued');
$seal = null;
if ($last && !empty($last['token'])) { $v = AKPP_License::verify_certificate($last['token']); $seal = $v; }
$pubkey = AKPP_License::pubkey();
?>
<style>
.akpp-lic{ position:relative; max-width:1140px; margin:20px 20px 60px 0; color:#e2e8f0; padding:32px; border-radius:18px; border:1px solid #2d3748; overflow:hidden;
  background: radial-gradient(900px 320px at 12% -10%, rgba(0,255,136,.08), transparent 60%), radial-gradient(700px 300px at 100% 0%, rgba(65,210,226,.07), transparent 55%), #0a0f1c; }
.akpp-lic::before{ content:''; position:absolute; left:0; right:0; top:0; height:3px; background:linear-gradient(90deg,#00ff88,#41d2e2 55%,#f5b544); }
.akpp-lic-head{ display:flex; align-items:flex-end; justify-content:space-between; gap:20px; flex-wrap:wrap; margin-bottom:26px; }
.akpp-lic-head h1{ position:relative; margin:0; padding-left:15px; font-family:var(--font-display,'Unbounded',sans-serif); font-size:clamp(24px,3.4vw,34px); font-weight:800; letter-spacing:-.6px; color:#fff; }
.akpp-lic-head h1::before{ content:''; position:absolute; left:0; top:.08em; bottom:.08em; width:4px; border-radius:4px; background:linear-gradient(180deg,#00ff88,#00cc6a); }
.akpp-lic-head .sub{ margin:8px 0 0; padding-left:15px; color:#a0aec0; max-width:64ch; line-height:1.6; font-size:13.5px; }
.akpp-lic-api{ font-family:'Courier New',monospace; font-size:11.5px; color:#41d2e2; background:rgba(65,210,226,.08); border:1px solid rgba(65,210,226,.25); padding:7px 12px; border-radius:9px; white-space:nowrap; }
.akpp-lic-metrics{ display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:14px; margin-bottom:24px; }
.akpp-lic-metric{ position:relative; background:#1a1f2e; border:1px solid #2d3748; border-radius:14px; padding:18px 18px 16px; overflow:hidden; transition:transform .25s, border-color .25s, box-shadow .25s; }
.akpp-lic-metric:hover{ transform:translateY(-3px); border-color:rgba(0,255,136,.4); box-shadow:0 18px 40px -24px rgba(0,0,0,.9); }
.akpp-lic-metric::after{ content:''; position:absolute; left:0; top:0; bottom:0; width:3px; background:var(--mc,#4a5568); }
.akpp-lic-metric .num{ font-family:var(--font-display,'Unbounded',sans-serif); font-size:38px; font-weight:800; line-height:1; letter-spacing:-1px; color:var(--mc,#fff); font-variant-numeric:tabular-nums; }
.akpp-lic-metric .lbl{ margin-top:9px; font-size:11px; text-transform:uppercase; letter-spacing:.7px; color:#a0aec0; font-weight:700; }
.akpp-lic-card{ position:relative; background:#1a1f2e; border:1px solid #2d3748; border-radius:16px; padding:24px; margin-bottom:22px; overflow:hidden; }
.akpp-lic-card::before{ content:''; position:absolute; left:0; right:0; top:0; height:3px; background:linear-gradient(90deg,rgba(0,255,136,.85),rgba(65,210,226,.5) 60%,transparent); }
.akpp-lic-card h2{ margin:0 0 6px; font-size:16px; font-weight:800; letter-spacing:.2px; color:#fff; }
.akpp-lic-card .hint{ margin:0 0 16px; color:#a0aec0; font-size:12.5px; line-height:1.55; }
.akpp-lic form.inline{ display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
.akpp-lic label{ display:flex; flex-direction:column; gap:6px; color:#a0aec0; font-size:12px; font-weight:600; }
.akpp-lic select,.akpp-lic input[type=text],.akpp-lic textarea{ background:#0a0f1c; border:1px solid #2d3748; border-radius:9px; color:#fff; padding:10px 13px; font-family:'Courier New',monospace; font-size:12.5px; transition:border-color .2s, box-shadow .2s; }
.akpp-lic select{ font-family:inherit; }
.akpp-lic select:focus,.akpp-lic input:focus,.akpp-lic textarea:focus{ outline:none; border-color:#00ff88; box-shadow:0 0 0 3px rgba(0,255,136,.13); }
.akpp-lic textarea{ width:100%; min-height:74px; resize:vertical; }
.akpp-lic .btn{ background:linear-gradient(135deg,#00ff88,#00cc6a); color:#08120c; border:none; padding:11px 20px; border-radius:10px; font-weight:800; cursor:pointer; text-decoration:none; display:inline-block; transition:transform .2s, box-shadow .25s, filter .2s; }
.akpp-lic .btn:hover{ transform:translateY(-2px); box-shadow:0 14px 30px -14px rgba(0,255,136,.7); filter:brightness(1.05); }
.akpp-lic .btn-gray{ background:#2d3748; color:#fff; border:1px solid #4a5568; padding:6px 11px; border-radius:8px; cursor:pointer; font-size:12px; font-weight:600; transition:all .2s; }
.akpp-lic .btn-gray:hover{ border-color:#00ff88; color:#00ff88; transform:translateY(-1px); }
.akpp-lic .btn-red{ background:rgba(252,129,129,.16); color:#fc8181; border:1px solid rgba(252,129,129,.4); padding:6px 11px; border-radius:8px; cursor:pointer; font-size:12px; font-weight:700; transition:all .2s; }
.akpp-lic .btn-red:hover{ background:#fc8181; color:#08120c; transform:translateY(-1px); }
.akpp-lic table{ width:100%; border-collapse:collapse; }
.akpp-lic th,.akpp-lic td{ padding:12px; border-bottom:1px solid #2d3748; text-align:left; vertical-align:middle; font-size:13px; }
.akpp-lic th{ color:#a0aec0; text-transform:uppercase; font-size:10.5px; letter-spacing:.6px; font-weight:700; }
.akpp-lic tbody tr{ transition:background .18s; animation:lic-rise .45s cubic-bezier(.2,.7,.2,1) both; }
.akpp-lic tbody tr:nth-child(2){animation-delay:.03s}.akpp-lic tbody tr:nth-child(3){animation-delay:.06s}.akpp-lic tbody tr:nth-child(4){animation-delay:.09s}
.akpp-lic tbody tr:hover{ background:rgba(0,255,136,.05); }
.akpp-lic code{ background:#0a0f1c; border:1px solid #2d3748; color:#63b3ed; padding:4px 9px; border-radius:6px; font-size:12px; letter-spacing:.3px; }
.akpp-lic .st{ display:inline-flex; align-items:center; gap:7px; font-weight:700; font-size:12.5px; white-space:nowrap; }
.akpp-lic .dot{ width:8px; height:8px; border-radius:50%; background:currentColor; }
.akpp-lic .st-pending{ color:#f6ad55; } .akpp-lic .st-active{ color:#00ff88; } .akpp-lic .st-blocked{ color:#fc8181; } .akpp-lic .st-expired{ color:#fc8181; }
.akpp-lic .st-active .dot{ animation:lic-pulse 2s infinite; }
.akpp-lic .muted{ color:#718096; font-size:11px; }
.akpp-lic .acts{ white-space:nowrap; display:flex; gap:6px; flex-wrap:wrap; }
.akpp-lic .seal-badge{ display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:700; color:#00ff88; background:rgba(0,255,136,.1); border:1px solid rgba(0,255,136,.3); padding:3px 8px; border-radius:999px; }
.akpp-lic-flow{ display:grid; grid-template-columns:1fr auto 1fr; gap:16px; align-items:stretch; }
.akpp-lic-flow .arrow{ display:flex; align-items:center; justify-content:center; color:#41d2e2; font-size:26px; }
.akpp-lic-flow .step{ background:#0a0f1c; border:1px solid #2d3748; border-radius:12px; padding:16px; }
.akpp-lic-flow .step h3{ margin:0 0 10px; font-size:12px; text-transform:uppercase; letter-spacing:.6px; color:#a0aec0; }
.akpp-lic-tokenbox{ background:#0a0f1c; border:1px solid rgba(0,255,136,.35); border-radius:12px; padding:14px; word-break:break-all; font-family:'Courier New',monospace; font-size:11.5px; color:#9ae6b4; line-height:1.5; max-height:120px; overflow:auto; }
.akpp-seal{ display:flex; gap:18px; align-items:center; background:linear-gradient(135deg,rgba(0,255,136,.07),rgba(65,210,226,.05)); border:1px solid rgba(0,255,136,.3); border-radius:14px; padding:18px 20px; }
.akpp-seal .mark{ font-size:44px; line-height:1; filter:drop-shadow(0 0 14px rgba(0,255,136,.5)); animation:lic-pulse 2.4s infinite; }
.akpp-seal .info{ flex:1; }
.akpp-seal .info .row{ display:flex; gap:10px; font-size:12.5px; padding:3px 0; color:#cbd5e0; }
.akpp-seal .info .row b{ color:#a0aec0; min-width:96px; font-weight:600; }
.akpp-seal .ok{ color:#00ff88; font-weight:800; }
.akpp-seal .bad{ color:#fc8181; font-weight:800; }
.akpp-lic ol.steps{ margin:0; padding-left:20px; color:#cbd5e0; line-height:1.8; font-size:13px; }
.akpp-lic ol.steps code{ font-size:11.5px; }
@media (max-width:760px){ .akpp-lic-flow{ grid-template-columns:1fr; } .akpp-lic-flow .arrow{ transform:rotate(90deg); } }
@keyframes lic-rise{ from{ opacity:0; transform:translateY(10px);} to{ opacity:1; transform:none;} }
@keyframes lic-pulse{ 0%{ box-shadow:0 0 0 0 rgba(0,255,136,.5);} 70%{ box-shadow:0 0 0 8px rgba(0,255,136,0);} 100%{ box-shadow:0 0 0 0 rgba(0,255,136,0);} }
@media (prefers-reduced-motion:reduce){ .akpp-lic tbody tr,.akpp-lic .st-active .dot,.akpp-seal .mark{ animation:none; } .akpp-lic .btn,.akpp-lic .btn-gray,.akpp-lic .btn-red,.akpp-lic-metric{ transition:none; } }
.akpp-lic-tabs{ display:flex; gap:8px; flex-wrap:wrap; margin:0 0 22px; padding:6px; background:#0a0f1c; border:1px solid #2d3748; border-radius:14px; position:sticky; top:32px; z-index:20; box-shadow:0 10px 30px -18px rgba(0,0,0,.9); }
.akpp-lic-tab-btn{ background:transparent; border:1px solid transparent; color:#a0aec0; padding:11px 18px; border-radius:10px; font-weight:700; font-size:13px; cursor:pointer; transition:all .2s; font-family:inherit; white-space:nowrap; }
.akpp-lic-tab-btn:hover{ color:#fff; background:#1a1f2e; transform:translateY(-1px); }
.akpp-lic-tab-btn.active{ background:linear-gradient(135deg,#00ff88,#00cc6a); color:#08120c; box-shadow:0 8px 20px -10px rgba(0,255,136,.6); }
.akpp-lic-tab{ display:none; }
.akpp-lic-tab.active{ display:block; animation:lic-rise .35s cubic-bezier(.2,.7,.2,1) both; }
@media (max-width:760px){ .akpp-lic-tabs{ position:static; } .akpp-lic-tab-btn{ flex:1; text-align:center; padding:10px 8px; font-size:12px; } }
@media (prefers-reduced-motion:reduce){ .akpp-lic-tab.active{ animation:none; } .akpp-lic-tab-btn{ transition:none; } }
</style>
<div class="wrap akpp-lic">
    <div class="akpp-lic-head">
        <div>
            <h1>🔑 Лицензии</h1>
            <p class="sub">Аренда CRM без права доработки кода. Офлайн‑валидация подписанными сертификатами: клиентские установки проверяют лицензию локально и не зависят от сервера лицензий в рабочем режиме.</p>
        </div>
        <span class="akpp-lic-api">Ed25519 · подпись сертификата · офлайн</span>
    </div>

    <div class="akpp-lic-metrics">
        <div class="akpp-lic-metric" style="--mc:#fff"><div class="num"><?php echo $m_total; ?></div><div class="lbl">Всего ключей</div></div>
        <div class="akpp-lic-metric" style="--mc:#00ff88"><div class="num"><?php echo $m_active; ?></div><div class="lbl">Активных</div></div>
        <div class="akpp-lic-metric" style="--mc:#f6ad55"><div class="num"><?php echo $m_pending; ?></div><div class="lbl">Ожидают активации</div></div>
        <div class="akpp-lic-metric" style="--mc:#fc8181"><div class="num"><?php echo $m_expired + $m_blocked; ?></div><div class="lbl">Истекло / блок</div></div>
    </div>

    <?php if ($seal && !empty($last['token'])): ?>
    <div class="akpp-lic-card">
        <h2>🔏 Пломба последнего сертификата</h2>
        <p class="hint">Подписан мастером. Клиент проверяет эту подпись локально — подделать срок/домен без приватного ключа невозможно.</p>
        <div class="akpp-seal">
            <div class="mark"><?php echo !empty($seal['ok']) ? '🔏' : '⛔'; ?></div>
            <div class="info">
                <div class="row"><b>Подпись</b> <span class="<?php echo !empty($seal['ok']) ? 'ok' : 'bad'; ?>"><?php echo !empty($seal['ok']) ? '✅ валидна' : '❌ не прошла проверку'; ?></span></div>
                <div class="row"><b>Домен</b> <span><?php echo esc_html($last['domain'] ?? '—'); ?></span></div>
                <div class="row"><b>Валиден до</b> <span><?php echo esc_html($last['valid'] ?? '—'); ?></span></div>
                <div class="row"><b>Ключ</b> <span><code><?php echo esc_html($last['key'] ?? '—'); ?></code></span></div>
            </div>
        </div>
        <div style="margin-top:14px">
            <div class="akpp-lic-tokenbox" id="last-token"><?php echo esc_html($last['token']); ?></div>
            <button type="button" class="btn" style="margin-top:10px" onclick="var b=this;navigator.clipboard.writeText(document.getElementById('last-token').textContent);var o=b.textContent;b.textContent='✓ Сертификат скопирован';setTimeout(function(){b.textContent=o;},1500);">📋 Скопировать сертификат для клиента</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="akpp-lic-tabs">
        <button type="button" class="akpp-lic-tab-btn active" data-tab="registry">📋 Реестр лицензий</button>
        <button type="button" class="akpp-lic-tab-btn" data-tab="deploy">🆕 Установить CRM</button>
        <button type="button" class="akpp-lic-tab-btn" data-tab="updates">🚀 Обновления</button>
        <button type="button" class="akpp-lic-tab-btn" data-tab="keys">🔐 Ключ и сборка</button>
    </div>
    <div class="akpp-lic-tab active" data-tab="registry">
    <div class="akpp-lic-card">
        <h2>➕ Создать код активации</h2>
        <p class="hint">Код выдаётся клиенту. Активация — онлайн (один запрос) или полностью офлайн через обмен строками ниже.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="inline">
            <?php wp_nonce_field('akpp_license_create'); ?>
            <input type="hidden" name="action" value="akpp_license_create">
            <label>Срок аренды
                <select name="plan_months"><option value="12">1 год</option><option value="24">2 года</option><option value="36">3 года</option><option value="1">1 месяц (тест)</option></select>
            </label>
            <label>Заметка (клиент / домен)<input type="text" name="note" placeholder="ИП Иванов · client.ru"></label>
            <button type="submit" class="btn">Сгенерировать код</button>
        </form>
    </div>

    <div class="akpp-lic-card">
        <h2>🔁 Офлайн‑активация (без сети у клиента)</h2>
        <p class="hint">Клиент в своей CRM формирует строку запроса <code>AKPPREQ-…</code> (код + домен + отпечаток кода) и передаёт её вам любым способом. Вставьте её сюда — мастер выпустит подписанный сертификат, который вы вернёте клиенту. Сервер лицензий в рабочем режиме клиенту не нужен.</p>
        <div class="akpp-lic-flow">
            <div class="step">
                <h3>1 · Запрос клиента</h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('akpp_license_offline_issue'); ?>
                    <input type="hidden" name="action" value="akpp_license_offline_issue">
                    <textarea name="request" placeholder="AKPPREQ-…" required></textarea>
                    <button type="submit" class="btn" style="margin-top:10px">Выпустить сертификат →</button>
                </form>
            </div>
            <div class="arrow">→</div>
            <div class="step">
                <h3>2 · Сертификат клиенту</h3>
                <p class="hint" style="margin-bottom:8px">Появится в «Пломбе» выше и в строке лицензии (🔏). Скопируйте и передайте клиенту — он вставит его в настройки своей CRM.</p>
                <div class="akpp-lic-tokenbox" style="opacity:.7">Сертификат будет здесь после выпуска…</div>
            </div>
        </div>
    </div>

    <div class="akpp-lic-card">
        <h2>📋 Реестр лицензий</h2>
        <?php if (empty($licenses)): ?><p class="muted">Ключей пока нет — создайте первый выше.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Код активации</th><th>Срок</th><th>Статус</th><th>Домен</th><th>Валидна до</th><th>Сертификат</th><th>Действия</th></tr></thead>
            <tbody>
            <?php foreach ($licenses as $l):
                $st = $l->status; if ($st === 'active' && strtotime($l->valid_until) < $now) $st = 'expired';
                $st_label = ['pending'=>'Ожидает активации','active'=>'Активна','blocked'=>'Заблокирована','expired'=>'Истекла'][$st] ?? $st;
            ?>
                <tr>
                    <td><code><?php echo esc_html($l->activation_key); ?></code><?php if ($l->note): ?><div class="muted"><?php echo esc_html($l->note); ?></div><?php endif; ?></td>
                    <td><?php echo (int) $l->plan_months; ?> мес</td>
                    <td><span class="st st-<?php echo esc_attr($st); ?>"><span class="dot"></span><?php echo esc_html($st_label); ?></span></td>
                    <td><?php echo $l->domain ? esc_html($l->domain) : '<span class="muted">—</span>'; ?></td>
                    <td><?php echo $l->valid_until ? esc_html($l->valid_until) : '<span class="muted">—</span>'; ?></td>
                    <td><?php if ($l->license_token): ?><span class="seal-badge" title="Подписанный сертификат выдан">🔏 выдан</span> <button type="button" class="btn-gray" onclick="var b=this;navigator.clipboard.writeText('<?php echo esc_js($l->license_token); ?>');var o=b.textContent;b.textContent='✓';setTimeout(function(){b.textContent=o;},1200);">Копировать</button><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                    <td><div class="acts">
                        <button type="button" class="btn-gray" onclick="var b=this;navigator.clipboard.writeText('<?php echo esc_js($l->activation_key); ?>');var o=b.textContent;b.textContent='✓ Код';setTimeout(function(){b.textContent=o;},1200);">Код</button>
                        <?php if (in_array($st, ['active','expired'], true)): ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-flex;gap:5px;align-items:center">
                            <?php wp_nonce_field('akpp_license_extend_' . $l->id); ?><input type="hidden" name="action" value="akpp_license_extend"><input type="hidden" name="id" value="<?php echo (int) $l->id; ?>">
                            <select name="months" style="padding:5px 7px;font-size:12px;background:#0a0f1c;border:1px solid #2d3748;border-radius:7px;color:#fff"><option value="12">+12м</option><option value="24">+24м</option><option value="36">+36м</option></select>
                            <button type="submit" class="btn-gray" title="Продлить аренду (перевыпуск сертификата)">⏱</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($l->status !== 'blocked'): ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline" onsubmit="return confirm('Заблокировать лицензию?');">
                            <?php wp_nonce_field('akpp_license_block_' . $l->id); ?><input type="hidden" name="action" value="akpp_license_block"><input type="hidden" name="id" value="<?php echo (int) $l->id; ?>">
                            <button type="submit" class="btn-red">Блок</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($l->status === 'active'): ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline" onsubmit="return confirm('Сбросить привязку домена/кода (для переноса)?');">
                            <?php wp_nonce_field('akpp_license_reset_' . $l->id); ?><input type="hidden" name="action" value="akpp_license_reset"><input type="hidden" name="id" value="<?php echo (int) $l->id; ?>">
                            <button type="submit" class="btn-gray" title="Сброс привязки">↺</button>
                        </form>
                        <?php endif; ?>
                    <?php if (!empty($l->domain) && $l->status === 'active' && class_exists('AKPP_Updater')) echo AKPP_Updater::render_row_button($l->domain); ?>
                      </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    </div><!-- /tab registry -->
<div class="akpp-lic-tab" data-tab="deploy">
    <?php include AKPP_CRM_PATH . 'templates/deploy-block.php'; ?>

    </div><!-- /tab deploy -->
<div class="akpp-lic-tab" data-tab="updates">
    <?php include AKPP_CRM_PATH . 'templates/updater-block.php'; ?>

    </div><!-- /tab updates -->
<div class="akpp-lic-tab" data-tab="keys">
    <div class="akpp-lic-card">
        <h2>🔑 Публичный ключ (вшивается в коробку CRM)</h2>
        <p class="hint">Клиентская CRM проверяет сертификаты этим ключом. Приватный ключ остаётся только на мастере и в коробку не попадает.</p>
        <div class="akpp-lic-tokenbox" id="pub-key"><?php echo esc_html($pubkey); ?></div>
        <button type="button" class="btn-gray" style="margin-top:10px" onclick="var b=this;navigator.clipboard.writeText(document.getElementById('pub-key').textContent);var o=b.textContent;b.textContent='✓ Скопировано';setTimeout(function(){b.textContent=o;},1300);">Копировать публичный ключ</button>
    </div>

    <div class="akpp-lic-card">
        <h2>📦 Как собрать коробку CRM для клиента (многосайтовость, без доступа к серверу лицензий)</h2>
        <ol class="steps">
            <li>Скопируйте тему CRM на домен клиента.</li>
            <li><b>Не переносите</b> опцию <code>akpp_license_keypair</code> — приватный ключ остаётся только на мастере.</li>
            <li>В <code>wp-config.php</code> коробки добавьте: <code>define('AKPP_LICENSE_PUBKEY', '<?php echo esc_html($pubkey); ?>');</code></li>
            <li>Выдайте клиенту сертификат (кнопка «Копировать сертификат» в реестре) и задайте на его сайте опцию <code>akpp_license_token</code> = этот сертификат (через <code>wp-cli</code>: <code>wp option update akpp_license_token '…'</code>).</li>
            <li>Агент включится сам (видит токен) и будет проверять подпись <b>локально</b> — сервер лицензий клиенту не нужен.</li>
        </ol>
    </div>
</div><!-- /tab keys -->
<script>
(function(){
  var tabs=document.querySelectorAll('.akpp-lic-tab');
  var btns=document.querySelectorAll('.akpp-lic-tab-btn');
  function show(name){
    tabs.forEach(function(t){ t.classList.toggle('active', t.getAttribute('data-tab')===name); });
    btns.forEach(function(b){ b.classList.toggle('active', b.getAttribute('data-tab')===name); });
    if(history.replaceState) history.replaceState(null,'','#'+name);
  }
  btns.forEach(function(b){ b.addEventListener('click', function(){ show(b.getAttribute('data-tab')); }); });
  var h=(location.hash||'').replace('#','');
  var valid=Array.prototype.some.call(tabs,function(t){return t.getAttribute('data-tab')===h;});
  show(valid?h:'registry');
})();
</script>
</div>
