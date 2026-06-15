<?php
if (!defined('ABSPATH')) exit;

class AKPP_VIN_Decoder {
    
    private $api_url = 'https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVinValues/';

    public function __construct() {
        // AJAX хук уже зарегистрирован в class-akpp-ajax.php, 
        // но мы можем добавить его и здесь для автономности, если класс загружается отдельно
        // add_action('wp_ajax_akpp_decode_vin', [$this, 'ajax_decode']);
    }

    /**
     * Основная логика декодирования VIN
     *
     * @param string $vin 17-значный VIN-код
     * @return array Расшифрованные данные или ошибка
     */
    public function decode($vin) {
        // 1. Очистка и валидация VIN
        $vin = strtoupper(trim($vin));
        $vin = preg_replace('/[^A-HJ-NPR-Z0-9]/', '', $vin); // Удаляем недопустимые символы (I, O, Q запрещены)

        if (strlen($vin) !== 17) {
            return [
                'success' => false,
                'message' => 'VIN должен содержать ровно 17 символов (без букв I, O, Q)'
            ];
        }

        // 2. Проверка локального кэша
        $cached_data = $this->get_from_cache($vin);
        if ($cached_data) {
            return [
                'success' => true,
                'source' => 'cache',
                'data' => $cached_data
            ];
        }

        // 3. Запрос к API NHTSA
        $response = wp_remote_get($this->api_url . urlencode($vin) . '?format=json', [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'AKPP45-CRM/1.0'
            ]
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Ошибка соединения с API: ' . $response->get_error_message()
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['Results']) || empty($body['Results'][0])) {
            return [
                'success' => false,
                'message' => 'Не удалось расшифровать VIN. Проверьте корректность номера.'
            ];
        }

        $result = $body['Results'][0];

        // 4. Форматирование данных
        $decoded_data = [
            'brand'       => sanitize_text_field($result['Make'] ?? 'Неизвестно'),
            'model'       => sanitize_text_field($result['Model'] ?? 'Неизвестно'),
            'year'        => intval($result['ModelYear'] ?? 0),
            'engine'      => sanitize_text_field($result['EngineConfiguration'] ?? '') . ' ' . sanitize_text_field($result['DisplacementL'] ?? '') . 'L',
            'body_style'  => sanitize_text_field($result['BodyClass'] ?? ''),
            'drive_type'  => sanitize_text_field($result['DriveType'] ?? ''),
            'country'     => sanitize_text_field($result['PlantCountry'] ?? ''),
            'raw_data'    => $result // Сохраняем сырые данные на случай, если понадобится что-то еще
        ];

        // 5. Сохранение в кэш
        $this->save_to_cache($vin, $decoded_data);

        return [
            'success' => true,
            'source' => 'api',
            'data' => $decoded_data
        ];
    }

    /**
     * Получение данных из кэша
     */
    private function get_from_cache($vin) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_vin_cache';
        
        $cached = $wpdb->get_row($wpdb->prepare(
            "SELECT response_data FROM $table WHERE vin = %s",
            $vin
        ), ARRAY_A);

        if ($cached && !empty($cached['response_data'])) {
            return json_decode($cached['response_data'], true);
        }

        return null;
    }

    /**
     * Сохранение данных в кэш
     */
    private function save_to_cache($vin, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'akpp_vin_cache';
        
        $wpdb->replace($table, [
            'vin' => $vin,
            'response_data' => wp_json_encode($data)
        ]);
    }

    /**
     * AJAX обработчик (дублирует логику из class-akpp-ajax.php для удобства)
     */
    public function ajax_decode() {
        check_ajax_referer('akpp_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options') && !current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Недостаточно прав']);
        }

        $vin = sanitize_text_field($_POST['vin'] ?? '');
        
        if (empty($vin)) {
            wp_send_json_error(['message' => 'VIN не указан']);
        }

        $result = $this->decode($vin);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }
}
