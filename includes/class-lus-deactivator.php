<?php
/** class-lus-deactivator.php
 * Fired during plugin deactivation.
 *
 * @package    LUS
 * @subpackage LUS/includes
 */

class LUS_Deactivator {
    /**
     * Clean up plugin data based on settings
     */
    public static function deactivate() {
        // Only clean up if preserve_data is false
        if (!get_option('lus_preserve_data', true)) {
            self::remove_database_tables();
            self::remove_uploads();
            self::remove_options();
        }

        // Always clean up transients
        self::remove_transients();
    }

    /**
     * Remove all plugin database tables
     */
    private static function remove_database_tables() {
        global $wpdb;

        $tables = [
            'lus_responses',      // Remove child tables first (foreign key constraints)
            'lus_assessments',
            'lus_assignments',
            'lus_admin_interactions',
            'lus_questions',
            'lus_recordings',
            'lus_passages'        // Remove parent tables last
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }
    }

    /**
     * Remove uploaded files and directories
     */
    private static function remove_uploads() {
        if (file_exists(LUS_Constants::UPLOAD_DIR)) {
            self::recursive_remove_directory(LUS_Constants::UPLOAD_DIR);
        }
    }

    /**
     * Remove all plugin options
     */
    private static function remove_options() {
        $options = [
            'lus_version',
            'lus_db_version',
            'lus_installed_at',
            'lus_enable_tracking',
            'lus_preserve_data',
            'lus_data_migrated'
        ];

        foreach ($options as $option) {
            delete_option($option);
        }
    }

    /**
     * Remove any transients we've created
     */
    private static function remove_transients() {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '%_transient_lus_%'
             OR option_name LIKE '%_transient_timeout_lus_%'"
        );
    }

    /**
     * Helper function to recursively remove a directory
     */
    private static function recursive_remove_directory($directory) {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                self::recursive_remove_directory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}