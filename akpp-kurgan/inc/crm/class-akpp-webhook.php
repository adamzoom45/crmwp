<?php
if (!defined('ABSPATH')) exit;

class AKPP_Webhook {
    
    private $secret_token;

    public function __construct() {
        // Загружаем секретный ключ из настроек
        $this->secret_token = get_option('akpp_webhook_secret', '');

        // Регистрируем endpoint для приема вебхуков (доступен без авторизации)
        add_action('wp_ajax_nopriv_akpp_process_webhook', [$this, 'handle_incoming_webhook']);
        
        // Также добавляем REST API endpoint как более современную альтернативу
        add_action('rest_api_init', [$this, 'register_rest_webhook_route']);
    }

    /**
     * Основная точка входа для обработки вебхуков через admin-ajax.php
     */
    public function handle_incoming_webhook() {
        // 1. Получаем сырые данные (вебхуки часто приходят как JSON)
        $raw_data = file_get_contents('php://input');
        $data = json_decode($raw_data, true);

        // Если данные пришли как POST form-data
        if (empty($data) && !empty($_POST)) {
            $data = $_POST;
        }

        // 2. Проверка секретного ключа (обязательно!)
        $provided_secret = isset($data['secret']) ? sanitize_text_field($data['secret']) : '';
        
        // Также проверяем заголовок, если сервис передает его там (например, Avito или YooKassa)
        $header_secret = isset($_SERVER['HTTP_X_WEBHOOK_SECRET']) ? sanitize_text_field($_SERVER['HTTP_X_WEBHOOK_SECRET']) : '';

        if (!empty($this->secret_token) && ($provided_secret !== $this->secret_token && $header_secret !== $this->secret_token)) {
            $this->log_webhook('security_error', 'Invalid secret token', $data);
            wp_die('Unauthorized', 401);
        }

        // 3. Маршрутизация по типу события
        $event_type = isset($data['type']) ? sanitize_text_field($data['type']) : 'unknown';
        $payload = isset($data['payload']) ? $data['payload'] : $data;

        $response = ['status' => 'ignored', 'message' => 'Unknown event type'];

        switch ($event_type) {
            case 'avito_new_message':
                $response = $this->process_avito_message($payload);
                break;
            
            case 'payment_status_changed':
                $response = $this->process_payment_status($payload);
                break;
                
            case 'test_ping':
                $response = ['status' => 'success', 'message' => 'Pong! Webhook is working.'];
                break;

            default:
                $this->log_webhook('ignored', "Unknown event type: {$event_type}", $payload);
                break;
        }

        // 4. Возвращаем ответ (важно вернуть 200 OK, чтобы сервис не пытался отправить вебхук повторно)
        if (isset($response['status']) && $response['status'] === 'success') {
            wp_send_json_success($response);
        } else {
            // Даже при ошибке обработки возвращаем 200, но с флагом ошибки в теле, 
            // чтобы не спамить внешние сервисы повторными попытками
            wp_send_json($response, 200);
        }
    }

    /**
     * Регистрация REST API маршрута для вебхуков (рекомендуемый способ)
     * URL: /wp-json/akpp/v1/webhook
     */
    public function register_rest_webhook_route() {
        register_rest_route('akpp/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_rest_webhook'],
            'permission_callback' => '__return_true', // Разрешаем всем, проверка внутри по secret key
        ]);
    }

    public function handle_rest_webhook($request) {
        $data = $request->get_json_params() ?: $request->get_body_params();
        
        $provided_secret = isset($data['secret']) ? sanitize_text_field($data['secret']) : '';
        $header_secret = $request->get_header('x-webhook-secret');

        if (!empty($this->secret_token) && ($provided_secret !== $this->secret_token && $header_secret !== $this->secret_token)) {
            $this->log_webhook('security_error', 'Invalid secret token (REST)', $data);
            return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        $event_type = isset($data['type']) ? sanitize_text_field($data['type']) : 'unknown';
        $payload = isset($data['payload']) ? $data['payload'] : $data;

        if ($event_type === 'avito_new_message') {
            $result = $this->process_avito_message($payload);
            return rest_ensure_response($result);
        }

        return rest_ensure_response(['status' => 'ignored', 'message' => 'Unknown event type']);
    }

    /**
     * Обработка нового сообщения от Авито
     */
    private function process_avito_message($payload) {
        global $wpdb;
        
        $dialog_id = sanitize_text_field($payload['dialog_id'] ?? '');
        $message_id = sanitize_text_field($payload['message_id'] ?? '');
        $text = sanitize_textarea_field($payload['text'] ?? '');
        $author = ($payload['author'] === 'user') ? 'client' : 'manager';
        $created_at = isset($payload['created_at']) ? date('Y-m-d H:i:s', strtotime($payload['created_at'])) : current_time('mysql');

        if (empty($dialog_id) || empty($message_id)) {
            return ['status' => 'error', 'message' => 'Missing dialog_id or message_id'];
        }

        // Сохраняем в кэш сообщений Авито
        $table = $wpdb->prefix . 'akpp_avito_messages_cache';
        $wpdb->replace($table, [
            'dialog_id' => $dialog_id,
            'message_id' => $message_id,
            'author' => $author,
            'text' => $text,
            'created_at' => $created_at
        ]);

        // Обновляем статус диалога на "непрочитанный"
        $dialog_table = $wpdb->prefix . 'akpp_avito_dialogs';
        $wpdb->update($dialog_table, 
            ['is_read' => 0, 'last_message' => $text, 'last_message_at' => $created_at],
            ['dialog_id' => $dialog_id]
        );

        // Опционально: отправить Push-уведомление менеджеру
        if (class_exists('AKPP_Push') && $author === 'client') {
            $push = new AKPP_Push();
            $push->broadcast_notification(
                'Новое сообщение с Авито',
                'Клиент написал в диалог #' . $dialog_id,
                ['screen' => 'avito_chat', 'dialog_id' => $dialog_id]
            );
        }

        $this->log_webhook('success', "Avito message saved: {$message_id}", $payload);
        return ['status' => 'success', 'message' => 'Message processed'];
    }

    /**
     * Обработка изменения статуса оплаты (заготовка под интеграцию с ЮKassa/Robokassa)
     */
    private function process_payment_status($payload) {
        global $wpdb;
        
        $deal_id = intval($payload['deal_id'] ?? 0);
        $status = sanitize_text_field($payload['status'] ?? ''); // например, 'succeeded'

        if ($deal_id > 0 && $status === 'succeeded') {
            $table = $wpdb->prefix . 'akpp_deals';
            $wpdb->update($table, 
                ['status' => 'completed'], 
                ['id' => $deal_id]
            );
            
            $this->log_webhook('success', "Payment succeeded for deal #{$deal_id}", $payload);
            return ['status' => 'success', 'message' => 'Deal marked as completed'];
        }

        return ['status' => 'ignored', 'message' => 'Invalid deal_id or status'];
    }

    /**
     * Логирование вебхуков для отладки
     */
    private function log_webhook($status, $message, $data) {
        // Используем стандартный error_log, но с четким префиксом для удобного поиска
        $log_entry = sprintf(
            '[AKPP Webhook] [%s] %s | Payload: %s',
            date('Y-m-d H:i:s'),
            $message,
            wp_json_encode($data)
        );
        error_log($log_entry);
    }
}

// Инициализация
new AKPP_Webhook();
