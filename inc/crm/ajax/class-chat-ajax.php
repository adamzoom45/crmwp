<?php
/**
 * AJAX обработчики чата для личного кабинета
 */
if (!defined('ABSPATH')) exit;

class AKPP_Chat_AJAX {
    
    public function __construct() {
        // Клиентские AJAX
        add_action('wp_ajax_akpp_get_chat_messages', [$this, 'ajax_get_chat_messages']);
        add_action('wp_ajax_akpp_send_chat_message', [$this, 'ajax_send_chat_message']);
        
        // Админские AJAX (для менеджера)
        add_action('wp_ajax_akpp_admin_get_chat_messages', [$this, 'ajax_admin_get_chat_messages']);
        add_action('wp_ajax_akpp_admin_send_chat_message', [$this, 'ajax_admin_send_chat_message']);
        add_action('wp_ajax_akpp_admin_get_chat_users', [$this, 'ajax_admin_get_chat_users']);
    }
    
    /**
     * Получить сообщения для клиента
     */
    public function ajax_get_chat_messages() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Необходимо войти']);
            return;
        }
        
        check_ajax_referer('akpp_chat_action_nonce', 'nonce');
        
        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'akpp_chat_messages';
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE user_id = %d 
             ORDER BY created_at ASC 
             LIMIT 200",
            $user_id
        ));
        
        $formatted = [];
        foreach ($messages as $msg) {
            $formatted[] = [
                'id' => $msg->id,
                'message' => esc_html($msg->message),
                'sender_type' => $msg->sender_type,
                'created_at' => date_i18n('H:i', strtotime($msg->created_at))
            ];
        }
        
        // Помечаем сообщения менеджера как прочитанные
        $wpdb->update($table, 
            ['is_read' => 1], 
            ['user_id' => $user_id, 'sender_type' => 'manager', 'is_read' => 0],
            ['%d'],
            ['%d', '%s', '%d']
        );
        
        wp_send_json_success(['messages' => $formatted]);
    }
    
    /**
     * Отправить сообщение от клиента
     */
    public function ajax_send_chat_message() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Необходимо войти']);
            return;
        }
        
        check_ajax_referer('akpp_chat_action_nonce', 'nonce');
        
        global $wpdb;
        $user_id = get_current_user_id();
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (empty($message)) {
            wp_send_json_error(['message' => 'Сообщение пустое']);
            return;
        }
        
        $table = $wpdb->prefix . 'akpp_chat_messages';
        
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'sender_type' => 'client',
            'message' => $message,
            'is_read' => 0,
            'created_at' => current_time('mysql')
        ]);
        
        wp_send_json_success(['message' => 'Сообщение отправлено']);
    }
    
    /**
     * Админка: Получить список пользователей с чатами
     */
    public function ajax_admin_get_chat_users() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Нет прав']);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_chat_messages';
        
        $users = $wpdb->get_results("
            SELECT DISTINCT u.ID, u.display_name, u.user_email,
                   (SELECT COUNT(*) FROM {$table} WHERE user_id = u.ID AND sender_type = 'client' AND is_read = 0) as unread_count,
                   (SELECT created_at FROM {$table} WHERE user_id = u.ID ORDER BY created_at DESC LIMIT 1) as last_message_at
            FROM {$wpdb->users} u
            INNER JOIN {$table} m ON u.ID = m.user_id
            ORDER BY last_message_at DESC
        ");
        
        wp_send_json_success(['users' => $users]);
    }
    
    /**
     * Админка: Получить сообщения с пользователем
     */
    public function ajax_admin_get_chat_messages() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Нет прав']);
            return;
        }
        
        check_ajax_referer('akpp_chat_action_nonce', 'nonce');
        
        global $wpdb;
        $target_user_id = intval($_POST['user_id'] ?? 0);
        $table = $wpdb->prefix . 'akpp_chat_messages';
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE user_id = %d 
             ORDER BY created_at ASC 
             LIMIT 200",
            $target_user_id
        ));
        
        $formatted = [];
        foreach ($messages as $msg) {
            $formatted[] = [
                'id' => $msg->id,
                'message' => esc_html($msg->message),
                'sender_type' => $msg->sender_type,
                'created_at' => date_i18n('H:i', strtotime($msg->created_at))
            ];
        }
        
        // Помечаем сообщения клиента как прочитанные
        $wpdb->update($table, 
            ['is_read' => 1], 
            ['user_id' => $target_user_id, 'sender_type' => 'client', 'is_read' => 0],
            ['%d'],
            ['%d', '%s', '%d']
        );
        
        wp_send_json_success(['messages' => $formatted]);
    }
    
    /**
     * Админка: Отправить сообщение от менеджера
     */
    public function ajax_admin_send_chat_message() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Нет прав']);
            return;
        }
        
        check_ajax_referer('akpp_chat_action_nonce', 'nonce');
        
        global $wpdb;
        $target_user_id = intval($_POST['user_id'] ?? 0);
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (empty($message) || $target_user_id <= 0) {
            wp_send_json_error(['message' => 'Неверные данные']);
            return;
        }
        
        $table = $wpdb->prefix . 'akpp_chat_messages';
        
        $wpdb->insert($table, [
            'user_id' => $target_user_id,
            'sender_type' => 'manager',
            'message' => $message,
            'is_read' => 0,
            'created_at' => current_time('mysql')
        ]);
        
        wp_send_json_success(['message' => 'Сообщение отправлено']);
    }
}

new AKPP_Chat_AJAX();