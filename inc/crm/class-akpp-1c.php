<?php
/**
 * АКПП45 — Интеграция с 1С (универсальный обмен XML, формат DataExchange)
 */
if (!defined('ABSPATH')) exit;

class AKPP_1C {
    private static $instance = null;
    public static function get_instance() { if (!self::$instance) self::$instance = new self(); return self::$instance; }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('wp_ajax_akpp_1c_export_products', [$this, 'ajax_export_products']);
        add_action('wp_ajax_nopriv_akpp_1c_export_products', [$this, 'ajax_export_products']);
        add_action('wp_ajax_akpp_1c_import_products', [$this, 'ajax_import_products']);
        add_action('wp_ajax_nopriv_akpp_1c_import_products', [$this, 'ajax_import_products']);
        add_action('wp_ajax_akpp_1c_export_finances', [$this, 'ajax_export_finances']);
        add_action('wp_ajax_nopriv_akpp_1c_export_finances', [$this, 'ajax_export_finances']);
        add_action('wp_ajax_akpp_1c_import_finances', [$this, 'ajax_import_finances']);
        add_action('wp_ajax_nopriv_akpp_1c_import_finances', [$this, 'ajax_import_finances']);
        add_action('admin_post_akpp_1c_regen_token', [$this, 'handle_regen_token']);
        add_action('admin_post_akpp_1c_save_origins', [$this, 'handle_save_origins']);
        add_action('admin_init', [__CLASS__, 'install']);
    }

    public static function install() {
        global $wpdb;
        $t = $wpdb->prefix . 'akpp_1c_log';
        if ($wpdb->get_var("SHOW TABLES LIKE '$t'") !== $t) {
            $wpdb->query("CREATE TABLE IF NOT EXISTS $t (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                created_at DATETIME NOT NULL,
                operation VARCHAR(50) NOT NULL,
                direction VARCHAR(10) NOT NULL DEFAULT 'out',
                status VARCHAR(10) NOT NULL DEFAULT 'ok',
                records INT NOT NULL DEFAULT 0,
                details TEXT,
                PRIMARY KEY (id), KEY created_at (created_at)
            ) DEFAULT CHARSET=utf8mb4");
        }
        if (!get_option('akpp_1c_token')) update_option('akpp_1c_token', self::generate_token());
    }

    public static function generate_token() { return bin2hex(random_bytes(24)); }
    public static function get_token() { $t = get_option('akpp_1c_token'); if (!$t) { $t = self::generate_token(); update_option('akpp_1c_token', $t); } return $t; }

    private function check_token() {
        $token = isset($_REQUEST['token']) ? $_REQUEST['token'] : (isset($_SERVER['HTTP_X_1C_TOKEN']) ? $_SERVER['HTTP_X_1C_TOKEN'] : '');
        return hash_equals(self::get_token(), (string) $token);
    }

    public function log($operation, $direction, $status, $records = 0, $details = '') {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'akpp_1c_log', [
            'created_at' => current_time('mysql'), 'operation' => $operation, 'direction' => $direction,
            'status' => $status, 'records' => (int) $records, 'details' => sanitize_text_field($details),
        ]);
    }

    public function add_menu() {
        add_menu_page('1С интеграция', '🔗 1С интеграция', 'akpp_manage_integrations', 'akpp-crm-integrations', [$this, 'render_page'], 'dashicons-networking', 35);
        add_submenu_page('akpp-crm-integrations', 'Обмен данными', '📤 Обмен', 'akpp_manage_integrations', 'akpp-crm-1c-exchange', [$this, 'render_page']);
        add_submenu_page('akpp-crm-integrations', 'Инструкция', '📖 Инструкция', 'akpp_manage_integrations', 'akpp-crm-1c-help', [$this, 'render_help']);
    }

    /** Ищем slug уже существующего top-level раздела «Интеграции», чтобы не плодить дубль */
    private function find_integrations_parent() {
        $files = glob(get_template_directory() . '/inc/crm/*.php');
        foreach ($files as $file) {
            if (strpos($file, 'class-akpp-1c.php') !== false) continue;
            $code = @file_get_contents($file);
            if ($code && preg_match("/add_menu_page\(\s*'[^']*'\s*,\s*'[^']*Интеграци[^']*'\s*,\s*'[^']*'\s*,\s*'([^']+)'/u", $code, $m)) {
                return $m[1];
            }
        }
        return null;
    }

    /** CORS: разрешает обмен с других доменов по whitelist (для будущих CRM на других доменах) */
    private function cors_headers() {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        $allow = array_map('trim', explode(',', (string) get_option('akpp_1c_allowed_origins', '')));
        $allow = array_filter($allow);
        $ok = in_array('*', $allow, true) || ($origin !== '' && in_array($origin, $allow, true));
        if ($ok && $origin !== '') {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: X-1C-Token, Content-Type, Authorization');
            header('Access-Control-Max-Age: 86400');
        }
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') { status_header(204); exit; }
    }

    public function render_page() {
        $token = self::get_token();
        $ajax = admin_url('admin-ajax.php');
        $export_url = add_query_arg(['action' => 'akpp_1c_export_products', 'token' => $token], $ajax);
        $import_url = add_query_arg(['action' => 'akpp_1c_import_products', 'token' => $token], $ajax);
        global $wpdb;
        $origins = get_option('akpp_1c_allowed_origins', '');
        $logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}akpp_1c_log ORDER BY created_at DESC LIMIT 20");
        include AKPP_CRM_PATH . 'templates/integrations.php';
    }

    // === EXPORT каталога товаров в XML ===
    public function ajax_export_products() {
        $this->cors_headers();
        if (!$this->check_token()) { status_header(403); echo 'Forbidden: invalid token'; exit; }
        $xml = $this->build_products_xml();
        $this->log('export_products', 'out', 'ok', substr_count($xml, '<Product '));
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="akpp45_products_' . date('Ymd_His') . '.xml"');
        echo $xml;
        exit;
    }

    public function build_products_xml() {
        global $wpdb;
        $products = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}akpp_shop_products ORDER BY id ASC");
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('DataExchange');
        $root->setAttribute('Direction', 'Магазин запчастей');
        $dom->appendChild($root);
        $prods = $dom->createElement('Products');
        $root->appendChild($prods);
        foreach ($products as $p) {
            $el = $dom->createElement('Product');
            $el->setAttribute('UID', 'product-' . (int) $p->id);
            $el->appendChild($dom->createElement('Name', self::xml_safe($p->name)));
            $el->appendChild($dom->createElement('SKU', self::xml_safe($p->sku)));
            $el->appendChild($dom->createElement('PurchasePrice', number_format((float) $p->purchase_price, 2, '.', '')));
            $el->appendChild($dom->createElement('RetailPrice', number_format((float) $p->price, 2, '.', '')));
            $el->appendChild($dom->createElement('Stock', (int) $p->stock));
            $el->appendChild($dom->createElement('Category', self::xml_safe($p->category)));
            $el->appendChild($dom->createElement('Condition', self::xml_safe($p->condition_type)));
            $el->appendChild($dom->createElement('IsActive', (int) $p->is_active));
            $prods->appendChild($el);
        }
        return $dom->saveXML();
    }

    private static function xml_safe($s) { return htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }

    // === IMPORT каталога товаров из XML ===
    public function ajax_import_products() {
        $this->cors_headers();
        if (!$this->check_token()) { wp_send_json_error(['message' => 'Forbidden: invalid token'], 403); }
        $xmlstr = '';
        if (!empty($_FILES['xml']['tmp_name'])) $xmlstr = file_get_contents($_FILES['xml']['tmp_name']);
        else $xmlstr = file_get_contents('php://input');
        if (empty($xmlstr)) { wp_send_json_error(['message' => 'XML не получен']); }
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlstr);
        libxml_use_internal_errors($prev);
        if ($xml === false) { $this->log('import_products', 'in', 'error', 0, 'XML parse error'); wp_send_json_error(['message' => 'Ошибка разбора XML']); }
        global $wpdb; $table = $wpdb->prefix . 'akpp_shop_products';
        $created = 0; $updated = 0;
        foreach ($xml->Products->Product as $pr) {
            $sku = trim((string) $pr->SKU);
            if ($sku === '') continue;
            $data = [
                'sku' => sanitize_text_field($sku),
                'name' => sanitize_text_field((string) $pr->Name),
                'purchase_price' => floatval((string) $pr->PurchasePrice),
                'price' => floatval((string) $pr->RetailPrice),
                'stock' => intval((string) $pr->Stock),
                'category' => sanitize_text_field((string) $pr->Category ?: 'parts'),
                'condition_type' => sanitize_text_field((string) $pr->Condition ?: 'new'),
                'is_active' => intval((string) ($pr->IsActive ?? 1)),
                'updated_at' => current_time('mysql'),
            ];
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE sku = %s", $sku));
            if ($existing) { $wpdb->update($table, $data, ['id' => $existing]); $updated++; }
            else { $data['created_at'] = current_time('mysql'); $wpdb->insert($table, $data); $created++; }
        }
        $this->log('import_products', 'in', 'ok', $created + $updated, "created=$created updated=$updated");
        wp_send_json_success(['message' => "Импорт завершён: создано $created, обновлено $updated", 'created' => $created, 'updated' => $updated]);
    }

    // === EXPORT финансов: сделки + заказы + оплаты ===
    public function ajax_export_finances() {
        $this->cors_headers();
        if (!$this->check_token()) { status_header(403); echo 'Forbidden: invalid token'; exit; }
        $xml = $this->build_finances_xml();
        $this->log('export_finances', 'out', 'ok', substr_count($xml, '<Deal ') + substr_count($xml, '<Order ') + substr_count($xml, '<Invoice '));
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="akpp45_finances_' . date('Ymd_His') . '.xml"');
        echo $xml;
        exit;
    }

    public function build_finances_xml() {
        global $wpdb;
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('DataExchange');
        $root->setAttribute('Direction', 'Финансы');
        $dom->appendChild($root);
        $fin = $dom->createElement('Finances');
        $root->appendChild($fin);

        // Сделки автосервиса
        $deals = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}akpp_deals ORDER BY id ASC");
        $dealsEl = $dom->createElement('Deals');
        $fin->appendChild($dealsEl);
        foreach ($deals as $d) {
            $el = $dom->createElement('Deal');
            $el->setAttribute('UID', 'deal-' . (int) $d->id);
            $el->appendChild($dom->createElement('ClientId', (int) $d->client_id));
            $el->appendChild($dom->createElement('EmployeeId', (int) $d->employee_id));
            $el->appendChild($dom->createElement('Vehicle', self::xml_safe(trim($d->make . ' ' . $d->model . ' ' . $d->year))));
            $el->appendChild($dom->createElement('VIN', self::xml_safe($d->vin)));
            $el->appendChild($dom->createElement('Problem', self::xml_safe($d->problem_description)));
            $el->appendChild($dom->createElement('ServiceCategory', self::xml_safe($d->service_category)));
            $el->appendChild($dom->createElement('Status', self::xml_safe($d->status)));
            $el->appendChild($dom->createElement('WorkCost', number_format((float) $d->work_cost, 2, '.', '')));
            $el->appendChild($dom->createElement('PartsTotal', number_format((float) $d->parts_total, 2, '.', '')));
            $el->appendChild($dom->createElement('Total', number_format((float) $d->total_amount, 2, '.', '')));
            $el->appendChild($dom->createElement('PaymentAmount', number_format((float) $d->payment_amount, 2, '.', '')));
            $el->appendChild($dom->createElement('CreatedAt', self::xml_safe($d->created_at)));
            $dealsEl->appendChild($el);
        }

        // Заказы магазина с позициями
        $orders = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}akpp_shop_orders ORDER BY id ASC");
        $ordersEl = $dom->createElement('Orders');
        $fin->appendChild($ordersEl);
        foreach ($orders as $o) {
            $el = $dom->createElement('Order');
            $el->setAttribute('UID', 'order-' . (int) $o->id);
            $el->appendChild($dom->createElement('OrderNumber', self::xml_safe($o->order_number)));
            $el->appendChild($dom->createElement('ClientName', self::xml_safe($o->client_name)));
            $el->appendChild($dom->createElement('ClientPhone', self::xml_safe($o->client_phone)));
            $el->appendChild($dom->createElement('ClientEmail', self::xml_safe($o->client_email)));
            $el->appendChild($dom->createElement('Total', number_format((float) $o->total, 2, '.', '')));
            $el->appendChild($dom->createElement('PaymentMethod', self::xml_safe($o->payment_method)));
            $el->appendChild($dom->createElement('PaymentStatus', self::xml_safe($o->payment_status)));
            $el->appendChild($dom->createElement('Status', self::xml_safe($o->status)));
            $el->appendChild($dom->createElement('DeliveryType', self::xml_safe($o->delivery_type)));
            $el->appendChild($dom->createElement('CreatedAt', self::xml_safe($o->created_at)));
            $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}akpp_shop_order_items WHERE order_id = %d", $o->id));
            $itemsEl = $dom->createElement('Items');
            foreach ($items as $it) {
                $itEl = $dom->createElement('Item');
                $itEl->appendChild($dom->createElement('SKU', self::xml_safe($it->product_sku)));
                $itEl->appendChild($dom->createElement('Name', self::xml_safe($it->product_name)));
                $itEl->appendChild($dom->createElement('Qty', (int) $it->quantity));
                $itEl->appendChild($dom->createElement('Price', number_format((float) $it->price, 2, '.', '')));
                $itEl->appendChild($dom->createElement('Total', number_format((float) $it->total, 2, '.', '')));
                $itemsEl->appendChild($itEl);
            }
            $el->appendChild($itemsEl);
            $ordersEl->appendChild($el);
        }

        // Оплаты (оплаченные заказы + сделки с payment_amount)
        $invEl = $dom->createElement('Invoices');
        $fin->appendChild($invEl);
        foreach ($orders as $o) {
            if ($o->payment_status === 'paid') {
                $p = $dom->createElement('Invoice');
                $p->setAttribute('UID', 'inv-order-' . (int) $o->id);
                $p->appendChild($dom->createElement('Ref', 'order-' . (int) $o->id));
                $p->appendChild($dom->createElement('Amount', number_format((float) $o->total, 2, '.', '')));
                $p->appendChild($dom->createElement('Method', self::xml_safe($o->payment_method)));
                $p->appendChild($dom->createElement('Date', self::xml_safe($o->created_at)));
                $invEl->appendChild($p);
            }
        }
        foreach ($deals as $d) {
            if ((float) $d->payment_amount > 0) {
                $p = $dom->createElement('Invoice');
                $p->setAttribute('UID', 'inv-deal-' . (int) $d->id);
                $p->appendChild($dom->createElement('Ref', 'deal-' . (int) $d->id));
                $p->appendChild($dom->createElement('Amount', number_format((float) $d->payment_amount, 2, '.', '')));
                $p->appendChild($dom->createElement('Date', self::xml_safe($d->updated_at ?: $d->created_at)));
                $invEl->appendChild($p);
            }
        }
        return $dom->saveXML();
    }

    // === IMPORT статусов/оплат из 1С (по UID deal-N / order-N) ===
    public function ajax_import_finances() {
        $this->cors_headers();
        if (!$this->check_token()) { wp_send_json_error(['message' => 'Forbidden: invalid token'], 403); }
        $xmlstr = !empty($_FILES['xml']['tmp_name']) ? file_get_contents($_FILES['xml']['tmp_name']) : file_get_contents('php://input');
        if (empty($xmlstr)) { wp_send_json_error(['message' => 'XML не получен']); }
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlstr);
        libxml_use_internal_errors($prev);
        if ($xml === false) { $this->log('import_finances', 'in', 'error', 0, 'XML parse error'); wp_send_json_error(['message' => 'Ошибка разбора XML']); }
        global $wpdb;
        $deals_upd = 0; $orders_upd = 0;
        if (isset($xml->Deals)) foreach ($xml->Deals->Deal as $dl) {
            $uid = (string) $dl['UID'];
            if (preg_match('/^deal-(\d+)$/', $uid, $m)) {
                $data = [];
                if (isset($dl->Status) && (string) $dl->Status !== '') $data['status'] = sanitize_text_field((string) $dl->Status);
                if (isset($dl->PaymentAmount) && (string) $dl->PaymentAmount !== '') $data['payment_amount'] = floatval((string) $dl->PaymentAmount);
                if ($data) { $data['updated_at'] = current_time('mysql'); $wpdb->update($wpdb->prefix . 'akpp_deals', $data, ['id' => (int) $m[1]]); $deals_upd++; }
            }
        }
        if (isset($xml->Orders)) foreach ($xml->Orders->Order as $or) {
            $uid = (string) $or['UID'];
            if (preg_match('/^order-(\d+)$/', $uid, $m)) {
                $data = [];
                if (isset($or->Status) && (string) $or->Status !== '') $data['status'] = sanitize_text_field((string) $or->Status);
                if (isset($or->PaymentStatus) && (string) $or->PaymentStatus !== '') $data['payment_status'] = sanitize_text_field((string) $or->PaymentStatus);
                if ($data) { $data['updated_at'] = current_time('mysql'); $wpdb->update($wpdb->prefix . 'akpp_shop_orders', $data, ['id' => (int) $m[1]]); $orders_upd++; }
            }
        }
        $this->log('import_finances', 'in', 'ok', $deals_upd + $orders_upd, "deals=$deals_upd orders=$orders_upd");
        wp_send_json_success(['message' => "Импорт финансов: сделок $deals_upd, заказов $orders_upd", 'deals' => $deals_upd, 'orders' => $orders_upd]);
    }
    public function handle_save_origins() {
        if (!current_user_can('manage_options')) wp_die('Недостаточно прав');
        check_admin_referer('akpp_1c_save_origins');
        $val = sanitize_text_field($_POST['allowed_origins'] ?? '');
        update_option('akpp_1c_allowed_origins', $val);
        wp_safe_redirect(admin_url('admin.php?page=akpp-crm-1c-exchange&origins=1'));
        exit;
    }

    public function render_help() {
        $token = self::get_token();
        $ajax = admin_url('admin-ajax.php');
        $export_url = add_query_arg(['action' => 'akpp_1c_export_products', 'token' => $token], $ajax);
        $import_url = add_query_arg(['action' => 'akpp_1c_import_products', 'token' => $token], $ajax);
        $base = home_url('/');
        $exch = admin_url('admin.php?page=akpp-crm-1c-exchange');
        if (!empty($_GET['print'])) { $this->render_help_standalone(compact('token','ajax','export_url','import_url','base','exch')); return; }
        $print_mode = false;
        include AKPP_CRM_PATH . 'templates/integrations-help.php';
    }

    /** Чистый документ для печати/PDF: без админ-бара и сайдбара — ничего не обрезается */
    public function render_help_standalone($vars) {
        extract($vars);
        $print_mode = true;
        ?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Инструкция 1С — АКПП45</title>
<style>
  html,body{ margin:0; padding:0; background:#0a0f1c; }
  body{ font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; }
  @media print{ html,body{ background:#fff !important; } }
</style>
</head>
<body>
<?php include AKPP_CRM_PATH . 'templates/integrations-help.php'; ?>
</body>
</html><?php
        exit;
    }
    public function handle_regen_token() {
        if (!current_user_can('manage_options')) wp_die('Недостаточно прав');
        check_admin_referer('akpp_1c_regen');
        update_option('akpp_1c_token', self::generate_token());
        wp_safe_redirect(admin_url('admin.php?page=akpp-crm-integrations&regen=1'));
        exit;
    }
}
AKPP_1C::get_instance();
