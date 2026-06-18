<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Получаем список пользователей с JOIN на таблицу WP пользователей
$users = $wpdb->get_results("
    SELECT su.*, u.user_email, u.user_registered 
    FROM {$wpdb->prefix}akpp_site_users su
    LEFT JOIN {$wpdb->users} u ON su.wp_user_id = u.ID
    ORDER BY su.id DESC
", ARRAY_A);

?>

<div class="wrap akpp-crm-wrap">
    <h1 style="color: var(--akpp-accent); margin-bottom: 20px;">👤 Пользователи сайта (Клиенты)</h1>

    <div class="akpp-card" style="padding: 0; overflow: hidden;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>ФИО</th>
                    <th>Email</th>
                    <th>Телефон</th>
                    <th>Автомобиль</th>
                    <th>Дата регистрации</th>
                    <th style="width: 200px;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)) : ?>
                    <?php foreach ($users as $user) : ?>
                        <tr id="user-row-<?php echo esc_attr($user['id']); ?>">
                            <td>#<?php echo esc_html($user['id']); ?></td>
                            <td>
                                <strong><?php echo esc_html($user['full_name'] ?: 'Не указано'); ?></strong>
                            </td>
                            <td>
                                <?php if (!empty($user['user_email'])): ?>
                                    <a href="mailto:<?php echo esc_attr($user['user_email']); ?>" style="color: var(--akpp-accent); text-decoration: none;">
                                        <?php echo esc_html($user['user_email']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($user['phone'] ?: '—'); ?></td>
                            <td><?php echo esc_html($user['car_info'] ?: '—'); ?></td>
                            <td class="akpp-text-muted">
                                <?php 
                                if (!empty($user['user_registered'])) {
                                    echo date('d.m.Y H:i', strtotime($user['user_registered']));
                                } else {
                                    echo date('d.m.Y H:i', strtotime($user['registered_at']));
                                }
                                ?>
                            </td>
                            <td>
                                <?php if (!empty($user['wp_user_id'])): ?>
                                    <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $user['wp_user_id'])); ?>" class="button button-small" style="margin-right: 5px;">
                                        Профиль WP
                                    </a>
                                    <button type="button" class="button button-small button-link-delete akpp-delete-user" data-user-id="<?php echo esc_attr($user['wp_user_id']); ?>" data-row-id="<?php echo esc_attr($user['id']); ?>">
                                        Удалить
                                    </button>
                                <?php else: ?>
                                    <span style="color: #999;">Нет WP аккаунта</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--akpp-text-secondary);">
                            Зарегистрированных клиентов пока нет.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Обработка удаления пользователя
    $('.akpp-delete-user').on('click', function() {
        if (!confirm('⚠️ Вы уверены, что хотите удалить этого пользователя и все связанные данные? Это действие нельзя отменить.')) {
            return;
        }

        const $btn = $(this);
        const userId = $btn.data('user-id');
        const rowId = $btn.data('row-id');

        $btn.prop('disabled', true).text('Удаление...');

        $.post(ajaxurl, {
            action: 'akpp_delete_site_user',
            nonce: akpp_ajax.nonce,
            user_id: userId,
            row_id: rowId
        }, function(response) {
            if (response.success) {
                $(`#user-row-${rowId}`).fadeOut(300, function() {
                    $(this).remove();
                });
            } else {
                alert(response.data.message || 'Ошибка при удалении пользователя');
                $btn.prop('disabled', false).text('Удалить');
            }
        }).fail(function() {
            alert('Ошибка сети при удалении пользователя');
            $btn.prop('disabled', false).text('Удалить');
        });
    });
});
</script>