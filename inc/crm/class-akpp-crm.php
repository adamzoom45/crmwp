<?php
/**
 * АКПП45 CRM - Главный класс ядра (Singleton)
 * Подключение файлов, регистрация хуков, меню и управление ресурсами.
 *
 * @package AKPP_CRM
 * @version 4.4
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
    define('AKPP_CRM_VERSION', '4.4.0');
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
    }

    public function maybe_install_db() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $current_version = get_option('akpp_crm_db_version', '0');
        $target_version  = '4.4';
        
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

        add_submenu_page($menu_slug, 'Дашборд', '📊 Дашборд', $capability, $menu_slug, [$this, 'render_dashboard_page']);
        add_submenu_page($menu_slug, 'Сделки', '📋 Сделки', $capability, 'akpp-crm-deals', [$this, 'render_deals_page']);
        add_submenu_page($menu_slug, 'Новая сделка', '➕ Новая', $capability, 'akpp-crm-new-deal', [$this, 'render_new_deal_page']);
        add_submenu_page($menu_slug, 'Сотрудники', '👥 Сотрудники', $capability, 'akpp-crm-employees', [$this, 'render_employees_page']);
        add_submenu_page($menu_slug, 'Автомобили', '🚗 Авто', $capability, 'akpp-crm-vehicles', [$this, 'render_vehicles_page']);
        add_submenu_page($menu_slug, 'Каталог АКПП', '⚙️ АКПП', $capability, 'akpp-crm-transmissions', [$this, 'render_transmissions_page']);
        add_submenu_page($menu_slug, 'Склад запчастей', '📦 Склад', $capability, 'akpp-crm-parts', [$this, 'render_parts_page']);
        add_submenu_page($menu_slug, 'Масла', '🛢️ Масла', $capability, 'akpp-crm-oils', [$this, 'render_oils_page']);
        add_submenu_page($menu_slug, 'Парсер + AI', '🤖 Парсер', $capability, 'akpp-crm-parser', [$this, 'render_parser_page']);
        add_submenu_page($menu_slug, 'Лиды', '🎯 Лиды', $capability, 'akpp-crm-leads', [$this, 'render_leads_page']);
        add_submenu_page($menu_slug, 'Клиенты сайта', '👤 Клиенты', $capability, 'akpp-crm-users', [$this, 'render_users_page']);
        add_submenu_page($menu_slug, 'Диалоги Авито', '💬 Авито', $capability, 'akpp-crm-avito-dialogs', [$this, 'render_avito_dialogs_page']);
        add_submenu_page($menu_slug, 'Настройки Авито', '⚡ Настройки Авито', $capability, 'akpp-crm-avito-settings', [$this, 'render_avito_settings_page']);
        add_submenu_page($menu_slug, 'Telegram бот', '📱 Telegram', $capability, 'akpp-crm-telegram', [$this, 'render_telegram_page']);
    }

    public function enqueue_frontend_assets() {
        $theme_uri = get_template_directory_uri();
        
        wp_enqueue_style('akpp-frontend-style', $theme_uri . '/assets/css/frontend.css', [], AKPP_CRM_VERSION);
        wp_enqueue_style('akpp-modal-style', $theme_uri . '/assets/css/modal.css', [], AKPP_CRM_VERSION);
        
        wp_enqueue_script('akpp-auth-js', $theme_uri . '/assets/js/auth.js', ['jquery'], AKPP_CRM_VERSION, true);
        wp_enqueue_script('akpp-chat-frontend-js', $theme_uri . '/assets/js/chat.js', ['jquery'], AKPP_CRM_VERSION, true);
        
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
        
        wp_enqueue_script('akpp-admin-js', $theme_uri . '/assets/js/admin.js', ['jquery'], AKPP_CRM_VERSION, true);
        wp_enqueue_script('akpp-deal-calculator-js', $theme_uri . '/assets/js/deal-calculator.js', ['jquery'], AKPP_CRM_VERSION, true);
        wp_enqueue_script('akpp-vin-decoder-js', $theme_uri . '/assets/js/vin-decoder.js', ['jquery'], AKPP_CRM_VERSION, true);
        wp_enqueue_script('akpp-chat-js', $theme_uri . '/assets/js/chat.js', ['jquery'], AKPP_CRM_VERSION, true);
        
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
            
            // Проверка nonce
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
}

function akpp_crm() {
    return AKPP_CRM::get_instance();
}