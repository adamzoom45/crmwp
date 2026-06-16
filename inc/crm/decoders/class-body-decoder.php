<?php
/**
 * Класс для декодирования номеров кузова Toyota/Lexus
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_Body_Decoder {
    
    private static $instance = null;
    
    // База данных кузовов Toyota/Lexus
    private $body_database = [];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_database();
        add_action('wp_ajax_akpp_decode_body', [$this, 'ajax_decode_body']);
        add_action('wp_ajax_nopriv_akpp_decode_body', [$this, 'ajax_decode_body']);
    }
    
    /**
     * Инициализация базы данных кузовов
     */
    private function init_database() {
        $this->body_database = [
            // Toyota
            'AE86' => ['model' => 'Corolla Levin/Sprinter Trueno', 'years' => '1983-1987', 'engine' => '4A-GE', 'drive' => 'RWD'],
            'AE92' => ['model' => 'Corolla', 'years' => '1987-1991', 'engine' => '4A-FE/4A-GE', 'drive' => 'FWD'],
            'AE101' => ['model' => 'Corolla', 'years' => '1991-1995', 'engine' => '4A-FE/4A-GE', 'drive' => 'FWD'],
            'AE111' => ['model' => 'Corolla', 'years' => '1995-2000', 'engine' => '4A-FE/4A-GE', 'drive' => 'FWD'],
            'JZA80' => ['model' => 'Supra', 'years' => '1993-2002', 'engine' => '2JZ-GE/2JZ-GTE', 'drive' => 'RWD'],
            'JZA70' => ['model' => 'Supra', 'years' => '1988-1993', 'engine' => '1JZ-GTE', 'drive' => 'RWD'],
            'MA70' => ['model' => 'Supra', 'years' => '1986-1988', 'engine' => '7M-GE/7M-GTE', 'drive' => 'RWD'],
            'SW20' => ['model' => 'MR2', 'years' => '1989-1999', 'engine' => '3S-GE/3S-GTE', 'drive' => 'MR'],
            'ZZW30' => ['model' => 'MR2 Spyder', 'years' => '1999-2007', 'engine' => '1ZZ-FE', 'drive' => 'MR'],
            'SXE10' => ['model' => 'Altezza', 'years' => '1998-2005', 'engine' => '3S-GE/1G-FE', 'drive' => 'FR'],
            'GXE10' => ['model' => 'Altezza', 'years' => '1998-2005', 'engine' => '1G-FE', 'drive' => 'FR'],
            'JCE10' => ['model' => 'Altezza', 'years' => '2001-2005', 'engine' => '3S-GE', 'drive' => 'FR'],
            'UCF10' => ['model' => 'LS400', 'years' => '1989-1994', 'engine' => '1UZ-FE', 'drive' => 'FR'],
            'UCF20' => ['model' => 'LS400', 'years' => '1994-2000', 'engine' => '1UZ-FE', 'drive' => 'FR'],
            'UCF30' => ['model' => 'LS430', 'years' => '2000-2006', 'engine' => '3UZ-FE', 'drive' => 'FR'],
            'JZS147' => ['model' => 'Aristo', 'years' => '1991-1997', 'engine' => '2JZ-GE/2JZ-GTE', 'drive' => 'FR'],
            'JZS161' => ['model' => 'Aristo V300', 'years' => '1997-2005', 'engine' => '2JZ-GTE', 'drive' => 'FR'],
            'SXV10' => ['model' => 'Camry', 'years' => '1991-1996', 'engine' => '3S-FE/5S-FE', 'drive' => 'FWD'],
            'SXV20' => ['model' => 'Camry', 'years' => '1996-2001', 'engine' => '5S-FE/1MZ-FE', 'drive' => 'FWD'],
            'ACV30' => ['model' => 'Camry', 'years' => '2001-2006', 'engine' => '2AZ-FE/1MZ-FE', 'drive' => 'FWD'],
            'NCP10' => ['model' => 'Prius', 'years' => '1997-2003', 'engine' => '1NZ-FXE', 'drive' => 'FWD', 'hybrid' => true],
            'NHW20' => ['model' => 'Prius', 'years' => '2003-2009', 'engine' => '1NZ-FXE', 'drive' => 'FWD', 'hybrid' => true],
            'ZVW30' => ['model' => 'Prius', 'years' => '2009-2015', 'engine' => '2ZR-FXE', 'drive' => 'FWD', 'hybrid' => true],
            'GRX120' => ['model' => 'Mark X', 'years' => '2004-2009', 'engine' => '4GR-FSE', 'drive' => 'FR'],
            'GRX130' => ['model' => 'Mark X', 'years' => '2009-2019', 'engine' => '4GR-FSE/2GR-FSE', 'drive' => 'FR'],
            'GX71' => ['model' => 'Mark II', 'years' => '1984-1988', 'engine' => '1G-EU/1G-GEU', 'drive' => 'FR'],
            'GX81' => ['model' => 'Mark II', 'years' => '1988-1992', 'engine' => '1G-FE/1G-GTE', 'drive' => 'FR'],
            'JZX90' => ['model' => 'Mark II', 'years' => '1992-1996', 'engine' => '1JZ-GE/1JZ-GTE', 'drive' => 'FR'],
            'JZX100' => ['model' => 'Mark II', 'years' => '1996-2000', 'engine' => '1JZ-GE/1JZ-GTE', 'drive' => 'FR'],
            'JZX110' => ['model' => 'Mark II', 'years' => '2000-2004', 'engine' => '1JZ-FSE/1JZ-GTE', 'drive' => 'FR'],
            
            // Lexus
            'JZS160' => ['model' => 'GS300', 'years' => '1997-2005', 'engine' => '2JZ-GE', 'drive' => 'FR'],
            'JZS161' => ['model' => 'GS300/GS400', 'years' => '1997-2005', 'engine' => '2JZ-GE/1UZ-FE', 'drive' => 'FR'],
            'USF40' => ['model' => 'LS460', 'years' => '2006-2012', 'engine' => '1UR-FE', 'drive' => 'FR'],
            'GWL10' => ['model' => 'LS500h', 'years' => '2017+', 'engine' => '8GR-FXS', 'drive' => 'FR', 'hybrid' => true],
            'GRL10' => ['model' => 'LS500', 'years' => '2017+', 'engine' => 'V35A-FTS', 'drive' => 'FR'],
            'URJ201' => ['model' => 'LX570', 'years' => '2007-2021', 'engine' => '3UR-FE', 'drive' => '4WD'],
            'VZJ95' => ['model' => 'LX470', 'years' => '1998-2007', 'engine' => '2UZ-FE', 'drive' => '4WD'],
            'MCU15' => ['model' => 'RX300', 'years' => '1997-2003', 'engine' => '1MZ-FE', 'drive' => 'FWD/4WD'],
            'MCU35' => ['model' => 'RX330', 'years' => '2003-2006', 'engine' => '3MZ-FE', 'drive' => 'FWD/4WD'],
            'GGL10' => ['model' => 'RX350', 'years' => '2015+', 'engine' => '8GR-FXS', 'drive' => 'FWD/4WD'],
        ];
    }
    
    /**
     * AJAX: Декодирование номера кузова
     */
    public function ajax_decode_body() {
        if (!check_ajax_referer('akpp_decode_body_nonce', 'nonce', false)) {
            wp_send_json_error('Неверный security токен');
            return;
        }
        
        $body_number = isset($_POST['body_number']) ? strtoupper(sanitize_text_field($_POST['body_number'])) : '';
        
        if (empty($body_number)) {
            wp_send_json_error('Номер кузова не передан');
            return;
        }
        
        $result = $this->decode($body_number);
        
        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error('Не удалось распознать номер кузова');
        }
    }
    
    /**
     * Декодирование номера кузова
     */
    public function decode($body_number) {
        // Поиск точного совпадения
        if (isset($this->body_database[$body_number])) {
            $data = $this->body_database[$body_number];
            $data['body_code'] = $body_number;
            return $data;
        }
        
        // Поиск по префиксу
        foreach ($this->body_database as $code => $data) {
            if (strpos($body_number, $code) === 0) {
                $result = $data;
                $result['body_code'] = $code;
                $result['full_body_number'] = $body_number;
                return $result;
            }
        }
        
        return false;
    }
    
    /**
     * Получение информации о модели
     */
    public function get_model_info($model_code) {
        if (isset($this->body_database[$model_code])) {
            return $this->body_database[$model_code];
        }
        return false;
    }
    
    /**
     * Поиск по модели
     */
    public function search_by_model($model_name) {
        $results = [];
        $model_name = strtolower($model_name);
        
        foreach ($this->body_database as $code => $data) {
            if (strpos(strtolower($data['model']), $model_name) !== false) {
                $results[$code] = $data;
            }
        }
        
        return $results;
    }
    
    /**
     * Получение списка всех кузовов
     */
    public function get_all_bodies() {
        return $this->body_database;
    }
    
    /**
     * Получение кузовов по производителю
     */
    public function get_bodies_by_make($make) {
        $make = strtolower($make);
        $results = [];
        
        foreach ($this->body_database as $code => $data) {
            if ($make === 'toyota' && strpos($code, 'J') !== 0 && !in_array($code[0], ['U', 'G', 'M', 'V'])) {
                $results[$code] = $data;
            } elseif ($make === 'lexus' && (strpos($code, 'J') === 0 || in_array($code[0], ['U', 'G', 'M', 'V']))) {
                $results[$code] = $data;
            }
        }
        
        return $results;
    }
}
