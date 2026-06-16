<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Получаем текущий месяц и год для фильтрации статистики
$current_month = date('Y-m');
$first_day_of_month = date('Y-m-01');

// ==========================================================================
// 1. Сбор статистики
// ==========================================================================

// Общее количество активных сделок
$total_deals = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_deals WHERE status != 'cancelled' AND status != 'completed'");

// Количество новых лидов за текущий месяц
$leads_this_month = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}akpp_leads WHERE DATE(created_at) >= %s",
    $first_day_of_month
));

// Выручка завершенных сделок за текущий месяц
$revenue_this_month = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(total_amount) FROM {$wpdb->prefix}akpp_deals WHERE status = 'completed' AND DATE(created_at) >= %s",
    $first_day_of_month
)) ?: 0;

// Общая стоимость запчастей на складе (примерная)
$warehouse_value = $wpdb->get_var("SELECT SUM(quantity * price) FROM {$wpdb->prefix}akpp_parts");

// ==========================================================================
// 2. Воронка продаж
// ==========================================================================

$funnel_stages = [
    'lead'       => ['label' => 'Лиды', 'color' => 'info'],
    'new'        => ['label' => 'Новые', 'color' => 'success'],
    'diagnostic' => ['label' => 'Диагностика', 'color' => 'warning'],
    'in_work'    => ['label' => 'В работе', 'color' => 'primary'],
    'completed'  => ['label' => 'Завершены', 'color' => 'success']
];

$funnel_data = [];
foreach ($funnel_stages as $status => $info) {
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}akpp_deals WHERE status = %s",
        $status
    ));
    $funnel_data[$status] = [
        'count' => $count ?: 0,
        'label' => $info['label'],
        'color' => $info['color']
    ];
}

// ==========================================================================
// 3. Последние сделки
// ==========================================================================

$recent_deals = $wpdb->get_results("
    SELECT d.*, v.make, v.model, e.name as employee_name
    FROM {$wpdb->prefix}akpp_deals d
    LEFT JOIN {$wpdb->prefix}akpp_vehicles v ON d.vehicle_id = v.id
    LEFT JOIN {$wpdb->prefix}akpp_employees e ON d.employee_id = e.id
    ORDER BY d.created_at DESC 
    LIMIT 5
");

?>

<div class="wrap akpp-crm-wrap">
    <h1 style="color: var(--akpp-accent); margin-bottom: 30px;">📊 Панель управления АКПП45</h1>

    <!-- Карточки статистики -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="akpp-card" style="border-left: 4px solid var(--akpp-accent);">
            <div style="font-size: 14px; color: var(--akpp-text-secondary); text-transform: uppercase;">Активные сделки</div>
            <div style="font-size: 32px; font-weight: 700; color: var(--akpp-text-primary); margin-top: 10px;"><?php echo esc_html($total_deals); ?></div>
        </div>
        <div class="akpp-card" style="border-left: 4px solid var(--akpp-info);">
            <div style="font-size: 14px; color: var(--akpp-text-secondary); text-transform: uppercase;">Новые лиды (мес)</div>
            <div style="font-size: 32px; font-weight: 700; color: var(--akpp-text-primary); margin-top: 10px;"><?php echo esc_html($leads_this_month); ?></div>
        </div>
        <div class="akpp-card" style="border-left: 4px solid var(--akpp-success);">
            <div style="font-size: 14px; color: var(--akpp-text-secondary); text-transform: uppercase;">Выручка (мес)</div>
            <div style="font-size: 32px; font-weight: 700; color: var(--akpp-success); margin-top: 10px;"><?php echo number_format($revenue_this_month, 0, '.', ' '); ?> ₽</div>
        </div>
        <div class="akpp-card" style="border-left: 4px solid var(--akpp-warning);">
            <div style="font-size: 14px; color: var(--akpp-text-secondary); text-transform: uppercase;">Стоимость склада</div>
            <div style="font-size: 32px; font-weight: 700; color: var(--akpp-text-primary); margin-top: 10px;"><?php echo number_format($warehouse_value ?: 0, 0, '.', ' '); ?> ₽</div>
        </div>
    </div>

    <!-- Воронка продаж -->
    <div class="akpp-card">
        <div class="akpp-card-header">🌪️ Воронка продаж</div>
        <div class="akpp-funnel">
            <?php foreach ($funnel_data as $status => $data) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=akpp-deals&status=' . $status)); ?>" style="text-decoration: none; color: inherit;">
                    <div class="funnel-stage">
                        <div class="label"><?php echo esc_html($data['label']); ?></div>
                        <div class="count"><?php echo esc_html($data['count']); ?></div>
                        <span class="akpp-badge akpp-badge-<?php echo esc_attr($data['color']); ?>">Этап</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Последние сделки -->
    <div class="akpp-card">
        <div class="akpp-card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <span>🕒 Последние сделки</span>
            <a href="<?php echo esc_url(admin_url('admin.php?page=akpp-deals')); ?>" class="button button-secondary" style="font-size: 12px;">Все сделки →</a>
        </div>
        
        <?php if (!empty($recent_deals)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>Клиент</th>
                        <th>Автомобиль</th>
                        <th>Мастер</th>
                        <th>Сумма</th>
                        <th>Статус</th>
                        <th>Дата</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_deals as $deal) : 
                        $badges = [
                            'lead' => '<span class="akpp-badge akpp-badge-info">Лид</span>',
                            'new' => '<span class="akpp-badge akpp-badge-success">Новая</span>',
                            'diagnostic' => '<span class="akpp-badge akpp-badge-warning">Диагностика</span>',
                            'in_work' => '<span class="akpp-badge akpp-badge-primary">В работе</span>',
                            'completed' => '<span class="akpp-badge akpp-badge-success">Завершена</span>',
                            'cancelled' => '<span class="akpp-badge akpp-badge-danger">Отменена</span>'
                        ];
                        $status_badge = $badges[$deal['status']] ?? $deal['status'];
                        $vehicle_name = (!empty($deal['brand']) && !empty($deal['model'])) ? esc_html($deal['brand'] . ' ' . $deal['model']) : '<span class="akpp-text-muted">Не указано</span>';
                    ?>
                        <tr>
                            <td><strong>#<?php echo esc_html($deal['id']); ?></strong></td>
                            <td>
                                <?php echo esc_html($deal['client_name']); ?><br>
                                <small class="akpp-text-muted"><?php echo esc_html($deal['client_phone']); ?></small>
                            </td>
                            <td><?php echo $vehicle_name; ?></td>
                            <td><?php echo esc_html($deal['employee_name'] ?: 'Не назначен'); ?></td>
                            <td><strong><?php echo number_format($deal['total_amount'], 0, '.', ' '); ?> ₽</strong></td>
                            <td><?php echo $status_badge; ?></td>
                            <td class="akpp-text-muted"><?php echo date('d.m.Y', strtotime($deal['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <div style="text-align: center; padding: 40px; color: var(--akpp-text-secondary);">
                <p>Сделок пока нет. <a href="<?php echo esc_url(admin_url('admin.php?page=akpp-new-deal')); ?>">Создать первую сделку</a></p>
            </div>
        <?php endif; ?>
    </div>
</div>
