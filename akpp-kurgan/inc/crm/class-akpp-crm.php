<?php
/**
 * Главный класс CRM АКПП45 (ПОЛНЫЙ ФАЙЛ)
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class AKPP_CRM {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->define_constants();
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function define_constants() {
        define('AKPP_CRM_PATH', dirname(__FILE__) . '/');
        define('AKPP_CRM_URL', get_template_directory_uri() . '/inc/crm/');
        define('AKPP_CRM_VERSION', '4.2');
    }
    
    private function load_dependencies() {
        require_once AKPP_CRM_PATH . 'class-akpp-install.php';
        require_once AKPP_CRM_PATH . 'class-akpp-ajax.php';
        require_once AKPP_CRM_PATH . 'class-akpp-auth.php';
        require_once AKPP_CRM_PATH . 'class-akpp-email.php';
        require_once AKPP_CRM_PATH . 'class-akpp-push.php';
        require_once AKPP_CRM_PATH . 'class-akpp-telegram.php';
        require_once AKPP_CRM_PATH . 'class-akpp-avito.php';
        require_once AKPP_CRM_PATH . 'class-akpp-webhook.php';
        require_once AKPP_CRM_PATH . 'class-akpp-parser.php';
        require_once AKPP_CRM_PATH . 'class-akpp-cron.php';
        
        require_once AKPP_CRM_PATH . 'decoders/class-vin-decoder.php';
        require_once AKPP_CRM_PATH . 'decoders/class-body-decoder.php';
        require_once AKPP_CRM_PATH . 'decoders/class-deal-calculator.php';
        require_once AKPP_CRM_PATH . 'ai/class-ai-analyzer.php';
        
        require_once AKPP_CRM_PATH . 'tables/class-deals-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-employees-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-vehicles-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-transmissions-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-leads-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-parts-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-oils-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-parser-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-users-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-avito-dialogs-table.php';
    }
    
    private function init_hooks() {
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        
        // Регистрация страниц фронтенда
        add_action('init', [$this, 'register_frontend_pages']);
        
        add_action('init', [$this, 'init_components']);
    }
    
    public function init() {
        if (is_admin()) {
            $install = AKPP_Install::get_instance();
            $install->create_tables();
        }
    }
    
    public function init_components() {
        AKPP_AJAX::get_instance();
        AKPP_Cron::get_instance();
        AKPP_Webhook::get_instance();
    }
    
    /**
     * Регистрация страниц фронтенда
     */
    public function register_frontend_pages() {
        add_rewrite_rule('^crm-login/?$', 'index.php?akpp_page=login', 'top');
        add_rewrite_rule('^crm-register/?$', 'index.php?akpp_page=register', 'top');
        add_rewrite_rule('^crm-profile/?$', 'index.php?akpp_page=profile', 'top');
        add_rewrite_rule('^crm-chat/?$', 'index.php?akpp_page=chat', 'top');
        
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_include', [$this, 'load_frontend_template']);
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'akpp_page';
        return $vars;
    }
    
    public function load_frontend_template($template) {
        $page = get_query_var('akpp_page');
        
        if (!$page) {
            return $template;
        }
        
        $template_path = AKPP_CRM_PATH . 'templates/frontend/';
        
        switch ($page) {
            case 'login':
                $template = $template_path . 'login.php';
                break;
            case 'register':
                $template = $template_path . 'register.php';
                break;
            case 'profile':
                $template = $template_path . 'profile.php';
                break;
            case 'chat':
                $template = $template_path . 'chat.php';
                break;
        }
        
        return $template;
    }
    
    public function add_admin_menus() {
        add_menu_page(
            'АКПП45 CRM',
            'CRM',
            'manage_options',
            'akpp-crm',
            [$this, 'render_dashboard'],
            'dashicons-car',
            25
        );
        
        add_submenu_page(
            'akpp-crm',
            'Панель',
            '📊 Панель',
            'manage_options',
            'akpp-crm',
            [$this, 'render_dashboard']
        );
        
        add_submenu_page(
            'akpp-crm',
            'Сделки',
            '💰 Сделки',
            'manage_options',
            'akpp-crm-deals',
            [$this, 'render_deals']
        );
        
        add_submenu_page(
            'akpp-crm',
            'Новая сделка',
            '➕ Новая',
            'manage_options',
            'akpp-crm-deal-form',
            [$this, 'render_deal_form']
        );
        
        add_submenu_page(
            'akpp-crm',
            'Сотрудники',
            '👥 Сотрудники',
            'manage_options',
            'akpp-crm-employees',
            [$this, 'render_employees']
        );
        
        add_submenu_page(
            'akpp-crm',
            'Авто',
            '🚗 Авто',
            'manage_options',
            'akpp-crm-vehicles',
            [$this, 'render_vehicles']
        );
        
        add_submenu_page(
            'akpp-crm',
            'АКПП',
            '⚙️ АКПП',
            'manage_options',
            'akpp-crm-transmissions',
            [$this, 'render_transmissions']
        );
        
        add_submenu_page(
            'akpp-crm',
            'Склад',
            '📦 Склад',
            'manage_options',
            'akpp-crm-parts',
            [$this, 'render_parts']
        );
        
        add_submenu_page(
            'akpp-crm',
            'Масла',
            '🛢️ Масла',
            'manage_options',
            'akpp-crm-oils',
            [$this, 'render_oils']
        );
        
        add_submenu_page(
            'akpp-crm',
            'Парсер',
            '🤖 Парсер',
            'manage_options',
            'akpp-crm-parser',
            [$this, 'render_parser']
        );
        
        add_submenu_page(
            'akpp-crm',
            'Лиды',
            '📋 Лиды',
            'manage_options',
            'akpp-crm-leads',
            [$this, 'render_leads']
        );
        
        add_submenu_page(
            'akpp-crm',
            'Пользователи',
            '👤 Пользователи',
            'manage_options',
            'akpp-crm-users',
            [$this, 'render_users']
        );
        
        add_submenu_page(
            'akpp-crm',
            'Авито чаты',
            '💬 Авито чаты',
            'manage_options',
            'akpp-crm-avito-dialogs',
            [$this, 'render_avito_dialogs']
        );
        
        add_submenu_page(
            'akpp-crm',
            'Telegram',
            '📱 Telegram',
            'manage_options',
            'akpp-crm-telegram',
            [$this, 'render_telegram']
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'akpp-crm') === false) {
            return;
        }
        
        wp_enqueue_style('akpp-admin-css', AKPP_CRM_URL . 'assets/css/admin.css', [], AKPP_CRM_VERSION);
        wp_enqueue_script('akpp-admin-js', AKPP_CRM_URL . 'assets/js/admin.js', ['jquery'], AKPP_CRM_VERSION, true);
        
        wp_localize_script('akpp-admin-js', 'akpp_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('akpp_admin_nonce')
        ]);
    }
    
    public function enqueue_frontend_assets() {
        $page = get_query_var('akpp_page');
        
        if (!$page) {
            return;
        }
        
        wp_enqueue_style('akpp-frontend-css', AKPP_CRM_URL . 'assets/css/frontend.css', [], AKPP_CRM_VERSION);
        
        if ($page === 'chat') {
            wp_enqueue_script('akpp-chat-js', AKPP_CRM_URL . 'assets/js/chat.js', ['jquery'], AKPP_CRM_VERSION, true);
            wp_localize_script('akpp-chat-js', 'akpp_chat', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('akpp_get_chat_nonce'),
                'user_id' => get_current_user_id()
            ]);
        }
        
        if ($page === 'profile') {
            wp_enqueue_script('akpp-profile-js', AKPP_CRM_URL . 'assets/js/profile.js', ['jquery'], AKPP_CRM_VERSION, true);
        }
        
        if ($page === 'register' || $page === 'login') {
            wp_enqueue_script('akpp-auth-js', AKPP_CRM_URL . 'assets/js/auth.js', ['jquery'], AKPP_CRM_VERSION, true);
        }
    }
    
    // ==================== МЕТОДЫ ОТОБРАЖЕНИЯ ====================
    
    public function render_dashboard() {
        include AKPP_CRM_PATH . 'templates/dashboard.php';
    }
    
    public function render_deals() {
        include AKPP_CRM_PATH . 'templates/deals.php';
    }
    
    public function render_deal_form() {
        include AKPP_CRM_PATH . 'templates/deal-form.php';
    }
    
    public function render_employees() {
        include AKPP_CRM_PATH . 'templates/employees.php';
    }
    
    public function render_vehicles() {
        include AKPP_CRM_PATH . 'templates/vehicles.php';
    }
    
    public function render_transmissions() {
        include AKPP_CRM_PATH . 'templates/transmissions.php';
    }
    
    public function render_parts() {
        include AKPP_CRM_PATH . 'templates/parts.php';
    }
    
    public function render_oils() {
        include AKPP_CRM_PATH . 'templates/oils.php';
    }
    
    public function render_parser() {
        include AKPP_CRM_PATH . 'templates/parser.php';
    }
    
    public function render_leads() {
        include AKPP_CRM_PATH . 'templates/leads.php';
    }
    
    public function render_users() {
        include AKPP_CRM_PATH . 'templates/users.php';
    }
    
    public function render_avito_dialogs() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dialogs';
        
        if ($tab === 'settings') {
            include AKPP_CRM_PATH . 'templates/avito-settings.php';
        } else {
            include AKPP_CRM_PATH . 'templates/avito-dialogs.php';
        }
    }
    
    public function render_telegram() {
        include AKPP_CRM_PATH . 'templates/telegram.php';
    }
}
