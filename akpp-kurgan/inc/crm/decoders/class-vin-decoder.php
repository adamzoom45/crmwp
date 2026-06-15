<?php
/**
 * Класс для декодирования VIN кодов через NHTSA API
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_VIN_Decoder {
    
    private static $instance = null;
    private $api_url = 'https://vpic.nhtsa.dot.gov/api/vehicles/';
    private $cache_time = 2592000; // 30 дней в секундах
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
    /**
     * Декодирование VIN кода
     * 
     * @param string $vin VIN код (17 символов)
     * @return array|false Данные автомобиля или false при ошибке
     */
    public function decode($vin) {
        $vin = strtoupper(preg_replace('/[^A-Z0-9]/', '', $vin));
        
        if (strlen($vin) !== 17) {
            $this->log_error("Неверная длина VIN: " . strlen($vin));
            return false;
        }
        
        // Проверяем кэш
        $cached = $this->get_from_cache($vin);
        if ($cached) {
            $this->log_event("VIN {$vin} получен из кэша");
            return $cached;
        }
        
        // Запрос к NHTSA API
        $data = $this->fetch_from_api($vin);
        
        if ($data) {
            $this->save_to_cache($vin, $data);
            return $data;
        }
        
        return false;
    }
    
    /**
     * Получение данных из кэша
     */
    private function get_from_cache($vin) {
        global $wpdb;
        
        $table_cache = $wpdb->prefix . 'akpp_vin_cache';
        
        $cached = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_cache} WHERE vin = %s AND created_at > DATE_SUB(NOW(), INTERVAL %d SECOND)",
            $vin,
            $this->cache_time
        ));
        
        if ($cached && !empty($cached->decoded_data)) {
            return json_decode($cached->decoded_data, true);
        }
        
        return false;
    }
    
    /**
     * Сохранение данных в кэш
     */
    private function save_to_cache($vin, $data) {
        global $wpdb;
        
        $table_cache = $wpdb->prefix . 'akpp_vin_cache';
        
        // Удаляем старый кэш
        $wpdb->delete($table_cache, ['vin' => $vin]);
        
        // Сохраняем новый
        $wpdb->insert(
            $table_cache,
            [
                'vin' => $vin,
                'decoded_data' => json_encode($data),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s']
        );
    }
    
    /**
     * Запрос к NHTSA API
     */
    private function fetch_from_api($vin) {
        $url = $this->api_url . 'DecodeVinValues/' . $vin . '?format=json';
        
        $args = [
            'method' => 'GET',
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'AKPP45 CRM/4.2'
            ]
        ];
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('Ошибка API NHTSA: ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            $this->log_error("Ошибка API NHTSA. Статус: {$status_code}");
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['Results']) || empty($data['Results'])) {
            $this->log_error("Пустой ответ от API для VIN: {$vin}");
            return false;
        }
        
        $result = $data['Results'][0];
        
        // Проверяем валидность ответа
        if (isset($result['ErrorCode']) && $result['ErrorCode'] !== '0') {
            $this->log_error("Ошибка декодирования VIN {$vin}: " . ($result['ErrorText'] ?? 'Unknown error'));
            return false;
        }
        
        return $this->parse_api_response($result);
    }
    
    /**
     * Парсинг ответа API
     */
    private function parse_api_response($result) {
        $vehicle = [
            'vin' => $result['VIN'] ?? '',
            'make' => $this->clean_value($result['Make'] ?? ''),
            'model' => $this->clean_value($result['Model'] ?? ''),
            'year' => $this->extract_year($result['ModelYear'] ?? $result['Year'] ?? ''),
            'manufacturer' => $this->clean_value($result['Manufacturer'] ?? ''),
            'plant_country' => $this->clean_value($result['PlantCountry'] ?? ''),
            'plant_state' => $this->clean_value($result['PlantState'] ?? ''),
            'body_class' => $this->clean_value($result['BodyClass'] ?? ''),
            'drive_type' => $this->clean_value($result['DriveType'] ?? ''),
            'engine_cylinders' => $this->clean_value($result['EngineCylinders'] ?? ''),
            'engine_model' => $this->clean_value($result['EngineModel'] ?? ''),
            'fuel_type' => $this->clean_value($result['FuelTypePrimary'] ?? ''),
            'transmission_style' => $this->clean_value($result['TransmissionStyle'] ?? ''),
            'transmission_speeds' => $this->clean_value($result['TransmissionSpeeds'] ?? ''),
            'gross_vehicle_weight_rating' => $this->clean_value($result['GrossVehicleWeightRating'] ?? ''),
        ];
        
        // Определяем рынок
        $vehicle['market'] = $this->determine_market($vehicle['make'], $vehicle['plant_country']);
        
        return $vehicle;
    }
    
    /**
     * Определение рынка автомобиля
     */
    private function determine_market($make, $plant_country) {
        $japanese_brands = ['TOYOTA', 'HONDA', 'NISSAN', 'MAZDA', 'MITSUBISHI', 'SUBARU', 'SUZUKI', 'LEXUS', 'ACURA', 'INFINITI'];
        $korean_brands = ['HYUNDAI', 'KIA', 'GENESIS'];
        $european_brands = ['BMW', 'MERCEDES-BENZ', 'MERCEDES', 'AUDI', 'VOLKSWAGEN', 'VW', 'PORSCHE', 'VOLVO', 'RENAULT', 'PEUGEOT', 'CITROEN', 'FIAT', 'JAGUAR', 'LAND ROVER'];
        
        $make_upper = strtoupper($make);
        
        if (in_array($make_upper, $japanese_brands)) {
            return 'japan';
        }
        
        if (in_array($make_upper, $korean_brands)) {
            return 'asia';
        }
        
        if (in_array($make_upper, $european_brands)) {
            return 'europe';
        }
        
        if (strtoupper($plant_country) === 'UNITED STATES' || strtoupper($plant_country) === 'USA') {
            return 'usa';
        }
        
        return 'europe';
    }
    
    /**
     * Извлечение года из разных форматов
     */
    private function extract_year($year_field) {
        if (empty($year_field)) {
            return 0;
        }
        
        if (is_numeric($year_field)) {
            return intval($year_field);
        }
        
        preg_match('/\d{4}/', $year_field, $matches);
        return !empty($matches) ? intval($matches[0]) : 0;
    }
    
    /**
     * Очистка значения
     */
    private function clean_value($value) {
        if (empty($value) || $value === 'Not Applicable' || $value === 'Unknown') {
            return '';
        }
        return trim($value);
    }
    
    /**
     * Декодирование VIN с получением полной информации
     */
    public function decode_full($vin) {
        $basic = $this->decode($vin);
        
        if (!$basic) {
            return false;
        }
        
        // Добавляем информацию о АКПП (если есть в базе)
        global $wpdb;
        $table_transmissions = $wpdb->prefix . 'akpp_transmissions';
        
        $transmission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_transmissions} 
            WHERE make = %s AND model LIKE %s 
            LIMIT 1",
            $basic['make'],
            '%' . $basic['model'] . '%'
        ));
        
        if ($transmission) {
            $basic['transmission_id'] = $transmission->id;
            $basic['transmission_code'] = $transmission->code;
            $basic['transmission_type'] = $transmission->type;
        }
        
        return $basic;
    }
    
    /**
     * Получение списка популярных VIN (для автозаполнения)
     */
    public function get_suggestions($query) {
        global $wpdb;
        
        $table_cache = $wpdb->prefix . 'akpp_vin_cache';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT vin FROM {$table_cache} 
            WHERE vin LIKE '%%%s%%' 
            ORDER BY created_at DESC 
            LIMIT 10",
            $query
        ));
        
        return array_column($results, 'vin');
    }
    
    /**
     * Очистка старого кэша
     */
    public function clear_old_cache($days = 30) {
        global $wpdb;
        
        $table_cache = $wpdb->prefix . 'akpp_vin_cache';
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_cache} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        $this->log_event("Удалено {$deleted} записей из кэша VIN");
        
        return $deleted;
    }
    
    private function log_error($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_VIN_DECODER] ОШИБКА: ' . $message);
        }
    }
    
    private function log_event($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_VIN_DECODER] СОБЫТИЕ: ' . $message);
        }
    }
}
