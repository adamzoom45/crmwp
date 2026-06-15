<?php
if (!defined('ABSPATH')) exit;

// Если пользователь не авторизован, перенаправляем на страницу входа или показываем сообщение
if (!is_user_logged_in()) {
    echo '<div class="akpp-frontend-wrap">';
    echo '<div class="akpp-form-card" style="text-align: center;">';
    echo '<h3>Доступ ограничен</h3>';
    echo '<p class="akpp-text-muted" style="margin-bottom: 25px;">Для просмотра этой страницы необходимо авторизоваться.</p>';
    echo '<a href="' . esc_url(home_url('/login/')) . '" class="akpp-btn">Войти в систему</a>';
    echo '</div>';
    echo '</div>';
    return;
}

global $wpdb;
$current_user_id = get_current_user_id();
$current_user = wp_get_current_user();

// Получаем дополнительные данные клиента из нашей таблицы
$client_data = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}akpp_site_users WHERE wp_user_id = %d",
    $current_user_id
), ARRAY_A);

// Получаем статистику по сделкам клиента (ищем по номеру телефона или email)
$phone = $client_data['phone'] ?? $current_user->user_email;
$deals_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}akpp_deals WHERE client_phone = %s OR client_name LIKE %s",
    $phone, '%' . $wpdb->esc_like($current_user->display_name) . '%'
)) ?: 0;

$active_deals_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}akpp_deals WHERE (client_phone = %s OR client_name LIKE %s) AND status IN ('new', 'diagnostic', 'in_work')",
    $phone, '%' . $wpdb->esc_like($current_user->display_name) . '%'
)) ?: 0;

// Получаем последние 5 сделок/заявок
$recent_deals = $wpdb->get_results($wpdb->prepare(
    "SELECT id, client_name, status, total_amount, created_at 
     FROM {$wpdb->prefix}akpp_deals 
     WHERE client_phone = %s OR client_name LIKE %s 
     ORDER BY created_at DESC LIMIT 5",
    $phone, '%' . $wpdb->esc_like($current_user->display_name) . '%'
), ARRAY_A);

$ajax_url = admin_url('admin-ajax.php');
$nonce = wp_create_nonce('akpp_crm_nonce');
?>

<div class="akpp-profile-container">
    <div class="akpp-profile-header">
        <h3>👤 Личный кабинет</h3>
        <button type="button" id="akpp-logout-btn" class="akpp-btn akpp-btn-danger" style="width: auto; padding: 8px 20px; margin: 0; font-size: 14px;">
            Выйти
        </button>
    </div>

    <!-- Статистика -->
    <div class="akpp-profile-stats">
        <div class="akpp-stat-box">
            <div class="stat-value"><?php echo esc_html($deals_count); ?></div>
            <div class="stat-label">Всего обращений</div>
        </div>
        <div class="akpp-stat-box">
            <div class="stat-value" style="color: var(--akpp-f-warning, #f59e0b);"><?php echo esc_html($active_deals_count); ?></div>
            <div class="stat-label">В работе</div>
        </div>
        <div class="akpp-stat-box">
            <div class="stat-value" style="color: var(--akpp-f-success, #10b981);"><?php echo esc_html(max(0, $deals_count - $active_deals_count)); ?></div>
            <div class="stat-label">Завершено</div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        
        <!-- ЛЕВАЯ КОЛОНКА: Форма профиля -->
        <div class="akpp-form-card" style="margin: 0;">
            <h4 style="margin-top: 0; color: var(--akpp-f-accent); border-bottom: 1px solid var(--akpp-f-border); padding-bottom: 10px;">
                ⚙️ Мои данные
            </h4>
            <form id="akpp-profile-form" class="akpp-form">
                <?php wp_nonce_field('akpp_crm_nonce', 'nonce'); ?>
                
                <div class="form-group">
                    <label for="prof_name">ФИО</label>
                    <input type="text" id="prof_name" name="full_name" value="<?php echo esc_attr($client_data['full_name'] ?: $current_user->display_name); ?>" required>
                </div>

                <div class="form-group">
                    <label for="prof_phone">Телефон</label>
                    <input type="tel" id="prof_phone" name="phone" value="<?php echo esc_attr($client_data['phone']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="prof_car">Информация об авто</label>
                    <input type="text" id="prof_car" name="car_info" value="<?php echo esc_attr($client_data['car_info']); ?>" placeholder="Марка, модель, год, VIN">
                </div>

                <button type="submit" class="akpp-btn" style="margin-top: 10px;">Сохранить изменения</button>
                <div class="akpp-form-message"></div>
            </form>
        </div>

        <!-- ПРАВАЯ КОЛОНКА: История обращений -->
        <div class="akpp-form-card" style="margin: 0;">
            <h4 style="margin-top: 0; color: var(--akpp-f-accent); border-bottom: 1px solid var(--akpp-f-border); padding-bottom: 10px;">
                📋 История обращений
            </h4>
            
            <?php if (!empty($recent_deals)) : ?>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($recent_deals as $deal) : 
                        $status_labels = [
                            'new' => '<span style="color: #3b82f6;">● Новая</span>',
                            'diagnostic' => '<span style="color: #f59e0b;">● Диагностика</span>',
                            'in_work' => '<span style="color: #f97316;">● В работе</span>',
                            'completed' => '<span style="color: #10b981;">● Завершена</span>',
                            'cancelled' => '<span style="color: #ef4444;">● Отменена</span>'
                        ];
                        $badge = $status_labels[$deal['status']] ?? $deal['status'];
                    ?>
                        <div style="padding: 12px 0; border-bottom: 1px solid var(--akpp-f-border); font-size: 14px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <strong>Заявка #<?php echo esc_html($deal['id']); ?></strong>
                                <span style="font-size: 12px; color: var(--akpp-f-text-muted);">
                                    <?php echo date('d.m.Y', strtotime($deal['created_at'])); ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <?php echo $badge; ?>
                                <span style="font-weight: 600; color: var(--akpp-f-accent);">
                                    <?php echo number_format($deal['total_amount'], 0, '.', ' '); ?> ₽
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div style="text-align: center; padding: 30px 10px; color: var(--akpp-f-text-muted);">
                    <p>У вас пока нет активных обращений.</p>
                    <a href="<?php echo esc_url(home_url('/contact/')); ?>" class="akpp-btn" style="margin-top: 15px; font-size: 14px; padding: 10px;">
                        Оставить новую заявку
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Скрытые поля для передачи конфигурации в JS (fallback) -->
<script>
    if (typeof akppCRM === 'undefined') {
        var akppCRM = {
            ajax_url: '<?php echo esc_js($ajax_url); ?>',
            nonce: '<?php echo esc_js($nonce); ?>'
        };
    }
    var isUserLoggedIn = true; // Для инициализации Push-уведомлений
</script>
