<?php
/**
 * АКПП45 Shop - Настройки магазина (способы оплаты, доставка, контакты)
 */
if (!defined('ABSPATH')) exit;

class AKPP_Shop_Settings {
    private static $instance = null;
    public static function get_instance() { if (null === self::$instance) self::$instance = new self(); return self::$instance; }

    private function __construct() {
        // add_action('admin_menu', [$this, 'add_menu']); // настройки встроены вкладкой в админку магазина (shop-admin.php)
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu() {
        add_menu_page('Настройки магазина', 'Магазин', 'manage_options', 'akpp-shop-settings', [$this, 'render_page'], 'dashicons-cart', 56);
    }

    public function register_settings() {
        register_setting('akpp_shop_settings_group', 'akpp_shop_settings', ['sanitize_callback' => [$this, 'sanitize']]);
    }

    public function sanitize($in) {
        $out = [];
        $out['payments'] = (isset($in['payments']) && is_array($in['payments'])) ? array_map('sanitize_text_field', $in['payments']) : [];
        $txt = ['card_number','card_holder','card_bank','sbp_phone','sbp_bank','sbp_name','gateway_provider','gateway_shop_id','gateway_secret','crypto_usdt','crypto_btc','crypto_note','pickup_address','delivery_cost','delivery_cities','contact_phone','contact_email','contact_telegram'];
        foreach ($txt as $k) $out[$k] = sanitize_text_field($in[$k] ?? '');
        return $out;
    }

    /** Хелпер чтения настроек: AKPP_Shop_Settings::get('card_number') */
    public static function get($key = '', $default = '') {
        $s = get_option('akpp_shop_settings', []);
        if ($key === '') return $s;
        return $s[$key] ?? $default;
    }
    public static function has_payment($method) {
        return in_array($method, (array) self::get('payments', []), true);
    }

    private function field($key, $label, $placeholder = '', $type = 'text') {
        $s = get_option('akpp_shop_settings', []);
        $val = esc_attr($s[$key] ?? '');
        echo '<div class="akpp-ss-field"><label>' . esc_html($label) . '</label>';
        echo '<input type="' . esc_attr($type) . '" name="akpp_shop_settings[' . esc_attr($key) . ']" value="' . $val . '" placeholder="' . esc_attr($placeholder) . '" class="regular-text">';
        echo '</div>';
    }

    public function render_page() {
        $s = get_option('akpp_shop_settings', []);
        $pay = (array) ($s['payments'] ?? []);
        $has = function ($m) use ($pay) { return in_array($m, $pay, true); };
        ?>
        <style>
            .akpp-shop-settings{ background:#0a0f1c; color:#e2e8f0; margin:16px 16px 40px 0; padding:28px 30px; border-radius:16px; border:1px solid rgba(0,255,136,.18); max-width:980px; }
            .akpp-shop-settings h1{ color:#00ff88; font-size:26px; margin:0 0 6px; }
            .akpp-shop-settings .akpp-ss-sub{ color:#9ca3af; margin:0 0 24px; }
            .akpp-shop-settings h2.akpp-ss-h{ color:#fff; font-size:16px; margin:26px 0 12px; padding-bottom:8px; border-bottom:1px solid rgba(255,255,255,.1); }
            .akpp-ss-payments{ display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:10px; }
            .akpp-ss-payments label{ display:flex; align-items:center; gap:10px; background:#111827; border:1px solid rgba(255,255,255,.1); border-radius:10px; padding:13px 15px; cursor:pointer; font-weight:600; transition:border-color .2s; }
            .akpp-ss-payments label:hover{ border-color:rgba(0,255,136,.5); }
            .akpp-ss-payments input{ width:18px; height:18px; accent-color:#00ff88; }
            .akpp-ss-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:14px 18px; }
            .akpp-ss-field label{ display:block; color:#9ca3af; font-size:12.5px; font-weight:700; margin-bottom:6px; letter-spacing:.3px; }
            .akpp-ss-field input, .akpp-ss-field select{ width:100%; background:#111827; border:1px solid rgba(255,255,255,.12); border-radius:9px; color:#fff; padding:10px 12px; font-size:14px; }
            .akpp-ss-field input:focus, .akpp-ss-field select:focus{ outline:none; border-color:#00ff88; box-shadow:0 0 0 3px rgba(0,255,136,.14); }
            .akpp-shop-settings .button-primary{ background:#00ff88; color:#08120c; border:none; font-weight:800; padding:10px 26px; border-radius:10px; margin-top:26px; }
            .akpp-shop-settings .button-primary:hover{ background:#00cc6a; }
            .akpp-ss-note{ color:#9ca3af; font-size:12.5px; margin-top:6px; }
        </style>
        <div class="wrap akpp-shop-settings">
            <h1>🛒 Настройки магазина</h1>
            <p class="akpp-ss-sub">Способы оплаты, доставка и контакты. Включённые способы отображаются покупателю при оформлении заказа.</p>
            <form method="post" action="options.php">
                <?php settings_fields('akpp_shop_settings_group'); ?>

                <h2 class="akpp-ss-h">Способы оплаты</h2>
                <div class="akpp-ss-payments">
                    <label><input type="checkbox" name="akpp_shop_settings[payments][]" value="card" <?php checked($has('card')); ?>> 💳 Перевод на карту</label>
                    <label><input type="checkbox" name="akpp_shop_settings[payments][]" value="sbp" <?php checked($has('sbp')); ?>> 📱 СБП</label>
                    <label><input type="checkbox" name="akpp_shop_settings[payments][]" value="gateway" <?php checked($has('gateway')); ?>> 🌐 Платёжная система</label>
                    <label><input type="checkbox" name="akpp_shop_settings[payments][]" value="crypto" <?php checked($has('crypto')); ?>> ₿ Криптовалюта</label>
                </div>

                <h2 class="akpp-ss-h">💳 Реквизиты карты</h2>
                <div class="akpp-ss-grid">
                    <?php $this->field('card_number', 'Номер карты', '0000 0000 0000 0000'); ?>
                    <?php $this->field('card_holder', 'Получатель', 'Иванов Иван Иванович'); ?>
                    <?php $this->field('card_bank', 'Банк', 'Сбербанк / Т-Банк'); ?>
                </div>

                <h2 class="akpp-ss-h">📱 СБП (Система быстрых платежей)</h2>
                <div class="akpp-ss-grid">
                    <?php $this->field('sbp_phone', 'Телефон СБП', '+7 (___) ___-__-__'); ?>
                    <?php $this->field('sbp_bank', 'Банк', 'Сбербанк'); ?>
                    <?php $this->field('sbp_name', 'Имя получателя', 'Иван Иванович И.'); ?>
                </div>

                <h2 class="akpp-ss-h">🌐 Платёжная система (эквайринг)</h2>
                <div class="akpp-ss-grid">
                    <div class="akpp-ss-field">
                        <label>Провайдер</label>
                        <select name="akpp_shop_settings[gateway_provider]">
                            <?php foreach (['' => '— не выбрано —', 'yookassa' => 'ЮKassa', 'tinkoff' => 'Тинькофф', 'cloudpayments' => 'CloudPayments', 'robokassa' => 'Robokassa'] as $v => $t): ?>
                                <option value="<?php echo esc_attr($v); ?>" <?php selected($s['gateway_provider'] ?? '', $v); ?>><?php echo esc_html($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php $this->field('gateway_shop_id', 'Shop ID / Terminal', 'shopId или terminal key'); ?>
                    <?php $this->field('gateway_secret', 'Секретный ключ', 'secret key / пароль'); ?>
                </div>
                <p class="akpp-ss-note">Для приёма онлайн‑платежей нужна регистрация у провайдера и включённый HTTPS. Ключи хранятся в базе и используются сервером для создания платежа.</p>

                <h2 class="akpp-ss-h">₿ Криптовалюта</h2>
                <div class="akpp-ss-grid">
                    <?php $this->field('crypto_usdt', 'USDT (TRC‑20) адрес', 'T...'); ?>
                    <?php $this->field('crypto_btc', 'BTC адрес', 'bc1...'); ?>
                    <?php $this->field('crypto_note', 'Примечание', 'Сеть TRC-20, комиссия за счёт отправителя'); ?>
                </div>

                <h2 class="akpp-ss-h">🚚 Доставка</h2>
                <div class="akpp-ss-grid">
                    <?php $this->field('pickup_address', 'Адрес самовывоза', 'г. Курган, ул. Бурова-Петрова, 121 (ГСК КАС №8)'); ?>
                    <?php $this->field('delivery_cost', 'Стоимость самовывоза / погрузки (₽, 0 если бесплатно)', '0'); ?>
                    <?php $this->field('delivery_cities', 'Транспортные компании (через запятую)', 'СДЭК, Деловые Линии, ПЭК'); ?>
                </div>

                <h2 class="akpp-ss-h">📞 Контакты магазина</h2>
                <div class="akpp-ss-grid">
                    <?php $this->field('contact_phone', 'Телефон', '+7 (963) 866-99-96'); ?>
                    <?php $this->field('contact_email', 'Email', 'adamzoom@bk.ru'); ?>
                    <?php $this->field('contact_telegram', 'Telegram', '@akppkgn'); ?>
                </div>

                <?php submit_button('Сохранить настройки'); ?>
            </form>
        </div>
        <?php
    }
}
AKPP_Shop_Settings::get_instance();
