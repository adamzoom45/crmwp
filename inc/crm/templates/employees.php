<?php
if (!defined('ABSPATH')) exit;

// Подключаем класс таблицы, если он еще не загружен
if (!class_exists('AKPP_Employees_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-employees-table.php';
}

global $wpdb;

// Проверяем, находимся ли мы в режиме редактирования
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

// Инициализируем и подготавливаем таблицу
$employees_table = new AKPP_Employees_Table();
$employees_table->prepare_items();

?>

<div class="wrap akpp-crm-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="color: var(--akpp-accent); margin: 0;">👥 Сотрудники</h1>
        <button type="button" class="button button-primary akpp-open-modal" data-target="#akpp-employee-modal" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600;">
            + Добавить сотрудника
        </button>
    </div>

    <!-- Вывод таблицы сотрудников -->
    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
        <?php 
        $employees_table->search_box('Поиск сотрудников', 'employee_search'); 
        $employees_table->display(); 
        ?>
    </form>
</div>

<!-- Модальное окно добавления/редактирования сотрудника -->
<div id="akpp-employee-modal" class="akpp-modal">
    <div class="akpp-modal-content">
        <span class="akpp-modal-close">&times;</span>
        <h2 style="margin-top: 0; color: var(--akpp-accent);">
            <?php echo $action === 'edit' ? 'Редактировать сотрудника' : 'Новый сотрудник'; ?>
        </h2>
        
        <form class="akpp-ajax-form" data-action="akpp_save_employee">
            <?php wp_nonce_field('akpp_crm_nonce', 'nonce'); ?>
            
            <?php if ($action === 'edit' && $employee_data) : ?>
                <input type="hidden" name="id" value="<?php echo esc_attr($employee_data['id']); ?>">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="emp_full_name">ФИО *</label>
                <input type="text" id="emp_full_name" name="full_name" value="<?php echo esc_attr($employee_data['full_name'] ?? ''); ?>" required style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="emp_role">Должность *</label>
                <select id="emp_role" name="role" required style="width: 100%;">
                    <option value="manager" <?php selected($employee_data['role'] ?? '', 'manager'); ?>>Менеджер</option>
                    <option value="mechanic" <?php selected($employee_data['role'] ?? '', 'mechanic'); ?>>Механик</option>
                    <option value="admin" <?php selected($employee_data['role'] ?? '', 'admin'); ?>>Администратор</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="emp_phone">Телефон</label>
                <input type="tel" id="emp_phone" name="phone" value="<?php echo esc_attr($employee_data['phone'] ?? ''); ?>" style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="emp_status">Статус</label>
                <select id="emp_status" name="status" style="width: 100%;">
                    <option value="active" <?php selected($employee_data['status'] ?? 'active', 'active'); ?>>Активен</option>
                    <option value="inactive" <?php selected($employee_data['status'] ?? 'active', 'inactive'); ?>>Неактивен</option>
                </select>
            </div>

            <div style="text-align: right;">
                <button type="button" class="button akpp-modal-close" style="margin-right: 10px;">Отмена</button>
                <button type="submit" class="button button-primary" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600;">
                    <?php echo $action === 'edit' ? 'Сохранить изменения' : 'Добавить сотрудника'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Если мы в режиме редактирования, открываем модальное окно автоматически
    <?php if ($action === 'edit' && $employee_data) : ?>
        $('#akpp-employee-modal').fadeIn(200);
    <?php endif; ?>

    // После успешного сохранения закрываем модальное окно и перезагружаем страницу
    window.akppFormSuccess = function(data, $form) {
        $('#akpp-employee-modal').fadeOut(200);
        // Сброс формы
        $form[0].reset();
        // Перезагрузка для обновления таблицы
        setTimeout(() => {
            window.location.href = '<?php echo esc_url(admin_url('admin.php?page=akpp-employees')); ?>';
        }, 500);
    };
});
</script>
