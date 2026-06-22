<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('AKPP_Transmissions_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-transmissions-table.php';
}

global $wpdb;

$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$transmission_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$transmission_data = null;

if ($action === 'edit' && $transmission_id > 0) {
    $table_name = $wpdb->prefix . 'akpp_transmissions';
    $transmission_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $transmission_id), ARRAY_A);
    if (!$transmission_data) {
        echo '<div class="notice notice-error"><p>Запись не найдена.</p></div>';
        return;
    }
}

$transmissions_table = new AKPP_Transmissions_Table();
$transmissions_table->prepare_items();

// Статистика по регионам
$region_stats = $wpdb->get_results("SELECT region, COUNT(*) as count FROM {$wpdb->prefix}akpp_transmissions GROUP BY region ORDER BY region", ARRAY_A);
$region_labels = ['japan' => '🇯🇵 Япония', 'korea' => '🇰🇷 Корея', 'china' => '🇨🇳 Китай', 'europe' => '🇪🇺 Европа', 'america' => '🇺🇸 Америка'];
$total = array_sum(array_column($region_stats, 'count'));
$current_region = isset($_GET['region_filter']) ? sanitize_text_field($_GET['region_filter']) : '';
?>

<div class="wrap akpp-crm-wrap">
    <!-- Статистика по регионам -->
    <div class="akpp-region-stats" style="display: flex; gap: 20px; flex-wrap: wrap; margin: 15px 0; background: #1a1f2e; padding: 15px; border-radius: 10px; border: 1px solid #2d3748;">
        <div style="color: #a0aec0; font-weight: 600; padding-right: 20px; border-right: 1px solid #2d3748;">📊 Регионы:</div>
        <?php foreach ($region_stats as $stat) : 
            $region = $stat['region'];
            $count = $stat['count'];
            $label = $region_labels[$region] ?? $region;
            $is_active = ($current_region === $region);
            $url = add_query_arg('region_filter', $region, remove_query_arg('paged'));
        ?>
            <a href="<?php echo esc_url($url); ?>" style="color: <?php echo $is_active ? '#00ff88' : '#e2e8f0'; ?>; background: <?php echo $is_active ? 'rgba(0,255,136,0.1)' : 'transparent'; ?>; padding: 5px 12px; border-radius: 20px; border: 1px solid <?php echo $is_active ? '#00ff88' : '#4a5568'; ?>; text-decoration: none; font-weight: <?php echo $is_active ? '700' : '400'; ?>;">
                <?php echo esc_html($label); ?> (<?php echo $count; ?>)
            </a>
        <?php endforeach; ?>
        <a href="<?php echo remove_query_arg('region_filter'); ?>" style="color: #a0aec0; padding: 5px 12px; border-radius: 20px; border: 1px solid #4a5568; text-decoration: none; <?php echo empty($current_region) ? 'background: rgba(0,255,136,0.1); border-color: #00ff88; color: #00ff88;' : ''; ?>">Все (<?php echo $total; ?>)</a>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="color: var(--akpp-accent); margin: 0;">⚙️ Каталог АКПП</h1>
        <button type="button" class="button button-primary akpp-open-modal" data-target="#akpp-transmission-modal" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600;">+ Добавить АКПП</button>
    </div>

    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
        <?php $transmissions_table->search_box('Поиск по коду или производителю', 'transmission_search'); ?>
        <?php $transmissions_table->display(); ?>
    </form>
</div>

<!-- Модальное окно (упрощённое) -->
<div id="akpp-transmission-modal" class="akpp-modal">
    <div class="akpp-modal-content" style="max-width: 700px; width: 95%;">
        <div class="akpp-modal-header">
            <h2 style="margin: 0; color: #00ff88; border: none; padding: 0;"><?php echo $action === 'edit' ? '✏️ Редактировать АКПП' : '➕ Новая АКПП'; ?></h2>
            <span class="akpp-modal-close">&times;</span>
        </div>
        <div class="akpp-modal-body">
            <form class="akpp-ajax-form" data-action="akpp_save_transmission">
                <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
                <?php if ($action === 'edit' && $transmission_data) : ?>
                    <input type="hidden" name="id" value="<?php echo esc_attr($transmission_data['id']); ?>">
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="trans_code">Код АКПП <span style="color: #fc8181;">*</span></label>
                        <input type="text" id="trans_code" name="code" value="<?php echo esc_attr($transmission_data['code'] ?? ''); ?>" required style="width: 100%; text-transform: uppercase;" placeholder="U140E">
                    </div>
                    <div class="form-group">
                        <label for="trans_type">Тип трансмиссии <span style="color: #fc8181;">*</span></label>
                        <select id="trans_type" name="type" required style="width: 100%;">
                            <option value="AT" <?php selected($transmission_data['type'] ?? '', 'AT'); ?>>AT</option>
                            <option value="CVT" <?php selected($transmission_data['type'] ?? '', 'CVT'); ?>>CVT</option>
                            <option value="DCT" <?php selected($transmission_data['type'] ?? '', 'DCT'); ?>>DCT</option>
                            <option value="AMT" <?php selected($transmission_data['type'] ?? '', 'AMT'); ?>>AMT</option>
                            <option value="MT" <?php selected($transmission_data['type'] ?? '', 'MT'); ?>>MT</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="trans_manufacturer">Производитель</label>
                        <input type="text" id="trans_manufacturer" name="manufacturer" value="<?php echo esc_attr($transmission_data['manufacturer'] ?? ''); ?>" style="width: 100%;" placeholder="Aisin, Jatco, ZF...">
                    </div>
                    <div class="form-group">
                        <label for="trans_region">Регион</label>
                        <select id="trans_region" name="region" style="width: 100%;">
                            <option value="">— Не выбран —</option>
                            <option value="japan" <?php selected($transmission_data['region'] ?? '', 'japan'); ?>>🇯🇵 Япония</option>
                            <option value="korea" <?php selected($transmission_data['region'] ?? '', 'korea'); ?>>🇰🇷 Корея</option>
                            <option value="china" <?php selected($transmission_data['region'] ?? '', 'china'); ?>>🇨🇳 Китай</option>
                            <option value="europe" <?php selected($transmission_data['region'] ?? '', 'europe'); ?>>🇪🇺 Европа</option>
                            <option value="america" <?php selected($transmission_data['region'] ?? '', 'america'); ?>>🇺🇸 Америка</option>
                        </select>
                    </div>
                </div>

                <div style="text-align: right; padding-top: 20px; margin-top: 20px; border-top: 1px solid #2d3748;">
                    <button type="button" class="button akpp-modal-close" style="margin-right: 10px;">Отмена</button>
                    <button type="submit" class="button button-primary" style="background-color: var(--akpp-accent); border-color: var(--akpp-accent); color: var(--akpp-bg-primary); font-weight: 600;">
                        <?php echo $action === 'edit' ? '💾 Сохранить' : '➕ Добавить'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Открыть/закрыть модалку
    $('.akpp-open-modal').on('click', function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        $(target).addClass('active');
    });
    $(document).on('click', '.akpp-modal-close, .akpp-modal', function(e) {
        if ($(e.target).hasClass('akpp-modal-close') || $(e.target).hasClass('akpp-modal')) {
            $('.akpp-modal').removeClass('active');
        }
    });
    // AJAX отправка формы
    $('.akpp-ajax-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var action = $form.data('action');
        $submitBtn.prop('disabled', true).text('⏳ Сохранение...');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $form.serialize() + '&action=' + action,
            dataType: 'json',
            success: function(response) {
                $submitBtn.prop('disabled', false).text($submitBtn.data('original-text') || 'Сохранить');
                if (response.success) {
                    $('#akpp-transmission-modal').removeClass('active');
                    location.reload();
                } else {
                    alert('Ошибка: ' + response.data.message);
                }
            },
            error: function() {
                $submitBtn.prop('disabled', false).text($submitBtn.data('original-text') || 'Сохранить');
                alert('Ошибка соединения.');
            }
        });
    });
    $('.akpp-ajax-form button[type="submit"]').each(function() {
        $(this).data('original-text', $(this).text());
    });
    <?php if ($action === 'edit' && $transmission_data) : ?>
        $('#akpp-transmission-modal').addClass('active');
    <?php endif; ?>
});
</script>

<style>
.akpp-modal {
    display: none !important;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.85);
    z-index: 999999;
    align-items: center;
    justify-content: center;
}
.akpp-modal.active { display: flex !important; }
.akpp-modal-content {
    background: #1a1f2e;
    border: 1px solid #2d3748;
    border-radius: 12px;
    max-width: 700px;
    width: 95%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,255,136,0.2);
}
.akpp-modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #2d3748;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #1a1f2e 0%, #2d3748 100%);
    border-radius: 12px 12px 0 0;
}
.akpp-modal-header h2 { margin: 0; color: #00ff88; border: none; padding: 0; }
.akpp-modal-close { font-size: 28px; cursor: pointer; color: #a0aec0; padding: 0 8px; transition: color 0.2s; }
.akpp-modal-close:hover { color: #fff; }
.akpp-modal-body { padding: 24px; }
.akpp-modal-body .form-group { margin-bottom: 15px; }
.akpp-modal-body .form-group label { display: block; color: #a0aec0; font-weight: 500; font-size: 13px; margin-bottom: 5px; }
.akpp-modal-body .form-group input,
.akpp-modal-body .form-group select {
    width: 100%;
    padding: 10px 14px;
    background: #0a0f1c;
    border: 1px solid #2d3748;
    border-radius: 6px;
    color: #e2e8f0;
    font-size: 14px;
}
.akpp-modal-body .form-group input:focus,
.akpp-modal-body .form-group select:focus {
    border-color: #00ff88;
    outline: none;
    box-shadow: 0 0 0 2px rgba(0,255,136,0.2);
}
.akpp-modal-body .button-primary {
    background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%);
    color: #1a1f2e;
    border: none;
    padding: 10px 24px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
}
.akpp-modal-body .button-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,255,136,0.4); }
</style>