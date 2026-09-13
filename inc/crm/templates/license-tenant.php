<?php if (!defined('ABSPATH')) exit;
$st = $lic_state['status'] ?? 'unknown';
$ok = !empty($lic_v['ok']);
$st_label = ['active' => 'Активна', 'expired' => 'Истекла', 'blocked' => 'Заблокирована', 'not_activated' => 'Не активирована'][$st] ?? 'Нет данных';
$st_color = $ok ? '#00ff88' : ($st === 'expired' ? '#f5b544' : '#fc8181');
$valid = $lic_payload['exp'] ?? 0;
$valid_str = $valid ? date('d.m.Y', $valid) : '—';
$plan = (int) ($lic_payload['plan'] ?? 0);
$plan_str = $lic_unlimited ? 'Безлимит' : ($plan > 0 ? $plan . ' мес' : '—');
$dom = $lic_payload['domain'] ?? wp_parse_url(home_url(), PHP_URL_HOST);
$days_str = $lic_unlimited ? 'бессрочно' : ($lic_days_left > 0 ? $lic_days_left . ' дн.' : 'истёк');
$seal_icon = $ok ? '🔏' : '⛔';
?>
<style>
.akpp-mylic{position:relative;max-width:1140px;margin:20px 20px 60px 0;color:#e2e8f0;padding:34px;border-radius:18px;border:1px solid #2d3748;overflow:hidden;
 background:radial-gradient(820px 300px at 8% -12%,rgba(0,255,136,.10),transparent 60%),radial-gradient(680px 320px at 100% 0%,rgba(65,210,226,.08),transparent 55%),radial-gradient(600px 400px at 70% 120%,rgba(245,181,68,.05),transparent 60%),#0a0f1c}
.akpp-mylic::before{content:'';position:absolute;left:0;right:0;top:0;height:3px;background:linear-gradient(90deg,#00ff88,#41d2e2 55%,#f5b544)}
.akpp-mylic::after{content:'';position:absolute;inset:0;opacity:.4;pointer-events:none;background-image:linear-gradient(rgba(255,255,255,.022) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.022) 1px,transparent 1px);background-size:54px 54px;mask-image:radial-gradient(circle at 50% 0%,#000,transparent 80%)}
.akpp-mylic-head{position:relative;display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:28px}
.akpp-mylic-head h1{position:relative;margin:0;padding-left:15px;font-family:var(--font-display,'Unbounded',sans-serif);font-size:clamp(24px,3.4vw,34px);font-weight:800;letter-spacing:-.6px;color:#fff}
.akpp-mylic-head h1::before{content:'';position:absolute;left:0;top:.08em;bottom:.08em;width:4px;border-radius:4px;background:linear-gradient(180deg,#00ff88,#00cc6a)}
.akpp-mylic-head .sub{margin:8px 0 0;padding-left:15px;color:#a0aec0;max-width:60ch;line-height:1.6;font-size:13.5px}
.akpp-mylic-pill{font:800 12px/1 'Manrope',sans-serif;letter-spacing:.06em;text-transform:uppercase;padding:8px 15px;border-radius:999px;display:inline-flex;align-items:center;gap:8px;white-space:nowrap}
.akpp-mylic-pill .d{width:8px;height:8px;border-radius:50%;background:currentColor}
.akpp-mylic-pill.ok{color:#00ff88;background:rgba(0,255,136,.1);border:1px solid rgba(0,255,136,.35)}
.akpp-mylic-pill.ok .d{animation:mlp 2s infinite}
.akpp-mylic-pill.warn{color:#f5b544;background:rgba(245,181,68,.1);border:1px solid rgba(245,181,68,.35)}
.akpp-mylic-pill.bad{color:#fc8181;background:rgba(252,129,129,.1);border:1px solid rgba(252,129,129,.35)}
.akpp-mylic-grid{position:relative;display:grid;grid-template-columns:1.05fr 1.25fr;gap:22px;align-items:stretch}
.akpp-mylic-seal{position:relative;background:linear-gradient(160deg,rgba(0,255,136,.08),rgba(65,210,226,.04));border:1px solid rgba(0,255,136,.3);border-radius:18px;padding:30px;display:flex;flex-direction:column;gap:18px;overflow:hidden}
.akpp-mylic-seal::after{content:'';position:absolute;right:-40px;bottom:-40px;width:160px;height:160px;border-radius:50%;background:radial-gradient(circle,rgba(0,255,136,.16),transparent 70%);pointer-events:none}
.akpp-mylic-seal .mark{font-size:58px;line-height:1;filter:drop-shadow(0 0 18px rgba(0,255,136,.5))}
.akpp-mylic-seal .mark.ok{animation:mlp 2.4s infinite}
.akpp-mylic-seal .stt{font-family:var(--font-display,'Unbounded',sans-serif);font-size:30px;font-weight:800;letter-spacing:-.5px}
.akpp-mylic-seal .sig{display:inline-flex;align-items:center;gap:7px;font-weight:700;font-size:13px;color:#9ae6b4}
.akpp-mylic-seal .sig .dot{width:7px;height:7px;border-radius:50%;background:#00ff88}
.akpp-mylic-seal .sig.bad{color:#fc8181}.akpp-mylic-seal .sig.bad .dot{background:#fc8181}
.akpp-mylic-prog{margin-top:auto}
.akpp-mylic-prog .lab{display:flex;justify-content:space-between;font-size:12px;color:#a0aec0;font-weight:600;margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em}
.akpp-mylic-prog .lab b{color:#e2e8f0;font-weight:800}
.akpp-mylic-prog .track{height:9px;background:rgba(255,255,255,.07);border-radius:999px;overflow:hidden}
.akpp-mylic-prog .fill{height:100%;width:0;border-radius:999px;background:linear-gradient(90deg,#00ff88,#41d2e2);transition:width 1.3s cubic-bezier(.2,.7,.2,1)}
.akpp-mylic-prog.unlim .fill{background:linear-gradient(90deg,#00ff88,#00cc6a,#00ff88);background-size:200% 100%;animation:mls 3s linear infinite}
.akpp-mylic-metrics{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.akpp-mylic-m{position:relative;background:#1a1f2e;border:1px solid #2d3748;border-radius:15px;padding:18px 18px 16px;overflow:hidden;transition:transform .25s,border-color .25s,box-shadow .25s}
.akpp-mylic-m:hover{transform:translateY(-3px);border-color:rgba(0,255,136,.4);box-shadow:0 18px 40px -24px rgba(0,0,0,.9)}
.akpp-mylic-m::after{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--mc,#4a5568)}
.akpp-mylic-m .k{font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:#a0aec0;font-weight:700;margin-bottom:8px}
.akpp-mylic-m .v{font-family:var(--font-display,'Unbounded',sans-serif);font-size:21px;font-weight:800;color:var(--mc,#fff);letter-spacing:-.3px;word-break:break-word}
.akpp-mylic-m .v.sm{font-size:15px;font-family:'Manrope',sans-serif;font-weight:700}
.akpp-mylic-m .v .okc{color:#00ff88}.akpp-mylic-m .v .badc{color:#fc8181}.akpp-mylic-m .v .mutc{color:#718096}
.akpp-mylic-inc{position:relative;margin-top:22px;background:#1a1f2e;border:1px solid #2d3748;border-radius:16px;padding:24px}
.akpp-mylic-inc h2{margin:0 0 14px;font-size:15px;font-weight:800;color:#fff;letter-spacing:.2px}
.akpp-mylic-inc ul{list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:11px 22px;margin:0}
.akpp-mylic-inc li{display:flex;gap:10px;align-items:center;color:#cbd5e0;font-size:14px}
.akpp-mylic-inc li svg{width:18px;height:18px;stroke:#00ff88;flex:0 0 auto}
.akpp-mylic-inc li.off{color:#5a6478}.akpp-mylic-inc li.off svg{stroke:#3a4252}
.akpp-mylic-note{position:relative;margin-top:22px;background:rgba(65,210,226,.07);border:1px solid rgba(65,210,226,.28);border-radius:13px;padding:15px 18px;color:#a9d8e6;font-size:13px;line-height:1.6;display:flex;gap:12px;align-items:flex-start}
.akpp-mylic-note .ic{font-size:18px;flex:0 0 auto;line-height:1.3}
.akpp-mylic-note a{color:#41d2e2;font-weight:700}
.rv{opacity:0;transform:translateY(18px);transition:opacity .6s cubic-bezier(.2,.7,.2,1),transform .6s cubic-bezier(.2,.7,.2,1)}.rv.v{opacity:1;transform:none}
@keyframes mlp{0%{filter:drop-shadow(0 0 0 rgba(0,255,136,.5))}50%{filter:drop-shadow(0 0 16px rgba(0,255,136,.6))}100%{filter:drop-shadow(0 0 0 rgba(0,255,136,.5))}}
@keyframes mls{0%{background-position:0 0}100%{background-position:200% 0}}
@media(max-width:880px){.akpp-mylic-grid{grid-template-columns:1fr}.akpp-mylic-metrics{grid-template-columns:1fr 1fr}.akpp-mylic-inc ul{grid-template-columns:1fr}}
@media(max-width:480px){.akpp-mylic-metrics{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}.rv{opacity:1;transform:none}.akpp-mylic-prog .fill{transition:none}}
</style>
<div class="wrap akpp-mylic">
  <div class="akpp-mylic-head">
    <div>
      <h1>🔑 Моя лицензия</h1>
      <p class="sub">Статус аренды CRM на этом сайте. Лицензия проверяется локально и не требует постоянного соединения с сервером поставщика.</p>
    </div>
    <span class="akpp-mylic-pill <?php echo $ok ? 'ok' : ($st === 'expired' ? 'warn' : 'bad'); ?>"><span class="d"></span><?php echo esc_html($st_label); ?></span>
  </div>

  <div class="akpp-mylic-grid">
    <div class="akpp-mylic-seal rv">
      <div class="mark <?php echo $ok ? 'ok' : ''; ?>"><?php echo $seal_icon; ?></div>
      <div>
        <div class="stt" style="color:<?php echo $st_color; ?>"><?php echo esc_html($st_label); ?></div>
        <div class="sig <?php echo $ok ? '' : 'bad'; ?>"><span class="dot"></span><?php echo $ok ? 'Подпись поставщика валидна' : 'Подпись не прошла проверку'; ?></div>
      </div>
      <div class="akpp-mylic-prog <?php echo $lic_unlimited ? 'unlim' : ''; ?>">
        <div class="lab"><span>Срок аренды</span><b><?php echo $lic_unlimited ? 'Безлимит' : 'использовано ' . $lic_pct . '%'; ?></b></div>
        <div class="track"><div class="fill" data-pct="<?php echo $lic_pct; ?>"></div></div>
      </div>
    </div>

    <div class="akpp-mylic-metrics">
      <div class="akpp-mylic-m rv" style="--mc:#00ff88"><div class="k">Действует до</div><div class="v"><?php echo esc_html($valid_str); ?></div></div>
      <div class="akpp-mylic-m rv" style="--mc:#41d2e2"><div class="k">Осталось</div><div class="v"><?php echo esc_html($days_str); ?></div></div>
      <div class="akpp-mylic-m rv" style="--mc:#f5b544"><div class="k">Тарифный план</div><div class="v"><?php echo esc_html($plan_str); ?></div></div>
      <div class="akpp-mylic-m rv" style="--mc:#fff"><div class="k">Домен</div><div class="v sm"><?php echo esc_html($dom); ?> <?php echo $lic_domain_match ? '<span class="okc">✓</span>' : '<span class="badc">⚠</span>'; ?></div></div>
      <div class="akpp-mylic-m rv" style="--mc:<?php echo $lic_unlimited ? '#00ff88' : ($lic_fp_match ? '#00ff88' : '#fc8181'); ?>"><div class="k">Целостность кода</div><div class="v sm"><?php if ($lic_unlimited): ?><span class="okc">не контролируется</span><?php elseif ($lic_fp_match === true): ?><span class="okc">совпадает ✓</span><?php elseif ($lic_fp_match === false): ?><span class="badc">код изменён ⚠</span><?php else: ?><span class="mutc">—</span><?php endif; ?></div></div>
      <div class="akpp-mylic-m rv" style="--mc:#a0aec0"><div class="k">Тип лицензии</div><div class="v sm"><?php echo $lic_unlimited ? 'Сайт владельца' : 'Аренда'; ?></div></div>
    </div>
  </div>

  <div class="akpp-mylic-inc rv">
    <h2>Что включено в аренду</h2>
    <ul>
      <li><svg viewBox="0 0 24 24" fill="none" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg>Воронка сделок и лиды</li>
      <li><svg viewBox="0 0 24 24" fill="none" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg>Клиентская база</li>
      <li><svg viewBox="0 0 24 24" fill="none" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg>Финансы и учёт</li>
      <li><svg viewBox="0 0 24 24" fill="none" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg>Интеграции 1С / Avito</li>
      <li class="<?php echo get_option('akpp_shop_enabled', 1) ? '' : 'off'; ?>"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg>Магазин и каталог</li>
      <li><svg viewBox="0 0 24 24" fill="none" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg>Обновления и поддержка</li>
    </ul>
  </div>

  <div class="akpp-mylic-note rv">
    <span class="ic">🛡️</span>
    <span>Лицензией управляет поставщик: продление срока, перенос на другой домен и смена тарифа выполняются на стороне мастера. Чтобы продлить аренду или изменить параметры — <a href="mailto:<?php echo esc_attr(get_option('admin_email')); ?>">свяжитесь с поддержкой</a>. Код системы защищён от изменений — это гарантирует стабильность и автоматические обновления.</span>
  </div>
</div>
<script>
(function(){
 var io=new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){x.target.classList.add('v');io.unobserve(x.target);}});},{threshold:.12});
 document.querySelectorAll('.akpp-mylic .rv').forEach(function(el){io.observe(el);});
 setTimeout(function(){document.querySelectorAll('.akpp-mylic-prog .fill').forEach(function(f){f.style.width=(f.dataset.pct||0)+'%';});},250);
})();
</script>
