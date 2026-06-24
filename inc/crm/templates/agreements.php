<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'akpp_agreements';

// Статистика
$total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
$today = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE DATE(accepted_at) = CURDATE()");
$month = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE MONTH(accepted_at) = MONTH(CURDATE()) AND YEAR(accepted_at) = YEAR(CURDATE())");

// Последние согласия
$agreements = $wpdb->get_results("SELECT * FROM {$table} ORDER BY accepted_at DESC LIMIT 50");
?>

<div class="wrap akpp-crm-wrap">
    <div class="agreements-page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h1>📜 Согласия с договором-офертой</h1>
        <button type="button" id="export-agreements" class="button button-primary">📥 Экспорт CSV</button>
    </div>

    <!-- Статистика -->
    <div class="agreements-stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:25px;">
        <div class="agreements-stat-card" style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:20px;text-align:center;">
            <div class="agreements-stat-value" style="font-size:32px;font-weight:700;color:#00ff88;"><?php echo $total; ?></div>
            <div class="agreements-stat-label" style="color:#a0aec0;font-size:13px;text-transform:uppercase;">Всего согласий</div>
        </div>
        <div class="agreements-stat-card" style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:20px;text-align:center;">
            <div class="agreements-stat-value" style="font-size:32px;font-weight:700;color:#63b3ed;"><?php echo $today; ?></div>
            <div class="agreements-stat-label" style="color:#a0aec0;font-size:13px;text-transform:uppercase;">Сегодня</div>
        </div>
        <div class="agreements-stat-card" style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:20px;text-align:center;">
            <div class="agreements-stat-value" style="font-size:32px;font-weight:700;color:#f6ad55;"><?php echo $month; ?></div>
            <div class="agreements-stat-label" style="color:#a0aec0;font-size:13px;text-transform:uppercase;">За месяц</div>
        </div>
    </div>

    <!-- Таблица -->
    <div class="agreements-table-wrapper" style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:20px;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th>Клиент</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Сделка</th>
                    <th>Источник</th>
                    <th>IP</th>
                    <th>Дата согласия</th>
                    <th style="width:100px;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($agreements)): ?>
                    <tr><td colspan="9" style="text-align:center;padding:40px;color:#718096;">Согласий ещё нет</td></tr>
                <?php else: ?>
                    <?php foreach ($agreements as $agr): ?>
                        <tr>
                            <td><?php echo intval($agr->id); ?></td>
                            <td><strong><?php echo esc_html($agr->client_name); ?></strong></td>
                            <td><?php echo esc_html($agr->client_phone); ?></td>
                            <td><?php echo esc_html($agr->client_email ?: '—'); ?></td>
                            <td>
                                <?php if ($agr->deal_id): ?>
                                    <a href="<?php echo admin_url('admin.php?page=akpp-crm-deals&view=' . $agr->deal_id); ?>" 
                                       style="color:#00ff88;">#<?php echo intval($agr->deal_id); ?></a>
                                <?php else: ?>
                                    <span style="color:#718096;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="source-badge" style="display:inline-block;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;background:#2d3748;color:#e2e8f0;">
                                    <?php 
                                    $sources = ['crm_deal' => 'CRM', 'site_form' => 'Сайт', 'registration' => 'Регистрация'];
                                    echo $sources[$agr->source] ?? $agr->source;
                                    ?>
                                </span>
                            </td>
                            <td><code style="font-size:11px;"><?php echo esc_html($agr->ip_address); ?></code></td>
                            <td><?php echo date_i18n('d.m.Y H:i', strtotime($agr->accepted_at)); ?></td>
                            <td>
                                <button class="button button-small btn-view-agreement" 
                                        data-id="<?php echo $agr->id; ?>"
                                        data-name="<?php echo esc_attr($agr->client_name); ?>"
                                        data-phone="<?php echo esc_attr($agr->client_phone); ?>"
                                        data-email="<?php echo esc_attr($agr->client_email); ?>"
                                        data-deal="<?php echo intval($agr->deal_id); ?>"
                                        data-source="<?php echo esc_attr($agr->source); ?>"
                                        data-ip="<?php echo esc_attr($agr->ip_address); ?>"
                                        data-date="<?php echo esc_attr($agr->accepted_at); ?>"
                                        data-version="<?php echo esc_attr($agr->agreement_version); ?>"
                                        data-useragent="<?php echo esc_attr($agr->user_agent); ?>"
                                        style="background:#00ff88;border-color:#00ff88;color:#1a1f2e;">👁️</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Просмотр деталей согласия
    $(document).on('click', '.btn-view-agreement', function() {
        var data = $(this).data();
        var html = '<div style="padding:20px;">' +
            '<h3 style="color:#00ff88;">📜 Детали согласия #' + data.id + '</h3>' +
            '<p><strong>Клиент:</strong> ' + data.name + '</p>' +
            '<p><strong>Телефон:</strong> ' + data.phone + '</p>' +
            '<p><strong>Email:</strong> ' + (data.email || '—') + '</p>' +
            '<p><strong>Сделка:</strong> ' + (data.deal ? '#' + data.deal : '—') + '</p>' +
            '<p><strong>Источник:</strong> ' + data.source + '</p>' +
            '<p><strong>IP-адрес:</strong> <code>' + data.ip + '</code></p>' +
            '<p><strong>Дата согласия:</strong> ' + data.date + '</p>' +
            '<p><strong>Версия оферты:</strong> ' + data.version + '</p>' +
            '<hr style="border-color:#2d3748;margin:15px 0;">' +
            '<p style="color:#a0aec0;font-size:13px;"><strong>User-Agent:</strong><br><code style="font-size:11px;word-break:break-all;">' + (data.useragent || '—') + '</code></p>' +
            '<p style="color:#a0aec0;font-size:13px;">Клиент подтвердил согласие с условиями договора-оферты</p>' +
            '</div>';
        
        var $modal = $('<div class="akpp-modal active" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:99999;display:flex;align-items:center;justify-content:center;"><div class="akpp-modal-content" style="max-width:600px;background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;position:relative;">' +
            '<span class="akpp-modal-close" style="position:absolute;top:10px;right:15px;font-size:24px;cursor:pointer;color:#fff;">&times;</span>' + html + '</div></div>');
        $('body').append($modal);
        
        $modal.find('.akpp-modal-close, .akpp-modal').on('click', function(e) {
            if (e.target === this) $modal.remove();
        });
    });
    
    // Экспорт CSV
    $('#export-agreements').on('click', function() {
        alert('Функция экспорта будет добавлена позже');
    });
});
</script>