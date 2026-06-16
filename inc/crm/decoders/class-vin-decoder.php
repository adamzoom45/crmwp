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
    private $cache_time = 2592000; // 30 дней
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_akpp_decode_vin', [$this, 'ajax_decode_vin']);
        add_action('wp_ajax_nopriv_akpp_decode_vin', [$this, 'ajax_decode_vin']);
        add_action('wp_ajax_akpp_vin_suggestions', [$this, 'ajax_vin_suggestions']);
        add_action('wp_ajax_akpp_clear_vin_cache', [$this, 'ajax_clear_vin_cache']);
    }
    
    /**
     * AJAX: Декодирование VIN
     */
    public function ajax_decode_vin() {
        if (!check_ajax_referer('akpp_decode_vin_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $vin = isset($_POST['vin']) ? sanitize_text_field($_POST['vin']) : '';
        
        if (empty($vin)) {
            wp_send_json_error('VIN код не передан');
            return;
        }
        
        $vin = strtoupper(preg_replace('/[^A-Z0-9]/', '', $vin));
        
        if (strlen($vin) !== 17) {
            wp_send_json_error('Неверный VIN код. Должен содержать 17 символов');
            return;
        }
        
        $result = $this->decode($vin);
        
        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error('Не удалось расшифровать VIN код');
        }
    }
    
    /**
     * AJAX: Подсказки VIN
     */
    public function ajax_vin_suggestions() {
        if (!check_ajax_referer('akpp_vin_suggestions_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        
        if (strlen($query) < 3) {
            wp_send_json_success([]);
            return;
        }
        
        $suggestions = $this->get_suggestions($query);
        wp_send_json_success($suggestions);
    }
    
    /**
     * AJAX: Очистка кэша VIN
     */
    public function ajax_clear_vin_cache() {
        if (!check_ajax_referer('akpp_clear_vin_cache_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
            return;
        }
        
        $deleted = $this->clear_cache(0);
        
        wp_send_json_success([
            'message' => "Удалено {$deleted} записей из кэша VIN"
        ]);
    }
    
    /**
     * Декодирование VIN
     */
    public function decode($vin) {
        $vin = strtoupper(preg_replace('/[^A-Z0-9]/', '', $vin));
        
        if (strlen($vin) !== 17) {
            return false;
        }
        
        // Проверяем кэш
        $cached = $this->get_from_cache($vin);
        if ($cached) {
            return $cached;
        }
        
        // Запрашиваем API
        $data = $this->fetch_from_api($vin);
        
        if ($data) {
            $this->save_to_cache($vin, $data);
            return $data;
        }
        
        return false;
    }
    
    /**
     * Получение из кэша
     */
    private function get_from_cache($vin) {
        global $wpdb;
        $table_cache = $wpdb->prefix . 'akpp_vin_cache';
        
        $cached = $wpdb->get_row($wpdb->prepare(
            "SELECT decoded_data FROM {$table_cache} 
            WHERE vin = %s AND created_at > DATE_SUB(NOW(), INTERVAL %d SECOND)",
            $vin,
            $this->cache_time
        ));
        
        if ($cached && !empty($cached->decoded_data)) {
            return json_decode($cached->decoded_data, true);
        }
        
        return false;
    }
    
    /**
     * Сохранение в кэш
     */
    private function save_to_cache($vin, $data) {
        global $wpdb;
        $table_cache = $wpdb->prefix . 'akpp_vin_cache';
        
        $wpdb->delete($table_cache, ['vin' => $vin]);
        
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
            'headers' => ['User-Agent' => 'AKPP45 CRM/4.2']
        ];
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('Ошибка API: ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            $this->log_error("HTTP ошибка: {$status_code}");
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['Results']) || empty($data['Results'])) {
            return false;
        }
        
        $result = $data['Results'][0];
        
        if (isset($result['ErrorCode']) && $result['ErrorCode'] !== '0') {
            return false;
        }
        
        return $this->parse_response($result);
    }
    
    /**
     * Парсинг ответа API
     */
    private function parse_response($result) {
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
            'market' => $this->determine_market($result['Make'] ?? '', $result['PlantCountry'] ?? '')
        ];
        
        return $vehicle;
    }
    
    /**
     * Определение рынка
     */
    private function determine_market($make, $plant_country) {
        $japanese = ['TOYOTA', 'HONDA', 'NISSAN', 'MAZDA', 'MITSUBISHI', 'SUBARU', 'SUZUKI', 'LEXUS', 'ACURA', 'INFINITI'];
        $korean = ['HYUNDAI', 'KIA', 'GENESIS'];
        $european = ['BMW', 'MERCEDES-BENZ', 'MERCEDES', 'AUDI', 'VOLKSWAGEN', 'VW', 'PORSCHE', 'VOLVO', 'RENAULT', 'PEUGEOT', 'CITROEN', 'FIAT', 'JAGUAR', 'LAND ROVER'];
        
        $make_upper = strtoupper($make);
        
        if (in_array($make_upper, $japanese)) return 'japan';
        if (in_array($make_upper, $korean)) return 'asia';
        if (in_array($make_upper, $european)) return 'europe';
        if (strtoupper($plant_country) === 'UNITED STATES') return 'usa';
        
        return 'europe';
    }
    
    /**
     * Извлечение года
     */
    private function extract_year($year_field) {
        if (empty($year_field)) return 0;
        if (is_numeric($year_field)) return intval($year_field);
        
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
     * Получение подсказок VIN
     */
    public function get_suggestions($query) {
        global $wpdb;
        $table_cache = $wpdb->prefix . 'akpp_vin_cache';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT vin FROM {$table_cache} WHERE vin LIKE '%%%s%%' ORDER BY created_at DESC LIMIT 10",
            $query
        ));
        
        return array_column($results, 'vin');
    }
    
    /**
     * Очистка кэша
     */
    public function clear_cache($days = 30) {
        global $wpdb;
        $table_cache = $wpdb->prefix . 'akpp_vin_cache';
        
        if ($days === 0) {
            $deleted = $wpdb->query("DELETE FROM {$table_cache}");
        } else {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table_cache} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ));
        }
        
        return $deleted;
    }
    
    private function log_error($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[AKPP_VIN] ОШИБКА: ' . $message);
        }
    }
}
