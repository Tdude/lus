<?php
/**
 * File: includes/class-lus-i18n.php
 * Define the internationalization functionality
 *
 * @package    LUS
 * @subpackage LUS/includes
 */
class LUS_i18n {
    private static $instance = null;

    public function __construct() {
        if (self::$instance !== null) {
            return self::$instance;
        }
        self::$instance = $this;
    }

    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            LUS_Constants::PLUGIN_NAME ,
            false,
            dirname( LUS_Constants::PLUGIN_DIR ) . '/languages/'
        );
    }
}