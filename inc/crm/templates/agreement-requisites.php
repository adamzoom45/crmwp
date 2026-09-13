<?php if (!defined('ABSPATH')) exit;
if (isset($_GET['saved'])) echo '<div class="notice notice-success"><p>✅ Реквизиты сохранены. Оферта и форма сделки обновлены.</p></div>';
$g = function ($k, $d) { return esc_attr(get_option($k, $d)); };
?>
<style>
.akpp-req{position:relative;max-width:1080px;margin:20px 20px 60px 0;color:#e2e8f0;padding:32px;border-radius:18px;border:1px solid #2d3748;overflow:hidden;
 background:radial-gradient(820px 300px at 8% -12%,rgba(0,255,136,.09),transparent 60%),radial-gradient(680px 320px at 100% 0%,rgba(65,210,226,.07),transparent 55%),#0a0f1c}
.akpp-req::before{content:'';position:absolute;left:0;right:0;top:0;height:3px;background:linear-gradient(90deg,#00ff88,#41d2e2 55%,#f5b544)}
.akpp-req h1{position:relative;margin:0 0 6px;padding-left:15px;font-family:var(--font-display,'Unbounded',sans-serif);font-size:clamp(23px,3.2vw,32px);font-weight:800;letter-spacing:-.5px;color:#fff}
.akpp-req h1::before{content:'';position:absolute;left:0;top:.08em;bottom:.08em;width:4px;border-radius:4px;background:linear-gradient(180deg,#00ff88,#00cc6a)}
.akpp-req .sub{margin:0 0 26px;padding-left:15px;color:#a0aec0;max-width:70ch;line-height:1.6;font-size:13.5px}
.akpp-req-grp{position:relative;background:#1a1f2e;border:1px solid #2d3748;border-radius:16px;padding:24px;margin-bottom:20px;overflow:hidden}
.akpp-req-grp::before{content:'';position:absolute;left:0;right:0;top:0;height:3px;background:linear-gradient(90deg,rgba(0,255,136,.8),rgba(65,210,226,.4) 60%,transparent)}
.akpp-req-grp h2{margin:0 0 18px;font-size:15px;font-weight:800;color:#fff;letter-spacing:.2px;display:flex;align-items:center;gap:9px}
.akpp-req-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px 18px}
.akpp-req .fg{display:flex;flex-direction:column;gap:6px}
.akpp-req .fg.full{grid-column:1/-1}
.akpp-req label{font-size:12px;font-weight:700;color:#a0aec0;text-transform:uppercase;letter-spacing:.05em}
.akpp-req input,.akpp-req textarea{width:100%;background:#0a0f1c;border:1px solid #2d3748;border-radius:10px;color:#fff;padding:12px 14px;font-family:var(--fb,'Manrope',sans-serif);font-size:14.5px;transition:border-color .2s,box-shadow .2s}
.akpp-req input:focus,.akpp-req textarea:focus{outline:none;border-color:#00ff88;box-shadow:0 0 0 3px rgba(0,255,136,.14)}
.akpp-req textarea{resize:vertical;min-height:70px}
.akpp-req .hint{font-size:11.5px;color:#718096;margin-top:2px}
.akpp-req .save{display:flex;align-items:center;gap:16px;margin-top:6px}
.akpp-req .btn{background:linear-gradient(135deg,#00ff88,#00cc6a);color:#08120c;border:none;border-radius:11px;padding:14px 30px;font-weight:800;font-size:15px;cursor:pointer;transition:transform .2s,box-shadow .25s,filter .2s}
.akpp-req .btn:hover{transform:translateY(-2px);box-shadow:0 14px 30px -14px rgba(0,255,136,.7);filter:brightness(1.05)}
.akpp-req .note{color:#718096;font-size:12.5px}
.rv{opacity:0;transform:translateY(16px);transition:opacity .55s cubic-bezier(.2,.7,.2,1),transform .55s cubic-bezier(.2,.7,.2,1)}.rv.v{opacity:1;transform:none}
@media(max-width:760px){.akpp-req-grid{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}.rv{opacity:1;transform:none}}
</style>
<div class="wrap akpp-req">
  <h1>📄 Реквизиты оферты</h1>
  <p class="sub">Эти данные подставляются в договор‑оферту и форму сделки на вашем сайте. Заполните свои реквизиты — чужие данные мастера перестанут отображаться. Изменения применяются сразу после сохранения.</p>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('akpp_save_requisites'); ?>
    <input type="hidden" name="action" value="akpp_save_requisites">

    <div class="akpp-req-grp rv">
      <h2>👤 Исполнитель (ваши реквизиты)</h2>
      <div class="akpp-req-grid">
        <div class="fg full"><label>ФИО / название исполнителя</label><input name="akpp_company_name" value="<?php echo $g('akpp_company_name','Рытов Максим Александрович'); ?>"></div>
        <div class="fg"><label>ИНН</label><input name="akpp_company_inn" value="<?php echo $g('akpp_company_inn','[ИНН — впишите сюда]'); ?>"></div>
        <div class="fg"><label>Статус / режим налогообложения</label><input name="akpp_company_status" value="<?php echo $g('akpp_company_status','физическое лицо, плательщик налога на профессиональный доход (НПД, самозанятый)'); ?>"></div>
        <div class="fg"><label>Город (в шапке договора)</label><input name="akpp_company_city" value="<?php echo $g('akpp_company_city','г. Курган'); ?>"></div>
        <div class="fg"><label>Адрес</label><input name="akpp_company_address" value="<?php echo $g('akpp_company_address','г. Курган'); ?>"></div>
        <div class="fg"><label>Телефон</label><input name="akpp_company_phone" value="<?php echo $g('akpp_company_phone','+7 (963) 866-99-96'); ?>"></div>
        <div class="fg"><label>Email</label><input name="akpp_company_email" value="<?php echo $g('akpp_company_email','info@akpp45.ru'); ?>"></div>
        <div class="fg"><label>Сайт</label><input name="akpp_company_site" value="<?php echo $g('akpp_company_site','https://akpp45.ru'); ?>"></div>
        <div class="fg"><label>Версия оферты</label><input name="akpp_agreement_version" value="<?php echo $g('akpp_agreement_version','1.1'); ?>"></div>
        <div class="fg full"><label>Предмет услуг (подзаголовок договора)</label><textarea name="akpp_company_service_subject"><?php echo esc_textarea(get_option('akpp_company_service_subject','по оказанию услуг по ремонту автоматических коробок передач (АКПП)')); ?></textarea><div class="hint">Например: «по оказанию услуг по ремонту АКПП» или «по техническому обслуживанию автомобилей».</div></div>
      </div>
    </div>

    <div class="akpp-req-grp rv">
      <h2>📢 Объявление на Авито</h2>
      <div class="akpp-req-grid">
        <div class="fg full"><label>Ссылка на объявление</label><input name="akpp_avito_ad_url" value="<?php echo $g('akpp_avito_ad_url','https://www.avito.ru/kurgan/predlozheniya_uslug/remont_akpp_7991698408'); ?>"></div>
        <div class="fg"><label>Текст ссылки (slug)</label><input name="akpp_avito_ad_slug" value="<?php echo $g('akpp_avito_ad_slug','remont_akpp_7991698408'); ?>"></div>
        <div class="fg"><label>Номер объявления</label><input name="akpp_avito_ad_id" value="<?php echo $g('akpp_avito_ad_id','7991698408'); ?>"></div>
      </div>
      <div class="hint" style="margin-top:10px">Подключение самого аккаунта Avito (токены) — в разделе «🔌 Интеграции → ⚙️ Настройки Авито». Здесь — только ссылка на объявление, которая показывается в оферте и форме сделки.</div>
    </div>

    <div class="save">
      <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:16px;padding:24px;margin:0 0 22px;flex:1 1 100%;width:100%;">
    <h3 style="margin:0 0 6px;color:#fff;font-size:16px;">🗺 2ГИС</h3>
    <p style="margin:0 0 16px;color:#a0aec0;font-size:12.5px;">Ссылка на страницу вашей компании в 2ГИС. Показывается в контактах шаблонов сайта. Каждая лицензия вписывает свою.</p>
    <label style="display:block;color:#a0aec0;font-size:11.5px;font-weight:600;margin-bottom:6px;">ССЫЛКА НА СТРАНИЦУ 2ГИС</label>
    <input type="url" name="akpp_2gis_url" value="<?php echo esc_attr(get_option('akpp_2gis_url', '')); ?>" placeholder="https://2gis.ru/kurgan/firm/70000000000000000" style="width:100%;background:#0a0f1c;border:1px solid #2d3748;border-radius:9px;color:#fff;padding:11px 13px;font-size:13px;">
</div>
<div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:16px;padding:24px;margin:0 0 22px;flex:1 1 100%;width:100%;">
    <h3 style="margin:0 0 6px;color:#fff;font-size:16px;">📄 Тексты оферты и гарантии</h3>
    <p style="margin:0 0 16px;color:#a0aec0;font-size:12.5px;">Эти тексты выводятся на страницах «Оферта» и «Гарантия» и в блоке гарантии шаблонов. Каждая лицензия редактирует свои тексты — чужие не подтягиваются.</p>
    <label style="display:block;color:#a0aec0;font-size:11.5px;font-weight:600;margin-bottom:6px;">ГАРАНТИЯ — ЗАГОЛОВОК</label>
    <input type="text" name="akpp_warranty_title" value="<?php echo esc_attr(get_option('akpp_warranty_title', '')); ?>" placeholder="Гарантия и договор" style="width:100%;background:#0a0f1c;border:1px solid #2d3748;border-radius:9px;color:#fff;padding:11px 13px;font-size:13px;margin-bottom:14px;">
    <label style="display:block;color:#a0aec0;font-size:11.5px;font-weight:600;margin-bottom:6px;">ГАРАНТИЯ — ТЕКСТ</label>
    <textarea name="akpp_warranty_text" rows="4" style="width:100%;background:#0a0f1c;border:1px solid #2d3748;border-radius:9px;color:#fff;padding:12px;font-size:13px;line-height:1.6;"><?php echo esc_textarea(get_option('akpp_warranty_text', '')); ?></textarea>
</div>
<div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:16px;padding:24px;margin:0 0 22px;flex:1 1 100%;width:100%;">
    <h3 style="margin:0 0 6px;color:#fff;font-size:16px;">📝 Своя оферта (опционально)</h3>
    <p style="margin:0 0 16px;color:#a0aec0;font-size:12.5px;">Если оставить пустым — используется наша стандартная оферта с реквизитами выше. Если заполнить — будет использоваться <b>только ваша</b>, стандартная блокируется. Поддерживает HTML.</p>
    <label style="display:block;color:#a0aec0;font-size:11.5px;font-weight:600;margin-bottom:6px;">ВАША ОФЕРТА (HTML)</label>
    <textarea name="akpp_custom_agreement" rows="14" style="width:100%;background:#0a0f1c;border:1px solid #2d3748;border-radius:9px;color:#fff;padding:12px;font-size:13px;line-height:1.6;font-family:ui-monospace,monospace;"><?php echo esc_textarea(get_option('akpp_custom_agreement', '')); ?></textarea>
    <p style="margin:10px 0 0;color:#718096;font-size:11px;">💡 Если пустое — на /oferta/ и в сделках используется стандартная оферта с вашими реквизитами.</p>
</div>
<button type="submit" class="btn">💾 Сохранить реквизиты</button>
      <span class="note">Данные хранятся только в вашей базе и видны только вам.</span>
    </div>
  </form>
</div>
<script>
(function(){var io=new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){x.target.classList.add('v');io.unobserve(x.target);}});},{threshold:.1});
document.querySelectorAll('.akpp-req .rv').forEach(function(el){io.observe(el);});})();
</script>
