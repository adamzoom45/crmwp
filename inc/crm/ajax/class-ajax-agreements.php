<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX модуль: Договор-оферта
 * 1. Согласие клиента с офертой (при создании сделки)
 * 2. Редактирование текста оферты (админка)
 * 3. Просмотр списка согласий
 */
class AKPP_AJAX_Agreements extends AKPP_AJAX_Base {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->register_hooks();
    }
    
    /**
     * Регистрация AJAX хуков
     */
    private function register_hooks() {
        // 1. СОГЛАСИЕ КЛИЕНТА (из new-deal.php)
        add_action('wp_ajax_akpp_save_agreement', [$this, 'ajax_save_agreement']);
        add_action('wp_ajax_nopriv_akpp_save_agreement', [$this, 'ajax_save_agreement']);
        
        // 2. РЕДАКТИРОВАНИЕ ТЕКСТА ОФЕРТЫ (админка)
        add_action('wp_ajax_akpp_save_agreement_text', [$this, 'ajax_save_agreement_text']);
        add_action('wp_ajax_akpp_get_agreement_text', [$this, 'ajax_get_agreement_text']);
        
        // 3. ПРОСМОТР СОГЛАСИЙ (админка)
        add_action('wp_ajax_akpp_get_agreements', [$this, 'ajax_get_agreements']);
        add_action('wp_ajax_akpp_export_agreements', [$this, 'ajax_export_agreements']);
        add_action('wp_ajax_akpp_delete_agreement', [$this, 'ajax_delete_agreement']);
        add_action('wp_ajax_akpp_bulk_delete_agreements', [$this, 'ajax_bulk_delete_agreements']);
    }
    
    // ========================================================================
    // 1. СОГЛАСИЕ КЛИЕНТА С ОФЕРТОЙ (из new-deal.php)
    // ========================================================================
    
    public function ajax_save_agreement() {
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_agreements';
        
        // Проверяем существование таблицы
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
        if (!$table_exists) {
            wp_send_json_error(['message' => 'Таблица согласий не создана']);
            return;
        }
        
        $client_name = sanitize_text_field($_POST['client_name'] ?? '');
        $client_phone = sanitize_text_field($_POST['client_phone'] ?? '');
        $client_email = sanitize_email($_POST['client_email'] ?? '');
        $source = sanitize_text_field($_POST['source'] ?? 'crm_deal');
        $deal_id = intval($_POST['deal_id'] ?? 0);
        
        if (empty($client_name) || empty($client_phone)) {
            wp_send_json_error(['message' => 'Укажите имя и телефон клиента']);
            return;
        }
        
        // Получаем текущую версию оферты
        $version = get_option('akpp_agreement_version', '1.0');
        
        // Сохраняем согласие
        $wpdb->insert($table, [
            'deal_id' => $deal_id ?: null,
            'client_name' => $client_name,
            'client_phone' => $client_phone,
            'client_email' => !empty($client_email) ? $client_email : null,
            'agreement_version' => $version,
            'source' => $source,
            'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'accepted_at' => current_time('mysql'),
            'created_at' => current_time('mysql')
        ]);
        
        $agreement_id = $wpdb->insert_id;
        
        // Обновляем сделку если есть
        if ($deal_id > 0) {
            $wpdb->update($wpdb->prefix . 'akpp_deals', [
                'agreement_accepted' => 1,
                'agreement_id' => $agreement_id
            ], ['id' => $deal_id]);
        }
        
        wp_send_json_success([
            'message' => '✅ Согласие с офертой сохранено',
            'agreement_id' => $agreement_id
        ]);
    }
    
    // ========================================================================
    // 2. РЕДАКТИРОВАНИЕ ТЕКСТА ОФЕРТЫ (админка)
    // ========================================================================
    
    public function ajax_save_agreement_text() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        $title = sanitize_text_field($_POST['title'] ?? 'Договор-оферта');
        $content = wp_kses_post($_POST['content'] ?? '');
        $version = sanitize_text_field($_POST['version'] ?? '1.0');
        
        if (empty($content)) {
            wp_send_json_error(['message' => 'Текст оферты не может быть пустым']);
            return;
        }
        
        update_option('akpp_agreement_title', $title);
        update_option('akpp_agreement_content', $content);
        update_option('akpp_agreement_version', $version);
        update_option('akpp_agreement_updated_at', current_time('mysql'));
        
        wp_send_json_success([
            'message' => 'Текст оферты сохранён',
            'version' => $version
        ]);
    }
    
    public function ajax_get_agreement_text() {
        if (!$this->check_permissions()) return;
        
        if (!function_exists('akpp_get_agreement_text')) {
            $file = dirname(dirname(__FILE__)) . '/templates/agreement-text.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        if (function_exists('akpp_get_agreement_text')) {
            $html = akpp_get_agreement_text('1.0');
            wp_send_json_success(['html' => $html]);
        } else {
            wp_send_json_error(['message' => 'Файл agreement-text.php не найден']);
        }
    }
    
    // ========================================================================
    // 3. ПРОСМОТР СОГЛАСИЙ (админка)
    // ========================================================================
    
    public function ajax_get_agreements() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_agreements';
        
        $page = intval($_POST['page'] ?? 1);
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $agreements = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY accepted_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ), ARRAY_A);
        
        wp_send_json_success([
            'agreements' => $agreements ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ]);
    }
    
    public function ajax_export_agreements() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_agreements';
        
        $agreements = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY accepted_at DESC",
            ARRAY_A
        );
        
        $csv = "ID;Клиент;Телефон;Email;Сделка;Источник;IP;Дата согласия;Версия\n";
        
        foreach ($agreements as $agr) {
            $csv .= sprintf(
                "%d;%s;%s;%s;%s;%s;%s;%s;%s\n",
                $agr['id'],
                $agr['client_name'],
                $agr['client_phone'],
                $agr['client_email'] ?? '',
                $agr['deal_id'] ?? '',
                $agr['source'] ?? '',
                $agr['ip_address'] ?? '',
                $agr['accepted_at'] ?? '',
                $agr['agreement_version'] ?? ''
            );
        }
        
        // Конвертируем в UTF-8 с BOM для Excel
        $csv = "\xEF\xBB\xBF" . $csv;
        
        wp_send_json_success([
            'csv' => $csv,
            'filename' => 'agreements_' . date('Y-m-d') . '.csv'
        ]);
    }

    // ========================================================================
    // 4. УДАЛЕНИЕ СОГЛАСИЯ (админка, с защитой привязанных к сделке)
    // ========================================================================


    // ========================================================================
    // 5. МАССОВОЕ УДАЛЕНИЕ СОГЛАСИЙ (админка, с защитой привязанных к сделке)
    // ========================================================================

    public function ajax_bulk_delete_agreements() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'akpp_agreements';

        $ids = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : [];
        $ids = array_filter($ids, function ($v) { return $v > 0; });
        if (empty($ids)) {
            wp_send_json_error(['message' => 'Не выбрано ни одного согласия']);
            return;
        }

        $deleted = 0;
        $skipped = [];

        foreach ($ids as $id) {
            $agreement = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
            if (!$agreement) continue;

            // Защита 1: согласие привязано к сделке через deal_id
            $linked_deal_id = intval($agreement['deal_id'] ?? 0);
            if ($linked_deal_id > 0) {
                $deal_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}akpp_deals WHERE id = %d", $linked_deal_id
                ));
                if ($deal_exists) { $skipped[] = $id; continue; }
            }

            // Защита 2: сделка ссылается на согласие через agreement_id
            $deal_by_agreement = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}akpp_deals WHERE agreement_id = %d LIMIT 1", $id
            ));
            if ($deal_by_agreement) { $skipped[] = $id; continue; }

            if ($wpdb->delete($table, ['id' => $id], ['%d']) !== false) {
                $deleted++;
            }
        }

        $msg = '✅ Удалено согласий: ' . $deleted;
        if (!empty($skipped)) {
            $msg .= '. Пропущено (привязаны к сделкам): ' . count($skipped) . ' (ID: ' . implode(', ', $skipped) . ')';
        }

        wp_send_json_success(['message' => $msg, 'deleted' => $deleted, 'skipped' => $skipped]);
    }
    public function ajax_delete_agreement() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'akpp_agreements';

        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error(['message' => 'Неверный ID согласия']);
            return;
        }

        // Проверка существования записи
        $agreement = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        if (!$agreement) {
            wp_send_json_error(['message' => 'Согласие не найдено']);
            return;
        }

        // ЗАЩИТА 1: согласие привязано к сделке через deal_id
        $linked_deal_id = intval($agreement['deal_id'] ?? 0);
        if ($linked_deal_id > 0) {
            $deal_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}akpp_deals WHERE id = %d", $linked_deal_id
            ));
            if ($deal_exists) {
                wp_send_json_error(['message' => 'Нельзя удалить: согласие привязано к сделке #' . $linked_deal_id . '. Сначала удалите сделку.']);
                return;
            }
        }

        // ЗАЩИТА 2: сделка ссылается на это согласие через agreement_id
        $deal_by_agreement = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_deals WHERE agreement_id = %d LIMIT 1", $id
        ));
        if ($deal_by_agreement) {
            wp_send_json_error(['message' => 'Нельзя удалить: на это согласие ссылается сделка #' . intval($deal_by_agreement) . '. Сначала удалите сделку.']);
            return;
        }

        // Удаляем
        $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);
        if ($deleted === false) {
            wp_send_json_error(['message' => 'Ошибка удаления: ' . $wpdb->last_error]);
            return;
        }

        wp_send_json_success(['message' => '✅ Согласие #' . $id . ' удалено']);
    }
}
