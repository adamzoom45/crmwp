<?php
/**
 * АКПП45 CRM - AJAX обработчики для чатов
 * Обрабатывает отправку сообщений из внутреннего чата CRM и клиентского чата на фронтенде.
 *
 * @package AKPP_CRM
 * @version 4.2
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

class AKPP_Chat_AJAX {

    /**
     * Конструктор: регистрация хуков AJAX
     */
    public function __construct() {
        // Внутренний чат CRM (только для авторизованных сотрудников)
        add_action('wp_ajax_akpp_send_chat_message', [$this, 'handle_internal_chat_message']);
        
        // Клиентский чат на фронтенде (для авторизованных пользователей сайта)
        add_action('wp_ajax_akpp_send_frontend_chat_message', [$this, 'handle_frontend_chat_message']);
    }

    /**
     * Обработчик отправки сообщения из внутреннего чата CRM (Оператор -> Клиент Авито)
     */
    public function handle_internal_chat_message() {
        // 1. Проверка безопасности (Nonce)
        check_ajax_referer('akpp_chat_action', 'akpp_chat_nonce_field');

        // 2. Проверка прав доступа
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Недостаточно прав для отправки сообщения.', 'akpp-crm')], 403);
        }

        // 3. Получение и санитизация данных
        $dialog_id    = isset($_POST['dialog_id']) ? intval($_POST['dialog_id']) : 0;
        $message_text = isset($_POST['message_text']) ? sanitize_textarea_field($_POST['message_text']) : '';
        $user_id      = get_current_user_id();

        if (empty($dialog_id) || empty($message_text)) {
            wp_send_json_error(['message' => __('Некорректные данные: указан диалог и текст сообщения.', 'akpp-crm')], 400);
        }

        global $wpdb;
        $messages_table = $wpdb->prefix . 'akpp_avito_messages_cache';
        $dialogs_table  = $wpdb->prefix . 'akpp_avito_dialogs';

        try {
            // 4. Сохранение сообщения в локальную БД (как исходящее)
            $wpdb->insert(
                $messages_table,
                [
                    'dialog_id'        => $dialog_id,
                    'author_id'        => $user_id, // ID сотрудника CRM
                    'message_text'     => $message_text,
                    'direction'        => 'outgoing',
                    'created_at'       => current_time('mysql'),
                    'is_read'          => 1
                ],
                ['%d', '%d', '%s', '%s', '%s', '%d']
            );
            $message_id = $wpdb->insert_id;

            // 5. Обновление сводки диалога
            $wpdb->update(
                $dialogs_table,
                [
                    'last_message_id'      => $message_id,
                    'last_message_text'    => wp_trim_words($message_text, 15, '...'),
                    'last_message_date'    => current_time('mysql'),
                    'last_message_direction' => 'outgoing',
                    'unread_count'         => 0, // Сбрасываем непрочитанные, так как мы сами написали
                    'updated_at'           => current_time('mysql')
                ],
                ['id' => $dialog_id],
                [null, null, '%s', '%s', '%d', '%s'],
                ['%d']
            );

            // 6. Отправка сообщения в Авито через API (если класс доступен)
            if (class_exists('AKPP_Avito_API')) {
                $avito_api = AKPP_Avito_API::get_instance();
                
                // Получаем avito_dialog_id из нашей БД
                $avito_dialog_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT avito_dialog_id FROM $dialogs_table WHERE id = %d",
                    $dialog_id
                ));

                if ($avito_dialog_id) {
                    $api_response = $avito_api->send_message($avito_dialog_id, $message_text, 'text');
                    
                    if (is_wp_error($api_response)) {
                        // Логируем ошибку, но не прерываем сохранение в локальную БД
                        error_log('[AKPP Chat AJAX] Avito API Send Error: ' . $api_response->get_error_message());
                    }
                }
            }

            // 7. Успешный ответ для фронтенда
            wp_send_json_success([
                'message_id'   => $message_id,
                'message_text' => nl2br(esc_html($message_text)),
                'created_at'   => date('H:i', strtotime(current_time('mysql'))),
                'direction'    => 'outgoing'
            ]);

        } catch (Exception $e) {
            error_log('[AKPP Chat AJAX] Database Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Ошибка базы данных при сохранении сообщения.', 'akpp-crm')], 500);
        }
    }

    /**
     * Обработчик отправки сообщения из клиентского чата на фронтенде (Клиент -> Менеджер)
     */
    public function handle_frontend_chat_message() {
        // 1. Проверка безопасности
        check_ajax_referer('akpp_frontend_chat_action', 'akpp_frontend_chat_nonce_field');

        // 2. Проверка авторизации
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Необходима авторизация.', 'akpp-crm')], 401);
        }

        // 3. Получение и санитизация данных
        $message_text = isset($_POST['message_text']) ? sanitize_textarea_field($_POST['message_text']) : '';
        $user_id      = get_current_user_id();
        $current_user = wp_get_current_user();

        if (empty($message_text)) {
            wp_send_json_error(['message' => __('Сообщение не может быть пустым.', 'akpp-crm')], 400);
        }

        global $wpdb;
        $messages_table = $wpdb->prefix . 'akpp_chat_messages';

        try {
            // 4. Сохранение сообщения в БД клиентского чата
            $wpdb->insert(
                $messages_table,
                [
                    'user_id'      => $user_id,
                    'sender_id'    => $user_id,
                    'sender_name'  => $current_user->display_name,
                    'message_text' => $message_text,
                    'created_at'   => current_time('mysql'),
                    'is_read'      => 0 // Менеджер ещё не прочитал
                ],
                ['%d', '%d', '%s', '%s', '%s', '%d']
            );
            $message_id = $wpdb->insert_id;

            // 5. (Опционально) Здесь можно добавить отправку уведомления менеджеру в Telegram
            // do_action('akpp_frontend_new_message', $user_id, $message_text);

            // 6. Успешный ответ
            wp_send_json_success([
                'message_id'   => $message_id,
                'message_text' => nl2br(esc_html($message_text)),
                'created_at'   => date('H:i', strtotime(current_time('mysql'))),
                'sender_name'  => esc_html($current_user->display_name)
            ]);

        } catch (Exception $e) {
            error_log('[AKPP Chat AJAX] Frontend Database Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Ошибка при отправке сообщения.', 'akpp-crm')], 500);
        }
    }
}
