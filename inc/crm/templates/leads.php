<?php
if (!defined('ABSPATH')) exit;

// Подключаем класс таблицы лидов
if (!class_exists('AKPP_Leads_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-leads-table.php';
}

global $wpdb;

// Получаем список активных сотрудников для назначения
$employees = $wpdb->get_results("SELECT id, name as full_name FROM {$wpdb->prefix}akpp_employees WHERE is_active = 1 ORDER BY name ASC", ARRAY_A);
// Инициализируем таблицу
$leads_table = new AKPP_Leads_Table();
$leads_table->prepare_items();

?>

<div class="wrap akpp-crm-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="color: var(--akpp-accent); margin: 0;">📨 Входящие заявки (Лиды)</h1>
        <button type="button" class="button button-primary akpp-open-modal" data-target="#akpp-lead-modal" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600;">
            + Добавить лид вручную
        </button>
    </div>

    <!-- Уведомления о массовых действиях -->
    <?php if (isset($_GET['updated']) && intval($_GET['updated']) > 0) : ?>
        <div class="notice notice-success is-dismissible" style="border-left-color: var(--akpp-success);">
            <p>Успешно обновлено записей: <strong><?php echo esc_html($_GET['updated']); ?></strong></p>
        </div>
    <?php endif; ?>

    <!-- Таблица лидов с поиском и фильтрами -->
    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
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
            <?php wp_nonce_field('akpp_crm_nonce', 'nonce'); ?>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="lead_source">Источник *</label>
                <select id="lead_source" name="source" required style="width: 100%;">
                    <option value="call">📞 Звонок</option>
                    <option value="site">🌐 Сайт</option>
                    <option value="avito">🟢 Авито</option>
                    <option value="telegram">🔵 Telegram</option>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label for="lead_name">Имя клиента</label>
                    <input type="text" id="lead_name" name="name" style="width: 100%;" placeholder="Неизвестно">
                </div>
                <div class="form-group">
                    <label for="lead_phone">Телефон *</label>
                    <input type="tel" id="lead_phone" name="phone" required style="width: 100%;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="lead_message">Сообщение / Описание проблемы</label>
                <textarea id="lead_message" name="message" rows="4" style="width: 100%;" placeholder="Краткое описание обращения"></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="lead_assigned">Назначить гида/менеджера</label>
                <select id="lead_assigned" name="assigned_to" style="width: 100%;">
                    <option value="0">Не назначен</option>
                    <?php foreach ($employees as $emp) : ?>
                        <option value="<?php echo esc_attr($emp['id']); ?>">
                            <?php echo esc_html($emp['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="text-align: right;">
                <button type="button" class="button akpp-modal-close" style="margin-right: 10px;">Отмена</button>
                <button type="submit" class="button button-primary" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600;">
                    Сохранить лид
                </button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Автоматическое скрытие уведомлений WordPress через 5 секунд
    setTimeout(function() {
        $('.notice.is-dismissible').fadeOut(500, function() {
            $(this).remove();
        });
    }, 5000);

    // Обработка успешного сохранения
    window.akppFormSuccess = function(data, $form) {
        $('#akpp-lead-modal').fadeOut(200);
        $form[0].reset();
        setTimeout(() => {
            window.location.href = '<?php echo esc_url(admin_url('admin.php?page=akpp-leads')); ?>';
        }, 500);
    };
});
</script>
