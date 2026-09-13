<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
list($cat_key, $cat_title) = $cat;
$deals = $wpdb->get_results($wpdb->prepare(
    "SELECT d.*, e.name AS employee_name FROM {$wpdb->prefix}akpp_deals d
     LEFT JOIN {$wpdb->prefix}akpp_employees e ON d.employee_id=e.id
     WHERE d.service_category=%s ORDER BY d.created_at DESC LIMIT 200", $cat_key));
$statuses = ['new'=>'🆕 Новая','diagnostic'=>'🔍 Диагностика','in_work'=>'🔧 В работе','waiting_parts'=>'📦 Ожидание запчастей','completed'=>'✅ Выполнено','cancelled'=>'❌ Отменено'];
?>
<div class="wrap akpp-crm-wrap">
    <h1 style="color:#00ff88;border-left:4px solid #00ff88;padding-left:15px;"><?php echo esc_html($cat_title); ?> — сделки</h1>
    <p style="color:#a0aec0;">Сделки направления «<?php echo esc_html($cat_title); ?>». Направление будет указываться в карточке сделки (добавлю в 8b).</p>
    <?php if (empty($deals)): ?>
        <div style="text-align:center;padding:60px;color:#a0aec0;"><p style="font-size:48px;margin:0;">📭</p><p>Пока нет сделок этого направления</p></div>
    <?php else: ?>
        <table style="width:100%;border-collapse:collapse;background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;overflow:hidden;">
            <thead><tr style="background:#2d3748;">
                <th style="color:#00ff88;padding:12px;text-align:left;">№</th>
                <th style="color:#00ff88;padding:12px;text-align:left;">Авто</th>
                <th style="color:#00ff88;padding:12px;text-align:left;">Проблема</th>
                <th style="color:#00ff88;padding:12px;text-align:left;">Мастер</th>
                <th style="color:#00ff88;padding:12px;text-align:right;">Работы</th>
                <th style="color:#00ff88;padding:12px;text-align:center;">Статус</th>
                <th style="color:#00ff88;padding:12px;text-align:left;">Дата</th>
            </tr></thead>
            <tbody>
            <?php foreach ($deals as $d): ?>
                <tr style="border-bottom:1px solid #2d3748;">
                    <td style="color:#e2e8f0;padding:12px;">#<?php echo intval($d->id); ?></td>
                    <td style="color:#e2e8f0;padding:12px;"><?php echo esc_html(trim($d->make.' '.$d->model.' '.$d->year)); ?></td>
                    <td style="color:#e2e8f0;padding:12px;"><?php echo esc_html(wp_trim_words($d->problem_description ?: '—', 8)); ?></td>
                    <td style="color:#e2e8f0;padding:12px;"><?php echo esc_html($d->employee_name ?: '—'); ?></td>
                    <td style="color:#9ae6b4;padding:12px;text-align:right;"><?php echo number_format($d->work_cost,0,',',' '); ?> ₽</td>
                    <td style="color:#e2e8f0;padding:12px;text-align:center;"><?php echo $statuses[$d->status] ?? $d->status; ?></td>
                    <td style="color:#a0aec0;padding:12px;"><?php echo date_i18n('d.m.Y', strtotime($d->created_at)); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
