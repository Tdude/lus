<?php
/**
 * lus.php
 * LUS (LäsUppSkattning)
 *
 * @package     LUS
 * @author      Tibor Berki
 * @copyright   2024 @Tdude
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: LUS
 * Plugin URI:  https://example.com/plugins/lus
 * Description: A plugin for recording and evaluating reading comprehension
 * Version:     1.0.0
 * Author:      Tibor Berki
 * Author URI:  https://klickomaten.com
 * Text Domain: lus
 * License:     GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Plugin constants
define('LUS_VERSION', '1.0.0');
define('LUS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LUS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Runs during plugin activation.
 */
function activate_lus() {
    require_once LUS_PLUGIN_DIR . 'includes/class-lus-activator.php';
    LUS_Activator::activate();
}

/**
 * Runs during plugin deactivation.
 */
function deactivate_lus() {
    require_once LUS_PLUGIN_DIR . 'includes/class-lus-deactivator.php';
    LUS_Deactivator::deactivate();
}

/**
 * Allow setting data preservation on deactivation!
 */
register_deactivation_hook(__FILE__, function() {
    // Default to preserving data
    add_option('lus_preserve_data', true);
});

register_activation_hook(__FILE__, 'activate_lus');
register_deactivation_hook(__FILE__, 'deactivate_lus');

/**
 * Load core plugin class
 */
require_once LUS_PLUGIN_DIR . 'includes/class-lus.php';

/**
 * Begin plugin execution
 */
function run_lus() {
    $plugin = new LUS();
    $plugin->run();
}
run_lus();