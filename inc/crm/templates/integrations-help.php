<?php if (!defined('ABSPATH')) exit; ?>
<style>
.akpp-help{ max-width:1000px; margin:20px 20px 60px 0; background:#0a0f1c; color:#e2e8f0; padding:30px; border-radius:16px; border:1px solid #2d3748; }
.akpp-help h1{ color:#00ff88; margin:0 0 6px; font-size:26px; }
.akpp-help .sub{ color:#a0aec0; margin:0 0 24px; }
.akpp-help h2{ color:#00ff88; margin:30px 0 12px; font-size:18px; border-left:3px solid #00ff88; padding-left:12px; }
.akpp-help h3{ color:#e2e8f0; margin:20px 0 8px; font-size:14px; }
.akpp-help p,.akpp-help li{ color:#cbd5e0; line-height:1.7; }
.akpp-help ul{ margin:8px 0 8px 20px; }
.akpp-help code{ background:#0a0f1c; border:1px solid #2d3748; color:#63b3ed; padding:2px 7px; border-radius:5px; font-size:12.5px; }
.akpp-help pre{ background:#0a0f1c; border:1px solid #2d3748; border-radius:10px; padding:16px; overflow-x:auto; color:#a0f0c0; font-size:12.5px; line-height:1.6; }
.akpp-help table{ width:100%; border-collapse:collapse; margin:10px 0; }
.akpp-help th,.akpp-help td{ padding:9px 12px; border-bottom:1px solid #2d3748; text-align:left; vertical-align:top; font-size:13px; }
.akpp-help th{ color:#a0aec0; text-transform:uppercase; font-size:11px; letter-spacing:.5px; }
.akpp-help td code{ color:#00ff88; }
.akpp-help .note{ background:rgba(99,179,237,.1); border:1px solid rgba(99,179,237,.35); border-radius:10px; padding:14px 18px; margin:14px 0; color:#cbd5e0; }
.akpp-help .warn{ background:rgba(245,181,68,.1); border:1px solid rgba(245,181,68,.35); border-radius:10px; padding:14px 18px; margin:14px 0; color:#fbd38d; }
.akpp-help .ok{ background:rgba(0,255,136,.1); border:1px solid rgba(0,255,136,.35); border-radius:10px; padding:14px 18px; margin:14px 0; color:#9ae6b4; }
.akpp-help .back{ display:inline-block; margin-bottom:18px; color:#a0aec0; text-decoration:none; font-weight:600; }
.akpp-help .back:hover{ color:#00ff88; }

    /* === печать / сохранение в PDF: только чистый текст инструкции === */
    @media print {
      #wpadminbar, #adminmenuwrap, #adminmenumain, #adminmenuback, #adminmenu, #collapse-menu,
      #screen-meta, #screen-meta-links, .notice, .update-nag, #wpfooter, .no-print { display:none !important; }
      #wpcontent, #wpbody, #wpbody-content { margin:0 !important; padding:0 !important; }
      body, #wpwrap { background:#fff !important; }
      .akpp-help { background:#fff !important; color:#000 !important; border:none !important; max-width:100% !important; margin:0 !important; padding:8px 0 !important; box-shadow:none !important; }
      .akpp-help h1 { color:#000 !important; font-size:22px !important; }
      .akpp-help h2 { color:#000 !important; border-left:3px solid #000 !important; page-break-after:avoid; }
      .akpp-help h3 { color:#000 !important; page-break-after:avoid; }
      .akpp-help p, .akpp-help li, .akpp-help td, .akpp-help th { color:#222 !important; }
      .akpp-help th { color:#000 !important; }
      .akpp-help pre { background:#f5f5f5 !important; color:#000 !important; border:1px solid #ccc !important; white-space:pre-wrap !important; word-break:break-word !important; page-break-inside:avoid; }
      .akpp-help code { background:#f0f0f0 !important; color:#000 !important; border:1px solid #ddd !important; }
      .akpp-help table { page-break-inside:auto; width:100% !important; }
      .akpp-help tr { page-break-inside:avoid; }
      .akpp-help .note, .akpp-help .warn, .akpp-help .ok { background:#f5f5f5 !important; color:#000 !important; border:1px solid #999 !important; page-break-inside:avoid; }
      .akpp-help a { color:#000 !important; text-decoration:none !important; }
      .akpp-help .back { display:none !important; }
      .akpp-help, .akpp-help pre, .akpp-help table, .akpp-help .note, .akpp-help .warn, .akpp-help .ok { max-width:100% !important; }
      .akpp-help pre { white-space:pre-wrap !important; word-break:break-word !important; overflow:visible !important; }
      .akpp-help code { word-break:break-word !important; }
      .akpp-help td, .akpp-help th { word-break:break-word !important; }
      .akpp-help table { width:100% !important; border-collapse:collapse !important; }
      .akpp-help h2, .akpp-help h3 { page-break-after:avoid !important; }
      .akpp-help pre, .akpp-help table, .akpp-help .note, .akpp-help .warn, .akpp-help .ok { page-break-inside:avoid !important; }
      @page { size:A4; margin:12mm; }
    }
</style>
<div class="wrap akpp-help">
    <a href="<?php echo esc_url($exch); ?>" class="back">← К обмену 1С</a>
    <h1>📖 Инструкция: интеграция с 1С</h1>
    <div class="no-print" style="margin:16px 0 4px">
        <?php if (!empty($print_mode)): ?>
            <button type="button" onclick="window.print()" style="background:#00ff88;color:#08120c;border:none;padding:13px 26px;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer;letter-spacing:.2px">🖨 Печать / Сохранить в PDF</button>
            <span style="color:#a0aec0;font-size:12px;margin-left:12px">В диалоге печати выберите «Сохранить как PDF» — документ полный, без меню сайта.</span>
        <?php else: ?>
            <a href="<?php echo esc_url(add_query_arg('print', '1')); ?>" target="_blank" rel="noopener" style="display:inline-block;background:#00ff88;color:#08120c;text-decoration:none;padding:13px 26px;border-radius:10px;font-weight:700;font-size:14px;letter-spacing:.2px">📄 Открыть версию для PDF</a>
            <span style="color:#a0aec0;font-size:12px;margin-left:12px">Откроется чистое окно без админ‑меню — там кнопка печати.</span>
        <?php endif; ?>
    </div>
    <p class="sub">Универсальный обмен данными через XML формата <code>DataExchange</code>. Модуль домено‑независим — работает на любом домене без правок кода.</p>

    <div class="ok"><strong>Архитектура.</strong> Сайт отдаёт и принимает XML по HTTP‑эндпоинтам. 1С (или любая другая система) ходит к этим эндпоинтам по токену. Формат общий для обеих сторон — стыкуется один в один со схемой из спецификации обмена.</div>

    <h2>1. Эндпоинты (текущий сайт)</h2>
    <table>
        <tr><th>Операция</th><th>Метод</th><th>URL</th></tr>
        <tr><td>Выгрузка каталога</td><td><code>GET</code></td><td><code><?php echo esc_html($export_url); ?></code></td></tr>
        <tr><td>Импорт каталога</td><td><code>POST</code> (тело = XML или multipart поле <code>xml</code>)</td><td><code><?php echo esc_html($import_url); ?></code></td></tr>
    </table>
    <p>Базовый URL сайта: <code><?php echo esc_html($base); ?></code>. На другом домене эндпоинты будут на том домене автоматически (см. раздел 6).</p>

    <h2>2. Авторизация</h2>
    <p>Токен передаётся <strong>одним из способов</strong>:</p>
    <ul>
        <li>параметром запроса: <code>?token=<?php echo esc_html($token); ?></code></li>
        <li>заголовком: <code>X-1C-Token: <?php echo esc_html($token); ?></code></li>
    </ul>
    <div class="warn"><strong>Токен = секрет.</strong> Не публикуйте его, не кладите в публичные репозитории. Передавайте по HTTPS. При компрометации — «Перегенерировать» на странице обмена; старый токен перестаёт работать мгновенно.</div>

    <h2>3. Формат XML (каталог товаров)</h2>
    <pre>&lt;?xml version="1.0" encoding="UTF-8"?&gt;
&lt;DataExchange Direction="Магазин запчастей"&gt;
  &lt;Products&gt;
    &lt;Product UID="product-3"&gt;
      &lt;Name&gt;Промывка АКПП Liqui Moly&lt;/Name&gt;
      &lt;SKU&gt;AKPP-FLUSH-001&lt;/SKU&gt;
      &lt;PurchasePrice&gt;1200.00&lt;/PurchasePrice&gt;
      &lt;RetailPrice&gt;2500.00&lt;/RetailPrice&gt;
      &lt;Stock&gt;8&lt;/Stock&gt;
      &lt;Category&gt;parts&lt;/Category&gt;
      &lt;Condition&gt;new&lt;/Condition&gt;
      &lt;IsActive&gt;1&lt;/IsActive&gt;
    &lt;/Product&gt;
  &lt;/Products&gt;
&lt;/DataExchange&gt;</pre>
    <table>
        <tr><th>Поле</th><th>Тип</th><th>Описание</th></tr>
        <tr><td><code>UID</code></td><td>атрибут</td><td>Уникальный ключ объекта. У нас — <code>product-{id}</code>. 1С может класть свой GUID и сопоставлять по <code>SKU</code>.</td></tr>
        <tr><td><code>Name</code></td><td>строка</td><td>Название товара.</td></tr>
        <tr><td><code>SKU</code></td><td>строка</td><td>Артикул — <strong>ключ сопоставления</strong> при импорте (по нему ищем существующий товар).</td></tr>
        <tr><td><code>PurchasePrice</code></td><td>decimal</td><td>Закупочная цена (себестоимость). Точка — разделитель.</td></tr>
        <tr><td><code>RetailPrice</code></td><td>decimal</td><td>Розничная цена продажи.</td></tr>
        <tr><td><code>Stock</code></td><td>int</td><td>Остаток на складе.</td></tr>
        <tr><td><code>Category</code></td><td>строка</td><td>Slug категории (akpp / oils / engine / suspension / parts).</td></tr>
        <tr><td><code>Condition</code></td><td>строка</td><td>new / used / refurbished.</td></tr>
        <tr><td><code>IsActive</code></td><td>0/1</td><td>Показывать ли на витрине.</td></tr>
    </table>

    <h2>4. Примеры запросов</h2>
    <h3>curl — выгрузка каталога</h3>
    <pre>curl -H "X-1C-Token: YOUR_TOKEN" "https://<?php echo esc_html(parse_url($base, PHP_URL_HOST)); ?>/wp-admin/admin-ajax.php?action=akpp_1c_export_products"</pre>
    <h3>curl — импорт каталога</h3>
    <pre>curl -X POST -H "X-1C-Token: YOUR_TOKEN" \
  -H "Content-Type: application/xml" \
  --data-binary @products.xml \
  "https://<?php echo esc_html(parse_url($base, PHP_URL_HOST)); ?>/wp-admin/admin-ajax.php?action=akpp_1c_import_products"</pre>
    <h3>1С (встроенный язык) — выгрузка</h3>
    <pre>Соединение = Новый HTTPСоединение("<?php echo esc_html(parse_url($base, PHP_URL_HOST)); ?>", 443, , , , Новый ЗащищенноеСоединениеOpenSSL);
Заголовки = Новый Соответствие;
Заголовки.Вставить("X-1C-Token", "&lt;ТОКЕН&gt;");
Запрос = Новый HTTPЗапрос("/wp-admin/admin-ajax.php?action=akpp_1c_export_products", Заголовки);
Ответ = Соединение.Получить(Запрос);
ТекстXML = Ответ.ПолучитьТелоКакСтроку();
// далее разбор ТекстXML по схеме DataExchange</pre>
    <h3>1С (встроенный язык) — импорт</h3>
    <pre>Соединение = Новый HTTPСоединение("&lt;ХОСТ&gt;", 443, , , , Новый ЗащищенноеСоединениеOpenSSL);
Заголовки = Новый Соответствие;
Заголовки.Вставить("X-1C-Token", "&lt;ТОКЕН&gt;");
Заголовки.Вставить("Content-Type", "application/xml");
Запрос = Новый HTTPЗапрос("/wp-admin/admin-ajax.php?action=akpp_1c_import_products", Заголовки);
Запрос.УстановитьТелоИзСтроки(ТекстXML);
Ответ = Соединение.Получить(Запрос);  // для POST: Соединение.ОтправитьДляОбработки(Запрос, ...)</pre>

    <h2>5. CORS и работа с другого домена</h2>
    <p>Обмен <strong>сервер‑сервер</strong> (1С → сайт) не требует CORS — это не браузер. CORS нужен, когда обмен идёт из <strong>браузерного клиента на другом домене</strong> (например, внешняя админка/CRM‑панель): браузер блокирует запрос без разрешающих заголовков.</p>
    <ul>
        <li>На странице «1С: обмен» есть поле <strong>«Разрешённые домены (CORS)»</strong>. Укажите через запятую домены, которым разрешён обмен, или <code>*</code> для всех.</li>
        <li>Модуль отвечает на <code>OPTIONS</code> (preflight) кодом 204 с заголовками <code>Access-Control-Allow-Origin / Allow-Headers (X-1C-Token, Content-Type) / Allow-Methods</code>.</li>
        <li>Поле пусто → CORS не выставляется (только same‑origin и server‑to‑server). Это безопасно по умолчанию.</li>
    </ul>
    <div class="note"><strong>HTTPS обязателен.</strong> Токен в заголовке/параметре передаётся открытым текстом внутри запроса — только HTTPS защищает его в пути. На всех продакшен‑доменах держите валидный сертификат.</div>

    <h2>6. Будущие CRM‑реализации на других доменах</h2>
    <p>Модуль спроектирован так, чтобы копироваться вместе с темой на любой домен без правок кода:</p>
    <ul>
        <li><strong>Эндпоинты абсолютные относительно текущего сайта</strong> (<code>admin_url()</code>) — на новом домене URL станут доменными автоматически.</li>
        <li><strong>Токен создаётся сам</strong> при первом запуске (<code>install()</code>) и хранится в опциях WP нового сайта — свой для каждого домена.</li>
        <li><strong>Таблица лога</strong> <code>akpp_1c_log</code> создаётся автоматически при установке.</li>
        <li>На стороне 1С достаточно сменить <strong>базовый URL</strong> (хост) на нужный домен — формат XML и имена эндпоинтов идентичны.</li>
    </ul>
    <div class="ok"><strong>Чек‑лист запуска обмена на новом домене:</strong> 1) тема с модулем развёрнута; 2) открыть «Интеграции → 1С: обмен» — токен уже есть; 3) при необходимости заполнить CORS‑whitelist; 4) передать программисту 1С новый хост + токен; 5) проверить выгрузку кнопкой «Скачать каталог (XML)».</div>

    <h2>7. Безопасность</h2>
    <ul>
        <li>Все эндпоинты проверяют токен (<code>hash_equals</code> — защита от timing‑атак).</li>
        <li>Импорт санитизирует все поля (<code>sanitize_text_field</code>, <code>floatval</code>, <code>intval</code>), XML разбирается с <code>libxml_use_internal_errors</code>.</li>
        <li>Каждый обмен пишется в лог (операция, направление, статус, число записей) — видно на странице обмена.</li>
        <li>Ротация токена одной кнопкой; старое значение инвалидируется сразу.</li>
    </ul>

    <h2>Финансы (реализовано)</h2>
    <p>Эндпоинты: выгрузка <code>GET …?action=akpp_1c_export_finances</code>, импорт статусов/оплат <code>POST …?action=akpp_1c_import_finances</code>. Формат:</p>
    <pre>&lt;DataExchange Direction="Финансы"&gt;
  &lt;Finances&gt;
    &lt;Deals&gt;
      &lt;Deal UID="deal-74"&gt;
        &lt;ClientId&gt;1&lt;/ClientId&gt;
        &lt;Vehicle&gt;Toyota Camry 2010&lt;/Vehicle&gt;
        &lt;Status&gt;in_work&lt;/Status&gt;
        &lt;WorkCost&gt;8000.00&lt;/WorkCost&gt;
        &lt;PartsTotal&gt;0.00&lt;/PartsTotal&gt;
        &lt;Total&gt;24500.00&lt;/Total&gt;
        &lt;PaymentAmount&gt;0.00&lt;/PaymentAmount&gt;
      &lt;/Deal&gt;
    &lt;/Deals&gt;
    &lt;Orders&gt;
      &lt;Order UID="order-1"&gt;
        &lt;OrderNumber&gt;AKPP-20260726-5450&lt;/OrderNumber&gt;
        &lt;Total&gt;2350.00&lt;/Total&gt;
        &lt;PaymentStatus&gt;pending&lt;/PaymentStatus&gt;
        &lt;Status&gt;new&lt;/Status&gt;
        &lt;Items&gt;
          &lt;Item&gt;&lt;SKU&gt;…&lt;/SKU&gt;&lt;Qty&gt;1&lt;/Qty&gt;&lt;Price&gt;…&lt;/Price&gt;&lt;Total&gt;…&lt;/Total&gt;&lt;/Item&gt;
        &lt;/Items&gt;
      &lt;/Order&gt;
    &lt;/Orders&gt;
    &lt;Invoices&gt;
      &lt;Invoice UID="inv-order-1"&gt;&lt;Ref&gt;order-1&lt;/Ref&gt;&lt;Amount&gt;2350.00&lt;/Amount&gt;&lt;Method&gt;cash&lt;/Method&gt;&lt;/Invoice&gt;
    &lt;/Invoices&gt;
  &lt;/Finances&gt;
&lt;/DataExchange&gt;</pre>
    <div class="note"><strong>Импорт статусов из 1С.</strong> Чтобы 1С сообщила «сделка закрыта/оплачена», она присылает тот же XML, где у <code>&lt;Deal UID="deal-N"&gt;</code> заполнены <code>Status</code>/<code>PaymentAmount</code>, а у <code>&lt;Order UID="order-N"&gt;</code> — <code>Status</code>/<code>PaymentStatus</code>. Мы обновляем записи по UID (N = наш id).</div>
<h2>8. План расширения формата</h2>
    <p>Следующими этапами в тот же <code>DataExchange</code> добавятся блоки:</p>
    <ul>
        <li><code>&lt;Finances&gt;</code> — <strong>реализовано выше</strong>: сделки, заказы, оплаты (экспорт + импорт статусов).</li>
        <li><code>&lt;Employees&gt;</code> и <code>&lt;TimeRecord&gt;</code> — сотрудники и табель.</li>
        <li>Импорт статусов/оплат из 1С (обратное направление) — чтобы 1С могла прислать «оплачено/закрыто».</li>
        <li>Автообмен по расписанию (cron раз в час).</li>
    </ul>
</div>
