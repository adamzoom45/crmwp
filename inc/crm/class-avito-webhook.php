<?php
/**
 * АКПП45 CRM - Обработчик Webhook от Авито
 * Принимает, валидирует и сохраняет входящие сообщения через REST API.
 *
 * @package AKPP_CRM
 * @version 4.3
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

class AKPP_Avito_Webhook {

    /**
     * Конструктор: регистрация REST маршрута
     */
    public function __construct() {
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
                        return in_array($param, ['message.created', 'message.updated', 'dialog.created'], true);
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
        
        // 1. Базовая валидация структуры
        if (empty($data) || empty($data['type']) || empty($data['object'])) {
            return new WP_REST_Response(['error' => 'Invalid payload structure'], 400);
        }

        $type   = sanitize_text_field($data['type']);
        $object = $data['object'];

        try {
            // 2. Маршрутизация по типу события
            if ($type === 'message.created' || $type === 'message.updated') {
                $this->process_message($object);
            } elseif ($type === 'dialog.created') {
                $this->process_new_dialog($object);
            }

            // 3. КРИТИЧЕСКИ ВАЖНО: Быстрый ответ 200 OK, чтобы Авито не делал ретраи
            return new WP_REST_Response(['success' => true, 'message' => 'Webhook processed'], 200);

        } catch (Exception $e) {
            // Логируем ошибку, но всё равно возвращаем 200, чтобы остановить цикл ретраев Авито
            error_log('[AKPP Avito Webhook] Exception: ' . $e->getMessage());
            return new WP_REST_Response(['success' => true, 'message' => 'Logged error, but accepted'], 200);
        }
    }

    /**
     * Обработка нового или обновленного сообщения
     *
     * @param array $message_data Данные сообщения от Авито
     */
    private function process_message($message_data) {
        global $wpdb;
        
        $messages_table = $wpdb->prefix . 'akpp_avito_messages_cache';
        $dialogs_table  = $wpdb->prefix . 'akpp_avito_dialogs';

        $avito_message_id = intval($message_data['id'] ?? 0);
        $avito_dialog_id  = intval($message_data['dialog_id'] ?? 0);
        $author_id        = intval($message_data['author_id'] ?? 0);
        $text             = sanitize_textarea_field($message_data['text'] ?? '');
        $created_at       = sanitize_text_field($message_data['created_at'] ?? current_time('mysql'));

        if (!$avito_message_id || !$avito_dialog_id) {
            throw new Exception('Missing message_id or dialog_id in webhook payload');
        }

        // 1. ИДЕМПОТЕНТНОСТЬ: Проверка на дубликат
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$messages_table} WHERE avito_message_id = %d",
            $avito_message_id
        ));

        if ($exists) {
            return; // Сообщение уже обработано, выходим тихо
        }

        // 2. Определение направления (если author_id не наш сотрудник, считаем входящим)
        // В упрощенном виде: если это webhook от Авито, обычно это входящее от клиента
        $direction = 'incoming'; 

        // 3. Сохранение сообщения в кэш
        $wpdb->insert(
            $messages_table,
            [
                'avito_message_id' => $avito_message_id,
                'dialog_id'        => $avito_dialog_id, // Связь по avito_dialog_id (будет обновлена ниже при наличии локального ID)
                'author_id'        => $author_id,
                'message_text'     => $text,
                'direction'        => $direction,
                'created_at'       => $created_at,
                'is_read'          => 0
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%d']
        );

        // 4. Обновление сводки диалога
        // Сначала находим наш внутренний ID диалога по avito_dialog_id
        $local_dialog = $wpdb->get_row($wpdb->prepare(
            "SELECT id, unread_count FROM {$dialogs_table} WHERE avito_dialog_id = %d",
            $avito_dialog_id
        ), ARRAY_A);

        if ($local_dialog) {
            $new_unread_count = $direction === 'incoming' ? (intval($local_dialog['unread_count']) + 1) : 0;
            
            $wpdb->update(
                $dialogs_table,
                [
                    'last_message_id'        => $avito_message_id,
                    'last_message_text'      => wp_trim_words($text, 15, '...'),
                    'last_message_date'      => $created_at,
                    'last_message_direction' => $direction,
                    'unread_count'           => $new_unread_count,
                    'updated_at'             => current_time('mysql')
                ],
                ['id' => $local_dialog['id']],
                ['%d', '%s', '%s', '%s', '%d', '%s'],
                ['%d']
            );
        } else {
            // Fallback: Если диалог пришел раньше, чем мы его создали, создаем базовую запись
            $wpdb->insert(
                $dialogs_table,
                [
                    'avito_dialog_id'        => $avito_dialog_id,
                    'client_id'              => $author_id,
                    'client_name'            => 'Клиент Авито #' . $author_id,
                    'status'                 => 'active',
                    'unread_count'           => ($direction === 'incoming' ? 1 : 0),
                    'last_message_id'        => $avito_message_id,
                    'last_message_text'      => wp_trim_words($text, 15, '...'),
                    'last_message_date'      => $created_at,
                    'last_message_direction' => $direction,
                    'created_at'             => current_time('mysql'),
                    'updated_at'             => current_time('mysql')
                ],
                ['%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
            );
        }
    }

    /**
     * Обработка создания нового диалога
     *
     * @param array $dialog_data Данные диалога от Авито
     */
    private function process_new_dialog($dialog_data) {
        global $wpdb;
        $dialogs_table = $wpdb->prefix . 'akpp_avito_dialogs';
        
        $avito_dialog_id = intval($dialog_data['id'] ?? 0);
        if (!$avito_dialog_id) {
            return;
        }

        // Проверка на существование
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$dialogs_table} WHERE avito_dialog_id = %d",
            $avito_dialog_id
        ));

        if (!$exists) {
            $client_id = intval($dialog_data['user']['id'] ?? 0);
            $client_name = sanitize_text_field($dialog_data['user']['name'] ?? 'Неизвестный клиент');
            $item_id = intval($dialog_data['item']['id'] ?? 0);

            $wpdb->insert(
                $dialogs_table,
                [
                    'avito_dialog_id' => $avito_dialog_id,
                    'avito_item_id'   => $item_id,
                    'client_id'       => $client_id,
                    'client_name'     => $client_name,
                    'status'          => 'active',
                    'unread_count'    => 0,
                    'created_at'      => current_time('mysql'),
                    'updated_at'      => current_time('mysql')
                ],
                ['%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s']
            );
        }
    }
}
