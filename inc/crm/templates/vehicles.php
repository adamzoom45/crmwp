<?php
/**
 * Шаблон базы автомобилей
 * Работает с таблицей wp_akpp_vehicles
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'akpp_vehicles';

// Проверка существования таблицы
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
if (!$table_exists) {
    echo '<div class="notice notice-error"><p>❌ Таблица автомобилей не существует. Выполните SQL скрипт vehicles-structure.sql</p></div>';
    return;
}

// Обработка действий
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$vehicle_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$vehicle_data = null;

if ($action === 'edit' && $vehicle_id > 0) {
    $vehicle_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $vehicle_id
    ), ARRAY_A);
    
    if (!$vehicle_data) {
        echo '<div class="notice notice-error"><p>Автомобиль не найден.</p></div>';
        $action = '';
    }
}

// Фильтры
$market_filter = isset($_GET['market']) ? sanitize_text_field($_GET['market']) : '';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

$where = [];
$params = [];

if (!empty($market_filter)) {
    $where[] = "market = %s";
    $params[] = $market_filter;
}

if (!empty($search)) {
    $where[] = "(make LIKE %s OR model LIKE %s OR vin LIKE %s)";
    $search_like = '%' . $wpdb->esc_like($search) . '%';
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$query = "SELECT * FROM $table_name {$where_clause} ORDER BY created_at DESC";
if (!empty($params)) {
    $query = $wpdb->prepare($query, ...$params);
}
$vehicles = $wpdb->get_results($query, ARRAY_A);

$markets = $wpdb->get_col("SELECT DISTINCT market FROM $table_name WHERE market IS NOT NULL AND market != '' ORDER BY market");
$total_vehicles = count($vehicles);
?>

<div class="wrap akpp-crm-wrap">
    <div class="vehicles-page-header">
        <h1>🚗 База автомобилей</h1>
        <button type="button" class="button button-primary akpp-open-modal" data-target="#akpp-vehicle-modal">
            + Добавить автомобиль
        </button>
    </div>

    <!-- Фильтры -->
    <div class="vehicles-filters">
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
            <select name="market">
                <option value="">Все рынки</option>
                <?php foreach ($markets as $market) : ?>
                    <option value="<?php echo esc_attr($market); ?>" <?php selected($market_filter, $market); ?>>
                        <?php
                        $market_labels = [
                            'japan' => '🇯🇵 Япония',
                            'korea' => '🇰 Корея',
                            'europe' => '🇪🇺 Европа',
                            'usa' => '🇺🇸 США',
                            'asia' => '🌏 Азия',
                        ];
                        echo esc_html($market_labels[$market] ?? ucfirst($market));
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button">Фильтровать</button>
            <?php if ($market_filter) : ?>
                <a href="?page=<?php echo esc_attr($_REQUEST['page']); ?>" class="button button-secondary">Сбросить</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Таблица -->
    <div class="vehicles-table-wrapper">
        <?php if (empty($vehicles)) : ?>
            <div class="vehicles-empty-state">
                <div class="icon">🚗</div>
                <p>Нет автомобилей для отображения</p>
                <button type="button" class="button button-primary akpp-open-modal" data-target="#akpp-vehicle-modal">
                    ➕ Добавить первый автомобиль
                </button>
            </div>
        <?php else : ?>
            <table class="vehicles-table wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Марка</th>
                        <th>Модель</th>
                        <th>Год</th>
                        <th>VIN</th>
                        <th>Двигатель</th>
                        <th>Рынок</th>
                        <th style="width: 150px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vehicles as $vehicle) : ?>
                        <tr>
                            <td><?php echo intval($vehicle['id']); ?></td>
                            <td class="vehicle-make"><?php echo esc_html($vehicle['make'] ?? '—'); ?></td>
                            <td><?php echo esc_html($vehicle['model'] ?? '—'); ?></td>
                            <td><?php echo esc_html($vehicle['year'] ?? '—'); ?></td>
                            <td><span class="vehicle-vin"><?php echo esc_html($vehicle['vin'] ?? '—'); ?></span></td>
                            <td><?php echo esc_html($vehicle['engine'] ?? '—'); ?></td>
                            <td>
                                <?php
                                $market = $vehicle['market'] ?? '';
                                $market_labels = [
                                    'japan' => '🇯🇵 Япония',
                                    'korea' => '🇰🇷 Корея',
                                    'europe' => '🇪🇺 Европа',
                                    'usa' => '🇺🇸 США',
                                    'asia' => '🌏 Азия',
                                ];
                                ?>
                                <span class="vehicles-market-badge <?php echo esc_attr($market); ?>">
                                    <?php echo esc_html($market_labels[$market] ?? ucfirst($market)); ?>
                                </span>
                            </td>
                            <td>
                                <div class="vehicles-actions">
                                    <a href="?page=<?php echo esc_attr($_REQUEST['page']); ?>&action=edit&id=<?php echo intval($vehicle['id']); ?>"
                                       class="button btn-edit" title="Редактировать">✏️</a>
                                    <a href="#"
                                       class="button btn-delete akpp-delete-vehicle"
                                       data-id="<?php echo intval($vehicle['id']); ?>"
                                       data-nonce="<?php echo wp_create_nonce('akpp45_nonce'); ?>"
                                       title="Удалить">🗑️</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="vehicles-stats">
                Всего автомобилей: <strong><?php echo $total_vehicles; ?></strong>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Модальное окно -->
<div id="akpp-vehicle-modal" class="akpp-modal <?php echo ($action === 'edit') ? 'active' : ''; ?>">
    <div class="akpp-modal-content">
        <span class="akpp-modal-close">&times;</span>
        <h2><?php echo $action === 'edit' ? '✏️ Редактировать автомобиль' : ' Новый автомобиль'; ?></h2>
        
        <form class="akpp-ajax-form" data-action="akpp_save_vehicle" id="vehicle-form">
            <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
            <?php if ($action === 'edit' && $vehicle_data) : ?>
                <input type="hidden" name="id" value="<?php echo esc_attr($vehicle_data['id']); ?>">
            <?php endif; ?>
            
            <div class="vehicle-form-grid">
                <div class="form-group">
                    <label for="veh_vin">VIN / Кузовной номер</label>
                    <input type="text" id="akpp_vin_input" name="vin" value="<?php echo esc_attr($vehicle_data['vin'] ?? ''); ?>" maxlength="17" style="text-transform: uppercase;" placeholder="17 символов">
                </div>
                <div class="form-group">
                    <label for="veh_market">Рынок</label>
                    <select id="veh_market" name="market">
                        <option value="">Не указан</option>
                        <option value="japan" <?php selected($vehicle_data['market'] ?? '', 'japan'); ?>>🇯🇵 Япония (JDM)</option>
                        <option value="korea" <?php selected($vehicle_data['market'] ?? '', 'korea'); ?>>🇰🇷 Корея</option>
                        <option value="europe" <?php selected($vehicle_data['market'] ?? '', 'europe'); ?>>🇪🇺 Европа</option>
                        <option value="usa" <?php selected($vehicle_data['market'] ?? '', 'usa'); ?>>🇺🇸 США</option>
                        <option value="asia" <?php selected($vehicle_data['market'] ?? '', 'asia'); ?>>🌏 Азия</option>
                    </select>
                </div>
            </div>
            
            <div class="vehicle-form-grid">
                <div class="form-group">
                    <label for="veh_brand">Марка *</label>
                    <input type="text" id="akpp_brand_input" name="make" value="<?php echo esc_attr($vehicle_data['make'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="veh_model">Модель *</label>
                    <input type="text" id="akpp_model_input" name="model" value="<?php echo esc_attr($vehicle_data['model'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="vehicle-form-grid">
                <div class="form-group">
                    <label for="veh_year">Год выпуска</label>
                    <input type="number" id="akpp_year_input" name="year" value="<?php echo esc_attr($vehicle_data['year'] ?? ''); ?>" min="1950" max="<?php echo date('Y') + 1; ?>">
                </div>
                <div class="form-group">
                    <label for="veh_engine">Двигатель</label>
                    <input type="text" id="akpp_engine_input" name="engine" value="<?php echo esc_attr($vehicle_data['engine'] ?? ''); ?>" placeholder="Например: 2.0L 2AZ-FE">
                </div>
            </div>
            
            <div class="vehicle-form-grid">
                <div class="form-group">
                    <label for="veh_fuel">Тип топлива</label>
                    <select name="fuel_type">
                        <option value="">Не указан</option>
                        <option value="gasoline" <?php selected($vehicle_data['fuel_type'] ?? '', 'gasoline'); ?>>Бензин</option>
                        <option value="diesel" <?php selected($vehicle_data['fuel_type'] ?? '', 'diesel'); ?>>Дизель</option>
                        <option value="hybrid" <?php selected($vehicle_data['fuel_type'] ?? '', 'hybrid'); ?>>Гибрид</option>
                        <option value="electric" <?php selected($vehicle_data['fuel_type'] ?? '', 'electric'); ?>>Электро</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="veh_drive">Привод</label>
                    <select name="drive_type">
                        <option value="">Не указан</option>
                        <option value="fwd" <?php selected($vehicle_data['drive_type'] ?? '', 'fwd'); ?>>Передний (FWD)</option>
                        <option value="rwd" <?php selected($vehicle_data['drive_type'] ?? '', 'rwd'); ?>>Задний (RWD)</option>
                        <option value="awd" <?php selected($vehicle_data['drive_type'] ?? '', 'awd'); ?>>Полный (AWD/4WD)</option>
                    </select>
                </div>
            </div>
            
            <div class="vehicle-form-actions">
                <button type="button" class="button button-secondary akpp-modal-close">Отмена</button>
                <button type="submit" class="button button-primary">
                    <?php echo $action === 'edit' ? '💾 Сохранить изменения' : '➕ Добавить автомобиль'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var isSubmitting = false;
    
    // Открытие модального окна
    $('.akpp-open-modal').on('click', function() {
        var target = $(this).data('target');
        $(target).addClass('active');
    });
    
    // Закрытие модального окна
    $('.akpp-modal-close, .akpp-modal').on('click', function(e) {
        if (e.target === this || $(this).hasClass('akpp-modal-close')) {
            $('.akpp-modal').removeClass('active');
        }
    });
    
    // AJAX отправка формы
    $('#vehicle-form').off('submit').on('submit', function(e) {
        e.preventDefault();
        
        // Защита от двойной отправки
        if (isSubmitting) {
            console.log(' Форма уже отправляется...');
            return false;
        }
        
        isSubmitting = true;
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var originalText = $btn.html();
        
        // Валидация обязательных полей
        var make = $form.find('[name="make"]').val().trim();
        var model = $form.find('[name="model"]').val().trim();
        
        if (!make || !model) {
            showNotice('❌ Укажите марку и модель', 'error');
            $btn.prop('disabled', false).html(originalText);
            isSubmitting = false;
            return false;
        }
        
        $btn.prop('disabled', true).html('⏳ Сохранение...');
        
        var formData = $form.serializeArray();
        formData.push({name: 'action', value: $form.data('action')});
        
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message || '✅ Сохранено', 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotice(response.data.message || '❌ Ошибка', 'error');
                    $btn.prop('disabled', false).html(originalText);
                    isSubmitting = false;
                }
            },
            error: function(xhr) {
                console.error('Ошибка:', xhr.responseText);
                showNotice('❌ Ошибка соединения', 'error');
                $btn.prop('disabled', false).html(originalText);
                isSubmitting = false;
            }
        });
        
        return false;
    });
    
    // Удаление автомобиля
    $('.akpp-delete-vehicle').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Удалить автомобиль?')) return;
        
        var $link = $(this);
        var vehicleId = $link.data('id');
        var nonce = $link.data('nonce');
        
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'akpp_delete_vehicle',
                id: vehicleId,
                nonce: nonce
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotice('🗑️ Автомобиль удален', 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotice(response.data.message || '❌ Ошибка удаления', 'error');
                }
            },
            error: function() {
                showNotice('❌ Ошибка соединения', 'error');
            }
        });
    });
    
    // Уведомления
    function showNotice(message, type) {
        var bgColor = type === 'success' ? '#00ff88' : '#fc8181';
        var textColor = type === 'success' ? '#0a0f1c' : '#fff';
        var $notice = $('<div style="position:fixed;top:20px;right:20px;background:' + bgColor + ';color:' + textColor + ';padding:16px 24px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:99999;font-weight:600;max-width:400px;">' + message + '</div>');
        $('body').append($notice);
        setTimeout(function() {
            $notice.fadeOut(300, function() { $(this).remove(); });
        }, 3000);
    }
});
</script>