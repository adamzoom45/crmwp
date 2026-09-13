<?php if (!defined('ABSPATH')) exit;
if (!class_exists('AKPP_Tenant_Deployer') || !AKPP_Tenant_Deployer::is_master()) return;
?>
<style>
.akpp-dep-form{ display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:14px; margin:6px 0 16px; }
.akpp-dep-form label{ display:flex; flex-direction:column; gap:6px; color:#a0aec0; font-size:12px; font-weight:600; }
.akpp-dep-form input,.akpp-dep-form select{ background:#0a0f1c; border:1px solid #2d3748; border-radius:9px; color:#fff; padding:10px 13px; font-family:'Courier New',monospace; font-size:12.5px; transition:border-color .2s, box-shadow .2s; }
.akpp-dep-form input:focus,.akpp-dep-form select:focus{ outline:none; border-color:#00ff88; box-shadow:0 0 0 3px rgba(0,255,136,.13); }
.akpp-dep-section{ grid-column:1/-1; margin-top:10px; padding-top:12px; border-top:1px solid #2d3748; color:#41d2e2; font-size:11px; text-transform:uppercase; letter-spacing:.7px; font-weight:700; }
.akpp-dep-actions{ display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
.akpp-dep-state{ color:#f6ad55; font-size:12.5px; font-weight:600; }
.akpp-dep-log{ background:#0a0f1c; border:1px solid #2d3748; border-radius:12px; padding:14px; font-family:'Courier New',monospace; font-size:12px; color:#9ca3af; max-height:340px; overflow:auto; }
.akpp-dep-log .step{ display:flex; gap:10px; padding:5px 0; border-bottom:1px solid #1a1f2e; }
.akpp-dep-log .step .t{ color:#4a5568; min-width:60px; }
.akpp-dep-log .step.OK .s{ color:#00ff88; } .akpp-dep-log .step.FAIL .s{ color:#fc8181; }
.akpp-dep-log .step.OK .m{ color:#9ae6b4; } .akpp-dep-log .step.FAIL .m{ color:#fc8181; }
.akpp-dep-result{ margin-top:12px; padding:14px; border-radius:10px; font-size:13px; line-height:1.6; }
.akpp-dep-result.done{ background:rgba(0,255,136,.08); border:1px solid rgba(0,255,136,.3); color:#9ae6b4; }
.akpp-dep-result.failed{ background:rgba(252,129,129,.08); border:1px solid rgba(252,129,129,.3); color:#fc8181; }
.akpp-dep-gen{ background:#2d3748; color:#41d2e2; border:1px solid #4a5568; border-radius:7px; padding:6px 10px; cursor:pointer; font-size:12px; }
.akpp-dep-gen:hover{ border-color:#41d2e2; }
@media (prefers-reduced-motion:reduce){ .akpp-dep-form input,.akpp-dep-form select{ transition:none; } }
</style>
<div class="akpp-lic-card akpp-dep-block">
    <h2>🆕 Установить CRM на новый домен</h2>
    <p class="hint">Автоинсталл <b>полноценной CRM</b> (41 таблица, офлайн‑лицензия, реквизиты) на новый сайт. <b>Сначала создайте сайт в CloudPanel</b>: Sites → Add PHP Site (домен, PHP 8.4, пользователь) → Databases → Add Database (имя/юзер/пароль — запишите) → SSL → Let's Encrypt → Force HTTPS. Затем заполните форму данными из CloudPanel и нажмите «Установить». Скрипт проверит готовность сайта и БД, сам развернёт CRM и выпустит лицензию.</p>
    <div class="akpp-dep-form">
        <div class="akpp-dep-section">🌐 Сайт (из CloudPanel)</div>
        <label>Домен *<input type="text" id="dep-domain" placeholder="newclient.ru"></label>
        <label>Путь установки *<input type="text" id="dep-docroot" placeholder="/home/user/htdocs/newclient.ru"></label>
        <label>Системный пользователь *<input type="text" id="dep-siteuser" placeholder="user"></label>
        <div class="akpp-dep-section">🗄 База данных (CloudPanel → Databases)</div>
        <label>Хост БД<input type="text" id="dep-db-host" value="127.0.0.1"></label>
        <label>Имя БД *<input type="text" id="dep-db-name" placeholder="newclient_crm"></label>
        <label>Пользователь БД *<input type="text" id="dep-db-user" placeholder="newclient_user"></label>
        <label>Пароль БД *<input type="password" id="dep-db-pass" placeholder="••••••••"></label>
        <label>Префикс таблиц<input type="text" id="dep-db-prefix" value="wp_"></label>
        <div class="akpp-dep-section">👤 Администратор WordPress</div>
        <label>Логин админа<input type="text" id="dep-admin-user" value="admin"></label>
        <label>Пароль админа *<span style="display:flex;gap:6px"><input type="password" id="dep-admin-pass" placeholder="••••••••" style="flex:1"><button type="button" class="akpp-dep-gen" id="dep-gen-pass" title="Сгенерировать пароль">🎲</button></span></label>
        <label>Email админа<input type="email" id="dep-admin-email" placeholder="admin@newclient.ru"></label>
        <label>Название сайта<input type="text" id="dep-site-title" placeholder="Новый клиент — CRM"></label>
        <div class="akpp-dep-section">📜 Реквизиты оферты (данные арендатора)</div>
        <label>Исполнитель (ИП/ООО)<input type="text" id="dep-company-name" placeholder="ИП Иванов Иван Иванович"></label>
        <label>Город<input type="text" id="dep-company-city" placeholder="г. Москва"></label>
        <label>Сайт (авто из домена)<input type="text" id="dep-company-site" placeholder="https://newclient.ru"></label>
        <label>Avito ID (опционально)<input type="text" id="dep-avito" placeholder=""></label>
        <div class="akpp-dep-section">🔑 Лицензия</div>
        <label>План аренды<select id="dep-plan"><option value="12">1 год (12 мес)</option><option value="24">2 года (24 мес)</option><option value="36">3 года (36 мес)</option><option value="1">1 месяц (тест)</option><option value="1200">Безлимит (100 лет)</option></select></label>
        <label>Заметка (клиент)<input type="text" id="dep-note" placeholder="ИП Иванов · newclient.ru"></label>
    </div>
    <div class="akpp-dep-actions">
        <button type="button" id="dep-run" class="btn">🚀 Установить CRM на новый домен</button>
        <span id="dep-state" class="akpp-dep-state"></span>
    </div>
    <div id="dep-log" class="akpp-dep-log"><em>Пока пусто. Заполните форму и нажмите «Установить».</em></div>
</div>
