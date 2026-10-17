<?php
/**
 * АКПП45 CRM - Telegram-интеграция ОТКЛЮЧЕНА (v5.1.2: удаление интеграции).
 * Заглушка сохраняет совместимость вызовов: все методы возвращают false.
 *
 * @package AKPP_CRM
 * @version 5.1.2
 */

if (!defined('ABSPATH')) exit;

class AKPP_Telegram {
    private static $instance = null;
    public static function get_instance() { if (null === self::$instance) self::$instance = new self(); return self::$instance; }
    public function __construct() {}
    public static function __callStatic($name, $args) { return false; }
    public function __call($name, $args) { return false; }
}
