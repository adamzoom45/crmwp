<?php
if (!defined('ABSPATH')) exit;

// Подключаем класс таблицы масел
if (!class_exists('AKPP_Oils_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-oils-table.php';
}

global $wpdb;

// Проверяем режим редактирования
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$oil_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$oil_data = null;

if ($action === 'edit' && $oil_id > 0) {
    $table_name = $wpdb->prefix . 'akpp_oils';
    $oil_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $oil_id), ARRAY_A);
    
    if (!$oil_data) {
        echo '<div class="notice notice-error"><p>Позиция масла не найдена.</p></div>';
        return;
    }
}

// Типы масел
$oil_types = [
    'ATF' => 'ATF (Гидромеханические)',
    'CVT' => 'CVT (Вариаторные)',
    'DCT' => 'DCT (Роботизированные)',
    'MTF' => 'MTF (Механические)'
];

// Инициализируем таблицу
$oils_table = new AKPP_Oils_Table();
$oils_table->prepare_items();

?>

<div class="wrap akpp-crm-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="color: var(--akpp-accent); margin: 0;">🛢️ Склад масел</h1>
        <button type="button" class="button button-primary akpp-open-modal" data-target="#akpp-oil-modal" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600;">
            + Добавить масло
        </button>
    </div>

    <!-- Таблица масел с поиском и фильтрами -->
    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
        <?php 
        $oils_table->search_box('Поиск по названию', 'oil_search'); 
        $oils_table->display(); 
        ?>
    </form>
</div>

<!-- Модальное окно добавления/редактирования масла -->
<div id="akpp-oil-modal" class="akpp-modal">
    <div class="akpp-modal-content">
        <span class="akpp-modal-close">&times;</span>
        <h2 style="margin-top: 0; color: var(--akpp-accent);">
            <?php echo $action === 'edit' ? 'Редактировать масло' : 'Новое масло'; ?>
        </h2>
        
        <form class="akpp-ajax-form" data-action="akpp_save_oil">
            <?php wp_nonce_field('akpp_crm_nonce', 'nonce'); ?>
            
            <?php if ($action === 'edit' && $oil_data) : ?>
                <input type="hidden" name="id" value="<?php echo esc_attr($oil_data['id']); ?>">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="oil_name">Название / Бренд *</label>
                <input type="text" id="oil_name" name="name" value="<?php echo esc_attr($oil_data['name'] ?? ''); ?>" required style="width: 100%;" placeholder="Например: Toyota ATF WS">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label for="oil_type">Тип масла *</label>
                    <select id="oil_type" name="type" required style="width: 100%;">
                        <option value="">Выберите тип</option>
                        <?php foreach ($oil_types as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($oil_data['type'] ?? '', $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="oil_volume">Объем тары (литры) *</label>
                    <input type="number" id="oil_volume" name="volume_liters" value="<?php echo esc_attr($oil_data['volume_liters'] ?? 1); ?>" min="0.1" step="0.1" required style="width: 100%;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="oil_quantity">Остаток (шт/банок) *</label>
                    <input type="number" id="oil_quantity" name="quantity" value="<?php echo esc_attr($oil_data['quantity'] ?? 0); ?>" min="0" required style="width: 100%;">
                </div>

                <div class="form-group">
                    <label for="oil_price">Цена за единицу (₽) *</label>
                    <input type="number" id="oil_price" name="price" value="<?php echo esc_attr($oil_data['price'] ?? 0); ?>" min="0" step="0.01" required style="width: 100%;">
                </div>
            </div>

            <div style="text-align: right;">
                <button type="button" class="button akpp-modal-close" style="margin-right: 10px;">Отмена</button>
                <button type="submit" class="button button-primary" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600;">
                    <?php echo $action === 'edit' ? 'Сохранить изменения' : 'Добавить на склад'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Автоматическое открытие модального окна при редактировании
    <?php if ($action === 'edit' && $oil_data) : ?>
        $('#akpp-oil-modal').fadeIn(200);
    <?php endif; ?>

    // Обработка успешного сохранения
    window.akppFormSuccess = function(data, $form) {
        $('#akpp-oil-modal').fadeOut(200);
        $form[0].reset();
        setTimeout(() => {
            window.location.href = '<?php echo esc_url(admin_url('admin.php?page=akpp-oils')); ?>';
        }, 500);
    };
});
</script>
