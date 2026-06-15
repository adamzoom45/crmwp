<?php
if (!defined('ABSPATH')) exit;

// Подключаем класс таблицы
if (!class_exists('AKPP_Transmissions_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-transmissions-table.php';
}

global $wpdb;

// Проверяем режим редактирования
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$transmission_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$transmission_data = null;

if ($action === 'edit' && $transmission_id > 0) {
    $table_name = $wpdb->prefix . 'akpp_transmissions';
    $transmission_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $transmission_id), ARRAY_A);
    
    if (!$transmission_data) {
        echo '<div class="notice notice-error"><p>Запись АКПП не найдена.</p></div>';
        return;
    }
}

// Инициализируем таблицу
$transmissions_table = new AKPP_Transmissions_Table();
$transmissions_table->prepare_items();

?>

<div class="wrap akpp-crm-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="color: var(--akpp-accent); margin: 0;">⚙️ Каталог АКПП</h1>
        <button type="button" class="button button-primary akpp-open-modal" data-target="#akpp-transmission-modal" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600;">
            + Добавить АКПП
        </button>
    </div>

    <!-- Таблица каталога АКПП -->
    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
        <?php 
        $transmissions_table->search_box('Поиск по коду или названию', 'transmission_search'); 
        $transmissions_table->display(); 
        ?>
    </form>
</div>

<!-- Модальное окно добавления/редактирования АКПП -->
<div id="akpp-transmission-modal" class="akpp-modal">
    <div class="akpp-modal-content">
        <span class="akpp-modal-close">&times;</span>
        <h2 style="margin-top: 0; color: var(--akpp-accent);">
            <?php echo $action === 'edit' ? 'Редактировать запись АКПП' : 'Новая запись АКПП'; ?>
        </h2>
        
        <form class="akpp-ajax-form" data-action="akpp_save_transmission">
            <?php wp_nonce_field('akpp_crm_nonce', 'nonce'); ?>
            
            <?php if ($action === 'edit' && $transmission_data) : ?>
                <input type="hidden" name="id" value="<?php echo esc_attr($transmission_data['id']); ?>">
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label for="trans_code">Код АКПП *</label>
                    <input type="text" id="trans_code" name="code" value="<?php echo esc_attr($transmission_data['code'] ?? ''); ?>" required style="width: 100%; text-transform: uppercase;" placeholder="Например: U140E, JA404E">
                    <small class="akpp-text-muted">Уникальный идентификатор модели</small>
                </div>

                <div class="form-group">
                    <label for="trans_type">Тип трансмиссии *</label>
                    <select id="trans_type" name="type" required style="width: 100%;">
                        <option value="AT" <?php selected($transmission_data['type'] ?? '', 'AT'); ?>>AT (Гидротрансформатор)</option>
                        <option value="CVT" <?php selected($transmission_data['type'] ?? '', 'CVT'); ?>>CVT (Вариатор)</option>
                        <option value="DCT" <?php selected($transmission_data['type'] ?? '', 'DCT'); ?>>DCT (Робот с двумя сцеплениями)</option>
                        <option value="AMT" <?php selected($transmission_data['type'] ?? '', 'AMT'); ?>>AMT (Робот с одним сцеплением)</option>
                        <option value="MT" <?php selected($transmission_data['type'] ?? '', 'MT'); ?>>MT (Механическая)</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="trans_name">Название *</label>
                <input type="text" id="trans_name" name="name" value="<?php echo esc_attr($transmission_data['name'] ?? ''); ?>" required style="width: 100%;" placeholder="Например: Toyota Camry 3.0 01-06">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="trans_description">Описание / Совместимость</label>
                <textarea id="trans_description" name="description" rows="4" style="width: 100%;" placeholder="Перечислите совместимые автомобили, особенности, объем масла и т.д."><?php echo esc_textarea($transmission_data['description'] ?? ''); ?></textarea>
            </div>

            <div style="text-align: right;">
                <button type="button" class="button akpp-modal-close" style="margin-right: 10px;">Отмена</button>
                <button type="submit" class="button button-primary" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600;">
                    <?php echo $action === 'edit' ? 'Сохранить изменения' : 'Добавить в каталог'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Автоматическое открытие модального окна при редактировании
    <?php if ($action === 'edit' && $transmission_data) : ?>
        $('#akpp-transmission-modal').fadeIn(200);
    <?php endif; ?>

    // Обработка успешного сохранения
    window.akppFormSuccess = function(data, $form) {
        $('#akpp-transmission-modal').fadeOut(200);
        $form[0].reset();
        setTimeout(() => {
            window.location.href = '<?php echo esc_url(admin_url('admin.php?page=akpp-transmissions')); ?>';
        }, 500);
    };
});
</script>
