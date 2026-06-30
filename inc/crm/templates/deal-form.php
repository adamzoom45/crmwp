<?php
/**
 * Шаблон формы создания/редактирования сделки
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Получаем список сотрудников
$table_employees = $wpdb->prefix . 'akpp_employees';
$employees = $wpdb->get_results("SELECT id, name, percent FROM {$table_employees} WHERE is_active = 1 ORDER BY name ASC");

// Получаем список клиентов
$table_users = $wpdb->prefix . 'akpp_site_users';
$clients = $wpdb->get_results("SELECT id, name, email, phone FROM {$table_users} WHERE role = 'client' AND status = 'active' ORDER BY name ASC");

// Режим редактирования
$edit_mode = isset($_GET['id']) && intval($_GET['id']) > 0;
$deal_id = $edit_mode ? intval($_GET['id']) : 0;
$deal_data = null;

if ($edit_mode && $deal_id) {
    $deal_data = $wpdb->get_row($wpdb->prepare(
        "SELECT d.*, 
                c.full_name as client_name, c.phone as client_phone, c.email as client_email,
                v.make, v.model, v.year, v.vin, v.engine,
                e.name as employee_name, e.percent as employee_percent
         FROM {$wpdb->prefix}akpp_deals d
         LEFT JOIN {$wpdb->prefix}akpp_site_users c ON d.client_id = c.id
         LEFT JOIN {$wpdb->prefix}akpp_vehicles v ON d.vehicle_id = v.id
         LEFT JOIN {$wpdb->prefix}akpp_employees e ON d.employee_id = e.id
         WHERE d.id = %d",
        $deal_id
    ));
}
if (!$deal_data) {
    echo '<div class="notice notice-error"><p>❌ Сделка #' . $deal_id . ' не найдена</p></div>';
}
?>

<div class="wrap akpp-crm-wrap">
    <h1 class="wp-heading-inline">
        <?php echo $edit_mode ? '✏️ Редактирование сделки' : '➕ Новая сделка'; ?>
    </h1>
    <hr class="wp-header-end">
    
    <div id="deal-message"></div>
    
    <form id="deal-form" class="deal-form-container">
        <?php wp_nonce_field('akpp_save_deal_nonce', 'deal_nonce'); ?>
        <input type="hidden" name="deal_id" value="<?php echo $deal_id; ?>">
        
        <div class="deal-form-sections">
            
            <!-- ==================== БЛОК 1: ИНФОРМАЦИЯ О КЛИЕНТЕ ==================== -->
            <div class="form-section">
                <h2>👤 Информация о клиенте</h2>
                <div class="form-row">
                    <div class="form-field">
                        <label for="client_id">Клиент <span class="required">*</span></label>
                        <select id="client_id" name="client_id" required>
                            <option value="">Выберите клиента</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client->id; ?>" <?php echo ($deal_data && $deal_data->client_id == $client->id) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($client->name); ?> (<?php echo esc_html($client->phone); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-field">
                        <label for="employee_id">Ответственный сотрудник <span class="required">*</span></label>
                        <select id="employee_id" name="employee_id" required>
                            <option value="">Выберите сотрудника</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee->id; ?>" <?php echo ($deal_data && $deal_data->employee_id == $employee->id) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($employee->name); ?> (процент: <?php echo $employee->percent; ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- ==================== БЛОК 2: ИНФОРМАЦИЯ О АВТОМОБИЛЕ (VIN ДЕКОДЕР) ==================== -->
            <div class="form-section vin-decode-section">
                <h2>🚗 Информация об автомобиле</h2>
                
                <div class="vin-input-group">
                    <div class="form-field" style="flex: 1;">
                        <label for="vin-input">VIN код</label>
                        <input type="text" id="vin-input" name="vin" value="<?php echo $deal_data ? esc_attr($deal_data->vin) : ''; ?>" placeholder="Введите 17-значный VIN код">
                    </div>
                    <button type="button" id="decode-vin-btn" class="button button-primary">🔍 Расшифровать VIN</button>
                    <button type="button" id="clear-vin-form" class="button">🗑️ Очистить</button>
                </div>
                
                <div id="vin-decode-result" style="display: none;"></div>
                
                <div class="vin-fields">
                    <div class="form-row">
                        <div class="form-field">
                            <label for="car-make">Марка</label>
                            <input type="text" id="car-make" name="make" value="<?php echo $deal_data ? esc_attr($deal_data->make) : ''; ?>">
                        </div>
                        <div class="form-field">
                            <label for="car-model">Модель</label>
                            <input type="text" id="car-model" name="model" value="<?php echo $deal_data ? esc_attr($deal_data->model) : ''; ?>">
                        </div>
                        <div class="form-field">
                            <label for="car-year">Год</label>
                            <input type="number" id="car-year" name="year" value="<?php echo $deal_data ? esc_attr($deal_data->year) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="engine-cylinders">Цилиндры</label>
                            <input type="text" id="engine-cylinders" name="engine_cylinders" readonly>
                        </div>
                        <div class="form-field">
                            <label for="engine-model">Двигатель</label>
                            <input type="text" id="engine-model" name="engine_model" readonly>
                        </div>
                        <div class="form-field">
                            <label for="fuel-type">Топливо</label>
                            <input type="text" id="fuel-type" name="fuel_type" readonly>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="drive-type">Привод</label>
                            <input type="text" id="drive-type" name="drive_type" readonly>
                        </div>
                        <div class="form-field">
                            <label for="car-market">Рынок</label>
                            <input type="text" id="car-market" name="market" readonly>
                            <span id="market-badge" class="market-badge"></span>
                        </div>
                    </div>
                    
                    <input type="hidden" id="decoded-vin-data" name="decoded_vin_data">
                </div>
                
                <div id="manual-entry-warning" style="display: none;" class="warning-box">
                    ⚠️ Не удалось автоматически расшифровать VIN. Пожалуйста, заполните поля вручную.
                </div>
            </div>
            
            <!-- ==================== БЛОК 3: ОПИСАНИЕ ПРОБЛЕМЫ ==================== -->
            <div class="form-section">
                <h2>📝 Описание проблемы</h2>
                <div class="form-field">
                    <textarea id="problem-description" name="problem_description" rows="4" placeholder="Опишите проблему с АКПП..."><?php echo $deal_data ? esc_textarea($deal_data->problem_description) : ''; ?></textarea>
                </div>
            </div>
            
            <!-- ==================== БЛОК 4: ЗАПЧАСТИ СО СКЛАДА ==================== -->
            <div class="form-section">
                <h2>📦 Запчасти со склада</h2>
                
                <div class="parts-search-container">
                    <div class="form-field">
                        <label for="parts-search">Поиск запчастей</label>
                        <input type="text" id="parts-search" placeholder="Введите название или артикул..." autocomplete="off">
                    </div>
                    <div id="parts-search-results" class="search-results" style="display: none;"></div>
                </div>
                
                <div id="selected-parts-container">
                    <h3>Выбранные запчасти</h3>
                    <div id="selected-parts-list"></div>
                </div>
            </div>
            
            <!-- ==================== БЛОК 5: КАЛЬКУЛЯТОР ОПЛАТЫ ==================== -->
            <div class="form-section">
                <h2>💰 Калькулятор оплаты</h2>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="work-cost">Стоимость работ (₽)</label>
                        <input type="number" id="work-cost" name="work_cost" value="<?php echo $deal_data ? esc_attr($deal_data->work_cost) : '0'; ?>" step="1000">
                    </div>
                    <div class="form-field">
                        <label for="work-hours">Фактические часы</label>
                        <input type="number" id="work-hours" name="work_hours" value="<?php echo $deal_data ? esc_attr($deal_data->work_hours) : '0'; ?>" step="0.5">
                    </div>
                    <div class="form-field">
                        <label for="standard-hours">Норма часов</label>
                        <input type="number" id="standard-hours" name="standard_hours" value="<?php echo $deal_data ? esc_attr($deal_data->standard_hours) : '1'; ?>" step="0.5">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="percent">Процент сотрудника (%)</label>
                        <input type="number" id="percent" name="percent" value="<?php echo $deal_data ? esc_attr($deal_data->employee_percent) : '0'; ?>" step="5" readonly>
                        <small class="hint">Автоматически подставляется из профиля сотрудника</small>
                    </div>
                    <div class="form-field">
                        <label for="payment-amount">Оплата сотруднику (₽)</label>
                        <input type="text" id="payment-amount" readonly value="0 ₽" class="readonly-field">
                    </div>
                </div>
                
                <div class="total-section">
                    <div class="total-row">
                        <span>💰 Сумма запчастей:</span>
                        <strong id="parts-total">0 ₽</strong>
                    </div>
                    <div class="total-row">
                        <span>🔧 Стоимость работ:</span>
                        <strong id="work-cost-display">0 ₽</strong>
                    </div>
                    <div class="total-row grand-total">
                        <span>💵 ИТОГО по сделке:</span>
                        <strong id="deal-total-amount">0 ₽</strong>
                    </div>
                </div>
                
                <input type="hidden" id="deal-total" name="total_amount">
                <input type="hidden" id="deal-payment-amount" name="payment_amount">
                <input type="hidden" id="saved-parts-data" name="saved_parts">
            </div>
            
            <!-- ==================== БЛОК 6: СТАТУС ==================== -->
            <div class="form-section">
                <h2>📊 Статус сделки</h2>
                <div class="form-field">
                    <select id="deal-status" name="status">
                        <option value="new" <?php echo ($deal_data && $deal_data->status == 'new') ? 'selected' : ''; ?>>🆕 Новая</option>
                        <option value="diagnostic" <?php echo ($deal_data && $deal_data->status == 'diagnostic') ? 'selected' : ''; ?>>🔧 Диагностика</option>
                        <option value="in_work" <?php echo ($deal_data && $deal_data->status == 'in_work') ? 'selected' : ''; ?>>⚙️ В работе</option>
                        <option value="completed" <?php echo ($deal_data && $deal_data->status == 'completed') ? 'selected' : ''; ?>>✅ Выполнена</option>
                        <option value="rejected" <?php echo ($deal_data && $deal_data->status == 'rejected') ? 'selected' : ''; ?>>❌ Отклонена</option>
                    </select>
                </div>
            </div>
            
            <!-- ==================== БЛОК 7: ДЕЙСТВИЯ ==================== -->
            <div class="form-actions">
                <button type="submit" id="save-deal-btn" class="button button-primary button-large">💾 Сохранить сделку</button>
                <a href="?page=akpp-crm-deals" class="button button-large">❌ Отмена</a>
            </div>
        </div>
    </form>
</div>

<style>
.deal-form-container {
    max-width: 1200px;
    margin-top: 20px;
}

.form-section {
    background: #fff;
    border-radius: 12px;
    padding: 20px 25px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.form-section h2 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 18px;
    color: #333;
    border-left: 4px solid #667eea;
    padding-left: 15px;
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.form-field {
    flex: 1;
    min-width: 180px;
}

.form-field label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    font-size: 13px;
    color: #495057;
}

.form-field input,
.form-field select,
.form-field textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
}

.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}

.readonly-field {
    background: #f8f9fa;
    font-weight: 600;
    color: #28a745;
}

.required {
    color: #dc3545;
}

.vin-input-group {
    display: flex;
    gap: 10px;
    align-items: flex-end;
    margin-bottom: 20px;
}

#decode-vin-btn {
    height: 42px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border: none;
}

.search-results {
    margin-top: 15px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    max-height: 300px;
    overflow-y: auto;
    background: #fff;
}

.search-result-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    border-bottom: 1px solid #f0f2f5;
}

.search-result-item:last-child {
    border-bottom: none;
}

.part-info {
    flex: 1;
}

.part-name {
    font-weight: 600;
    margin-bottom: 4px;
}

.part-sku {
    font-size: 12px;
    color: #6c757d;
}

.part-details {
    display: flex;
    gap: 15px;
    font-size: 12px;
    margin-top: 5px;
}

.in-stock {
    border-left: 3px solid #28a745;
}

.out-of-stock {
    border-left: 3px solid #dc3545;
    opacity: 0.6;
}

.add-part-btn {
    padding: 6px 15px;
    background: #28a745;
    color: #fff;
    border: none;
    border-radius: 20px;
    cursor: pointer;
}

.add-part-btn.disabled {
    background: #6c757d;
    cursor: not-allowed;
}

.parts-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.parts-table th,
.parts-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.parts-table th {
    background: #f8f9fa;
    font-weight: 600;
}

.part-quantity {
    width: 70px;
    padding: 5px;
    text-align: center;
}

.remove-part {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 18px;
    color: #dc3545;
}

.total-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 2px solid #e9ecef;
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 16px;
}

.grand-total {
    font-size: 20px;
    font-weight: bold;
    color: #28a745;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #e9ecef;
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 25px;
}

.warning-box {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 12px 15px;
    border-radius: 4px;
    margin-top: 15px;
}

.hint {
    font-size: 11px;
    color: #6c757d;
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .vin-input-group {
        flex-direction: column;
    }
    
    .search-result-item {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
}
</style>

<script>
var akpp_deal = {
    ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>',
    nonce: '<?php echo wp_create_nonce("akpp_save_deal_nonce"); ?>'
};
</script>

<?php
// Подключаем скрипты
wp_enqueue_script('akpp-vin-decoder', AKPP_CRM_URL . 'assets/js/vin-decoder.js', ['jquery', 'jquery-ui-autocomplete'], AKPP_CRM_VERSION, true);
wp_enqueue_script('akpp-deal-calculator', AKPP_CRM_URL . 'assets/js/deal-calculator.js', ['jquery'], AKPP_CRM_VERSION, true);
wp_localize_script('akpp-vin-decoder', 'akpp_vin', [
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('akpp_decode_vin_nonce')
]);
?>
