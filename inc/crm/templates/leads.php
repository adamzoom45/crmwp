<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'akpp_leads';

// ====================================================================
// ОБРАБОТКА ДЕЙСТВИЙ
// ====================================================================

// 1. Удаление лида
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_lead_' . $id)) {
        echo '<div class="notice notice-error is-dismissible"><p>❌ Ошибка безопасности (nonce). Попробуйте ещё раз.</p></div>';
    } else {
        $result = $wpdb->delete($table_name, ['id' => $id]);
        
        if ($result !== false) {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Лид #' . $id . ' успешно удалён</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>❌ Ошибка удаления лида</p></div>';
        }
    }
}

// 2. Сохранение отредактированного лида (POST)
if (isset($_POST['save_lead']) && isset($_POST['lead_id'])) {
    $lead_id = intval($_POST['lead_id']);
    
    $update_data = [
        'client_name'  => sanitize_text_field($_POST['client_name'] ?? ''),
        'client_phone' => sanitize_text_field($_POST['client_phone'] ?? ''),
        'car_brand'    => sanitize_text_field($_POST['car_brand'] ?? ''),
        'problem'      => sanitize_textarea_field($_POST['problem'] ?? ''),
        'status'       => sanitize_text_field($_POST['status'] ?? 'new'),
        'updated_at'   => current_time('mysql'),
    ];
    
    $result = $wpdb->update($table_name, $update_data, ['id' => $lead_id]);
    
    if ($result !== false) {
        echo '<div class="notice notice-success is-dismissible"><p>✅ Лид #' . $lead_id . ' успешно обновлён</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>❌ Ошибка обновления лида</p></div>';
    }
}

// 3. Редактирование лида (GET с action=edit)
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $lead = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $id
    ), ARRAY_A);
    
    if (!$lead) {
        echo '<div class="notice notice-error is-dismissible"><p>❌ Лид не найден</p></div>';
    } else {
        $statuses = [
            'new'        => '🆕 Новый',
            'contacted'  => '📞 Связались',
            'diagnostic' => '🔍 Диагностика',
            'in_work'    => '🔧 В работе',
            'completed'  => '✅ Выполнено',
            'converted'  => '💰 Конвертирован',
            'cancelled'  => '❌ Отменено',
            'lost'       => '❌ Потерян',
        ];
        ?>
        <div class="wrap akpp-crm-wrap">
            <h1 style="color: #00ff88; border-left: 4px solid #00ff88; padding-left: 15px;">
                ✏️ Редактирование лида #<?php echo intval($lead['id']); ?>
            </h1>
            
            <div class="akpp-card" style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 24px; max-width: 800px;">
                <form method="post" action="">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;color:#a0aec0;text-transform:uppercase;font-size:13px;">
                            ФИО Клиента <span style="color:#fc8181;">*</span>
                        </label>
                        <input type="text" name="client_name"
                               value="<?php echo esc_attr($lead['client_name']); ?>"
                               required
                               style="width:100%;padding:12px 16px;background:#2d3748;border:2px solid #4a5568;border-radius:8px;color:#fff;font-size:14px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;color:#a0aec0;text-transform:uppercase;font-size:13px;">
                            Телефон <span style="color:#fc8181;">*</span>
                        </label>
                        <input type="tel" name="client_phone"
                               value="<?php echo esc_attr($lead['client_phone']); ?>"
                               required
                               style="width:100%;padding:12px 16px;background:#2d3748;border:2px solid #4a5568;border-radius:8px;color:#fff;font-size:14px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;color:#a0aec0;text-transform:uppercase;font-size:13px;">
                            Автомобиль
                        </label>
                        <input type="text" name="car_brand"
                               value="<?php echo esc_attr($lead['car_brand']); ?>"
                               placeholder="Toyota Camry 2020"
                               style="width:100%;padding:12px 16px;background:#2d3748;border:2px solid #4a5568;border-radius:8px;color:#fff;font-size:14px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;color:#a0aec0;text-transform:uppercase;font-size:13px;">
                            Проблема
                        </label>
                        <textarea name="problem" rows="5"
                                  style="width:100%;padding:12px 16px;background:#2d3748;border:2px solid #4a5568;border-radius:8px;color:#fff;font-size:14px;resize:vertical;"><?php echo esc_textarea($lead['problem']); ?></textarea>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;color:#a0aec0;text-transform:uppercase;font-size:13px;">
                            Статус
                        </label>
                        <select name="status"
                                style="width:100%;padding:12px 16px;background:#2d3748;border:2px solid #4a5568;border-radius:8px;color:#fff;font-size:14px;">
                            <?php foreach ($statuses as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>"
                                    <?php selected($lead['status'], $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 20px; border-top: 1px solid #2d3748;">
                        <a href="?page=akpp-crm-leads"
                           class="button"
                           style="background:transparent;color:#a0aec0;border:2px solid #4a5568;padding:12px 24px;border-radius:8px;font-weight:600;text-decoration:none;">
                            Отмена
                        </a>
                        <button type="submit" name="save_lead" value="1"
                                style="background:linear-gradient(135deg,#00ff88 0%,#00cc6a 100%);color:#1a1f2e;border:none;padding:12px 24px;border-radius:8px;font-weight:600;cursor:pointer;box-shadow:0 4px 12px rgba(0,255,136,0.3);">
                            💾 Сохранить изменения
                        </button>
                    </div>
                    
                    <input type="hidden" name="lead_id" value="<?php echo intval($lead['id']); ?>">
                </form>
            </div>
        </div>
        <?php
        return;
    }
}

// ====================================================================
// ОТОБРАЖЕНИЕ ТАБЛИЦЫ ЛИДОВ
// ====================================================================

if (!class_exists('AKPP_Leads_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-leads-table.php';
}

$employees = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}akpp_employees WHERE is_active = 1 ORDER BY name ASC",
    ARRAY_A
);

$leads_table = new AKPP_Leads_Table();
$leads_table->prepare_items();
?>

<div class="wrap akpp-crm-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="color: var(--akpp-accent); margin: 0;">📨 Входящие заявки (Лиды)</h1>
        <button type="button" class="button button-primary akpp-open-modal" data-target="#akpp-lead-modal"
                style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600;">
            ➕ Добавить лид вручную
        </button>
    </div>

    <?php if (isset($_GET['updated']) && intval($_GET['updated']) > 0) : ?>
        <div class="notice notice-success is-dismissible" style="border-left-color: var(--akpp-success);">
            <p>Успешно обновлено записей: <strong><?php echo esc_html($_GET['updated']); ?></strong></p>
        </div>
    <?php endif; ?>

    <!-- Таблица лидов с массовыми действиями -->
    <form method="post">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
        <?php wp_nonce_field('bulk-leads'); ?>
        <?php
        $leads_table->search_box('Поиск по имени, телефону или сообщению', 'lead_search');
        $leads_table->display();
        ?>
    </form>
</div>

<!-- Модальное окно добавления лида вручную -->
<div id="akpp-lead-modal" class="akpp-modal">
    <div class="akpp-modal-content">
        <span class="akpp-modal-close">&times;</span>
        <h2 style="margin-top: 0; color: var(--akpp-accent);">Добавить лид вручную (например, звонок)</h2>
        
        <form class="akpp-ajax-form" data-action="akpp_save_lead">
            <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label for="lead_source">Источник *</label>
                <select id="lead_source" name="source" required style="width: 100%;">
                    <option value="call">📞 Звонок</option>
                    <option value="site">🌐 Сайт</option>
                    <option value="avito">🟢 Авито</option>
                    <option value="telegram">🔵 Telegram</option>
                    <option value="whatsapp">💬 WhatsApp</option>
                </select>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label for="lead_name">Имя клиента</label>
                    <input type="text" id="lead_name" name="client_name" style="width: 100%;" placeholder="Неизвестно">
                </div>
                <div class="form-group">
                    <label for="lead_phone">Телефон *</label>
                    <input type="tel" id="lead_phone" name="client_phone" required style="width: 100%;" placeholder="+7 (___) ___-__-__">
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label for="lead_email">Email</label>
                <input type="email" id="lead_email" name="client_email" style="width: 100%;" placeholder="email@example.com">
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label for="lead_car">Марка автомобиля</label>
                <input type="text" id="lead_car" name="car_brand" style="width: 100%;" placeholder="Toyota Camry">
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label for="lead_message">Сообщение / Описание проблемы</label>
                <textarea id="lead_message" name="problem" rows="4" style="width: 100%;" placeholder="Краткое описание обращения"></textarea>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="lead_assigned">Назначить сотрудника</label>
                <select id="lead_assigned" name="guide_id" style="width: 100%;">
                    <option value="0">Не назначен</option>
                    <?php foreach ($employees as $emp) : ?>
                        <option value="<?php echo esc_attr($emp['id']); ?>">
                            <?php echo esc_html($emp['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="text-align: right;">
                <button type="button" class="button akpp-modal-close" style="margin-right: 10px;">Отмена</button>
                <button type="submit" class="button button-primary"
                        style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600;">
                    💾 Сохранить лид
                </button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // ========================================================================
    // ❗ ВАЖНО: Обработчик submit УЖЕ ЕСТЬ в admin.js!
    // Здесь НЕ добавляем свой, чтобы не было дублей!
    // ========================================================================
    
    // Callback для успешного сохранения (вызывается из admin.js)
    window.akppFormSuccess = function(data, $form) {
        $('#akpp-lead-modal').removeClass('active').fadeOut(200);
        $form[0].reset();
        
        // Уведомление
        var notice = $('<div style="position:fixed;top:20px;right:20px;background:#00ff88;color:#0a0f1c;padding:16px 24px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:99999;font-weight:600;">✅ ' + (data.message || 'Лид создан') + '</div>');
        $('body').append(notice);
        setTimeout(function() { 
            notice.fadeOut(300, function() { $(this).remove(); }); 
        }, 3000);
        
        // Перезагрузка через 1 сек
        setTimeout(function() {
            window.location.href = '<?php echo esc_url(admin_url('admin.php?page=akpp-crm-leads')); ?>';
        }, 1000);
    };
    
    // ========================================================================
    // ОТКРЫТИЕ/ЗАКРЫТИЕ МОДАЛЬНОГО ОКНА
    // ========================================================================
    $(document).on('click', '.akpp-open-modal', function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        if (target) {
            $(target).addClass('active').fadeIn(200);
        }
    });
    
    $(document).on('click', '.akpp-modal-close', function() {
        $(this).closest('.akpp-modal').removeClass('active').fadeOut(200);
    });
    
    $(document).on('click', '.akpp-modal', function(e) {
        if ($(e.target).hasClass('akpp-modal')) {
            $(this).removeClass('active').fadeOut(200);
        }
    });
    
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.akpp-modal.active').removeClass('active').fadeOut(200);
        }
    });
    
    // ========================================================================
    // АВТОСКРЫТИЕ УВЕДОМЛЕНИЙ
    // ========================================================================
    setTimeout(function() {
        $('.notice.is-dismissible').fadeOut(500, function() {
            $(this).remove();
        });
    }, 5000);
    
    // ========================================================================
    // МАСКА ДЛЯ ТЕЛЕФОНА
    // ========================================================================
    $('#lead_phone').on('input', function() {
        var value = $(this).val().replace(/\D/g, '');
        if (value.length > 0) {
            if (value[0] === '7' || value[0] === '8') {
                value = value.substring(1);
            }
            var formatted = '+7';
            if (value.length > 0) {
                formatted += ' (' + value.substring(0, 3);
            }
            if (value.length >= 3) {
                formatted += ') ' + value.substring(3, 6);
            }
            if (value.length >= 6) {
                formatted += '-' + value.substring(6, 8);
            }
            if (value.length >= 8) {
                formatted += '-' + value.substring(8, 10);
            }
            $(this).val(formatted);
        }
    });
});
</script>

<style>
.akpp-modal {
    display: none;
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
}

.akpp-modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.akpp-modal-content {
    background: #1a1f2e;
    border: 1px solid #2d3748;
    border-radius: 12px;
    padding: 30px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
}

.akpp-modal-close {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 28px;
    font-weight: bold;
    color: #a0aec0;
    cursor: pointer;
    transition: color 0.3s;
}

.akpp-modal-close:hover {
    color: #00ff88;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: #00ff88;
    font-weight: 600;
    font-size: 14px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    background: #2d3748;
    border: 1px solid #4a5568;
    border-radius: 4px;
    color: #fff;
    box-sizing: border-box;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #00ff88;
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: #718096;
}
</style>