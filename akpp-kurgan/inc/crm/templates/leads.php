<?php
/**
 * Шаблон страницы лидов
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Получаем сотрудников для фильтра (исправленный запрос)
$employees = $wpdb->get_results("SELECT id, name as full_name FROM {$wpdb->prefix}akpp_employees WHERE is_active = 1 ORDER BY name ASC");

// Получаем лиды (исправленный запрос)
$status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

$where = [];
if ($status_filter !== 'all') {
    $where[] = $wpdb->prepare("status = %s", $status_filter);
}
if (!empty($search)) {
    $where[] = $wpdb->prepare("(client_name LIKE '%%%s%%' OR client_phone LIKE '%%%s%%')", $search, $search);
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$leads = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}akpp_leads {$where_clause} ORDER BY created_at DESC");
?>

<div class="wrap akpp-crm-wrap">
    <h1 class="wp-heading-inline">📋 Лиды</h1>
    <hr class="wp-header-end">
    
    <form method="get">
        <input type="hidden" name="page" value="akpp-crm-leads">
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="status_filter">
                    <option value="all">Все статусы</option>
                    <option value="new" <?php selected($status_filter, 'new'); ?>>🆕 Новый</option>
                    <option value="contacted" <?php selected($status_filter, 'contacted'); ?>>📞 Связались</option>
                    <option value="converted" <?php selected($status_filter, 'converted'); ?>>✅ Конвертирован</option>
                    <option value="lost" <?php selected($status_filter, 'lost'); ?>>❌ Потерян</option>
                </select>
                
                <input type="submit" class="button" value="Фильтровать">
            </div>
            
            <div class="alignright">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Поиск...">
                <input type="submit" class="button" value="Найти">
            </div>
        </div>
    </form>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Клиент</th>
                <th>Телефон</th>
                <th>Автомобиль</th>
                <th>Источник</th>
                <th>Статус</th>
                <th>Дата</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($leads): ?>
                <?php foreach ($leads as $lead): ?>
                <tr>
                    <td>#<?php echo $lead->id; ?></td>
                    <td><?php echo esc_html($lead->client_name); ?></td>
                    <td><?php echo esc_html($lead->client_phone); ?></td>
                    <td><?php echo esc_html($lead->car_brand); ?></td>
                    <td><?php echo esc_html($lead->source); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo esc_attr($lead->status); ?>">
                            <?php echo esc_html($lead->status); ?>
                        </span>
                    </td>
                    <td><?php echo date_i18n('d.m.Y H:i', strtotime($lead->created_at)); ?></td>
                    <td>
                        <button class="button button-small view-lead" data-id="<?php echo $lead->id; ?>">👁️</button>
                        <a href="?page=akpp-crm-deal-form&lead_id=<?php echo $lead->id; ?>" class="button button-small">➕ В сделку</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center;">Нет лидов для отображения</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
