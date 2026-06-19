<?php
if (!defined('ABSPATH')) exit;

// Подключаем класс таблицы запчастей
if (!class_exists('AKPP_Parts_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-parts-table.php';
}

global $wpdb;

// Проверяем режим редактирования
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$part_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$part_data = null;

if ($action === 'edit' && $part_id > 0) {
    $table_name = $wpdb->prefix . 'akpp_parts';
    $part_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $part_id), ARRAY_A);
    
    if (!$part_data) {
        echo '<div class="notice notice-error"><p>Запчасть не найдена.</p></div>';
        return;
    }
}

// 12 основных категорий запчастей для АКПП
$categories = [
    'Гидроблок', 'Фрикционы', 'Стальной диск', 'Пакет фрикционов',
    'Соленоиды', 'Масляный насос', 'Планетарный ряд', 'Валы',
    'Корпус', 'ЭБУ (Блок управления)', 'Сальники и уплотнения', 'Прочее'
];

// Инициализируем таблицу
$parts_table = new AKPP_Parts_Table();
$parts_table->prepare_items();

?>

<div class="wrap akpp-crm-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="color: var(--akpp-accent); margin: 0;">📦 Склад запчастей</h1>
        <button type="button" class="button button-primary akpp-open-modal" data-target="#akpp-part-modal" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600;">
            + Добавить запчасть
        </button>
    </div>

    <!-- Таблица запчастей с поиском и фильтрами -->
    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
        <?php 
        $parts_table->search_box('Поиск по названию или артикулу', 'part_search'); 
        $parts_table->display(); 
        ?>
    </form>
</div>

<!-- Модальное окно добавления/редактирования запчасти -->
<div id="akpp-part-modal" class="akpp-modal">
    <div class="akpp-modal-content">
        <span class="akpp-modal-close">&times;</span>
        <h2 style="margin-top: 0; color: var(--akpp-accent);">
            <?php echo $action === 'edit' ? 'Редактировать запчасть' : 'Новая запчасть'; ?>
        </h2>
        
        <form class="akpp-ajax-form" data-action="akpp_save_part">
            <?php wp_nonce_field('akpp_crm_nonce', 'nonce'); ?>
            
            <?php if ($action === 'edit' && $part_data) : ?>
                <input type="hidden" name="id" value="<?php echo esc_attr($part_data['id']); ?>">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="part_name">Название запчасти *</label>
                <input type="text" id="part_name" name="name" value="<?php echo esc_attr($part_data['name'] ?? ''); ?>" required style="width: 100%;" placeholder="Например: Фрикцион передний">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label for="part_sku">Артикул (SKU)</label>
                    <input type="text" id="part_sku" name="sku" value="<?php echo esc_attr($part_data['sku'] ?? ''); ?>" style="width: 100%;" placeholder="Например: 12345-AB">
                </div>

                <div class="form-group">
                    <label for="part_category">Категория *</label>
                    <select id="part_category" name="category" required style="width: 100%;">
                        <option value="">Выберите категорию</option>
                        <?php foreach ($categories as $cat) : ?>
                            <option value="<?php echo esc_attr($cat); ?>" <?php selected($part_data['category'] ?? '', $cat); ?>>
                                <?php echo esc_html($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="part_quantity">Количество на складе *</label>
                    <input type="number" id="part_quantity" name="quantity" value="<?php echo esc_attr($part_data['quantity'] ?? 0); ?>" min="0" required style="width: 100%;">
                </div>

                <div class="form-group">
                    <label for="part_price">Цена закупки (₽) *</label>
                    <input type="number" id="part_price" name="price" value="<?php echo esc_attr($part_data['price'] ?? 0); ?>" min="0" step="0.01" required style="width: 100%;">
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
    <?php if ($action === 'edit' && $part_data) : ?>
        $('#akpp-part-modal').fadeIn(200);
    <?php endif; ?>

    // Обработка успешного сохранения
    window.akppFormSuccess = function(data, $form) {
        $('#akpp-part-modal').fadeOut(200);
        $form[0].reset();
        setTimeout(() => {
            window.location.href = '<?php echo esc_url(admin_url('admin.php?page=akpp-crm-parts')); ?>';
        }, 500);
    };
});
</script>
