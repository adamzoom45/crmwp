<?php
/**
 * АКПП45 CRM - Обработчик Webhook от Авито
 * Принимает, валидирует и сохраняет входящие сообщения через REST API.
 *
 * @package AKPP_CRM
 * @version 4.2
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

class AKPP_Avito_Webhook {

    /**
     * Конструктор
     */
    public function __construct() {
        // Регистрация REST маршрута при инициализации REST API
        add_action('rest_api_init', [$this, 'register_webhook_route']);
    }

    /**
     * Регистрация маршрута /wp-json/akpp/v1/avito-webhook
     */
    public function register_webhook_route() {
        register_rest_route('akpp/v1', '/avito-webhook', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_incoming_webhook'],
            'permission_callback' => '__return_true', // Webhook доступен публично, валидация внутри по структуре данных
            'args'                => [
                'type'   => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return in_array($param, ['message.created', 'message.updated', 'dialog.created']);
                    }
                ],
                'object' => [
                    'required' => true,
                    'type'     => 'object'
                ]
            ]
        ]);
    }

    /**
     * Основной обработчик входящего Webhook
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_incoming_webhook($request) {
        $data = $request->get_json_params();
        
        if (empty($data) || empty($data['type']) || empty($data['object'])) {
            return new WP_REST_Response(['error' => 'Invalid payload structure'], 400);
        }

        $type   = sanitize_text_field($data['type']);
        $object = $data['object'];

        // Логируем входящий запрос для отладки (опционально)
        error_log('[AKPP Avito Webhook] Received: ' . $type);

        try {
            if ($type === 'message.created' || $type === 'message.updated') {
                $this->process_message($object);
            } elseif ($type === 'dialog.created') {
                $this->process_new_dialog($object);
            }

            // Авито ожидает быстрый ответ 200 OK, иначе будет повторять запрос
            return new WP_REST_Response(['success' => true, 'message' => 'Webhook processed'], 200);

        } catch (Exception $e) {
            error_log('[AKPP Avito Webhook] Error: ' . $e->getMessage());
            return new WP_REST_Response(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Обработка нового сообщения
     *
     * @param array $message_data Данные сообщения от Авито
     */
    private function process_message($message_data) {
        global $wpdb;
        
        $messages_table = $wpdb->prefix . 'akpp_avito_messages_cache';
        $dialogs_table  = $wpdb->prefix . 'akpp_avito_dialogs';

        $avito_message_id = intval($message_data['id']);
        $dialog_id        = intval($message_data['dialog_id']);
        $author_id        = intval($message_data['author_id']);
        $text             = sanitize_textarea_field($message_data['text'] ?? '');
        $created_at       = sanitize_text_field($message_data['created_at'] ?? current_time('mysql'));
        $direction        = ($author_id > 0) ? 'incoming' : 'outgoing'; // Упрощённая логика: если author_id есть, это клиент

        // 1. Сохраняем или обновляем сообщение в кэше
        // Используем REPLACE INTO или проверку на существование, чтобы избежать дублей при повторных webhook
        $existing_msg = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $messages_table WHERE avito_message_id = %d",
            $avito_message_id
        ));

        if (!$existing_msg) {
            $wpdb->insert(
                $messages_table,
                [
                    'avito_message_id' => $avito_message_id,
                    'dialog_id'        => $dialog_id,
                    'author_id'        => $author_id,
                    'message_text'     => $text,
                    'direction'        => $direction,
                    'created_at'       => $created_at,
                    'is_read'          => ($direction === 'incoming') ? 0 : 1
                ],
                ['%d', '%d', '%d', '%s', '%s', '%s', '%d']
            );
        }

        // 2. Обновляем сводку диалога (последнее сообщение, время, счётчик непрочитанных)
        $update_data = [
            'last_message_id'   => $avito_message_id,
            'last_message_text' => wp_trim_words($text, 15, '...'),
            'last_message_date' => $created_at,
            'last_message_direction' => $direction,
            'updated_at'        => current_time('mysql')
        ];

        // Если сообщение входящее, увеличиваем счётчик непрочитанных
        if ($direction === 'incoming') {
            // Получаем текущий счётчик
            $current_unread = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT unread_count FROM $dialogs_table WHERE avito_dialog_id = %d",
                $dialog_id
            ));
            $update_data['unread_count'] = $current_unread + 1;
        }

        // Обновляем или создаём запись диалога, если её вдруг нет
        $dialog_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $dialogs_table WHERE avito_dialog_id = %d",
            $dialog_id
        ));

        if ($dialog_exists) {
            $wpdb->update(
                $dialogs_table,
                $update_data,
                ['avito_dialog_id' => $dialog_id],
                null, // Форматы update
                ['%d']
            );
        } else {
            // Fallback: если диалог ещё не создан в нашей БД, создаём базовую запись
            $update_data['avito_dialog_id'] = $dialog_id;
            $update_data['client_id']       = $author_id;
            $update_data['client_name']     = 'Клиент Авито #' . $author_id;
            $update_data['status']          = 'active';
            $update_data['created_at']      = current_time('mysql');
            
            $wpdb->insert($dialogs_table, $update_data);
        }

        // 3. (Опционально) Здесь можно добавить триггер отправки Push/Telegram уведомления менеджеру
        // do_action('akpp_avito_new_message_received', $dialog_id, $text, $author_id);
    }

    /**
     * Обработка создания нового диалога (заглушка для будущей реализации)
     *
     * @param array $dialog_data
     */
    private function process_new_dialog($dialog_data) {
        global $wpdb;
        $dialogs_table = $wpdb->prefix . 'akpp_avito_dialogs';
        
        $dialog_id = intval($dialog_data['id']);
        
        // Проверяем, нет ли уже такого диалога
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $dialogs_table WHERE avito_dialog_id = %d",
            $dialog_id
        ));

        if (!$exists) {
            $wpdb->insert(
                $dialogs_table,
                [
                    'avito_dialog_id' => $dialog_id,
                    'client_id'       => intval($dialog_data['user_id'] ?? 0),
                    'client_name'     => sanitize_text_field($dialog_data['user_name'] ?? 'Неизвестный клиент'),
                    'status'          => 'active',
                    'unread_count'    => 0,
                    'created_at'      => current_time('mysql'),
                    'updated_at'      => current_time('mysql')
                ],
                ['%d', '%d', '%s', '%s', '%d', '%s', '%s']
            );
        }
    }
}
