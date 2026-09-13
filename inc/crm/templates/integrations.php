<?php if (!defined('ABSPATH')) exit; ?>
<style>
.akpp-integrations{ max-width:1100px; margin:20px 20px 60px 0; background:#0a0f1c; color:#e2e8f0; padding:30px; border-radius:16px; border:1px solid #2d3748; }
.akpp-integrations h1{ color:#00ff88; margin:0 0 6px; font-size:26px; }
.akpp-int-sub{ color:#a0aec0; margin:0 0 24px; }
.akpp-int-notice{ background:rgba(0,255,136,.12); border:1px solid rgba(0,255,136,.4); color:#00ff88; padding:12px 16px; border-radius:10px; margin-bottom:20px; }
.akpp-int-card{ background:#1a1f2e; border:1px solid #2d3748; border-radius:14px; padding:24px; }
.akpp-int-card h2{ color:#00ff88; margin:0 0 10px; font-size:19px; }
.akpp-int-card h3{ color:#e2e8f0; margin:24px 0 10px; font-size:14px; text-transform:uppercase; letter-spacing:.5px; border-left:3px solid #00ff88; padding-left:10px; }
.akpp-int-card p{ color:#a0aec0; line-height:1.6; }
.akpp-int-token{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.akpp-int-token code{ background:#0a0f1c; border:1px solid #2d3748; color:#00ff88; padding:10px 14px; border-radius:8px; font-size:13px; word-break:break-all; flex:1; min-width:260px; }
.akpp-int-endpoints{ width:100%; border-collapse:collapse; }
.akpp-int-endpoints td{ padding:10px 12px; border-bottom:1px solid #2d3748; vertical-align:top; }
.akpp-int-endpoints td:first-child{ color:#a0aec0; white-space:nowrap; width:240px; }
.akpp-int-endpoints code{ background:#0a0f1c; border:1px solid #2d3748; color:#63b3ed; padding:6px 10px; border-radius:6px; font-size:12px; word-break:break-all; display:inline-block; }
.akpp-int-hint{ color:#718096; font-size:12.5px; }
.akpp-int-log{ background:transparent; }
.akpp-int-log th{ color:#a0aec0; text-transform:uppercase; font-size:11px; letter-spacing:.5px; }
.akpp-int-log td{ color:#e2e8f0; }
.akpp-integrations .button{ background:#2d3748; color:#fff; border:1px solid #4a5568; border-radius:8px; padding:8px 16px; cursor:pointer; }
.akpp-integrations .button-primary{ background:#00ff88; color:#08120c; border-color:#00ff88; font-weight:700; text-decoration:none; display:inline-block; }
.akpp-integrations input[type=file]{ color:#a0aec0; }
</style>
<div class="wrap akpp-integrations">
    <h1>🔗 Интеграции</h1>
    <p class="akpp-int-sub">Управление обменом данными с внешними системами</p>

    <?php if (isset($_GET['regen'])): ?><div class="akpp-int-notice">✅ Токен перегенерирован. Обновите его на стороне 1С.</div><?php endif; ?>

    <div class="akpp-int-card">
        <h2>1С: Универсальный обмен (XML)</h2>
        <p>Обмен каталогом товаров (закуп/розница, остатки) через XML формата <code>DataExchange</code>. Настройте соединение на стороне 1С, используя токен и эндпоинты ниже. Финансы и табель подключим следующими этапами.</p>

        <h3>🔑 Токен авторизации</h3>
        <div class="akpp-int-token">
            <code id="akpp1c-token"><?php echo esc_html($token); ?></code>
            <button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById('akpp1c-token').textContent); this.textContent='✓ Скопировано';">Копировать</button>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline" onsubmit="return confirm('Перегенерировать токен? Старый перестанет работать на стороне 1С.');">
                <?php wp_nonce_field('akpp_1c_regen'); ?>
                <input type="hidden" name="action" value="akpp_1c_regen_token">
                <button type="submit" class="button">Перегенерировать</button>
            </form>
        </div>

        <h3>🔗 Эндпоинты (для программиста 1С)</h3>
        <table class="akpp-int-endpoints">
            <tr><td>Выгрузка каталога (GET)</td><td><code><?php echo esc_html($export_url); ?></code></td></tr>
            <tr><td>Импорт каталога (POST, тело = XML)</td><td><code><?php echo esc_html($import_url); ?></code></td></tr>
        </table>
        <p class="akpp-int-hint">Формат: корень <code>&lt;DataExchange Direction="Магазин запчастей"&gt;</code> → <code>&lt;Products&gt;&lt;Product UID="product-N"&gt;</code> с полями Name, SKU, PurchasePrice, RetailPrice, Stock, Category, Condition, IsActive. Авторизация: параметр <code>token</code> или заголовок <code>X-1C-Token</code>.</p>

        <h3>📤 Быстрая выгрузка</h3>
        <a href="<?php echo esc_url($export_url); ?>" target="_blank" class="button button-primary">Скачать каталог (XML)</a>

        <h3>📥 Импорт каталога из 1С</h3>
        <form method="post" action="<?php echo esc_url($import_url); ?>" enctype="multipart/form-data">
            <input type="file" name="xml" accept=".xml,application/xml,text/xml" required>
            <button type="submit" class="button">Импортировать</button>
        </form>

        <h3>💰 Финансы (сделки + заказы + оплаты)</h3>
        <p class="akpp-int-hint">Выгрузка сделок автосервиса, заказов магазина (с позициями) и оплат. Импорт статусов и оплат из 1С — по UID <code>deal-N</code> / <code>order-N</code>.</p>
        <a href="<?php echo esc_url(add_query_arg(['action' => 'akpp_1c_export_finances', 'token' => $token], admin_url('admin-ajax.php'))); ?>" target="_blank" class="button button-primary">Скачать финансы (XML)</a>
        <form method="post" action="<?php echo esc_url(add_query_arg(['action' => 'akpp_1c_import_finances', 'token' => $token], admin_url('admin-ajax.php'))); ?>" enctype="multipart/form-data" style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <input type="file" name="xml" accept=".xml,application/xml,text/xml" required>
            <button type="submit" class="button">Импортировать статусы/оплаты</button>
        </form>
<h3>🌐 Доступ с других доменов (CORS)</h3>
        <p class="akpp-int-hint">Для обмена сервер‑сервер (1С → сайт) CORS не нужен. Заполняйте, только если обмен идёт из браузера на другом домене. Через запятую — разрешённые домены (например <code>https://crm.example.com</code>), или <code>*</code> для всех. Пусто = только same‑origin.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <?php wp_nonce_field('akpp_1c_save_origins'); ?>
            <input type="hidden" name="action" value="akpp_1c_save_origins">
            <input type="text" name="allowed_origins" value="<?php echo esc_attr($origins ?? ''); ?>" placeholder="https://crm.example.com, *" style="flex:1;min-width:280px;background:#0a0f1c;border:1px solid #2d3748;border-radius:8px;color:#fff;padding:10px 14px">
            <button type="submit" class="button">Сохранить домены</button>
        </form>

        <h3>📖 Подробная инструкция</h3>
        <p class="akpp-int-hint">Формат XML, примеры curl и кода 1С, настройка обмена с другого домена и чек‑лист для будущих CRM‑реализаций.</p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=akpp-crm-1c-help')); ?>" class="button button-primary">Открыть инструкцию 1С →</a>
<h3>📋 Лог обменов</h3>
        <?php if (empty($logs)): ?>
            <p class="akpp-int-hint">Обменов пока не было.</p>
        <?php else: ?>
            <table class="widefat striped akpp-int-log">
                <thead><tr><th>Дата</th><th>Операция</th><th>Направление</th><th>Статус</th><th>Записей</th><th>Детали</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><?php echo esc_html($l->created_at); ?></td>
                        <td><?php echo esc_html($l->operation); ?></td>
                        <td><?php echo $l->direction === 'out' ? '📤 выгрузка' : '📥 импорт'; ?></td>
                        <td><?php echo $l->status === 'ok' ? '<span style="color:#00ff88">✅ ok</span>' : '<span style="color:#fc8181">❌ error</span>'; ?></td>
                        <td><?php echo (int) $l->records; ?></td>
                        <td><?php echo esc_html($l->details); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
