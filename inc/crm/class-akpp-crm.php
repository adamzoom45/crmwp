<?php
/**
 * АКПП45 CRM - Главный класс ядра (Singleton)
 * Подключение файлов, регистрация хуков, меню и управление ресурсами.
 *
 * @package AKPP_CRM
 * @version 4.5
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

    private function includes() {
        require_once AKPP_CRM_PATH . 'class-akpp-install.php';
        require_once AKPP_CRM_PATH . 'class-akpp-ajax.php';
        require_once AKPP_CRM_PATH . 'class-akpp-auth.php';
        require_once AKPP_CRM_PATH . 'class-akpp-db.php';
        require_once AKPP_CRM_PATH . 'class-akpp-email.php';
        require_once AKPP_CRM_PATH . 'class-akpp-push.php';
        
        require_once AKPP_CRM_PATH . 'class-avito-api.php';
        require_once AKPP_CRM_PATH . 'class-avito-webhook.php';
        require_once AKPP_CRM_PATH . 'class-avito-cron.php';
        require_once AKPP_CRM_PATH . 'class-chat-ajax.php';
        require_once AKPP_CRM_PATH . 'class-user-registration.php';
        require_once AKPP_CRM_PATH . 'class-akpp-telegram.php';
        require_once AKPP_CRM_PATH . 'class-akpp-parser.php';
        
        // ✅ НОВОЕ: Магазин
        if (file_exists(AKPP_CRM_PATH . 'class-akpp-shop.php')) {
            require_once AKPP_CRM_PATH . 'class-akpp-shop.php';
        }
        
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
        add_action('admin_menu', [$this, 'register_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('init', [$this, 'register_shortcodes']);
        add_action('admin_init', [$this, 'maybe_install_db']);
        
        // ✅ НОВОЕ: Ссылка на магазин в верхней панели админки
        add_action('admin_bar_menu', [$this, 'add_shop_link_to_admin_bar'], 100);
    }

    public function maybe_install_db() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $current_version = get_option('akpp_crm_db_version', '0');
        $target_version  = '5.1';
        
        if (version_compare($current_version, $target_version, '<')) {
            $lock_key = 'akpp_crm_db_installing';
            if (get_transient($lock_key)) {
                return;
            }
            
            set_transient($lock_key, true, 300);
            
            try {
                require_once AKPP_CRM_PATH . 'class-akpp-install.php';
                $installer = new AKPP_Install();
                $result = $installer->run();
                
                if ($result !== false) {
                    update_option('akpp_crm_db_version', $target_version);
                }
            } catch (Exception $e) {
                error_log('AKPP CRM DB Install Error: ' . $e->getMessage());
            } finally {
                delete_transient($lock_key);
            }
        }
    }

    private function init_components() {
        AKPP_AJAX::get_instance();
        new AKPP_Chat_AJAX();
        new AKPP_User_Registration();
        
        if (class_exists('AKPP_Avito_API')) {
            AKPP_Avito_API::get_instance();
        }
        if (class_exists('AKPP_Avito_Webhook')) {
            new AKPP_Avito_Webhook();
        }
        if (class_exists('AKPP_Avito_Cron')) {
            new AKPP_Avito_Cron();
        }
        
        if (class_exists('AKPP_Telegram')) {
            AKPP_Telegram::get_instance();
        }
        
        // ✅ НОВОЕ: Инициализация магазина
        if (class_exists('AKPP_Shop')) {
            AKPP_Shop::get_instance();
        }
    }

    /**
     * Регистрация меню в админ-панели
     */
    public function register_admin_menus() {
        $capability = 'manage_options';
        $menu_slug  = 'akpp-crm-dashboard';

        add_menu_page(
            'АКПП CRM',
            '🚗 АКПП CRM',
            $capability,
            $menu_slug,
            [$this, 'render_dashboard_page'],
            'dashicons-chart-area',
            30
        );

        // Основные разделы
        add_submenu_page($menu_slug, 'Дашборд', '📊 Дашборд', $capability, $menu_slug, [$this, 'render_dashboard_page']);
        add_submenu_page($menu_slug, 'Сделки', '📋 Сделки', $capability, 'akpp-crm-deals', [$this, 'render_deals_page']);
        add_submenu_page($menu_slug, 'Новая сделка', '➕ Новая', $capability, 'akpp-crm-new-deal', [$this, 'render_new_deal_page']);
        add_submenu_page($menu_slug, 'Сотрудники', '👥 Сотрудники', $capability, 'akpp-crm-employees', [$this, 'render_employees_page']);
        add_submenu_page($menu_slug, 'Автомобили', '🚗 Авто', $capability, 'akpp-crm-vehicles', [$this, 'render_vehicles_page']);
        add_submenu_page($menu_slug, 'Каталог АКПП', '⚙️ АКПП', $capability, 'akpp-crm-transmissions', [$this, 'render_transmissions_page']);
        
        // ✅ НОВОЕ: Магазин и склад
        add_submenu_page($menu_slug, 'Склад запчастей', '📦 Склад', $capability, 'akpp-crm-parts', [$this, 'render_parts_page']);
        add_submenu_page($menu_slug, '🛒 Магазин', '🛒 Магазин', $capability, 'akpp-crm-shop', [$this, 'render_shop_page']);
        add_submenu_page($menu_slug, 'Масла', '🛢️ Масла', $capability, 'akpp-crm-oils', [$this, 'render_oils_page']);
        
        // Аналитика и клиенты
        add_submenu_page($menu_slug, 'Парсер + AI', '🤖 Парсер', $capability, 'akpp-crm-parser', [$this, 'render_parser_page']);
        add_submenu_page($menu_slug, 'Лиды', '🎯 Лиды', $capability, 'akpp-crm-leads', [$this, 'render_leads_page']);
        add_submenu_page($menu_slug, 'Клиенты сайта', '👤 Клиенты', $capability, 'akpp-crm-users', [$this, 'render_users_page']);
        
        // ✅ НОВОЕ: Согласия с офертой
        add_submenu_page($menu_slug, '📜 Согласия с офертой', '📜 Оферты', $capability, 'akpp-crm-agreements', [$this, 'render_agreements_page']);
        
        // Интеграции
        add_submenu_page($menu_slug, 'Диалоги Авито', '💬 Авито', $capability, 'akpp-crm-avito-dialogs', [$this, 'render_avito_dialogs_page']);
        add_submenu_page($menu_slug, 'Настройки Авито', '⚡ Настройки Авито', $capability, 'akpp-crm-avito-settings', [$this, 'render_avito_settings_page']);
        add_submenu_page($menu_slug, 'Telegram бот', '📱 Telegram', $capability, 'akpp-crm-telegram', [$this, 'render_telegram_page']);
        
        // ✅ НОВОЕ: Разделитель + ссылка на магазин на сайте
        add_submenu_page($menu_slug, 'Открыть магазин на сайте', '🔗 Открыть магазин ↗', $capability, 'akpp-crm-shop-link', [$this, 'redirect_to_shop']);
    }

    /**
     * ✅ НОВОЕ: Ссылка на магазин в верхней панели админки
     */
    public function add_shop_link_to_admin_bar($wp_admin_bar) {
        $wp_admin_bar->add_node([
            'id'    => 'akpp-shop-link',
            'title' => '🛒 Магазин АКПП45',
            'href'  => home_url('/shop/'),
            'meta'  => [
                'target' => '_blank',
                'title'  => 'Открыть магазин на сайте в новой вкладке'
            ]
        ]);
    }

    /**
     * ✅ НОВОЕ: Редирект на магазин на сайте
     */
    public function redirect_to_shop() {
        wp_redirect(home_url('/shop/'));
        exit;
    }

    public function enqueue_frontend_assets() {
        $theme_uri = get_template_directory_uri();
        
        wp_enqueue_style('akpp-frontend-style', $theme_uri . '/assets/css/frontend.css', [], AKPP_CRM_VERSION);
        wp_enqueue_style('akpp-modal-style', $theme_uri . '/assets/css/modal.css', [], AKPP_CRM_VERSION);
        
        // ✅ НОВОЕ: Стили магазина на фронтенде
        if (file_exists(get_template_directory() . '/assets/css/shop.css')) {
            wp_enqueue_style('akpp-shop-style', $theme_uri . '/assets/css/shop.css', [], AKPP_CRM_VERSION);
        }
        
        wp_enqueue_script('akpp-auth-js', $theme_uri . '/assets/js/auth.js', ['jquery'], AKPP_CRM_VERSION, true);
        wp_enqueue_script('akpp-chat-frontend-js', $theme_uri . '/assets/js/chat.js', ['jquery'], AKPP_CRM_VERSION, true);
        
        // ✅ НОВОЕ: Скрипты магазина на фронтенде
        if (file_exists(get_template_directory() . '/assets/js/shop.js')) {
            wp_enqueue_script('akpp-shop-js', $theme_uri . '/assets/js/shop.js', ['jquery'], AKPP_CRM_VERSION, true);
            wp_localize_script('akpp-shop-js', 'akpp_shop_config', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('akpp45_nonce'),
                'home_url' => home_url('/'),
            ]);
        }
        
        wp_localize_script('akpp-chat-frontend-js', 'akpp_frontend_chat_config', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp_frontend_chat_action'),
            'strings'  => [
                'sending' => 'Отправка...',
                'error'   => 'Ошибка отправки'
            ]
        ]);
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'akpp-crm') === false && strpos($hook, 'toplevel_page_akpp-crm') === false) {
            return;
        }

        $theme_uri = get_template_directory_uri();
        
        wp_enqueue_style('akpp-admin-style', $theme_uri . '/assets/css/admin.css', [], AKPP_CRM_VERSION);
        
        // ✅ НОВОЕ: Стили магазина в админке
        if (file_exists(get_template_directory() . '/assets/css/shop.css')) {
            wp_enqueue_style('akpp-shop-admin-style', $theme_uri . '/assets/css/shop.css', [], AKPP_CRM_VERSION);
        }
        
        wp_enqueue_script('akpp-admin-js', $theme_uri . '/assets/js/admin.js', ['jquery'], AKPP_CRM_VERSION, true);
        wp_enqueue_script('akpp-deal-calculator-js', $theme_uri . '/assets/js/deal-calculator.js', ['jquery'], AKPP_CRM_VERSION, true);
        wp_enqueue_script('akpp-vin-decoder-js', $theme_uri . '/assets/js/vin-decoder.js', ['jquery'], AKPP_CRM_VERSION, true);
        wp_enqueue_script('akpp-chat-js', $theme_uri . '/assets/js/chat.js', ['jquery'], AKPP_CRM_VERSION, true);
        
        // ✅ НОВОЕ: Скрипты магазина в админке
        if (file_exists(get_template_directory() . '/assets/js/shop.js')) {
            wp_enqueue_script('akpp-shop-admin-js', $theme_uri . '/assets/js/shop.js', ['jquery'], AKPP_CRM_VERSION, true);
            wp_localize_script('akpp-shop-admin-js', 'akpp_shop_config', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('akpp45_nonce'),
            ]);
        }
        
        wp_localize_script('akpp-admin-js', 'akpp_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('akpp45_nonce')
        ]);
        
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
                'sending' => 'Отправка...',
                'error'   => 'Ошибка отправки'
            ]
        ]);
    }

    public function register_shortcodes() {
        add_shortcode('akpp_registration_form', [$this, 'shortcode_registration_form']);
        add_shortcode('akpp_client_chat', [$this, 'shortcode_client_chat']);
        // ✅ НОВОЕ: Шорткоды магазина
        add_shortcode('akpp_shop_catalog', [$this, 'shortcode_shop_catalog']);
        add_shortcode('akpp_shop_cart', [$this, 'shortcode_shop_cart']);
        add_shortcode('akpp_shop_checkout', [$this, 'shortcode_shop_checkout']);
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

    // ✅ НОВОЕ: Шорткоды магазина
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

    // ✅ НОВОЕ: Страница магазина
    public function render_shop_page() {
        $file = AKPP_CRM_PATH . 'templates/shop-admin.php';
        if (file_exists($file)) {
            include $file;
        } else {
            echo '<div class="wrap akpp-crm-wrap">';
            echo '<h1>🛒 Магазин АКПП45</h1>';
            echo '<div class="notice notice-error"><p>❌ Файл шаблона не найден: <code>' . esc_html($file) . '</code></p></div>';
            echo '<p>Создайте файл <code>templates/shop-admin.php</code> для отображения страницы магазина.</p>';
            echo '<hr>';
            echo '<h2>🔗 Быстрые ссылки:</h2>';
            echo '<ul>';
            echo '<li><a href="' . esc_url(home_url('/shop/')) . '" target="_blank">🛒 Открыть магазин на сайте ↗</a></li>';
            echo '<li><a href="' . esc_url(admin_url('admin.php?page=akpp-crm-parts')) . '">📦 Перейти к складу</a></li>';
            echo '<li><a href="' . esc_url(admin_url('admin.php?page=akpp-crm-agreements')) . '">📜 Согласия с офертой</a></li>';
            echo '</ul>';
            echo '</div>';
        }
    }

    public function render_oils_page() {
        include AKPP_CRM_PATH . 'templates/oils.php';
    }

    public function render_parser_page() {
        include AKPP_CRM_PATH . 'templates/parser.php';
    }

    /**
     * Страница лидов с обработкой действий (удаление/редактирование)
     */
    public function render_leads_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'akpp_leads';
        
        // ====================================================================
        // ОБРАБОТКА ДЕЙСТВИЙ
        // ====================================================================
        
        // 1. Удаление лида
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_lead_' . $id)) {
                echo '<div class="notice notice-error is-dismissible"><p>❌ Ошибка безопасности (nonce). Попробуйте ещё раз.</p></div>';
            } else {
                $result = $wpdb->delete($table_name, ['id' => $id]);
                if ($result !== false) {
                    echo '<div class="notice notice-success is-dismissible"><p>✅ Лид #' . $id . ' успешно удалён</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>❌ Ошибка удаления лида</p></div>';
                }
            }
        }
        
        // 2. Сохранение отредактированного лида (POST)
        if (isset($_POST['save_lead']) && isset($_POST['lead_id'])) {
            $lead_id = intval($_POST['lead_id']);
            
            $update_data = [
                'client_name'  => sanitize_text_field($_POST['client_name'] ?? ''),
                'client_phone' => sanitize_text_field($_POST['client_phone'] ?? ''),
                'car_brand'    => sanitize_text_field($_POST['car_brand'] ?? ''),
                'problem'      => sanitize_textarea_field($_POST['problem'] ?? ''),
                'status'       => sanitize_text_field($_POST['status'] ?? 'new'),
                'updated_at'   => current_time('mysql'),
            ];
            
            $result = $wpdb->update($table_name, $update_data, ['id' => $lead_id]);
            
            if ($result !== false) {
                echo '<div class="notice notice-success is-dismissible"><p>✅ Лид #' . $lead_id . ' успешно обновлён</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>❌ Ошибка обновления лида</p></div>';
            }
        }
        
        // 3. Редактирование лида (GET с action=edit)
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $lead = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d", 
                $id
            ), ARRAY_A);
            
            if (!$lead) {
                echo '<div class="notice notice-error is-dismissible"><p>❌ Лид не найден</p></div>';
            } else {
                $this->render_lead_edit_form($lead);
                return;
            }
        }
        
        // ====================================================================
        // ОТОБРАЖЕНИЕ ТАБЛИЦЫ ЛИДОВ
        // ====================================================================
        if (!class_exists('AKPP_Leads_Table')) {
            require_once AKPP_CRM_PATH . 'tables/class-leads-table.php';
        }
        
        echo '<div class="wrap akpp-crm-wrap">';
        echo '<h1 style="color: #00ff88; border-left: 4px solid #00ff88; padding-left: 15px;">🎯 Лиды</h1>';
        
        $table = new AKPP_Leads_Table();
        $table->prepare_items();
        
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="akpp-crm-leads">';
        $table->display();
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Форма редактирования лида
     */
    private function render_lead_edit_form($lead) {
        $statuses = [
            'new'        => '🆕 Новый',
            'contacted'  => '📞 Связались',
            'diagnostic' => '🔍 Диагностика',
            'in_work'    => '🔧 В работе',
            'completed'  => '✅ Выполнено',
            'converted'  => '💰 Конвертирован',
            'cancelled'  => '❌ Отменено',
            'lost'       => '❌ Потерян',
        ];
        ?>
        <div class="wrap akpp-crm-wrap">
            <h1 style="color: #00ff88; border-left: 4px solid #00ff88; padding-left: 15px;">
                ✏️ Редактирование лида #<?php echo intval($lead['id']); ?>
            </h1>
            
            <div class="akpp-card" style="background: #1a1f2e; border: 1px solid #2d3748; border-radius: 12px; padding: 24px; max-width: 800px;">
                <form method="post" action="">
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;color:#a0aec0;text-transform:uppercase;font-size:13px;">
                            ФИО Клиента <span style="color:#fc8181;">*</span>
                        </label>
                        <input type="text" name="client_name" 
                               value="<?php echo esc_attr($lead['client_name']); ?>" 
                               required
                               style="width:100%;padding:12px 16px;background:#2d3748;border:2px solid #4a5568;border-radius:8px;color:#fff;font-size:14px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;color:#a0aec0;text-transform:uppercase;font-size:13px;">
                            Телефон <span style="color:#fc8181;">*</span>
                        </label>
                        <input type="tel" name="client_phone" 
                               value="<?php echo esc_attr($lead['client_phone']); ?>" 
                               required
                               style="width:100%;padding:12px 16px;background:#2d3748;border:2px solid #4a5568;border-radius:8px;color:#fff;font-size:14px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;color:#a0aec0;text-transform:uppercase;font-size:13px;">
                            Автомобиль
                        </label>
                        <input type="text" name="car_brand" 
                               value="<?php echo esc_attr($lead['car_brand']); ?>"
                               placeholder="Toyota Camry 2020"
                               style="width:100%;padding:12px 16px;background:#2d3748;border:2px solid #4a5568;border-radius:8px;color:#fff;font-size:14px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;color:#a0aec0;text-transform:uppercase;font-size:13px;">
                            Проблема
                        </label>
                        <textarea name="problem" rows="5" 
                                  style="width:100%;padding:12px 16px;background:#2d3748;border:2px solid #4a5568;border-radius:8px;color:#fff;font-size:14px;resize:vertical;"><?php echo esc_textarea($lead['problem']); ?></textarea>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;color:#a0aec0;text-transform:uppercase;font-size:13px;">
                            Статус
                        </label>
                        <select name="status" 
                                style="width:100%;padding:12px 16px;background:#2d3748;border:2px solid #4a5568;border-radius:8px;color:#fff;font-size:14px;">
                            <?php foreach ($statuses as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" 
                                        <?php selected($lead['status'], $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 20px; border-top: 1px solid #2d3748;">
                        <a href="?page=akpp-crm-leads" 
                           class="button" 
                           style="background:transparent;color:#a0aec0;border:2px solid #4a5568;padding:12px 24px;border-radius:8px;font-weight:600;text-decoration:none;">
                            Отмена
                        </a>
                        <button type="submit" name="save_lead" value="1"
                                style="background:linear-gradient(135deg,#00ff88 0%,#00cc6a 100%);color:#1a1f2e;border:none;padding:12px 24px;border-radius:8px;font-weight:600;cursor:pointer;box-shadow:0 4px 12px rgba(0,255,136,0.3);">
                            💾 Сохранить изменения
                        </button>
                    </div>
                    
                    <input type="hidden" name="lead_id" value="<?php echo intval($lead['id']); ?>">
                </form>
                
                <!-- Информация о лиде -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #2d3748;">
                    <h3 style="color: #00ff88; font-size: 14px; margin-bottom: 12px;">📋 Информация</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13px; color: #a0aec0;">
                        <div><strong>ID лида:</strong> #<?php echo intval($lead['id']); ?></div>
                        <div><strong>Источник:</strong> <?php echo esc_html($lead['source'] ?? '—'); ?></div>
                        <div><strong>Создан:</strong> <?php echo esc_html($lead['created_at'] ?? '—'); ?></div>
                        <div><strong>Обновлён:</strong> <?php echo esc_html($lead['updated_at'] ?? '—'); ?></div>
                    </div>
                    
                    <?php if (!empty($lead['source']) && $lead['source'] === 'site_booking') : ?>
                    <div style="margin-top: 15px;">
                        <a href="?page=akpp-crm-new-deal&lead_id=<?php echo intval($lead['id']); ?>&_wpnonce=<?php echo wp_create_nonce('create_deal_from_lead_' . $lead['id']); ?>" 
                           class="button" 
                           style="background:linear-gradient(135deg,#00ff88 0%,#00cc6a 100%);color:#1a1f2e;border:none;padding:10px 20px;border-radius:8px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
                            💰 Создать сделку из этого лида
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_users_page() {
        if (!class_exists('AKPP_Users_Table')) return;
        $table = new AKPP_Users_Table();
        echo '<div class="wrap"><h1>Клиенты сайта</h1><form method="post">';
        $table->prepare_items();
        $table->display();
        echo '</form></div>';
    }

    public function render_avito_dialogs_page() {
        if (!class_exists('AKPP_Avito_Dialogs_Table')) return;
        $table = new AKPP_Avito_Dialogs_Table();
        echo '<div class="wrap"><h1>Диалоги Авито</h1><form method="post">';
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

    // ✅ НОВОЕ: Страница согласий с офертой
    public function render_agreements_page() {
        $file = AKPP_CRM_PATH . 'templates/agreements.php';
        if (file_exists($file)) {
            include $file;
        } else {
            // Встроенный шаблон, если файл отсутствует
            global $wpdb;
            $table = $wpdb->prefix . 'akpp_agreements';
            
            // Проверяем существование таблицы
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            
            echo '<div class="wrap akpp-crm-wrap">';
            echo '<h1 style="color: #00ff88; border-left: 4px solid #00ff88; padding-left: 15px;">📜 Согласия с договором-офертой</h1>';
            
            if (!$table_exists) {
                echo '<div class="notice notice-warning"><p>⚠️ Таблица <code>' . esc_html($table) . '</code> не создана. Выполните SQL-скрипт для создания таблицы согласий.</p>';
                echo '<p><a href="' . esc_url(admin_url('admin.php?page=akpp-crm-shop')) . '" class="button">Перейти к настройкам магазина</a></p></div>';
                echo '</div>';
                return;
            }
            
            // Статистика
            $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $today = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE DATE(accepted_at) = CURDATE()"
            ));
            $month = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE MONTH(accepted_at) = MONTH(CURDATE()) AND YEAR(accepted_at) = YEAR(CURDATE())"
            ));
            
            echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin:20px 0;">';
            echo '<div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:20px;text-align:center;">';
            echo '<div style="font-size:32px;font-weight:700;color:#00ff88;">' . $total . '</div>';
            echo '<div style="color:#a0aec0;font-size:13px;text-transform:uppercase;">Всего согласий</div>';
            echo '</div>';
            echo '<div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:20px;text-align:center;">';
            echo '<div style="font-size:32px;font-weight:700;color:#63b3ed;">' . $today . '</div>';
            echo '<div style="color:#a0aec0;font-size:13px;text-transform:uppercase;">Сегодня</div>';
            echo '</div>';
            echo '<div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:20px;text-align:center;">';
            echo '<div style="font-size:32px;font-weight:700;color:#f6ad55;">' . $month . '</div>';
            echo '<div style="color:#a0aec0;font-size:13px;text-transform:uppercase;">За месяц</div>';
            echo '</div>';
            echo '</div>';
            
            // Таблица согласий
            $agreements = $wpdb->get_results("SELECT * FROM {$table} ORDER BY accepted_at DESC LIMIT 100");
            
            echo '<div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:20px;">';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th style="width:50px;">ID</th>';
            echo '<th>Клиент</th>';
            echo '<th>Телефон</th>';
            echo '<th>Сделка</th>';
            echo '<th>Источник</th>';
            echo '<th>IP</th>';
            echo '<th>Дата согласия</th>';
            echo '</tr></thead><tbody>';
            
            if (empty($agreements)) {
                echo '<tr><td colspan="7" style="text-align:center;padding:40px;color:#718096;">Согласий ещё нет</td></tr>';
            } else {
                foreach ($agreements as $agr) {
                    $sources = ['crm_deal' => 'CRM Сделка', 'site_form' => 'Форма на сайте', 'registration' => 'Регистрация'];
                    $source_label = $sources[$agr->source] ?? $agr->source;
                    
                    echo '<tr>';
                    echo '<td>' . intval($agr->id) . '</td>';
                    echo '<td><strong>' . esc_html($agr->client_name) . '</strong></td>';
                    echo '<td>' . esc_html($agr->client_phone) . '</td>';
                    echo '<td>';
                    if ($agr->deal_id) {
                        echo '<a href="' . esc_url(admin_url('admin.php?page=akpp-crm-deals&view=' . $agr->deal_id)) . '" style="color:#00ff88;">#' . intval($agr->deal_id) . '</a>';
                    } else {
                        echo '<span style="color:#718096;">—</span>';
                    }
                    echo '</td>';
                    echo '<td><span style="display:inline-block;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;background:#2d3748;color:#e2e8f0;">' . esc_html($source_label) . '</span></td>';
                    echo '<td><code style="font-size:11px;">' . esc_html($agr->ip_address) . '</code></td>';
                    echo '<td>' . date_i18n('d.m.Y H:i', strtotime($agr->accepted_at)) . '</td>';
                    echo '</tr>';
                }
            }
            
            echo '</tbody></table></div>';
            echo '</div>';
        }
    }
}

function akpp_crm() {
    return AKPP_CRM::get_instance();
}