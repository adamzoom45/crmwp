<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Проверяем режим редактирования
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$deal_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$deal_data = null;
$deal_parts = [];

if ($action === 'edit' && $deal_id > 0) {
    $deal_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}akpp_deals WHERE id = %d", $deal_id), ARRAY_A);
    if (!$deal_data) {
        echo '<div class="notice notice-error"><p>Сделка не найдена.</p></div>';
        return;
    }
    // Получаем запчасти этой сделки
    $deal_parts = $wpdb->get_results($wpdb->prepare(
        "SELECT dp.*, p.name as part_name, p.sku FROM {$wpdb->prefix}akpp_deal_parts dp 
         LEFT JOIN {$wpdb->prefix}akpp_parts p ON dp.part_id = p.id 
         WHERE dp.deal_id = %d", $deal_id), ARRAY_A);
    
    // Получаем данные авто для предзаполнения
    if ($deal_data['vehicle_id'] > 0) {
        $vehicle_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}akpp_vehicles WHERE id = %d", $deal_data['vehicle_id']), ARRAY_A);
    }
}

// Получаем список запчастей для выпадающего списка
$all_parts = $wpdb->get_results("SELECT id, name, sku, price, quantity FROM {$wpdb->prefix}akpp_parts WHERE quantity > 0 ORDER BY name ASC", ARRAY_A);

// Получаем список сотрудников
$employees = $wpdb->get_results("SELECT id, full_name, role FROM {$wpdb->prefix}akpp_employees WHERE status = 'active' ORDER BY full_name ASC", ARRAY_A);

?>

<div class="wrap akpp-crm-wrap">
    <h1 style="color: var(--akpp-accent); margin-bottom: 20px;">
        <?php echo $action === 'edit' ? '✏️ Редактирование сделки #' . $deal_id : '➕ Новая сделка'; ?>
    </h1>

    <form class="akpp-ajax-form" data-action="akpp_save_deal" id="akpp-deal-calculator">
        <?php wp_nonce_field('akpp_crm_nonce', 'nonce'); ?>
        <?php if ($action === 'edit' && $deal_data) : ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($deal_data['id']); ?>">
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            
            <!-- ЛЕВАЯ КОЛОНКА: Клиент и Авто -->
            <div>
                <div class="akpp-card">
                    <div class="akpp-card-header">👤 Данные клиента</div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>ФИО Клиента *</label>
                        <input type="text" name="client_name" value="<?php echo esc_attr($deal_data['client_name'] ?? ''); ?>" required style="width: 100%;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Телефон *</label>
                        <input type="tel" name="client_phone" value="<?php echo esc_attr($deal_data['client_phone'] ?? ''); ?>" required style="width: 100%;">
                    </div>
                </div>

                <div class="akpp-card">
                    <div class="akpp-card-header">🚗 Автомобиль</div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>VIN / Кузовной номер</label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" id="akpp_vin_input" name="vin" value="<?php echo esc_attr($vehicle_data['vin'] ?? $deal_data['vin'] ?? ''); ?>" maxlength="17" style="width: 100%; text-transform: uppercase;" placeholder="17 символов">
                            <span class="akpp-vin-status" style="font-size: 12px;"></span>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label>Марка</label>
                            <input type="text" id="akpp_brand_input" name="brand" value="<?php echo esc_attr($vehicle_data['brand'] ?? ''); ?>" style="width: 100%;">
                            <input type="hidden" name="vehicle_id" value="<?php echo esc_attr($vehicle_data['id'] ?? 0); ?>">
                        </div>
                        <div class="form-group">
                            <label>Модель</label>
                            <input type="text" id="akpp_model_input" name="model" value="<?php echo esc_attr($vehicle_data['model'] ?? ''); ?>" style="width: 100%;">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Год</label>
                            <input type="number" id="akpp_year_input" name="year" value="<?php echo esc_attr($vehicle_data['year'] ?? ''); ?>" style="width: 100%;">
                        </div>
                        <div class="form-group">
                            <label>Двигатель</label>
                            <input type="text" id="akpp_engine_input" name="engine" value="<?php echo esc_attr($vehicle_data['engine'] ?? ''); ?>" style="width: 100%;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ПРАВАЯ КОЛОНКА: Работы, Запчасти и Расчет -->
            <div>
                <div class="akpp-card">
                    <div class="akpp-card-header">🔧 Запчасти и материалы</div>
                    <div id="deal-parts-container">
                        <?php 
                        $part_index = 0;
                        if (!empty($deal_parts)) {
                            foreach ($deal_parts as $dp) {
                                render_part_row($dp, $all_parts, $part_index);
                                $part_index++;
                            }
                        } else {
                            render_part_row(null, $all_parts, 0);
                        }
                        ?>
                    </div>
                    <button type="button" id="add-part-row" class="button button-secondary" style="margin-top: 10px; width: 100%;">+ Добавить запчасть</button>
                </div>

                <div class="akpp-card">
                    <div class="akpp-card-header">💰 Калькулятор стоимости</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label>Нормо-часы</label>
                            <input type="number" step="0.5" name="hours" value="1.0" style="width: 100%;">
                        </div>
                        <div class="form-group">
                            <label>Ставка за час (₽)</label>
                            <input type="number" name="hourly_rate" value="1500" style="width: 100%;">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label>Наценка на запчасти (%)</label>
                            <input type="number" name="parts_markup" value="30" style="width: 100%;">
                        </div>
                        <div class="form-group">
                            <label>% сотрудника от работ</label>
                            <input type="number" name="emp_percent" value="40" style="width: 100%;">
                        </div>
                    </div>

                    <div style="background: var(--akpp-bg-tertiary); padding: 15px; border-radius: 6px; margin-top: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span>Работы:</span>
                            <strong class="calc-labor-cost">0.00 ₽</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span>Запчасти (с наценкой):</span>
                            <strong class="calc-parts-total">0.00 ₽</strong>
                        </div>
                        <hr style="border-color: var(--akpp-border); margin: 10px 0;">
                        <div style="display: flex; justify-content: space-between; font-size: 18px; margin-bottom: 8px;">
                            <span style="color: var(--akpp-accent);">ИТОГО КЛИЕНТУ:</span>
                            <strong class="calc-grand-total" style="color: var(--akpp-accent);">0.00 ₽</strong>
                            <input type="hidden" name="total_amount" value="0">
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--akpp-text-secondary);">
                            <span>Выплата сотруднику:</span>
                            <span class="calc-employee-payout">0.00 ₽</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--akpp-text-secondary);">
                            <span>Прибыль компании:</span>
                            <span class="calc-company-profit">0.00 ₽</span>
                        </div>
                    </div>
                </div>

                <div class="akpp-card">
                    <div class="akpp-card-header">⚙️ Параметры сделки</div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Ответственный сотрудник</label>
                        <select name="employee_id" style="width: 100%;">
                            <option value="0">Не назначен</option>
                            <?php foreach ($employees as $emp) : ?>
                                <option value="<?php echo esc_attr($emp->id); ?>" <?php selected($deal_data['employee_id'] ?? 0, $emp->id); ?>>
                                    <?php echo esc_html($emp->full_name); ?> (<?php echo esc_html($emp->role); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Статус</label>
                        <select name="status" style="width: 100%;">
                            <option value="lead" <?php selected($deal_data['status'] ?? 'new', 'lead'); ?>>🔵 Лид</option>
                            <option value="new" <?php selected($deal_data['status'] ?? 'new', 'new'); ?>>🟢 Новая</option>
                            <option value="diagnostic" <?php selected($deal_data['status'] ?? 'new', 'diagnostic'); ?>>🟡 Диагностика</option>
                            <option value="in_work" <?php selected($deal_data['status'] ?? 'new', 'in_work'); ?>>🟠 В работе</option>
                            <option value="completed" <?php selected($deal_data['status'] ?? 'new', 'completed'); ?>>✅ Завершена</option>
                            <option value="cancelled" <?php selected($deal_data['status'] ?? 'new', 'cancelled'); ?>>❌ Отменена</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 20px; text-align: right;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=akpp-deals')); ?>" class="button button-secondary" style="margin-right: 10px;">Отмена</a>
            <button type="submit" class="button button-primary" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600; padding: 10px 30px; font-size: 16px;">
                💾 Сохранить сделку
            </button>
        </div>
    </form>
</div>

<?php
// Вспомогательная функция для рендера строки запчасти
function render_part_row($part_data, $all_parts, $index) {
    $part_id = $part_data ? $part_data['part_id'] : '';
    $qty = $part_data ? $part_data['quantity'] : 1;
    $price = $part_data ? $part_data['price_at_deal'] : 0;
    ?>
    <div class="deal-part-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
        <select name="parts[<?php echo $index; ?>][id]" class="part-select" style="flex: 2;" required>
            <option value="">Выберите запчасть</option>
            <?php foreach ($all_parts as $p) : ?>
                <option value="<?php echo esc_attr($p['id']); ?>" 
                        data-price="<?php echo esc_attr($p['price']); ?>"
                        data-name="<?php echo esc_attr($p['name']); ?>"
                        <?php selected($part_id, $p['id']); ?>>
                    <?php echo esc_html($p['name'] . ' (' . $p['sku'] . ') - Ост: ' . $p['quantity']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="parts[<?php echo $index; ?>][quantity]" class="part-qty" value="<?php echo esc_attr($qty); ?>" min="1" style="flex: 0.5;" placeholder="Кол-во" required>
        <input type="number" step="0.01" name="parts[<?php echo $index; ?>][price]" class="part-price" value="<?php echo esc_attr($price); ?>" style="flex: 1;" placeholder="Цена" required>
        <button type="button" class="button button-link-delete remove-part-row" style="color: var(--akpp-danger);">✕</button>
    </div>
    <?php
}
?>

<script>
jQuery(document).ready(function($) {
    // 1. Добавление новой строки запчасти
    let partIndex = <?php echo $part_index; ?>;
    
    $('#add-part-row').on('click', function() {
        const newRow = `
            <div class="deal-part-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                <select name="parts[${partIndex}][id]" class="part-select" style="flex: 2;" required>
                    <option value="">Выберите запчасть</option>
                    <?php foreach ($all_parts as $p) : ?>
                        <option value="<?php echo esc_attr($p['id']); ?>" data-price="<?php echo esc_attr($p['price']); ?>">
                            <?php echo esc_html($p['name'] . ' (' . $p['sku'] . ') - Ост: ' . $p['quantity']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="parts[${partIndex}][quantity]" class="part-qty" value="1" min="1" style="flex: 0.5;" placeholder="Кол-во" required>
                <input type="number" step="0.01" name="parts[${partIndex}][price]" class="part-price" value="0" style="flex: 1;" placeholder="Цена" required>
                <button type="button" class="button button-link-delete remove-part-row" style="color: var(--akpp-danger);">✕</button>
            </div>
        `;
        $('#deal-parts-container').append(newRow);
        partIndex++;
        // Триггерим пересчет, так как добавилась новая строка (хотя и с нулевой ценой пока)
        $('#akpp-deal-calculator input, #akpp-deal-calculator select').first().trigger('change');
    });

    // 2. Удаление строки запчасти
    $(document).on('click', '.remove-part-row', function() {
        $(this).closest('.deal-part-row').remove();
        // Триггерим пересчет
        $('#akpp-deal-calculator input, #akpp-deal-calculator select').first().trigger('change');
    });

    // 3. Автоподстановка цены при выборе запчасти
    $(document).on('change', '.part-select', function() {
        const price = $(this).find(':selected').data('price') || 0;
        $(this).closest('.deal-part-row').find('.part-price').val(price);
        // Триггерим пересчет калькулятора
        $('#akpp-deal-calculator input, #akpp-deal-calculator select').first().trigger('change');
    });

    $(document).on('input change', '.part-qty, .part-price', function() {
        // Триггерим пересчет калькулятора при изменении количества или цены
        $('#akpp-deal-calculator input, #akpp-deal-calculator select').first().trigger('change');
    });

    // 4. Обработка успешного сохранения (перезагрузка или редирект)
    window.akppFormSuccess = function(data, $form) {
        setTimeout(() => {
            window.location.href = '<?php echo esc_url(admin_url('admin.php?page=akpp-deals')); ?>';
        }, 1000);
    };
});
</script>
