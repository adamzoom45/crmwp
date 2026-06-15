<?php
if (!defined('ABSPATH')) exit;

class AKPP_Body_Decoder {
    
    public function __construct() {
        // AJAX хук для проверки совместимости и декодирования
        add_action('wp_ajax_akpp_decode_body', [$this, 'ajax_decode_body']);
        add_action('wp_ajax_akpp_check_compatibility', [$this, 'ajax_check_compatibility']);
    }

    /**
     * Декодирование кузовного номера (Frame/Chassis number)
     * Особенно полезно для JDM рынка (например, ACU30-1234567)
     *
     * @param string $body_number Кузовной номер
     * @return array Распарсенные данные
     */
    public function decode_body_number($body_number) {
        $body_number = strtoupper(trim($body_number));
        
        // Регулярное выражение для японского формата: Буквы+Цифры (модель) - Цифры (номер кузова)
        // Пример: ACU30-1234567, ZZE121-0123456
        if (preg_match('/^([A-Z]{1,3}[0-9]{2,4})-?([0-9]{4,7})$/', $body_number, $matches)) {
            $model_code = $matches[1];
            $serial_number = $matches[2];
            
            // Простая эвристика для определения поколения/модели по коду
            $model_info = $this->get_model_info_by_code($model_code);

            return [
                'success' => true,
                'type' => 'jdm_frame',
                'full_number' => $body_number,
                'model_code' => $model_code,
                'serial_number' => $serial_number,
                'model_info' => $model_info
            ];
        }

        // Если формат не распознан, возвращаем как есть для ручной проверки
        return [
            'success' => false,
            'message' => 'Не удалось распознать формат кузовного номера. Требуется ручной ввод данных.',
            'raw_input' => $body_number
        ];
    }

    /**
     * База знаний кодов моделей (упрощенная, можно расширять или вынести в БД)
     */
    private function get_model_info_by_code($code) {
        $database = [
            'ACU30' => ['brand' => 'Toyota', 'model' => 'Camry', 'engine_family' => '2AZ-FE', 'transmission_family' => 'U241E / U140E'],
            'ACU35' => ['brand' => 'Toyota', 'model' => 'Camry (4WD)', 'engine_family' => '2AZ-FE', 'transmission_family' => 'U140F'],
            'ZZE121' => ['brand' => 'Toyota', 'model' => 'Corolla', 'engine_family' => '1ZZ-FE', 'transmission_family' => 'U341E / C59'],
            'GZ10'  => ['brand' => 'Suzuki', 'model' => 'Swift', 'engine_family' => 'M13A / M15A', 'transmission_family' => 'JA404E / JA504E'],
            'DBA-GE6' => ['brand' => 'Honda', 'model' => 'Fit', 'engine_family' => 'L13A', 'transmission_family' => 'CVT (MMHA) / AT (S45A)'],
        ];

        return $database[$code] ?? [
            'brand' => 'Неизвестно',
            'model' => 'Неизвестно',
            'engine_family' => 'Неизвестно',
            'transmission_family' => 'Неизвестно'
        ];
    }

    /**
     * Проверка совместимости АКПП с автомобилем
     *
     * @param array $vehicle_data Данные автомобиля (из VIN или Body декодера)
     * @param string $transmission_code Код коробки передач (например, 'U140E')
     * @return array Результат проверки
     */
    public function check_compatibility($vehicle_data, $transmission_code) {
        $transmission_code = strtoupper(trim($transmission_code));
        
        // 1. Прямая проверка по базе совместимости (упрощенная логика)
        $compatible_models = $this->get_compatible_models_for_transmission($transmission_code);
        
        $is_compatible = false;
        $confidence = 'low';

        if (!empty($compatible_models)) {
            $vehicle_model_str = ($vehicle_data['brand'] ?? '') . ' ' . ($vehicle_data['model'] ?? '');
            
            foreach ($compatible_models as $compat) {
                if (stripos($vehicle_model_str, $compat['brand']) !== false && 
                    stripos($vehicle_model_str, $compat['model']) !== false) {
                    $is_compatible = true;
                    $confidence = 'high';
                    break;
                }
            }
        }

        // 2. Проверка по семейству двигателей (вторичный признак)
        if (!$is_compatible && isset($vehicle_data['engine_family'])) {
            $engine = $vehicle_data['engine_family'];
            // Пример: U140E часто идет с 2AZ-FE или 1MZ-FE
            if (strpos($transmission_code, 'U140') !== false && (strpos($engine, '2AZ') !== false || strpos($engine, '1MZ') !== false)) {
                $is_compatible = true;
                $confidence = 'medium';
            }
        }

        return [
            'is_compatible' => $is_compatible,
            'confidence' => $confidence, // high, medium, low
            'message' => $is_compatible 
                ? 'Коробка передач совместима с данным автомобилем' 
                : 'Внимание: прямая совместимость не подтверждена. Требуется проверка по каталогу.',
            'transmission_code' => $transmission_code
        ];
    }

    /**
     * База совместимости трансмиссий (заготовка)
     */
    private function get_compatible_models_for_transmission($code) {
        $db = [
            'U140E' => [['brand' => 'Toyota', 'model' => 'Camry'], ['brand' => 'Toyota', 'model' => 'RAV4']],
            'U241E' => [['brand' => 'Toyota', 'model' => 'Camry'], ['brand' => 'Scion', 'model' => 'tC']],
            'C59'   => [['brand' => 'Toyota', 'model' => 'Corolla'], ['brand' => 'Toyota', 'model' => 'Matrix']],
            'JA404E'=> [['brand' => 'Suzuki', 'model' => 'Swift'], ['brand' => 'Suzuki', 'model' => 'Ignis']],
        ];

        return $db[$code] ?? [];
    }

    // --- AJAX ОБРАБОТЧИКИ ---

    public function ajax_decode_body() {
        check_ajax_referer('akpp_crm_nonce', 'nonce');
        
        $body_number = sanitize_text_field($_POST['body_number'] ?? '');
        if (empty($body_number)) {
            wp_send_json_error(['message' => 'Кузовной номер не указан']);
        }

        $result = $this->decode_body_number($body_number);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => $result['message'], 'data' => $result]);
        }
    }

    public function ajax_check_compatibility() {
        check_ajax_referer('akpp_crm_nonce', 'nonce');
        
        $vehicle_data = [
            'brand' => sanitize_text_field($_POST['brand'] ?? ''),
            'model' => sanitize_text_field($_POST['model'] ?? ''),
            'engine_family' => sanitize_text_field($_POST['engine_family'] ?? '')
        ];
        $transmission_code = sanitize_text_field($_POST['transmission_code'] ?? '');

        if (empty($vehicle_data['brand']) || empty($transmission_code)) {
            wp_send_json_error(['message' => 'Недостаточно данных для проверки']);
        }

        $result = $this->check_compatibility($vehicle_data, $transmission_code);
        wp_send_json_success($result);
    }
}
