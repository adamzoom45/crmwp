<?php
/**
 * АКПП45 — Лицензирование / аренда CRM (многосайтовость, без права доработки кода)
 * Master-сервер лицензий + клиентский агент + REST API проверки/активации.
 * Модель: код активации (привязка домена+fingerprint) + контроль по времени оплаты.
 */
if (!defined('ABSPATH')) exit;

class AKPP_License {
    private static $instance = null;
    public static function get_instance() { if (!self::$instance) self::$instance = new self(); return self::$instance; }

    private function __construct() {
        add_action('admin_init', [__CLASS__, 'install']);
        add_action('rest_api_init', [$this, 'register_rest']);          // master API
        add_action('admin_menu', [$this, 'add_menu']);                  // master-панель
        add_action('admin_post_akpp_license_create', [$this, 'handle_create']);
        add_action('admin_post_akpp_license_block', [$this, 'handle_block']);
        add_action('admin_post_akpp_license_reset', [$this, 'handle_reset']);
        add_action('admin_post_akpp_license_extend', [$this, 'handle_extend']);
        add_action('admin_post_akpp_license_offline_issue', [$this, 'handle_offline_issue']);
        // клиентский агент (только если задан URL master-сервера)
        if (get_option('akpp_license_token')) {
            add_action('admin_init', [$this, 'client_check']);
            add_action('admin_notices', [$this, 'client_banner']);
        }
    }

    // ===================== СХЕМА =====================
    public static function install() {
        global $wpdb; $t = $wpdb->prefix . 'akpp_licenses';
        if ($wpdb->get_var("SHOW TABLES LIKE '$t'") !== $t) {
            $wpdb->query("CREATE TABLE IF NOT EXISTS $t (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                activation_key VARCHAR(64) NOT NULL,
                installation_id VARCHAR(64) DEFAULT NULL,
                plan_months INT NOT NULL DEFAULT 12,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                domain VARCHAR(190) DEFAULT NULL,
                fingerprint VARCHAR(128) DEFAULT NULL,
                activated_at DATETIME DEFAULT NULL,
                valid_until DATETIME DEFAULT NULL,
                last_check DATETIME DEFAULT NULL,
                note VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY activation_key (activation_key),
                UNIQUE KEY installation_id (installation_id),
                KEY status (status), KEY valid_until (valid_until)
            ) DEFAULT CHARSET=utf8mb4");
        }
        // колонка для офлайн-сертификата (подписанный токен)
        $cols = array_column($wpdb->get_results("SHOW COLUMNS FROM $t", ARRAY_A), 'Field');
        if (!in_array('license_token', $cols, true)) {
            $wpdb->query("ALTER TABLE $t ADD COLUMN license_token MEDIUMTEXT DEFAULT NULL AFTER fingerprint");
        }
    }

    // ===================== УТИЛИТЫ =====================
    public static function gen_key() {
        $p = []; for ($i = 0; $i < 4; $i++) $p[] = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        return 'AKPP-' . implode('-', $p);
    }
    public static function gen_installation_id() { return bin2hex(random_bytes(16)); }

    /** Отпечаток целостности кода (защита от доработки): хеш набора ключевых файлов */
    public static function code_fingerprint() {
        $base = get_template_directory();
        $files = ['functions.php','header.php','style.css',
            'inc/crm/class-akpp-crm.php','inc/crm/class-akpp-shop.php',
            'inc/crm/class-akpp-account.php','inc/crm/class-akpp-auth.php'];
        $h = '';
        foreach ($files as $rel) { $f = $base . '/' . $rel; if (is_file($f)) $h .= md5_file($f); }
        return $h === '' ? 'empty' : sha1($h);
    }

    public static function client_url() { return trim((string) get_option('akpp_license_client_url', '')); }

    // ===================== КРИПТО: ключи + подписанные сертификаты (офлайн-валидация) =====================
    /** Пара ключей: приватный только на мастере, публичный вшивается в коробку CRM */
    public static function keypair() {
        $kp = get_option('akpp_license_keypair');
        if (is_array($kp) && !empty($kp['priv']) && !empty($kp['pub'])) return $kp;
        if (function_exists('sodium_crypto_sign_keypair')) {
            $k = sodium_crypto_sign_keypair();
            $kp = ['engine' => 'ed25519', 'priv' => base64_encode(sodium_crypto_sign_secretkey($k)), 'pub' => base64_encode(sodium_crypto_sign_publickey($k))];
        } else {
            $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($res, $priv); $det = openssl_pkey_get_details($res);
            $kp = ['engine' => 'rsa', 'priv' => base64_encode($priv), 'pub' => base64_encode($det['key'])];
        }
        update_option('akpp_license_keypair', $kp);
        return $kp;
    }
    public static function pubkey() {
        if (defined('AKPP_LICENSE_PUBKEY') && AKPP_LICENSE_PUBKEY) return AKPP_LICENSE_PUBKEY; // коробка: публичный ключ вшит, приватного нет
        $kp = self::keypair(); return $kp['pub'];
    }

    private static function canon($payload) { ksort($payload); return wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }

    /** Подпись payload приватным ключом мастера */
    public static function sign_payload($payload) {
        $kp = self::keypair(); $canon = self::canon($payload);
        if (($kp['engine'] ?? '') === 'rsa') {
            $key = openssl_pkey_get_private(base64_decode($kp['priv']));
            openssl_sign($canon, $sig, $key, OPENSSL_ALGO_SHA256);
            return base64_encode($sig);
        }
        return base64_encode(sodium_crypto_sign_detached($canon, base64_decode($kp['priv'])));
    }

    /** Проверка подписи ПУБЛИЧНЫМ ключом (работает в коробке без мастера) */
    public static function verify_signature($payload, $sig_b64, $pub_b64 = null) {
        $pub = $pub_b64 ?: self::pubkey(); $canon = self::canon($payload); $sig = base64_decode($sig_b64);
        $kp = get_option('akpp_license_keypair', []); $engine = $pub_b64 ? (strlen(base64_decode($pub)) > 64 ? 'rsa' : 'ed25519') : ($kp['engine'] ?? 'ed25519');
        if ($engine === 'rsa') {
            $key = openssl_pkey_get_public(base64_decode($pub));
            return $key && openssl_verify($canon, $sig, $key, OPENSSL_ALGO_SHA256) === 1;
        }
        try { return sodium_crypto_sign_verify_detached($sig, $canon, base64_decode($pub)); } catch (\Exception $e) { return false; }
    }

    /** Выпуск сертификата (мастер): payload + подпись -> токен "base64(payload).base64(sig)" */
    public static function issue_certificate($iid, $domain, $fp, $plan_months, $valid_until, $note = '', $unlimited = false) {
        $payload = ['v' => 1, 'iid' => $iid, 'domain' => $domain, 'fp' => $fp, 'plan' => (int) $plan_months,
            'iat' => current_time('timestamp'), 'exp' => strtotime($valid_until), 'note' => (string) $note];
        if ($unlimited) $payload['unlimited'] = true;
        return base64_encode(self::canon($payload)) . '.' . self::sign_payload($payload);
    }

    /** Разбор + проверка сертификата ЛОКАЛЬНО (без сети). Возвращает ['ok'=>bool,'payload'=>...,'reason'=>...] */
    public static function verify_certificate($token, $domain = null, $fp = null, $pub_b64 = null) {
        if (!is_string($token) || strpos($token, '.') === false) return ['ok' => false, 'reason' => 'bad_format'];
        [$p_b64, $sig] = array_pad(explode('.', $token, 2), 2, '');
        $payload = json_decode(base64_decode($p_b64), true);
        if (!is_array($payload)) return ['ok' => false, 'reason' => 'bad_payload'];
        if (!self::verify_signature($payload, $sig, $pub_b64)) return ['ok' => false, 'reason' => 'bad_signature'];
        if ($domain !== null && !empty($payload['domain']) && $payload['domain'] !== $domain) return ['ok' => false, 'reason' => 'domain_mismatch', 'payload' => $payload];
        if (empty($payload['unlimited']) && $fp !== null && !empty($payload['fp']) && $payload['fp'] !== $fp) return ['ok' => false, 'reason' => 'code_modified', 'payload' => $payload];
        if (!empty($payload['exp']) && $payload['exp'] < current_time('timestamp')) return ['ok' => false, 'reason' => 'expired', 'payload' => $payload];
        return ['ok' => true, 'payload' => $payload];
    }

    /** Офлайн-активация без сети: клиент -> строка запроса; мастер -> строка сертификата */
    public static function encode_activation_request($code, $domain, $fp) {
        return 'AKPPREQ-' . base64_encode(wp_json_encode(['code' => $code, 'domain' => $domain, 'fp' => $fp, 'ts' => current_time('timestamp')]));
    }
    public static function decode_activation_request($str) {
        $str = trim($str); if (strpos($str, 'AKPPREQ-') !== 0) return null;
        $d = json_decode(base64_decode(substr($str, 8)), true);
        return is_array($d) ? $d : null;
    }
    // ===================== MASTER: REST API =====================
    public function register_rest() {
        register_rest_route('akpp-license/v1', '/activate', ['methods' => 'POST', 'callback' => [$this, 'rest_activate'], 'permission_callback' => '__return_true']);
        register_rest_route('akpp-license/v1', '/check',     ['methods' => 'POST', 'callback' => [$this, 'rest_check'],     'permission_callback' => '__return_true']);
    }

    public function rest_activate($req) {
        global $wpdb; $t = $wpdb->prefix . 'akpp_licenses';
        $key = sanitize_text_field($req->get_param('activation_key'));
        $domain = sanitize_text_field($req->get_param('domain'));
        $fp = sanitize_text_field($req->get_param('fingerprint'));
        if (!$key || !$domain || !$fp) return new WP_REST_Response(['ok' => false, 'reason' => 'missing_params'], 400);
        $lic = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE activation_key = %s", $key));
        if (!$lic) return new WP_REST_Response(['ok' => false, 'reason' => 'key_not_found'], 404);
        if ($lic->status === 'blocked') return new WP_REST_Response(['ok' => false, 'reason' => 'key_blocked'], 403);
        // уже активирована на другом домене/коде — запрет (защита от копирования)
        if ($lic->status === 'active' && ($lic->domain !== $domain || $lic->fingerprint !== $fp)) {
            return new WP_REST_Response(['ok' => false, 'reason' => 'already_bound_elsewhere'], 403);
        }
        $iid = $lic->installation_id ?: self::gen_installation_id();
        $now = current_time('mysql');
        $wpdb->update($t, [
            'installation_id' => $iid, 'status' => 'active', 'domain' => $domain, 'fingerprint' => $fp,
            'activated_at' => $lic->activated_at ?: $now,
            'valid_until' => date('Y-m-d H:i:s', strtotime($now . ' +' . (int) $lic->plan_months . ' months')),
            'last_check' => $now,
        ], ['id' => $lic->id]);
        $valid = date('Y-m-d H:i:s', strtotime($now . ' +' . (int) $lic->plan_months . ' months'));
        $token = self::issue_certificate($iid, $domain, $fp, (int) $lic->plan_months, $valid, $lic->note);
        $wpdb->update($t, ['license_token' => $token], ['id' => $lic->id]);
        return new WP_REST_Response(['ok' => true, 'installation_id' => $iid, 'valid_until' => $valid, 'license_token' => $token]);
    }

    public function rest_check($req) {
        global $wpdb; $t = $wpdb->prefix . 'akpp_licenses';
        $iid = sanitize_text_field($req->get_param('installation_id'));
        $domain = sanitize_text_field($req->get_param('domain'));
        $fp = sanitize_text_field($req->get_param('fingerprint'));
        if (!$iid) return new WP_REST_Response(['ok' => false, 'status' => 'not_found'], 404);
        $lic = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE installation_id = %s", $iid));
        if (!$lic) return new WP_REST_Response(['ok' => false, 'status' => 'not_found'], 404);
        $wpdb->update($t, ['last_check' => current_time('mysql')], ['id' => $lic->id]);
        if ($lic->status === 'blocked') return new WP_REST_Response(['ok' => false, 'status' => 'blocked', 'reason' => 'license_blocked']);
        if ($lic->domain && $lic->domain !== $domain) return new WP_REST_Response(['ok' => false, 'status' => 'blocked', 'reason' => 'domain_mismatch']);
        if ($lic->fingerprint && $lic->fingerprint !== $fp) return new WP_REST_Response(['ok' => false, 'status' => 'blocked', 'reason' => 'code_modified']);
        if (strtotime($lic->valid_until) < current_time('timestamp')) return new WP_REST_Response(['ok' => false, 'status' => 'expired', 'valid_until' => $lic->valid_until]);
        return new WP_REST_Response(['ok' => true, 'status' => 'active', 'valid_until' => $lic->valid_until, 'plan_months' => (int) $lic->plan_months]);
    }

    // ===================== КЛИЕНТСКИЙ АГЕНТ =====================
    public function client_check() {
        if (get_transient('akpp_license_state_ts')) return; // не чаще раза в час (офлайн, дёшево)
        set_transient('akpp_license_state_ts', 1, HOUR_IN_SECONDS);
        $token = get_option('akpp_license_token');
        if (!$token) { update_option('akpp_license_state', ['status' => 'not_activated']); return; }
        $r = self::verify_certificate($token, wp_parse_url(home_url(), PHP_URL_HOST), self::code_fingerprint());
        if ($r['ok']) {
            update_option('akpp_license_state', ['status' => 'active', 'valid_until' => date('Y-m-d H:i:s', $r['payload']['exp']), 'plan_months' => (int) ($r['payload']['plan'] ?? 0)]);
        } else {
            $map = ['expired' => 'expired', 'domain_mismatch' => 'blocked', 'code_modified' => 'blocked', 'bad_signature' => 'blocked', 'bad_format' => 'blocked', 'bad_payload' => 'blocked'];
            update_option('akpp_license_state', ['status' => $map[$r['reason']] ?? 'blocked', 'reason' => $r['reason'], 'valid_until' => isset($r['payload']['exp']) ? date('Y-m-d H:i:s', $r['payload']['exp']) : null]);
        }
    }

    public static function client_state() { $s = get_option('akpp_license_state', []); return is_array($s) ? $s : []; }
    public static function is_locked() { $s = self::client_state(); return in_array($s['status'] ?? '', ['expired', 'blocked'], true); }

    public function client_banner() {
        $s = self::client_state(); $st = $s['status'] ?? '';
        if ($st === 'blocked') $msg = '🔒 Лицензия CRM заблокирована (код изменён или домен не совпадает). Свяжитесь с поставщиком.';
        elseif ($st === 'expired') $msg = '⏳ Аренда CRM истекла ' . esc_html($s['valid_until'] ?? '') . '. Продлите подписку для продолжения работы.';
        else return;
        echo '<div class="notice notice-error"><p><strong>' . $msg . '</strong></p></div>';
    }

    // ===================== MASTER-ПАНЕЛЬ =====================
    public function add_menu() {
        add_menu_page('Лицензии', '🔑 Лицензии', 'manage_options', 'akpp-licenses', [$this, 'render_panel'], 'dashicons-lock', 36);
    }

    public function render_panel() {
        if (defined('AKPP_TENANT_MODE') && AKPP_TENANT_MODE) {
            // смежный сайт-арендатор: read-only вид СВОЕЙ лицензии (без master-функций)
            $lic_token = get_option('akpp_license_token');
            $lic_v = $lic_token ? self::verify_certificate($lic_token) : ['ok' => false, 'reason' => 'no_token'];
            $lic_payload = $lic_v['payload'] ?? [];
            $lic_state = self::client_state();
            $lic_fp_now = self::code_fingerprint();
            $lic_unlimited = !empty($lic_payload['unlimited']);
            $now = current_time('timestamp');
            $exp = (int) ($lic_payload['exp'] ?? 0);
            $iat = (int) ($lic_payload['iat'] ?? $now);
            $lic_days_left = $exp > $now ? (int) ceil(($exp - $now) / 86400) : 0;
            $span = max(1, $exp - $iat);
            $lic_pct = $lic_unlimited ? 100 : max(0, min(100, (int) round(($now - $iat) / $span * 100)));
            $lic_domain_match = empty($lic_payload['domain']) || $lic_payload['domain'] === wp_parse_url(home_url(), PHP_URL_HOST);
            $lic_fp_match = $lic_unlimited ? null : (!empty($lic_payload['fp']) && $lic_payload['fp'] === $lic_fp_now);
            include AKPP_CRM_PATH . 'templates/license-tenant.php';
            return;
        }
        include AKPP_CRM_PATH . 'templates/licenses.php'; // мастер: панель управления
    }

    public function handle_create() {
        if (!current_user_can('manage_options')) wp_die('Нет прав');
        check_admin_referer('akpp_license_create');
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'akpp_licenses', [
            'activation_key' => self::gen_key(), 'plan_months' => max(1, intval($_POST['plan_months'] ?? 12)),
            'status' => 'pending', 'note' => sanitize_text_field($_POST['note'] ?? ''), 'created_at' => current_time('mysql'),
        ]);
        wp_safe_redirect(admin_url('admin.php?page=akpp-licenses&created=1')); exit;
    }

    public function handle_offline_issue() {
        if (!current_user_can('manage_options')) wp_die('Нет прав');
        check_admin_referer('akpp_license_offline_issue');
        global $wpdb; $t = $wpdb->prefix . 'akpp_licenses';
        $req = self::decode_activation_request(wp_unslash($_POST['request'] ?? ''));
        if (!$req) { wp_safe_redirect(admin_url('admin.php?page=akpp-licenses&offerr=badreq')); exit; }
        $lic = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE activation_key = %s", sanitize_text_field($req['code'])));
        if (!$lic) { wp_safe_redirect(admin_url('admin.php?page=akpp-licenses&offerr=nokey')); exit; }
        if ($lic->status === 'blocked') { wp_safe_redirect(admin_url('admin.php?page=akpp-licenses&offerr=blocked')); exit; }
        $iid = $lic->installation_id ?: self::gen_installation_id();
        $now = current_time('mysql');
        $valid = ($lic->valid_until && strtotime($lic->valid_until) > current_time('timestamp')) ? $lic->valid_until : date('Y-m-d H:i:s', strtotime($now . ' +' . (int) $lic->plan_months . ' months'));
        $tok = self::issue_certificate($iid, $req['domain'], $req['fp'], (int) $lic->plan_months, $valid, $lic->note);
        $wpdb->update($t, ['installation_id' => $iid, 'domain' => $req['domain'], 'fingerprint' => $req['fp'], 'status' => 'active',
            'activated_at' => $lic->activated_at ?: $now, 'valid_until' => $valid, 'license_token' => $tok, 'last_check' => $now], ['id' => $lic->id]);
        set_transient('akpp_license_last_issued', ['key' => $lic->activation_key, 'token' => $tok, 'domain' => $req['domain'], 'valid' => $valid], HOUR_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=akpp-licenses&issued=1')); exit;
    }
    public function handle_extend() {
        if (!current_user_can('manage_options')) wp_die('Нет прав');
        $id = intval($_POST['id']);
        check_admin_referer('akpp_license_extend_' . $id);
        global $wpdb; $t = $wpdb->prefix . 'akpp_licenses';
        $lic = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $id));
        if ($lic && $lic->status !== 'blocked') {
            $months = max(1, intval($_POST['months'] ?? 12));
            $base = ($lic->valid_until && strtotime($lic->valid_until) > current_time('timestamp')) ? $lic->valid_until : current_time('mysql');
            $new = date('Y-m-d H:i:s', strtotime($base . ' +' . $months . ' months'));
            $upd = ['valid_until' => $new];
            if ($lic->status === 'expired') $upd['status'] = 'active';
            $wpdb->update($t, $upd, ['id' => $id]);
            $lic2 = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $id));
            if ($lic2 && $lic2->installation_id && $lic2->domain && $lic2->fingerprint) {
                $tok = self::issue_certificate($lic2->installation_id, $lic2->domain, $lic2->fingerprint, (int) $lic2->plan_months, $new, $lic2->note);
                $wpdb->update($t, ['license_token' => $tok], ['id' => $id]);
            }
        }
        wp_safe_redirect(admin_url('admin.php?page=akpp-licenses&extended=1')); exit;
    }
    public function handle_block() {
        if (!current_user_can('manage_options')) wp_die('Нет прав');
        check_admin_referer('akpp_license_block_' . intval($_POST['id']));
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'akpp_licenses', ['status' => 'blocked'], ['id' => intval($_POST['id'])]);
        wp_safe_redirect(admin_url('admin.php?page=akpp-licenses')); exit;
    }

    public function handle_reset() {
        if (!current_user_can('manage_options')) wp_die('Нет прав');
        check_admin_referer('akpp_license_reset_' . intval($_POST['id']));
        global $wpdb; // сброс привязки домена/кода (для переноса на другой домен)
        $wpdb->update($wpdb->prefix . 'akpp_licenses', ['domain' => null, 'fingerprint' => null, 'installation_id' => null, 'status' => 'pending'], ['id' => intval($_POST['id'])]);
        wp_safe_redirect(admin_url('admin.php?page=akpp-licenses')); exit;
    }
}
AKPP_License::get_instance();
