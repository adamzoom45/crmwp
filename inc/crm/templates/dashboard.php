<?php
/**
 * Шаблон дашборда CRM
 * Воронка учитывает лиды из таблицы leads
 */

if (!defined('ABSPATH')) exit;

global $wpdb;

$deals_table = $wpdb->prefix . 'akpp_deals';
$leads_table = $wpdb->prefix . 'akpp_leads';
$parts_table = $wpdb->prefix . 'akpp_parts';
$clients_table = $wpdb->prefix . 'akpp_site_users';
$vehicles_table = $wpdb->prefix . 'akpp_vehicles';

// ============================================================================
// СТАТИСТИКА
// ============================================================================

// Активные сделки
$active_deals = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$deals_table} WHERE status NOT IN ('completed', 'cancelled')"
);

// Новые лиды за месяц
$new_leads_month = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$leads_table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) AND status != 'converted'"
);

// Выручка за месяц
$revenue_month = (float) $wpdb->get_var(
    "SELECT COALESCE(SUM(total_amount), 0) FROM {$deals_table} WHERE status = 'completed' AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
);

// Стоимость склада
$warehouse_value = (float) $wpdb->get_var(
    "SELECT COALESCE(SUM(price * quantity), 0) FROM {$parts_table}"
);

// ============================================================================
// ВОРОНКА ПРОДАЖ (сделки + неконвертированные лиды)
// ============================================================================

$funnel_stats = [
    'lead' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$deals_table} WHERE status = 'lead'"),
    'new' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$deals_table} WHERE status = 'new'"),
    'diagnostic' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$deals_table} WHERE status = 'diagnostic'"),
    'in_work' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$deals_table} WHERE status = 'in_work'"),
    'completed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$deals_table} WHERE status = 'completed'"),
];

// Добавляем неконвертированные лиды к этапу "new"
$unconverted_leads = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$leads_table} WHERE status NOT IN ('converted', 'cancelled', 'lost')"
);
$funnel_stats['new'] += $unconverted_leads;

// ============================================================================
// ПОСЛЕДНИЕ СДЕЛКИ (с JOIN)
// ============================================================================

$recent_deals = $wpdb->get_results(
    "SELECT 
        d.*,
        c.full_name as client_name,
        c.phone as client_phone,
        v.make,
        v.model,
        v.year,
        CONCAT_WS(' ', v.make, v.model, v.year) as car_info
     FROM {$deals_table} d
     LEFT JOIN {$clients_table} c ON d.client_id = c.id
     LEFT JOIN {$vehicles_table} v ON d.vehicle_id = v.id
     ORDER BY d.created_at DESC 
     LIMIT 5",
    ARRAY_A
);

// ============================================================================
// ПОСЛЕДНИЕ ЛИДЫ
// ============================================================================

$recent_leads = $wpdb->get_results(
    "SELECT * FROM {$leads_table} ORDER BY created_at DESC LIMIT 5",
    ARRAY_A
);

$status_labels = [
    'lead' => ['label' => '🔵 Лид', 'color' => '#63b3ed'],
    'new' => ['label' => '🟢 Новая', 'color' => '#00ff88'],
    'diagnostic' => ['label' => ' Диагностика', 'color' => '#f6ad55'],
    'in_work' => ['label' => '🟠 В работе', 'color' => '#f6ad55'],
    'completed' => ['label' => '✅ Завершена', 'color' => '#00ff88'],
    'cancelled' => ['label' => '❌ Отменена', 'color' => '#fc8181'],
];

$lead_status_labels = [
    'new' => ['label' => ' Новый', 'color' => '#63b3ed'],
    'contacted' => ['label' => '📞 Связались', 'color' => '#f6ad55'],
    'converted' => ['label' => ' Конвертирован', 'color' => '#00ff88'],
    'cancelled' => ['label' => '❌ Отменено', 'color' => '#fc8181'],
    'lost' => ['label' => '❌ Потерян', 'color' => '#fc8181'],
];

$source_labels = [
    'site_form' => '🌐 Форма',
    'site_booking' => '🌐 Сайт',
    'avito' => '📱 Авито',
    'telegram' => '📨 Telegram',
    'phone' => '📞 Телефон',
];
?>

<div class="wrap akpp-crm-wrap">
    <h1 style="color: #00ff88; border-left: 4px solid #00ff88; padding-left: 15px; margin-bottom: 30px;">
        📊 Панель управления АКПП45
    </h1>

    <!-- КАРТОЧКИ СТАТИСТИКИ -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        
        <div class="akpp-card" style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 24px;">
            <div style="color: #a0aec0; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">
                АКТИВНЫЕ СДЕЛКИ
            </div>
            <div style="font-size: 36px; font-weight: 700; color: #00ff88;">
                <?php echo number_format($active_deals, 0, '.', ' '); ?>
            </div>
        </div>

        <div class="akpp-card" style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 24px;">
            <div style="color: #a0aec0; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">
                НОВЫЕ ЛИДЫ (МЕС)
            </div>
            <div style="font-size: 36px; font-weight: 700; color: #f6ad55;">
                <?php echo number_format($new_leads_month, 0, '.', ' '); ?>
            </div>
        </div>

        <div class="akpp-card" style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 24px;">
            <div style="color: #a0aec0; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">
                ВЫРУЧКА (МЕС)
            </div>
            <div style="font-size: 36px; font-weight: 700; color: #00ff88;">
                <?php echo number_format($revenue_month, 0, '.', ' '); ?> ₽
            </div>
        </div>

        <div class="akpp-card" style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 24px;">
            <div style="color: #a0aec0; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">
                СТОИМОСТЬ СКЛАДА
            </div>
            <div style="font-size: 36px; font-weight: 700; color: #63b3ed;">
                <?php echo number_format($warehouse_value, 0, '.', ' '); ?> ₽
            </div>
        </div>
    </div>

    <!-- ВОРОНКА ПРОДАЖ -->
    <div class="akpp-card" style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 24px; margin-bottom: 30px;">
        <h2 style="color: #00ff88; margin: 0 0 20px 0; font-size: 18px; border-bottom: 1px solid #2d3748; padding-bottom: 12px;">
            📈 Воронка продаж
        </h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
            <?php 
            $stage_names = [
                'lead' => '🔵 Лиды',
                'new' => '🟢 Новые',
                'diagnostic' => '🟡 Диагностика',
                'in_work' => '🟠 В работе',
                'completed' => '✅ Завершены',
            ];
            $stage_colors = [
                'lead' => '#63b3ed',
                'new' => '#00ff88',
                'diagnostic' => '#f6ad55',
                'in_work' => '#f6ad55',
                'completed' => '#00ff88',
            ];
            
            foreach ($funnel_stats as $stage => $count) : 
            ?>
            <div style="background: #2d3748; border-radius: 8px; padding: 16px; text-align: center;">
                <div style="color: <?php echo $stage_colors[$stage]; ?>; font-weight: 600; margin-bottom: 8px; font-size: 14px;">
                    <?php echo $stage_names[$stage]; ?>
                </div>
                <div style="font-size: 32px; font-weight: 700; color: #fff;">
                    <?php echo $count; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ДВЕ КОЛОНКИ: СДЕЛКИ И ЛИДЫ -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        
        <!-- ПОСЛЕДНИЕ СДЕЛКИ -->
        <div class="akpp-card" style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="color: #00ff88; margin: 0; font-size: 18px;">🕐 Последние сделки</h2>
                <a href="?page=akpp-crm-deals" style="color: #00ff88; text-decoration: none; font-weight: 600; font-size: 14px;">Все →</a>
            </div>

            <?php if (empty($recent_deals)) : ?>
                <div style="text-align: center; padding: 40px 20px; color: #718096;">
                    <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                    <p style="font-size: 14px; margin-bottom: 16px;">Сделок пока нет</p>
                    <a href="?page=akpp-crm-new-deal" 
                       style="background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%); color: #1a1f2e; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-block;">
                        ➕ Создать сделку
                    </a>
                </div>
            <?php else : ?>
                <?php foreach ($recent_deals as $deal) : 
                    $status = $status_labels[$deal['status']] ?? ['label' => $deal['status'], 'color' => '#a0aec0'];
                    $client_name = $deal['client_name'] ?? '—';
                    $car_info = trim($deal['car_info'] ?? '');
                    if (empty($car_info)) $car_info = $deal['vin'] ?? '—';
                ?>
                <div style="padding: 12px; border-bottom: 1px solid #2d3748; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 600; color: #e2e8f0; font-size: 14px;">
                            #<?php echo intval($deal['id']); ?> <?php echo esc_html($client_name); ?>
                        </div>
                        <div style="font-size: 13px; color: #718096;"><?php echo esc_html($car_info); ?></div>
                    </div>
                    <div style="text-align: right;">
                        <div style="color: #00ff88; font-weight: 600; font-size: 14px;">
                            <?php echo number_format($deal['total_amount'] ?? 0, 0, '.', ' '); ?> ₽
                        </div>
                        <span style="display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; background: <?php echo $status['color']; ?>22; color: <?php echo $status['color']; ?>;">
                            <?php echo $status['label']; ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ПОСЛЕДНИЕ ЛИДЫ -->
        <div class="akpp-card" style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="color: #00ff88; margin: 0; font-size: 18px;">🎯 Последние лиды</h2>
                <a href="?page=akpp-crm-leads" style="color: #00ff88; text-decoration: none; font-weight: 600; font-size: 14px;">Все →</a>
            </div>

            <?php if (empty($recent_leads)) : ?>
                <div style="text-align: center; padding: 40px 20px; color: #718096;">
                    <div style="font-size: 48px; margin-bottom: 16px;">🎯</div>
                    <p style="font-size: 14px;">Лидов пока нет</p>
                </div>
            <?php else : ?>
                <?php foreach ($recent_leads as $lead) : 
                    $status = $lead_status_labels[$lead['status']] ?? ['label' => $lead['status'], 'color' => '#a0aec0'];
                    $source = $source_labels[$lead['source']] ?? $lead['source'];
                ?>
                <div style="padding: 12px; border-bottom: 1px solid #2d3748; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 600; color: #e2e8f0; font-size: 14px;">
                            #<?php echo intval($lead['id']); ?> <?php echo esc_html($lead['client_name']); ?>
                        </div>
                        <div style="font-size: 13px; color: #718096;">
                            <?php echo esc_html($lead['car_brand'] ?? '—'); ?> • <?php echo esc_html($source); ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span style="display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; background: <?php echo $status['color']; ?>22; color: <?php echo $status['color']; ?>;">
                            <?php echo $status['label']; ?>
                        </span>
                        <?php if ($lead['status'] !== 'converted') : ?>
                        <div style="margin-top: 4px;">
                            <a href="?page=akpp-crm-new-deal&lead_id=<?php echo intval($lead['id']); ?>&_wpnonce=<?php echo wp_create_nonce('create_deal_from_lead_' . $lead['id']); ?>" 
                               style="color: #00ff88; text-decoration: none; font-size: 12px; font-weight: 600;">
                                💰 В сделку
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- БЫСТРЫЕ ДЕЙСТВИЯ -->
    <div class="akpp-card" style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 24px;">
        <h2 style="color: #00ff88; margin: 0 0 20px 0; font-size: 18px; border-bottom: 1px solid #2d3748; padding-bottom: 12px;"> Быстрые действия</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
            <a href="?page=akpp-crm-new-deal" style="background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%); color: #1a1f2e; padding: 16px; border-radius: 8px; text-decoration: none; font-weight: 600; text-align: center;">➕ Новая сделка</a>
            <a href="?page=akpp-crm-leads" style="background: #2d3748; color: #fff; padding: 16px; border-radius: 8px; text-decoration: none; font-weight: 600; text-align: center; border: 1px solid #4a5568;">🎯 Все лиды</a>
            <a href="?page=akpp-crm-employees" style="background: #2d3748; color: #fff; padding: 16px; border-radius: 8px; text-decoration: none; font-weight: 600; text-align: center; border: 1px solid #4a5568;"> Сотрудники</a>
            <a href="?page=akpp-crm-parts" style="background: #2d3748; color: #fff; padding: 16px; border-radius: 8px; text-decoration: none; font-weight: 600; text-align: center; border: 1px solid #4a5568;">📦 Склад</a>
        </div>
    </div>
</div>

<style>
@media (max-width: 768px) {
    .wrap .akpp-crm-wrap > div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>