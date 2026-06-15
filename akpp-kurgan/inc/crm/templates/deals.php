<?php
if (!defined('ABSPATH')) exit;

// Подключаем класс таблицы сделок
if (!class_exists('AKPP_Deals_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-deals-table.php';
}

// Инициализируем и подготавливаем таблицу
$deals_table = new AKPP_Deals_Table();
$deals_table->prepare_items();

// URL для создания новой сделки
$new_deal_url = admin_url('admin.php?page=akpp-new-deal');

?>

<div class="wrap akpp-crm-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="color: var(--akpp-accent); margin: 0;">📋 Сделки</h1>
        <a href="<?php echo esc_url($new_deal_url); ?>" class="button button-primary" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
            <span style="font-size: 18px; line-height: 1;">+</span> Новая сделка
        </a>
    </div>

    <!-- Уведомления о массовых действиях -->
    <?php if (isset($_GET['deleted']) && intval($_GET['deleted']) > 0) : ?>
        <div class="notice notice-success is-dismissible" style="border-left-color: var(--akpp-success);">
            <p>Успешно удалено сделок: <strong><?php echo esc_html($_GET['deleted']); ?></strong></p>
        </div>
    <?php endif; ?>

    <!-- Таблица сделок с поиском и фильтрами -->
    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
        
        <?php 
        // Выводим поле поиска
        $deals_table->search_box('Поиск по клиенту, телефону или VIN', 'deal_search'); 
        
        // Выводим саму таблицу (включает extra_tablenav с фильтрами по статусу)
        $deals_table->display(); 
        ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Автоматическое скрытие уведомлений WordPress через 5 секунд
    setTimeout(function() {
        $('.notice.is-dismissible').fadeOut(500, function() {
            $(this).remove();
        });
    }, 5000);
});
</script>
