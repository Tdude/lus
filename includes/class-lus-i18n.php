<?php
/**
 * File: includes/class-lus-i18n.php
 * Define the internationalization functionality
 *
 * @package    LUS
 * @subpackage LUS/includes
 */
class LUS_i18n {

    /**
     * Load the plugin text domain for translation
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            LUS_Constants::PLUGIN_NAME ,
            false,
            dirname( LUS_Constants::PLUGIN_DIR ) . '/languages/'
        );
    }
}