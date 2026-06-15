<?php
/**
 * Шаблон панели управления CRM
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Статистика
$deals_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_deals");
$leads_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_leads WHERE status = 'new'");
$employees_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_employees WHERE is_active = 1");

// Статистика по воронке
$funnel_stats = [
    'lead' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_leads WHERE status = 'new'"),
    'new' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_deals WHERE status = 'new'"),
    'diagnostic' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_deals WHERE status = 'diagnostic'"),
    'in_work' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_deals WHERE status = 'in_work'"),
    'completed' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_deals WHERE status = 'completed'")
];

// Финансы
$total_revenue = $wpdb->get_var("SELECT SUM(total_amount) FROM {$wpdb->prefix}akpp_deals WHERE status = 'completed'");
$total_revenue = $total_revenue ?: 0;

// Последние сделки (исправленный запрос)
$recent_deals = $wpdb->get_results("
    SELECT d.*, 
           v.make, v.model,
           e.name as employee_name
    FROM {$wpdb->prefix}akpp_deals d
    LEFT JOIN {$wpdb->prefix}akpp_vehicles v ON d.vehicle_id = v.id
    LEFT JOIN {$wpdb->prefix}akpp_employees e ON d.employee_id = e.id
    ORDER BY d.created_at DESC 
    LIMIT 5
");

// Склад (количество запчастей)
$parts_total = $wpdb->get_var("SELECT SUM(quantity) FROM {$wpdb->prefix}akpp_parts");
$parts_low = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_parts WHERE quantity < 5");
?>

<div class="wrap akpp-crm-wrap">
    <h1>📊 Панель управления CRM</h1>
    
    <!-- Карточки статистики -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo intval($deals_count); ?></div>
            <div class="stat-label">Всего сделок</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #ffc107;"><?php echo intval($leads_count); ?></div>
            <div class="stat-label">Новых лидов</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #28a745;"><?php echo number_format($total_revenue, 0, ',', ' '); ?> ₽</div>
            <div class="stat-label">Выручка</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo intval($employees_count); ?></div>
            <div class="stat-label">Сотрудников</div>
        </div>
    </div>
    
    <!-- Воронка продаж -->
    <div class="dashboard-section">
        <h2>🔄 Воронка продаж</h2>
        <div class="funnel-container">
            <div class="funnel-stage">
                <div class="funnel-stage-header" style="background: #6c5ce7;">Лиды</div>
                <div class="funnel-stage-value"><?php echo $funnel_stats['lead']; ?></div>
            </div>
            <div class="funnel-stage">
                <div class="funnel-stage-header" style="background: #0984e3;">Новые</div>
                <div class="funnel-stage-value"><?php echo $funnel_stats['new']; ?></div>
            </div>
            <div class="funnel-stage">
                <div class="funnel-stage-header" style="background: #fdcb6e;">Диагностика</div>
                <div class="funnel-stage-value"><?php echo $funnel_stats['diagnostic']; ?></div>
            </div>
            <div class="funnel-stage">
                <div class="funnel-stage-header" style="background: #00b894;">В работе</div>
                <div class="funnel-stage-value"><?php echo $funnel_stats['in_work']; ?></div>
            </div>
            <div class="funnel-stage">
                <div class="funnel-stage-header" style="background: #27ae60;">Выполнено</div>
                <div class="funnel-stage-value"><?php echo $funnel_stats['completed']; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Последние сделки -->
    <div class="dashboard-section">
        <h2>📋 Последние сделки</h2>
        <?php if ($recent_deals): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Клиент</th>
                    <th>Автомобиль</th>
                    <th>Сотрудник</th>
                    <th>Сумма</th>
                    <th>Статус</th>
                    <th>Дата</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_deals as $deal): ?>
                <tr>
                    <td>#<?php echo $deal->id; ?></td>
                    <td><?php echo esc_html($deal->client_id); ?></td>
                    <td><?php echo esc_html($deal->make . ' ' . $deal->model); ?></td>
                    <td><?php echo esc_html($deal->employee_name ?: '—'); ?></td>
                    <td><?php echo number_format($deal->total_amount, 0, ',', ' ') . ' ₽'; ?></td>
                    <td><?php echo $deal->status; ?></td>
                    <td><?php echo date_i18n('d.m.Y', strtotime($deal->created_at)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>Нет сделок для отображения</p>
        <?php endif; ?>
    </div>
    
    <!-- Склад -->
    <div class="dashboard-section">
        <h2>📦 Склад</h2>
        <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr);">
            <div class="stat-card">
                <div class="stat-value"><?php echo intval($parts_total); ?></div>
                <div class="stat-label">Всего запчастей</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #ffc107;"><?php echo intval($parts_low); ?></div>
                <div class="stat-label">Требуют пополнения</div>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-section {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.dashboard-section h2 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 18px;
    border-left: 4px solid #667eea;
    padding-left: 12px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-value {
    font-size: 32px;
    font-weight: bold;
    color: #333;
}

.stat-label {
    font-size: 14px;
    color: #666;
    margin-top: 8px;
}

.funnel-container {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.funnel-stage {
    flex: 1;
    min-width: 120px;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.funnel-stage-header {
    padding: 12px;
    text-align: center;
    color: #fff;
    font-weight: 600;
}

.funnel-stage-value {
    padding: 20px;
    text-align: center;
    font-size: 28px;
    font-weight: bold;
}
</style>
