<?php
if (!defined('ABSPATH')) exit;

class AKPP_CRM {
    private static $instance = null;
    public $version = '1.0.0';
    public $plugin_url;
    public $plugin_path;
    
    public static function get_instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }
    
    private function __construct() {
        $this->plugin_path = dirname(__FILE__) . '/';
        $this->plugin_url  = get_template_directory_uri() . '/inc/crm/';
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        $classes = [
            'class-akpp-install.php', 'class-akpp-ajax.php', 'class-akpp-auth.php',
            'class-akpp-email.php', 'class-akpp-telegram.php', 'class-akpp-avito.php',
            'class-akpp-parser.php', 'class-akpp-push.php', 'class-akpp-webhook.php',
            'decoders/class-vin-decoder.php', 'decoders/class-body-decoder.php',
            'decoders/class-deal-calculator.php', 'ai/class-ai-analyzer.php',
        ];
        foreach ($classes as $class) {
            $file = $this->plugin_path . $class;
            if (file_exists($file)) require_once $file;
        }
    }
    
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('init', [$this, 'init']);
        if (class_exists('AKPP_Ajax')) new AKPP_Ajax();
        if (class_exists('AKPP_Auth')) new AKPP_Auth();
    }
    
    public function init() {
        if (get_option('akpp_crm_version') !== $this->version) {
            if (class_exists('AKPP_Install')) {
                AKPP_Install::install_tables();
                update_option('akpp_crm_version', $this->version);
            }
        }
    }
    
    public function add_menu() {
        add_menu_page('АКПП45 CRM', 'АКПП45 CRM', 'manage_options', 'akpp-crm', [$this, 'render_page'], 'dashicons-car', 3);
        $submenus = [
            ['akpp-deals', '📋 Сделки'], ['akpp-new-deal', '➕ Новая'], ['akpp-employees', '👥 Сотрудники'],
            ['akpp-vehicles', '🚗 Авто'], ['akpp-transmissions', '⚙️ АКПП'], ['akpp-parts', '📦 Склад'],
            ['akpp-oils', '🛢️ Масла'], ['akpp-parser', '🔍 Парсер'], ['akpp-leads', '📨 Лиды'],
            ['akpp-users', '👤 Пользователи'], ['akpp-avito', '💬 Авито'], ['akpp-telegram', '📱 Telegram'],
        ];
        foreach ($submenus as $menu) {
            add_submenu_page('akpp-crm', $menu[1], $menu[1], 'manage_options', $menu[0], [$this, 'render_page']);
        }
    }
    
    public function render_page() {
        $page = isset($_GET['page']) ? str_replace('akpp-', '', sanitize_text_field($_GET['page'])) : 'crm';
        $map = ['crm'=>'dashboard','dashboard'=>'dashboard','deals'=>'deals','new-deal'=>'new-deal',
                'employees'=>'employees','vehicles'=>'vehicles','transmissions'=>'transmissions',
                'parts'=>'parts','oils'=>'oils','parser'=>'parser','leads'=>'leads','users'=>'users',
                'avito'=>'avito','telegram'=>'telegram'];
        $slug = isset($map[$page]) ? $map[$page] : 'dashboard';
        $template = $this->plugin_path . "templates/{$slug}.php";
        if (file_exists($template)) include $template;
        else echo '<div class="notice notice-error"><p>Шаблон не найден: ' . $slug . '.php</p></div>';
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'akpp-') === false && $hook !== 'toplevel_page_akpp-crm') return;
        wp_enqueue_style('akpp-admin', $this->plugin_url . 'assets/css/admin.css', [], $this->version);
        wp_enqueue_script('akpp-admin', $this->plugin_url . 'assets/js/admin.js', ['jquery'], $this->version, true);
        wp_localize_script('akpp-admin', 'akppCRM', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('akpp_crm_nonce'),
        ]);
    }
}

add_action('after_setup_theme', function() { AKPP_CRM::get_instance(); });
