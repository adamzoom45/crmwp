<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX модуль: Сделки
 * Создание, редактирование, удаление, смена статуса
 * Автоматическое создание клиента и автомобиля
 * AI-расшифровка VIN (Qwen) + настройка ключа
 *
 * @package AKPP_CRM
 * @version 5.1.0
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
        // Сделки
        add_action('wp_ajax_akpp_save_deal', [$this, 'ajax_save_deal']);
        add_action('wp_ajax_akpp_delete_deal', [$this, 'ajax_delete_deal']);
        add_action('wp_ajax_akpp_update_deal_status', [$this, 'ajax_update_deal_status']);
        add_action('wp_ajax_akpp_get_deal', [$this, 'ajax_get_deal']);
        add_action('wp_ajax_akpp_get_deals', [$this, 'ajax_get_deals']);

        // VIN декодирование
        add_action('wp_ajax_akpp_decode_vin', [$this, 'ajax_decode_vin']);
        add_action('wp_ajax_akpp_decode_vin_ai', [$this, 'ajax_decode_vin_ai']);

        // Настройки Qwen AI
        add_action('wp_ajax_akpp_save_qwen_settings', [$this, 'ajax_save_qwen_settings']);
        add_action('wp_ajax_akpp_get_qwen_settings', [$this, 'ajax_get_qwen_settings']);
        add_action('wp_ajax_akpp_test_qwen_connection', [$this, 'ajax_test_qwen_connection']);
    }

    // ========================================================================
    // СОХРАНЕНИЕ СДЕЛКИ (ПОЛНАЯ АВТОМАТИЗАЦИЯ)
    // ========================================================================

    public function ajax_save_deal() {
        // ЖЕЛЕЗНЫЙ логгер fatal (минует все настройки error_log)
        register_shutdown_function(function() {
            $e = error_get_last();
            if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                @file_put_contents(
                    WP_CONTENT_DIR . '/fatal.log',
                    date('c') . ' | ' . ($e['message'] ?? '') . ' | ' . ($e['file'] ?? '') . ':' . ($e['line'] ?? 0) . "\n",
                    FILE_APPEND
                );
            }
        });
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');

        global $wpdb;

        try {
            $deal_id = intval($_POST['deal_id'] ?? $_POST['id'] ?? 0);
            $lead_id = intval($_POST['lead_id'] ?? 0);
            $client_id = intval($_POST['client_id'] ?? 0);

            // ================================================================
            // 1. СОЗДАЁМ ИЛИ НАХОДИМ КЛИЕНТА
            // ================================================================
            $client_name  = sanitize_text_field($_POST['client_name'] ?? '');
            $client_phone = sanitize_text_field($_POST['client_phone'] ?? '');
            $client_email = sanitize_email($_POST['client_email'] ?? '');

            if ($client_id <= 0 && (empty($client_name) || empty($client_phone))) {
                wp_send_json_error(['message' => 'Укажите ФИО и телефон клиента']);
                return;
            }

            if ($client_id > 0) goto skip_client_creation;
            $client_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}akpp_site_users WHERE phone = %s LIMIT 1",
                $client_phone
            ));

            if (!$client_id) {
                $wpdb->insert($wpdb->prefix . 'akpp_site_users', [
                    'full_name'     => $client_name,
                    'phone'         => $client_phone,
                    'email'         => !empty($client_email) ? $client_email : null,
                    'registered_at' => current_time('mysql'),
                ]);
                $client_id = $wpdb->insert_id;

                if (!$client_id) {
                    wp_send_json_error(['message' => 'Не удалось создать клиента']);
                    return;
                }
            }
            skip_client_creation:

            // ================================================================
            // 2. СОЗДАЁМ ИЛИ НАХОДИМ АВТОМОБИЛЬ
            // ================================================================
            $vehicle_id = intval($_POST['vehicle_id'] ?? 0);

            if ($vehicle_id <= 0) {
                $vin    = strtoupper(sanitize_text_field($_POST['vin'] ?? ''));
                $make   = sanitize_text_field($_POST['brand'] ?? $_POST['make'] ?? '');
                $model  = sanitize_text_field($_POST['model'] ?? '');
                $year   = intval($_POST['year'] ?? 0);
                $engine = sanitize_text_field($_POST['engine'] ?? '');

                if (!empty($vin) && strlen($vin) === 17) {
                    $vehicle_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}akpp_vehicles WHERE vin = %s LIMIT 1",
                        $vin
                    ));
                }

                if (!$vehicle_id && !empty($make) && !empty($model)) {
                    $vehicle_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}akpp_vehicles WHERE make = %s AND model = %s AND year = %d LIMIT 1",
                        $make, $model, $year
                    ));
                }

                if (!$vehicle_id) {
                    $wpdb->insert($wpdb->prefix . 'akpp_vehicles', [
                        'vin'        => (!empty($vin) && strlen($vin) === 17) ? $vin : null,
                        'make'       => $make,
                        'model'      => $model,
                        'year'       => $year,
                        'engine'     => !empty($engine) ? $engine : null,
                        'created_at' => current_time('mysql'),
                    ]);
                    $vehicle_id = $wpdb->insert_id;
                }
            }

            // ================================================================
            // 3. РАСЧЁТ СТОИМОСТИ
            // ================================================================
            $standard_hours   = floatval($_POST['standard_hours'] ?? $_POST['hours'] ?? 0);
            $hourly_rate      = floatval($_POST['hourly_rate'] ?? 1500);
            $work_cost_manual = floatval($_POST['work_cost'] ?? 0);
            $total_amount     = floatval($_POST['total_amount'] ?? 0);
            $emp_percent      = floatval($_POST['emp_percent'] ?? $_POST['employee_percent'] ?? 0);

            $work_cost      = $work_cost_manual > 0 ? $work_cost_manual : ($standard_hours * $hourly_rate);
            $employee_share = ($work_cost * $emp_percent) / 100;

            if ($total_amount <= 0) {
                $parts_total = 0;
                if (isset($_POST['parts']) && is_array($_POST['parts'])) {
                    foreach ($_POST['parts'] as $part) {
                        if (is_string($part)) {
                            $part = json_decode($part, true);
                        }
                        $parts_total += floatval($part['price'] ?? 0) * intval($part['quantity'] ?? 1);
                    }
                }
                $total_amount = $work_cost + $parts_total;
            }

            // ================================================================
            // 4. ФОРМИРУЕМ ДАННЫЕ СДЕЛКИ
            // ================================================================
            $data = [
                'client_id'      => $client_id,
                'vehicle_id'     => $vehicle_id ?: null,
                'employee_id'    => $this->resolve_deal_employee_id(),
                'status'         => sanitize_text_field($_POST['status'] ?? 'new'),
                'standard_hours' => $standard_hours,
                'hourly_rate'    => $hourly_rate,
                'work_cost'      => $work_cost,
                'employee_percent' => $emp_percent,
                'total_amount'   => $total_amount,
                'problem_description' => sanitize_textarea_field($_POST['comment'] ?? $_POST['description'] ?? ''),
                'service_category' => sanitize_text_field($_POST['service_category'] ?? 'akpp'),
                'updated_at'     => current_time('mysql'),
            ];

            // ================================================================
            // 5. СОХРАНЕНИЕ СДЕЛКИ
            // ================================================================
            if ($deal_id > 0) {
                $wpdb->update($wpdb->prefix . 'akpp_deals', $data, ['id' => $deal_id]);
                $result_id = $deal_id;
                $wpdb->delete($wpdb->prefix . 'akpp_deal_parts', ['deal_id' => $deal_id]);
            } else {
                $duplicate = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}akpp_deals
                     WHERE client_id = %d AND total_amount = %f AND work_cost = %f AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND) /* dedup-fix-8d */
                     LIMIT 1",
                    $client_id, $total_amount, $work_cost
                ));

                if ($duplicate) {
                    wp_send_json_success([
                        'id'        => intval($duplicate),
                        'message'   => 'Сделка уже существует (возвращена существующая)',
                        'duplicate' => true,
                    ]);
                    return;
                }

                $data['created_at'] = current_time('mysql');
                $wpdb->insert($wpdb->prefix . 'akpp_deals', $data);
                $result_id = $wpdb->insert_id;
                if (!$result_id) { wp_send_json_error(['message' => '❌ Ошибка записи в БД: ' . $wpdb->last_error]); return; }
            }

            // ================================================================
            // 6. СОХРАНЕНИЕ ЗАПЧАСТЕЙ
            // ================================================================
            $parts_data = $_POST['parts'] ?? $_POST['deal_parts'] ?? [];

            if (is_array($parts_data) && !empty($parts_data)) {
                foreach ($parts_data as $part) {
                    if (is_string($part)) {
                        $part = json_decode($part, true);
                    }
                    if (!is_array($part)) continue;

                    $part_id   = intval($part['id'] ?? $part['part_id'] ?? 0);
                    $source    = sanitize_text_field($part['source'] ?? 'part');
                    $part_name = sanitize_text_field($part['name'] ?? '');
                    $part_sku  = sanitize_text_field($part['sku'] ?? '');
                    $quantity  = max(1, intval($part['quantity'] ?? 1));
                    $price     = floatval($part['price'] ?? $part['price_at_deal'] ?? 0);
                    // склад -> реальный part_id; магазин/масла/ручные -> 0 (свободная позиция)
                    $real_part_id = ($source === 'part' && $part_id > 0) ? $part_id : null;
                    if ($real_part_id > 0 || $part_name !== '') {
                        static $dp_cols = null;
                        if ($dp_cols === null) $dp_cols = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}akpp_deal_parts", 0);
                        $price_col = in_array('price_at_deal', $dp_cols) ? 'price_at_deal' : 'price';
                        $ins = ['deal_id'=>$result_id, 'part_id'=>$real_part_id, 'quantity'=>$quantity, $price_col=>$price];
                        if (in_array('part_name', $dp_cols)) $ins['part_name'] = $part_name;
                        if (in_array('part_sku', $dp_cols))  $ins['part_sku']  = $part_sku;
                        if (in_array('total', $dp_cols))     $ins['total']     = $price * $quantity;
                        $wpdb->insert($wpdb->prefix . 'akpp_deal_parts', $ins);
                    }
                }
            }

            // ================================================================
            // ================================================================
            // 6b. СОХРАНЕНИЕ УСЛУГ СДЕЛКИ (8c-3b)
            // ================================================================
            $services_data = $_POST['services'] ?? [];
            if (is_array($services_data) && !empty($services_data)) {
                foreach ($services_data as $svc) {
                    if (is_string($svc)) $svc = json_decode($svc, true);
                    if (!is_array($svc)) continue;
                    $service_id   = intval($svc['id'] ?? $svc['service_id'] ?? 0);
                    $service_name = sanitize_text_field($svc['name'] ?? '');
                    $quantity     = max(1, intval($svc['quantity'] ?? 1));
                    $norm_hours   = floatval($svc['norm_hours'] ?? 0);
                    $price        = floatval($svc['price'] ?? 0);
                    if ($service_id > 0 || $service_name !== '') {
                        $wpdb->insert($wpdb->prefix . 'akpp_deal_services', [
                            'deal_id'      => $result_id,
                            'service_id'   => $service_id > 0 ? $service_id : null,
                            'service_name' => $service_name,
                            'quantity'     => $quantity,
                            'norm_hours'   => $norm_hours,
                            'price'        => $price,
                            'total'        => $price * $quantity,
                        ]);
                    }
                }
            }


            // Пересчёт total_amount с учётом услуг (8c-3b-C)
            $services_total = (float)$wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(total), 0) FROM {$wpdb->prefix}akpp_deal_services WHERE deal_id = %d", $result_id));
            if ($services_total > 0) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}akpp_deals SET total_amount = total_amount + %f WHERE id = %d",
                    $services_total, $result_id));
                $total_amount += $services_total; // 8c-3b-C-fix: актуальный итог для ответа и уведомления
            }
            delete_transient('akpp_dashboard_stats'); // dash-cache-clear-8c3b
            // 7. КОНВЕРТАЦИЯ ЛИДА
            // ================================================================
            if ($lead_id > 0 && $deal_id <= 0) {
                $wpdb->update(
                    $wpdb->prefix . 'akpp_leads',
                    ['status' => 'converted', 'deal_id' => $result_id, 'updated_at' => current_time('mysql')],
                    ['id' => $lead_id]
                );
            }

            // ================================================================
            // 8. УВЕДОМЛЕНИЕ В TELEGRAM
            // ================================================================
            $this->send_deal_notification($result_id, $client_name, $client_phone, $total_amount);

            // ================================================================
            // 9. ОТВЕТ
            // ================================================================
            wp_send_json_success([
                'message'      => 'Сделка сохранена',
                'id'           => $result_id,
                'work_cost'    => $work_cost,
                'total_amount' => $total_amount,
            ]);

        } catch (Throwable $e) {
            error_log('[AKPP DEALS] Ошибка: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Ошибка сохранения: ' . $e->getMessage()]);
        }
    }

    // ========================================================================
    // УДАЛЕНИЕ СДЕЛКИ
    // ========================================================================

    public function ajax_delete_deal() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');

        global $wpdb;
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            wp_send_json_error(['message' => 'Неверный ID сделки']);
            return;
        }


        // Этап 4: механик удаляет только свою сделку
        if ($this->should_filter_by_employee()) {
            $deal = $wpdb->get_row($wpdb->prepare("SELECT id, employee_id FROM {$wpdb->prefix}akpp_deals WHERE id = %d", $id), ARRAY_A);
            if (!$deal || intval($deal['employee_id'] ?? 0) !== $this->get_my_employee_id()) {
                wp_send_json_error(['message' => 'Сделка не найдена']);
                return;
            }
        }
        try {
            $wpdb->delete($wpdb->prefix . 'akpp_deal_parts', ['deal_id' => $id]);
            $wpdb->delete($wpdb->prefix . 'akpp_deals', ['id' => $id]);
            wp_send_json_success(['message' => 'Сделка удалена']);
        } catch (Throwable $e) {
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

        $id     = intval($_POST['id'] ?? $_POST['deal_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');

        $valid_statuses = ['new', 'diagnostic', 'in_work', 'waiting_parts', 'completed', 'cancelled'];

        if (!in_array($status, $valid_statuses, true)) {
            wp_send_json_error(['message' => 'Недопустимый статус: ' . $status]);
            return;
        }


        // Этап 4: механик меняет статус только своей сделки
        if ($this->should_filter_by_employee()) {
            $deal = $wpdb->get_row($wpdb->prepare("SELECT id, employee_id FROM {$wpdb->prefix}akpp_deals WHERE id = %d", $id), ARRAY_A);
            if (!$deal || intval($deal['employee_id'] ?? 0) !== $this->get_my_employee_id()) {
                wp_send_json_error(['message' => 'Сделка не найдена']);
                return;
            }
        }
        $wpdb->update(
            $wpdb->prefix . 'akpp_deals',
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $id]
        );

        wp_send_json_success(['message' => 'Статус обновлён', 'status' => $status]);
    }

    // ========================================================================
    // ПОЛУЧЕНИЕ ОДНОЙ СДЕЛКИ
    // ========================================================================

    public function ajax_get_deal() {
        if (!$this->check_permissions()) return;

        global $wpdb;
        $id = intval($_POST['id'] ?? $_POST['deal_id'] ?? 0);

        if ($id <= 0) {
            wp_send_json_error(['message' => 'Неверный ID']);
            return;
        }

        $deal = $wpdb->get_row($wpdb->prepare(
            "SELECT d.*,
                    c.full_name AS client_name,
                    c.phone AS client_phone,
                    c.email AS client_email,
                    v.make, v.model, v.year, v.vin, v.engine,
                    e.name AS employee_name
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

        // Этап 4: механик видит только свою сделку
        if ($this->should_filter_by_employee()) {
            $my_emp_id = $this->get_my_employee_id();
            if (intval($deal['employee_id'] ?? 0) !== $my_emp_id) {
                wp_send_json_error(['message' => 'Сделка не найдена']);
                return;
            }
        }

        $deal['parts'] = $wpdb->get_results($wpdb->prepare(
            "SELECT dp.*, p.name AS part_name, p.sku
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
        $search = sanitize_text_field($_POST['search'] ?? '');
        $limit  = min(100, intval($_POST['limit'] ?? 50));
        $offset = intval($_POST['offset'] ?? 0);

        $where  = "1=1";
        $params = [];

        // Этап 4: механик видит только свои сделки
        if ($this->should_filter_by_employee()) {
            $my_emp_id = $this->get_my_employee_id();
            if ($my_emp_id > 0) {
                $where .= " AND d.employee_id = %d";
                $params[] = $my_emp_id;
            } else {
                $where .= " AND 1=0"; // механик без привязки к сотруднику — ничего не видит
            }
        }

        if (!empty($status)) {
            $where .= " AND d.status = %s";
            $params[] = $status;
        }

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (c.full_name LIKE %s OR c.phone LIKE %s OR v.make LIKE %s OR v.model LIKE %s)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}akpp_deals d
             LEFT JOIN {$wpdb->prefix}akpp_site_users c ON d.client_id = c.id
             LEFT JOIN {$wpdb->prefix}akpp_vehicles v ON d.vehicle_id = v.id
             WHERE {$where}",
            $params
        ));

        $params[] = $limit;
        $params[] = $offset;

        $deals = $wpdb->get_results($wpdb->prepare(
            "SELECT d.*,
                    c.full_name AS client_name,
                    c.phone AS client_phone,
                    v.make, v.model, v.year,
                    e.name AS employee_name
             FROM {$wpdb->prefix}akpp_deals d
             LEFT JOIN {$wpdb->prefix}akpp_site_users c ON d.client_id = c.id
             LEFT JOIN {$wpdb->prefix}akpp_vehicles v ON d.vehicle_id = v.id
             LEFT JOIN {$wpdb->prefix}akpp_employees e ON d.employee_id = e.id
             WHERE {$where}
             ORDER BY d.created_at DESC
             LIMIT %d OFFSET %d",
            $params
        ), ARRAY_A);

        wp_send_json_success([
            'deals' => $deals,
            'total' => (int) $total,
        ]);
    }

    // ========================================================================
    // РАСШИФРОВКА VIN (простая, NHTSA) — для совместимости
    // ========================================================================

    public function ajax_decode_vin() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');

        $vin = strtoupper(sanitize_text_field($_POST['vin'] ?? ''));

        if (strlen($vin) !== 17) {
            wp_send_json_error(['message' => 'VIN должен содержать 17 символов']);
            return;
        }

        $result = $this->decode_vin_via_nhtsa($vin);

        if ($result) {
            wp_send_json_success(['data' => $result]);
        } else {
            wp_send_json_error(['message' => 'VIN не распознан']);
        }
    }

    // ========================================================================
    // AI РАСШИФРОВКА VIN (хук akpp_decode_vin_ai — то, что шлёт фронт)
    // Порядок: кэш → Qwen AI (если ключ) → NHTSA (бесплатный fallback)
    // Фронт ждёт ответ в res.data.data {make, model, year, engine, transmission_code}
    // ========================================================================

    public function ajax_decode_vin_ai() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');

        $vin = strtoupper(sanitize_text_field($_POST['vin'] ?? ''));

        if (strlen($vin) !== 17) {
            wp_send_json_error(['message' => 'VIN должен содержать 17 символов']);
            return;
        }

        global $wpdb;

        // 1. Кэш (безопасно: SELECT * не падает если колонок нет)
        $cache_table = $wpdb->prefix . 'akpp_vin_cache';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $cache_table)) === $cache_table) {
            $cached = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$cache_table} WHERE vin = %s LIMIT 1", $vin
            ), ARRAY_A);
            if ($cached) {
                $json = $cached['decoded_data'] ?? $cached['data'] ?? '';
                $cached_data = json_decode($json, true);
                if (is_array($cached_data) && !empty($cached_data['make'])) {
                    wp_send_json_success(['message' => '✅ VIN расшифрован (кэш)', 'data' => $cached_data]);
                    return;
                }
            }
        }

        // 2. Qwen AI (если ключ настроен)
        $api_key = $this->get_qwen_api_key();
        if (!empty($api_key)) {
            $ai = $this->decode_vin_via_ai($vin, $api_key);
            if ($ai) {
                $this->cache_vin_result($vin, $ai);
                wp_send_json_success(['message' => '✅ VIN расшифрован (AI)', 'data' => $ai]);
                return;
            }
        }

        // 3. NHTSA (бесплатно, без ключа)
        $nhtsa = $this->decode_vin_via_nhtsa($vin);
        if ($nhtsa) {
            $this->cache_vin_result($vin, $nhtsa);
            wp_send_json_success(['message' => '✅ VIN расшифрован', 'data' => $nhtsa]);
            return;
        }

        wp_send_json_error(['message' => 'Не удалось расшифровать VIN. Заполните данные вручную.']);
    }

    /**
     * Декодирование через Qwen AI (DashScope)
     */
    private function decode_vin_via_ai($vin, $api_key) {
        $prompt = "Расшифруй VIN номер {$vin}. Верни ТОЛЬКО валидный JSON без markdown в формате: "
            . '{"make":"марка","model":"модель","year":год_числом,"engine":"объем и тип двигателя","engine_code":"код двигателя","transmission":"тип КПП","transmission_code":"код АКПП"}';

        $response = wp_remote_post(get_option('akpp_qwen_endpoint', 'https://ws-pomu6e8cx1hvgwnc.ap-southeast-1.maas.aliyuncs.com/compatible-mode/v1') . '/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'    => $this->get_qwen_model(),
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]),
        ]);

        if (is_wp_error($response)) return null;

        $body    = json_decode(wp_remote_retrieve_body($response), true);
        $content = $body['choices'][0]['message']['content'] ?? '';
        if (empty($content)) return null;

        // Чистим возможную markdown-разметку ```json ... ```
        $content = trim(preg_replace('/```(?:json)?/i', '', $content));
        $data    = json_decode($content, true);

        if (!is_array($data) || empty($data['make'])) return null;

        return [
            'make'              => sanitize_text_field($data['make'] ?? ''),
            'model'             => sanitize_text_field($data['model'] ?? ''),
            'year'              => intval($data['year'] ?? 0),
            'engine'            => sanitize_text_field($data['engine'] ?? ''),
            'engine_code'       => sanitize_text_field($data['engine_code'] ?? ''),
            'transmission'      => sanitize_text_field($data['transmission'] ?? ''),
            'transmission_code' => sanitize_text_field($data['transmission_code'] ?? ''),
        ];
    }

    /**
     * Декодирование через NHTSA (бесплатный fallback)
     */
    private function decode_vin_via_nhtsa($vin) {
        $response = wp_remote_get(
            "https://vpic.nhtsa.dot.gov/api/vehicles/decodevin/{$vin}?format=json",
            ['timeout' => 15]
        );

        if (is_wp_error($response)) return null;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['Results'])) return null;

        $raw = [];
        foreach ($body['Results'] as $item) {
            if (!empty($item['Variable']) && !empty($item['Value'])) {
                $raw[$item['Variable']] = $item['Value'];
            }
        }

        if (empty($raw['Make'])) return null;

        return [
            'make'              => sanitize_text_field($raw['Make'] ?? ''),
            'model'             => sanitize_text_field($raw['Model'] ?? ''),
            'year'              => intval($raw['Model Year'] ?? 0),
            'engine'            => trim(($raw['DisplacementL'] ?? '') . 'L ' . ($raw['Engine Model'] ?? '')),
            'engine_code'       => sanitize_text_field($raw['Engine Model'] ?? ''),
            'transmission'      => '',
            'transmission_code' => '',
        ];
    }

    /**
     * Безопасная запись в кэш (не падает если таблицы/колонок нет)
     */
    private function cache_vin_result($vin, $data) {
        global $wpdb;
        $cache_table = $wpdb->prefix . 'akpp_vin_cache';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $cache_table)) !== $cache_table) {
            return;
        }
        try {
            $json   = wp_json_encode($data, JSON_UNESCAPED_UNICODE);
            $exists = $wpdb->get_var($wpdb->prepare("SELECT vin FROM {$cache_table} WHERE vin = %s", $vin));
            if ($exists) {
                @$wpdb->update($cache_table, ['decoded_data' => $json], ['vin' => $vin]);
            } else {
                @$wpdb->insert($cache_table, [
                    'vin'          => $vin,
                    'decoded_data' => $json,
                    'created_at'   => current_time('mysql'),
                ]);
            }
        } catch (Throwable $e) {
            // Кэш не критичен — игнорируем
        }
    }

    // ========================================================================
    // НАСТРОЙКИ QWEN AI
    // ========================================================================

    /**
     * Получить API ключ Qwen (с совместимостью со старым именем опции)
     */
    private function get_qwen_api_key() {
        $key = get_option('akpp_qwen_api_key', '');
        if (empty($key)) {
            $key = get_option('akpp_openai_api_key', ''); // совместимость
        }
        return trim($key);
    }

    /**
     * Получить модель Qwen
     */
    private function get_qwen_model() {
        return get_option('akpp_qwen_model', 'qwen-plus');
    }

    /**
     * Сохранить настройки Qwen AI
     */
    public function ajax_save_qwen_settings() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $model   = sanitize_text_field($_POST['model'] ?? 'qwen-plus');

        $allowed_models = ['qwen-plus', 'qwen-turbo', 'qwen-max', 'qwen-long'];
        if (!in_array($model, $allowed_models, true)) {
            $model = 'qwen-plus';
        }

        // Если ключ пустой — не перезаписываем существующий (защита от случайной очистки)
        if (!empty($api_key)) {
            update_option('akpp_qwen_api_key', $api_key);
        }
        update_option('akpp_qwen_model', $model);

        wp_send_json_success(['message' => '✅ Настройки Qwen AI сохранены']);
    }

    /**
     * Получить текущие настройки Qwen AI (ключ маскируется)
     */
    public function ajax_get_qwen_settings() {
        if (!$this->check_permissions()) return;

        $api_key = $this->get_qwen_api_key();
        $model   = $this->get_qwen_model();

        wp_send_json_success([
            'has_key'    => !empty($api_key),
            'key_masked' => !empty($api_key) ? substr($api_key, 0, 4) . '****' . substr($api_key, -4) : '',
            'model'      => $model,
        ]);
    }

    /**
     * Проверить соединение с Qwen AI
     */
    public function ajax_test_qwen_connection() {
        if (!$this->check_permissions()) return;
        check_ajax_referer('akpp45_nonce', 'nonce');

        $api_key = $this->get_qwen_api_key();
        if (empty($api_key)) {
            wp_send_json_error(['message' => '❌ API ключ не установлен. Сохраните ключ.']);
            return;
        }

        $response = wp_remote_post(get_option('akpp_qwen_endpoint', 'https://ws-pomu6e8cx1hvgwnc.ap-southeast-1.maas.aliyuncs.com/compatible-mode/v1') . '/chat/completions', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'    => $this->get_qwen_model(),
                'messages' => [['role' => 'user', 'content' => 'Ответь одним словом: OK']],
            ]),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => '❌ Ошибка соединения: ' . $response->get_error_message()]);
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200 && !empty($body['choices'][0]['message']['content'])) {
            wp_send_json_success(['message' => '✅ Qwen AI подключён! Модель: ' . $this->get_qwen_model()]);
        } else {
            $error_msg = $body['error']['message'] ?? $body['message'] ?? 'Неизвестная ошибка';
            wp_send_json_error(['message' => '❌ Ошибка API (код ' . $code . '): ' . $error_msg]);
        }
    }

    // ========================================================================
    // УВЕДОМЛЕНИЕ В TELEGRAM
    // ========================================================================

    protected function send_deal_notification($deal_id, $client_name, $client_phone, $total_amount) {
        if (!class_exists('AKPP_Telegram')) return;

        $enabled = get_option('akpp_telegram_notify_deals', 1);
        if (!$enabled) return;

        $message  = "🔧 <b>Новая сделка #{$deal_id}</b>\n\n";
        $message .= "👤 <b>Клиент:</b> {$client_name}\n";
        $message .= "📱 <b>Телефон:</b> {$client_phone}\n";
        $message .= "💰 <b>Сумма:</b> " . number_format($total_amount, 0, '.', ' ') . " ₽\n\n";
        $message .= "🔗 <a href='" . admin_url("admin.php?page=akpp-crm-deals") . "'>Открыть в CRM</a>";

        try { if (class_exists("AKPP_Telegram") && method_exists("AKPP_Telegram","get_instance")) { $tg = AKPP_Telegram::get_instance(); if (is_object($tg) && method_exists($tg,"send_message")) { $tg->send_message($message); } } } catch (Throwable $e) { error_log("[AKPP DEALS] TG notify skipped: " . $e->getMessage()); }
    }
}
