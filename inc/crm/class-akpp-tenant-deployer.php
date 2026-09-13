<?php
/**
 * AKPP CRM Tenant Deployer — автоинсталл CRM на новый домен из страницы «🔑 Лицензии».
 * Master-only. Пишет job-<id>.json и запускает /home/akpp/bin/deploy-tenant.sh через sudo.
 * Пароли (БД, WP-админ) хранятся в job chmod 600 и НЕ отдаются в poll.
 */
if (!defined('ABSPATH')) exit;

class AKPP_Tenant_Deployer {
    const NONCE  = 'akpp_deploy_nonce';
    const SCRIPT = '/home/akpp/bin/deploy-tenant.sh';
    const LOGDIR = '/home/akpp/backups/logs';

    private static $inst = null;
    public static function get_instance() { if (self::$inst === null) self::$inst = new self(); return self::$inst; }
    private function __construct() {
        if (!self::is_master()) return;
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_ajax_akpp_deploy_start', [$this, 'ajax_start']);
        add_action('wp_ajax_akpp_deploy_poll',  [$this, 'ajax_poll']);
    }
    public static function is_master() { return (bool) get_option('akpp_master_mode'); }

    public function enqueue($hook) {
        if (!isset($_GET['page']) || $_GET['page'] !== 'akpp-licenses') return;
        $ver = defined('AKPP_THEME_VERSION') ? AKPP_THEME_VERSION : '1.0';
        wp_enqueue_script('akpp-deployer', get_template_directory_uri() . '/assets/js/deployer.js', ['jquery'], $ver, true);
        wp_localize_script('akpp-deployer', 'AKPP_DEP', ['ajaxurl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce(self::NONCE)]);
    }

    private function job_path($id) { return self::LOGDIR . '/job-' . $id . '.json'; }

    public function ajax_start() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options') || !self::is_master()) wp_send_json_error('forbidden');

        $domain    = sanitize_text_field($_POST['domain'] ?? '');
        $docroot   = sanitize_text_field($_POST['docroot'] ?? '');
        $siteuser  = sanitize_text_field($_POST['siteuser'] ?? '');
        $db_host   = sanitize_text_field($_POST['db_host'] ?? '127.0.0.1');
        $db_name   = sanitize_text_field($_POST['db_name'] ?? '');
        $db_user   = sanitize_text_field($_POST['db_user'] ?? '');
        $db_pass   = (string) ($_POST['db_pass'] ?? '');
        $db_prefix = sanitize_text_field($_POST['db_prefix'] ?? 'wp_');
        $wp_user   = sanitize_text_field($_POST['wp_admin_user'] ?? 'admin');
        $wp_pass   = (string) ($_POST['wp_admin_pass'] ?? '');
        $wp_email  = sanitize_email($_POST['wp_admin_email'] ?? '');
        $title     = sanitize_text_field($_POST['site_title'] ?? '');
        $plan      = intval($_POST['plan_months'] ?? 12);
        $unlimited = !empty($_POST['unlimited']);
        $note      = sanitize_text_field($_POST['note'] ?? '');
        $c_name    = sanitize_text_field($_POST['company_name'] ?? '');
        $c_site    = sanitize_text_field($_POST['company_site'] ?? '');
        $c_city    = sanitize_text_field($_POST['company_city'] ?? '');
        $avito     = sanitize_text_field($_POST['avito_ad_id'] ?? '');

        if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) wp_send_json_error('bad_domain');
        if (empty($docroot))  wp_send_json_error('bad_docroot');
        if (empty($siteuser)) wp_send_json_error('bad_siteuser');
        if (empty($db_name) || empty($db_user) || $db_pass === '') wp_send_json_error('bad_db');
        if ($wp_pass === '') wp_send_json_error('bad_admin_pass');

        global $wpdb;
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_licenses WHERE domain=%s", $domain));
        if ($exists > 0) wp_send_json_error('domain_exists');

        $id = 'deploy-' . bin2hex(random_bytes(4));
        $job = [
            'id' => $id, 'domain' => $domain, 'docroot' => $docroot, 'siteuser' => $siteuser,
            'db_host' => $db_host, 'db_name' => $db_name, 'db_user' => $db_user, 'db_pass' => $db_pass, 'db_prefix' => $db_prefix,
            'wp_admin_user' => $wp_user, 'wp_admin_pass' => $wp_pass, 'wp_admin_email' => $wp_email,
            'site_title' => $title !== '' ? $title : $domain, 'plan_months' => $plan, 'unlimited' => $unlimited,
            'note' => $note, 'company_name' => $c_name, 'company_site' => $c_site !== '' ? $c_site : 'https://' . $domain,
            'company_city' => $c_city, 'avito_ad_id' => $avito,
            'status' => 'running', 'started_at' => date('Y-m-d H:i:s'), 'log' => [],
        ];
        $path = $this->job_path($id);
        if (file_put_contents($path, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) wp_send_json_error('cannot_write_job');
        @chmod($path, 0600);

        $cmd = 'nohup /usr/bin/sudo -n ' . escapeshellarg(self::SCRIPT) . ' ' . escapeshellarg($id) . ' >/dev/null 2>&1 & echo $!';
        $pid = trim((string) shell_exec($cmd));
        wp_send_json_success(['id' => $id, 'pid' => $pid, 'domain' => $domain]);
    }

    public function ajax_poll() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options') || !self::is_master()) wp_send_json_error('forbidden');
        $id = sanitize_key($_POST['job_id'] ?? '');
        if (!preg_match('/^[a-z0-9-]{4,40}$/', $id)) wp_send_json_error('bad_job_id');
        $path = $this->job_path($id);
        if (!is_readable($path)) wp_send_json_error('no_job');
        $j = json_decode(file_get_contents($path), true);
        if (is_array($j)) { unset($j['db_pass'], $j['wp_admin_pass']); }
        wp_send_json_success(is_array($j) ? $j : []);
    }
}
AKPP_Tenant_Deployer::get_instance();
