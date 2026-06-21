<?php
/**
 * Шаблон списка сделок
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('AKPP_Deals_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-deals-table.php';
}

$table = new AKPP_Deals_Table();
$table->prepare_items();
?>

<div class="wrap akpp-crm-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1 style="color: #00ff88; border-left: 4px solid #00ff88; padding-left: 15px; margin: 0;">
            📋 Сделки
        </h1>
        <a href="?page=akpp-crm-new-deal" 
           class="button" 
           style="background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%); color: #1a1f2e; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none;">
            ➕ Новая сделка
        </a>
    </div>

    <?php
    // Отображение уведомлений
    settings_errors();
    ?>

    <div class="akpp-card" style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 20px;">
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
            <?php $table->search_box('Поиск по клиенту, телефону или VIN', 'deal_search'); ?>
            <?php $table->display(); ?>
        </form>
    </div>
</div>