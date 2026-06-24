<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Получаем данные
$employees = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}akpp_employees WHERE is_active = 1 ORDER BY name");
$vehicles = $wpdb->get_results("SELECT id, make, model, year, vin, engine FROM {$wpdb->prefix}akpp_vehicles ORDER BY make, model LIMIT 500");
$parts = $wpdb->get_results("SELECT id, name, sku, category, price, markup_percent, quantity FROM {$wpdb->prefix}akpp_parts WHERE price > 0 ORDER BY name LIMIT 500");
$transmissions = $wpdb->get_results("SELECT id, code, make, model FROM {$wpdb->prefix}akpp_transmissions ORDER BY code LIMIT 200");

// Подключаем текст оферты
if (!function_exists('akpp_get_agreement_text')) {
    require_once dirname(__FILE__) . '/agreement-text.php';
}
?>

<div class="wrap akpp-crm-wrap">
    <div class="deal-page-header">
        <h1>➕ Новая сделка</h1>
    </div>

    <form id="akpp-deal-form" class="akpp-form">
        <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
        <input type="hidden" name="deal_id" id="deal-id">
        <input type="hidden" name="lead_id" value="<?php echo intval($_GET['lead_id'] ?? 0); ?>">

        <!-- КЛИЕНТ -->
        <div class="form-section">
            <h2>👤 Клиент</h2>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>ФИО *</label>
                    <input type="text" name="client_name" id="client-name" required>
                </div>
                <div class="form-group">
                    <label>Телефон *</label>
                    <input type="tel" name="client_phone" id="client-phone" required placeholder="+7 (___) ___-__-__">
                </div>
            </div>
        </div>

        <!-- АВТОМОБИЛЬ -->
        <div class="form-section">
            <h2>🚗 Автомобиль</h2>
            
            <div class="form-group" style="position:relative;">
                <label>Поиск авто из БД (или введите VIN)</label>
                <input type="text" id="vehicle-search" placeholder="Начните вводить марку или модель..." autocomplete="off">
                <div id="vehicle-search-results" class="search-dropdown"></div>
            </div>

            <div class="form-group">
                <label>VIN (не обязательно)</label>
                <div class="vin-input-group">
                    <input type="text" name="vin" id="vin-input" maxlength="17" placeholder="Введите VIN для автозаполнения">
                    <button type="button" id="btn-decode-vin" class="button">🤖 AI Расшифровать</button>
                </div>
            </div>

            <div class="form-grid-3">
                <div class="form-group">
                    <label>Марка *</label>
                    <input type="text" name="brand" id="car-make" required>
                </div>
                <div class="form-group">
                    <label>Модель *</label>
                    <input type="text" name="model" id="car-model" required>
                </div>
                <div class="form-group">
                    <label>Год</label>
                    <input type="number" name="year" id="car-year" min="1950" max="2030">
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label>Двигатель</label>
                    <input type="text" name="engine" id="car-engine" placeholder="2.5L 2AR-FE">
                </div>
                <div class="form-group">
                    <label>Код АКПП</label>
                    <select name="transmission_code" id="transmission-code">
                        <option value="">-- Не указан --</option>
                        <?php foreach ($transmissions as $trans): ?>
                            <option value="<?php echo esc_attr($trans->code); ?>">
                                <?php echo esc_html($trans->code . ' (' . $trans->make . ' ' . $trans->model . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <input type="hidden" name="vehicle_id" id="vehicle-id" value="0">
        </div>

        <!-- РАБОТЫ -->
        <div class="form-section">
            <h2>🔧 Работы</h2>
            
            <div class="form-group">
                <label>Тип расчёта</label>
                <select name="calculation_type" id="calculation-type">
                    <option value="norm">Норма-часы</option>
                    <option value="manual">Ручной ввод</option>
                </select>
            </div>

            <div id="norm-calc-fields">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Норма-часы</label>
                        <input type="number" name="standard_hours" id="standard-hours" step="0.5" min="0" value="1">
                    </div>
                    <div class="form-group">
                        <label>Ставка (₽/час)</label>
                        <input type="number" name="hourly_rate" id="hourly-rate" min="0" value="1500">
                    </div>
                </div>
                <div class="form-group">
                    <label>Стоимость работ (авто)</label>
                    <input type="text" id="work-cost-auto" readonly style="background:#1a3a2e;color:#00ff88;font-weight:700;">
                </div>
            </div>

            <div id="manual-calc-fields" style="display:none;">
                <div class="form-group">
                    <label>Стоимость работ (₽)</label>
                    <input type="number" name="work_cost" id="work-cost-manual" min="0" value="0">
                </div>
            </div>

            <div class="form-group">
                <label>% сотрудника</label>
                <input type="number" name="emp_percent" id="employee-percent" min="0" max="100" value="40">
            </div>
        </div>

        <!-- ЗАПЧАСТИ -->
        <div class="form-section">
            <h2>📦 Запчасти</h2>
            
            <div class="form-group" style="position:relative;">
                <label>Поиск запчасти из БД</label>
                <input type="text" id="part-search" placeholder="Начните вводить название или артикул..." autocomplete="off">
                <div id="part-search-results" class="search-dropdown"></div>
            </div>

            <table class="wp-list-table widefat striped" id="parts-table">
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Артикул</th>
                        <th>Цена (с наценкой)</th>
                        <th>Кол-во</th>
                        <th>Сумма</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="parts-list"></tbody>
                <tfoot>
                    <tr>
                        <td colspan="4"><strong>Итого запчасти:</strong></td>
                        <td><strong id="parts-total">0 ₽</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- ИТОГО -->
        <div class="form-section">
            <h2>💰 Итого</h2>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Работы</label>
                    <input type="text" id="total-work" readonly style="background:#1a3a2e;color:#00ff88;">
                </div>
                <div class="form-group">
                    <label>Запчасти</label>
                    <input type="text" id="total-parts" readonly style="background:#1a3a2e;color:#00ff88;">
                </div>
            </div>
            <div class="form-group">
                <label>Общая сумма *</label>
                <input type="number" name="total_amount" id="total-amount" min="0" required 
                       style="font-size:24px;font-weight:700;color:#00ff88;">
            </div>
        </div>

        <!-- ДОПОЛНИТЕЛЬНО -->
        <div class="form-section">
            <h2>📝 Дополнительно</h2>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Сотрудник</label>
                    <select name="employee_id">
                        <option value="">-- Не выбран --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp->id; ?>"><?php echo esc_html($emp->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Статус</label>
                    <select name="status">
                        <option value="new">Новая</option>
                        <option value="in_progress">В работе</option>
                        <option value="completed">Завершена</option>
                        <option value="cancelled">Отменена</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Описание проблемы</label>
                <textarea name="comment" rows="4" placeholder="Опишите проблему..."></textarea>
            </div>
        </div>

        <!-- ДОГОВОР-ОФЕРТА -->
        <div class="form-section agreement-section" style="background:linear-gradient(135deg,#1a3a2e 0%,#1a1f2e 100%);border-left:4px solid #00ff88;padding:20px;border-radius:8px;margin-bottom:20px;">
            <h2 style="color:#00ff88;margin-top:0;">📜 Договор-оферта</h2>
            
            <div style="background:rgba(0,255,136,0.1);padding:15px;border-radius:8px;margin-bottom:15px;">
                <p style="margin:0;color:#e2e8f0;">
                    <strong>Объявление на Авито:</strong> 
                    <a href="https://www.avito.ru/kurgan/predlozheniya_uslug/remont_akpp_7991698408" 
                       target="_blank" 
                       style="color:#00ff88;font-weight:600;text-decoration:none;">
                        🔗 remont_akpp_7991698408
                    </a>
                </p>
            </div>
            
            <div style="margin-bottom:15px;">
                <button type="button" id="toggle-agreement" class="button" style="background:#2d3748;color:#fff;border:1px solid #4a5568;">
                    📖 Показать текст договора-оферты
                </button>
                <button type="button" id="print-agreement" class="button" style="background:#2d3748;color:#fff;border:1px solid #4a5568;margin-left:10px;">
                    🖨️ Распечатать договор
                </button>
            </div>
            
            <div id="agreement-full-text" style="display:none;margin-bottom:20px;">
                <?php echo akpp_get_agreement_text('1.0'); ?>
            </div>
            
            <div style="background:#0a0f1c;padding:20px;border-radius:8px;border:1px solid #2d3748;">
                <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;color:#e2e8f0;font-size:14px;line-height:1.5;">
                    <input type="checkbox" name="agreement_accepted" id="agreement-accepted" value="1" required 
                           style="width:20px;height:20px;margin-top:2px;cursor:pointer;accent-color:#00ff88;">
                    <span>
                        <strong style="color:#00ff88;">Клиент ознакомлен с условиями договора-оферты</strong> и 
                        <a href="#" id="show-agreement-inline" style="color:#00ff88;text-decoration:none;">согласен с условиями</a> 
                        оказания услуг, включая ограничения гарантийных обязательств, отсутствие гарантии на б/у запчасти 
                        и обработку персональных данных в соответствии с ФЗ № 152-ФЗ
                    </span>
                </label>
                <div id="agreement-warning" style="display:none;margin-top:10px;padding:10px;background:#fc818122;border:1px solid #fc8181;border-radius:6px;color:#fc8181;font-size:13px;">
                    ⚠️ Для сохранения сделки необходимо подтверждение согласия клиента с условиями договора-оферты
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="button button-secondary" onclick="history.back()">Отмена</button>
            <button type="submit" class="button button-primary button-hero">💾 Сохранить сделку</button>
        </div>
    </form>
</div>

<style>
.search-dropdown {
    position: absolute;
    background: #1a1f2e;
    border: 1px solid #2d3748;
    border-radius: 6px;
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    width: 100%;
    display: none;
}
.search-dropdown.active { display: block; }
.search-item {
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid #2d3748;
}
.search-item:hover { background: #2d3748; }
.search-item small { color: #718096; display: block; }
.search-item .price { color: #00ff88; font-weight: 600; }
.vin-input-group { display: flex; gap: 10px; }
.vin-input-group input { flex: 1; }
.form-section {
    background: #1a1f2e;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 8px;
    border-left: 4px solid #00ff88;
}
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}
.form-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 15px;
}
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    color: #00ff88;
    font-weight: 600;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    background: #2d3748;
    border: 1px solid #4a5568;
    border-radius: 4px;
    color: #fff;
    box-sizing: border-box;
}
</style>

<script>
jQuery(document).ready(function($) {
    var partsList = [];
    var vehiclesData = <?php echo json_encode($vehicles); ?>;
    var partsData = <?php echo json_encode($parts); ?>;
    
    // ========================================================================
    // ПОИСК АВТО ИЗ БД
    // ========================================================================
    $('#vehicle-search').on('input', function() {
        var query = $(this).val().toLowerCase().trim();
        var $results = $('#vehicle-search-results');
        
        if (query.length < 2) {
            $results.removeClass('active').empty();
            return;
        }
        
        var matches = vehiclesData.filter(function(v) {
            return (v.make && v.make.toLowerCase().includes(query)) || 
                   (v.model && v.model.toLowerCase().includes(query)) ||
                   (v.vin && v.vin.toLowerCase().includes(query));
        }).slice(0, 10);
        
        if (matches.length === 0) {
            $results.html('<div class="search-item">Не найдено. Введите вручную.</div>').addClass('active');
            return;
        }
        
        var html = '';
        matches.forEach(function(v) {
            html += '<div class="search-item" data-id="' + v.id + '" data-make="' + (v.make || '') + '" data-model="' + (v.model || '') + '" data-year="' + (v.year || '') + '" data-engine="' + (v.engine || '') + '" data-vin="' + (v.vin || '') + '">';
            html += '<strong>' + v.make + ' ' + v.model + '</strong>';
            html += '<small>' + (v.year || '') + ' | ' + (v.engine || '—') + (v.vin ? ' | VIN: ' + v.vin : '') + '</small>';
            html += '</div>';
        });
        
        $results.html(html).addClass('active');
    });
    
    $(document).on('click', '.search-item[data-id]', function() {
        $('#vehicle-id').val($(this).data('id'));
        $('#car-make').val($(this).data('make'));
        $('#car-model').val($(this).data('model'));
        $('#car-year').val($(this).data('year'));
        $('#car-engine').val($(this).data('engine'));
        $('#vin-input').val($(this).data('vin'));
        $('#vehicle-search-results').removeClass('active');
        $('#vehicle-search').val('');
    });
    
    $('#car-make, #car-model, #car-year').on('input', function() {
        $('#vehicle-id').val(0);
    });
    
    // ========================================================================
    // ПОИСК ЗАПЧАСТЕЙ ИЗ БД
    // ========================================================================
    $('#part-search').on('input', function() {
        var query = $(this).val().toLowerCase().trim();
        var $results = $('#part-search-results');
        
        if (query.length < 2) {
            $results.removeClass('active').empty();
            return;
        }
        
        var matches = partsData.filter(function(p) {
            return (p.name && p.name.toLowerCase().includes(query)) || 
                   (p.sku && p.sku.toLowerCase().includes(query));
        }).slice(0, 15);
        
        if (matches.length === 0) {
            $results.html('<div class="search-item">Не найдено</div>').addClass('active');
            return;
        }
        
        var html = '';
        matches.forEach(function(p) {
            var markup = parseFloat(p.markup_percent) || 0;
            var priceWithMarkup = parseFloat(p.price) * (1 + markup / 100);
            
            html += '<div class="search-item" data-id="' + p.id + '" data-name="' + p.name + '" data-sku="' + (p.sku || '') + '" data-price="' + priceWithMarkup.toFixed(2) + '">';
            html += '<strong>' + p.name + '</strong>';
            html += '<small>Арт: ' + (p.sku || '—') + ' | Наценка: ' + markup + '%</small>';
            html += '<span class="price">' + priceWithMarkup.toLocaleString('ru-RU', {maximumFractionDigits: 0}) + ' ₽</span>';
            html += '</div>';
        });
        
        $results.html(html).addClass('active');
    });
    
    $(document).on('click', '#part-search-results .search-item[data-id]', function() {
        var part = {
            id: $(this).data('id'),
            name: $(this).data('name'),
            sku: $(this).data('sku'),
            price: parseFloat($(this).data('price')),
            qty: 1
        };
        
        var exists = partsList.find(function(p) { return p.id === part.id; });
        if (exists) {
            exists.qty++;
        } else {
            partsList.push(part);
        }
        
        renderParts();
        $('#part-search-results').removeClass('active');
        $('#part-search').val('');
    });
    
    // ========================================================================
    // РЕНДЕР ЗАПЧАСТЕЙ
    // ========================================================================
    function renderParts() {
        var html = '';
        var total = 0;
        
        partsList.forEach(function(part, index) {
            var sum = part.price * part.qty;
            total += sum;
            
            html += '<tr data-index="' + index + '">';
            html += '<td>' + part.name + '</td>';
            html += '<td>' + (part.sku || '—') + '</td>';
            html += '<td>' + part.price.toLocaleString('ru-RU', {maximumFractionDigits: 0}) + ' ₽</td>';
            html += '<td><input type="number" class="part-qty" data-index="' + index + '" value="' + part.qty + '" min="1" style="width:60px;"></td>';
            html += '<td>' + sum.toLocaleString('ru-RU', {maximumFractionDigits: 0}) + ' ₽</td>';
            html += '<td><button type="button" class="button btn-remove-part" data-index="' + index + '">✕</button></td>';
            html += '</tr>';
        });
        
        $('#parts-list').html(html);
        $('#parts-total').text(total.toLocaleString('ru-RU', {maximumFractionDigits: 0}) + ' ₽');
        $('#total-parts').val(total.toLocaleString('ru-RU', {maximumFractionDigits: 0}) + ' ₽');
        calculateTotal();
    }
    
    $(document).on('change', '.part-qty', function() {
        var index = $(this).data('index');
        partsList[index].qty = parseInt($(this).val()) || 1;
        renderParts();
    });
    
    $(document).on('click', '.btn-remove-part', function() {
        var index = $(this).data('index');
        partsList.splice(index, 1);
        renderParts();
    });
    
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#vehicle-search, #vehicle-search-results').length) {
            $('#vehicle-search-results').removeClass('active');
        }
        if (!$(e.target).closest('#part-search, #part-search-results').length) {
            $('#part-search-results').removeClass('active');
        }
    });
    
    // ========================================================================
    // КАЛЬКУЛЯТОР
    // ========================================================================
    $('#calculation-type').on('change', function() {
        if ($(this).val() === 'norm') {
            $('#norm-calc-fields').show();
            $('#manual-calc-fields').hide();
            calculateWorkCost();
        } else {
            $('#norm-calc-fields').hide();
            $('#manual-calc-fields').show();
        }
    });
    
    $('#standard-hours, #hourly-rate').on('input', calculateWorkCost);
    $('#work-cost-manual').on('input', calculateTotal);
    
    function calculateWorkCost() {
        var hours = parseFloat($('#standard-hours').val()) || 0;
        var rate = parseFloat($('#hourly-rate').val()) || 0;
        var cost = hours * rate;
        $('#work-cost-auto').val(cost.toLocaleString('ru-RU') + ' ₽');
        calculateTotal();
    }
    
    function calculateTotal() {
        var workCost = 0;
        if ($('#calculation-type').val() === 'norm') {
            var hours = parseFloat($('#standard-hours').val()) || 0;
            var rate = parseFloat($('#hourly-rate').val()) || 0;
            workCost = hours * rate;
        } else {
            workCost = parseFloat($('#work-cost-manual').val()) || 0;
        }
        
        var partsTotal = partsList.reduce(function(sum, p) { return sum + (p.price * p.qty); }, 0);
        var total = workCost + partsTotal;
        
        $('#total-work').val(workCost.toLocaleString('ru-RU') + ' ₽');
        $('#total-amount').val(total);
    }
    
    // ========================================================================
    // AI РАСШИФРОВКА VIN
    // ========================================================================
    $('#btn-decode-vin').on('click', function() {
        var vin = $('#vin-input').val().trim().toUpperCase();
        if (vin.length !== 17) {
            showNotice('VIN должен содержать 17 символов', 'error');
            return;
        }
        
        var btn = $(this);
        btn.prop('disabled', true).text('⏳ Расшифровка...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'akpp_decode_vin_ai',
                vin: vin,
                nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
            },
            dataType: 'json',
            success: function(res) {
                if (res.success && res.data.data) {
                    var d = res.data.data;
                    $('#car-make').val(d.make || '');
                    $('#car-model').val(d.model || '');
                    $('#car-year').val(d.year || '');
                    $('#car-engine').val(d.engine || '');
                    if (d.transmission_code) {
                        $('#transmission-code').val(d.transmission_code);
                    }
                    showNotice('✅ VIN расшифрован: ' + (d.make || '') + ' ' + (d.model || ''), 'success');
                } else {
                    showNotice(res.data.message || '❌ Ошибка расшифровки', 'error');
                }
                btn.prop('disabled', false).text('🤖 AI Расшифровать');
            },
            error: function() {
                showNotice('❌ Ошибка соединения', 'error');
                btn.prop('disabled', false).text('🤖 AI Расшифровать');
            }
        });
    });
    
    // ========================================================================
    // ДОГОВОР-ОФЕРТА
    // ========================================================================
    $('#toggle-agreement').on('click', function() {
        $('#agreement-full-text').slideToggle();
        var text = $('#agreement-full-text').is(':visible') 
            ? '📖 Скрыть текст договора-оферты' 
            : '📖 Показать текст договора-оферты';
        $(this).text(text);
    });
    
    $('#show-agreement-inline').on('click', function(e) {
        e.preventDefault();
        $('#agreement-full-text').slideDown();
        $('#toggle-agreement').text('📖 Скрыть текст договора-оферты');
        $('html, body').animate({
            scrollTop: $('#agreement-full-text').offset().top - 100
        }, 500);
    });
    
    $('#print-agreement').on('click', function() {
        var clientName = $('#client-name').val() || '[ФИО клиента]';
        var clientPhone = $('#client-phone').val() || '[Телефон]';
        var carInfo = ($('#car-make').val() || '') + ' ' + ($('#car-model').val() || '');
        var totalAmount = $('#total-amount').val() || '0';
        var today = new Date().toLocaleDateString('ru-RU');
        
        var printContent = '<html><head><title>Договор-оферта от ' + today + '</title>' +
            '<style>body{font-family:Times New Roman,serif;font-size:12pt;line-height:1.5;padding:20px;}' +
            'h1{text-align:center;font-size:14pt;}' +
            '.header{text-align:right;margin-bottom:20px;}' +
            '.signature{margin-top:40px;display:flex;justify-content:space-between;}' +
            '.signature div{width:45%;}' +
            '.client-info{background:#f0f0f0;padding:10px;margin:10px 0;}</style></head><body>' +
            '<div class="header"><p>Заказ-наряд №[ID] от ' + today + '</p></div>' +
            '<h1>ДОГОВОР-ОФЕРТА НА РЕМОНТ АКПП</h1>' +
            '<div class="client-info"><strong>Заказчик:</strong> ' + clientName + '<br>' +
            '<strong>Телефон:</strong> ' + clientPhone + '<br>' +
            '<strong>Автомобиль:</strong> ' + carInfo + '<br>' +
            '<strong>Сумма работ:</strong> ' + totalAmount + ' ₽</div>' +
            '<p>Заказчик подтверждает согласие с условиями договора-оферты, размещённой на сайте Исполнителя ' +
            'и на Авито (объявление № 7991698408).</p>' +
            '<div class="signature"><div><p>_____________________ / ' + clientName + ' /</p><p>Заказчик</p></div>' +
            '<div><p>_____________________ / Представитель /</p><p>Исполнитель</p></div></div>' +
            '</body></html>';
        
        var printWindow = window.open('', '_blank');
        printWindow.document.write(printContent);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(function() { printWindow.print(); }, 500);
    });
    
    // ========================================================================
    // ОТПРАВКА ФОРМЫ
    // ========================================================================
    $('#akpp-deal-form').on('submit', function(e) {
        e.preventDefault();
        
        if (!$('#agreement-accepted').is(':checked')) {
            $('#agreement-warning').show();
            $('html, body').animate({
                scrollTop: $('.agreement-section').offset().top - 100
            }, 500);
            return false;
        }
        $('#agreement-warning').hide();
        
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('⏳ Сохранение...');
        
        // Сначала сохраняем согласие с офертой
        var agreementData = {
            action: 'akpp_save_agreement',
            client_name: $('#client-name').val(),
            client_phone: $('#client-phone').val(),
            source: 'crm_deal',
            nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
        };
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: agreementData,
            dataType: 'json',
            success: function(res) {
                if (!res.success) {
                    showNotice(res.data.message || '❌ Ошибка сохранения согласия', 'error');
                    btn.prop('disabled', false).text('💾 Сохранить сделку');
                    return;
                }
                
                // Теперь сохраняем сделку
                var formData = $('#akpp-deal-form').serializeArray();
                formData.push({name: 'action', value: 'akpp_save_deal'});
                formData.push({name: 'agreement_id', value: res.data.agreement_id});
                formData.push({name: 'agreement_accepted', value: '1'});
                
                // Добавляем запчасти
                partsList.forEach(function(part) {
                    formData.push({
                        name: 'parts[]',
                        value: JSON.stringify({
                            id: part.id,
                            name: part.name,
                            sku: part.sku,
                            price: part.price,
                            quantity: part.qty,
                            category: 'parts'
                        })
                    });
                });
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(res2) {
                        if (res2.success) {
                            showNotice('✅ ' + res2.data.message, 'success');
                            setTimeout(function() {
                                window.location.href = '<?php echo admin_url("admin.php?page=akpp-crm-deals"); ?>';
                            }, 1500);
                        } else {
                            showNotice(res2.data.message || '❌ Ошибка', 'error');
                            btn.prop('disabled', false).text('💾 Сохранить сделку');
                        }
                    },
                    error: function() {
                        showNotice('❌ Ошибка соединения', 'error');
                        btn.prop('disabled', false).text('💾 Сохранить сделку');
                    }
                });
            },
            error: function() {
                showNotice('❌ Ошибка соединения', 'error');
                btn.prop('disabled', false).text('💾 Сохранить сделку');
            }
        });
    });
    
    function showNotice(message, type) {
        var bgColor = type === 'success' ? '#00ff88' : '#fc8181';
        var textColor = type === 'success' ? '#0a0f1c' : '#fff';
        var $notice = $('<div style="position:fixed;top:20px;right:20px;background:' + bgColor + ';color:' + textColor + ';padding:16px 24px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:99999;font-weight:600;">' + message + '</div>');
        $('body').append($notice);
        setTimeout(function() { $notice.fadeOut(300, function() { $(this).remove(); }); }, 3000);
    }
});
</script>