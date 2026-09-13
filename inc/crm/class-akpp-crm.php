<?php
/**
 * АКПП45 CRM - Главный класс ядра (Singleton)
 * Подключение файлов, регистрация хуков, меню и управление ресурсами.
 *
 * @package AKPP_CRM
 * @version 5.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('AKPP_CRM_PATH')) {
    define('AKPP_CRM_PATH', get_template_directory() . '/inc/crm/');
}

if (!defined('AKPP_CRM_URL')) {
    define('AKPP_CRM_URL', get_template_directory_uri() . '/inc/crm/');
}

if (!defined('AKPP_CRM_VERSION')) {
    define('AKPP_CRM_VERSION', '5.1.0');
}

class AKPP_CRM {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
        $this->init_components();
    }

    // ========================================================================
    // ПОДКЛЮЧЕНИЕ ФАЙЛОВ
    // ========================================================================

    private function includes() {
        // AJAX модули (декомпозированные)
        $this->require_file('ajax/class-ajax-base.php');
        $this->require_file('ajax/class-ajax-loader.php');

        // Ядро
        $this->require_file('class-akpp-auth.php');
        $this->require_file('class-akpp-db.php');
        $this->require_file('class-akpp-email.php');
        $this->require_file('class-chat-ajax.php');
        $this->require_file('class-user-registration.php');

        // Интеграции
        $this->require_file('class-akpp-push.php');
        $this->require_file('class-akpp-telegram.php');
        $this->require_file('class-akpp-parser.php');
        $this->require_file('class-avito-api.php');
        $this->require_file('class-avito-webhook.php');
        $this->require_file('class-avito-cron.php');

        // Магазин
        $this->require_file('class-akpp-shop.php');

        // Личный кабинет
        $this->require_file('class-akpp-account.php');

        // Декодеры
        $this->require_file('decoders/class-vin-decoder.php');
        $this->require_file('decoders/class-body-decoder.php');
        $this->require_file('decoders/class-deal-calculator.php');

        // AI
        $this->require_file('ai/class-ai-analyzer.php');

        // Таблицы (WP_List_Table)
        $this->require_file('tables/class-deals-table.php');
        $this->require_file('tables/class-employees-table.php');
        $this->require_file('tables/class-vehicles-table.php');
        $this->require_file('tables/class-transmissions-table.php');
        $this->require_file('tables/class-leads-table.php');
        $this->require_file('tables/class-parts-table.php');
        $this->require_file('tables/class-oils-table.php');
        $this->require_file('tables/class-parser-table.php');
        $this->require_file('tables/class-users-table.php');
        $this->require_file('tables/class-avito-dialogs-table.php');
    }

    /**
     * Безопасное подключение файла
     */
    private function require_file($relative_path) {
        $full_path = AKPP_CRM_PATH . $relative_path;
        if (file_exists($full_path)) {
            require_once $full_path;
        } else {
            error_log('[AKPP CRM] Файл не найден: ' . $full_path);
        }
    }

    // ========================================================================
    // ХУКИ
    // ========================================================================

    private function init_hooks() {
        add_action('admin_menu', [$this, 'register_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_bar_menu', [$this, 'add_shop_link_to_admin_bar'], 100);
        add_action('init', [$this, 'register_shortcodes']);
    }

    // ========================================================================
    // ИНИЦИАЛИЗАЦИЯ КОМПОНЕНТОВ
    // ========================================================================

    private function init_components() {
        // AJAX загрузчик (все модули)
        if (class_exists('AKPP_AJAX_Loader')) {
            AKPP_AJAX_Loader::get_instance();
        }

        // Чат
        if (class_exists('AKPP_Chat_AJAX')) {
            new AKPP_Chat_AJAX();
        }

        // Регистрация пользователей
        if (class_exists('AKPP_User_Registration')) {
            new AKPP_User_Registration();
        }

        // Авито API
        if (class_exists('AKPP_Avito_API')) {
            AKPP_Avito_API::get_instance();
        }

        // Авито Webhook
        if (class_exists('AKPP_Avito_Webhook')) {
            new AKPP_Avito_Webhook();
        }

        // Авито Cron
        if (class_exists('AKPP_Avito_Cron')) {
            new AKPP_Avito_Cron();
        }

        // Telegram
        if (class_exists('AKPP_Telegram')) {
            AKPP_Telegram::get_instance();
        }

        // Push уведомления
        if (class_exists('AKPP_Push')) {
            AKPP_Push::get_instance();
        }

        // Магазин
        if (class_exists('AKPP_Shop')) {
            AKPP_Shop::get_instance();
        }

        // Личный кабинет
        if (class_exists('AKPP_Account')) {
            AKPP_Account::get_instance();
        }

        // VIN декодер
        if (class_exists('AKPP_VIN_Decoder')) {
            AKPP_VIN_Decoder::get_instance();
        }

        // Калькулятор сделок
        if (class_exists('AKPP_Deal_Calculator')) {
            AKPP_Deal_Calculator::get_instance();
        }

        // AI анализатор
        if (class_exists('AKPP_AI_Analyzer')) {
            AKPP_AI_Analyzer::get_instance();
        }
    }

    // ========================================================================
    // МЕНЮ АДМИН-ПАНЕЛИ
    // ========================================================================

    public function register_admin_menus() {
        // === Дашборд ===
        add_menu_page('Дашборд', '📊 Дашборд', 'akpp_view_dashboard', 'akpp-crm', [$this, 'render_dashboard_page'], 'dashicons-chart-area', 30);
        // === Сделки и клиенты ===
        add_menu_page('Сделки', '📋 Сделки', 'akpp_view_deals', 'akpp-crm-deals', [$this, 'render_deals_page'], 'dashicons-clipboard', 31);
        add_submenu_page('akpp-crm-deals', 'Сделки', '📋 Сделки', 'akpp_view_deals', 'akpp-crm-deals', [$this, 'render_deals_page']);
        add_submenu_page('akpp-crm-deals', 'Новая сделка', '➕ Новая', 'akpp_edit_deals', 'akpp-crm-new-deal', [$this, 'render_new_deal_page']);
        add_submenu_page('akpp-crm-deals', 'Лиды', '📨 Лиды', 'akpp_manage_leads', 'akpp-crm-leads', [$this, 'render_leads_page']);
        add_submenu_page('akpp-crm-deals', 'Клиенты сайта', '👤 Клиенты', 'akpp_view_clients', 'akpp-crm-users', [$this, 'render_users_page']);
        add_submenu_page('akpp-crm-deals', 'Согласия с офертой', '📜 Оферты', 'akpp_view_agreements', 'akpp-crm-agreements', [$this, 'render_agreements_page']);
        // === Бухгалтерия ===
        add_menu_page('Бухгалтерия', '👥 Бухгалтерия', 'akpp_view_employees', 'akpp-crm-employees', [$this, 'render_employees_page'], 'dashicons-money-alt', 32);
        add_submenu_page('akpp-crm-employees', 'Сотрудники', '👥 Сотрудники', 'akpp_view_employees', 'akpp-crm-employees', [$this, 'render_employees_page']);
        add_submenu_page('akpp-crm-employees', 'Табель', '📅 Табель', 'akpp_view_finance', 'akpp-crm-attendance', [$this, 'render_attendance_page']);
        add_submenu_page('akpp-crm-employees', 'Финансы', '💰 Финансы', 'akpp_view_finance', 'akpp-crm-finance', [$this, 'render_finance_page']);
        // === Автосервис ===
        add_menu_page('Автосервис', '🔧 Автосервис', 'akpp_view_vehicles', 'akpp-crm-vehicles', [$this, 'render_vehicles_page'], 'dashicons-car', 33);
        add_submenu_page('akpp-crm-vehicles', 'Авто', '🚙 Авто', 'akpp_view_vehicles', 'akpp-crm-vehicles', [$this, 'render_vehicles_page']);
        add_submenu_page('akpp-crm-vehicles', 'Каталог АКПП', '⚙️ Каталог АКПП', 'akpp_view_vehicles', 'akpp-crm-transmissions', [$this, 'render_transmissions_page']);
        add_submenu_page('akpp-crm-vehicles', 'ДВС', '🔩 ДВС', 'akpp_view_deals', 'akpp-crm-deals-engine', [$this, 'render_service_category_page']);
        add_submenu_page('akpp-crm-vehicles', 'Услуги', '📋 Услуги', 'akpp_view_deals', 'akpp-crm-services', [$this, 'render_services_page']);
        add_submenu_page('akpp-crm-vehicles', 'Парсер + AI', '🤖 Парсер + AI', 'akpp_use_parser', 'akpp-crm-parser', [$this, 'render_parser_page']);
        add_submenu_page('akpp-crm-vehicles', 'Масла', '🛢️ Масла', 'akpp_view_parts', 'akpp-crm-oils', [$this, 'render_oils_page']);
        add_submenu_page('akpp-crm-vehicles', 'Категории', '📁 Категории', 'akpp_view_parts', 'akpp-crm-categories', [$this, 'render_categories_page']);
        add_submenu_page('akpp-crm-vehicles', 'Склад запчастей', '📦 Склад запчастей', 'akpp_view_parts', 'akpp-crm-parts', [$this, 'render_parts_page']);
        // === Магазин ===
        add_menu_page('Магазин', '🛒 Магазин', 'akpp_view_shop', 'akpp-crm-shop', [$this, 'render_shop_page'], 'dashicons-cart', 34);
        add_submenu_page('akpp-crm-shop', 'Товары магазина', '🛒 Товары', 'akpp_view_shop', 'akpp-crm-shop', [$this, 'render_shop_page']);
        add_submenu_page('akpp-crm-shop', 'Категории', '📁 Категории', 'akpp_view_shop', 'akpp-crm-shop-categories', [$this, 'render_categories_page']);
        add_submenu_page('akpp-crm-shop', 'Масла', '🛢️ Масла', 'akpp_view_parts', 'akpp-crm-shop-oils', [$this, 'render_oils_page']);
        add_submenu_page('akpp-crm-shop', 'Склад', '📦 Склад', 'akpp_view_parts', 'akpp-crm-shop-parts', [$this, 'render_parts_page']);
        add_submenu_page('akpp-crm-shop', 'Открыть магазин на сайте', '🔗 Открыть на сайте ↗', 'akpp_view_shop', 'akpp-crm-shop-link', [$this, 'redirect_to_shop']);
        // === Интеграции ===
        add_menu_page('Интеграции', '🔌 Интеграции', 'akpp_manage_integrations', 'akpp-crm-avito-dialogs', [$this, 'render_avito_dialogs_page'], 'dashicons-admin-plugins', 35);
        add_submenu_page('akpp-crm-avito-dialogs', 'Диалоги Авито', '💬 Авито', 'akpp_manage_integrations', 'akpp-crm-avito-dialogs', [$this, 'render_avito_dialogs_page']);
        add_submenu_page('akpp-crm-avito-dialogs', 'Настройки Авито', '⚡ Настройки Авито', 'akpp_manage_integrations', 'akpp-crm-avito-settings', [$this, 'render_avito_settings_page']);
        add_submenu_page('akpp-crm-avito-dialogs', 'Telegram бот', '📱 Telegram', 'akpp_manage_integrations', 'akpp-crm-telegram', [$this, 'render_telegram_page']);
    }

    // ========================================================================
    // СТИЛИ И СКРИПТЫ (АДМИНКА)
    // ========================================================================

    public function enqueue_admin_assets($hook) {
        // Загружаем только на страницах CRM
        if (strpos($hook, 'akpp-crm') === false && strpos($hook, 'toplevel_page_akpp-crm') === false) {
            return;
        }

        $theme_uri = get_template_directory_uri();

        // Стили
        wp_enqueue_style('akpp-admin-style', $theme_uri . '/assets/css/admin.css', [], AKPP_CRM_VERSION);
        wp_enqueue_style('akpp-modal-style', $theme_uri . '/assets/css/modal.css', [], AKPP_CRM_VERSION);

        if (file_exists(get_template_directory() . '/assets/css/shop.css')) {
            wp_enqueue_style('akpp-shop-admin-style', $theme_uri . '/assets/css/shop.css', [], AKPP_CRM_VERSION);
        }

        // Скрипты
        wp_enqueue_script('jquery');
        wp_enqueue_script('akpp-admin-js', $theme_uri . '/assets/js/admin.js', ['jquery'], AKPP_CRM_VERSION, true);
        wp_enqueue_script('akpp-deal-calculator-js', $theme_uri . '/assets/js/deal-calculator.js', ['jquery'], AKPP_CRM_VERSION, true);
        wp_enqueue_script('akpp-vin-decoder-js', $theme_uri . '/assets/js/vin-decoder.js', ['jquery'], AKPP_CRM_VERSION, true);
        wp_enqueue_script('akpp-chat-js', $theme_uri . '/assets/js/chat.js', ['jquery'], AKPP_CRM_VERSION, true);

        if (file_exists(get_template_directory() . '/assets/js/shop.js')) {
            wp_enqueue_script('akpp-shop-admin-js', $theme_uri . '/assets/js/shop.js', ['jquery'], AKPP_CRM_VERSION, true);
            wp_localize_script('akpp-shop-admin-js', 'akpp_shop_config', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('akpp45_nonce'),
            ]);
        }

        // Локализация
        wp_localize_script('akpp-admin-js', 'akpp_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp45_nonce'),
        ]);

        wp_localize_script('akpp-deal-calculator-js', 'akpp_deal', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp45_nonce'),
        ]);

        wp_localize_script('akpp-vin-decoder-js', 'akpp_vin_decoder_config', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp45_nonce'),
        ]);

        wp_localize_script('akpp-chat-js', 'akpp_chat_config', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp_chat_action_nonce'),
            'strings'  => [
                'sending' => 'Отправка...',
                'error'   => 'Ошибка отправки',
            ],
        ]);
    }

    // ========================================================================
    // СТИЛИ И СКРИПТЫ (ФРОНТЕНД)
    // ========================================================================

    public function enqueue_frontend_assets() {
        $theme_uri = get_template_directory_uri();

        // Стили
        wp_enqueue_style('akpp-frontend-style', $theme_uri . '/assets/css/frontend.css', [], AKPP_CRM_VERSION);
        wp_enqueue_style('akpp-modal-style', $theme_uri . '/assets/css/modal.css', [], AKPP_CRM_VERSION);

        if (file_exists(get_template_directory() . '/assets/css/shop.css')) {
            wp_enqueue_style('akpp-shop-frontend', $theme_uri . '/assets/css/shop.css', [], AKPP_CRM_VERSION);
        }

        // Скрипты
        wp_enqueue_script('jquery');

        if (file_exists(get_template_directory() . '/assets/js/auth.js')) {
            wp_enqueue_script('akpp-auth-js', $theme_uri . '/assets/js/auth.js', ['jquery'], AKPP_CRM_VERSION, true);
        }

        if (file_exists(get_template_directory() . '/assets/js/chat.js')) {
            wp_enqueue_script('akpp-chat-frontend-js', $theme_uri . '/assets/js/chat.js', ['jquery'], AKPP_CRM_VERSION, true);
        }

        if (file_exists(get_template_directory() . '/assets/js/shop.js')) {
            wp_enqueue_script('akpp-shop-js', $theme_uri . '/assets/js/shop.js', ['jquery'], AKPP_CRM_VERSION, true);
            wp_localize_script('akpp-shop-js', 'akpp_shop_config', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('akpp45_nonce'),
                'home_url' => home_url('/'),
            ]);
        }

        // Локализация для чата (фронтенд)
        wp_localize_script('akpp-chat-frontend-js', 'akpp_frontend_chat_config', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp_frontend_chat_action'),
            'strings'  => [
                'sending' => 'Отправка...',
                'error'   => 'Ошибка отправки',
            ],
        ]);
    }

    // ========================================================================
    // ШОРТКОДЫ
    // ========================================================================

    public function register_shortcodes() {
        add_shortcode('akpp_registration_form', [$this, 'shortcode_registration_form']);
        add_shortcode('akpp_client_chat', [$this, 'shortcode_client_chat']);
        // akpp-shop-dup: шорткод akpp_shop_catalog регистрирует AKPP_Shop (class-akpp-shop.php)
        // add_shortcode('akpp_shop_catalog', [$this, 'shortcode_shop_catalog']);
        // akpp-shop-dup: шорткод akpp_shop_cart регистрирует AKPP_Shop (class-akpp-shop.php)
        // add_shortcode('akpp_shop_cart', [$this, 'shortcode_shop_cart']);
        // akpp-shop-dup: шорткод akpp_shop_checkout регистрирует AKPP_Shop (class-akpp-shop.php)
        // add_shortcode('akpp_shop_checkout', [$this, 'shortcode_shop_checkout']);
    }

    public function shortcode_registration_form() {
        ob_start();
        include AKPP_CRM_PATH . 'templates/frontend/registration.php';
        return ob_get_clean();
    }

    public function shortcode_client_chat() {
        if (!is_user_logged_in()) {
            return '<p>Пожалуйста, войдите в систему для доступа к чату.</p>';
        }
        ob_start();
        include AKPP_CRM_PATH . 'templates/frontend/chat.php';
        return ob_get_clean();
    }

    public function shortcode_shop_catalog($atts = []) {
        if (!class_exists('AKPP_Shop')) {
            return '<p>❌ Модуль магазина не загружен</p>';
        }
        return AKPP_Shop::get_instance()->shortcode_catalog($atts);
    }

    public function shortcode_shop_cart($atts = []) {
        if (!class_exists('AKPP_Shop')) {
            return '<p>❌ Модуль магазина не загружен</p>';
        }
        return AKPP_Shop::get_instance()->shortcode_cart($atts);
    }

    public function shortcode_shop_checkout($atts = []) {
        if (!class_exists('AKPP_Shop')) {
            return '<p>❌ Модуль магазина не загружен</p>';
        }
        return AKPP_Shop::get_instance()->shortcode_checkout($atts);
    }

    // ========================================================================
    // РЕНДЕР СТРАНИЦ
    // ========================================================================

    public function render_service_category_page() {
        $page = sanitize_text_field($_GET['page'] ?? '');
        $map = [
            'akpp-crm-deals-engine'     => ['engine', '🔩 ДВС'],
            'akpp-crm-deals-suspension' => ['suspension', '🛞 Ходовая и рулевое'],
            'akpp-crm-deals-body'       => ['body', '🚗 Кузовные'],
            'akpp-crm-deals-electric'   => ['electric', '⚡ Электрика'],
            'akpp-crm-deals-interior'   => ['interior', '💺 Салон'],
        ];
        $cat = $map[$page] ?? ['akpp', '🚗 Сделки'];
        include AKPP_CRM_PATH . 'templates/service_category.php';
    }

    public function render_section_placeholder() {
        echo '<div class="wrap akpp-crm-wrap"><h1 style="color:#00ff88;border-left:4px solid #00ff88;padding-left:15px;">📁 Раздел</h1><p style="color:#a0aec0;">Раздел в разработке — скоро здесь появится функционал.</p></div>';
    }

    public function render_categories_page() {
        include AKPP_CRM_PATH . 'templates/categories.php';
    }

    public function render_services_page() {
        include AKPP_CRM_PATH . 'templates/services.php';
    }

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

    public function render_shop_page() {
        $file = AKPP_CRM_PATH . 'templates/shop-admin.php';
        if (file_exists($file)) {
            include $file;
        } else {
            echo '<div class="wrap akpp-crm-wrap">';
            echo '<h1>🛒 Магазин АКПП45</h1>';
            echo '<div class="notice notice-warning"><p>⚠️ Файл шаблона не найден: <code>' . esc_html($file) . '</code></p></div>';
            echo '<h2>🔗 Быстрые ссылки:</h2><ul>';
            echo '<li><a href="' . esc_url(home_url('/shop/')) . '" target="_blank">🛒 Открыть магазин на сайте</a></li>';
            echo '<li><a href="' . esc_url(admin_url('admin.php?page=akpp-crm-parts')) . '">📦 Перейти к складу</a></li>';
            echo '<li><a href="' . esc_url(admin_url('admin.php?page=akpp-crm-agreements')) . '">📜 Согласия с офертой</a></li>';
            echo '</ul></div>';
        }
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

    public function render_users_page() {
        if (!class_exists('AKPP_Users_Table')) {
            echo '<div class="wrap"><h1>Клиенты сайта</h1><div class="notice notice-error"><p>Класс AKPP_Users_Table не найден</p></div></div>';
            return;
        }
        $table = new AKPP_Users_Table();
        echo '<div class="wrap"><h1>👤 Клиенты сайта</h1><form method="post">';
        $table->prepare_items();
        $table->display();
        echo '</form></div>';
    }

    public function render_attendance_page() {
        include AKPP_CRM_PATH . 'templates/attendance.php';
    }

    public function render_finance_page() {
        include AKPP_CRM_PATH . 'templates/finance.php';
    }

    public function render_agreements_page() {
        include AKPP_CRM_PATH . 'templates/agreements.php';
    }

    public function render_avito_dialogs_page() {
        if (!class_exists('AKPP_Avito_Dialogs_Table')) {
            echo '<div class="wrap"><h1>Диалоги Авито</h1><div class="notice notice-error"><p>Класс AKPP_Avito_Dialogs_Table не найден</p></div></div>';
            return;
        }
        $table = new AKPP_Avito_Dialogs_Table();
        echo '<div class="wrap"><h1>💬 Диалоги Авито</h1><form method="post">';
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

    // ========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // ========================================================================

    /**
     * Ссылка на магазин в верхней панели админки
     */
    public function add_shop_link_to_admin_bar($wp_admin_bar) {
        $wp_admin_bar->add_node([
            'id'    => 'akpp-shop-link',
            'title' => '🛒 Магазин АКПП45',
            'href'  => home_url('/shop/'),
            'meta'  => [
                'target' => '_blank',
                'title'  => 'Открыть магазин на сайте в новой вкладке',
            ],
        ]);
    }

    /**
     * Редирект на магазин на сайте
     */
    public function redirect_to_shop() {
        // Редирект через JS/мета-тег, т.к. шапка админки уже выведена
        $url = esc_url(home_url('/shop/'));
        echo '<script>window.location.href="' . $url . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . $url . '"></noscript>';
        echo '<p>Переход в магазин... <a href="' . $url . '">Нажмите здесь</a></p>';
        exit;
    }
}

// ============================================================================
// ЗАПУСК
// ============================================================================

function akpp_crm() {
    return AKPP_CRM::get_instance();
}

function akpp_crm_init() {
    AKPP_CRM::get_instance();
}
add_action('plugins_loaded', 'akpp_crm_init');
