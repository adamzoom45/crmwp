<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX модуль: Сделки
 * Создание, редактирование, удаление, смена статуса
 * Автоматическое создание клиента и автомобиля
 */
class AKPP_AJAX_Deals extends AKPP_AJAX_Base {
    
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
        add_action('wp_ajax_akpp_save_deal', [$this, 'ajax_save_deal']);
        add_action('wp_ajax_akpp_delete_deal', [$this, 'ajax_delete_deal']);
        add_action('wp_ajax_akpp_update_deal_status', [$this, 'ajax_update_deal_status']);
        add_action('wp_ajax_akpp_get_deal', [$this, 'ajax_get_deal']);
        add_action('wp_ajax_akpp_get_deals', [$this, 'ajax_get_deals']);
        add_action('wp_ajax_akpp_decode_vin', [$this, 'ajax_decode_vin']);
    }
    
    // ========================================================================
    // СОХРАНЕНИЕ СДЕЛКИ (ПОЛНАЯ АВТОМАТИЗАЦИЯ)
    // ========================================================================
    
    public function ajax_save_deal() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        
        // Логирование для отладки
        error_log('[AKPP DEALS] Начало сохранения сделки');
        error_log('[AKPP DEALS] POST: ' . print_r($_POST, true));
        
        try {
            $deal_id = intval($_POST['deal_id'] ?? $_POST['id'] ?? 0);
            $lead_id = intval($_POST['lead_id'] ?? 0);
            
            // ================================================================
// 1. СОЗДАЁМ ИЛИ НАХОДИМ КЛИЕНТА
// ================================================================
$client_name = sanitize_text_field($_POST['client_name'] ?? '');
$client_phone = sanitize_text_field($_POST['client_phone'] ?? '');

if (empty($client_name) || empty($client_phone)) {
    wp_send_json_error(['message' => 'Укажите ФИО и телефон клиента']);
    return;
}

// Ищем клиента по телефону
$client_id = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}akpp_site_users WHERE phone = %s LIMIT 1",
    $client_phone
));

if (!$client_id) {
    // Проверяем какие поля есть в таблице
    $columns = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}akpp_site_users");
    
    $client_data = [
        'full_name' => $client_name,
        'phone' => $client_phone
    ];
    
    // Добавляем registered_at если поле существует
    if (in_array('registered_at', $columns)) {
        $client_data['registered_at'] = current_time('mysql');
    }
    
    // Добавляем created_at если поле существует (вместо registered_at)
    if (in_array('created_at', $columns)) {
        $client_data['created_at'] = current_time('mysql');
    }
    
    $wpdb->insert($wpdb->prefix . 'akpp_site_users', $client_data);
    $client_id = $wpdb->insert_id;
    error_log('[AKPP DEALS] Создан новый клиент ID: ' . $client_id);
} else {
    error_log('[AKPP DEALS] Найден существующий клиент ID: ' . $client_id);
}

// Если клиент не создался — выходим
if (!$client_id) {
    wp_send_json_error(['message' => 'Не удалось создать клиента']);
    return;
}            
           // ================================================================
// 2. СОЗДАЁМ ИЛИ НАХОДИМ АВТОМОБИЛЬ
// ================================================================
$vehicle_id = intval($_POST['vehicle_id'] ?? 0);

if ($vehicle_id <= 0) {
    $vin = strtoupper(sanitize_text_field($_POST['vin'] ?? ''));
    $make = sanitize_text_field($_POST['brand'] ?? $_POST['make'] ?? '');
    $model = sanitize_text_field($_POST['model'] ?? '');
    $year = intval($_POST['year'] ?? 0);
    $engine = sanitize_text_field($_POST['engine'] ?? '');
    
    // Ищем по VIN если есть
    if (!empty($vin) && strlen($vin) === 17) {
        $vehicle_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_vehicles WHERE vin = %s LIMIT 1",
            $vin
        ));
        error_log('[AKPP DEALS] Найден автомобиль по VIN ID: ' . ($vehicle_id ?: 'не найден'));
    }
    
    // Если не нашли по VIN — ищем по марке/модели/году
    if (!$vehicle_id && !empty($make) && !empty($model)) {
        $vehicle_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_vehicles 
             WHERE make = %s AND model = %s AND year = %d 
             LIMIT 1",
            $make, $model, $year
        ));
        error_log('[AKPP DEALS] Найден автомобиль по марке/модели ID: ' . ($vehicle_id ?: 'не найден'));
    }
    
    // Создаём если не нашли
    if (!$vehicle_id) {
        $wpdb->insert($wpdb->prefix . 'akpp_vehicles', [
            'vin' => !empty($vin) && strlen($vin) === 17 ? $vin : null,
            'make' => $make,
            'model' => $model,
            'year' => $year,
            'engine' => !empty($engine) ? $engine : null,
            'created_at' => current_time('mysql')
        ]);
        $vehicle_id = $wpdb->insert_id;
        error_log('[AKPP DEALS] Создан новый автомобиль ID: ' . $vehicle_id);
    }
} else {
    error_log('[AKPP DEALS] Используется существующий автомобиль ID: ' . $vehicle_id);
}            
            // ================================================================
            // 3. РАСЧЁТ СТОИМОСТИ
            // ================================================================
            $standard_hours = floatval($_POST['standard_hours'] ?? $_POST['hours'] ?? 0);
            $hourly_rate = floatval($_POST['hourly_rate'] ?? 1500);
            $work_cost_manual = floatval($_POST['work_cost'] ?? 0);
            $total_amount = floatval($_POST['total_amount'] ?? 0);
            $emp_percent = floatval($_POST['emp_percent'] ?? $_POST['employee_percent'] ?? 0);
            
            // Если work_cost не указан вручную — считаем автоматически
            $work_cost = $work_cost_manual > 0 ? $work_cost_manual : ($standard_hours * $hourly_rate);
            
            // Если total_amount не указан — используем work_cost
            if ($total_amount <= 0) {
                $total_amount = $work_cost;
            }
            
            // ================================================================
            // 4. СОБИРАЕМ ДАННЫЕ СДЕЛКИ
            // ================================================================
            $data = [
                'client_id'           => $client_id,
                'vehicle_id'          => $vehicle_id ?: null,
                'employee_id'         => intval($_POST['employee_id'] ?? 0) ?: null,
                'status'              => sanitize_text_field($_POST['status'] ?? 'new'),
                'work_hours'          => $standard_hours,
                'work_cost'           => $work_cost,
                'total_amount'        => $total_amount,
                'problem_description' => sanitize_textarea_field($_POST['comment'] ?? $_POST['description'] ?? ''),
                'updated_at'          => current_time('mysql')
            ];
            
            // Добавляем employee_percent если есть в БД
            if ($emp_percent > 0) {
                $data['employee_percent'] = $emp_percent;
            }
            
            error_log('[AKPP DEALS] Данные сделки: ' . print_r($data, true));
            
            // ================================================================
            // 5. СОХРАНЕНИЕ СДЕЛКИ
            // ================================================================
            if ($deal_id > 0) {
                $result = $wpdb->update($wpdb->prefix . 'akpp_deals', $data, ['id' => $deal_id]);
                $result_id = $deal_id;
                
                // Удаляем старые запчасти
                $wpdb->delete($wpdb->prefix . 'akpp_deal_parts', ['deal_id' => $deal_id]);
                
                error_log('[AKPP DEALS] Сделка обновлена ID: ' . $result_id);
            } else {
                // Проверка дублей по client_id + дата
                $duplicate = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}akpp_deals 
                     WHERE client_id = %d 
                     AND DATE(created_at) = CURDATE()
                     AND total_amount = %f
                     LIMIT 1",
                    $client_id, $total_amount
                ));
                
                if ($duplicate) {
                    error_log('[AKPP DEALS] Найден дубль сделки ID: ' . $duplicate);
                    wp_send_json_success([
                        'id' => intval($duplicate),
                        'message' => 'Сделка уже существует (возвращена существующая)',
                        'duplicate' => true
                    ]);
                    return;
                }
                
                $data['created_at'] = current_time('mysql');
                $wpdb->insert($wpdb->prefix . 'akpp_deals', $data);
                $result_id = $wpdb->insert_id;
                
                error_log('[AKPP DEALS] Создана новая сделка ID: ' . $result_id);
            }
            
            // ================================================================
            // 6. СОХРАНЕНИЕ ЗАПЧАСТЕЙ (если есть)
            // ================================================================
            if (isset($_POST['parts']) && is_array($_POST['parts'])) {
                foreach ($_POST['parts'] as $part) {
                    if (is_string($part)) {
                        $part = json_decode($part, true);
                    }
                    if (!empty($part['id'])) {
                        $wpdb->insert($wpdb->prefix . 'akpp_deal_parts', [
                            'deal_id' => $result_id,
                            'part_id' => intval($part['id']),
                            'quantity' => intval($part['quantity'] ?? 1),
                            'price_at_deal' => floatval($part['price'] ?? 0)
                        ]);
                    }
                }
            } elseif (isset($_POST['deal_parts']) && is_array($_POST['deal_parts'])) {
                foreach ($_POST['deal_parts'] as $part) {
                    $part_id = intval($part['part_id'] ?? 0);
                    $quantity = max(1, intval($part['quantity'] ?? 1));
                    $price = floatval($part['price'] ?? 0);
                    
                    if ($part_id > 0) {
                        $wpdb->insert($wpdb->prefix . 'akpp_deal_parts', [
                            'deal_id' => $result_id,
                            'part_id' => $part_id,
                            'quantity' => $quantity,
                            'price_at_deal' => $price
                        ]);
                    }
                }
            }
            
            // ================================================================
            // 7. КОНВЕРТАЦИЯ ЛИДА (если создаём из лида)
            // ================================================================
            if ($lead_id > 0 && $deal_id <= 0) {
                $wpdb->update($wpdb->prefix . 'akpp_leads', [
                    'status' => 'converted',
                    'deal_id' => $result_id
                ], ['id' => $lead_id]);
                
                error_log('[AKPP DEALS] Лид #' . $lead_id . ' конвертирован в сделку #' . $result_id);
            }
            
            // ================================================================
            // 8. УВЕДОМЛЕНИЕ В TELEGRAM
            // ================================================================
            $this->send_deal_notification($result_id, $client_name, $client_phone, $total_amount);
            
            wp_send_json_success([
                'message' => 'Сделка сохранена',
                'id' => $result_id,
                'work_cost' => $work_cost,
                'total_amount' => $total_amount
            ]);
            
        } catch (Exception $e) {
            error_log('[AKPP DEALS] Ошибка сохранения: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Ошибка сохранения: ' . $e->getMessage()]);
        }
    }
    
    // ========================================================================
    // РАСШИФРОВКА VIN (NHTSA API)
    // ========================================================================
    
    public function ajax_decode_vin() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        $vin = strtoupper(sanitize_text_field($_POST['vin'] ?? ''));
        
        if (strlen($vin) !== 17) {
            wp_send_json_error(['message' => 'VIN должен содержать 17 символов']);
            return;
        }
        
        $response = wp_remote_get("https://vpic.nhtsa.dot.gov/api/vehicles/decodevin/{$vin}?format=json", [
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Ошибка соединения с NHTSA API']);
            return;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['Results'])) {
            wp_send_json_error(['message' => 'VIN не распознан']);
            return;
        }
        
        $data = [];
        foreach ($body['Results'] as $item) {
            $data[$item['Variable']] = $item['Value'];
        }
        
        wp_send_json_success([
            'make' => $data['Make'] ?? '',
            'model' => $data['Model'] ?? '',
            'year' => intval($data['Model Year'] ?? 0),
            'engine' => trim(($data['DisplacementL'] ?? '') . 'L ' . ($data['Engine Model'] ?? '')),
            'country' => $data['Plant Country'] ?? ''
        ]);
    }
    
    // ========================================================================
    // УДАЛЕНИЕ СДЕЛКИ
    // ========================================================================
    
    public function ajax_delete_deal() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        try {
            // Сначала удаляем запчасти
            $wpdb->delete($wpdb->prefix . 'akpp_deal_parts', ['deal_id' => $id]);
            
            // Потом удаляем сделку
            $wpdb->delete($wpdb->prefix . 'akpp_deals', ['id' => $id]);
            
            wp_send_json_success(['message' => 'Сделка удалена']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Ошибка удаления: ' . $e->getMessage()]);
        }
    }
    
    // ========================================================================
    // ОБНОВЛЕНИЕ СТАТУСА
    // ========================================================================
    
    public function ajax_update_deal_status() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $id = intval($_POST['id'] ?? $_POST['deal_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        $valid_statuses = ['new', 'diagnostic', 'in_work', 'waiting_parts', 'completed', 'cancelled'];
        if (!in_array($status, $valid_statuses)) {
            wp_send_json_error(['message' => 'Недопустимый статус']);
            return;
        }
        
        $wpdb->update(
            $wpdb->prefix . 'akpp_deals',
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $id]
        );
        
        wp_send_json_success(['message' => 'Статус обновлён']);
    }
    
    // ========================================================================
    // ПОЛУЧЕНИЕ СДЕЛКИ
    // ========================================================================
    
    public function ajax_get_deal() {
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        
        $deal = $wpdb->get_row($wpdb->prepare(
            "SELECT d.*, 
                    c.full_name as client_name, c.phone as client_phone,
                    v.make, v.model, v.year, v.vin, v.engine,
                    e.name as employee_name
             FROM {$wpdb->prefix}akpp_deals d
             LEFT JOIN {$wpdb->prefix}akpp_site_users c ON d.client_id = c.id
             LEFT JOIN {$wpdb->prefix}akpp_vehicles v ON d.vehicle_id = v.id
             LEFT JOIN {$wpdb->prefix}akpp_employees e ON d.employee_id = e.id
             WHERE d.id = %d",
            $id
        ), ARRAY_A);
        
        if (!$deal) {
            wp_send_json_error(['message' => 'Сделка не найдена']);
            return;
        }
        
        // Получаем запчасти
        $deal['parts'] = $wpdb->get_results($wpdb->prepare(
            "SELECT dp.*, p.name as part_name, p.sku
             FROM {$wpdb->prefix}akpp_deal_parts dp
             LEFT JOIN {$wpdb->prefix}akpp_parts p ON dp.part_id = p.id
             WHERE dp.deal_id = %d",
            $id
        ), ARRAY_A);
        
        wp_send_json_success(['deal' => $deal]);
    }
    
    // ========================================================================
    // ПОЛУЧЕНИЕ СПИСКА СДЕЛОК
    // ========================================================================
    
    public function ajax_get_deals() {
        if (!$this->check_permissions()) return;
        
        global $wpdb;
        $status = sanitize_text_field($_POST['status'] ?? '');
        $limit = intval($_POST['limit'] ?? 50);
        
        $where = "1=1";
        $params = [];
        
        if (!empty($status)) {
            $where .= " AND d.status = %s";
            $params[] = $status;
        }
        
        $query = "SELECT d.*, 
                         c.full_name as client_name, c.phone as client_phone,
                         v.make, v.model, 
                         e.name as employee_name
                  FROM {$wpdb->prefix}akpp_deals d
                  LEFT JOIN {$wpdb->prefix}akpp_site_users c ON d.client_id = c.id
                  LEFT JOIN {$wpdb->prefix}akpp_vehicles v ON d.vehicle_id = v.id
                  LEFT JOIN {$wpdb->prefix}akpp_employees e ON d.employee_id = e.id
                  WHERE {$where}
                  ORDER BY d.created_at DESC
                  LIMIT %d";
        
        $params[] = $limit;
        
        $deals = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        
        wp_send_json_success(['deals' => $deals]);
    }
}