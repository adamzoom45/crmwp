<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

$period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
$today = current_time('Y-m-d');
switch ($period) {
    case 'quarter': $from = date('Y-m-d', strtotime('-3 months')); break;
    case 'year':    $from = date('Y-m-d', strtotime('-1 year')); break;
    case 'all':     $from = '2000-01-01'; break;
    default:        $from = date('Y-m-01');
}
$to = $today;
$from_month = date('Y-m', strtotime($from));
$to_month   = date('Y-m', strtotime($to));
$cur_month  = current_time('Y-m');

$percent_expr = "COALESCE(NULLIF(d.employee_percent,0), e.percent, 0)";

// План (все кроме отменённых)
$totals = $wpdb->get_row($wpdb->prepare(
    "SELECT COALESCE(SUM(work_cost),0) work_total, COALESCE(SUM(parts_total),0) parts_total,
            COALESCE(SUM(payment_amount),0) paid_total, COUNT(*) deals_count
     FROM {$wpdb->prefix}akpp_deals WHERE status != 'cancelled' AND created_date BETWEEN %s AND %s", $from, $to), ARRAY_A);
// Закрытые
$closed = $wpdb->get_row($wpdb->prepare(
    "SELECT COALESCE(SUM(work_cost),0) work_closed, COUNT(*) closed_count
     FROM {$wpdb->prefix}akpp_deals WHERE status = 'completed' AND created_date BETWEEN %s AND %s", $from, $to), ARRAY_A);
// По сделкам на сотрудника
$deal_by_emp = [];
foreach ($wpdb->get_results($wpdb->prepare(
    "SELECT d.employee_id, COUNT(*) deals,
            SUM(CASE WHEN d.status='completed' THEN 1 ELSE 0 END) closed_cnt,
            COALESCE(SUM(d.work_cost),0) work,
            COALESCE(SUM(CASE WHEN d.status='completed' THEN d.work_cost * $percent_expr / 100 ELSE 0 END),0) salary_deals,
            COALESCE(SUM(CASE WHEN d.status NOT IN ('completed','cancelled') THEN d.work_cost * $percent_expr / 100 ELSE 0 END),0) pending
     FROM {$wpdb->prefix}akpp_deals d LEFT JOIN {$wpdb->prefix}akpp_employees e ON d.employee_id=e.id
     WHERE d.status != 'cancelled' AND d.employee_id IS NOT NULL AND d.created_date BETWEEN %s AND %s
     GROUP BY d.employee_id", $from, $to), ARRAY_A) as $r) $deal_by_emp[$r['employee_id']] = $r;
// Табель: по сотруднику / месяцу / статусу
$att = [];
foreach ($wpdb->get_results($wpdb->prepare(
    "SELECT employee_id, DATE_FORMAT(date,'%%Y-%%m') ym, status, COUNT(*) c
     FROM {$wpdb->prefix}akpp_attendance WHERE date BETWEEN %s AND %s AND status IN ('work','absence')
     GROUP BY employee_id, ym, status", $from, $to), ARRAY_A) as $r) $att[$r['employee_id']][$r['ym']][$r['status']] = (int)$r['c'];
// Ручные начисления
$manual_rows = $wpdb->get_results($wpdb->prepare(
    "SELECT employee_id, type, comment, amount, period_month, id FROM {$wpdb->prefix}akpp_salary_manual
     WHERE period_month BETWEEN %s AND %s ORDER BY created_at DESC", $from_month, $to_month), ARRAY_A);
$manual_map = []; $manual_total = 0;
foreach ($manual_rows as $m) { $manual_map[$m['employee_id']] = ($manual_map[$m['employee_id']] ?? 0) + floatval($m['amount']); $manual_total += floatval($m['amount']); }

// Список месяцев периода
$months = []; { $t = strtotime($from_month.'-01'); $end = strtotime($to_month.'-01'); while ($t <= $end) { $months[] = date('Y-m', $t); $t = strtotime('+1 month', $t); } }

$employees = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}akpp_employees ORDER BY name", ARRAY_A);
$type_labels = ['bonus'=>'🎁 Бонус','advance'=>'💵 Аванс','salary'=>'📅 Оклад','adjustment'=>'✏️ Корректировка','sick'=>'🤒 Больничный','vacation'=>'🏖 Отпускные'];
$pt_labels = ['percent'=>'💰 Сдельно','salary'=>'📅 Оклад','mixed'=>'🔀 Оклад+%'];

$rows = [];
foreach ($employees as $emp) {
    $eid = (int)$emp['id'];
    $has_deal = isset($deal_by_emp[$eid]); $has_att = isset($att[$eid]); $has_manual = isset($manual_map[$eid]);
    if (!$emp['is_active'] && !$has_deal && !$has_att && !$has_manual) continue;

    // ЗП по табелю (по месяцам) + норма/отработано/прогулы
    $norm_total = 0; $work_total = 0; $abs_total = 0; $salary_tab = 0.0;
    foreach ($months as $ym) {
        $y = (int)substr($ym,0,4); $mo = (int)substr($ym,5,2); $dim = (int)date('t', mktime(0,0,0,$mo,1,$y));
        $norm_m = 0;
        for ($d = 1; $d <= $dim; $d++) { if (function_exists('akpp_is_workday') && akpp_is_workday($emp, mktime(0,0,0,$mo,$d,$y))) $norm_m++; }
        $norm_total += $norm_m;
        $work_m = (int)($att[$eid][$ym]['work'] ?? 0); $work_total += $work_m;
        $abs_total += (int)($att[$eid][$ym]['absence'] ?? 0);
        $rate = $norm_m > 0 ? (float)$emp['salary'] / $norm_m : 0;
        $salary_tab += $rate * $work_m;
    }
    $de = $deal_by_emp[$eid] ?? null;
    $salary_deals = $de ? floatval($de['salary_deals']) : 0.0;
    $pending = $de ? floatval($de['pending']) : 0.0;
    $deals_cnt = $de ? (int)$de['deals'] : 0; $closed_cnt = $de ? (int)$de['closed_cnt'] : 0; $work_deals = $de ? floatval($de['work']) : 0.0;
    $manual = $manual_map[$eid] ?? 0.0;
    $pt = $emp['payment_type'] ?? 'percent';
    $penalty_total = $abs_total * floatval($emp['absence_penalty']);
    // base по типу оплаты
    $base = ($pt === 'salary') ? $salary_tab : (($pt === 'mixed') ? ($salary_deals + $salary_tab) : $salary_deals);
    $total = $base - $penalty_total + $manual;

    $rows[$eid] = [
        'name'=>$emp['name'] ?: 'Сотрудник #'.$eid, 'pt'=>$pt, 'percent'=>floatval($emp['percent']),
        'deals'=>$deals_cnt, 'closed'=>$closed_cnt, 'work_deals'=>$work_deals,
        'salary_deals'=>$salary_deals, 'salary_tab'=>$salary_tab, 'pending'=>$pending,
        'norm'=>$norm_total, 'work_att'=>$work_total, 'abs'=>$abs_total, 'penalty'=>$penalty_total,
        'manual'=>$manual, 'total'=>$total,
    ];
}

// Суммы для карточек (с учётом типа оплаты)
$sum_deals = 0; $sum_tab = 0; $sum_penalty = 0; $sum_pending = 0; $sum_total = 0;
foreach ($rows as $r) {
    if ($r['pt'] !== 'salary') $sum_deals += $r['salary_deals'];
    if ($r['pt'] !== 'percent') $sum_tab += $r['salary_tab'];
    $sum_penalty += $r['penalty']; $sum_total += $r['total'];
    if ($r['pt'] !== 'salary') $sum_pending += $r['pending'];
}
$work_total = floatval($totals['work_total']); $work_closed = floatval($closed['work_closed']);
$parts_total = floatval($totals['parts_total']); $paid_total = floatval($totals['paid_total']);
$profit = $work_closed - $sum_deals - $sum_tab;
$periods = ['month'=>'Этот месяц','quarter'=>'Квартал','year'=>'Год','all'=>'Всё время'];
// === Доходы магазина (агрегация из akpp_shop_orders + себестоимость) ===
$shop_paid       = (float) $wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$wpdb->prefix}akpp_shop_orders WHERE payment_status='paid'");
$shop_paid_count = (int)   $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_shop_orders WHERE payment_status='paid'");
$shop_all        = (float) $wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$wpdb->prefix}akpp_shop_orders");
$shop_cost_paid  = (float) $wpdb->get_var("SELECT COALESCE(SUM(oi.purchase_price*oi.quantity),0) FROM {$wpdb->prefix}akpp_shop_order_items oi JOIN {$wpdb->prefix}akpp_shop_orders o ON oi.order_id=o.id WHERE o.payment_status='paid'");
$shop_profit     = $shop_paid - $shop_cost_paid;
$shop_margin     = $shop_paid > 0 ? round($shop_profit / $shop_paid * 100) : 0;
$shop_orders     = $wpdb->get_results("SELECT o.id, o.order_number, o.client_name, o.total, o.payment_status, o.status, o.created_at, (SELECT COALESCE(SUM(oi.purchase_price*oi.quantity),0) FROM {$wpdb->prefix}akpp_shop_order_items oi WHERE oi.order_id=o.id) AS cost FROM {$wpdb->prefix}akpp_shop_orders o ORDER BY o.created_at DESC LIMIT 15");
$shop_meta       = class_exists('AKPP_Shop') ? AKPP_Shop::order_statuses() : [];
$fmt = function($n){ return number_format($n,0,',',' '); };
?>
<div class="wrap akpp-crm-wrap">
    <h1 style="color:#00ff88;border-left:4px solid #00ff88;padding-left:15px;">💰 Финансы и ЗП</h1>
    <div style="margin:20px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <span style="color:#a0aec0;">Период:</span>
        <?php foreach ($periods as $key=>$label): ?>
            <a href="?page=akpp-crm-finance&period=<?php echo $key; ?>" class="button <?php echo $period===$key?'button-primary':''; ?>"
               style="<?php echo $period===$key?'background:#00ff88;border-color:#00ff88;color:#0a0f1c;':''; ?>"><?php echo $label; ?></a>
        <?php endforeach; ?>
        <span style="color:#718096;font-size:13px;margin-left:10px;">с <?php echo date_i18n('d.m.Y', strtotime($from)); ?> по <?php echo date_i18n('d.m.Y', strtotime($to)); ?></span>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:12px;margin-bottom:24px;">
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:16px;text-align:center;"><h3 style="font-size:20px;color:#00ff88;margin:0 0 6px;"><?php echo $fmt($work_total); ?> ₽</h3><p style="color:#a0aec0;margin:0;font-size:12px;">🔧 Выручка работ (план)</p></div>
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:16px;text-align:center;"><h3 style="font-size:20px;color:#63b3ed;margin:0 0 6px;"><?php echo $fmt($work_closed); ?> ₽</h3><p style="color:#a0aec0;margin:0;font-size:12px;">✅ Выручка закрытых</p></div>
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:16px;text-align:center;"><h3 style="font-size:20px;color:#b794f4;margin:0 0 6px;"><?php echo $fmt($parts_total); ?> ₽</h3><p style="color:#a0aec0;margin:0;font-size:12px;">📦 Запчасти</p></div>
            <div style="background:#1a1f2e;border:1px solid rgba(0,255,136,.35);border-radius:12px;padding:16px;text-align:center;"><h3 style="font-size:20px;color:#00ff88;margin:0 0 6px;"><?php echo $fmt($shop_profit); ?> ₽</h3><p style="color:#a0aec0;margin:0;font-size:12px;">🛒 Магазин · прибыль (маржа <?php echo (int)$shop_margin; ?>%)</p></div>
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:16px;text-align:center;"><h3 style="font-size:20px;color:#9ae6b4;margin:0 0 6px;"><?php echo $fmt($paid_total); ?> ₽</h3><p style="color:#a0aec0;margin:0;font-size:12px;">✅ Оплачено клиентами</p></div>
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:16px;text-align:center;"><h3 style="font-size:20px;color:#00ff88;margin:0 0 6px;"><?php echo $fmt($sum_deals); ?> ₽</h3><p style="color:#a0aec0;margin:0;font-size:12px;">💰 ЗП по сделкам</p></div>
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:16px;text-align:center;"><h3 style="font-size:20px;color:#f6ad55;margin:0 0 6px;"><?php echo $fmt($sum_tab); ?> ₽</h3><p style="color:#a0aec0;margin:0;font-size:12px;">📅 ЗП по табелю</p></div>
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:16px;text-align:center;"><h3 style="font-size:20px;color:#fc8181;margin:0 0 6px;"><?php echo $fmt($sum_penalty); ?> ₽</h3><p style="color:#a0aec0;margin:0;font-size:12px;">⛔ Штрафы за прогулы</p></div>
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:16px;text-align:center;"><h3 style="font-size:20px;color:#ed8936;margin:0 0 6px;"><?php echo $fmt($manual_total); ?> ₽</h3><p style="color:#a0aec0;margin:0;font-size:12px;">✋ Ручные выплаты</p></div>
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:16px;text-align:center;"><h3 style="font-size:20px;color:#00ff88;margin:0 0 6px;"><?php echo $fmt($sum_total); ?> ₽</h3><p style="color:#a0aec0;margin:0;font-size:12px;">💵 Итого к выплате</p></div>
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:16px;text-align:center;"><h3 style="font-size:20px;color:<?php echo $profit>=0?'#00ff88':'#fc8181'; ?>;margin:0 0 6px;"><?php echo $fmt($profit); ?> ₽</h3><p style="color:#a0aec0;margin:0;font-size:12px;">📈 Прибыль по закрытым</p></div>
        <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:16px;text-align:center;"><h3 style="font-size:20px;color:#e2e8f0;margin:0 0 6px;"><?php echo intval($totals['deals_count']); ?> / <?php echo intval($closed['closed_count']); ?></h3><p style="color:#a0aec0;margin:0;font-size:12px;">📋 Сделок / закрыто</p></div>
    </div>

    <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:24px;margin-bottom:24px;">
        <h2 style="color:#00ff88;margin:0 0 6px;">👥 Расчёт ЗП по сотрудникам</h2>
        <p style="color:#718096;font-size:12px;margin:0 0 18px;">Окладник: <code>ЗП = (оклад ÷ норма дней) × отработано</code> по каждому месяцу, минус штрафы за прогулы. Больничные/отпускные — отдельными ручными выплатами. Сдельщик: % с закрытых сделок.</p>
        <?php if (empty($rows)): ?>
            <p style="color:#a0aec0;text-align:center;padding:30px;">Нет данных за период</p>
        <?php else: ?>
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr style="background:#2d3748;">
                    <th style="color:#00ff88;padding:10px;text-align:left;">Сотрудник</th>
                    <th style="color:#00ff88;padding:10px;text-align:center;">Тип</th>
                    <th style="color:#00ff88;padding:10px;text-align:center;">Сделок/закр</th>
                    <th style="color:#00ff88;padding:10px;text-align:right;">ЗП по сделкам</th>
                    <th style="color:#f6ad55;padding:10px;text-align:right;">ЗП по табелю</th>
                    <th style="color:#fc8181;padding:10px;text-align:right;">Штрафы</th>
                    <th style="color:#ed8936;padding:10px;text-align:right;">Ручные</th>
                    <th style="color:#00ff88;padding:10px;text-align:right;">Итого</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr style="border-bottom:1px solid #2d3748;">
                        <td style="color:#e2e8f0;padding:10px;">
                            <strong><?php echo esc_html($r['name']); ?></strong>
                            <?php if ($r['pt'] !== 'percent'): ?>
                                <div style="font-size:11px;color:#718096;margin-top:3px;">📅 норма <?php echo $r['norm']; ?> · отработано <?php echo $r['work_att']; ?> · прогулов <?php echo $r['abs']; ?></div>
                            <?php elseif ($r['abs'] > 0): ?>
                                <div style="font-size:11px;color:#fc8181;margin-top:3px;">⛔ прогулов <?php echo $r['abs']; ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="color:#a0aec0;padding:10px;text-align:center;font-size:12px;"><?php echo $pt_labels[$r['pt']] ?? $r['pt']; ?></td>
                        <td style="color:#e2e8f0;padding:10px;text-align:center;"><?php echo $r['deals']; ?>/<?php echo $r['closed']; ?></td>
                        <td style="color:#9ae6b4;padding:10px;text-align:right;"><?php echo $r['pt']==='salary' ? '—' : $fmt($r['salary_deals']).' ₽'; ?></td>
                        <td style="color:#f6ad55;padding:10px;text-align:right;"><?php echo $r['pt']==='percent' ? '—' : $fmt($r['salary_tab']).' ₽'; ?></td>
                        <td style="color:#fc8181;padding:10px;text-align:right;"><?php echo $r['penalty']>0 ? '−'.$fmt($r['penalty']).' ₽' : '—'; ?></td>
                        <td style="color:#ed8936;padding:10px;text-align:right;"><?php echo $r['manual']>0 ? $fmt($r['manual']).' ₽' : '—'; ?></td>
                        <td style="color:#00ff88;padding:10px;text-align:right;font-weight:700;"><?php echo $fmt($r['total']); ?> ₽</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr style="background:#2d3748;">
                    <td colspan="3" style="color:#00ff88;padding:10px;font-weight:700;text-align:right;">Итого:</td>
                    <td style="color:#9ae6b4;padding:10px;text-align:right;font-weight:700;"><?php echo $fmt($sum_deals); ?> ₽</td>
                    <td style="color:#f6ad55;padding:10px;text-align:right;font-weight:700;"><?php echo $fmt($sum_tab); ?> ₽</td>
                    <td style="color:#fc8181;padding:10px;text-align:right;font-weight:700;"><?php echo $sum_penalty>0?'−'.$fmt($sum_penalty).' ₽':'—'; ?></td>
                    <td style="color:#ed8936;padding:10px;text-align:right;font-weight:700;"><?php echo $fmt($manual_total); ?> ₽</td>
                    <td style="color:#00ff88;padding:10px;text-align:right;font-weight:700;font-size:16px;"><?php echo $fmt($sum_total); ?> ₽</td>
                </tr></tfoot>
            </table>
        <?php endif; ?>
    </div>

    <!-- Ручные начисления -->
    <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:24px;">
        <h2 style="color:#00ff88;margin:0 0 18px;">✋ Ручные начисления (премия / аванс / оклад / больничный / отпускные / корректировка)</h2>
        <div class="akppfs">
                <style>
                .akppfs{border:2px solid rgba(0,255,136,.4);border-radius:16px;overflow:hidden;margin:0 0 24px;background:linear-gradient(180deg,rgba(0,255,136,.07),rgba(0,255,136,.01));box-shadow:0 20px 50px -30px rgba(0,255,136,.4);}
                .akppfs-head{background:linear-gradient(135deg,#00ff88,#00cc6a);color:#08120c;padding:13px 22px;font-weight:800;font-size:16px;letter-spacing:.3px;}
                .akppfs-body{padding:22px;}
                .akppfs-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:20px;}
                .akppfs-metric{background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:16px;text-align:center;}
                .akppfs-metric h3{margin:0 0 6px;font-size:19px;font-variant-numeric:tabular-nums;}
                .akppfs-metric p{margin:0;font-size:12px;color:#a0aec0;}
                .akppfs-metric.rev h3{color:#e2e8f0;} .akppfs-metric.paid h3{color:#00ff88;} .akppfs-metric.cost h3{color:#ed8936;}
                .akppfs-metric.profit{border-color:rgba(0,255,136,.35);} .akppfs-metric.profit h3{font-size:22px;color:#00ff88;}
                .akppfs table{width:100%;border-collapse:collapse;background:transparent;}
                .akppfs th{text-align:left;color:#a0aec0;font-size:11px;text-transform:uppercase;letter-spacing:.5px;padding:10px 12px;border-bottom:1px solid #2d3748;font-weight:700;}
                .akppfs td{padding:11px 12px;border-bottom:1px solid rgba(255,255,255,.06);color:#e2e8f0;font-size:13px;vertical-align:middle;}
                .akppfs tbody tr{transition:background .15s ease;} .akppfs tbody tr:hover{background:rgba(0,255,136,.05);}
                .akppfs .r{text-align:right;font-variant-numeric:tabular-nums;} .akppfs .rev{color:#e2e8f0;} .akppfs .cost{color:#ed8936;} .akppfs .profit-cell{color:#00ff88;font-weight:700;}
                .akppfs .muted{color:#a0aec0;} .akppfs .ok{color:#00ff88;} .akppfs .wait{color:#ed8936;} .akppfs .num{color:#718096;font-size:11px;}
                .akppfs-empty{color:#a0aec0;margin:0;}
                </style>
                <div class="akppfs-head">🛒 Магазин запчастей — финансы</div>
                <div class="akppfs-body">
                    <div class="akppfs-metrics">
                        <div class="akppfs-metric rev"><h3><?php echo $fmt($shop_all); ?> ₽</h3><p>Выручка (все заказы)</p></div>
                        <div class="akppfs-metric paid"><h3><?php echo $fmt($shop_paid); ?> ₽</h3><p>Оплачено · <?php echo (int)$shop_paid_count; ?> шт</p></div>
                        <div class="akppfs-metric cost"><h3><?php echo $fmt($shop_cost_paid); ?> ₽</h3><p>Закуп (себестоимость)</p></div>
                        <div class="akppfs-metric profit"><h3><?php echo $fmt($shop_profit); ?> ₽</h3><p>Прибыль · маржа <?php echo (int)$shop_margin; ?>%</p></div>
                    </div>
                    <?php if (empty($shop_orders)): ?>
                        <p class="akppfs-empty">Заказов пока нет.</p>
                    <?php else: ?>
                    <table>
                        <thead><tr><th>№</th><th>Дата</th><th>Клиент</th><th class="r">Выручка</th><th class="r">Закуп</th><th class="r">Прибыль</th><th>Оплата</th><th>Статус</th></tr></thead>
                        <tbody>
                        <?php foreach ($shop_orders as $so): $sm = $shop_meta[$so->status] ?? null; $pr = (float)$so->total - (float)$so->cost; ?>
                            <tr>
                                <td>#<?php echo (int)$so->id; ?> <span class="num"><?php echo esc_html($so->order_number); ?></span></td>
                                <td class="muted"><?php echo date_i18n('d.m.Y', strtotime($so->created_at)); ?></td>
                                <td><?php echo esc_html($so->client_name); ?></td>
                                <td class="r rev"><?php echo $fmt($so->total); ?> ₽</td>
                                <td class="r cost"><?php echo $fmt($so->cost); ?> ₽</td>
                                <td class="r profit-cell"><?php echo $fmt($pr); ?> ₽</td>
                                <td><?php echo $so->payment_status === 'paid' ? '<span class="ok">✅ оплачен</span>' : '<span class="wait">⏳ ожидает</span>'; ?></td>
                                <td class="muted"><?php echo $sm ? esc_html($sm['icon'] . ' ' . $sm['label']) : esc_html($so->status); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            <form id="akpp-manual-form" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;align-items:end;margin-bottom:24px;">
            <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
            <div><label style="display:block;color:#a0aec0;font-size:12px;margin-bottom:6px;">Сотрудник</label>
                <select name="employee_id" required style="width:100%;padding:10px;background:#2d3748;border:1px solid #4a5568;border-radius:8px;color:#fff;">
                    <option value="">-- выбрать --</option>
                    <?php foreach ($employees as $e): ?><option value="<?php echo $e['id']; ?>"><?php echo esc_html($e['name']); ?></option><?php endforeach; ?>
                </select></div>
            <div><label style="display:block;color:#a0aec0;font-size:12px;margin-bottom:6px;">Сумма ₽</label>
                <input type="number" step="0.01" name="amount" required style="width:100%;padding:10px;background:#2d3748;border:1px solid #4a5568;border-radius:8px;color:#fff;"></div>
            <div><label style="display:block;color:#a0aec0;font-size:12px;margin-bottom:6px;">Тип</label>
                <select name="type" style="width:100%;padding:10px;background:#2d3748;border:1px solid #4a5568;border-radius:8px;color:#fff;">
                    <?php foreach ($type_labels as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?>
                </select></div>
            <div><label style="display:block;color:#a0aec0;font-size:12px;margin-bottom:6px;">Месяц</label>
                <input type="month" name="period_month" value="<?php echo esc_attr($cur_month); ?>" style="width:100%;padding:10px;background:#2d3748;border:1px solid #4a5568;border-radius:8px;color:#fff;"></div>
            <div><label style="display:block;color:#a0aec0;font-size:12px;margin-bottom:6px;">Комментарий</label>
                <input type="text" name="comment" placeholder="Напр. больничный лист №" style="width:100%;padding:10px;background:#2d3748;border:1px solid #4a5568;border-radius:8px;color:#fff;"></div>
            <div><button type="submit" class="button button-primary" style="background:#00ff88;border-color:#00ff88;color:#0a0f1c;font-weight:600;padding:10px 18px;">➕ Начислить</button></div>
        </form>
        <?php if (empty($manual_rows)): ?>
            <p style="color:#a0aec0;text-align:center;padding:20px;">Ручных начислений за период нет</p>
        <?php else: ?>
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr style="background:#2d3748;">
                    <th style="color:#00ff88;padding:10px;text-align:left;">Сотрудник</th>
                    <th style="color:#00ff88;padding:10px;text-align:left;">Тип</th>
                    <th style="color:#00ff88;padding:10px;text-align:left;">Комментарий</th>
                    <th style="color:#00ff88;padding:10px;text-align:center;">Месяц</th>
                    <th style="color:#00ff88;padding:10px;text-align:right;">Сумма</th>
                    <th style="color:#00ff88;padding:10px;text-align:center;"></th>
                </tr></thead>
                <tbody>
                <?php $emp_names = []; foreach ($employees as $e) $emp_names[$e['id']] = $e['name']; foreach ($manual_rows as $m): ?>
                    <tr style="border-bottom:1px solid #2d3748;">
                        <td style="color:#e2e8f0;padding:10px;"><?php echo esc_html($emp_names[$m['employee_id']] ?? '#'.$m['employee_id']); ?></td>
                        <td style="color:#e2e8f0;padding:10px;"><?php echo $type_labels[$m['type']] ?? $m['type']; ?></td>
                        <td style="color:#a0aec0;padding:10px;"><?php echo esc_html($m['comment']); ?></td>
                        <td style="color:#e2e8f0;padding:10px;text-align:center;"><?php echo esc_html($m['period_month']); ?></td>
                        <td style="color:#ed8936;padding:10px;text-align:right;font-weight:600;"><?php echo $fmt($m['amount']); ?> ₽</td>
                        <td style="padding:10px;text-align:center;"><button type="button" class="button button-small akpp-del-manual" data-id="<?php echo intval($m['id']); ?>" style="color:#fc8181;">🗑️</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<script>
jQuery(document).ready(function($){
    var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var nonce = '<?php echo wp_create_nonce('akpp45_nonce'); ?>';
    $('#akpp-manual-form').on('submit', function(e){
        e.preventDefault();
        var $btn = $(this).find('button[type=submit]').prop('disabled', true).text('Сохранение...');
        $.post(ajaxUrl, $(this).serialize() + '&action=akpp_save_salary_manual', function(r){
            if (r.success) { location.reload(); } else { alert(r.data.message || 'Ошибка'); $btn.prop('disabled', false).text('➕ Начислить'); }
        }).fail(function(){ alert('Ошибка соединения'); $btn.prop('disabled', false).text('➕ Начислить'); });
    });
    $(document).on('click', '.akpp-del-manual', function(){
        if (!confirm('Удалить начисление?')) return;
        var $btn = $(this).prop('disabled', true), id = $btn.data('id');
        $.post(ajaxUrl, { action:'akpp_delete_salary_manual', id:id, nonce:nonce }, function(r){
            if (r.success) { location.reload(); } else { alert(r.data.message || 'Ошибка'); $btn.prop('disabled', false); }
        });
    });
});
</script>
