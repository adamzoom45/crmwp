<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

// --- выбор сотрудника и месяца ---
$employees = $wpdb->get_results("SELECT id,name,payment_type,salary,schedule_type,schedule_anchor,schedule_mask,absence_penalty,percent,is_active FROM {$wpdb->prefix}akpp_employees ORDER BY name");
$emp_id = intval($_GET['emp'] ?? ($employees[0]->id ?? 0));
$emp = null;
foreach ($employees as $e) if ((int)$e->id === $emp_id) { $emp = $e; break; }
if (!$emp && $employees) { $emp = $employees[0]; $emp_id = (int)$emp->id; }

$month = preg_replace('/[^0-9-]/', '', $_GET['month'] ?? current_time('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = current_time('Y-m');
$y = (int)substr($month, 0, 4); $m = (int)substr($month, 5, 2);
$days_in = (int)date('t', mktime(0, 0, 0, $m, 1, $y));
$first_dow = (int)date('N', mktime(0, 0, 0, $m, 1, $y));
$prev = date('Y-m', mktime(0, 0, 0, $m - 1, 1, $y));
$next = date('Y-m', mktime(0, 0, 0, $m + 1, 1, $y));

// --- записи табеля за месяц ---
$recs = [];
if ($emp_id) {
    foreach ($wpdb->get_results($wpdb->prepare(
        "SELECT date,status,hours,comment FROM {$wpdb->prefix}akpp_attendance WHERE employee_id=%d AND date BETWEEN %s AND %s",
        $emp_id, "$month-01", "$month-$days_in")) as $r) $recs[$r->date] = $r;
}

// --- плановый рабочий день (та же логика, что в mu-plugin) ---
$is_work = function($ts) use ($emp) {
    if (!$emp) return false;
    $type = $emp->schedule_type; $dow = (int)date('N', $ts);
    if ($type === '5/2') return $dow <= 5;
    if ($type === 'custom') { $mask = str_pad($emp->schedule_mask ?: '1111100', 7, '0'); return ($mask[$dow - 1] ?? '0') === '1'; }
    if ($type === '2/2' || $type === '3/3') {
        $anchor = !empty($emp->schedule_anchor) ? strtotime($emp->schedule_anchor) : strtotime('2024-01-01');
        $diff = (int)floor(($ts - $anchor) / 86400); $cycle = $type === '2/2' ? 4 : 6;
        return ((($diff % $cycle) + $cycle) % $cycle) < ($type === '2/2' ? 2 : 3);
    }
    return false;
};

$meta = [
    'work'     => ['✅', '#00ff88', 'Отработал'],
    'sick'     => ['🤒', '#63b3ed', 'Больничный'],
    'absence'  => ['❌', '#fc8181', 'Прогул'],
    'vacation' => ['🏖', '#b794f4', 'Отпуск'],
    'dayoff'   => ['😴', '#f6ad55', 'Отгул'],
    'holiday'  => ['🎉', '#ed8936', 'Праздник'],
    'weekend'  => ['⬜', '#4a5568', 'Выходной'],
];
$pt_labels = ['percent' => '💰 Сдельно (% со сделок)', 'salary' => '📅 Оклад (по табелю)', 'mixed' => '🔀 Оклад + %'];
$sch_labels = ['5/2' => '5/2', '2/2' => '2/2', '3/3' => '3/3', 'custom' => 'Свой (маска)', 'individual' => 'Индивидуальный'];

// статистика месяца
$stat = ['work' => 0, 'sick' => 0, 'absence' => 0, 'vacation' => 0, 'dayoff' => 0, 'plan_work' => 0];
?>
<div class="wrap akpp-crm-wrap">
    <h1 style="color:#00ff88;border-left:4px solid #00ff88;padding-left:15px;">📅 Табель и график работы</h1>

    <!-- выбор сотрудника -->
    <div style="margin:16px 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <span style="color:#a0aec0;">Сотрудник:</span>
        <?php foreach ($employees as $e): ?>
            <a href="?page=akpp-crm-attendance&emp=<?php echo $e->id; ?>&month=<?php echo $month; ?>" class="button <?php echo (int)$e->id===$emp_id?'button-primary':''; ?>"
               style="<?php echo (int)$e->id===$emp_id?'background:#00ff88;border-color:#00ff88;color:#0a0f1c;':''; ?>"><?php echo esc_html($e->name); ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($emp): ?>
    <!-- навигация по месяцам -->
    <div style="margin:10px 0 18px;display:flex;gap:10px;align-items:center;">
        <a href="?page=akpp-crm-attendance&emp=<?php echo $emp_id; ?>&month=<?php echo $prev; ?>" class="button">◀</a>
        <strong style="color:#e2e8f0;font-size:16px;min-width:140px;text-align:center;"><?php echo date_i18n('F Y', mktime(0,0,0,$m,1,$y)); ?></strong>
        <a href="?page=akpp-crm-attendance&emp=<?php echo $emp_id; ?>&month=<?php echo $next; ?>" class="button">▶</a>
        <a href="?page=akpp-crm-attendance&emp=<?php echo $emp_id; ?>&month=<?php echo current_time('Y-m'); ?>" class="button">Сегодня</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start;">
    <!-- КАЛЕНДАРЬ -->
    <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
            <h2 style="color:#00ff88;margin:0;">Календарь выхода · <?php echo esc_html($emp->name); ?></h2>
            <button type="button" id="akpp-fill-month" class="button button-primary" style="background:#00ff88;border-color:#00ff88;color:#0a0f1c;font-weight:600;">⚙️ Заполнить месяц по графику</button>
        </div>
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px;">
            <?php foreach (['Пн','Вт','Ср','Чт','Пт','Сб','Вс'] as $h): ?>
                <div style="text-align:center;color:#718096;font-size:12px;font-weight:600;padding:4px;"><?php echo $h; ?></div>
            <?php endforeach; ?>
            <?php for ($i = 1; $i < $first_dow; $i++): ?><div></div><?php endfor; ?>
            <?php for ($d = 1; $d <= $days_in; $d++):
                $date = sprintf('%s-%02d', $month, $d);
                $ts = strtotime($date);
                $rec = $recs[$date] ?? null;
                if ($rec) { $st = $rec->status; } else { $st = $is_work($ts) ? 'plan_work' : 'plan_off'; }
                if ($st === 'plan_work') { $stat['plan_work']++; $color = 'transparent'; $border = '#00ff88'; $emoji = '·'; }
                elseif ($st === 'plan_off') { $color = '#2d3748'; $border = '#2d3748'; $emoji = ''; }
                else { if (isset($stat[$st])) $stat[$st]++; $color = $meta[$st][1].'33'; $border = $meta[$st][1]; $emoji = $meta[$st][0]; }
            ?>
                <div class="att-day" data-date="<?php echo $date; ?>" data-status="<?php echo $rec ? esc_attr($rec->status) : ''; ?>"
                     title="<?php echo $d.' '.($meta[$st][2] ?? ($st==='plan_work'?'Плановый рабочий':($st==='plan_off'?'Плановый выходной':''))); ?>"
                     style="border:2px solid <?php echo $border; ?>;background:<?php echo $color; ?>;border-radius:8px;padding:8px 4px;text-align:center;cursor:pointer;min-height:46px;">
                    <div style="color:#e2e8f0;font-weight:600;font-size:13px;"><?php echo $d; ?></div>
                    <div style="font-size:16px;line-height:1;"><?php echo $emoji; ?></div>
                </div>
            <?php endfor; ?>
        </div>
        <div style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap;font-size:12px;color:#a0aec0;">
            <?php foreach ($meta as $k=>$v): ?><span style="display:flex;align-items:center;gap:4px;"><span style="width:12px;height:12px;border-radius:3px;background:<?php echo $v[1]; ?>;display:inline-block;"></span><?php echo $v[0].' '.$v[2]; ?></span><?php endforeach; ?>
            <span style="display:flex;align-items:center;gap:4px;"><span style="width:12px;height:12px;border:2px solid #00ff88;border-radius:3px;display:inline-block;"></span>· плановый рабочий</span>
        </div>
        <div style="margin-top:14px;background:#2d3748;border-radius:8px;padding:12px;font-size:13px;color:#e2e8f0;">
            📊 За месяц: отработано <strong style="color:#00ff88;"><?php echo $stat['work']; ?></strong> ·
            больничных <strong style="color:#63b3ed;"><?php echo $stat['sick']; ?></strong> ·
            прогулов <strong style="color:#fc8181;"><?php echo $stat['absence']; ?></strong> ·
            отпуск <strong style="color:#b794f4;"><?php echo $stat['vacation']; ?></strong> ·
            отгулов <strong style="color:#f6ad55;"><?php echo $stat['dayoff']; ?></strong>
            <?php if ($emp->schedule_type !== 'individual'): ?> · незаполнено плановых <strong style="color:#718096;"><?php echo $stat['plan_work']; ?></strong><?php endif; ?>
        </div>
    </div>

    <!-- ПРАВАЯ КОЛОНКА: форма дня + график -->
    <div>
        <!-- форма отметки дня -->
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:18px;margin-bottom:16px;">
            <h3 style="color:#00ff88;margin:0 0 12px;">✏️ Отметить день</h3>
            <form id="akpp-day-form" style="display:flex;flex-direction:column;gap:10px;">
                <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
                <input type="hidden" name="employee_id" value="<?php echo $emp_id; ?>">
                <div><label style="color:#a0aec0;font-size:12px;">Дата</label>
                    <input type="date" name="date" id="akpp-day-date" required style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;"></div>
                <div><label style="color:#a0aec0;font-size:12px;">Статус</label>
                    <select name="status" id="akpp-day-status" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;">
                        <?php foreach ($meta as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v[0].' '.$v[2]; ?></option><?php endforeach; ?>
                    </select></div>
                <div><label style="color:#a0aec0;font-size:12px;">Часы (необяз.)</label>
                    <input type="number" step="0.5" name="hours" value="0" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;"></div>
                <div><label style="color:#a0aec0;font-size:12px;">Комментарий</label>
                    <input type="text" name="comment" placeholder="Напр. больничный лист №..." style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;"></div>
                <button type="submit" class="button button-primary" style="background:#00ff88;border-color:#00ff88;color:#0a0f1c;font-weight:600;">💾 Сохранить день</button>
            </form>
        </div>
        <!-- форма графика -->
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:18px;">
            <h3 style="color:#00ff88;margin:0 0 12px;">⚙️ График и оплата</h3>
            <form id="akpp-schedule-form" style="display:flex;flex-direction:column;gap:10px;">
                <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
                <input type="hidden" name="employee_id" value="<?php echo $emp_id; ?>">
                <div><label style="color:#a0aec0;font-size:12px;">Тип оплаты</label>
                    <select name="payment_type" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;">
                        <?php foreach ($pt_labels as $k=>$v): ?><option value="<?php echo $k; ?>" <?php selected($emp->payment_type,$k); ?>><?php echo $v; ?></option><?php endforeach; ?>
                    </select></div>
                <div><label style="color:#a0aec0;font-size:12px;">Оклад ₽ (для «Оклад» / «Оклад + %»)</label>
                    <input type="number" step="0.01" name="salary" value="<?php echo esc_attr($emp->salary); ?>" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;"></div>
                <div><label style="color:#a0aec0;font-size:12px;">График</label>
                    <select name="schedule_type" id="akpp-sch-type" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;">
                        <?php foreach ($sch_labels as $k=>$v): ?><option value="<?php echo $k; ?>" <?php selected($emp->schedule_type,$k); ?>><?php echo $v; ?></option><?php endforeach; ?>
                    </select></div>
                <div id="akpp-sch-anchor-wrap"><label style="color:#a0aec0;font-size:12px;">Опорная дата (для 2/2, 3/3)</label>
                    <input type="date" name="schedule_anchor" value="<?php echo esc_attr($emp->schedule_anchor); ?>" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;"></div>
                <div id="akpp-sch-mask-wrap"><label style="color:#a0aec0;font-size:12px;">Маска дней (пн…вс, 1=раб / 0=вых, напр. 1111100)</label>
                    <input type="text" name="schedule_mask" maxlength="7" value="<?php echo esc_attr($emp->schedule_mask ?: '1111100'); ?>" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;"></div>
                <div><label style="color:#a0aec0;font-size:12px;">Штраф за прогул ₽/день (0 = без штрафа)</label>
                    <input type="number" step="0.01" name="absence_penalty" value="<?php echo esc_attr($emp->absence_penalty); ?>" style="width:100%;padding:8px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#fff;"></div>
                <button type="submit" class="button button-primary" style="background:#00ff88;border-color:#00ff88;color:#0a0f1c;font-weight:600;">💾 Сохранить график</button>
            </form>
        </div>
    </div>
    </div>
    <?php else: ?>
        <p style="color:#a0aec0;">Нет сотрудников. Создайте сотрудника в разделе «👥 Сотрудники».</p>
    <?php endif; ?>
</div>
<script>
jQuery(document).ready(function($){
    var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    function toggleSchFields(){
        var t = $('#akpp-sch-type').val();
        $('#akpp-sch-anchor-wrap').toggle(t==='2/2'||t==='3/3');
        $('#akpp-sch-mask-wrap').toggle(t==='custom');
    }
    toggleSchFields(); $('#akpp-sch-type').on('change', toggleSchFields);

    // клик по дню -> подставить в форму
    $(document).on('click', '.att-day', function(){
        var d = $(this).data('date'), s = $(this).data('status');
        $('#akpp-day-date').val(d);
        if (s) $('#akpp-day-status').val(s);
    });

    $('#akpp-day-form').on('submit', function(e){
        e.preventDefault();
        var $b = $(this).find('button[type=submit]').prop('disabled',true).text('Сохранение...');
        $.post(ajaxUrl, $(this).serialize()+'&action=akpp_save_attendance', function(r){
            if (r.success) location.reload(); else { alert(r.data.message||'Ошибка'); $b.prop('disabled',false).text('💾 Сохранить день'); }
        }).fail(function(){ alert('Ошибка соединения'); $b.prop('disabled',false).text('💾 Сохранить день'); });
    });
    $('#akpp-schedule-form').on('submit', function(e){
        e.preventDefault();
        var $b = $(this).find('button[type=submit]').prop('disabled',true).text('Сохранение...');
        $.post(ajaxUrl, $(this).serialize()+'&action=akpp_save_schedule', function(r){
            if (r.success) location.reload(); else { alert(r.data.message||'Ошибка'); $b.prop('disabled',false).text('💾 Сохранить график'); }
        }).fail(function(){ alert('Ошибка соединения'); $b.prop('disabled',false).text('💾 Сохранить график'); });
    });
    $('#akpp-fill-month').on('click', function(){
        if (!confirm('Заполнить месяц плановыми рабочими/выходными по графику? Больничные, прогулы, отпуска, отгулы и праздники НЕ затираются.')) return;
        var $b = $(this).prop('disabled',true).text('Заполнение...');
        $.post(ajaxUrl, { action:'akpp_fill_month', employee_id:<?php echo $emp_id; ?>, month:'<?php echo $month; ?>', nonce:'<?php echo wp_create_nonce('akpp45_nonce'); ?>' }, function(r){
            alert(r.data.message||'Готово'); location.reload();
        }).fail(function(){ alert('Ошибка соединения'); $b.prop('disabled',false).text('⚙️ Заполнить месяц по графику'); });
    });
});
</script>
