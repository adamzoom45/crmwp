<?php
if (!defined('ABSPATH')) exit;

// Подключаем класс таблицы
if (!class_exists('AKPP_Vehicles_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-vehicles-table.php';
}

global $wpdb;

// Проверяем режим редактирования
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$vehicle_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$vehicle_data = null;

if ($action === 'edit' && $vehicle_id > 0) {
    $table_name = $wpdb->prefix . 'akpp_vehicles';
    $vehicle_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $vehicle_id), ARRAY_A);
    
    if (!$vehicle_data) {
        echo '<div class="notice notice-error"><p>Автомобиль не найден.</p></div>';
        return;
    }
}

// Инициализируем таблицу
$vehicles_table = new AKPP_Vehicles_Table();
$vehicles_table->prepare_items();

?>

<div class="wrap akpp-crm-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="color: var(--akpp-accent); margin: 0;">🚗 База автомобилей</h1>
        <button type="button" class="button button-primary akpp-open-modal" data-target="#akpp-vehicle-modal" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600;">
            + Добавить автомобиль
        </button>
    </div>

    <!-- Таблица автомобилей -->
    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
        <?php 
        $vehicles_table->search_box('Поиск по марке, модели или VIN', 'vehicle_search'); 
        $vehicles_table->display(); 
        ?>
    </form>
</div>

<!-- Модальное окно добавления/редактирования автомобиля -->
<div id="akpp-vehicle-modal" class="akpp-modal">
    <div class="akpp-modal-content">
        <span class="akpp-modal-close">&times;</span>
        <h2 style="margin-top: 0; color: var(--akpp-accent);">
            <?php echo $action === 'edit' ? 'Редактировать автомобиль' : 'Новый автомобиль'; ?>
        </h2>
        
        <form class="akpp-ajax-form" data-action="akpp_save_vehicle">
            <?php wp_nonce_field('akpp_crm_nonce', 'nonce'); ?>
            
            <?php if ($action === 'edit' && $vehicle_data) : ?>
                <input type="hidden" name="id" value="<?php echo esc_attr($vehicle_data['id']); ?>">
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label for="veh_vin">VIN / Кузовной номер</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="text" id="akpp_vin_input" name="vin" value="<?php echo esc_attr($vehicle_data['vin'] ?? ''); ?>" maxlength="17" style="width: 100%; text-transform: uppercase;" placeholder="17 символов">
                        <span class="akpp-vin-status" style="font-size: 12px; white-space: nowrap;"></span>
                    </div>
                    <small class="akpp-text-muted">Введите 17 символов для автозаполнения</small>
                </div>

                <div class="form-group">
                    <label for="veh_market">Рынок</label>
                    <select id="veh_market" name="market" style="width: 100%;">
                        <option value="japan" <?php selected($vehicle_data['market'] ?? '', 'japan'); ?>>🇯🇵 Япония (JDM)</option>
                        <option value="asia" <?php selected($vehicle_data['market'] ?? '', 'asia'); ?>>🌏 Азия</option>
                        <option value="europe" <?php selected($vehicle_data['market'] ?? '', 'europe'); ?>>🇪🇺 Европа</option>
                        <option value="usa" <?php selected($vehicle_data['market'] ?? '', 'usa'); ?>>🇺🇸 США</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label for="veh_brand">Марка *</label>
                    <input type="text" id="akpp_brand_input" name="brand" value="<?php echo esc_attr($vehicle_data['brand'] ?? ''); ?>" required style="width: 100%;">
                </div>

                <div class="form-group">
                    <label for="veh_model">Модель *</label>
                    <input type="text" id="akpp_model_input" name="model" value="<?php echo esc_attr($vehicle_data['model'] ?? ''); ?>" required style="width: 100%;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="veh_year">Год выпуска</label>
                    <input type="number" id="akpp_year_input" name="year" value="<?php echo esc_attr($vehicle_data['year'] ?? ''); ?>" min="1950" max="<?php echo date('Y') + 1; ?>" style="width: 100%;">
                </div>

                <div class="form-group">
                    <label for="veh_engine">Двигатель</label>
                    <input type="text" id="akpp_engine_input" name="engine" value="<?php echo esc_attr($vehicle_data['engine'] ?? ''); ?>" style="width: 100%;" placeholder="Например: 2.0L 2AZ-FE">
                </div>
            </div>

            <div style="text-align: right;">
                <button type="button" class="button akpp-modal-close" style="margin-right: 10px;">Отмена</button>
                <button type="submit" class="button button-primary" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600;">
                    <?php echo $action === 'edit' ? 'Сохранить изменения' : 'Добавить автомобиль'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Автоматическое открытие модального окна при редактировании
    <?php if ($action === 'edit' && $vehicle_data) : ?>
        $('#akpp-vehicle-modal').fadeIn(200);
    <?php endif; ?>

    // Обработка успешного сохранения
    window.akppFormSuccess = function(data, $form) {
        $('#akpp-vehicle-modal').fadeOut(200);
        $form[0].reset();
        $('.akpp-vin-status').html(''); // Очистка статуса VIN
        setTimeout(() => {
            window.location.href = '<?php echo esc_url(admin_url('admin.php?page=akpp-vehicles')); ?>';
        }, 500);
    };
});
</script>
