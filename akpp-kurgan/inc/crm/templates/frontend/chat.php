<?php
/**
 * АКПП45 CRM - Клиентский чат (Frontend)
 * Интерфейс для зарегистрированного пользователя сайта для общения с менеджером.
 *
 * @package AKPP_CRM
 * @version 4.2
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

// =============================================================================
// ПРОВЕРКА АВТОРИЗАЦИИ
// =============================================================================
if (!is_user_logged_in()) {
    ?>
    <div class="akpp-frontend-chat-login-prompt">
        <h3><?php _e('Доступ к чату ограничен', 'akpp-crm'); ?></h3>
        <p><?php _e('Пожалуйста, войдите в свой личный кабинет или зарегистрируйтесь, чтобы общаться с менеджером.', 'akpp-crm'); ?></p>
        <div class="akpp-chat-auth-buttons">
            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="button button-primary">
                <?php _e('Войти', 'akpp-crm'); ?>
            </a>
            <a href="<?php echo esc_url(wp_registration_url()); ?>" class="button button-secondary">
                <?php _e('Регистрация', 'akpp-crm'); ?>
            </a>
        </div>
    </div>
    <?php
    return; // Прерываем выполнение шаблона
}

// =============================================================================
// ПОЛУЧЕНИЕ ДАННЫХ ПОЛЬЗОВАТЕЛЯ И ЧАТА
// =============================================================================
global $wpdb;
$current_user_id = get_current_user_id();
$current_user    = wp_get_current_user();

// Таблица сообщений чата (предполагается структура из плана)
$messages_table = $wpdb->prefix . 'akpp_chat_messages';

// Получаем последние 50 сообщений текущего пользователя
// Примечание: В реальном проекте здесь может быть JOIN с таблицей диалогов (chat_rooms)
$messages = $wpdb->get_results($wpdb->prepare(
    "SELECT id, sender_id, sender_name, message_text, created_at, is_read 
     FROM $messages_table 
     WHERE user_id = %d 
     ORDER BY created_at ASC 
     LIMIT 50",
    $current_user_id
));

// Определяем имя менеджера (заглушка: берём из мета-данных или последнего сообщения от админа)
$manager_name = __('Менеджер АКПП45', 'akpp-crm');
$manager_status = 'online'; // online, offline, typing

if (!empty($messages)) {
    foreach ($messages as $msg) {
        if ($msg->sender_id != $current_user_id) {
            $manager_name = $msg->sender_name;
            break;
        }
    }
}

// Генерация nonce для безопасной отправки сообщений через AJAX
$chat_nonce = wp_create_nonce('akpp_frontend_chat_nonce');
?>

<div class="akpp-frontend-chat-container">
    
    <!-- Шапка чата -->
    <div class="akpp-chat-header">
        <div class="akpp-chat-header-avatar">
            <?php echo esc_html(mb_substr($manager_name, 0, 1)); ?>
        </div>
        <div class="akpp-chat-header-info">
            <h3 class="akpp-chat-manager-name"><?php echo esc_html($manager_name); ?></h3>
            <span class="akpp-chat-status-indicator status-<?php echo esc_attr($manager_status); ?>">
                <?php echo $manager_status === 'online' ? __('Онлайн', 'akpp-crm') : __('Ожидает ответа', 'akpp-crm'); ?>
            </span>
        </div>
    </div>

    <!-- Область сообщений -->
    <div class="akpp-chat-messages-area" id="akpp-frontend-chat-messages">
        <?php if (empty($messages)) : ?>
            <div class="akpp-chat-welcome-message">
                <p><?php _e('Здравствуйте! Чем мы можем вам помочь? Напишите ваш вопрос, и менеджер скоро ответит.', 'akpp-crm'); ?></p>
            </div>
        <?php else : ?>
            <?php foreach ($messages as $msg) : 
                $is_mine = ($msg->sender_id == $current_user_id);
                $time = date('H:i', strtotime($msg->created_at));
            ?>
                <div class="akpp-message-bubble <?php echo $is_mine ? 'message-mine' : 'message-theirs'; ?>">
                    <?php if (!$is_mine) : ?>
                        <div class="akpp-message-sender"><?php echo esc_html($msg->sender_name); ?></div>
                    <?php endif; ?>
                    
                    <div class="akpp-message-text">
                        <?php echo nl2br(esc_html($msg->message_text)); ?>
                    </div>
                    
                    <div class="akpp-message-meta">
                        <span class="akpp-message-time"><?php echo esc_html($time); ?></span>
                        <?php if ($is_mine) : ?>
                            <span class="akpp-message-status <?php echo $msg->is_read ? 'read' : 'sent'; ?>">
                                <?php echo $msg->is_read ? '✓✓' : '✓'; ?>
                            </span>
                        <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Область ввода -->
    <div class="akpp-chat-input-area">
        <form id="akpp-frontend-chat-form" class="akpp-chat-form">
            <?php wp_nonce_field('akpp_frontend_chat_action', 'akpp_frontend_chat_nonce_field'); ?>
            <input type="hidden" name="action" value="akpp_send_frontend_chat_message">
            <input type="hidden" name="user_id" value="<?php echo esc_attr($current_user_id); ?>">
            
            <div class="akpp-chat-input-wrapper">
                <textarea 
                    name="message_text" 
                    id="akpp-frontend-message-input" 
                    class="akpp-chat-textarea" 
                    rows="1" 
                    placeholder="<?php _e('Напишите сообщение...', 'akpp-crm'); ?>"
                    required
                    maxlength="2000"
                ></textarea>
                
                <button type="submit" class="akpp-chat-send-btn" id="akpp-frontend-send-btn" disabled>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 2L11 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M22 2L15 22L11 13L2 9L22 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
            <div class="akpp-chat-hint">
                <?php _e('Нажмите Enter для отправки, Shift+Enter для новой строки', 'akpp-crm'); ?>
            </div>
        </form>
    </div>
</div>

<!-- Конфигурация для Frontend JS -->
<script type="text/javascript">
    var akpp_frontend_chat_config = {
        ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo esc_js($chat_nonce); ?>',
        user_id: <?php echo intval($current_user_id); ?>,
        strings: {
            sending: '<?php _e('Отправка...', 'akpp-crm'); ?>',
            error: '<?php _e('Ошибка отправки. Попробуйте позже.', 'akpp-crm'); ?>',
            empty: '<?php _e('Сообщение не может быть пустым', 'akpp-crm'); ?>'
        }
    };
</script>
