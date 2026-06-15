<?php
/**
 * АКПП45 CRM - Основной класс ядра
 * Инициализация, подключение файлов, хуки и меню админ-панели.
 *
 * @package AKPP_CRM
 * @version 4.2
 */

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
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
     * Конструктор
     */
    private function __construct() {
        // 1. Подключение необходимых файлов
        $this->includes();

        // 2. Хуки инициализации
        add_action('admin_menu', [$this, 'register_admin_menus']);
        add_action('admin_init', [$this, 'handle_bulk_actions']);
        
        // 3. Здесь можно добавить другие хуки (enqueue_scripts, ajax_handlers и т.д.)
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Подключение файлов ядра и новых классов
     */
    private function includes() {
        $inc_dir = get_template_directory() . '/inc/';
        $crm_dir = $inc_dir . 'crm/';

        // --- СУЩЕСТВУЮЩИЕ ФАЙЛЫ (оставьте ваши текущие подключения здесь) ---
        // require_once $crm_dir . 'class-akpp-database.php';
        // require_once $crm_dir . 'class-akpp-api.php';
        // require_once $crm_dir . 'class-akpp-email.php';
        // require_once $crm_dir . 'class-deal-calculator.php';

        // --- НОВЫЕ КЛАССЫ ТАБЛИЦ (Приоритет 1) ---
        require_once $crm_dir . 'tables/class-users-table.php';
        require_once $crm_dir . 'tables/class-avito-dialogs-table.php';
        
        // --- ДРУГИЕ ТАБЛИЦЫ (если есть) ---
        // require_once $crm_dir . 'tables/class-deals-table.php';
        // require_once $crm_dir . 'tables/class-warehouse-table.php';
    }

    /**
     * Подключение стилей и скриптов в админке
     */
    public function enqueue_admin_assets($hook) {
        // Подключаем стили только на наших страницах CRM
        if (strpos($hook, 'akpp-') !== false) {
            wp_enqueue_style(
                'akpp-crm-admin',
                get_template_directory_uri() . '/inc/crm/assets/css/admin.css',
                [],
                '4.2.0'
            );
            
            // Скрипт для подтверждения действий
            wp_enqueue_script(
                'akpp-crm-admin-js',
                get_template_directory_uri() . '/inc/crm/assets/js/admin.js',
                ['jquery'],
                '4.2.0',
                true
            );
        }
    }

    /**
     * Регистрация пунктов меню в админ-панели
     */
    public function register_admin_menus() {
        // Главное меню CRM (если его ещё нет, создаём)
        add_menu_page(
            __('АКПП CRM', 'akpp-crm'),
            __('АКПП CRM', 'akpp-crm'),
            'manage_options',
            'akpp-crm-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-chart-area',
            30
        );

        // Подменю: Дашборд
        add_submenu_page(
            'akpp-crm-dashboard',
            __('Дашборд', 'akpp-crm'),
            __('Дашборд', 'akpp-crm'),
            'manage_options',
            'akpp-crm-dashboard',
            [$this, 'render_dashboard_page']
        );

        // =====================================================================
        // НОВЫЕ ПУНКТЫ МЕНЮ (Приоритет 1)
        // =====================================================================

        // 1. Клиенты сайта
        add_submenu_page(
            'akpp-crm-dashboard',
            __('Клиенты сайта', 'akpp-crm'),
            __('Клиенты', 'akpp-crm'),
            'manage_options',
            'akpp-users',
            [$this, 'render_users_page']
        );

        // 2. Диалоги Авито
        add_submenu_page(
            'akpp-crm-dashboard',
            __('Диалоги Авито', 'akpp-crm'),
            __('Авито чаты', 'akpp-crm'),
            'manage_options',
            'akpp-avito-dialogs',
            [$this, 'render_avito_dialogs_page']
        );

        // 3. Настройки Авито (заглушка для будущего)
        add_submenu_page(
            'akpp-crm-dashboard',
            __('Настройки Авито', 'akpp-crm'),
            __('Настройки Авито', 'akpp-crm'),
            'manage_options',
            'akpp-avito-settings',
            [$this, 'render_avito_settings_page']
        );
    }

    /**
     * Обработка массовых действий (вызывается до рендеринга)
     */
    public function handle_bulk_actions() {
        // Этот метод гарантирует, что process_bulk_action() в таблицах 
        // отработает до вывода HTML, чтобы редиректы сработали корректно.
        if (isset($_GET['page']) && in_array($_GET['page'], ['akpp-users', 'akpp-avito-dialogs'])) {
            if ($_GET['page'] === 'akpp-users') {
                $table = new AKPP_Users_Table();
                $table->process_bulk_action();
            } elseif ($_GET['page'] === 'akpp-avito-dialogs') {
                $table = new AKPP_Avito_Dialogs_Table();
                $table->process_bulk_action();
            }
        }
    }

    // =========================================================================
    // МЕТОДЫ РЕНДЕРИНГА СТРАНИЦ
    // =========================================================================

    /**
     * Рендер страницы Дашборда (заглушка)
     */
    public function render_dashboard_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Дашборд АКПП CRM', 'akpp-crm'); ?></h1>
            <p><?php _e('Добро пожаловать в панель управления. Выберите раздел в меню слева.', 'akpp-crm'); ?></p>
        </div>
        <?php
    }

    /**
     * Рендер страницы "Клиенты сайта"
     */
    public function render_users_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Недостаточно прав для доступа к этой странице.', 'akpp-crm'));
        }

        $users_table = new AKPP_Users_Table();
        $users_table->prepare_items(); // Подготовка данных ДО вывода уведомлений

        // Обработка уведомлений об удалении
        if (isset($_GET['deleted'])) {
            $count = intval($_GET['deleted']);
            $message = $count > 1 
                ? sprintf(__('Успешно удалено клиентов: %d', 'akpp-crm'), $count)
                : __('Клиент успешно удалён.', 'akpp-crm');
            
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Клиенты сайта', 'akpp-crm'); ?></h1>
            <hr class="wp-header-end">
            
            <form method="post">
                <?php $users_table->display(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Рендер страницы "Диалоги Авито"
     */
    public function render_avito_dialogs_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Недостаточно прав для доступа к этой странице.', 'akpp-crm'));
        }

        $dialogs_table = new AKPP_Avito_Dialogs_Table();
        $dialogs_table->prepare_items();

        // Обработка уведомлений об обновлениях
        if (isset($_GET['updated'])) {
            $count = intval($_GET['updated']);
            $message = $count > 1 
                ? sprintf(__('Действие успешно выполнено для %d диалогов.', 'akpp-crm'), $count)
                : __('Действие успешно выполнено.', 'akpp-crm');
            
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Диалоги Авито', 'akpp-crm'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=akpp-avito-settings')); ?>" class="page-title-action">
                <?php _e('⚙️ Настройки API', 'akpp-crm'); ?>
            </a>
            <hr class="wp-header-end">
            
            <form method="post">
                <?php $dialogs_table->display(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Рендер страницы "Настройки Авито" (заглушка для Приоритета 2)
     */
    public function render_avito_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Недостаточно прав для доступа к этой странице.', 'akpp-crm'));
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Настройки интеграции с Авито', 'akpp-crm'); ?></h1>
            <p><?php _e('Здесь будут поля для Client ID, Client Secret и настройки Webhook (Фаза 2).', 'akpp-crm'); ?></p>
        </div>
        <?php
    }
}

// Инициализация плагина/темы
function akpp_crm_init() {
    return AKPP_CRM::get_instance();
}
add_action('plugins_loaded', 'akpp_crm_init'); // Или 'after_setup_theme', если это строго тема
