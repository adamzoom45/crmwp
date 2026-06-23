<?php
/**
 * Шаблон страницы АКПП
 */
if (!defined('ABSPATH')) exit;

// Подключаем класс таблицы
if (!class_exists('AKPP_Transmissions_Table')) {
    require_once dirname(__FILE__) . '/../tables/class-transmissions-table.php';
}

global $wpdb;
$table_name = $wpdb->prefix . 'akpp_transmissions';

// Проверка таблицы
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
if (!$table_exists) {
    echo '<div class="notice notice-error"><p>❌ Таблица АКПП не существует</p></div>';
    return;
}

// Статистика
$total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
$by_type = $wpdb->get_results("SELECT type, COUNT(*) as cnt FROM {$table_name} GROUP BY type", ARRAY_A);
?>

<div class="wrap akpp-crm-wrap">
    <div class="transmissions-page-header">
        <h1>🔧 База АКПП</h1>
        <button type="button" class="button button-primary akpp-open-modal" data-target="#akpp-transmission-modal">
            + Добавить АКПП
        </button>
    </div>

    <!-- Статистика -->
    <div class="transmissions-stats-grid">
        <div class="transmissions-stat-card">
            <div class="transmissions-stat-value total"><?php echo $total; ?></div>
            <div class="transmissions-stat-label">ВСЕГО АКПП</div>
        </div>
        <?php foreach ($by_type as $type) : 
            $label = strtoupper($type['type'] ?: 'OTHER');
            $icon = '🔧';
            if (stripos($type['type'], 'гидро') !== false || stripos($type['type'], 'at') !== false) $icon = '🔄';
            elseif (stripos($type['type'], 'вариатор') !== false || stripos($type['type'], 'cvt') !== false) $icon = '⚙️';
            elseif (stripos($type['type'], 'робот') !== false || stripos($type['type'], 'dct') !== false) $icon = '🤖';
        ?>
        <div class="transmissions-stat-card">
            <div class="transmissions-stat-value"><?php echo $icon . ' ' . $type['cnt']; ?></div>
            <div class="transmissions-stat-label"><?php echo esc_html($label); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Таблица -->
    <div class="transmissions-table-wrapper">
        <?php
        $table = new AKPP_Transmissions_Table();
        $table->prepare_items();
        ?>
        <form method="get">
            <input type="hidden" name="page" value="akpp-crm-transmissions">
            <?php $table->search_box('Поиск по коду, марке, модели', 'transmission_search'); ?>
            <?php $table->display(); ?>
        </form>
    </div>
</div>

<!-- Модальное окно -->
<div id="akpp-transmission-modal" class="akpp-modal">
    <div class="akpp-modal-content">
        <span class="akpp-modal-close">&times;</span>
        <h2 id="transmission-modal-title">➕ Новая АКПП</h2>
        
        <form class="akpp-ajax-form" data-action="akpp_save_transmission" id="transmission-form">
            <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
            <input type="hidden" name="id" id="transmission-id">
            
            <div class="transmission-form-grid">
                <div class="form-group">
                    <label>Код АКПП *</label>
                    <input type="text" name="code" id="transmission-code" required placeholder="U660E, A340E, 09G...">
                </div>
                <div class="form-group">
                    <label>Тип</label>
                    <select name="type" id="transmission-type">
                        <option value="">Не указан</option>
                        <option value="гидротрансформатор">Гидротрансформатор</option>
                        <option value="вариатор">Вариатор (CVT)</option>
                        <option value="робот">Робот (DCT/DSG)</option>
                    </select>
                </div>
            </div>
            
            <div class="transmission-form-grid">
                <div class="form-group">
                    <label>Марка</label>
                    <input type="text" name="make" id="transmission-make" placeholder="Toyota, Hyundai...">
                </div>
                <div class="form-group">
                    <label>Модель</label>
                    <input type="text" name="model" id="transmission-model" placeholder="Camry, Sonata...">
                </div>
            </div>
            
            <div class="transmission-form-grid">
                <div class="form-group">
                    <label>Годы выпуска</label>
                    <input type="text" name="years" id="transmission-years" placeholder="2010-2020">
                </div>
                <div class="form-group">
                    <label>Двигатель</label>
                    <input type="text" name="engine" id="transmission-engine" placeholder="2.5L 2AR-FE">
                </div>
            </div>
            
            <div class="form-group">
                <label>Характерные проблемы</label>
                <textarea name="common_problems" id="transmission-problems" rows="3" placeholder="Опишите типичные проблемы этой АКПП..."></textarea>
            </div>
            
            <div class="transmission-form-grid">
                <div class="form-group">
                    <label>Стоимость ремонта (₽)</label>
                    <input type="number" name="repair_cost" id="transmission-cost" min="0" placeholder="50000">
                </div>
                <div class="form-group">
                    <label>Сложность (1-5)</label>
                    <select name="difficulty" id="transmission-difficulty">
                        <option value="1">⭐ Очень легко</option>
                        <option value="2">⭐⭐ Легко</option>
                        <option value="3" selected>⭐⭐⭐ Средне</option>
                        <option value="4">⭐⭐⭐⭐ Сложно</option>
                        <option value="5">⭐⭐⭐⭐⭐ Очень сложно</option>
                    </select>
                </div>
            </div>
            
            <div class="transmission-form-actions">
                <button type="button" class="button button-secondary akpp-modal-close">Отмена</button>
                <button type="submit" class="button button-primary">💾 Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var isSubmitting = false;
    
    // Открытие модалки
    $('.akpp-open-modal').on('click', function() {
        var target = $(this).data('target');
        $('#transmission-modal-title').text('➕ Новая АКПП');
        $('#transmission-form')[0].reset();
        $('#transmission-id').val('');
        $(target).addClass('active');
    });
    
    // Закрытие
    $('.akpp-modal-close, .akpp-modal').on('click', function(e) {
        if (e.target === this || $(this).hasClass('akpp-modal-close')) {
            $('.akpp-modal').removeClass('active');
        }
    });
    
    // Редактирование
    $(document).on('click', '.btn-edit-transmission', function(e) {
        e.preventDefault();
        var data = $(this).data();
        
        $('#transmission-modal-title').text('✏️ Редактировать АКПП');
        $('#transmission-id').val(data.id);
        $('#transmission-code').val(data.code);
        $('#transmission-type').val(data.type);
        $('#transmission-make').val(data.make);
        $('#transmission-model').val(data.model);
        $('#transmission-years').val(data.years);
        $('#transmission-engine').val(data.engine);
        $('#transmission-problems').val(data.problems);
        $('#transmission-cost').val(data.cost);
        $('#transmission-difficulty').val(data.difficulty);
        
        $('#akpp-transmission-modal').addClass('active');
    });
    
    // Удаление
    $(document).on('click', '.btn-delete-transmission', function(e) {
        e.preventDefault();
        if (!confirm('Удалить АКПП?')) return;
        
        var id = $(this).data('id');
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'akpp_delete_transmission',
                id: id,
                nonce: '<?php echo wp_create_nonce("akpp45_nonce"); ?>'
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showNotice('🗑️ АКПП удалена', 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    showNotice(res.data.message || '❌ Ошибка', 'error');
                }
            }
        });
    });
    
    // Сохранение
    $('#transmission-form').off('submit').on('submit', function(e) {
        e.preventDefault();
        if (isSubmitting) return false;
        isSubmitting = true;
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var originalText = $btn.html();
        
        $btn.prop('disabled', true).html('⏳ Сохранение...');
        
        var formData = $form.serializeArray();
        formData.push({name: 'action', value: $form.data('action')});
        
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showNotice(res.data.message || '✅ Сохранено', 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    showNotice(res.data.message || '❌ Ошибка', 'error');
                    $btn.prop('disabled', false).html(originalText);
                    isSubmitting = false;
                }
            },
            error: function() {
                showNotice('❌ Ошибка соединения', 'error');
                $btn.prop('disabled', false).html(originalText);
                isSubmitting = false;
            }
        });
        
        return false;
    });
    
    function showNotice(message, type) {
        var bgColor = type === 'success' ? '#00ff88' : '#fc8181';
        var textColor = type === 'success' ? '#0a0f1c' : '#fff';
        var $notice = $('<div style="position:fixed;top:20px;right:20px;background:' + bgColor + ';color:' + textColor + ';padding:16px 24px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:99999;font-weight:600;">' + message + '</div>');
        $('body').append($notice);
        setTimeout(function() { $notice.fadeOut(300, function() { $(this).remove(); }); }, 3000);
    }
});
</script>