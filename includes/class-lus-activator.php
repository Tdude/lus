<?php
/** class-lus-activator.php
 * Fired during plugin activation.
 *
 * Handles database creation, upgrades, and initialization of plugin settings.
 *
 * @package    LUS
 * @subpackage LUS/includes
 */

class LUS_Activator {

    /**
     * Create the necessary database tables and plugin setup.
     */
    public static function activate() {
        // 1. Check capabilities
        if (!current_user_can('activate_plugins')) {
            wp_die(__('You do not have sufficient permissions to activate plugins', 'lus'));
        }

        // 2. Check core requirements first
        self::check_requirements();

        // 3. Set up directories
        self::create_directories();

        // 4. Database operations
        self::create_database_tables();
        self::upgrade_database_schema();

        // 5. Final setup
        self::set_plugin_options();
        //self::migrate_data_if_needed(); // Removed this for brevity
        self::schedule_tasks();
    }

    private static function check_requirements() {
        global $wpdb;

        // Core requirement checks
        if (version_compare(get_bloginfo('version'), LUS_Constants::MIN_WP_VERSION, '<')) {
            wp_die(sprintf(__('LUS requires WordPress version %s or higher.', 'lus'),
                   LUS_Constants::MIN_WP_VERSION));
        }
        // PHP
        if (version_compare(PHP_VERSION, LUS_Constants::MIN_PHP_VERSION, '<')) {
            wp_die(sprintf(__('LUS requires PHP version %s or higher.', 'lus'),
                   LUS_Constants::MIN_PHP_VERSION));
        }

        // Database checks
        $mysql_version = $wpdb->db_version();
        if (version_compare($mysql_version, '5.6', '<')) {
            wp_die(__('LUS requires MySQL 5.6 or higher.', 'lus'));
        }

        // Check if uploads directory is writable
        $upload_dir = wp_upload_dir();
        if (!wp_is_writable($upload_dir['basedir'])) {
            wp_die(sprintf(__('Stupid LUSWordPress uploads directory is not writable: %s', 'lus'), $upload_dir['basedir']));
        }
    }

    private static function create_directories() {
        // First ensure WordPress upload directory exists
        $wp_upload_dir = wp_upload_dir();
        if ($wp_upload_dir['error']) {
            wp_die($wp_upload_dir['error']);
        }

        // Create WordPress year/month directories
        $year_dir = $wp_upload_dir['basedir'] . '/' . date('Y');
        $month_dir = $year_dir . '/' . date('m');

        if (!file_exists($year_dir)) {
            if (!wp_mkdir_p($year_dir)) {
                wp_die(sprintf(__('Could not create year directory: %s', 'lus'), $year_dir));
            }
        }

        if (!file_exists($month_dir)) {
            if (!wp_mkdir_p($month_dir)) {
                wp_die(sprintf(__('Could not create month directory: %s', 'lus'), $month_dir));
            }
        }

    // Use direct path construction since constants may not be available during activation
    $lus_base_dir = $wp_upload_dir['basedir'] . '/lus';

    $dirs = [
        $lus_base_dir,
        $lus_base_dir . '/temp'
    ];

        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                if (!wp_mkdir_p($dir)) {
                    wp_die(sprintf(__('Could not create directory: %s', 'lus'), $dir));
                }
                chmod($dir, 0755);
            }
        }
    }

    /**
     * Get complete table definitions
     *
     * @return array Array of table definitions
     */
    private static function get_table_definitions() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        return [
            // Passages (Reading Texts)
            'lus_passages' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lus_passages (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                title varchar(255) NOT NULL,
                content longtext NOT NULL,
                time_limit int(11) DEFAULT 180,
                difficulty_level tinyint(4) DEFAULT 1,
                created_by bigint(20) UNSIGNED NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                deleted_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY created_by (created_by),
                KEY difficulty_level (difficulty_level),
                KEY idx_soft_delete (deleted_at)
            ) {$charset_collate}",

            // Recordings
            'lus_recordings' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lus_recordings (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id bigint(20) UNSIGNED NOT NULL,
                passage_id bigint(20) UNSIGNED NOT NULL,
                audio_file_path varchar(255) NOT NULL,
                duration int(11) NOT NULL,
                status varchar(20) DEFAULT 'pending',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY passage_id (passage_id),
                KEY idx_status (status),
                KEY idx_created_at (created_at),
                CONSTRAINT fk_recording_user
                    FOREIGN KEY (user_id)
                    REFERENCES {$wpdb->users} (ID)
                    ON DELETE CASCADE,
                CONSTRAINT fk_recording_passage
                    FOREIGN KEY (passage_id)
                    REFERENCES {$wpdb->prefix}lus_passages (id)
                    ON DELETE CASCADE
            ) {$charset_collate}",

            // Questions
            'lus_questions' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lus_questions (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                passage_id bigint(20) UNSIGNED NOT NULL,
                question_text text NOT NULL,
                correct_answer text NOT NULL,
                weight float DEFAULT 1.0,
                active tinyint(1) DEFAULT 1,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY passage_id (passage_id),
                KEY idx_active (active),
                CONSTRAINT fk_question_passage
                    FOREIGN KEY (passage_id)
                    REFERENCES {$wpdb->prefix}lus_passages (id)
                    ON DELETE CASCADE
            ) {$charset_collate}",

            // Responses
            'lus_responses' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lus_responses (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                recording_id bigint(20) UNSIGNED NOT NULL,
                question_id bigint(20) UNSIGNED NOT NULL,
                user_answer text NOT NULL,
                is_correct tinyint(1) DEFAULT 0,
                score float DEFAULT 0,
                similarity float DEFAULT 0,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY recording_id (recording_id),
                KEY question_id (question_id),
                KEY idx_is_correct (is_correct),
                CONSTRAINT fk_response_recording
                    FOREIGN KEY (recording_id)
                    REFERENCES {$wpdb->prefix}lus_recordings (id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_response_question
                    FOREIGN KEY (question_id)
                    REFERENCES {$wpdb->prefix}lus_questions (id)
                    ON DELETE CASCADE
            ) {$charset_collate}",

            // Assessments
            'lus_assessments' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lus_assessments (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                recording_id bigint(20) UNSIGNED NOT NULL,
                total_score float DEFAULT 0,
                normalized_score float DEFAULT 0,
                assessed_by bigint(20) UNSIGNED NOT NULL,
                completed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY recording_id (recording_id),
                KEY assessed_by (assessed_by),
                KEY idx_completed_at (completed_at),
                CONSTRAINT fk_assessment_recording
                    FOREIGN KEY (recording_id)
                    REFERENCES {$wpdb->prefix}lus_recordings (id)
                    ON DELETE CASCADE
            ) {$charset_collate}",

            // Assignments
            $sql_assignments = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lus_assignments (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id bigint(20) UNSIGNED NOT NULL,
                passage_id bigint(20) UNSIGNED NOT NULL,
                assigned_by bigint(20) UNSIGNED NOT NULL,
                assigned_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                due_date datetime DEFAULT NULL,
                status varchar(20) DEFAULT 'pending',
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY passage_id (passage_id),
                CONSTRAINT fk_lus_assignment_user FOREIGN KEY (user_id)
                    REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE,
                CONSTRAINT fk_lus_assignment_passage FOREIGN KEY (passage_id)
                    REFERENCES {$wpdb->prefix}lus_passages (id) ON DELETE CASCADE
            ) {$charset_collate}",

            // Admin Activity Tracking
            'lus_admin_interactions' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lus_admin_interactions (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id bigint(20) UNSIGNED NOT NULL,
                page varchar(50) NOT NULL,
                action varchar(50) NOT NULL,
                clicks int DEFAULT 0,
                active_time int DEFAULT 0,
                idle_time int DEFAULT 0,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY idx_page_action (page, action),
                KEY idx_created_at (created_at),
                CONSTRAINT fk_interaction_user
                    FOREIGN KEY (user_id)
                    REFERENCES {$wpdb->users} (ID)
                    ON DELETE CASCADE
            ) {$charset_collate}",

            'lus_admin_interactions' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}lus_admin_interactions (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id bigint(20) UNSIGNED NOT NULL,
                page varchar(50) NOT NULL,
                action varchar(50) NOT NULL,
                clicks int DEFAULT 0,
                active_time int DEFAULT 0,
                idle_time int DEFAULT 0,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY idx_page_action (page, action),
                KEY idx_created_at (created_at),
                CONSTRAINT fk_lus_interaction_user
                    FOREIGN KEY (user_id)
                    REFERENCES {$wpdb->users} (ID)
                    ON DELETE CASCADE
            ) {$charset_collate}"
        ];
    }

    /**
     * Create all required database tables
     */
    private static function create_database_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $tables = self::get_table_definitions();

        foreach ($tables as $table_name => $sql) {
            $result = dbDelta($sql);
            if (!empty($wpdb->last_error)) {
                error_log("LUS: Error creating table {$table_name}: {$wpdb->last_error}");
            }
        }
    }

    /**
     * Add new columns and upgrade database schema
     */
    private static function upgrade_database_schema() {
        global $wpdb;
        $current_db_version = get_option('lus_db_version', '1.0');

        // Upgrade to 1.1 - Add soft delete to passages
        if (version_compare($current_db_version, '1.1', '<')) {
            $table = $wpdb->prefix . 'lus_passages';
            $column = 'deleted_at';

            if (!self::column_exists($table, $column)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} datetime DEFAULT NULL");
                if ($wpdb->last_error) {
                    error_log("LUS: Failed to add {$column} column: " . $wpdb->last_error);
                    return false;
                }
            }
            update_option('lus_db_version', '1.1');
        }

        // Future upgrades can be added here
    }

    /**
     * Set initial plugin options
     */
    private static function set_plugin_options() {
        $options = array(
            'lus_version' => LUS_Constants::PLUGIN_VERSION,
            'lus_db_version' => '1.0',
            'lus_installed_at' => current_time('mysql'),
            'lus_enable_tracking' => true,
            'lus_preserve_data' => true,
            'lus_user_role_created' => false
        );

        foreach ($options as $key => $value) {
            update_option($key, $value, 'no');
        }
    }

    /**
     * Helper function to check if a column exists in a table
     */
    private static function column_exists($table, $column) {
        global $wpdb;
        $column_check = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        return !empty($column_check);
    }

    /**
     * Schedule tasks
     */
    private static function schedule_tasks() {
        if (!wp_next_scheduled('lus_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'lus_daily_cleanup');
        }

        if (!wp_next_scheduled('lus_weekly_stats')) {
            wp_schedule_event(time(), 'weekly', 'lus_weekly_stats');
        }
    }
}