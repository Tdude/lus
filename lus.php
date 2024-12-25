<?php
/**
 * LUS (LäsUppSkattning) - Reading Assessment Plugin
 *
 * @package     LUS (with AI)
 * @author      Tibor Berki
 * @copyright   2024 @Tdude
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: LUS
 * Plugin URI:  https://klickomaten.com/lus
 * Description: A modularized plugin for recording and evaluating reading comprehension
 * Version:     0.0.2
 * Author:      Tibor Berki
 * Author URI:  https://klickomaten.com
 * Text Domain: lus
 * Domain Path: /languages
 * License:     GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Plugin main paths and URLs seem to need be here.
 * Nomenclature is in includes/config/class-lus-contants.php
 */
define('LUS_PLUGIN_NAME', plugin_basename(__FILE__));
define('LUS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LUS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LUS_UPLOAD_DIR', wp_upload_dir()['basedir'] . '/lus');
define('LUS_UPLOAD_URL', wp_upload_dir()['baseurl'] . '/lus');


// Ensure WordPress functions are available. ABSPATH is WP const.
if (!function_exists('plugin_dir_path')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

// Define plugin constants
require_once plugin_dir_path(__FILE__) . 'includes/config/class-lus-constants.php';

// Define directory path if not in constants
if (!defined('PLUGIN_DIR')) {
    define('PLUGIN_DIR', plugin_dir_path(__FILE__));
}

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    // Check if the class should be loaded by this autoloader
    if (strpos($class, 'LUS_') !== 0) {
        return;
    }

    // Convert class name to file path
    $class = strtolower(str_replace('_', '-', $class));
    $class = str_replace('lus-', '', $class);

    // Define possible paths for the class file
    $paths = [
        'includes',
        'includes/dto',
        'includes/strategy',
        'includes/value-objects',
        'admin',
        'public'
    ];

    // Try to find and load the class file
    foreach ($paths as $path) {
        $file = LUS_Constants::PLUGIN_DIR . $path . '/class-' . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});


/**
 * Register activation hook
 */
register_activation_hook(__FILE__, function() {
    // Check WordPress version
    if (version_compare(get_bloginfo('version'), LUS_Constants::MIN_WP_VERSION, '<')) {
        wp_die(sprintf(
            __('Detta plugin kräver WordPress version %s eller högre.', 'lus'),
            LUS_Constants::MIN_WP_VERSION
        ));
    }

    // Check PHP version
    if (version_compare(PHP_VERSION, LUS_Constants::MIN_PHP_VERSION, '<')) {
        wp_die(sprintf(
            __('Detta plugin kräver PHP version %s eller högre.', 'lus'),
            LUS_Constants::MIN_PHP_VERSION
        ));
    }

    // Run activation
    require_once LUS_Constants::PLUGIN_DIR . 'includes/class-lus-activator.php';
    LUS_Activator::activate();
});

/**
 * Register deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    require_once LUS_Constants::PLUGIN_DIR . 'includes/class-lus-deactivator.php';
    LUS_Deactivator::deactivate();
});

/**
 * Allow setting data preservation on deactivation
 */
register_deactivation_hook(__FILE__, function() {
    // Default to preserving data
    add_option('lus_preserve_data', true);
});

/**
 * Initialize the service container
 */
require_once LUS_Constants::PLUGIN_DIR . 'includes/class-lus-container.php';
LUS_Container::registerCommonServices();

/**
 * Initialize the plugin
 */
function run_lus() {
    try {
        // Core plugin instance
        $plugin = new LUS();

        // Initialize event system
        LUS_Events::on('plugin_initialized', function() {
            do_action('lus_plugin_initialized');
        });

        // Run plugin
        $plugin->run();

        // Emit initialization event
        LUS_Events::emit('plugin_initialized');

    } catch (Exception $e) {
        // Log error and display admin notice
        error_log('LUS Plugin Error: ' . $e->getMessage());
        add_action('admin_notices', function() use ($e) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html(sprintf(
                    __('LUS Plugin Error: %s', 'lus'),
                    $e->getMessage()
                ))
            );
        });
    }
}

// Start the plugin
add_action('plugins_loaded', 'run_lus');

/**
 * Register plugin lifecycle hooks
 */
class LUS_Lifecycle {
    /**
     * Register update checker
     */
    public static function register_updates() {
        if (is_admin()) {
            add_filter('pre_set_site_transient_update_plugins', [self::class, 'check_for_updates']);
        }
    }

    /**
     * Check for updates
     *
     * @param object $transient Transient data
     * @return object Modified transient data
     */
    public static function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Check for updates logic here
        return $transient;
    }

    /**
     * Clean up temporary files
     */
    public static function cleanup_temp_files() {
        $temp_dir = LUS_Constants::UPLOAD_DIR . '/lus/temp';

        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '/*');
            $now = time();

            foreach ($files as $file) {
                if (is_file($file) && ($now - filemtime($file) > 86400)) {
                    @unlink($file);
                }
            }
        }
    }
}

// Register cleanup schedule
add_action('wp', function() {
    if (!wp_next_scheduled('lus_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'lus_daily_cleanup');
    }
});

add_action('lus_daily_cleanup', ['LUS_Lifecycle', 'cleanup_temp_files']);

// Register update checker
LUS_Lifecycle::register_updates();