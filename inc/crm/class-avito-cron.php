<?php
/**
 * АКПП45 CRM - Фоновая синхронизация с Авито (WP-Cron)
 * Резервный механизм получения диалогов и сообщений на случай сбоя Webhook.
 *
 * @package AKPP_CRM
 * @version 4.3
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

class AKPP_Avito_Cron {

    /**
     * Конструктор: регистрация хуков Cron
     */
    public function __construct() {
        // Добавление собственного интервала (15 минут)
        add_filter('cron_schedules', [$this, 'add_custom_cron_interval']);
        
        // Планирование события при инициализации
        add_action('wp', [$this, 'schedule_sync_event']);
        
        // Хук выполнения задачи
        add_action('akpp_avito_sync_dialogs_hook', [$this, 'run_sync']);
        
        // Очистка при деактивации (если бы это был плагин, но для темы оставим на всякий случай)
        register_deactivation_hook(__FILE__, [$this, 'clear_sync_event']);
    }

    /**
     * Добавление интервала "каждые 15 минут"
     */
    public function add_custom_cron_interval($schedules) {
        $schedules['every_15_minutes'] = [
            'interval' => 900, // 15 минут в секундах
            'display'  => __('Каждые 15 минут', 'akpp-crm')
        ];
        return $schedules;
    }

    /**
     * Планирование события, если оно ещё не запланировано
     */
    public function schedule_sync_event() {
        // Проверяем, включена ли автосинхронизация в настройках (по умолчанию включена)
        $auto_sync_enabled = get_option('akpp_avito_auto_sync', 1);
        
        if (!$auto_sync_enabled) {
            $this->clear_sync_event();
            return;
        }

        if (!wp_next_scheduled('akpp_avito_sync_dialogs_hook')) {
            wp_schedule_event(time(), 'every_15_minutes', 'akpp_avito_sync_dialogs_hook');
        }
    }

    /**
     * Очистка запланированного события
     */
    public function clear_sync_event() {
        $timestamp = wp_next_scheduled('akpp_avito_sync_dialogs_hook');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'akpp_avito_sync_dialogs_hook');
        }
    }

    /**
     * Основная задача синхронизации
     */
    public function run_sync() {
        // 1. Блокировка одновременного выполнения (transient lock на 10 минут)
        // Это предотвращает наложение задач, если сервер медленно отвечает
        if (get_transient('akpp_avito_sync_lock')) {
            error_log('[AKPP Avito Cron] Sync already running, skipping.');
            return;
        }
        set_transient('akpp_avito_sync_lock', true, 10 * MINUTE_IN_SECONDS);

        try {
            if (!class_exists('AKPP_Avito_API')) {
                throw new Exception('Класс AKPP_Avito_API не найден.');
            }

            $api = AKPP_Avito_API::get_instance();
            
            // 2. Получаем последние 30 диалогов из Авито (оптимальный баланс нагрузки и актуальности)
            $dialogs_response = $api->get_dialogs(30);

            if (is_wp_error($dialogs_response)) {
                throw new Exception('Ошибка API при получении диалогов: ' . $dialogs_response->get_error_message());
            }

            // Авито возвращает массив диалогов в ключе 'dialogs' или 'items' в зависимости от версии API
            $dialogs_list = $dialogs_response['dialogs'] ?? ($dialogs_response['items'] ?? []);

            if (empty($dialogs_list)) {
                // Диалогов нет, это нормальная ситуация
                delete_transient('akpp_avito_sync_lock');
                return;
            }

            global $wpdb;
            $dialogs_table  = $wpdb->prefix . 'akpp_avito_dialogs';
            $messages_table = $wpdb->prefix . 'akpp_avito_messages_cache';

            $synced_messages_count = 0;
            $new_dialogs_count = 0;

            // 3. Обрабатываем каждый диалог
            foreach ($dialogs_list as $avito_dialog) {
                $avito_dialog_id = intval($avito_dialog['id'] ?? 0);
                if (!$avito_dialog_id) continue;

                $last_message = $avito_dialog['last_message'] ?? null;
                
                // Проверяем, есть ли диалог у нас в БД
                $local_dialog = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, last_message_id, status FROM {$dialogs_table} WHERE avito_dialog_id = %d",
                    $avito_dialog_id
                ), ARRAY_A);

                $is_new_dialog = !$local_dialog;
                $has_new_messages = false;

                // Если есть новое сообщение и его ID отличается от нашего последнего
                if ($last_message && isset($last_message['id'])) {
                    $avito_last_msg_id = intval($last_message['id']);
                    if (!$local_dialog || $avito_last_msg_id > intval($local_dialog['last_message_id'])) {
                        $has_new_messages = true;
                    }
                }

                // 4. Если диалог новый или есть новые сообщения, забираем их историю
                if ($is_new_dialog || $has_new_messages) {
                    if ($is_new_dialog) {
                        $new_dialogs_count++;
                    }

                    // Запрашиваем последние 10 сообщений диалога
                    $messages_response = $api->get_dialog_messages($avito_dialog_id, 10);
                    
                    if (!is_wp_error($messages_response) && !empty($messages_response['messages'])) {
                        $local_dialog_id = $local_dialog ? $local_dialog['id'] : 0;

                        foreach ($messages_response['messages'] as $msg) {
                            $avito_msg_id = intval($msg['id'] ?? 0);
                            if (!$avito_msg_id) continue;
                            
                            // Проверка на дубликат (идемпотентность)
                            $msg_exists = $wpdb->get_var($wpdb->prepare(
                                "SELECT id FROM {$messages_table} WHERE avito_message_id = %d",
                                $avito_msg_id
                            ));

                            if (!$msg_exists) {
                                $author_id = intval($msg['author_id'] ?? 0);
                                // Если author_id > 0, это обычно клиент (входящее), иначе исходящее от менеджера
                                $direction = ($author_id > 0) ? 'incoming' : 'outgoing';
                                
                                $wpdb->insert(
                                    $messages_table,
                                    [
                                        'avito_message_id' => $avito_msg_id,
                                        'dialog_id'        => $local_dialog_id, // Будет обновлено ниже, если диалог новый
                                        'author_id'        => $author_id,
                                        'message_text'     => sanitize_textarea_field($msg['text'] ?? ''),
                                        'direction'        => $direction,
                                        'created_at'       => sanitize_text_field($msg['created_at'] ?? current_time('mysql')),
                                        'is_read'          => ($direction === 'incoming') ? 0 : 1
                                    ],
                                    ['%d', '%d', '%d', '%s', '%s', '%s', '%d']
                                );
                                $synced_messages_count++;
                            }
                        }
                    }
                }

                // 5. Обновляем или создаём запись диалога в нашей БД
                $client_id   = intval($avito_dialog['user']['id'] ?? 0);
                $client_name = sanitize_text_field($avito_dialog['user']['name'] ?? 'Клиент Авито');
                $item_id     = intval($avito_dialog['item']['id'] ?? 0);

                $dialog_data = [
                    'avito_dialog_id' => $avito_dialog_id,
                    'client_id'       => $client_id,
                    'client_name'     => $client_name,
                    'avito_item_id'   => $item_id,
                    'status'          => $is_new_dialog ? 'active' : $local_dialog['status'],
                    'updated_at'      => current_time('mysql')
                ];

                if ($last_message) {
                    $dialog_data['last_message_id']        = intval($last_message['id']);
                    $dialog_data['last_message_text']      = wp_trim_words($last_message['text'] ?? '', 15, '...');
                    $dialog_data['last_message_date']      = sanitize_text_field($last_message['created_at'] ?? current_time('mysql'));
                    $dialog_data['last_message_direction'] = (intval($last_message['author_id'] ?? 0) > 0) ? 'incoming' : 'outgoing';
                }

                if ($local_dialog) {
                    // Обновляем существующий диалог
                    $wpdb->update(
                        $dialogs_table,
                        $dialog_data,
                        ['avito_dialog_id' => $avito_dialog_id],
                        null,
                        ['%d']
                    );
                    $dialog_db_id = $local_dialog['id'];
                } else {
                    // Создаём новый диалог
                    $dialog_data['created_at']    = current_time('mysql');
                    $dialog_data['unread_count']  = 0; // Будет пересчитано ниже
                    $wpdb->insert($dialogs_table, $dialog_data);
                    $dialog_db_id = $wpdb->insert_id;
                }

                // 6. Пересчёт непрочитанных сообщений для этого диалога
                if ($dialog_db_id) {
                    // Привязываем все сообщения без dialog_id к этому диалогу (на случай race condition)
                    $wpdb->update(
                        $messages_table,
                        ['dialog_id' => $dialog_db_id],
                        ['dialog_id' => 0, 'author_id' => $client_id], // Упрощённая привязка
                        ['%d'],
                        ['%d', '%d']
                    );

                    $unread_count = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$messages_table} WHERE dialog_id = %d AND direction = 'incoming' AND is_read = 0",
                        $dialog_db_id
                    ));

                    $wpdb->update(
                        $dialogs_table,
                        ['unread_count' => $unread_count],
                        ['id' => $dialog_db_id],
                        ['%d'],
                        ['%d']
                    );
                }
            }

            if ($synced_messages_count > 0 || $new_dialogs_count > 0) {
                error_log(sprintf(
                    '[AKPP Avito Cron] Синхронизация завершена. Новых диалогов: %d, новых сообщений: %d', 
                    $new_dialogs_count, 
                    $synced_messages_count
                ));
            }

        } catch (Exception $e) {
            error_log('[AKPP Avito Cron] Ошибка синхронизации: ' . $e->getMessage());
        } finally {
            // 7. Снимаем блокировку в любом случае
            delete_transient('akpp_avito_sync_lock');
        }
    }
}
