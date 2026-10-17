<?php
/**
 * АКПП45 CRM - Фоновые задачи (без Avito): очистка, курсы валют, AI-анализ.
 * Выделено из старого class-akpp-cron.php после перевода Avito на API v2.
 *
 * @package AKPP_CRM
 * @version 5.1.1
 */

if (!defined('ABSPATH')) exit;

class AKPP_Cron_Tasks {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_filter('cron_schedules', [$this, 'add_intervals']);
        add_action('wp', [$this, 'schedule']);
        add_action('akpp_cleanup_old_data', [$this, 'cleanup_old_data']);
        add_action('akpp_update_exchange_rates', [$this, 'update_exchange_rates']);
        add_action('akpp_ai_bulk_analysis', [$this, 'ai_bulk_analysis']);
    }

    public function add_intervals($s) {
        if (!isset($s['every_hour'])) $s['every_hour'] = ['interval' => 3600, 'display' => 'Каждый час'];
        if (!isset($s['every_day'])) $s['every_day'] = ['interval' => 86400, 'display' => 'Каждый день'];
        return $s;
    }

    public function schedule() {
        if (!wp_next_scheduled('akpp_cleanup_old_data')) wp_schedule_event(time() + 60, 'every_day', 'akpp_cleanup_old_data');
        if (!wp_next_scheduled('akpp_update_exchange_rates')) wp_schedule_event(time() + 120, 'every_day', 'akpp_update_exchange_rates');
        if (!wp_next_scheduled('akpp_ai_bulk_analysis')) wp_schedule_event(time() + 180, 'every_hour', 'akpp_ai_bulk_analysis');
    }

    public function cleanup_old_data() {
        global $wpdb;
        $p = $wpdb->prefix;
        $wpdb->query("DELETE FROM {$p}akpp_vin_cache WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $wpdb->query("DELETE FROM {$p}akpp_chat_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)");
        $wpdb->query("DELETE FROM {$p}akpp_parser_items WHERE status = 'rejected' AND created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
    }

    public function update_exchange_rates() {
        $res = wp_remote_get('https://www.cbr-xml-daily.ru/daily_json.js', ['timeout' => 30]);
        if (is_wp_error($res)) return;
        $data = json_decode(wp_remote_retrieve_body($res), true);
        if (!empty($data['Valute'])) {
            update_option('akpp_exchange_rates', [
                'USD' => $data['Valute']['USD']['Value'] ?? 0,
                'EUR' => $data['Valute']['EUR']['Value'] ?? 0,
                'CNY' => $data['Valute']['CNY']['Value'] ?? 0,
                'updated_at' => current_time('mysql'),
            ]);
        }
    }

    public function ai_bulk_analysis() {
        global $wpdb;
        $t = $wpdb->prefix . 'akpp_parser_items';
        if ($wpdb->get_var("SHOW TABLES LIKE '$t'") !== $t) return;
        $items = $wpdb->get_results("SELECT * FROM $t WHERE status = 'pending' ORDER BY created_at ASC LIMIT 5");
        if (empty($items) || !class_exists('AKPP_AI_Analyzer')) return;
        $an = AKPP_AI_Analyzer::get_instance();
        foreach ($items as $it) {
            $r = $an->analyze($it->content, $it->content_type);
            $wpdb->update($t, ['ai_analysis' => wp_json_encode($r), 'status' => 'ai_processed', 'updated_at' => current_time('mysql')], ['id' => $it->id]);
            usleep(800000);
        }
    }
}
AKPP_Cron_Tasks::get_instance();
