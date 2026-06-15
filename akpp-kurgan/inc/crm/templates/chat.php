<?php
/**
 * АКПП45 CRM - Шаблон чата оператора (Internal Chat UI)
 * Интерфейс для сотрудников: список диалогов слева, окно чата справа.
 *
 * @package AKPP_CRM
 * @version 4.2
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

// Проверка прав доступа (только для авторизованных сотрудников/админов)
if (!is_user_logged_in() || !current_user_can('edit_posts')) {
    wp_die(__('Недостаточно прав для доступа к чату.', 'akpp-crm'));
}

global $wpdb;
$current_user_id = get_current_user_id();

// Получаем ID текущего открытого диалога (из URL или по умолчанию первый активный)
$active_dialog_id = isset($_GET['dialog_id']) ? intval($_GET['dialog_id']) : 0;

// =============================================================================
// ПОЛУЧЕНИЕ СПИСКА ДИАЛОГОВ (Заглушка для демонстрации структуры)
// В реальности здесь должен быть запрос к $wpdb->prefix . 'akpp_avito_dialogs' 
// или 'akpp_chat_rooms' с условием WHERE assigned_to = $current_user_id OR assigned_to IS NULL
// =============================================================================
$dialogs_table = $wpdb->prefix . 'akpp_avito_dialogs';
$dialogs = $wpdb->get_results($wpdb->prepare(
    "SELECT id, client_name, last_message_text, last_message_date, unread_count, status 
     FROM $dialogs_table 
     WHERE status != 'archived' 
     ORDER BY last_message_date DESC 
     LIMIT 50",
    ARRAY_A
));

// Если диалогов нет, создаём пустой массив
if (!$dialogs) {
    $dialogs = [];
}

// Получение данных активного диалога (если выбран)
$active_dialog = null;
if ($active_dialog_id > 0) {
    $active_dialog = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $dialogs_table WHERE id = %d",
        $active_dialog_id
    ), ARRAY_A);
}

// Генерация nonce для AJAX-запросов (безопасность)
$chat_nonce = wp_create_nonce('akpp_chat_action_nonce');
?>

<div class="akpp-chat-container">
    
    <!-- ===================================================================== -->
    <!-- ЛЕВАЯ ПАНЕЛЬ: СПИСОК ДИАЛОГОВ -->
    <!-- ===================================================================== -->
    <div class="akpp-chat-sidebar">
        <div class="akpp-chat-sidebar-header">
            <h3><?php _e('Диалоги', 'akpp-crm'); ?></h3>
            <input type="text" id="akpp-chat-search" class="akpp-chat-search-input" placeholder="🔍 Поиск клиента...">
        </div>
        
        <div class="akpp-chat-list" id="akpp-chat-list">
            <?php if (empty($dialogs)) : ?>
                <div class="akpp-chat-empty-state">
                    <p><?php _e('Нет активных диалогов', 'akpp-crm'); ?></p>
                </div>
            <?php else : ?>
                <?php foreach ($dialogs as $dialog) : 
                    $is_active = ($dialog['id'] == $active_dialog_id) ? 'active' : '';
                    $has_unread = ($dialog['unread_count'] > 0) ? 'has-unread' : '';
                    $avatar_letter = mb_substr($dialog['client_name'], 0, 1);
                ?>
                    <a href="<?php echo esc_url(add_query_arg('dialog_id', $dialog['id'], remove_query_arg('dialog_id'))); ?>" 
                       class="akpp-chat-item <?php echo esc_attr($is_active . ' ' . $has_unread); ?>" 
                       data-dialog-id="<?php echo esc_attr($dialog['id']); ?>">
                        
                        <div class="akpp-chat-avatar">
                            <?php echo esc_html($avatar_letter); ?>
                        </div>
                        
                        <div class="akpp-chat-info">
                            <div class="akpp-chat-info-top">
                                <span class="akpp-chat-name"><?php echo esc_html($dialog['client_name']); ?></span>
                                <span class="akpp-chat-time"><?php echo esc_html(human_time_diff(strtotime($dialog['last_message_date']), current_time('timestamp')) . ' ' . __('назад', 'akpp-crm')); ?></span>
                            </div>
                            <div class="akpp-chat-info-bottom">
                                <span class="akpp-chat-preview"><?php echo esc_html(wp_trim_words($dialog['last_message_text'], 8, '...')); ?></span>
                                <?php if ($dialog['unread_count'] > 0) : ?>
                                    <span class="akpp-chat-badge"><?php echo intval($dialog['unread_count']); ?></span>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" class="akpp-dialog-client-name" value="<?php echo esc_attr($dialog['client_name']); ?>">
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- ПРАВАЯ ПАНЕЛЬ: ОКНО ЧАТА -->
    <!-- ===================================================================== -->
    <div class="akpp-chat-main">
        <?php if ($active_dialog) : ?>
            
            <!-- Шапка чата -->
            <div class="akpp-chat-header">
                <div class="akpp-chat-header-info">
                    <h2><?php echo esc_html($active_dialog['client_name']); ?></h2>
                    <span class="akpp-chat-status status-<?php echo esc_attr($active_dialog['status']); ?>">
                        <?php echo esc_html(ucfirst($active_dialog['status'])); ?>
                    </span>
                </div>
                <div class="akpp-chat-header-actions">
                    <button type="button" class="button" id="akpp-chat-archive-btn" data-dialog-id="<?php echo esc_attr($active_dialog['id']); ?>">
                        📦 <?php _e('В архив', 'akpp-crm'); ?>
                    </button>
                </div>
            </div>

            <!-- Область сообщений -->
            <div class="akpp-chat-messages" id="akpp-chat-messages" data-dialog-id="<?php echo esc_attr($active_dialog['id']); ?>">
                <!-- Сообщения будут загружены сюда через AJAX при инициализации -->
                <div class="akpp-chat-loading">
                    <span class="spinner is-active"></span> <?php _e('Загрузка истории...', 'akpp-crm'); ?>
                </div>
            </div>

            <!-- Область ввода сообщения -->
            <div class="akpp-chat-input-area">
                <form id="akpp-chat-form" class="akpp-chat-form">
                    <?php wp_nonce_field('akpp_chat_action', 'akpp_chat_nonce_field'); ?>
                    <input type="hidden" name="action" value="akpp_send_chat_message">
                    <input type="hidden" name="dialog_id" value="<?php echo esc_attr($active_dialog['id']); ?>">
                    <input type="hidden" name="user_id" value="<?php echo esc_attr($current_user_id); ?>">
                    
                    <div class="akpp-chat-input-wrapper">
                        <textarea 
                            name="message_text" 
                            id="akpp-message-input" 
                            class="akpp-chat-textarea" 
                            rows="2" 
                            placeholder="<?php _e('Введите сообщение...', 'akpp-crm'); ?>"
                            required
                        ></textarea>
                        
                        <div class="akpp-chat-actions">
                            <button type="button" class="akpp-chat-attach-btn" title="<?php _e('Прикрепить файл', 'akpp-crm'); ?>">
                                📎
                            </button>
                            <button type="submit" class="button button-primary akpp-chat-send-btn" id="akpp-chat-send-btn">
                                ➤ <?php _e('Отправить', 'akpp-crm'); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        <?php else : ?>
            
            <!-- Состояние "Диалог не выбран" -->
            <div class="akpp-chat-empty-main">
                <div class="akpp-chat-empty-icon">💬</div>
                <h3><?php _e('Выберите диалог для начала работы', 'akpp-crm'); ?></h3>
                <p><?php _e('Или создайте новый чат с клиентом.', 'akpp-crm'); ?></p>
            </div>

        <?php endif; ?>
    </div>
</div>

<!-- Скрытые данные для JS -->
<script type="text/javascript">
    var akpp_chat_config = {
        ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo esc_js($chat_nonce); ?>',
        current_dialog_id: <?php echo $active_dialog ? intval($active_dialog['id']) : 0; ?>,
        current_user_id: <?php echo $current_user_id; ?>,
        strings: {
            sending: '<?php _e('Отправка...', 'akpp-crm'); ?>',
            error: '<?php _e('Ошибка отправки сообщения', 'akpp-crm'); ?>'
        }
    };
</script>
