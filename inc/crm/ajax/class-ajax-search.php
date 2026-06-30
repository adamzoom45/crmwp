<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX модуль: Поиск
 * Поиск по запчастям, автомобилям, сотрудникам, сделкам
 */
class AKPP_AJAX_Search extends AKPP_AJAX_Base {
    
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
        // Поиск по запчастям
        add_action('wp_ajax_akpp_search_parts', [$this, 'ajax_search_parts']);
        add_action('wp_ajax_nopriv_akpp_search_parts', [$this, 'ajax_search_parts']);
        
        // Поиск по автомобилям
        add_action('wp_ajax_akpp_search_vehicles', [$this, 'ajax_search_vehicles']);
        
        // Поиск по сотрудникам
        add_action('wp_ajax_akpp_search_employees', [$this, 'ajax_search_employees']);
        
        // Поиск по сделкам
        add_action('wp_ajax_akpp_search_deals', [$this, 'ajax_search_deals']);
        
        // Универсальный поиск
        add_action('wp_ajax_akpp_global_search', [$this, 'ajax_global_search']);
    }
    
    // ========================================================================
    // ПОИСК ПО ЗАПЧАСТЯМ
    // ========================================================================
    
    public function ajax_search_parts() {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_parts';
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        $limit = min(50, intval($_POST['limit'] ?? 20));
        
        if (strlen($query) < 2) {
            wp_send_json_success(['results' => [], 'total' => 0]);
            return;
        }
        
        $like = '%' . $wpdb->esc_like($query) . '%';
        
        $where = "(p.name LIKE %s OR p.sku LIKE %s)";
        $params = [$like, $like];
        
        if ($category_id > 0) {
            $where .= " AND p.category_id = %d";
            $params[] = $category_id;
        }
        
        // Только активные товары
        $where .= " AND p.is_active = 1";
        
        // Общее количество
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} p WHERE {$where}",
            $params
        ));
        
        // Результаты
        $params[] = $limit;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, c.name as category_name
             FROM {$table} p
             LEFT JOIN {$wpdb->prefix}akpp_shop_categories c ON p.category_id = c.id
             WHERE {$where}
             ORDER BY p.name ASC
             LIMIT %d",
            $params
        ), ARRAY_A);
        
        wp_send_json_success([
            'results' => $results,
            'total' => (int) $total,
            'query' => $query
        ]);
    }
    
    // ========================================================================
    // ПОИСК ПО АВТОМОБИЛЯМ
    // ========================================================================
    
    public function ajax_search_vehicles() {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_vehicles';
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        $limit = min(50, intval($_POST['limit'] ?? 20));
        
        if (strlen($query) < 2) {
            wp_send_json_success(['results' => [], 'total' => 0]);
            return;
        }
        
        $like = '%' . $wpdb->esc_like($query) . '%';
        
        // Поиск по марке, модели, VIN, двигателю
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, make, model, year, vin, engine, market
             FROM {$table}
             WHERE make LIKE %s 
                OR model LIKE %s 
                OR vin LIKE %s 
                OR engine LIKE %s
             ORDER BY make, model, year DESC
             LIMIT %d",
            $like, $like, $like, $like, $limit
        ), ARRAY_A);
        
        wp_send_json_success([
            'results' => $results,
            'total' => count($results),
            'query' => $query
        ]);
    }
    
    // ========================================================================
    // ПОИСК ПО СОТРУДНИКАМ
    // ========================================================================
    
    public function ajax_search_employees() {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_employees';
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        $role = sanitize_text_field($_POST['role'] ?? '');
        $limit = min(50, intval($_POST['limit'] ?? 20));
        
        if (strlen($query) < 2) {
            wp_send_json_success(['results' => [], 'total' => 0]);
            return;
        }
        
        $like = '%' . $wpdb->esc_like($query) . '%';
        
        $where = "(e.name LIKE %s OR e.phone LIKE %s)";
        $params = [$like, $like];
        
        if (!empty($role)) {
            $where .= " AND e.role = %s";
            $params[] = $role;
        }
        
        // Только активные
        $where .= " AND e.is_active = 1";
        
        $params[] = $limit;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT e.id, e.name, e.role, e.phone, e.telegram_id
             FROM {$table} e
             WHERE {$where}
             ORDER BY e.name ASC
             LIMIT %d",
            $params
        ), ARRAY_A);
        
        wp_send_json_success([
            'results' => $results,
            'total' => count($results),
            'query' => $query
        ]);
    }
    
    // ========================================================================
    // ПОИСК ПО СДЕЛКАМ
    // ========================================================================
    
    public function ajax_search_deals() {
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_deals';
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? '');
        $limit = min(50, intval($_POST['limit'] ?? 20));
        
        if (strlen($query) < 2) {
            wp_send_json_success(['results' => [], 'total' => 0]);
            return;
        }
        
        $like = '%' . $wpdb->esc_like($query) . '%';
        
        $where = "(d.client_name LIKE %s OR d.client_phone LIKE %s OR d.description LIKE %s)";
        $params = [$like, $like, $like];
        
        if (!empty($status)) {
            $where .= " AND d.status = %s";
            $params[] = $status;
        }
        
        $params[] = $limit;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT d.id, d.client_name, d.client_phone, d.status, d.created_at,
                    v.make, v.model, v.year,
                    e.name as employee_name
             FROM {$table} d
             LEFT JOIN {$wpdb->prefix}akpp_vehicles v ON d.vehicle_id = v.id
             LEFT JOIN {$wpdb->prefix}akpp_employees e ON d.employee_id = e.id
             WHERE {$where}
             ORDER BY d.created_at DESC
             LIMIT %d",
            $params
        ), ARRAY_A);
        
        wp_send_json_success([
            'results' => $results,
            'total' => count($results),
            'query' => $query
        ]);
    }
    
    // ========================================================================
    // УНИВЕРСАЛЬНЫЙ ПОИСК (глобальный)
    // ========================================================================
    
    public function ajax_global_search() {
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $query = sanitize_text_field($_POST['query'] ?? '');
        
        if (strlen($query) < 2) {
            wp_send_json_success(['results' => []]);
            return;
        }
        
        $like = '%' . $wpdb->esc_like($query) . '%';
        $results = [];
        
        // 1. Сделки (по имени клиента или телефону)
        $deals = $wpdb->get_results($wpdb->prepare(
            "SELECT 'deal' as type, d.id, d.client_name as title, d.client_phone as subtitle, d.status, d.created_at
             FROM {$wpdb->prefix}akpp_deals d
             WHERE d.client_name LIKE %s OR d.client_phone LIKE %s
             ORDER BY d.created_at DESC LIMIT 5",
            $like, $like
        ), ARRAY_A);
        
        if (!empty($deals)) {
            foreach ($deals as $deal) {
                $deal['url'] = admin_url('admin.php?page=akpp-crm-deals&action=edit&id=' . $deal['id']);
                $deal['icon'] = '💼';
            }
            $results = array_merge($results, $deals);
        }
        
        // 2. Сотрудники
        $employees = $wpdb->get_results($wpdb->prepare(
            "SELECT 'employee' as type, e.id, e.name as title, e.role as subtitle, e.phone, e.is_active
             FROM {$wpdb->prefix}akpp_employees e
             WHERE e.name LIKE %s OR e.phone LIKE %s
             ORDER BY e.name ASC LIMIT 5",
            $like, $like
        ), ARRAY_A);
        
        if (!empty($employees)) {
            foreach ($employees as $emp) {
                $emp['url'] = admin_url('admin.php?page=akpp-crm-employees');
                $emp['icon'] = '👷';
            }
            $results = array_merge($results, $employees);
        }
        
        // 3. Автомобили
        $vehicles = $wpdb->get_results($wpdb->prepare(
            "SELECT 'vehicle' as type, v.id, CONCAT(v.make, ' ', v.model) as title, v.year as subtitle, v.vin
             FROM {$wpdb->prefix}akpp_vehicles v
             WHERE v.make LIKE %s OR v.model LIKE %s OR v.vin LIKE %s
             ORDER BY v.make, v.model LIMIT 5",
            $like, $like, $like
        ), ARRAY_A);
        
        if (!empty($vehicles)) {
            foreach ($vehicles as $veh) {
                $veh['url'] = admin_url('admin.php?page=akpp-crm-vehicles');
                $veh['icon'] = '🚗';
            }
            $results = array_merge($results, $vehicles);
        }
        
        // 4. Запчасти
        $parts = $wpdb->get_results($wpdb->prepare(
            "SELECT 'part' as type, p.id, p.name as title, p.sku as subtitle, p.price
             FROM {$wpdb->prefix}akpp_parts p
             WHERE p.name LIKE %s OR p.sku LIKE %s
             ORDER BY p.name ASC LIMIT 5",
            $like, $like
        ), ARRAY_A);
        
        if (!empty($parts)) {
            foreach ($parts as $part) {
                $part['url'] = admin_url('admin.php?page=akpp-crm-parts');
                $part['icon'] = '🔧';
            }
            $results = array_merge($results, $parts);
        }
        
        // 5. Лиды
        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT 'lead' as type, l.id, l.full_name as title, l.phone as subtitle, l.status, l.created_at
             FROM {$wpdb->prefix}akpp_leads l
             WHERE l.full_name LIKE %s OR l.phone LIKE %s
             ORDER BY l.created_at DESC LIMIT 5",
            $like, $like
        ), ARRAY_A);
        
        if (!empty($leads)) {
            foreach ($leads as $lead) {
                $lead['url'] = admin_url('admin.php?page=akpp-crm-leads');
                $lead['icon'] = '📨';
            }
            $results = array_merge($results, $leads);
        }
        
        // Сортировка по дате (если есть created_at)
        usort($results, function($a, $b) {
            $date_a = strtotime($a['created_at'] ?? '2000-01-01');
            $date_b = strtotime($b['created_at'] ?? '2000-01-01');
            return $date_b - $date_a;
        });
        
        // Ограничиваем общий результат
        $results = array_slice($results, 0, 20);
        
        wp_send_json_success([
            'results' => $results,
            'total' => count($results),
            'query' => $query
        ]);
    }
}