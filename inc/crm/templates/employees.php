<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('AKPP_Employees_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-employees-table.php';
}

global $wpdb;

$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$employee_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$employee_data = null;

if ($action === 'edit' && $employee_id > 0) {
    $table_name = $wpdb->prefix . 'akpp_employees';
    $employee_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $employee_id), ARRAY_A);
    
    if (!$employee_data) {
        echo '<div class="notice notice-error"><p>Сотрудник не найден.</p></div>';
        return;
    }
}

$employees_table = new AKPP_Employees_Table();
$employees_table->prepare_items();
?>

<div class="wrap akpp-crm-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="color: #00ff88; margin: 0; border-left: 4px solid #00ff88; padding-left: 15px;">👥 Сотрудники</h1>
        <button type="button" class="button button-primary akpp-open-modal" data-target="#akpp-employee-modal" style="background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%); color: #1a1f2e; border: none; padding: 12px 24px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,255,136,0.3); border-radius: 8px;">
            + Добавить сотрудника
        </button>
    </div>

    <div class="akpp-card" style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 20px;">
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
            <?php 
            $employees_table->search_box('Поиск сотрудников', 'employee_search'); 
            $employees_table->display(); 
            ?>
        </form>
    </div>
</div>

<!-- Модальное окно -->
<div id="akpp-employee-modal" class="akpp-modal">
    <div class="akpp-modal-content">
        <div class="akpp-modal-header">
            <h2 style="margin: 0; color: #00ff88; font-size: 20px;">
                <?php echo $action === 'edit' ? 'Редактировать сотрудника' : 'Новый сотрудник'; ?>
            </h2>
            <button class="akpp-modal-close">&times;</button>
        </div>
        
        <div class="akpp-modal-body">
            <form class="akpp-ajax-form" data-action="akpp_save_employee">
                <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
                
                <?php if ($action === 'edit' && $employee_data) : ?>
                    <input type="hidden" name="id" value="<?php echo esc_attr($employee_data['id']); ?>">
                <?php endif; ?>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #a0aec0; text-transform: uppercase; font-size: 13px;">ФИО <span>*</span></label>
                    <input type="text" name="full_name" value="<?php echo esc_attr($employee_data['name'] ?? ''); ?>" required style="width: 100%; padding: 12px 16px; background: #2d3748; border: 2px solid #4a5568; border-radius: 8px; color: #fff; font-size: 14px;">
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #a0aec0; text-transform: uppercase; font-size: 13px;">Должность <span>*</span></label>
                    <select name="role" required style="width: 100%; padding: 12px 16px; background: #2d3748; border: 2px solid #4a5568; border-radius: 8px; color: #fff; font-size: 14px;">
                        <option value="manager" <?php selected($employee_data['role'] ?? '', 'manager'); ?>>Менеджер</option>
                        <option value="mechanic" <?php selected($employee_data['role'] ?? '', 'mechanic'); ?>>Механик</option>
                        <option value="admin" <?php selected($employee_data['role'] ?? '', 'admin'); ?>>Администратор</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #a0aec0; text-transform: uppercase; font-size: 13px;">Телефон</label>
                    <input type="tel" name="phone" value="<?php echo esc_attr($employee_data['phone'] ?? ''); ?>" style="width: 100%; padding: 12px 16px; background: #2d3748; border: 2px solid #4a5568; border-radius: 8px; color: #fff; font-size: 14px;">
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #a0aec0; text-transform: uppercase; font-size: 13px;">Статус</label>
                    <select name="status" style="width: 100%; padding: 12px 16px; background: #2d3748; border: 2px solid #4a5568; border-radius: 8px; color: #fff; font-size: 14px;">
                        <option value="active" <?php selected($employee_data['is_active'] ?? 1, 1); ?>>Активен</option>
                        <option value="inactive" <?php selected($employee_data['is_active'] ?? 1, 0); ?>>Неактивен</option>
                    </select>
                </div>

                <div class="akpp-modal-footer" style="padding: 20px 0 0 0; border-top: 1px solid #2d3748; display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                    <button type="button" class="button akpp-modal-cancel" style="background: transparent; color: #a0aec0; border: 2px solid #4a5568; padding: 12px 24px; border-radius: 8px; font-weight: 600;">Отмена</button>
                    <button type="submit" class="button button-primary" style="background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%); color: #1a1f2e; border: none; padding: 12px 24px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,255,136,0.3); border-radius: 8px;">
                        <?php echo $action === 'edit' ? '💾 Сохранить' : '➕ Добавить'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    <?php if ($action === 'edit' && $employee_data) : ?>
        $('#akpp-employee-modal').css({
            'display': 'flex',
            'opacity': '1',
            'visibility': 'visible'
        }).addClass('active');
    <?php endif; ?>

    window.akppFormSuccess = function(data, $form) {
        $('#akpp-employee-modal').css({
            'display': 'none',
            'opacity': '0',
            'visibility': 'hidden'
        }).removeClass('active');
        $form[0].reset();
        setTimeout(() => {
            window.location.href = '<?php echo esc_url(admin_url('admin.php?page=akpp-crm-employees')); ?>';
        }, 500);
    };
});
</script>