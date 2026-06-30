<?php
if (!defined('ABSPATH')) exit;

class AKPP_AJAX_Loader {
    private static $instance = null;
    private $modules = [];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_modules();
    }
    
    private function load_modules() {
        $module_files = [
            'client'     => 'class-ajax-client.php',
            'admin'      => 'class-ajax-admin.php',
            'deals'      => 'class-ajax-deals.php',
            'parser'     => 'class-ajax-parser.php',
            'shop'       => 'class-ajax-shop.php',
            'telegram'   => 'class-ajax-telegram.php',
            'agreements' => 'class-ajax-agreements.php',
            'search'     => 'class-ajax-search.php',
        'leads'      => 'class-ajax-leads.php',
        ];
        
        foreach ($module_files as $name => $file) {
            $path = dirname(__FILE__) . '/' . $file;
            if (file_exists($path)) {
                require_once $path;
                $class_name = 'AKPP_AJAX_' . ucfirst($name);
                if (class_exists($class_name)) {
                    $this->modules[$name] = $class_name::get_instance();
                }
            }
        }
    }
    
    public function get_module($name) {
        return $this->modules[$name] ?? null;
    }
}
