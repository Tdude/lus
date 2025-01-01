<?php
/**
 * File: uninstall.php
 * Fired when the plugin is deleted.
 * Delete means complete data loss, DROP_TABLES, be careful!
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Force cleanup regardless of preserve_data setting
// This ensures complete removal when deleting the plugin
require_once plugin_dir_path(__FILE__) . 'includes/class-lus-deactivator.php';

// Override preserve_data setting temporarily
update_option('lus_preserve_data', false);

// Run deactivation cleanup
LUS_Deactivator::deactivate();

// Clean up the preserve_data option itself
delete_option('lus_preserve_data');