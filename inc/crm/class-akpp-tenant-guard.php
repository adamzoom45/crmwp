<?php
/**
 * АКПП45 — защита клиентского сайта (tenant).
 * Срабатывает ТОЛЬКО при AKPP_TENANT_MODE (вписано в wp-config арендатора).
 * На мастере (akpp45.ru) константы нет → ничего не режется, мы видим всё.
 * Цель: арендатор не может править код/темы/плагины — серьёзные изменения только у нас.
 */
if (!defined('ABSPATH')) exit;
if (defined('AKPP_TENANT_MODE') && AKPP_TENANT_MODE) {
    if (!defined('DISALLOW_FILE_MODS'))  define('DISALLOW_FILE_MODS', true);  // запрет установки/обновления/удаления тем и плагинов из UI
    if (!defined('DISALLOW_FILE_EDIT'))  define('DISALLOW_FILE_EDIT', true);  // запрет редактора файлов

    // скрыть опасные разделы меню у всех на tenant'е (владельцы работают через мастер/сервер)
    add_action('admin_menu', function () {
        foreach (['themes.php', 'plugins.php', 'theme-editor.php', 'plugin-editor.php', 'update-core.php'] as $hook) {
            remove_menu_page($hook);
        }
        remove_submenu_page('tools.php', 'site-health.php');
    }, 999);

    // блокировать прямой заход на закрытые страницы
    add_action('admin_init', function () {
        if (!is_admin()) return;
        global $pagenow;
        $blocked = ['themes.php', 'plugins.php', 'theme-editor.php', 'plugin-editor.php', 'update-core.php', 'plugin-install.php', 'theme-install.php'];
        if (in_array($pagenow, $blocked, true)) {
            wp_safe_redirect(admin_url('admin.php?page=akpp-site-templates&locked=1')); exit;
        }
    }, 1);

    // убрать ссылки «внешний вид/плагины» из админ-бара
    add_action('admin_bar_menu', function ($bar) { $bar->remove_node('themes'); $bar->remove_node('plugins'); }, 999);
}
