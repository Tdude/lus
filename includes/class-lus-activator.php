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
        // Create upload directory if it doesn't exist
        if (!file_exists( LUS_Constants::UPLOAD_DIR )) {
            if (!wp_mkdir_p( LUS_Constants::UPLOAD_DIR )) {
                wp_die(__('Kunde inte skapa uppladdningskatalogen.', 'lus'));
            }
        }
        // Set directory permissions
        chmod(LUS_Constants::UPLOAD_DIR, 0755);

        self::check_requirements();
        self::create_database_tables();
        self::upgrade_database_schema();
        self::create_directories();
        self::set_plugin_options();
        self::migrate_data_if_needed();
        self::schedule_tasks();
    }

    /**
     * Verify requirements
     */
    private static function check_requirements() {
        global $wpdb;

        // Check MySQL version
        $mysql_version = $wpdb->db_version();
        if (version_compare($mysql_version, '5.6', '<')) {
            wp_die(__('LUS kräver MySQL 5.6 eller högre.', 'lus'));
        }

        // Check write permissions
        $upload_dir = wp_upload_dir();
        if (!wp_is_writable( LUS_Constants::UPLOAD_DIR )) {
            wp_die(__('LUS kräver skrivrättigheter i uppladdningskatalogen.', 'lus'));
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
     * Create required directories for audio uploads
     */
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        $dirs = array(
            LUS_Constants::UPLOAD_DIR,
            LUS_Constants::UPLOAD_DIR . date('Y'),
            LUS_Constants::UPLOAD_DIR . date('Y') . '/' . date('m')
        );

        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                if (!wp_mkdir_p($dir)) {
                    error_log("LUS: Failed to create directory: {$dir}");
                } else {
                    // Create .htaccess to prevent directory listing
                    $htaccess = $dir . '/.htaccess';
                    if (!file_exists($htaccess)) {
                        file_put_contents($htaccess, "Options -Indexes\n");
                    }
                }
            }
        }
    }

    /**
     * Set initial plugin options
     */
    private static function set_plugin_options() {
        $options = array(
            'lus_version' => VERSION,
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
     * Migrate data from old ra_ tables if they exist
     */
    private static function migrate_data_if_needed() {
        global $wpdb;

        // Check if old tables exist and migration is needed
        $old_tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}ra_%'");
        if (empty($old_tables) || get_option('lus_data_migrated')) {
            return;
        }

        // Start transaction for data migration
        $wpdb->query('START TRANSACTION');

        try {
            $table_mappings = [
                'ra_passages' => 'lus_passages',
                'ra_recordings' => 'lus_recordings',
                'ra_questions' => 'lus_questions',
                'ra_responses' => 'lus_responses',
                'ra_assessments' => 'lus_assessments',
                'ra_assignments' => 'lus_assignments',
                'ra_admin_interactions' => 'lus_admin_interactions'
            ];

            foreach ($table_mappings as $old_table => $new_table) {
                $old_table = $wpdb->prefix . $old_table;
                $new_table = $wpdb->prefix . $new_table;

                // Check if old table exists
                $exists = $wpdb->get_var("SHOW TABLES LIKE '$old_table'");
                if ($exists) {
                    // Copy data
                    $wpdb->query("INSERT INTO $new_table SELECT * FROM $old_table");
                    if ($wpdb->last_error) {
                        throw new Exception("Failed to migrate data from $old_table: " . $wpdb->last_error);
                    }
                }
            }

            // Mark migration as complete
            update_option('lus_data_migrated', true);
            $wpdb->query('COMMIT');

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('LUS: Data migration failed: ' . $e->getMessage());
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