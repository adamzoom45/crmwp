<?php
if (!defined('ABSPATH')) exit;
$shop = AKPP_Shop::get_instance();
$products = $shop->get_products(['per_page' => 200, 'active_only' => true]);
$categories = $shop->get_categories();
$cart = $shop->get_cart();
$cat_icons = ['akpp' => '🔧', 'oils' => '🛢️', 'engine' => '⚙️', 'suspension' => '🚗', 'parts' => '📦'];
$ajax_url = admin_url('admin-ajax.php');
?>
<style>
.shop-catalog{ max-width:1200px; margin:0 auto; padding:48px 24px 80px; }
.shop-catalog-head{ display:flex; justify-content:space-between; align-items:flex-end; gap:24px; flex-wrap:wrap; margin-bottom:30px; }
.shop-catalog-title{ font-family:var(--font-display,'Unbounded',sans-serif); font-size:clamp(28px,4.5vw,46px); font-weight:800; letter-spacing:-.6px; margin:0 0 10px; color:var(--text-main,#fff); }
.shop-catalog-sub{ margin:0; max-width:56ch; color:var(--text-muted,#9ca3af); line-height:1.6; font-size:15px; }
.shop-cart-btn{ display:inline-flex; align-items:center; gap:10px; padding:13px 20px; border-radius:12px; text-decoration:none; background:var(--bg-secondary,#111827); border:1px solid var(--border,rgba(0,255,136,.2)); color:var(--text-main,#fff); font-weight:700; transition:transform .25s ease, box-shadow .25s ease, border-color .25s ease; }
.shop-cart-btn:hover{ transform:translateY(-2px); border-color:var(--accent,#00ff88); box-shadow:0 14px 30px -18px rgba(0,255,136,.6); }
.cart-count{ display:inline-grid; place-items:center; min-width:24px; height:24px; padding:0 6px; border-radius:999px; background:var(--accent,#00ff88); color:#08120c; font-size:13px; font-weight:800; font-variant-numeric:tabular-nums; }
.shop-toolbar{ display:flex; justify-content:space-between; align-items:center; gap:18px; flex-wrap:wrap; margin-bottom:28px; }
.shop-chips{ display:flex; flex-wrap:wrap; gap:9px; }
.shop-chip{ padding:9px 16px; border-radius:999px; border:1px solid var(--border,rgba(0,255,136,.2)); background:var(--bg-secondary,#111827); color:var(--text-muted,#9ca3af); font-weight:700; font-size:13px; cursor:pointer; transition:all .22s ease; }
.shop-chip:hover{ border-color:var(--accent,#00ff88); color:var(--text-main,#fff); transform:translateY(-1px); }
.shop-chip.active{ background:var(--accent,#00ff88); border-color:var(--accent,#00ff88); color:#08120c; box-shadow:0 8px 22px -12px rgba(0,255,136,.7); }
.shop-search input{ width:min(320px,100%); padding:12px 16px; border-radius:11px; border:1px solid var(--border,rgba(0,255,136,.2)); background:var(--bg-secondary,#111827); color:var(--text-main,#fff); font-size:14px; transition:border-color .22s ease, box-shadow .22s ease; }
.shop-search input:focus{ outline:none; border-color:var(--accent,#00ff88); box-shadow:0 0 0 4px rgba(0,255,136,.12); }
.shop-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(252px,1fr)); gap:20px; }
.shop-card{ display:flex; flex-direction:column; overflow:hidden; border-radius:16px; border:1px solid var(--border,rgba(0,255,136,.2)); background:linear-gradient(180deg,var(--bg-secondary,#111827),var(--bg-primary,#0a0f1c)); cursor:pointer; transition:transform .3s ease, box-shadow .3s ease, border-color .3s ease; animation:shop-rise .5s cubic-bezier(.2,.7,.2,1) both; }
.shop-card:nth-child(2){animation-delay:.05s}.shop-card:nth-child(3){animation-delay:.1s}.shop-card:nth-child(4){animation-delay:.15s}
.shop-card:nth-child(5){animation-delay:.2s}.shop-card:nth-child(6){animation-delay:.25s}.shop-card:nth-child(7){animation-delay:.3s}
.shop-card:nth-child(8){animation-delay:.35s}.shop-card:nth-child(n+9){animation-delay:.4s}
.shop-card:hover{ transform:translateY(-6px); border-color:rgba(0,255,136,.45); box-shadow:0 26px 56px -30px rgba(0,0,0,.95), 0 0 0 1px rgba(0,255,136,.12); }
.shop-card-img{ position:relative; height:128px; display:grid; place-items:center; }
.shop-card-img[data-icon-cat="akpp"]{ background:linear-gradient(135deg,rgba(0,255,136,.16),rgba(0,255,136,.03)); }
.shop-card-img[data-icon-cat="oils"]{ background:linear-gradient(135deg,rgba(245,181,68,.16),rgba(245,181,68,.03)); }
.shop-card-img[data-icon-cat="engine"]{ background:linear-gradient(135deg,rgba(65,210,226,.16),rgba(65,210,226,.03)); }
.shop-card-img[data-icon-cat="suspension"]{ background:linear-gradient(135deg,rgba(155,140,255,.16),rgba(155,140,255,.03)); }
.shop-card-img[data-icon-cat="parts"]{ background:linear-gradient(135deg,rgba(255,255,255,.09),rgba(255,255,255,.02)); }
.shop-card-ico{ font-size:46px; filter:drop-shadow(0 6px 14px rgba(0,0,0,.5)); }
        .shop-card-photo{ width:100%; height:100%; object-fit:cover; display:block; transition:transform .4s ease; }
        .shop-card:hover .shop-card-photo{ transform:scale(1.06); }
.shop-cond{ position:absolute; top:10px; left:10px; padding:4px 10px; border-radius:999px; font-size:10.5px; font-weight:800; letter-spacing:.6px; text-transform:uppercase; background:rgba(0,255,136,.16); color:var(--accent,#00ff88); border:1px solid rgba(0,255,136,.3); }
.shop-grade{ position:absolute; top:10px; right:10px; padding:4px 9px; border-radius:999px; font-size:10.5px; font-weight:800; letter-spacing:.5px; background:rgba(65,210,226,.14); color:var(--accent-2,#41d2e2); border:1px solid rgba(65,210,226,.3); }
.shop-card-body{ display:flex; flex-direction:column; gap:8px; padding:16px 16px 18px; flex:1; }
.shop-card-name{ margin:0; font-size:15.5px; font-weight:700; line-height:1.35; color:var(--text-main,#fff); min-height:42px; }
.shop-card-sku{ margin:0; font-size:11px; letter-spacing:.8px; color:var(--text-muted,#9ca3af); font-variant-numeric:tabular-nums; }
.shop-card-price{ display:flex; align-items:baseline; gap:9px; margin-top:2px; }
.shop-old{ color:var(--text-muted,#9ca3af); text-decoration:line-through; font-size:13px; }
.shop-now{ font-family:var(--font-display,'Unbounded',sans-serif); font-size:22px; font-weight:800; letter-spacing:-.4px; color:var(--accent,#00ff88); font-variant-numeric:tabular-nums; }
/* футер карточки — КОЛОНКА: наличие сверху, кнопка во всю ширину снизу (без переноса текста) */
.shop-card-foot{ display:flex; flex-direction:column; align-items:stretch; gap:11px; margin-top:auto; padding-top:14px; }
.shop-stock{ align-self:flex-start; font-size:11px; font-weight:800; letter-spacing:.3px; padding:5px 10px; border-radius:8px; white-space:nowrap; }
.shop-stock.in-stock{ background:rgba(0,255,136,.13); color:var(--accent,#00ff88); }
.shop-stock.low-stock{ background:rgba(245,181,68,.13); color:var(--accent-3,#f5b544); }
.shop-stock.out-of-stock{ background:rgba(255,255,255,.06); color:var(--text-muted,#9ca3af); }
.shop-add{ width:100%; white-space:nowrap; border:none; cursor:pointer; padding:12px 15px; border-radius:11px; font-weight:800; font-size:13.5px; letter-spacing:.2px; background:linear-gradient(135deg,var(--accent,#00ff88),var(--accent-dark,#00cc6a)); color:#08120c; transition:transform .2s ease, box-shadow .25s ease, filter .2s ease; }
.shop-add:hover{ transform:translateY(-2px); box-shadow:0 12px 26px -12px rgba(0,255,136,.75); filter:brightness(1.05); }
.shop-add:active{ transform:translateY(0) scale(.98); }
.shop-add:disabled{ opacity:.6; cursor:not-allowed; transform:none; box-shadow:none; }
.shop-empty{ text-align:center; color:var(--text-muted,#9ca3af); padding:60px 20px; font-size:15px; }
/* модалка товара */
.shop-modal{ position:fixed; inset:0; z-index:99998; display:none; align-items:center; justify-content:center; padding:20px; }
.shop-modal.open{ display:flex; }
.shop-modal-overlay{ position:absolute; inset:0; background:rgba(4,8,16,.78); backdrop-filter:blur(6px); animation:shop-fade .25s ease both; }
.shop-modal-content{ position:relative; width:min(560px,100%); max-height:90vh; overflow-y:auto; border-radius:20px; border:1px solid rgba(0,255,136,.28); background:linear-gradient(180deg,var(--bg-secondary,#111827),var(--bg-primary,#0a0f1c)); box-shadow:0 40px 100px -40px rgba(0,0,0,.95); animation:shop-pop .32s cubic-bezier(.2,.8,.2,1) both; }
.shop-modal-close{ position:absolute; top:14px; right:14px; z-index:2; width:38px; height:38px; border-radius:10px; border:1px solid rgba(255,255,255,.14); background:rgba(255,255,255,.05); color:#fff; font-size:20px; cursor:pointer; transition:transform .2s ease, background .2s ease; }
.shop-modal-close:hover{ transform:rotate(90deg); background:rgba(255,255,255,.12); }
.shop-modal-img{ height:170px; display:grid; place-items:center; font-size:74px; border-bottom:1px solid rgba(255,255,255,.08); }
.shop-modal-body{ padding:24px 26px 28px; }
.shop-modal-badges{ display:flex; gap:8px; margin-bottom:14px; }
.shop-modal-badges span{ padding:5px 11px; border-radius:999px; font-size:11px; font-weight:800; letter-spacing:.5px; }
.spm-cond{ background:rgba(0,255,136,.16); color:var(--accent,#00ff88); border:1px solid rgba(0,255,136,.3); }
.spm-grade{ background:rgba(65,210,226,.14); color:var(--accent-2,#41d2e2); border:1px solid rgba(65,210,226,.3); }
.shop-modal-body h3{ margin:0 0 6px; font-family:var(--font-display,'Unbounded',sans-serif); font-size:clamp(20px,3vw,26px); font-weight:800; letter-spacing:-.4px; color:var(--text-main,#fff); line-height:1.2; }
.spm-sku{ margin:0 0 16px; font-size:12px; letter-spacing:.8px; color:var(--text-muted,#9ca3af); font-variant-numeric:tabular-nums; }
.spm-price{ display:flex; align-items:baseline; gap:12px; margin-bottom:16px; }
.spm-price .now{ font-family:var(--font-display,'Unbounded',sans-serif); font-size:clamp(28px,4vw,38px); font-weight:800; letter-spacing:-.6px; color:var(--accent,#00ff88); font-variant-numeric:tabular-nums; }
.spm-price .old{ color:var(--text-muted,#9ca3af); text-decoration:line-through; font-size:16px; }
.spm-desc{ margin:0 0 16px; color:var(--text-muted,#9ca3af); line-height:1.65; font-size:14.5px; }
.spm-stock{ display:inline-block; margin-bottom:20px; font-size:12px; font-weight:800; padding:7px 12px; border-radius:9px; }
.spm-add{ margin-top:4px; }
@keyframes shop-rise{ from{ opacity:0; transform:translateY(16px);} to{ opacity:1; transform:none;} }
@keyframes shop-fade{ from{ opacity:0;} to{ opacity:1;} }
@keyframes shop-pop{ from{ opacity:0; transform:translateY(20px) scale(.96);} to{ opacity:1; transform:none;} }
@media (max-width:720px){ .shop-catalog-head{ flex-direction:column; align-items:flex-start; } .shop-toolbar{ flex-direction:column; align-items:stretch; } .shop-search input{ width:100%; } }
@media (prefers-reduced-motion:reduce){ .shop-card,.shop-modal-overlay,.shop-modal-content{ animation:none; } }
</style>

<section class="shop-catalog">
    <div class="shop-catalog-head">
        <div>
            <h2 class="shop-catalog-title">Магазин запчастей</h2>
            <p class="shop-catalog-sub">Запчасти для АКПП Toyota, Lexus и других марок в наличии.</p>
        </div>
        <a href="<?php echo esc_url(home_url('/cart/')); ?>" class="shop-cart-btn">🛒 <span>Корзина</span> <span class="cart-count"><?php echo (int) $cart['count']; ?></span></a>
    </div>

    <div class="shop-toolbar">
        <div class="shop-chips">
            <button type="button" class="shop-chip active" data-cat="">Все</button>
            <?php foreach ($categories as $c): ?>
                <button type="button" class="shop-chip" data-cat="<?php echo esc_attr($c->slug); ?>"><?php echo esc_html($c->icon ?: ($cat_icons[$c->slug] ?? '📦')); ?> <?php echo esc_html($c->name); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="shop-search"><input type="search" id="shop-search" placeholder="Поиск по названию или артикулу…"></div>
    </div>

    <div class="shop-grid" id="shop-grid">
        <?php foreach ($products as $pr):
            $icon = $cat_icons[$pr->category] ?? '📦';
            $stock = (int) $pr->stock;
            $stock_cls = $stock > 5 ? 'in-stock' : ($stock > 0 ? 'low-stock' : 'out-of-stock');
            $stock_txt = $stock > 5 ? ('В наличии · ' . $stock . ' шт') : ($stock > 0 ? ('Осталось ' . $stock . ' шт') : 'Под заказ');
            $cond = $pr->condition_type === 'new' ? 'Новый' : ($pr->condition_type === 'used' ? 'Б/У' : 'Восст.');
            $__imgs = json_decode($pr->images ?? '[]', true); $__photo = (is_array($__imgs) && !empty($__imgs)) ? $__imgs[0] : '';
        ?>
        <article class="shop-card"
            data-cat="<?php echo esc_attr($pr->category); ?>"
            data-name="<?php echo esc_attr($pr->name . ' ' . $pr->sku); ?>"
            data-id="<?php echo (int) $pr->id; ?>"
            data-title="<?php echo esc_attr($pr->name); ?>"
            data-sku="<?php echo esc_attr($pr->sku); ?>"
            data-price="<?php echo esc_attr($pr->price); ?>"
            data-oldprice="<?php echo esc_attr($pr->old_price); ?>"
            data-stock="<?php echo (int) $stock; ?>"
            data-stocktxt="<?php echo esc_attr($stock_txt); ?>"
            data-stockcls="<?php echo esc_attr($stock_cls); ?>"
            data-cond="<?php echo esc_attr($cond); ?>"
            data-grade="<?php echo esc_attr($pr->quality_grade); ?>"
            data-desc="<?php echo esc_attr($pr->description); ?>"
            data-icon="<?php echo esc_attr($icon); ?>"
            data-iconcat="<?php echo esc_attr($pr->category); ?>" data-photo="<?php echo esc_attr($__photo); ?>">
            <div class="shop-card-img" data-icon-cat="<?php echo esc_attr($pr->category); ?>">
                <?php if ($__photo): ?><img src="<?php echo esc_url($__photo); ?>" class="shop-card-photo" alt="<?php echo esc_attr($pr->name); ?>"><?php else: ?><span class="shop-card-ico"><?php echo esc_html($icon); ?></span><?php endif; ?>
                <span class="shop-cond"><?php echo esc_html($cond); ?></span>
                <?php if (!empty($pr->quality_grade)): ?><span class="shop-grade">Грейд <?php echo esc_html($pr->quality_grade); ?></span><?php endif; ?>
            </div>
            <div class="shop-card-body">
                <h3 class="shop-card-name"><?php echo esc_html($pr->name); ?></h3>
                <p class="shop-card-sku">Арт. <?php echo esc_html($pr->sku); ?></p>
                <div class="shop-card-price">
                    <?php if (!empty($pr->old_price) && $pr->old_price > 0): ?><span class="shop-old"><?php echo number_format($pr->old_price, 0, ',', ' '); ?> ₽</span><?php endif; ?>
                    <span class="shop-now"><?php echo number_format($pr->price, 0, ',', ' '); ?> ₽</span>
                </div>
                <div class="shop-card-foot">
                    <span class="shop-stock <?php echo $stock_cls; ?>"><?php echo esc_html($stock_txt); ?></span>
                    <button type="button" class="shop-add" data-product-id="<?php echo (int) $pr->id; ?>">🛒 В корзину</button>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <p class="shop-empty" id="shop-empty" style="display:none">Ничего не найдено. Попробуйте другой фильтр или запрос.</p>
</section>

<div class="shop-modal" id="shop-product-modal" aria-hidden="true">
    <div class="shop-modal-overlay" data-close></div>
    <div class="shop-modal-content">
        <button type="button" class="shop-modal-close" data-close aria-label="Закрыть">×</button>
        <div class="shop-modal-img" id="spm-img"><span id="spm-ico">📦</span></div>
        <div class="shop-modal-body">
            <div class="shop-modal-badges"><span class="spm-cond" id="spm-cond"></span><span class="spm-grade" id="spm-grade"></span></div>
            <h3 id="spm-name"></h3>
            <p class="spm-sku" id="spm-sku"></p>
            <div class="spm-price"><span class="now" id="spm-now"></span><span class="old" id="spm-old"></span></div>
            <p class="spm-desc" id="spm-desc"></p>
            <span class="spm-stock" id="spm-stock"></span>
            <button type="button" class="shop-add spm-add" id="spm-add" data-product-id="">🛒 В корзину</button>
        </div>
    </div>
</div>

<script>
(function(){
    var AJAX = '<?php echo esc_js($ajax_url); ?>';
    var grid = document.getElementById('shop-grid');
    var cards = grid ? Array.prototype.slice.call(grid.querySelectorAll('.shop-card')) : [];
    var chips = Array.prototype.slice.call(document.querySelectorAll('.shop-chip'));
    var search = document.getElementById('shop-search');
    var empty = document.getElementById('shop-empty');
    var curCat = '';
    var fmt = function(n){ return Math.round(Number(n)||0).toLocaleString('ru-RU') + ' ₽'; };
    function notice(msg, ok){
        var d = document.createElement('div'); d.textContent = msg;
        d.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;padding:15px 22px;border-radius:10px;font-weight:700;box-shadow:0 10px 30px rgba(0,0,0,.4);color:' + (ok?'#08120c':'#fff') + ';background:' + (ok?'#00ff88':'#fc8181') + ';';
        document.body.appendChild(d);
        setTimeout(function(){ d.style.transition='opacity .3s'; d.style.opacity='0'; setTimeout(function(){ d.remove(); }, 320); }, 2600);
    }
    function updateCount(){
        var fd = new FormData(); fd.append('action','akpp_shop_get_cart');
        fetch(AJAX, { method:'POST', body:fd, credentials:'same-origin' }).then(function(r){ return r.json(); })
            .then(function(res){ if (res && res.success) document.querySelectorAll('.cart-count').forEach(function(e){ e.textContent = res.data.count; }); }).catch(function(){});
    }
    function addToCart(btn){
        var id = btn.getAttribute('data-product-id'); if (!id) return;
        var orig = btn.textContent; btn.disabled = true; btn.textContent = '⏳ Добавление…';
        var fd = new FormData(); fd.append('action','akpp_shop_add_to_cart'); fd.append('product_id', id); fd.append('quantity', 1);
        fetch(AJAX, { method:'POST', body:fd, credentials:'same-origin' }).then(function(r){ return r.json(); })
            .then(function(res){ notice((res && res.success ? '✅ ' : '❌ ') + (res ? res.message : 'Ошибка'), !!(res && res.success)); if (res && res.success) updateCount(); })
            .catch(function(){ notice('❌ Ошибка соединения', false); }).finally(function(){ btn.disabled = false; btn.textContent = orig; });
    }
    var modal = document.getElementById('shop-product-modal');
    var imgBox = document.getElementById('spm-img');
    var grad = { akpp:'linear-gradient(135deg,rgba(0,255,136,.16),rgba(0,255,136,.03))', oils:'linear-gradient(135deg,rgba(245,181,68,.16),rgba(245,181,68,.03))', engine:'linear-gradient(135deg,rgba(65,210,226,.16),rgba(65,210,226,.03))', suspension:'linear-gradient(135deg,rgba(155,140,255,.16),rgba(155,140,255,.03))', parts:'linear-gradient(135deg,rgba(255,255,255,.09),rgba(255,255,255,.02))' };
    function openModal(card){
        var d = card.dataset;
        if(d.photo){ imgBox.innerHTML = '<img src="'+d.photo+'" style="width:100%;height:100%;object-fit:cover">'; } else { imgBox.innerHTML = '<span style="font-size:74px">'+(d.icon||'📦')+'</span>'; }
        imgBox.style.background = grad[d.iconcat] || grad.parts;
        document.getElementById('spm-cond').textContent = d.cond || '';
        var g = document.getElementById('spm-grade'); g.textContent = d.grade ? ('Грейд ' + d.grade) : ''; g.style.display = d.grade ? '' : 'none';
        document.getElementById('spm-name').textContent = d.title || '';
        document.getElementById('spm-sku').textContent = 'Артикул: ' + (d.sku || '—');
        document.getElementById('spm-now').textContent = fmt(d.price);
        var old = document.getElementById('spm-old'); old.textContent = (Number(d.oldprice) > 0) ? fmt(d.oldprice) : ''; old.style.display = (Number(d.oldprice) > 0) ? '' : 'none';
        var desc = document.getElementById('spm-desc'); desc.textContent = d.desc || ''; desc.style.display = d.desc ? '' : 'none';
        var st = document.getElementById('spm-stock'); st.textContent = d.stocktxt || ''; st.className = 'spm-stock shop-stock ' + (d.stockcls || '');
        document.getElementById('spm-add').setAttribute('data-product-id', d.id || '');
        modal.classList.add('open'); modal.setAttribute('aria-hidden','false'); document.body.style.overflow = 'hidden';
    }
    function closeModal(){ modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); document.body.style.overflow = ''; }
    document.addEventListener('click', function(e){
        var add = e.target.closest('.shop-add');
        if (add) { e.preventDefault(); e.stopPropagation(); addToCart(add); return; }
        if (e.target.closest('[data-close]')) { closeModal(); return; }
        var card = e.target.closest('.shop-card'); if (card) openModal(card);
    });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal.classList.contains('open')) closeModal(); });
    function apply(){
        var q = (search && search.value || '').toLowerCase().trim(); var shown = 0;
        cards.forEach(function(c){
            var okCat = !curCat || c.getAttribute('data-cat') === curCat;
            var okQ = !q || (c.getAttribute('data-name') || '').toLowerCase().indexOf(q) !== -1;
            var show = okCat && okQ; c.style.display = show ? '' : 'none'; if (show) shown++;
        });
        if (empty) empty.style.display = shown ? 'none' : 'block';
    }
    chips.forEach(function(ch){ ch.addEventListener('click', function(){ chips.forEach(function(x){ x.classList.remove('active'); }); ch.classList.add('active'); curCat = ch.getAttribute('data-cat') || ''; apply(); }); });
    if (search) search.addEventListener('input', apply);
    updateCount();
})();
</script>
