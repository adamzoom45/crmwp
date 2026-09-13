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

<div id="akpp-lead-chat-modal" class="akpp-modal">
    <div class="akpp-modal-content" style="max-width:700px;display:flex;flex-direction:column;height:80vh">
        <div class="akpp-modal-header">
            <div>
                <h3 style="margin:0">💬 Переписка с клиентом</h3>
                <p id="akpp-lead-chat-title" style="margin:4px 0 0 0;font-size:13px;color:#a0aec0"></p>
            </div>
            <button type="button" class="akpp-modal-close">&times;</button>
        </div>
        <div id="akpp-lead-chat-status" style="padding:10px 16px;background:#2d3748;border-bottom:1px solid #4a5568;font-size:13px"></div>
        <div id="akpp-lead-chat-messages" style="flex:1;overflow-y:auto;padding:20px;background:#0f1419"></div>
        <form id="akpp-lead-chat-form" style="display:flex;gap:12px;padding:16px;background:#2d3748">
            <input type="text" id="akpp-lead-chat-message" placeholder="Напишите сообщение клиенту..." style="flex:1;padding:12px 16px;background:#1a1f2e;border:1px solid #4a5568;border-radius:8px;color:#fff">
            <button type="submit" class="button button-primary">📤</button>
        </form>
        <div style="padding:12px 16px;background:#1a1f2e;border-top:1px solid #4a5568;display:flex;justify-content:space-between;align-items:center;gap:12px">
            <span id="akpp-lead-convert-hint" style="font-size:12px;color:#a0aec0"></span>
            <button type="button" id="akpp-lead-convert-btn" class="button button-primary" style="background:#00ff88;border-color:#00ff88;color:#0a0f1c;font-weight:600;white-space:nowrap">🚀 Создать сделку</button>
        </div>
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
    // === Чат менеджера с клиентом (Этап 6b-2) ===
    var leadChatModal = $('#akpp-lead-chat-modal');
    var leadChatMessages = $('#akpp-lead-chat-messages');
    var leadChatForm = $('#akpp-lead-chat-form');
    var leadChatInput = $('#akpp-lead-chat-message');
    var currentChatLeadId = null;
    var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var chatNonce = '<?php echo wp_create_nonce('akpp_lead_chat_nonce'); ?>';
    var adminNonce = '<?php echo wp_create_nonce('akpp45_nonce'); ?>';

    function loadManagerChat() {
        if (!currentChatLeadId) return;
        $.post(ajaxUrl, { action: 'akpp_get_lead_messages', lead_id: currentChatLeadId, nonce: chatNonce }, function(r) {
            if (r.success && r.data.messages) {
                leadChatMessages.html('');
                if (r.data.messages.length === 0) {
                    leadChatMessages.html('<div style="text-align:center;color:#718096;padding:40px">💬 Переписка пуста</div>');
                } else {
                    r.data.messages.forEach(function(msg) {
                        var isMgr = msg.sender_type === 'manager';
                        leadChatMessages.append(
                            '<div style="margin-bottom:12px;display:flex;justify-content:' + (isMgr ? 'flex-end' : 'flex-start') + '">' +
                            '<div style="max-width:70%;padding:10px 14px;border-radius:12px;background:' + (isMgr ? '#00ff88' : '#2d3748') + ';color:' + (isMgr ? '#1a1f2e' : '#fff') + '">' +
                            '<div style="font-size:11px;opacity:0.7">' + (isMgr ? 'Вы (менеджер)' : 'Клиент') + ' · ' + msg.created_at + '</div>' +
                            '<div>' + msg.message + '</div></div></div>'
                        );
                    });
                    leadChatMessages.scrollTop(leadChatMessages[0].scrollHeight);
                }
            }
        });
    }

    function loadConvertStatus() {
        if (!currentChatLeadId) return;
        $.post(ajaxUrl, { action: 'akpp_get_lead_agree_status_admin', lead_id: currentChatLeadId, nonce: adminNonce }, function(r) {
            if (r.success) {
                var $btn = $('#akpp-lead-convert-btn'), $hint = $('#akpp-lead-convert-hint'), $status = $('#akpp-lead-chat-status');
                if (r.data.deal_id > 0) {
                    $btn.prop('disabled', true).css({opacity:0.5}).text('💰 Сделка #' + r.data.deal_id);
                    $hint.text('Лид уже конвертирован в сделку');
                    $status.html('💰 <strong style="color:#00ff88">Конвертирован в сделку #' + r.data.deal_id + '</strong>');
                } else if (r.data.client_agreed === 1) {
                    $btn.prop('disabled', false).css({opacity:1}).text('🚀 Создать сделку');
                    $hint.html('✅ <strong style="color:#00ff88">Клиент согласен</strong> ' + (r.data.agreed_at || ''));
                    $status.html('✅ <strong style="color:#00ff88">Клиент согласовал условия</strong> ' + (r.data.agreed_at || ''));
                } else {
                    $btn.prop('disabled', true).css({opacity:0.5}).text('🚀 Создать сделку');
                    $hint.text('⏳ Клиент ещё не согласовал условия — конвертация заблокирована');
                    $status.html('⏳ <span style="color:#a0aec0">Ожидает согласования клиента</span>');
                }
            }
        });
    }

    $(document).on('click', '.akpp-open-lead-chat', function() {
        currentChatLeadId = $(this).data('lead-id');
        $('#akpp-lead-chat-title').text($(this).data('lead-title'));
        leadChatModal.addClass('active').fadeIn(200);
        loadManagerChat();
        loadConvertStatus();
    });

    leadChatForm.on('submit', function(e) {
        e.preventDefault();
        var message = leadChatInput.val().trim();
        if (!message || !currentChatLeadId) return;
        var $btn = $(this).find('button[type="submit"]').prop('disabled', true);
        $.post(ajaxUrl, { action: 'akpp_send_lead_message', lead_id: currentChatLeadId, message: message, nonce: chatNonce }, function(r) {
            if (r.success) { leadChatInput.val(''); loadManagerChat(); }
            else { alert(r.data.message || 'Ошибка'); }
            $btn.prop('disabled', false);
        }).fail(function() { alert('Ошибка соединения'); $btn.prop('disabled', false); });
    });

    $('#akpp-lead-convert-btn').on('click', function() {
        if (!currentChatLeadId || $(this).prop('disabled')) return;
        if (!confirm('Создать сделку из этого лида?')) return;
        var $btn = $(this).prop('disabled', true);
        $.post(ajaxUrl, { action: 'akpp_convert_lead', lead_id: currentChatLeadId, nonce: adminNonce }, function(r) {
            alert(r.data.message || (r.success ? '✅ Сделка создана' : '❌ Ошибка'));
            if (r.success) { setTimeout(function(){ location.reload(); }, 1000); }
            else { loadConvertStatus(); $btn.prop('disabled', false); }
        }).fail(function() { alert('Ошибка соединения'); $btn.prop('disabled', false); });
    });

    setInterval(function() {
        if (currentChatLeadId && leadChatModal.hasClass('active')) { loadManagerChat(); loadConvertStatus(); }
    }, 7000);

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
