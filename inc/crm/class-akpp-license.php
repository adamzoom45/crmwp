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
        // akpp-lic-unhook-marker
        add_action('admin_post_akpp_license_unblock', [$this, 'handle_unblock']);
        add_action('admin_post_akpp_license_autorenew', [$this, 'handle_autorenew']);
        add_action('akpp_license_autorenew_cron', [$this, 'cron_autorenew']);
        if (get_option('akpp_master_mode') && !wp_next_scheduled('akpp_license_autorenew_cron')) wp_schedule_event(time() + 60, 'daily', 'akpp_license_autorenew_cron');
        if (get_option('akpp_license_token')) {
            add_action('akpp_license_online_ping', [$this, 'online_ping']);
            add_action('init', [$this, 'online_check_apply']);
            add_action('template_redirect', [$this, 'front_lock'], 1);
            add_action('admin_init', [$this, 'admin_lock'], 1);
            add_action('rest_api_init', [$this, 'rest_lock'], 1);
            if (!wp_next_scheduled('akpp_license_online_ping')) wp_schedule_event(time() + 120, 'daily', 'akpp_license_online_ping');
        }
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
    public static function issue_certificate($iid, $domain, $fp, $plan_months, $valid_until, $note = '', $unlimited = false, $addons = []) {
        $payload = ['v' => 1, 'iid' => $iid, 'domain' => $domain, 'fp' => $fp, 'plan' => (int) $plan_months,
            'iat' => current_time('timestamp'), 'exp' => strtotime($valid_until), 'note' => (string) $note];
        if ($unlimited) $payload['unlimited'] = true;
        if (!empty($addons)) $payload['addons'] = array_values((array) $addons);
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
        // license_token_lookup_by_domain
        if (!$iid && $domain) {
            $lic0 = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE domain = %s LIMIT 1", $domain));
            if ($lic0) $iid = (string) $lic0->installation_id;
        }
        if (!$iid) return new WP_REST_Response(['ok' => false, 'status' => 'not_found'], 404);
        $lic = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE installation_id = %s", $iid));
        if (!$lic) return new WP_REST_Response(['ok' => false, 'status' => 'not_found'], 404);
        $wpdb->update($t, ['last_check' => current_time('mysql')], ['id' => $lic->id]);
        if ($lic->status === 'blocked') return new WP_REST_Response(['ok' => false, 'status' => 'blocked', 'reason' => 'license_blocked']);
        if ($lic->domain && $lic->domain !== $domain) return new WP_REST_Response(['ok' => false, 'status' => 'blocked', 'reason' => 'domain_mismatch']);
        $unl = ((int) $lic->plan_months >= 600) || stripos((string) $lic->note, 'безлимит') !== false || $lic->fingerprint === 'unlimited-owner';
        if (!$unl && $lic->fingerprint && $lic->fingerprint !== $fp) return new WP_REST_Response(['ok' => false, 'status' => 'blocked', 'reason' => 'code_modified']);
        if (strtotime($lic->valid_until) < current_time('timestamp')) return new WP_REST_Response(['ok' => false, 'status' => 'expired', 'valid_until' => $lic->valid_until]);
        return new WP_REST_Response(['ok' => true, 'status' => 'active', 'valid_until' => $lic->valid_until, 'plan_months' => (int) $lic->plan_months, 'addons' => self::decode_addons($lic->addons ?? null), 'license_token' => (string) $lic->license_token]);
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
            if (array_key_exists('addons', $r['payload'])) update_option('akpp_license_features', (array) $r['payload']['addons']); else delete_option('akpp_license_features');
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
            'addons' => wp_json_encode(array_values(array_intersect((array) ($_POST['addons'] ?? []), self::ADDON_WHITELIST))), 'status' => 'pending', 'note' => sanitize_text_field($_POST['note'] ?? ''), 'created_at' => current_time('mysql'),
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
                $tok = self::issue_certificate($lic2->installation_id, $lic2->domain, $lic2->fingerprint, (int) $lic2->plan_months, $new, $lic2->note, false, self::decode_addons($lic2->addons ?? null));
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

    /** Разблокировка вручную: статус active + перевыпуск сертификата */
    /** Онлайн-сверка с мастером: применяет blocked/expired/active (троттлинг 5 мин) */
    public function online_check_apply() {
        if (get_option('akpp_master_mode')) return;
        $token = get_option('akpp_license_token');
        $url = self::master_url();
        if (!$token || !$url) return;
        if (get_transient('akpp_license_ping_ts')) return;
        set_transient('akpp_license_ping_ts', 1, 300);
        $res = wp_remote_post(trailingslashit($url) . 'wp-json/akpp-license/v1/check', [
            'body' => ['domain' => wp_parse_url(home_url(), PHP_URL_HOST), 'fingerprint' => self::code_fingerprint()],
            'timeout' => 10,
        ]);
        if (is_wp_error($res)) return; // мастер недоступен — текущее состояние не трогаем
        $data = json_decode(wp_remote_retrieve_body($res), true);
        if (!is_array($data)) return;
        if (!empty($data['ok'])) {
            update_option('akpp_license_state', ['status' => 'active', 'valid_until' => $data['valid_until'] ?? null, 'plan_months' => (int) ($data['plan_months'] ?? 0)]);
            if (!empty($data['license_token']) && $data['license_token'] !== $token) {
                $v = self::verify_certificate($data['license_token'], wp_parse_url(home_url(), PHP_URL_HOST), self::code_fingerprint());
                if (!empty($v['ok'])) update_option('akpp_license_token', $data['license_token']);
            }
            if (array_key_exists('addons', $data)) update_option('akpp_license_features', (array) $data['addons']);
        } else {
            $st = (string) ($data['status'] ?? 'blocked');
            if (in_array($st, ['blocked', 'expired'], true)) {
                update_option('akpp_license_state', ['status' => $st, 'reason' => (string) ($data['reason'] ?? ''), 'valid_until' => $data['valid_until'] ?? null]);
            }
        }
    }

    /** Блокировка фронта: 503 + экран, когда мастер сказал blocked/expired */
    public function front_lock() {
        if (get_option('akpp_master_mode')) return;
        if (!get_option('akpp_license_token')) return;
        if (!self::is_locked()) return;
        $p = (string) ($_SERVER['REQUEST_URI'] ?? '');
        foreach (['/wp-login.php', '/wp-admin/', '/wp-cron.php', '/wp-json/akpp-license', 'robots.txt', 'favicon'] as $x) {
            if (strpos($p, $x) !== false) return;
        }
        status_header(503);
        header('Retry-After: 3600');
        nocache_headers();
        echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Сервис приостановлен</title><style>body{margin:0;background:#0a0f1c;color:#e2e8f0;font-family:system-ui,"Segoe UI",Roboto,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center}.c{max-width:560px;margin:16px;padding:40px;border:1px solid #2d3748;border-radius:18px;background:#1a1f2e;text-align:center}h1{color:#ff6b6b;margin:0 0 12px;font-size:26px}p{color:#a0aec0;line-height:1.6}</style></head><body><div class="c"><h1>🔒 Доступ приостановлен</h1><p>Аренда CRM приостановлена поставщиком: лицензия заблокирована или истекла.</p><p>Свяжитесь с поставщиком для продления — после этого сайт включится автоматически.</p></div></body></html>';
        exit;
    }
    /** Точка входа мастера: константа > шифрованная опция > миграция со старой прямой опции */
    public static function master_url() {
        if (defined('AKPP_LICENSE_MASTER_URL') && AKPP_LICENSE_MASTER_URL) return AKPP_LICENSE_MASTER_URL;
        $enc = (string) get_option('akpp_lc_ep', '');
        if ($enc !== '') { $dec = self::ep_decrypt($enc); if ($dec !== '') return $dec; }
        $plain = (string) get_option('akpp_license_client_url', '');
        if ($plain !== '') { self::ep_set($plain); return $plain; }
        return '';
    }

    private static function ep_key() { return hash('sha256', wp_salt('auth') . '|akpp-lc-ep', true); }

    private static function ep_encrypt($v) {
        if (!function_exists('openssl_encrypt')) return '';
        $iv = random_bytes(16);
        return base64_encode($iv . openssl_encrypt((string) $v, 'aes-256-cbc', self::ep_key(), OPENSSL_RAW_DATA, $iv));
    }

    private static function ep_decrypt($b) {
        if (!function_exists('openssl_decrypt')) return '';
        $raw = base64_decode((string) $b, true);
        if ($raw === false || strlen($raw) < 17) return '';
        return (string) openssl_decrypt(substr($raw, 16), 'aes-256-cbc', self::ep_key(), OPENSSL_RAW_DATA, substr($raw, 0, 16));
    }

    /** Пишет точку входа ТОЛЬКО в шифрованном виде, прямую опцию удаляет */
    public static function ep_set($url) {
        $e = self::ep_encrypt($url);
        if ($e === '') return false;
        update_option('akpp_lc_ep', $e);
        delete_option('akpp_license_client_url');
        return true;
    }

    /** Блокировка админки целиком (когда мастер сказал blocked/expired) */
    public function admin_lock() {
        if (get_option('akpp_master_mode')) return;
        if (!get_option('akpp_license_token')) return;
        if (!self::is_locked()) return;
        status_header(503);
        header('Retry-After: 3600');
        nocache_headers();
        echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Заблокировано</title><style>body{margin:0;background:#0a0f1c;color:#e2e8f0;font-family:system-ui,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center}.c{max-width:560px;margin:16px;padding:40px;border:1px solid #2d3748;border-radius:18px;background:#1a1f2e;text-align:center}h1{color:#ff6b6b;font-size:24px;margin:0 0 12px}p{color:#a0aec0;line-height:1.6}</style></head><body><div class="c"><h1>🔒 Панель управления заблокирована</h1><p>Доступ приостановлен поставщиком лицензии. После продления доступ восстановится автоматически.</p></div></body></html>';
        exit;
    }

    /** Блокировка REST (кроме лицензионных маршрутов) */
    public function rest_lock() {
        if (get_option('akpp_master_mode')) return;
        if (!get_option('akpp_license_token')) return;
        if (!self::is_locked()) return;
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if (strpos($uri, 'akpp-license') !== false) return;
        status_header(403);
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode(['code' => 'license_locked']);
        exit;
    }
    public function handle_unblock() {
        if (!current_user_can('manage_options')) wp_die('Нет прав');
        $id = intval($_POST['id']);
        check_admin_referer('akpp_license_unblock_' . $id);
        global $wpdb; $t = $wpdb->prefix . 'akpp_licenses';
        $lic = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $id));
        if ($lic) {
            $upd = ['status' => 'active'];
            if (strtotime($lic->valid_until) < current_time('timestamp')) {
                $upd['valid_until'] = date('Y-m-d H:i:s', strtotime(current_time('mysql') . ' +' . (int) $lic->plan_months . ' months'));
            }
            $wpdb->update($t, $upd, ['id' => $id]);
            $lic2 = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $id));
            if ($lic2 && $lic2->installation_id && $lic2->domain && $lic2->fingerprint) {
                $tok = self::issue_certificate($lic2->installation_id, $lic2->domain, $lic2->fingerprint, (int) $lic2->plan_months, $lic2->valid_until, $lic2->note, false, self::decode_addons($lic2->addons ?? null));
                $wpdb->update($t, ['license_token' => $tok], ['id' => $id]);
            }
        }
        wp_safe_redirect(admin_url('admin.php?page=akpp-licenses&unblocked=1')); exit;
    }

    /** Тумблер автопродления */
    public function handle_autorenew() {
        if (!current_user_can('manage_options')) wp_die('Нет прав');
        $id = intval($_POST['id']);
        check_admin_referer('akpp_license_autorenew_' . $id);
        global $wpdb; $t = $wpdb->prefix . 'akpp_licenses';
        $lic = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $id));
        if ($lic) $wpdb->update($t, ['auto_renew' => (int) ! (int) $lic->auto_renew], ['id' => $id]);
        wp_safe_redirect(admin_url('admin.php?page=akpp-licenses&autorenew=1')); exit;
    }

    /** Автопродление: активные с auto_renew=1 и сроком < 30 дней продлеваются на plan_months + перевыпуск сертификата */
    public function cron_autorenew() {
        global $wpdb; $t = $wpdb->prefix . 'akpp_licenses';
        $rows = $wpdb->get_results("SELECT * FROM $t WHERE status = 'active' AND auto_renew = 1");
        foreach ((array) $rows as $lic) {
            if (strtotime($lic->valid_until) > current_time('timestamp') + 30 * 86400) continue;
            $base = (strtotime($lic->valid_until) > current_time('timestamp')) ? $lic->valid_until : current_time('mysql');
            $new = date('Y-m-d H:i:s', strtotime($base . ' +' . (int) $lic->plan_months . ' months'));
            $wpdb->update($t, ['valid_until' => $new], ['id' => $lic->id]);
            if ($lic->installation_id && $lic->domain && $lic->fingerprint) {
                $tok = self::issue_certificate($lic->installation_id, $lic->domain, $lic->fingerprint, (int) $lic->plan_months, $new, $lic->note, false, self::decode_addons($lic->addons ?? null));
                $wpdb->update($t, ['license_token' => $tok], ['id' => $lic->id]);
            }
            error_log('[AKPP License] autorenew #' . $lic->id . ' ' . $lic->domain . ' -> ' . $new);
        }
    }

    /** Клиент: раз в сутки стучит на мастер и забирает свежий сертификат (автопродление на клиенте) */
    public function online_ping() {
        $url = self::master_url();
        $token = get_option('akpp_license_token');
        if (!$url || !$token) return;
        $res = wp_remote_post(trailingslashit($url) . 'wp-json/akpp-license/v1/check', [
            'body' => ['domain' => wp_parse_url(home_url(), PHP_URL_HOST), 'fingerprint' => self::code_fingerprint()],
            'timeout' => 15,
        ]);
        if (is_wp_error($res)) return;
        $data = json_decode(wp_remote_retrieve_body($res), true);
        if (!is_array($data) || empty($data['ok']) || empty($data['license_token'])) return;
        $new = (string) $data['license_token'];
        if ($new === $token) return;
        $v = self::verify_certificate($new, wp_parse_url(home_url(), PHP_URL_HOST), self::code_fingerprint());
        if (!empty($v['ok'])) {
            update_option('akpp_license_token', $new);
            if (array_key_exists('addons', $data)) update_option('akpp_license_features', (array) $data['addons']);
        }
    }
    public function handle_reset() {
        if (!current_user_can('manage_options')) wp_die('Нет прав');
        check_admin_referer('akpp_license_reset_' . intval($_POST['id']));
        global $wpdb; // сброс привязки домена/кода (для переноса на другой домен)
        $wpdb->update($wpdb->prefix . 'akpp_licenses', ['domain' => null, 'fingerprint' => null, 'installation_id' => null, 'status' => 'pending'], ['id' => intval($_POST['id'])]);
        wp_safe_redirect(admin_url('admin.php?page=akpp-licenses')); exit;
    }

    // ===================== ДОП-МОДУЛИ ПО ЛИЦЕНЗИИ =====================
    const ADDON_WHITELIST = ["shop", "1c", "integrations", "accounting", "autoservice", "parser"];

    public static function decode_addons($raw) {
        $a = json_decode((string) $raw, true);
        return is_array($a) ? array_values(array_intersect($a, self::ADDON_WHITELIST)) : [];
    }

    /**
     * Разрешён ли доп-модуль по лицензии.
     * Мастер и установки без лицензии — всё разрешено (обратная совместимость).
     */
    public static function feature_allowed($feat) {
        if (get_option("akpp_master_mode")) return true;
        if (!get_option("akpp_license_token")) return true;
        $f = get_option("akpp_license_features", null);
        if ($f === null || $f === false || $f === "") return true;
        return in_array($feat, (array) $f, true);
    }
}
AKPP_License::get_instance();

// akpp-license-addons-menu: tenant не видит меню допов, которых нет в лицензии
function akpp_feature($feat) { return class_exists('AKPP_License') ? AKPP_License::feature_allowed($feat) : true; }
add_action('admin_menu', function () {
    global $menu, $submenu;
    $map = [
        'akpp-crm-shop' => 'shop',
        'akpp-crm-integrations' => '1c',
        'akpp-crm-avito-dialogs' => 'integrations',
        'akpp-crm-employees' => 'accounting',
        'akpp-crm-vehicles' => 'autoservice'
    ];
    foreach ($map as $slug => $feat) {
        if (akpp_feature($feat)) continue;
        foreach ($menu as $i => $m) { if (isset($m[2]) && $m[2] === $slug) unset($menu[$i]); }
        unset($submenu[$slug]);
    }
}, 9999);
