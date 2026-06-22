<?php
/**
 * Шаблон списка сотрудников + редактирование
 */
if (!defined('ABSPATH')) exit;

global $wpdb;

// ============================================================================
// ОБРАБОТКА ДЕЙСТВИЙ
// ============================================================================
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$employee_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$employee_data = null;

// Загрузка данных для редактирования
if ($action === 'edit' && $employee_id > 0) {
    $employee_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}akpp_employees WHERE id = %d",
        $employee_id
    ), ARRAY_A);
    
    if (!$employee_data) {
        echo '<div class="notice notice-error is-dismissible"><p>❌ Сотрудник не найден</p></div>';
        $action = '';
    }
}

// Активация/деактивация
if (in_array($action, ['activate', 'deactivate']) && $employee_id > 0) {
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'toggle_employee_' . $employee_id)) {
        echo '<div class="notice notice-error is-dismissible"><p>❌ Ошибка безопасности</p></div>';
    } else {
        $is_active = ($action === 'activate') ? 1 : 0;
        $result = $wpdb->update(
            $wpdb->prefix . 'akpp_employees',
            ['is_active' => $is_active],
            ['id' => $employee_id]
        );
        if ($result !== false) {
            $msg = ($action === 'activate') ? '✅ Сотрудник активирован' : '❌ Сотрудник деактивирован';
            echo '<div class="notice notice-success is-dismissible"><p>' . $msg . '</p></div>';
        }
        $action = '';
    }
}

// Удаление
if ($action === 'delete' && $employee_id > 0) {
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_employee_' . $employee_id)) {
        echo '<div class="notice notice-error is-dismissible"><p>❌ Ошибка безопасности</p></div>';
    } else {
        $result = $wpdb->delete($wpdb->prefix . 'akpp_employees', ['id' => $employee_id]);
        if ($result !== false) {
            echo '<div class="notice notice-success is-dismissible"><p>🗑️ Сотрудник #' . $employee_id . ' удалён</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>❌ Ошибка удаления</p></div>';
        }
        $action = '';
    }
}

// ============================================================================
// РЕЖИМ РЕДАКТИРОВАНИЯ / ДОБАВЛЕНИЯ
// ============================================================================
if ($action === 'edit' || $action === 'add') :
    $title = ($action === 'edit') ? '✏️ Редактирование сотрудника #' . $employee_id : '➕ Новый сотрудник';
    ?>
    <div class="wrap akpp-crm-wrap">
        <h1 style="color: #00ff88; border-left: 4px solid #00ff88; padding-left: 15px; margin-bottom: 30px;">
            <?php echo $title; ?>
        </h1>
        
        <div class="akpp-card" style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 30px; max-width: 700px;">
            <form id="akpp-employee-form">
                <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
                <?php if ($action === 'edit') : ?>
                    <input type="hidden" name="id" value="<?php echo esc_attr($employee_id); ?>">
                <?php endif; ?>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display:block;color:#a0aec0;margin-bottom:8px;font-weight:600;">ФИО *</label>
                    <input type="text" name="full_name" value="<?php echo esc_attr($employee_data['name'] ?? ''); ?>" required 
                           style="width:100%;padding:10px;background:#0a0f1c;border:1px solid #2d3748;border-radius:6px;color:#fff;">
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display:block;color:#a0aec0;margin-bottom:8px;font-weight:600;">Должность</label>
                    <select name="role" style="width:100%;padding:10px;background:#0a0f1c;border:1px solid #2d3748;border-radius:6px;color:#fff;">
                        <?php 
                        $current_role = $employee_data['role'] ?? 'mechanic';
                        $roles = [
                            'mechanic' => '🔧 Механик',
                            'manager'  => '💼 Менеджер',
                            'admin'    => '⚙️ Админ',
                            'director' => '👑 Директор',
                        ];
                        foreach ($roles as $value => $label) : 
                        ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($current_role, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display:block;color:#a0aec0;margin-bottom:8px;font-weight:600;">Телефон</label>
                    <input type="tel" name="phone" value="<?php echo esc_attr($employee_data['phone'] ?? ''); ?>" 
                           style="width:100%;padding:10px;background:#0a0f1c;border:1px solid #2d3748;border-radius:6px;color:#fff;"
                           placeholder="+7 (999) 123-45-67">
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display:block;color:#a0aec0;margin-bottom:8px;font-weight:600;">Email</label>
                    <input type="email" name="email" value="<?php echo esc_attr($employee_data['email'] ?? ''); ?>" 
                           style="width:100%;padding:10px;background:#0a0f1c;border:1px solid #2d3748;border-radius:6px;color:#fff;">
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display:block;color:#a0aec0;margin-bottom:8px;font-weight:600;">Статус</label>
                    <select name="status" style="width:100%;padding:10px;background:#0a0f1c;border:1px solid #2d3748;border-radius:6px;color:#fff;">
                        <?php 
                        $current_status = ($employee_data['is_active'] ?? 1) ? 'active' : 'inactive';
                        ?>
                        <option value="active" <?php selected($current_status, 'active'); ?>>✅ Активен</option>
                        <option value="inactive" <?php selected($current_status, 'inactive'); ?>>❌ Неактивен</option>
                    </select>
                </div>
                
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:30px;">
                    <a href="?page=akpp-crm-employees" class="button button-secondary">Отмена</a>
                    <button type="submit" class="button button-primary" 
                            style="background:#00ff88;border-color:#00ff88;color:#0a0f1c;font-weight:600;padding:10px 30px;">
                        💾 Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var isSubmitting = false;
        
        $('#akpp-employee-form').off('submit').on('submit', function(e) {
            e.preventDefault();
            if (isSubmitting) return false;
            isSubmitting = true;
            
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]');
            var originalText = $btn.html();
            $btn.prop('disabled', true).html('⏳ Сохранение...');
            
            var formData = $form.serializeArray();
            formData.push({name: 'action', value: 'akpp_save_employee'});
            
            $.ajax({
                url: '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message || '✅ Сохранено', 'success');
                        setTimeout(function() {
                            window.location.href = '<?php echo esc_url(admin_url('admin.php?page=akpp-crm-employees')); ?>';
                        }, 1000);
                    } else {
                        showNotice(response.data.message || '❌ Ошибка', 'error');
                        $btn.prop('disabled', false).html(originalText);
                        isSubmitting = false;
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ошибка:', xhr.responseText);
                    showNotice('❌ Ошибка соединения', 'error');
                    $btn.prop('disabled', false).html(originalText);
                    isSubmitting = false;
                }
            });
            
            return false;
        });
        
        function showNotice(message, type) {
            var bgColor = type === 'success' ? '#00ff88' : '#fc8181';
            var textColor = type === 'success' ? '#0a0f1c' : '#fff';
            var $notice = $('<div style="position:fixed;top:20px;right:20px;background:' + bgColor + ';color:' + textColor + ';padding:16px 24px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:99999;font-weight:600;">' + message + '</div>');
            $('body').append($notice);
            setTimeout(function() { $notice.fadeOut(300, function() { $(this).remove(); }); }, 3000);
        }
    });
    </script>
    <?php
    return;
endif;

// ============================================================================
// ОТОБРАЖЕНИЕ ТАБЛИЦЫ
// ============================================================================
if (!class_exists('AKPP_Employees_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-employees-table.php';
}

$table = new AKPP_Employees_Table();
$table->prepare_items();
?>

<div class="wrap akpp-crm-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1 style="color: #00ff88; border-left: 4px solid #00ff88; padding-left: 15px; margin: 0;">
            👥 Сотрудники
        </h1>
        <a href="?page=akpp-crm-employees&action=add" 
           class="button" 
           style="background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%); color: #1a1f2e; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none;">
            ➕ Добавить сотрудника
        </a>
    </div>

    <div class="akpp-card" style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 20px;">
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
            <?php $table->search_box('Поиск по ФИО, телефону, email', 'employee_search'); ?>
            <?php $table->display(); ?>
        </form>
    </div>
</div>