<?php
/**
 * AKPP CRM Updater — рассылка обновлений на tenant'ы.
 * Встраивается в страницу «🔑 Лицензии» (master-only). Отдельного меню НЕ имеет.
 * На tenant'е опции akpp_master_mode нет -> хуки не вешаются, блок не рендерится.
 */
if (!defined('ABSPATH')) exit;

class AKPP_Updater {
    const NONCE  = 'akpp_updater_nonce';
    const SCRIPT = '/home/akpp/bin/crm-update.sh';
    const LOGDIR = '/home/akpp/backups/logs';

    private static $inst = null;
    public static function get_instance() {
        if (self::$inst === null) self::$inst = new self();
        return self::$inst;
    }
    private function __construct() {
        if (!self::is_master()) return; // master-only
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_ajax_akpp_updater_start', [$this, 'ajax_start']);
        add_action('wp_ajax_akpp_updater_poll',  [$this, 'ajax_poll']);
    }

    public static function is_master() { return (bool) get_option('akpp_master_mode'); }

    /** Whitelist активных доменов из реестра лицензий мастера. */
    public static function whitelist() {
        global $wpdb;
        $rows = $wpdb->get_col("SELECT domain FROM {$wpdb->prefix}akpp_licenses WHERE status='active' AND domain IS NOT NULL AND domain<>''");
        return is_array($rows) ? array_values(array_filter(array_map('trim', $rows))) : [];
    }

    public static function current_version() {
        $f = get_template_directory() . '/functions.php';
        if (is_readable($f) && preg_match("/define\(\s*'AKPP_THEME_VERSION'\s*,\s*'([^']+)'/", file_get_contents($f), $m)) return $m[1];
        return '0.0.0';
    }

    /** Кнопка «обновить один» для строки реестра лицензий. */
    public static function render_row_button($domain) {
        if (!self::is_master() || empty($domain)) return '';
        $d = esc_attr($domain);
        return '<button type="button" class="btn-gray akpp-upd-one" data-domain="' . $d . '" title="Обновить CRM на ' . $d . '">🔄</button>';
    }

    public function enqueue($hook) {
        if (!isset($_GET['page']) || $_GET['page'] !== 'akpp-licenses') return;
        $ver = defined('AKPP_THEME_VERSION') ? AKPP_THEME_VERSION : '1.0';
        wp_enqueue_script('akpp-updater', get_template_directory_uri() . '/assets/js/updater.js', ['jquery'], $ver, true);
        wp_localize_script('akpp-updater', 'AKPP_UPD', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE),
            'ver'     => self::current_version(),
        ]);
    }

    private function job_path($id) { return self::LOGDIR . '/job-' . $id . '.json'; }

    public function ajax_start() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options') || !self::is_master()) wp_send_json_error('forbidden');

        $wl     = self::whitelist();
        $posted = isset($_POST['tenants']) && is_array($_POST['tenants']) ? array_map('sanitize_text_field', $_POST['tenants']) : [];
        $chosen = array_values(array_intersect($posted, $wl)); // серверная валидация: только реестр
        if (!$chosen) wp_send_json_error('no_valid_tenants');

        $version = sanitize_text_field($_POST['version'] ?? '');
        $note    = sanitize_textarea_field($_POST['note'] ?? '');
        $migrate = !empty($_POST['migrate']);
        $canary  = !empty($_POST['canary']);
        $append  = !empty($_POST['append']);

        $rest = [];
        if ($append) {
            $this_tenants = $chosen;
        } elseif ($canary && count($chosen) > 1) {
            $this_tenants = [$chosen[0]];
            $rest = array_slice($chosen, 1);
        } else {
            $this_tenants = $chosen;
        }

        if ($append && !empty($_POST['job_id'])) {
            $id = sanitize_key($_POST['job_id']);
            if (!preg_match('/^[a-z0-9-]{4,40}$/', $id)) wp_send_json_error('bad_job_id');
            $path = $this->job_path($id);
            $j = is_readable($path) ? json_decode(file_get_contents($path), true) : null;
            if (!is_array($j)) $j = [];
        } else {
            $id = 'upd-' . bin2hex(random_bytes(4));
            $path = $this->job_path($id);
            $j = ['results' => []];
        }

        $j['id']      = $id;
        $j['version'] = $version;
        $j['note']    = $note;
        $j['migrate'] = $migrate;
        $j['canary']  = $canary;
        $j['tenants'] = $this_tenants;
        $j['status']  = 'running';
        $j['started_at'] = date('Y-m-d H:i:s');
        if (empty($j['results']) || !is_array($j['results'])) $j['results'] = [];

        if (file_put_contents($path, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) wp_send_json_error('cannot_write_job');
        @chmod($path, 0664);

        $cmd = 'nohup /usr/bin/sudo -n ' . escapeshellarg(self::SCRIPT) . ' ' . escapeshellarg($id) . ' >/dev/null 2>&1 & echo $!';
        $pid = trim((string) shell_exec($cmd));

        wp_send_json_success(['id' => $id, 'pid' => $pid, 'tenants' => $this_tenants, 'pending_rest' => $rest]);
    }

    public function ajax_poll() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options') || !self::is_master()) wp_send_json_error('forbidden');
        $id = sanitize_key($_POST['job_id'] ?? '');
        if (!preg_match('/^[a-z0-9-]{4,40}$/', $id)) wp_send_json_error('bad_job_id');
        $path = $this->job_path($id);
        if (!is_readable($path)) wp_send_json_error('no_job');
        $j = json_decode(file_get_contents($path), true);
        wp_send_json_success(is_array($j) ? $j : []);
    }
}
AKPP_Updater::get_instance();
