<?php if (!defined('ABSPATH')) exit;
$tpls = akpp_site_templates(); $cur = akpp_current_site_template();
if (isset($_GET['applied'])) echo '<div class="notice notice-success"><p>✅ Шаблон «' . esc_html($tpls[$_GET['applied']]['name'] ?? '') . '» применён. Откройте сайт, чтобы увидеть результат.</p></div>';
$shop_on = (bool) get_option('akpp_shop_enabled', 1);
?>
<style>
.akpp-tpl{position:relative;max-width:1140px;margin:20px 20px 60px 0;color:#e2e8f0;padding:32px;border-radius:18px;border:1px solid #2d3748;overflow:hidden;
 background:radial-gradient(900px 320px at 12% -10%,rgba(0,255,136,.08),transparent 60%),radial-gradient(700px 300px at 100% 0%,rgba(65,210,226,.07),transparent 55%),#0a0f1c}
.akpp-tpl::before{content:'';position:absolute;left:0;right:0;top:0;height:3px;background:linear-gradient(90deg,#00ff88,#41d2e2 55%,#f5b544)}
.akpp-tpl h1{position:relative;margin:0;padding-left:15px;font-family:var(--font-display,'Unbounded',sans-serif);font-size:clamp(24px,3.4vw,34px);font-weight:800;letter-spacing:-.6px;color:#fff}
.akpp-tpl h1::before{content:'';position:absolute;left:0;top:.08em;bottom:.08em;width:4px;border-radius:4px;background:linear-gradient(180deg,#00ff88,#00cc6a)}
.akpp-tpl .sub{margin:8px 0 8px;padding-left:15px;color:#a0aec0;max-width:64ch;line-height:1.6;font-size:13.5px}
.akpp-tpl .state{display:inline-flex;align-items:center;gap:8px;margin:6px 0 26px 15px;font-size:12.5px;font-weight:700;color:#00ff88;background:rgba(0,255,136,.1);border:1px solid rgba(0,255,136,.3);padding:6px 13px;border-radius:999px}
.akpp-tpl .state .d{width:8px;height:8px;border-radius:50%;background:#00ff88;animation:tp 2s infinite}
.akpp-tpl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:22px}
.akpp-tpl-card{position:relative;background:#1a1f2e;border:1px solid #2d3748;border-radius:18px;overflow:hidden;transition:transform .3s,border-color .3s,box-shadow .3s;animation:tr .5s cubic-bezier(.2,.7,.2,1) both}
.akpp-tpl-card:nth-child(2){animation-delay:.06s}.akpp-tpl-card:nth-child(3){animation-delay:.12s}
.akpp-tpl-card:hover{transform:translateY(-6px);border-color:rgba(0,255,136,.4);box-shadow:0 30px 60px -34px rgba(0,0,0,.9)}
.akpp-tpl-card.active{border-color:#00ff88;box-shadow:0 0 0 1px #00ff88,0 30px 60px -34px rgba(0,255,136,.4)}
.akpp-tpl-card .badge{position:absolute;top:14px;right:14px;z-index:3;font-size:10.5px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;padding:5px 11px;border-radius:999px;backdrop-filter:blur(6px)}
.akpp-tpl-card .badge.shop{background:rgba(0,255,136,.16);color:#00ff88;border:1px solid rgba(0,255,136,.4)}
.akpp-tpl-card .badge.noshop{background:rgba(99,179,237,.16);color:#63b3ed;border:1px solid rgba(99,179,237,.4)}
.akpp-tpl-card .tick{position:absolute;top:14px;left:14px;z-index:3;width:30px;height:30px;border-radius:50%;background:#00ff88;display:none;place-items:center;box-shadow:0 8px 20px -6px rgba(0,255,136,.7)}
.akpp-tpl-card.active .tick{display:grid}
.akpp-tpl-card .tick svg{width:16px;height:16px;stroke:#08120c}
/* preview mock */
.akpp-tpl-prev{height:188px;position:relative;overflow:hidden;border-bottom:1px solid #2d3748}
.akpp-tpl-prev .bar{position:absolute;top:0;left:0;right:0;height:22px;background:rgba(0,0,0,.3);display:flex;gap:5px;align-items:center;padding:0 10px;z-index:2}
.akpp-tpl-prev .bar i{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.25)}
.akpp-tpl-prev .bar i:nth-child(1){background:#ff5f57}.akpp-tpl-prev .bar i:nth-child(2){background:#febc2e}.akpp-tpl-prev .bar i:nth-child(3){background:#28c840}
/* dark preview (impulse) */
.prev-dark{background:radial-gradient(120px 80px at 20% 30%,rgba(0,255,136,.25),transparent),#0b1120}
.prev-dark .hero{position:absolute;left:18px;top:40px;width:46%}.prev-dark .hero .l1{height:9px;width:80%;background:linear-gradient(90deg,#00ff88,#41d2e2);border-radius:3px;margin-bottom:7px}.prev-dark .hero .l2{height:9px;width:55%;background:rgba(255,255,255,.5);border-radius:3px;margin-bottom:12px}.prev-dark .hero .b{height:14px;width:42%;background:#00ff88;border-radius:5px}
.prev-dark .mock{position:absolute;right:16px;top:42px;width:42%;height:120px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:9px;display:grid;grid-template-columns:1fr 1fr;gap:6px}
.prev-dark .mock i{background:rgba(0,255,136,.18);border-radius:4px}.prev-dark .mock i:nth-child(3){grid-column:1/-1;background:linear-gradient(90deg,rgba(0,255,136,.4),rgba(65,210,226,.2))}
/* light preview (minimal) */
.prev-light{background:#f5f6f8}
.prev-light .hero{position:absolute;left:18px;top:42px;width:44%}.prev-light .hero .l1{height:9px;width:75%;background:#0b1120;border-radius:3px;margin-bottom:7px}.prev-light .hero .l2{height:9px;width:50%;background:#0b1120;opacity:.4;border-radius:3px;margin-bottom:12px}.prev-light .hero .b{height:14px;width:40%;background:#0b1120;border-radius:5px}
.prev-light .stk{position:absolute;right:16px;top:42px;width:44%;display:flex;flex-direction:column;gap:6px}
.prev-light .stk i{height:24px;background:#fff;border:1px solid #e7e9ee;border-radius:6px}.prev-light .stk i:nth-child(2){margin-left:14px}.prev-light .stk i:nth-child(3){margin-left:28px}
.prev-light .bento{position:absolute;left:18px;right:16px;bottom:14px;height:34px;display:grid;grid-template-columns:2fr 1fr 1fr;gap:6px}
.prev-light .bento i{background:#fff;border:1px solid #e7e9ee;border-radius:6px}.prev-light .bento i:first-child{background:#0b1120;border-color:#0b1120}
.akpp-tpl-body{padding:22px 22px 24px}
.akpp-tpl-body h3{margin:0 0 6px;font-size:19px;font-weight:800;color:#fff;font-family:var(--font-display,'Unbounded',sans-serif)}
.akpp-tpl-body p{margin:0 0 18px;color:#a0aec0;font-size:13.5px;line-height:1.55;min-height:42px}
.akpp-tpl-body .acts{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.akpp-tpl .btn{border:none;border-radius:10px;padding:11px 20px;font-weight:800;font-size:13.5px;cursor:pointer;transition:transform .2s,box-shadow .25s,filter .2s}
.akpp-tpl .btn-apply{background:linear-gradient(135deg,#00ff88,#00cc6a);color:#08120c}
.akpp-tpl .btn-apply:hover{transform:translateY(-2px);box-shadow:0 14px 30px -14px rgba(0,255,136,.7)}
.akpp-tpl .btn-edit{background:transparent;color:#41d2e2;border:1px solid rgba(65,210,226,.4);padding:8px 14px;font-size:12px;font-weight:700;border-radius:9px}
.akpp-tpl .btn-edit:hover{background:rgba(65,210,226,.1);border-color:#41d2e2;transform:translateY(-1px)}
.akpp-tpl .btn-cur{background:rgba(0,255,136,.12);color:#00ff88;border:1px solid rgba(0,255,136,.4);cursor:default}
.akpp-tpl .btn-prev{background:#2d3748;color:#fff;border:1px solid #4a5568;text-decoration:none;display:inline-block}
.akpp-tpl .btn-prev:hover{border-color:#00ff88;color:#00ff88}
.akpp-tpl-note{margin-top:26px;background:rgba(245,181,68,.08);border:1px solid rgba(245,181,68,.3);border-radius:12px;padding:14px 18px;color:#fbd38d;font-size:12.5px;line-height:1.6}
@keyframes tr{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
@keyframes tp{0%{box-shadow:0 0 0 0 rgba(0,255,136,.5)}70%{box-shadow:0 0 0 7px rgba(0,255,136,0)}100%{box-shadow:0 0 0 0 rgba(0,255,136,0)}}
@media(prefers-reduced-motion:reduce){.akpp-tpl-card,.akpp-tpl .state .d{animation:none}}
</style>
<div class="wrap akpp-tpl">
  <h1>🎨 Шаблоны сайта</h1>
  <p class="sub">Выберите готовый дизайн для главной страницы вашего сайта. Применяется в один клик — без кода и настроек. Магазин включается или выключается вместе с шаблоном.</p>
  <div class="state"><span class="d"></span>Сейчас активен: <?php echo esc_html($tpls[$cur]['name'] ?? '—'); ?> · магазин <?php echo $shop_on ? 'включён' : 'выключен'; ?></div>
  <div class="akpp-tpl-grid">
    <?php foreach ($tpls as $id => $t): $active = ($id === $cur); $prev = $t['tone'] === 'dark' ? 'prev-dark' : 'prev-light'; ?>
      <div class="akpp-tpl-card<?php echo $active ? ' active' : ''; ?>">
        <span class="badge <?php echo $t['has_shop'] ? 'shop' : 'noshop'; ?>"><?php echo $t['has_shop'] ? '🛒 с магазином' : '✦ без магазина'; ?></span>
        <span class="tick"><svg viewBox="0 0 24 24" fill="none" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg></span>
        <div class="akpp-tpl-prev <?php echo $prev; ?>">
          <div class="bar"><i></i><i></i><i></i></div>
          <?php if ($t['tone'] === 'dark'): ?>
            <div class="hero"><div class="l1"></div><div class="l2"></div><div class="b"></div></div>
            <div class="mock"><i></i><i></i><i></i></div>
          <?php else: ?>
            <div class="hero"><div class="l1"></div><div class="l2"></div><div class="b"></div></div>
            <div class="stk"><i></i><i></i><i></i></div>
            <div class="bento"><i></i><i></i><i></i></div>
          <?php endif; ?>
        </div>
        <div class="akpp-tpl-body">
          <h3><?php echo esc_html($t['name']); ?></h3>
          <p><?php echo esc_html($t['desc']); ?></p>
          <div class="acts">
            <?php if ($active): ?>
              <span class="btn btn-cur">✓ Активен</span>
                    <button type="button" class="btn btn-edit akpp-edit-tpl-btn" data-tpl="<?php echo esc_attr($t['file']); ?>" data-name="<?php echo esc_attr($t['name']); ?>" title="Редактировать">✏️</button>
            <?php else: ?>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                <?php wp_nonce_field('akpp_apply_template'); ?>
                <input type="hidden" name="action" value="akpp_apply_template">
                <input type="hidden" name="template" value="<?php echo esc_attr($id); ?>">
                <button type="submit" class="btn btn-apply">Применить</button>
                  <button type="button" class="btn btn-edit akpp-edit-tpl-btn" data-tpl="<?php echo esc_attr($t['file']); ?>" data-name="<?php echo esc_attr($t['name']); ?>" title="Редактировать">✏️</button>
              </form>
            <?php endif; ?>
            <a class="btn btn-prev" href="<?php echo esc_url(add_query_arg('akpp_tpl_preview', $id, home_url('/'))); ?>" target="_blank">Предпросмотр ↗</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="akpp-tpl-card" style="margin:26px 0 0;padding:24px;border:1px solid #2d3748;border-radius:16px;background:#1a1f2e;">
    <h3 style="margin:0 0 6px;color:#fff;font-size:16px;">📝 Данные шаблонов (ручной ввод)</h3>
    <p style="margin:0 0 16px;color:#a0aec0;font-size:12.5px;">Эти значения подставляются во ВСЕ шаблоны. Пустое поле = берётся дефолт сайта (реальные данные: телефон, Telegram, специализация).</p>
    <?php if (isset($_GET['saved'])) echo '<div class="notice notice-success" style="margin:0 0 12px;"><p>✅ Данные сохранены.</p></div>'; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;">
        <?php wp_nonce_field('akpp_tpl_save_data'); ?>
        <input type="hidden" name="action" value="akpp_tpl_save_data">
        <?php $cur = akpp_tpl_data(); foreach (akpp_tpl_fields() as $k => $label): ?>
        <label style="display:flex;flex-direction:column;gap:5px;color:#a0aec0;font-size:11.5px;font-weight:600;">
            <?php echo esc_html($label); ?>
            <input type="text" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr(get_option('akpp_tpl_data', []) && array_key_exists($k, get_option('akpp_tpl_data', [])) ? esc_attr(get_option('akpp_tpl_data', [])[$k]) : ''); ?>" placeholder="<?php echo esc_attr(mb_substr($cur[$k], 0, 60)); ?>" style="background:#0a0f1c;border:1px solid #2d3748;border-radius:8px;color:#fff;padding:9px 11px;font-size:12.5px;">
        </label>
        <?php endforeach; ?>
        <div style="grid-column:1/-1;"><button type="submit" class="btn btn-apply">💾 Сохранить данные</button></div>
    </form>
</div>
<div class="akpp-tpl-note">🔒 На вашем сайте закрыт доступ к редактору кода, темам и плагинам — это гарантирует стабильность и обновления. Выбор готового шаблона — единственная безопасная смена дизайна, и она доступна вам здесь.</div>
</div>

<?php $__map = []; foreach ($tpls as $__id => $__t) { $__map[$__id] = $__t['file']; } ?>
<script>window.AKPP_TPL_MAP = <?php echo json_encode($__map); ?>;</script>
<div id="akpp-editor-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:99999;overflow:auto;">
<div style="max-width:1800px;margin:20px auto;background:#0a0f1c;border:1px solid #2d3748;border-radius:14px;overflow:hidden;height:calc(100vh - 40px);">
<div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-bottom:1px solid #2d3748;background:#0e1524;">
    <div style="color:#fff;font-weight:800;font-size:14px;">✏️ Редактор: <span id="akpp-ed-file" style="color:#00ff88;font-family:ui-monospace,monospace;"></span></div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="button" id="akpp-ed-reload" title="Перечитать файл">↻ Код</button>
        <button type="button" class="button" id="akpp-ed-refresh-preview" title="Обновить предпросмотр">👁 Обновить</button>
        <button type="button" class="button button-primary" id="akpp-ed-save">💾 Сохранить</button>
        <button type="button" class="button" id="akpp-ed-close" style="margin-left:10px">✕ Закрыть</button>
    </div>
</div>
<div id="akpp-ed-status" style="display:none;padding:8px 20px;font-family:ui-monospace,monospace;font-size:12px;white-space:pre-wrap;"></div>
<div style="display:grid;grid-template-columns:1fr 1fr 260px;gap:0;height:calc(100% - 60px);">
    <div style="display:flex;flex-direction:column;overflow:hidden;">
        <div style="padding:8px 14px;background:#0e1524;border-bottom:1px solid #2d3748;color:#41d2e2;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;">📝 Код</div>
        <textarea id="akpp-ed-code" style="flex:1;width:100%;background:#05080f;color:#e2e8f0;border:0;padding:14px;font-family:ui-monospace,monospace;font-size:13px;line-height:1.5;resize:none;"></textarea>
    </div>
    <div style="display:flex;flex-direction:column;overflow:hidden;border-left:1px solid #2d3748;">
        <div style="padding:8px 14px;background:#0e1524;border-bottom:1px solid #2d3748;color:#00ff88;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;">👁 Предпросмотр</div>
        <iframe id="akpp-ed-preview" style="flex:1;width:100%;border:0;background:#fff;" sandbox="allow-scripts allow-same-origin"></iframe>
    </div>
    <aside style="background:#0a0f1c;border-left:1px solid #2d3748;padding:14px;overflow:auto;">
        <div style="color:#00ff88;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px;">🕒 История</div>
        <div id="akpp-ed-history" style="font-size:11px;"><em>Выберите файл</em></div>
    </aside>
</div>
</div>
</div>
