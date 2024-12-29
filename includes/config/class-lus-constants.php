<?php
/**
 * File: class-lus-constants.php
 * Constants class for the LUS plugin
 *
 * @package    LUS
 * @subpackage LUS/includes/config
 */

if (!defined('ABSPATH')) {
    exit;
}


class LUS_Constants {
    public static function init() {
        if (!defined('LUS_VERSION')) {
            define('LUS_VERSION', '0.0.5');
            define('LUS_PLUGIN_NAME', plugin_basename(LUS_PLUGIN_DIR . 'lus.php'));
            define('LUS_PLUGIN_URL', plugin_dir_url(LUS_PLUGIN_DIR . 'lus.php'));
        }
    }

    public static function init_upload_paths() {
        if (!defined('LUS_UPLOAD_DIR')) {
            $upload_dir = wp_upload_dir();
            define('LUS_UPLOAD_DIR', $upload_dir['basedir'] . '/lus');
            define('LUS_UPLOAD_URL', $upload_dir['baseurl'] . '/lus');
        }
    }

    // Core constants that don't depend on WordPress
    const MIN_WP_VERSION = '5.8';
    const MIN_PHP_VERSION = '7.4';
    const TEXT_DOMAIN = 'lus';

    const PLUGIN_VERSION = LUS_VERSION;
    const PLUGIN_NAME = LUS_PLUGIN_NAME;
    const PLUGIN_DIR = LUS_PLUGIN_DIR;
    const PLUGIN_URL = LUS_PLUGIN_URL;
    const UPLOAD_DIR = LUS_UPLOAD_DIR;
    const UPLOAD_URL = LUS_UPLOAD_URL;

    /**
     * Database table names (without prefix)
     */
    const TABLE_PASSAGES = 'lus_passages';
    const TABLE_RECORDINGS = 'lus_recordings';
    const TABLE_QUESTIONS = 'lus_questions';
    const TABLE_RESPONSES = 'lus_responses';
    const TABLE_ASSESSMENTS = 'lus_assessments';
    const TABLE_ASSIGNMENTS = 'lus_assignments';
    const TABLE_ADMIN_INTERACTIONS = 'lus_admin_interactions';

    /**
     * Status codes
     */
    const STATUS_PENDING = 'pending';
    const STATUS_ASSESSED = 'assessed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_OVERDUE = 'overdue';

    /**
     * Default values
     */
    const DEFAULT_PER_PAGE = 20;
    const DEFAULT_TIME_LIMIT = 180;
    const DEFAULT_DIFFICULTY_LEVEL = 1;
    const DEFAULT_WEIGHT = 1.0;

    /**
     * File handling
     */
    const ALLOWED_AUDIO_TYPES = ['audio/webm', 'audio/wav', 'audio/mp3', 'audio/mpeg'];
    const MAX_UPLOAD_SIZE = 10485760; // 10MB

    /**
     * Cache settings
     */
    const CACHE_EXPIRATION = 300; // 5 minutes
    const CACHE_GROUP = 'lus_cache';

    /**
     * Assessment settings
     */
    const MIN_CONFIDENCE_THRESHOLD = 0.8; // Minimum similarity score for correct answer
    const MAX_DIFFICULTY_LEVEL = 20;
    const TEXT_RESPONSE_WEIGHT = 0.5;
    const AUDIO_EVALUATION_WEIGHT = 0.5;

    /**
     * UI settings
     */
    const NOTIFICATION_TIMEOUT = 5000; // 5 seconds
    const MODAL_ANIMATION_DURATION = 300; // 300ms

    /**
     * Option names
     */
    const OPTION_DB_VERSION = 'lus_db_version';
    const OPTION_INSTALLED_AT = 'lus_installed_at';
    const OPTION_ENABLE_TRACKING = 'lus_enable_tracking';
    const OPTION_PRESERVE_DATA = 'lus_preserve_data';
    const OPTION_DATA_MIGRATED = 'lus_data_migrated';

    /**
     * AJAX actions
     */
    const AJAX_SAVE_PASSAGE = 'lus_admin_passage_save';
    const AJAX_DELETE_PASSAGE = 'lus_admin_passage_delete';
    const AJAX_SAVE_RECORDING = 'lus_admin_recording_save';
    const AJAX_DELETE_RECORDING = 'lus_admin_recording_delete';

    /**
     * Nonce actions
     */
    const NONCE_ADMIN = 'lus_admin_action';
    const NONCE_PUBLIC = 'lus_public_action';

    /**
     * Roles and capabilities
     */
    const ROLE_ADMIN = 'administrator';
    const ROLE_USER = 'subscriber';
    const CAP_MANAGE_OPTIONS = 'manage_options';

    /**
     * Error messages
     */
    const ERROR_PERMISSION_DENIED = 'Permission denied';
    const ERROR_INVALID_NONCE = 'Security check failed';
    const ERROR_MISSING_DATA = 'Required data is missing';
    const ERROR_INVALID_FILE = 'Invalid file type';
    const ERROR_FILE_TOO_LARGE = 'File is too large';

    /**
     * Success messages
     */
    const SUCCESS_SAVED = 'Successfully saved';
    const SUCCESS_DELETED = 'Successfully deleted';
    const SUCCESS_UPDATED = 'Successfully updated';

    /**
     * Event names
     */
    const EVENT_PASSAGE_SAVED = 'passage_saved';
    const EVENT_RECORDING_SAVED = 'recording_saved';
    const EVENT_ASSESSMENT_COMPLETED = 'assessment_completed';
}

// Initialize core constants immediately
LUS_Constants::init();

// Initialize upload paths after WordPress is loaded
add_action('plugins_loaded', ['LUS_Constants', 'init_upload_paths'], 5);