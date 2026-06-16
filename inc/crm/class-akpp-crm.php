<?php
/**
 * АКПП45 CRM - Главный класс ядра (Singleton)
 * Подключение файлов, регистрация хуков, меню и управление ресурсами.
 *
 * @package AKPP_CRM
 * @version 4.3
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

// Определение констант путей (относительно темы)
if (!defined('AKPP_CRM_PATH')) {
    define('AKPP_CRM_PATH', get_template_directory() . '/inc/crm/');
}
if (!defined('AKPP_CRM_URL')) {
    define('AKPP_CRM_URL', get_template_directory_uri() . '/inc/crm/');
}
if (!defined('AKPP_CRM_VERSION')) {
    define('AKPP_CRM_VERSION', '4.3.0');
}

class AKPP_CRM {

    /**
     * Единственный экземпляр класса (Singleton)
     */
    private static $instance = null;

    /**
     * Получение экземпляра
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Конструктор (защищен для Singleton)
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
        $this->init_components();
    }

    /**
     * Подключение всех необходимых файлов CRM
     */
    private function includes() {
        // 1. Основные классы
        require_once AKPP_CRM_PATH . 'class-akpp-install.php';
        require_once AKPP_CRM_PATH . 'class-akpp-ajax.php';
        require_once AKPP_CRM_PATH . 'class-akpp-auth.php';
        require_once AKPP_CRM_PATH . 'class-akpp-db.php';
        require_once AKPP_CRM_PATH . 'class-akpp-email.php';
        require_once AKPP_CRM_PATH . 'class-akpp-push.php';
        
        // 2. Интеграции (НОВЫЕ)
        require_once AKPP_CRM_PATH . 'class-avito-api.php';
        require_once AKPP_CRM_PATH . 'class-avito-webhook.php';
        require_once AKPP_CRM_PATH . 'class-avito-cron.php';
        require_once AKPP_CRM_PATH . 'class-chat-ajax.php';
        require_once AKPP_CRM_PATH . 'class-user-registration.php';
        require_once AKPP_CRM_PATH . 'class-akpp-telegram.php';
        require_once AKPP_CRM_PATH . 'class-akpp-parser.php';
        
        // 3. Декодеры и калькуляторы
        require_once AKPP_CRM_PATH . 'decoders/class-vin-decoder.php';
        require_once AKPP_CRM_PATH . 'decoders/class-body-decoder.php';
        require_once AKPP_CRM_PATH . 'decoders/class-deal-calculator.php';
        
        // 4. AI анализ
        require_once AKPP_CRM_PATH . 'ai/class-ai-analyzer.php';

        // 5. WP_List_Table классы (Таблицы админки)
        require_once AKPP_CRM_PATH . 'tables/class-deals-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-employees-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-vehicles-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-transmissions-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-leads-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-parts-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-oils-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-parser-table.php';
        require_once AKPP_CRM_PATH . 'tables/class-users-table.php';               // ✅ НОВЫЙ
        require_once AKPP_CRM_PATH . 'tables/class-avito-dialogs-table.php';       // ✅ НОВЫЙ
    }

    /**
     * Инициализация хуков WordPress
     */
    private function init_hooks() {
        // Установка БД при активации (можно вызывать вручную или через register_activation_hook в плагине)
        add_action('admin_init', [$this, 'maybe_install_db']);
        
        // Регистрация меню
        add_action('admin_menu', [$this, 'register_admin_menus']);
        
        // Подключение стилей и скриптов (СТРОГО из корня темы /assets/)
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Регистрация шорткодов
        add_action('init', [$this, 'register_shortcodes']);
    }

    /**
     * Инициализация экземпляров новых компонентов
     */
    private function init_components() {
        // AJAX обработчики
        new AKPP_AJAX();
        new AKPP_Chat_AJAX();
        new AKPP_User_Registration();
        
        // Авито интеграция
        if (class_exists('AKPP_Avito_API')) {
            AKPP_Avito_API::get_instance();
        }
        if (class_exists('AKPP_Avito_Webhook')) {
            new AKPP_Avito_Webhook();
        }
        if (class_exists('AKPP_Avito_Cron')) {
            new AKPP_Avito_Cron();
        }
        
        // Telegram
        if (class_exists('AKPP_Telegram')) {
            AKPP_Telegram::get_instance();
        }
    }

    /**
     * Проверка и установка БД при первом запуске
     */
    public function maybe_install_db() {
        $db_version = get_option('akpp_crm_db_version', '0');
        if (version_compare($db_version, AKPP_CRM_VERSION, '<')) {
            AKPP_Install::install();
        }
    }

    /**
     * Регистрация меню в админ-панели
     */
    public function register_admin_menus() {
        $capability = 'manage_options';
        $menu_slug  = 'akpp-crm-dashboard';

        // Главное меню
        add_menu_page(
            __('АКПП CRM', 'akpp-crm'),
            __('АКПП CRM', 'akpp-crm'),
            $capability,
            $menu_slug,
            [$this, 'render_dashboard_page'],
            'dashicons-chart-area',
            30
        );

        // Подменю
        add_submenu_page($menu_slug, __('Дашборд', 'akpp-crm'), __('Дашборд', 'akpp-crm'), $capability, $menu_slug, [$this, 'render_dashboard_page']);
        add_submenu_page($menu_slug, __('Сделки', 'akpp-crm'), __('Сделки', 'akpp-crm'), $capability, 'akpp-crm-deals', [$this, 'render_deals_page']);
        add_submenu_page($menu_slug, __('Новая сделка', 'akpp-crm'), __('➕ Новая', 'akpp-crm'), $capability, 'akpp-crm-new-deal', [$this, 'render_new_deal_page']);
        add_submenu_page($menu_slug, __('Сотрудники', 'akpp-crm'), __('Сотрудники', 'akpp-crm'), $capability, 'akpp-crm-employees', [$this, 'render_employees_page']);
        add_submenu_page($menu_slug, __('Автомобили', 'akpp-crm'), __('Авто', 'akpp-crm'), $capability, 'akpp-crm-vehicles', [$this, 'render_vehicles_page']);
        add_submenu_page($menu_slug, __('Каталог АКПП', 'akpp-crm'), __('АКПП', 'akpp-crm'), $capability, 'akpp-crm-transmissions', [$this, 'render_transmissions_page']);
        add_submenu_page($menu_slug, __('Склад запчастей', 'akpp-crm'), __('Склад', 'akpp-crm'), $capability, 'akpp-crm-parts', [$this, 'render_parts_page']);
        add_submenu_page($menu_slug, __('Масла', 'akpp-crm'), __('Масла', 'akpp-crm'), $capability, 'akpp-crm-oils', [$this, 'render_oils_page']);
        add_submenu_page($menu_slug, __('Парсер + AI', 'akpp-crm'), __('Парсер', 'akpp-crm'), $capability, 'akpp-crm-parser', [$this, 'render_parser_page']);
        add_submenu_page($menu_slug, __('Лиды', 'akpp-crm'), __('Лиды', 'akpp-crm'), $capability, 'akpp-crm-leads', [$this, 'render_leads_page']);
        
        // ✅ НОВЫЕ ПУНКТЫ МЕНЮ
        add_submenu_page($menu_slug, __('Клиенты сайта', 'akpp-crm'), __('👥 Клиенты', 'akpp-crm'), $capability, 'akpp-crm-users', [$this, 'render_users_page']);
        add_submenu_page($menu_slug, __('Диалоги Авито', 'akpp-crm'), __('💬 Авито чаты', 'akpp-crm'), $capability, 'akpp-crm-avito-dialogs', [$this, 'render_avito_dialogs_page']);
        add_submenu_page($menu_slug, __('Настройки Авито', 'akpp-crm'), __('⚙️ Настройки Авито', 'akpp-crm'), $capability, 'akpp-crm-avito-settings', [$this, 'render_avito_settings_page']);
        add_submenu_page($menu_slug, __('Telegram бот', 'akpp-crm'), __('📱 Telegram', 'akpp-crm'), $capability, 'akpp-crm-telegram', [$this, 'render_telegram_page']);
    }

    /**
     * Подключение CSS и JS на фронтенде (СТРОГО из /assets/ темы)
     */
    public function enqueue_frontend_assets() {
        $theme_uri = get_template_directory_uri();
        
        // Стили
        wp_enqueue_style('akpp-frontend-style', $theme_uri . '/assets/css/frontend.css', [], AKPP_CRM_VERSION);
        wp_enqueue_style('akpp-modal-style', $theme_uri . '/assets/css/modal.css', [], AKPP_CRM_VERSION);
        
        // Скрипты
        wp_enqueue_script('akpp-auth-js', $theme_uri . '/assets/js/auth.js', ['jquery'], AKPP_CRM_VERSION, true);
        wp_enqueue_script('akpp-chat-frontend-js', $theme_uri . '/assets/js/chat.js', ['jquery'], AKPP_CRM_VERSION, true);
        
        // Локализация для JS
        wp_localize_script('akpp-chat-frontend-js', 'akpp_frontend_chat_config', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp_frontend_chat_action'),
            'strings'  => [
                'sending' => __('Отправка...', 'akpp-crm'),
                'error'   => __('Ошибка отправки', 'akpp-crm')
            ]
        ]);
    }

    /**
     * Подключение CSS и JS в админке (СТРОГО из /assets/ темы)
     */
    public function enqueue_admin_assets($hook) {
        // Подключаем только на страницах CRM
        if (strpos($hook, 'akpp-crm') === false && strpos($hook, 'toplevel_page_akpp-crm') === false) {
            return;
        }

        $theme_uri = get_template_directory_uri();
        
        // Стили
        wp_enqueue_style('akpp-admin-style', $theme_uri . '/assets/css/admin.css', [], AKPP_CRM_VERSION);
        
        // Скрипты
        wp_enqueue_script('akpp-admin-js', $theme_uri . '/assets/js/admin.js', ['jquery'], AKPP_CRM_VERSION, true);
        wp_enqueue_script('akpp-deal-calculator-js', $theme_uri . '/assets/js/deal-calculator.js', ['jquery'], AKPP_CRM_VERSION, true);
        wp_enqueue_script('akpp-vin-decoder-js', $theme_uri . '/assets/js/vin-decoder.js', ['jquery'], AKPP_CRM_VERSION, true);
        wp_enqueue_script('akpp-chat-js', $theme_uri . '/assets/js/chat.js', ['jquery'], AKPP_CRM_VERSION, true);
        
        // Локализация для JS (передача nonce и URL)
        wp_localize_script('akpp-deal-calculator-js', 'akpp_deal', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp_save_deal_nonce')
        ]);
        
        wp_localize_script('akpp-vin-decoder-js', 'akpp_vin_decoder_config', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp_vin_decode_nonce')
        ]);
        
        wp_localize_script('akpp-chat-js', 'akpp_chat_config', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp_chat_action_nonce'),
            'strings'  => [
                'sending' => __('Отправка...', 'akpp-crm'),
                'error'   => __('Ошибка отправки', 'akpp-crm')
            ]
        ]);
    }

    /**
     * Регистрация шорткодов
     */
    public function register_shortcodes() {
        add_shortcode('akpp_registration_form', [$this, 'shortcode_registration_form']);
        add_shortcode('akpp_client_chat', [$this, 'shortcode_client_chat']);
    }

    public function shortcode_registration_form() {
        ob_start();
        include AKPP_CRM_PATH . 'templates/frontend/registration.php';
        return ob_get_clean();
    }

    public function shortcode_client_chat() {
        if (!is_user_logged_in()) {
            return '<p>' . __('Пожалуйста, войдите в систему для доступа к чату.', 'akpp-crm') . '</p>';
        }
        ob_start();
        include AKPP_CRM_PATH . 'templates/frontend/chat.php';
        return ob_get_clean();
    }

    // =========================================================================
    // МЕТОДЫ РЕНДЕРИНГА СТРАНИЦ (Обёртки для шаблонов)
    // =========================================================================

    public function render_dashboard_page() {
        include AKPP_CRM_PATH . 'templates/dashboard.php';
    }

    public function render_deals_page() {
        include AKPP_CRM_PATH . 'templates/deals.php';
    }

    public function render_new_deal_page() {
        include AKPP_CRM_PATH . 'templates/new-deal.php';
    }

    public function render_employees_page() {
        include AKPP_CRM_PATH . 'templates/employees.php';
    }

    public function render_vehicles_page() {
        include AKPP_CRM_PATH . 'templates/vehicles.php';
    }

    public function render_transmissions_page() {
        include AKPP_CRM_PATH . 'templates/transmissions.php';
    }

    public function render_parts_page() {
        include AKPP_CRM_PATH . 'templates/parts.php';
    }

    public function render_oils_page() {
        include AKPP_CRM_PATH . 'templates/oils.php';
    }

    public function render_parser_page() {
        include AKPP_CRM_PATH . 'templates/parser.php';
    }

    public function render_leads_page() {
        include AKPP_CRM_PATH . 'templates/leads.php';
    }

    // ✅ НОВЫЕ МЕТОДЫ РЕНДЕРИНГА
    public function render_users_page() {
        if (!class_exists('AKPP_Users_Table')) return;
        $table = new AKPP_Users_Table();
        echo '<div class="wrap"><h1>' . __('Клиенты сайта', 'akpp-crm') . '</h1><form method="post">';
        $table->prepare_items();
        $table->display();
        echo '</form></div>';
    }

    public function render_avito_dialogs_page() {
        if (!class_exists('AKPP_Avito_Dialogs_Table')) return;
        $table = new AKPP_Avito_Dialogs_Table();
        echo '<div class="wrap"><h1>' . __('Диалоги Авито', 'akpp-crm') . '</h1><form method="post">';
        $table->prepare_items();
        $table->display();
        echo '</form></div>';
    }

    public function render_avito_settings_page() {
        include AKPP_CRM_PATH . 'templates/avito-settings.php';
    }

    public function render_telegram_page() {
        include AKPP_CRM_PATH . 'templates/telegram.php';
    }
}

// Глобальная функция для быстрого доступа к экземпляру
function akpp_crm() {
    return AKPP_CRM::get_instance();
}
