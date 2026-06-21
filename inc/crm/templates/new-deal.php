<?php
/**
 * Шаблон новой сделки
 * Поддерживает создание сделки из лида (lead_id в URL)
 */

if (!defined('ABSPATH')) exit;

global $wpdb;

// ============================================================================
// ЗАГРУЗКА ДАННЫХ ЛИДА (если передан lead_id)
// ============================================================================
$lead_data = null;
$lead_id = isset($_GET['lead_id']) ? intval($_GET['lead_id']) : 0;

if ($lead_id > 0) {
    // Проверка nonce для безопасности
    if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'create_deal_from_lead_' . $lead_id)) {
        $lead_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}akpp_leads WHERE id = %d",
            $lead_id
        ), ARRAY_A);
        
        // НЕ меняем статус лида здесь - это должно быть после сохранения сделки
    }
}

// ============================================================================
// РЕЖИМ РЕДАКТИРОВАНИЯ
// ============================================================================
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$deal_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$deal_data = null;
$deal_parts = [];
$vehicle_data = null;

if ($action === 'edit' && $deal_id > 0) {
    $deal_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}akpp_deals WHERE id = %d", 
        $deal_id
    ), ARRAY_A);
    
    if (!$deal_data) {
        echo '<div class="notice notice-error"><p>Сделка не найдена.</p></div>';
        return;
    }
    
    // Получаем запчасти этой сделки
    $deal_parts = $wpdb->get_results($wpdb->prepare(
        "SELECT dp.*, p.name as part_name, p.sku FROM {$wpdb->prefix}akpp_deal_parts dp 
         LEFT JOIN {$wpdb->prefix}akpp_parts p ON dp.part_id = p.id 
         WHERE dp.deal_id = %d", $deal_id), ARRAY_A);
    
    // Получаем данные авто
    if (!empty($deal_data['vehicle_id']) && $deal_data['vehicle_id'] > 0) {
        $vehicle_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}akpp_vehicles WHERE id = %d", 
            $deal_data['vehicle_id']
        ), ARRAY_A);
    }
}

// ============================================================================
// ДАННЫЕ ДЛЯ ПРЕДЗАПОЛНЕНИЯ (из лида ИЛИ из сделки)
// ============================================================================
if ($deal_data) {
    // Режим редактирования - берём из сделки
    $prefill_client_name  = $deal_data['client_name'] ?? '';
    $prefill_client_phone = $deal_data['client_phone'] ?? '';
    $prefill_car_brand    = $vehicle_data['make'] ?? '';
    $prefill_car_model    = $vehicle_data['model'] ?? '';
    $prefill_problem      = $deal_data['problem_description'] ?? $deal_data['comment'] ?? '';
    $prefill_status       = $deal_data['status'] ?? 'new';
    $prefill_employee_id  = $deal_data['employee_id'] ?? 0;
} elseif ($lead_data) {
    // Создание из лида - берём из лида
    $prefill_client_name  = $lead_data['client_name'] ?? '';
    $prefill_client_phone = $lead_data['client_phone'] ?? '';
    $prefill_car_brand    = $lead_data['car_brand'] ?? '';
    $prefill_car_model    = '';
    $prefill_problem      = $lead_data['problem'] ?? '';
    $prefill_status       = 'new';
    $prefill_employee_id  = 0;
} else {
    // Пустая форма
    $prefill_client_name  = '';
    $prefill_client_phone = '';
    $prefill_car_brand    = '';
    $prefill_car_model    = '';
    $prefill_problem      = '';
    $prefill_status       = 'new';
    $prefill_employee_id  = 0;
}

// ============================================================================
// СПРАВОЧНЫЕ ДАННЫЕ
// ============================================================================
$all_parts = $wpdb->get_results(
    "SELECT id, name, sku, price, quantity FROM {$wpdb->prefix}akpp_parts WHERE quantity > 0 ORDER BY name ASC", 
    ARRAY_A
);

$employees = $wpdb->get_results(
    "SELECT id, name as full_name, role FROM {$wpdb->prefix}akpp_employees WHERE is_active = 1 ORDER BY name ASC", 
    ARRAY_A
);

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

<div class="wrap akpp-crm-wrap">
    <h1 style="color: var(--akpp-accent); margin-bottom: 20px;">
        <?php 
        if ($action === 'edit') {
            echo '✏️ Редактирование сделки #' . $deal_id;
        } elseif ($lead_data) {
            echo '💰 Новая сделка из лида #' . $lead_id;
        } else {
            echo '➕ Новая сделка';
        }
        ?>
    </h1>

    <?php if ($lead_data) : ?>
    <div class="notice notice-info" style="margin-bottom: 20px; padding: 15px; background: #1a2e3a; border-left: 4px solid #63b3ed; color: #63b3ed;">
        <strong>📋 Данные из лида #<?php echo $lead_id; ?>:</strong> 
        <?php echo esc_html($lead_data['client_name']); ?>, 
        <?php echo esc_html($lead_data['client_phone']); ?>, 
        Авто: <?php echo esc_html($lead_data['car_brand']); ?>
    </div>
    <?php endif; ?>

    <form class="akpp-ajax-form" data-action="akpp_save_deal" id="akpp-deal-calculator">
        <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
        
        <?php if ($action === 'edit' && $deal_data) : ?>
            <input type="hidden" name="deal_id" value="<?php echo esc_attr($deal_data['id']); ?>">
        <?php endif; ?>
        
        <?php if ($lead_id > 0) : ?>
            <input type="hidden" name="lead_id" value="<?php echo esc_attr($lead_id); ?>">
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            
            <!-- ЛЕВАЯ КОЛОНКА: Клиент и Авто -->
            <div>
                <div class="akpp-card">
                    <div class="akpp-card-header">👤 Данные клиента</div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>ФИО Клиента *</label>
                        <input type="text" name="client_name" value="<?php echo esc_attr($prefill_client_name); ?>" required style="width: 100%;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Телефон *</label>
                        <input type="tel" name="client_phone" value="<?php echo esc_attr($prefill_client_phone); ?>" required style="width: 100%;">
                    </div>
                </div>

                <div class="akpp-card">
                    <div class="akpp-card-header">🚗 Автомобиль</div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>VIN / Кузовной номер</label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" id="akpp_vin_input" name="vin" value="<?php echo esc_attr($vehicle_data['vin'] ?? ''); ?>" maxlength="17" style="width: 100%; text-transform: uppercase;" placeholder="17 символов">
                            <span class="akpp-vin-status" style="font-size: 12px;"></span>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label>Марка</label>
                            <input type="text" id="akpp_brand_input" name="brand" value="<?php echo esc_attr($prefill_car_brand); ?>" style="width: 100%;">
                            <input type="hidden" name="vehicle_id" value="<?php echo esc_attr($vehicle_data['id'] ?? 0); ?>">
                        </div>
                        <div class="form-group">
                            <label>Модель</label>
                            <input type="text" id="akpp_model_input" name="model" value="<?php echo esc_attr($prefill_car_model); ?>" style="width: 100%;">
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

                <!-- Блок проблемы из лида -->
                <?php if (!empty($prefill_problem)) : ?>
                <div class="akpp-card">
                    <div class="akpp-card-header">🔧 Описание проблемы (из лида)</div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <textarea name="comment" rows="5" style="width: 100%;"><?php echo esc_textarea($prefill_problem); ?></textarea>
                    </div>
                </div>
                <?php else : ?>
                <div class="akpp-card">
                    <div class="akpp-card-header">📝 Комментарий к сделке</div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <textarea name="comment" rows="4" placeholder="Дополнительная информация..." style="width: 100%;"><?php echo esc_textarea($prefill_problem); ?></textarea>
                    </div>
                </div>
                <?php endif; ?>
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
                            <input type="number" step="0.5" name="hours" value="<?php echo esc_attr($deal_data['hours'] ?? '1.0'); ?>" style="width: 100%;">
                        </div>
                        <div class="form-group">
                            <label>Ставка за час (₽)</label>
                            <input type="number" name="hourly_rate" value="<?php echo esc_attr($deal_data['hourly_rate'] ?? '1500'); ?>" style="width: 100%;">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label>Наценка на запчасти (%)</label>
                            <input type="number" name="parts_markup" value="<?php echo esc_attr($deal_data['parts_markup'] ?? '30'); ?>" style="width: 100%;">
                        </div>
                        <div class="form-group">
                            <label>% сотрудника от работ</label>
                            <input type="number" name="emp_percent" value="<?php echo esc_attr($deal_data['emp_percent'] ?? '40'); ?>" style="width: 100%;">
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
                            <input type="hidden" name="cost" value="0">
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
                                <option value="<?php echo esc_attr($emp['id']); ?>" <?php selected($prefill_employee_id, $emp['id']); ?>>
                                    <?php echo esc_html($emp['full_name']); ?> (<?php echo esc_html($emp['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Статус</label>
                        <select name="status" style="width: 100%;">
                            <option value="new" <?php selected($prefill_status, 'new'); ?>>🟢 Новая</option>
                            <option value="diagnostic" <?php selected($prefill_status, 'diagnostic'); ?>>🟡 Диагностика</option>
                            <option value="in_work" <?php selected($prefill_status, 'in_work'); ?>>🟠 В работе</option>
                            <option value="completed" <?php selected($prefill_status, 'completed'); ?>>✅ Завершена</option>
                            <option value="cancelled" <?php selected($prefill_status, 'cancelled'); ?>>❌ Отменена</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 20px; text-align: right;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=akpp-crm-deals')); ?>" class="button button-secondary" style="margin-right: 10px;">Отмена</a>
            <button type="submit" class="button button-primary" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600; padding: 10px 30px; font-size: 16px;">
                💾 Сохранить сделку
            </button>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    console.log('🔍 Отладка new-deal.php');
    console.log('lead_id из GET:', '<?php echo $lead_id; ?>');
console.log('lead_data:', <?php echo json_encode($lead_data); ?>);
console.log('prefill_client_name:', '<?php echo $prefill_client_name; ?>');
console.log('prefill_client_phone:', '<?php echo $prefill_client_phone; ?>');
console.log('prefill_car_brand:', '<?php echo $prefill_car_brand; ?>');
console.log('prefill_car_model:', '<?php echo $prefill_car_model; ?>');
console.log('prefill_problem:', '<?php echo $prefill_problem; ?>');
console.log('prefill_status:', '<?php echo $prefill_status; ?>');
console.log('prefill_employee_id:', '<?php echo $prefill_employee_id; ?>');
console.log('all_parts:', <?php echo json_encode($all_parts); ?>);
console.log('employees:', <?php echo json_encode($employees); ?>);
    let partIndex = <?php echo $part_index; ?>;
    var isSubmitting = false; // Флаг защиты от дублей
    
    console.log('📝 Форма сделки загружена');
    console.log('lead_id:', $('[name="lead_id"]').val() || 'нет');
    
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
                <button type="button" class="button button-link-delete remove-part-row" style="color: var(--akpp-danger);">×</button>
            </div>
        `;
        $('#deal-parts-container').append(newRow);
        partIndex++;
    });

    $(document).on('click', '.remove-part-row', function() {
        $(this).closest('.deal-part-row').remove();
    });

    $(document).on('change', '.part-select', function() {
        const price = $(this).find(':selected').data('price') || 0;
        $(this).closest('.deal-part-row').find('.part-price').val(price);
    });

    // ВАЖНО: Используем off() чтобы удалить все предыдущие обработчики
    $('#akpp-deal-calculator').off('submit').on('submit', function(e) {
        e.preventDefault();
        
        // Защита от двойной отправки
        if (isSubmitting) {
            console.log('⏳ Форма уже отправляется, подождите...');
            return false;
        }
        
        isSubmitting = true;
        
        console.log('📤 Отправка формы сделки...');
        console.log('lead_id:', $('[name="lead_id"]').val());
        console.log('client_name:', $('[name="client_name"]').val());
        console.log('client_phone:', $('[name="client_phone"]').val());
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var originalText = $btn.html();
        
        // Блокируем кнопку
        $btn.prop('disabled', true).html('⏳ Сохранение...');
        
        var formData = $form.serializeArray();
        formData.push({name: 'action', value: 'akpp_save_deal'});
        
        // Отправляем AJAX запрос
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                console.log('✅ Ответ сервера:', response);
                
                if (response.success) {
                    showNotice(response.data.message || '✅ Сделка сохранена!', 'success');
                    
                    // Ждём 1 секунду и перенаправляем
                    setTimeout(function() {
                        window.location.href = '<?php echo esc_url(admin_url('admin.php?page=akpp-crm-deals')); ?>';
                    }, 1000);
                } else {
                    showNotice(response.data.message || '❌ Ошибка сохранения', 'error');
                    $btn.prop('disabled', false).html(originalText);
                    isSubmitting = false;
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Ошибка AJAX:', status, error);
                console.error('Response:', xhr.responseText);
                showNotice('❌ Ошибка соединения с сервером', 'error');
                $btn.prop('disabled', false).html(originalText);
                isSubmitting = false;
            },
            complete: function() {
                // Снимаем блокировку только если не было редиректа
                if (!isSubmitting) {
                    $btn.prop('disabled', false).html(originalText);
                }
            }
        });
        
        return false;
    });
    
    // Функция показа уведомлений
    function showNotice(message, type) {
        var bgColor = type === 'success' ? '#00ff88' : '#fc8181';
        var textColor = type === 'success' ? '#0a0f1c' : '#fff';
        var $notice = $('<div style="position:fixed;top:20px;right:20px;background:' + bgColor + ';color:' + textColor + ';padding:16px 24px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:99999;font-weight:600;max-width:400px;">' + message + '</div>');
        $('body').append($notice);
        setTimeout(function() {
            $notice.fadeOut(300, function() { $(this).remove(); });
        }, 4000);
    }
});
</script>