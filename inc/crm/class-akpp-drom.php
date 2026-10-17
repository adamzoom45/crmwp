<?php
if (!defined('ABSPATH')) exit;

/**
 * АКПП45 — интеграция Drom/Farpost (baza.drom.ru): выгрузка прайса запчастей.
 * API: POST https://baza.drom.ru/good/packet/api/sync (multipart: packetId, auth=sha512(key), data=файл прайса)
 */
class AKPP_Drom {
    private static $instance = null;
    const API = 'https://baza.drom.ru/good/packet/api/sync';

    public static function get_instance() { if (null === self::$instance) self::$instance = new self(); return self::$instance; }

    private function __construct() {
        
        add_action('admin_post_akpp_drom_save', [$this, 'handle_save']);
        add_action('admin_post_akpp_drom_send', [$this, 'handle_send']);
        add_action('akpp_drom_cron', [$this, 'sync']);
        if (get_option('akpp_drom_enabled') && !wp_next_scheduled('akpp_drom_cron')) {
            wp_schedule_event(time() + 300, 'daily', 'akpp_drom_cron');
        }
    }


    public static function settings() {
        return [
            'packet_id' => (string) get_option('akpp_drom_packet_id', ''),
            'secret'    => (string) get_option('akpp_drom_secret', ''),
            'enabled'   => (bool) get_option('akpp_drom_enabled', false),
        ];
    }

    public function handle_save() {
        if (!current_user_can('akpp_manage_integrations')) wp_die('Нет прав');
        check_admin_referer('akpp_drom_save');
        update_option('akpp_drom_packet_id', sanitize_text_field($_POST['packet_id'] ?? ''));
        $sec = trim((string) ($_POST['secret'] ?? ''));
        if ($sec !== '') update_option('akpp_drom_secret', $sec);
        $en = !empty($_POST['enabled']);
        update_option('akpp_drom_enabled', $en ? 1 : 0);
        if ($en && !wp_next_scheduled('akpp_drom_cron')) wp_schedule_event(time() + 300, 'daily', 'akpp_drom_cron');
        if (!$en) wp_clear_scheduled_hook('akpp_drom_cron');
        wp_safe_redirect(admin_url('admin.php?page=akpp-crm-drom&saved=1')); exit;
    }

    public function handle_send() {
        if (!current_user_can('akpp_manage_integrations')) wp_die('Нет прав');
        check_admin_referer('akpp_drom_send');
        $r = $this->sync();
        wp_safe_redirect(admin_url('admin.php?page=akpp-crm-drom&sent=' . ($r['ok'] ? 1 : 0))); exit;
    }

    /** XML-прайс: активные товары + удалённые (кол-во 0) по истории отправок */
    public function build_xml() {
        global $wpdb;
        $t = $wpdb->prefix . 'akpp_shop_products';
        $rows = $wpdb->get_results("SELECT sku, name, description, price, stock, is_active FROM $t ORDER BY id ASC");
        $current = [];
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('Products');
        $dom->appendChild($root);
        $mk = function ($sku, $name, $desc, $price, $qty) use ($dom, $root, &$current) {
            $current[] = $sku;
            $el = $dom->createElement('Product');
            foreach (['Name' => $name, 'Description' => $desc, 'Quantity' => (string) $qty, 'Price' => number_format((float) $price, 2, '.', ''), 'Article' => $sku] as $k => $v) {
                $el->appendChild($dom->createElement($k, htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8')));
            }
            $root->appendChild($el);
        };
        foreach ((array) $rows as $p) {
            if ((int) $p->is_active === 1) {
                $mk($p->sku, $p->name, $p->description ?: $p->name, $p->price, max(0, (int) $p->stock));
            }
        }
        // ранее отправленные, но теперь отсутствующие/выключенные -> Quantity 0 (удаление на Drom)
        $sent = (array) get_option('akpp_drom_sent_skus', []);
        foreach ($sent as $sku) {
            if (in_array($sku, $current, true)) continue;
            $mk($sku, 'Товар снят с продажи', 'Товар снят с продажи', 0, 0);
        }
        update_option('akpp_drom_sent_skus', $current);
        return ['xml' => $dom->saveXML(), 'count' => count($current)];
    }

    /** Отправка на Drom; $dry = только собрать XML */
    public function sync($dry = false) {
        $s = self::settings();
        $b = $this->build_xml();
        if ($dry) return ['ok' => true, 'dry' => true, 'count' => $b['count'], 'xml' => $b['xml']];
        if ($s['packet_id'] === '' || $s['secret'] === '') {
            $this->log('error', null, $b['count'], 'Не заданы packetId или секретный ключ');
            return ['ok' => false, 'error' => 'no_credentials'];
        }
        $res = wp_remote_post(self::API, [
            'timeout' => 45,
            'body' => [
                'packetId' => $s['packet_id'],
                'auth'     => hash('sha512', $s['secret']),
                'data'     => ['body' => $b['xml'], 'filename' => 'akpp45_price.xml', 'content-type' => 'application/xml'],
            ],
        ]);
        if (is_wp_error($res)) {
            $this->log('error', null, $b['count'], $res->get_error_message());
            return ['ok' => false, 'error' => $res->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        $this->log($code === 200 ? 'ok' : 'error', $code, $b['count'], $body);
        return ['ok' => $code === 200, 'code' => $code, 'response' => $body, 'count' => $b['count']];
    }

    private function log($status, $code, $products, $response) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'akpp_drom_log', [
            'created_at' => current_time('mysql'),
            'status'     => $status,
            'http_code'  => $code,
            'products'   => (int) $products,
            'response'   => mb_substr((string) $response, 0, 500),
        ]);
    }

    public function render() {
        $s = self::settings();
        global $wpdb;
        $logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}akpp_drom_log ORDER BY id DESC LIMIT 15");
        $dry = $this->sync(true);
        include AKPP_CRM_PATH . 'templates/drom-integration.php';
    }
}
AKPP_Drom::get_instance();

// Мост для меню из class-akpp-crm.php
function akpp_drom_render_page() { AKPP_Drom::get_instance()->render(); }
