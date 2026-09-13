<?php
/**
 * Админка магазина АКПП45
 * Управление товарами и заказами
 * v2.0 — с автогенерацией SKU
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$products_table = $wpdb->prefix . 'akpp_shop_products';
$orders_table = $wpdb->prefix . 'akpp_shop_orders';
$categories_table = $wpdb->prefix . 'akpp_categories';

// Статистика
$total_products = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$products_table} WHERE is_active = 1");
$total_orders = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$orders_table}");
$pending_orders = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$orders_table} WHERE status = 'new'");
$revenue_month = (float)$wpdb->get_var("SELECT COALESCE(SUM(total), 0) FROM {$orders_table} WHERE payment_status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");

$active_tab = sanitize_text_field($_GET['tab'] ?? 'products');

// Обработка действий
$message = '';
$message_type = 'success';

if (isset($_POST['shop_action'])) {
    check_admin_referer('akpp45_nonce', 'nonce');
    
    $action = sanitize_text_field($_POST['shop_action']);
    
    if ($action === 'save_product') {
        $id = intval($_POST['id'] ?? 0);
        
        // ✅ АВТОГЕНЕРАЦИЯ SKU если не указан
        $sku = strtoupper(sanitize_text_field($_POST['sku'] ?? ''));
        if (empty($sku)) {
            $sku = 'AKPP-' . strtoupper(substr(uniqid(), -6)) . '-' . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
            // Проверка уникальности
            while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$products_table} WHERE sku = %s", $sku))) {
                $sku = 'AKPP-' . strtoupper(substr(uniqid(), -6)) . '-' . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
            }
        }
        
        $data = [
            'part_id' => intval($_POST['part_id'] ?? 0),
            'sku' => $sku,
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? 'parts'),
            'condition_type' => sanitize_text_field($_POST['condition_type'] ?? 'new'),
            'quality_grade' => sanitize_text_field($_POST['quality_grade'] ?? 'A'),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'price' => floatval($_POST['price'] ?? 0),
            'old_price' => floatval($_POST['old_price'] ?? 0),
              'purchase_price' => floatval($_POST['purchase_price'] ?? 0),
            'stock' => intval($_POST['stock'] ?? 0),
            'is_active' => intval($_POST['is_active'] ?? 1),
            'is_featured' => intval($_POST['is_featured'] ?? 0),
            'updated_at' => current_time('mysql'),
          ];
          // Фото товара: загрузка в медиабиблиотеку + сохранение URL в images (JSON)
          $existing_images = ($id > 0) ? (string)($wpdb->get_var($wpdb->prepare("SELECT images FROM {$products_table} WHERE id = %d", $id)) ?: '[]') : '[]';
          $img_arr = json_decode($existing_images, true); if (!is_array($img_arr)) $img_arr = [];
          if (!empty($_FILES['product_image']['name'])) {
              require_once ABSPATH . 'wp-admin/includes/file.php';
              require_once ABSPATH . 'wp-admin/includes/image.php';
              require_once ABSPATH . 'wp-admin/includes/media.php';
              $up = wp_handle_upload($_FILES['product_image'], ['test_form' => false]);
              if ($up && empty($up['error'])) { $img_arr[] = esc_url_raw($up['url']); }
          }
          $data['images'] = json_encode(array_values($img_arr));
        
        if (empty($data['name'])) {
            $message = '❌ Заполните название товара';
            $message_type = 'error';
        } else {
            if ($id > 0) {
                $wpdb->update($products_table, $data, ['id' => $id]);
                $message = '✅ Товар обновлён (SKU: ' . $sku . ')';
            } else {
                $data['created_at'] = current_time('mysql');
                $wpdb->insert($products_table, $data);
                $message = '✅ Товар создан (SKU: ' . $sku . ')';
            }
        }
    } elseif ($action === 'delete_product') {
        $id = intval($_POST['id'] ?? 0);
        $wpdb->delete($products_table, ['id' => $id]);
        $message = '✅ Товар удалён';
    } elseif ($action === 'update_order_status') {
        $order_id = intval($_POST['order_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $wpdb->update($orders_table, 
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $order_id]
        );
        $message = '✅ Статус заказа обновлён';
    } elseif ($action === 'delete_order') {
        $order_id = intval($_POST['order_id'] ?? 0);
        $wpdb->delete($orders_table, ['id' => $order_id]);
        $message = '✅ Заказ удалён';
    }
}

// Получаем данные
$products = $wpdb->get_results("SELECT * FROM {$products_table} ORDER BY created_at DESC LIMIT 100");
$orders = $wpdb->get_results("SELECT * FROM {$orders_table} ORDER BY created_at DESC LIMIT 50");
$categories = $wpdb->get_results("SELECT * FROM {$categories_table} WHERE is_active = 1 AND scope IN ('shop','both') ORDER BY sort_order ASC");

$edit_product = null;
if (isset($_GET['edit_product'])) {
    $edit_id = intval($_GET['edit_product']);
    $edit_product = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$products_table} WHERE id = %d", $edit_id));
}

$view_order = null;
$order_items = [];
if (isset($_GET['view_order'])) {
    $order_id = intval($_GET['view_order']);
    $view_order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$orders_table} WHERE id = %d", $order_id));
    $order_items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}akpp_shop_order_items WHERE order_id = %d", $order_id));
}
?>

<div class="wrap akpp-crm-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="color: #00ff88; border-left: 4px solid #00ff88; padding-left: 15px; margin: 0;">
            🛒 Магазин АКПП45
        </h1>
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo esc_url(home_url('/shop/')); ?>" target="_blank" class="button" style="background: #2d3748; color: #fff; border: 1px solid #4a5568;">
                🔗 Открыть магазин ↗
            </a>
            <?php if ($active_tab === 'products' && !$edit_product): ?>
            <button type="button" class="button button-primary" onclick="document.getElementById('product-form-modal').style.display='flex'; generateSku();">
                 Добавить товар
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="notice notice-<?php echo $message_type; ?> is-dismissible" style="background: <?php echo $message_type === 'success' ? '#00ff8822' : '#fc818122'; ?>; border-left: 4px solid <?php echo $message_type === 'success' ? '#00ff88' : '#fc8181'; ?>; padding: 15px; margin-bottom: 20px; border-radius: 6px;">
        <p><?php echo esc_html($message); ?></p>
    </div>
    <?php endif; ?>

    <!-- Вкладки -->
    <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #2d3748; padding-bottom: 10px;">
        <a href="?page=akpp-crm-shop&tab=products" style="padding: 10px 20px; background: <?php echo $active_tab === 'products' ? '#00ff88' : '#2d3748'; ?>; color: <?php echo $active_tab === 'products' ? '#1a1f2e' : '#fff'; ?>; border-radius: 6px; text-decoration: none; font-weight: 600;">
            📦 Товары (<?php echo $total_products; ?>)
        </a>
        <a href="?page=akpp-crm-shop&tab=orders" style="padding: 10px 20px; background: <?php echo $active_tab === 'orders' ? '#00ff88' : '#2d3748'; ?>; color: <?php echo $active_tab === 'orders' ? '#1a1f2e' : '#fff'; ?>; border-radius: 6px; text-decoration: none; font-weight: 600;">
            📋 Заказы (<?php echo $total_orders; ?>)
            <?php if ($pending_orders > 0): ?>
            <span style="background: #fc8181; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 5px;"><?php echo $pending_orders; ?></span>
            <?php endif; ?>
        </a>
        <a href="?page=akpp-crm-shop&tab=stats" style="padding: 10px 20px; background: <?php echo $active_tab === 'stats' ? '#00ff88' : '#2d3748'; ?>; color: <?php echo $active_tab === 'stats' ? '#1a1f2e' : '#fff'; ?>; border-radius: 6px; text-decoration: none; font-weight: 600;">
            📊 Статистика
        </a>
        <a href="?page=akpp-crm-shop&tab=settings" style="padding: 10px 20px; background: <?php echo $active_tab === 'settings' ? '#00ff88' : '#2d3748'; ?>; color: <?php echo $active_tab === 'settings' ? '#1a1f2e' : '#fff'; ?>; border-radius: 6px; text-decoration: none; font-weight: 600;">⚙️ Настройки</a>
    </div>

    <?php if ($active_tab === 'settings'): /* akpp-shop-settings-tab */ ?>
    <div style="margin-top:20px;">
        <?php if (class_exists('AKPP_Shop_Settings')) { AKPP_Shop_Settings::get_instance()->render_page(); } else { echo '<p style="color:#fc8181">❌ Класс настроек магазина не загружен.</p>'; } ?>
    </div>
<?php endif; ?>
<?php if ($active_tab === 'products'): ?>
        <!-- СТАТИСТИКА -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
            <div style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 20px; text-align: center;">
                <div style="font-size: 32px; font-weight: 700; color: #00ff88;"><?php echo $total_products; ?></div>
                <div style="color: #a0aec0; font-size: 13px; text-transform: uppercase;">Активных товаров</div>
            </div>
            <div style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 20px; text-align: center;">
                <div style="font-size: 32px; font-weight: 700; color: #63b3ed;"><?php echo count($categories); ?></div>
                <div style="color: #a0aec0; font-size: 13px; text-transform: uppercase;">Категорий</div>
            </div>
        </div>

        <!-- ТАБЛИЦА ТОВАРОВ -->
        <div style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 20px;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Артикул</th>
                        <th>Название</th>
                        <th>Категория</th>
                        <th>Состояние</th>
                        <th>Цена</th>
                        <th>Остаток</th>
                        <th>Статус</th>
                        <th style="width: 150px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: #718096;">
                            Товаров пока нет. Добавьте первый товар.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo intval($product->id); ?></td>
                        <td><code style="background:#2d3748;padding:4px 8px;border-radius:4px;color:#00ff88;"><?php echo esc_html($product->sku); ?></code></td>
                        <td>
                            <strong><?php echo esc_html($product->name); ?></strong>
                            <?php if ($product->is_featured): ?>
                            <span style="color: #ffd700;">⭐</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($product->category); ?></td>
                        <td>
                            <span style="display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; background: <?php echo $product->condition_type === 'new' ? '#00ff8822' : '#f6ad5522'; ?>; color: <?php echo $product->condition_type === 'new' ? '#00ff88' : '#f6ad55'; ?>;">
                                <?php 
                                $conditions = ['new' => 'Новый', 'used' => 'Б/У', 'refurbished' => 'Восст.'];
                                echo $conditions[$product->condition_type] ?? $product->condition_type;
                                ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color: #00ff88;"><?php echo number_format($product->price, 0, ',', ' '); ?> ₽</strong>
                            <?php if ($product->old_price > 0): ?>
                            <br><small style="color: #718096; text-decoration: line-through;"><?php echo number_format($product->old_price, 0, ',', ' '); ?> ₽</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; background: <?php echo $product->stock > 10 ? '#00ff8822' : ($product->stock > 0 ? '#f6ad5522' : '#fc818122'); ?>; color: <?php echo $product->stock > 10 ? '#00ff88' : ($product->stock > 0 ? '#f6ad55' : '#fc8181'); ?>;">
                                <?php echo $product->stock; ?> шт
                            </span>
                        </td>
                        <td><?php echo $product->is_active ? '✅' : '❌'; ?></td>
                        <td>
                            <a href="?page=akpp-crm-shop&tab=products&edit_product=<?php echo $product->id; ?>" class="button button-small" style="background: #00ff88; color: #1a1f2e; border-color: #00ff88;">✏️</a>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Удалить товар?');">
                                <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
                                <input type="hidden" name="shop_action" value="delete_product">
                                <input type="hidden" name="id" value="<?php echo $product->id; ?>">
                                <button type="submit" class="button button-small" style="background: #fc8181; color: #fff; border-color: #fc8181;">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($active_tab === 'orders'): ?>
        <!-- СТАТИСТИКА ЗАКАЗОВ -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
            <div style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 20px; text-align: center;">
                <div style="font-size: 32px; font-weight: 700; color: #00ff88;"><?php echo $total_orders; ?></div>
                <div style="color: #a0aec0; font-size: 13px; text-transform: uppercase;">Всего заказов</div>
            </div>
            <div style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 20px; text-align: center;">
                <div style="font-size: 32px; font-weight: 700; color: #f6ad55;"><?php echo $pending_orders; ?></div>
                <div style="color: #a0aec0; font-size: 13px; text-transform: uppercase;">Новых</div>
            </div>
            <div style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 20px; text-align: center;">
                <div style="font-size: 32px; font-weight: 700; color: #63b3ed;"><?php echo number_format($revenue_month, 0, ',', ' '); ?> ₽</div>
                <div style="color: #a0aec0; font-size: 13px; text-transform: uppercase;">Выручка (мес)</div>
            </div>
        </div>

        <?php if ($view_order): ?>
        <!-- ПРОСМОТР ЗАКАЗА -->
        <div style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="color: #00ff88; margin: 0;">📋 Заказ #<?php echo esc_html($view_order->order_number); ?></h2>
                <a href="?page=akpp-crm-shop&tab=orders" class="button">← Назад к заказам</a>

        <?php /* akpp-adm-order-chat: переписка менеджера с клиентом по заказу */ if (class_exists('AKPP_Shop')): ?>
        <style>
        .akpp-adm-chat{ margin:18px 0; padding:18px 20px; background:#111827; border:1px solid rgba(0,255,136,.2); border-radius:12px; }
        .akpp-adm-chat h3{ color:#00ff88; font-size:14px; margin:0 0 12px; }
        .akpp-adm-chat-msgs{ max-height:320px; overflow-y:auto; padding:14px; background:#0a0f1c; border:1px solid #2d3748; border-radius:10px; margin-bottom:12px; }
        .akpp-adm-chat-empty{ color:#a0aec0; text-align:center; margin:14px 0; font-size:13px; }
        .akpp-adm-msg{ margin-bottom:10px; display:flex; flex-direction:column; }
        .akpp-adm-msg.manager{ align-items:flex-end; }
        .akpp-adm-msg.client{ align-items:flex-start; }
        .akpp-adm-bubble{ max-width:72%; padding:9px 13px; border-radius:12px; font-size:13.5px; line-height:1.5; word-break:break-word; }
        .akpp-adm-msg.manager .akpp-adm-bubble{ background:#00ff88; color:#08120c; border-bottom-right-radius:4px; }
        .akpp-adm-msg.client .akpp-adm-bubble{ background:#2d3748; color:#fff; border-bottom-left-radius:4px; }
        .akpp-adm-who{ font-size:10.5px; color:#a0aec0; margin-bottom:3px; letter-spacing:.3px; }
        .akpp-adm-time{ font-size:10px; opacity:.6; margin-top:3px; }
        .akpp-adm-form{ display:flex; gap:10px; }
        .akpp-adm-form input{ flex:1; padding:10px 14px; background:#0a0f1c; border:1px solid #2d3748; border-radius:9px; color:#fff; font-size:13.5px; }
        .akpp-adm-form input:focus{ outline:none; border-color:#00ff88; }
        .akpp-adm-form button{ border:none; cursor:pointer; padding:10px 20px; border-radius:9px; font-weight:700; background:#00ff88; color:#08120c; }
        .akpp-adm-form button:hover{ background:#00cc6a; }
        </style>
        <div class="akpp-adm-chat">
            <h3>💬 Переписка с клиентом по заказу #<?php echo (int) $view_order->id; ?></h3>
            <div class="akpp-adm-chat-msgs" id="akpp-adm-chat-msgs"><p class="akpp-adm-chat-empty">Загрузка…</p></div>
            <form class="akpp-adm-form" id="akpp-adm-chat-form">
                <input type="text" id="akpp-adm-chat-input" placeholder="Ответ клиенту…" autocomplete="off">
                <button type="submit">Отправить</button>
            </form>
        </div>
        <script>
        (function(){
            var orderId = <?php echo (int) $view_order->id; ?>;
            var nonce = '<?php echo wp_create_nonce('akpp45_nonce'); ?>';
            var AJAX = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            var payStatus = '<?php echo esc_js($view_order->payment_status); ?>';
            var box = document.getElementById('akpp-adm-chat-msgs');
            var form = document.getElementById('akpp-adm-chat-form');
            var input = document.getElementById('akpp-adm-chat-input');
            var last = -1;
            function esc(t){ var d=document.createElement('div'); d.textContent=t; return d.innerHTML; }
            function render(list){
                if(!list.length){ box.innerHTML='<p class="akpp-adm-chat-empty">Сообщений пока нет.</p>'; return; }
                box.innerHTML = list.map(function(m){
                    var who = m.sender_type==='manager' ? 'Вы (менеджер)' : 'Клиент';
                    var t=(m.created_at||'').replace('T',' ').substring(0,16);
                    var h='<div class="akpp-adm-msg '+m.sender_type+'"><div class="akpp-adm-who">'+esc(who)+'</div><div class="akpp-adm-bubble">';
                    if(m.msg_type==='payment_proof') h+='<div style="font-weight:800;margin-bottom:6px">💳 Подтверждение оплаты</div>';
                    if(m.message) h+=esc(m.message);
                    if(m.attachment) h+='<a href="'+esc(m.attachment)+'" target="_blank" style="display:block;margin-top:8px"><img src="'+esc(m.attachment)+'" style="max-width:100%;max-height:230px;border-radius:10px;border:1px solid rgba(255,255,255,.15)"></a>';
                    if(m.tx_hash) h+='<div style="margin-top:8px;font-size:11.5px;word-break:break-all;opacity:.85">🔗 '+esc(m.tx_hash)+'</div>';
                    if(m.msg_type==='payment_proof' && payStatus!=='paid') h+='<button type="button" class="akpp-adm-confirm" style="margin-top:10px;border:none;cursor:pointer;padding:8px 14px;border-radius:8px;font-weight:800;background:#00ff88;color:#08120c">✅ Подтвердить оплату</button>';
                    h+='</div><div class="akpp-adm-time">'+esc(t)+'</div></div>';
                    return h;
                }).join('');
                box.scrollTop=box.scrollHeight;
            }
            function load(){
                var fd=new FormData(); fd.append('action','akpp_shop_get_order_messages'); fd.append('order_id',orderId); fd.append('nonce',nonce);
                fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(res){ if(res&&res.success&&res.data.messages.length!==last){ last=res.data.messages.length; render(res.data.messages);} }).catch(function(){});
            }
            form.addEventListener('submit',function(e){ e.preventDefault(); var msg=input.value.trim(); if(!msg)return; var fd=new FormData(); fd.append('action','akpp_shop_send_order_message'); fd.append('order_id',orderId); fd.append('message',msg); fd.append('nonce',nonce); input.value=''; fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(res){ if(res&&res.success)load(); else input.value=msg; }).catch(function(){input.value=msg;}); });
            box.addEventListener('click',function(e){
                var b=e.target.closest('.akpp-adm-confirm'); if(!b) return;
                if(!confirm('Подтвердить оплату заказа #'+orderId+'?')) return;
                var fd=new FormData(); fd.append('action','akpp_shop_confirm_payment'); fd.append('order_id',orderId); fd.append('nonce',nonce);
                fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(res){ if(res&&res.success){ payStatus='paid'; last=-1; load(); alert('✅ Оплата подтверждена — статус заказа обновлён'); } else alert(res&&res.message?res.message:'Ошибка'); });
            });
            load(); setInterval(load,8000);
        })();
        </script>
        <?php endif; ?>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <h3 style="color: #00ff88; font-size: 14px; margin-bottom: 10px;">👤 Клиент</h3>
                    <p><strong>Имя:</strong> <?php echo esc_html($view_order->client_name); ?></p>
                    <p><strong>Телефон:</strong> <?php echo esc_html($view_order->client_phone); ?></p>
                    <?php if ($view_order->client_email): ?>
                    <p><strong>Email:</strong> <?php echo esc_html($view_order->client_email); ?></p>
                    <?php endif; ?>
                    <?php if ($view_order->client_address): ?>
                    <p><strong>Адрес:</strong> <?php echo esc_html($view_order->client_address); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 style="color: #00ff88; font-size: 14px; margin-bottom: 10px;">📊 Информация</h3>
                    <p><strong>Дата:</strong> <?php echo date_i18n('d.m.Y H:i', strtotime($view_order->created_at)); ?></p>
                    <p><strong>Оплата:</strong> <?php echo esc_html($view_order->payment_method); ?></p>
                    <p><strong>Статус оплаты:</strong> <?php echo esc_html($view_order->payment_status); ?></p>
                    <p><strong>Сумма:</strong> <span style="color: #00ff88; font-weight: 700;"><?php echo number_format($view_order->total, 0, ',', ' '); ?> ₽</span></p>
                </div>
            </div>

            <?php if ($view_order->notes): ?>
            <div style="background: #0a0f1c; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="color: #00ff88; font-size: 14px; margin-bottom: 10px;">💬 Комментарий клиента</h3>
                <p><?php echo nl2br(esc_html($view_order->notes)); ?></p>
            </div>
            <?php endif; ?>

            <h3 style="color: #00ff88; font-size: 14px; margin-bottom: 10px;">📦 Позиции заказа</h3>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>Товар</th>
                        <th>Артикул</th>
                        <th>Цена</th>
                        <th>Кол-во</th>
                        <th>Сумма</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): ?>
                    <tr>
                        <td><?php echo esc_html($item->product_name); ?></td>
                        <td><code><?php echo esc_html($item->product_sku); ?></code></td>
                        <td><?php echo number_format($item->price, 0, ',', ' '); ?> ₽</td>
                        <td><?php echo intval($item->quantity); ?></td>
                        <td><strong><?php echo number_format($item->total, 0, ',', ' '); ?> ₽</strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align: right;"><strong>Итого:</strong></td>
                        <td><strong style="color: #00ff88;"><?php echo number_format($view_order->total, 0, ',', ' '); ?> ₽</strong></td>
                    </tr>
                </tfoot>
            </table>

            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #2d3748;">
                <h3 style="color: #00ff88; font-size: 14px; margin-bottom: 10px;">⚙️ Изменить статус</h3>
                <form method="post" style="display: flex; gap: 10px; align-items: center;">
                    <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
                    <input type="hidden" name="shop_action" value="update_order_status">
                    <input type="hidden" name="order_id" value="<?php echo $view_order->id; ?>">
                    <select name="status" style="padding: 10px; background: #2d3748; border: 1px solid #4a5568; border-radius: 6px; color: #fff;">
                        <?php
$__os = AKPP_Shop::order_statuses();
$__gr = ['Оплата'=>['payment_pending','paid'], 'Сборка'=>['processing','preparing'], 'Выдача / отправка'=>['ready_pickup','ready_ship','shipped'], 'Финал'=>['completed','cancelled','refunded']];
echo '<option value="new" '.selected($view_order->status,'new',false).'>🆕 Новый</option>';
foreach ($__gr as $__g => $__codes) {
    echo '<optgroup label="'.esc_attr($__g).'">';
    foreach ($__codes as $__c) { if (isset($__os[$__c])) echo '<option value="'.esc_attr($__c).'" '.selected($view_order->status,$__c,false).'>'.esc_html($__os[$__c]['icon'].' '.$__os[$__c]['label']).'</option>'; }
    echo '</optgroup>';
}
?>
                    </select>
                    <button type="submit" class="button button-primary">💾 Сохранить</button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <!-- ТАБЛИЦА ЗАКАЗОВ -->
        <div style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 20px;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>№ заказа</th>
                        <th>Клиент</th>
                        <th>Телефон</th>
                        <th>Сумма</th>
                        <th>Статус</th>
                        <th>Оплата</th>
                        <th>Дата</th>
                        <th style="width: 150px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #718096;">
                            Заказов пока нет
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong><?php echo esc_html($order->order_number); ?></strong></td>
                        <td><?php echo esc_html($order->client_name); ?></td>
                        <td><?php echo esc_html($order->client_phone); ?></td>
                        <td><strong style="color: #00ff88;"><?php echo number_format($order->total, 0, ',', ' '); ?> ₽</strong></td>
                        <td>
                            <?php 
                            $__os = AKPP_Shop::order_statuses();
                            $status_labels = [];
                            $status_colors = [];
                            foreach ($__os as $__k => $__m) { $status_labels[$__k] = $__m['icon'].' '.$__m['label']; $status_colors[$__k] = $__m['color']; }
                            $color = $status_colors[$order->status] ?? '#718096';
                            ?>
                            <span style="display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; background: <?php echo $color; ?>22; color: <?php echo $color; ?>;">
                                <?php echo $status_labels[$order->status] ?? $order->status; ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($order->payment_status); ?></td>
                        <td><?php echo date_i18n('d.m.Y H:i', strtotime($order->created_at)); ?></td>
                        <td>
                            <a href="?page=akpp-crm-shop&tab=orders&view_order=<?php echo $order->id; ?>" class="button button-small" style="background: #00ff88; color: #1a1f2e; border-color: #00ff88;">👁️</a>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Удалить заказ?');">
                                <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
                                <input type="hidden" name="shop_action" value="delete_order">
                                <input type="hidden" name="order_id" value="<?php echo $order->id; ?>">
                                <button type="submit" class="button button-small" style="background: #fc8181; color: #fff; border-color: #fc8181;">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    <?php elseif ($active_tab === 'stats'): ?>
        <!-- СТАТИСТИКА -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 24px;">
                <div style="color: #a0aec0; font-size: 13px; text-transform: uppercase; margin-bottom: 12px;">Всего товаров</div>
                <div style="font-size: 36px; font-weight: 700; color: #00ff88;"><?php echo $total_products; ?></div>
            </div>
            <div style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 24px;">
                <div style="color: #a0aec0; font-size: 13px; text-transform: uppercase; margin-bottom: 12px;">Всего заказов</div>
                <div style="font-size: 36px; font-weight: 700; color: #63b3ed;"><?php echo $total_orders; ?></div>
            </div>
            <div style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 24px;">
                <div style="color: #a0aec0; font-size: 13px; text-transform: uppercase; margin-bottom: 12px;">Выручка (мес)</div>
                <div style="font-size: 36px; font-weight: 700; color: #00ff88;"><?php echo number_format($revenue_month, 0, ',', ' '); ?> ₽</div>
            </div>
            <div style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 24px;">
                <div style="color: #a0aec0; font-size: 13px; text-transform: uppercase; margin-bottom: 12px;">Новых заказов</div>
                <div style="font-size: 36px; font-weight: 700; color: #f6ad55;"><?php echo $pending_orders; ?></div>
            </div>
        </div>

        <div style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 24px;">
            <h2 style="color: #00ff88; margin-top: 0;">📊 Статистика по категориям</h2>
            <?php foreach ($categories as $cat): 
                $cat_count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$products_table} WHERE category = %s AND is_active = 1", $cat->slug));
            ?>
            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #2d3748;">
                <span><?php echo esc_html($cat->icon . ' ' . $cat->name); ?></span>
                <strong style="color: #00ff88;"><?php echo $cat_count; ?> товаров</strong>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- МОДАЛЬНОЕ ОКНО ДОБАВЛЕНИЯ/РЕДАКТИРОВАНИЯ ТОВАРА -->
<?php if ($edit_product || (isset($_GET['tab']) && $_GET['tab'] === 'products' && !$edit_product)): ?>
<div id="product-form-modal" style="display: <?php echo $edit_product ? 'flex' : 'none'; ?>; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 99999; align-items: center; justify-content: center;">
    <div style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 30px; max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="color: #00ff88; margin: 0;"><?php echo $edit_product ? '✏️ Редактировать товар' : '➕ Новый товар'; ?></h2>
            <button type="button" onclick="document.getElementById('product-form-modal').style.display='none'" style="background: none; border: none; color: #fff; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        
        <form method="post" id="shop-product-form" enctype="multipart/form-data">
            <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
            <input type="hidden" name="shop_action" value="save_product">
            <?php if ($edit_product): ?>
            <input type="hidden" name="id" value="<?php echo $edit_product->id; ?>">
            <?php endif; ?>
            
            <!-- ✅ БЛОК SKU С АВТОГЕНЕРАЦИЕЙ -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; color: #e2e8f0; font-weight: 600;">
                        Артикул 
                        <button type="button" id="generate-sku-btn" style="margin-left: 10px; font-size: 11px; background: #00ff88; color: #1a1f2e; border: none; padding: 3px 10px; border-radius: 4px; cursor: pointer; font-weight: 600;">
                            🎲 Сгенерировать
                        </button>
                    </label>
                    <input type="text" name="sku" id="product-sku" value="<?php echo $edit_product ? esc_attr($edit_product->sku) : ''; ?>" 
                           style="width: 100%; padding: 10px; background: #2d3748; border: 1px solid #4a5568; border-radius: 6px; color: #00ff88; font-family: 'Courier New', monospace; font-weight: 600;">
                    <small id="sku-status" style="display: block; margin-top: 5px; color: #a0aec0; font-size: 11px;"></small>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; color: #e2e8f0; font-weight: 600;">Категория *</label>
                    <select name="category" required style="width: 100%; padding: 10px; background: #2d3748; border: 1px solid #4a5568; border-radius: 6px; color: #fff;">
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat->slug); ?>" <?php echo ($edit_product && $edit_product->category === $cat->slug) ? 'selected' : ''; ?>>
                            <?php echo esc_html($cat->icon . ' ' . $cat->name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; color: #e2e8f0; font-weight: 600;">Название *</label>
                <input type="text" name="name" value="<?php echo $edit_product ? esc_attr($edit_product->name) : ''; ?>" required style="width: 100%; padding: 10px; background: #2d3748; border: 1px solid #4a5568; border-radius: 6px; color: #fff;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; color: #e2e8f0; font-weight: 600;">Описание</label>
                <textarea name="description" rows="3" style="width: 100%; padding: 10px; background: #2d3748; border: 1px solid #4a5568; border-radius: 6px; color: #fff;"><?php echo $edit_product ? esc_textarea($edit_product->description) : ''; ?></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; color: #e2e8f0; font-weight: 600;">Состояние</label>
                    <select name="condition_type" style="width: 100%; padding: 10px; background: #2d3748; border: 1px solid #4a5568; border-radius: 6px; color: #fff;">
                        <option value="new" <?php echo ($edit_product && $edit_product->condition_type === 'new') ? 'selected' : ''; ?>>Новый</option>
                        <option value="used" <?php echo ($edit_product && $edit_product->condition_type === 'used') ? 'selected' : ''; ?>>Б/У</option>
                        <option value="refurbished" <?php echo ($edit_product && $edit_product->condition_type === 'refurbished') ? 'selected' : ''; ?>>Восстановленный</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; color: #e2e8f0; font-weight: 600;">Качество</label>
                    <select name="quality_grade" style="width: 100%; padding: 10px; background: #2d3748; border: 1px solid #4a5568; border-radius: 6px; color: #fff;">
                        <option value="A" <?php echo ($edit_product && $edit_product->quality_grade === 'A') ? 'selected' : ''; ?>>A - Отличное</option>
                        <option value="B" <?php echo ($edit_product && $edit_product->quality_grade === 'B') ? 'selected' : ''; ?>>B - Хорошее</option>
                        <option value="C" <?php echo ($edit_product && $edit_product->quality_grade === 'C') ? 'selected' : ''; ?>>C - Удовлетворительное</option>
                        <option value="D" <?php echo ($edit_product && $edit_product->quality_grade === 'D') ? 'selected' : ''; ?>>D - Низкое</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; color: #e2e8f0; font-weight: 600;">Остаток</label>
                    <input type="number" name="stock" value="<?php echo $edit_product ? intval($edit_product->stock) : 0; ?>" min="0" style="width: 100%; padding: 10px; background: #2d3748; border: 1px solid #4a5568; border-radius: 6px; color: #fff;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; color: #e2e8f0; font-weight: 600;">Цена *</label>
                    <input type="number" name="price" value="<?php echo $edit_product ? floatval($edit_product->price) : 0; ?>" min="0" step="0.01" required style="width: 100%; padding: 10px; background: #2d3748; border: 1px solid #4a5568; border-radius: 6px; color: #fff;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; color: #e2e8f0; font-weight: 600;">Старая цена</label>
                    <input type="number" name="old_price" value="<?php echo $edit_product ? floatval($edit_product->old_price) : 0; ?>" min="0" step="0.01" style="width: 100%; padding: 10px; background: #2d3748; border: 1px solid #4a5568; border-radius: 6px; color: #fff;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
    <div>
        <label style="display: block; margin-bottom: 5px; color: #e2e8f0; font-weight: 600;">💰 Закупочная цена</label>
        <input type="number" name="purchase_price" value="<?php echo $edit_product ? floatval($edit_product->purchase_price) : 0; ?>" min="0" step="0.01" style="width: 100%; padding: 10px; background: #2d3748; border: 1px solid #4a5568; border-radius: 6px; color: #ed8936; font-weight: 600;">
    </div>
    <div style="display:flex;align-items:center;">
        <small style="color:#a0aec0;font-size:12px;line-height:1.5;">💡 Себестоимость товара — используется для расчёта реальной прибыли в разделе «Финансы».</small>
    </div>
</div>
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div>
                    <label style="display: flex; align-items: center; gap: 8px; color: #e2e8f0; cursor: pointer;">
                        <input type="checkbox" name="is_active" value="1" <?php echo (!$edit_product || $edit_product->is_active) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: #00ff88;">
                        Активен
                    </label>
                </div>
                <div>
                    <label style="display: flex; align-items: center; gap: 8px; color: #e2e8f0; cursor: pointer;">
                        <input type="checkbox" name="is_featured" value="1" <?php echo ($edit_product && $edit_product->is_featured) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: #ffd700;">
                        Рекомендуемый ⭐
                    </label>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
    <label style="display: block; margin-bottom: 5px; color: #e2e8f0; font-weight: 600;">📷 Фото товара</label>
    <?php $cur_imgs = ($edit_product && !empty($edit_product->images)) ? (json_decode($edit_product->images, true) ?: []) : []; if (!empty($cur_imgs)): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
        <?php foreach ($cur_imgs as $ci): ?><img src="<?php echo esc_url($ci); ?>" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid #4a5568;"><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <input type="file" name="product_image" accept="image/*" style="width: 100%; padding: 10px; background: #2d3748; border: 1px solid #4a5568; border-radius: 6px; color: #fff;">
    <small style="display:block;margin-top:5px;color:#a0aec0;font-size:11px;">Загружается в медиабиблиотеку. Можно добавлять по одному фото за сохранение.</small>
</div>
<div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('product-form-modal').style.display='none'" class="button" style="background: #4a5568; color: #fff;">Отмена</button>
                <button type="submit" class="button button-primary">💾 Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // ========================================================================
    // ГЕНЕРАЦИЯ УНИКАЛЬНОГО SKU
    // ========================================================================
    window.generateSku = function() {
        var timestamp = Date.now().toString(36).toUpperCase();
        var random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
        var sku = 'AKPP-' + timestamp + '-' + random;
        
        $('#product-sku').val(sku)
            .css('background', '#1a3a2e')
            .css('border-color', '#00ff88');
        
        $('#sku-status').text('✅ Артикул сгенерирован автоматически')
            .css('color', '#00ff88');
    };
    
    // Автогенерация при открытии формы нового товара
    <?php if (!$edit_product): ?>
    if ($('#product-sku').val() === '') {
        generateSku();
    }
    <?php endif; ?>
    
    // Кнопка генерации
    $('#generate-sku-btn').on('click', function(e) {
        e.preventDefault();
        generateSku();
    });
    
    // Проверка уникальности SKU при изменении
    var skuTimeout;
    $('#product-sku').on('input', function() {
        var sku = $(this).val().trim().toUpperCase();
        var $status = $('#sku-status');
        var productId = $('input[name="id"]').val() || 0;
        
        clearTimeout(skuTimeout);
        
        if (sku.length < 3) {
            $status.text('').hide();
            return;
        }
        
        $status.show();
        $status.text('⏳ Проверка...').css('color', '#a0aec0');
        
        skuTimeout = setTimeout(function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'akpp_shop_check_sku',
                    sku: sku,
                    product_id: productId,
                    nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        if (res.data.available) {
                            $status.text('✅ Артикул свободен').css('color', '#00ff88');
                            $('#product-sku').css('border-color', '#00ff88');
                        } else {
                            $status.text('❌ Артикул уже используется!').css('color', '#fc8181');
                            $('#product-sku').css('border-color', '#fc8181');
                        }
                    }
                }
            });
        }, 500);
    });
});
</script>
<?php endif; ?>