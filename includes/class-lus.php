<?php
/** class-lus.php
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks and public-facing site hooks.
 * The proper initialization order:
 * constants -> core classes -> singleton instances -> hooks
 *
 * @package    LUS
 * @subpackage LUS/includes
 */

class LUS {
    private static $instance = null;
    private static $hook_registered = false;
    private static $admin_instance = null;
    private static $public_instance = null;
    private $db;
    private $plugin_name;
    private $version;
    private $loader;
    private static $initialized = false;

    /**
     * Get singleton instance
     */
    public static function get_instance(LUS_Database $db = null) {
        if (null === self::$instance) {
            if (null === $db) {
                throw new Exception('Database instance required for first initialization');
            }
            lus_debug_log('Creating new LUS instance');
            self::$instance = new self($db);
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct(LUS_Database $db) {
        if (!$db instanceof LUS_Database) {
            throw new InvalidArgumentException('Invalid database instance');
        }
        $this->db = $db;
        $this->plugin_name = LUS_Constants::PLUGIN_NAME;
        $this->version = LUS_Constants::PLUGIN_VERSION;
        $this->loader = new LUS_Loader();

        lus_debug_log('LUS constructor called');
        $this->load_dependencies();
        $this->set_locale();
    }

    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        // Use base constants for core files
        require_once LUS_Constants::PLUGIN_DIR . 'includes/config/strings-lus-admin.php';

        // Core
        require_once LUS_Constants::PLUGIN_DIR . 'includes/class-lus-loader.php';
        require_once LUS_Constants::PLUGIN_DIR . 'includes/class-lus-i18n.php';
        require_once LUS_Constants::PLUGIN_DIR . 'includes/class-lus-database.php';
        require_once LUS_Constants::PLUGIN_DIR . 'includes/class-lus-container.php';
        require_once LUS_Constants::PLUGIN_DIR . 'includes/class-lus-events.php';

        // Feature modules
        require_once LUS_Constants::PLUGIN_DIR . 'includes/class-lus-recorder.php';
        require_once LUS_Constants::PLUGIN_DIR . 'includes/class-lus-statistics.php';
        require_once LUS_Constants::PLUGIN_DIR . 'includes/class-lus-assessment-handler.php';
        require_once LUS_Constants::PLUGIN_DIR . 'includes/class-lus-export-handler.php';

        // Strategies and interfaces
        require_once LUS_Constants::PLUGIN_DIR . 'includes/strategy/interface-lus-evaluation-strategy.php';
        require_once LUS_Constants::PLUGIN_DIR . 'includes/class-lus-evaluator.php';
        require_once LUS_Constants::PLUGIN_DIR . 'includes/strategy/class-lus-levenshtein-strategy.php';

        // Value objects
        require_once LUS_Constants::PLUGIN_DIR . 'includes/value-objects/class-lus-duration.php';
        require_once LUS_Constants::PLUGIN_DIR . 'includes/value-objects/class-lus-score.php';
        require_once LUS_Constants::PLUGIN_DIR . 'includes/value-objects/class-lus-status.php';
        require_once LUS_Constants::PLUGIN_DIR . 'includes/value-objects/class-lus-difficulty-level.php';

        // DTOs
        require_once LUS_Constants::PLUGIN_DIR . 'includes/dto/class-lus-passage-dto.php';
        require_once LUS_Constants::PLUGIN_DIR . 'includes/dto/class-lus-recording-dto.php';

        // Admin and public interfaces
        require_once LUS_Constants::PLUGIN_DIR . 'admin/class-lus-admin.php';
        require_once LUS_Constants::PLUGIN_DIR . 'public/class-lus-public.php';

        // Initialize main components
        $this->loader = new LUS_Loader();
    }

    /**
     * Define the locale for internationalization, i18n.
     */
    private function set_locale() {
        // Debug loader initialization
        if (!$this->loader) {
            error_log('$this->loader is not initialized!');
            return;
        }
        $plugin_i18n = new LUS_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register admin hooks
     */
    private function define_admin_hooks() {
        if (self::$admin_instance) {
            lus_debug_log('Admin hooks already registered');
            return;
        }

        self::$admin_instance = LUS_Admin::get_instance($this->db);
        self::$admin_instance->init_hooks(); // Initialize hooks only once

        // Admin scripts and styles - remove duplicate registrations
        $this->loader->add_action('admin_enqueue_scripts', self::$admin_instance, 'enqueue_styles', 10, 1);
        $this->loader->add_action('admin_enqueue_scripts', self::$admin_instance, 'enqueue_scripts', 10, 1);
    }

    /**
     * Register public-facing hooks
     */
    private function define_public_hooks() {
        if (self::$public_instance) {
            lus_debug_log('Public hooks already registered');
            return;
        }

        self::$public_instance = new LUS_Public($this->db);

        // Public scripts and styles
        $this->loader->add_action('wp_enqueue_scripts', self::$public_instance, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', self::$public_instance, 'enqueue_scripts');

        // Shortcodes and content handling
        $this->loader->add_action('init', self::$public_instance, 'register_shortcodes');

        // User handling
        $this->loader->add_filter('login_redirect', self::$public_instance, 'subscriber_login_redirect', 10, 3);
        $this->loader->add_action('wp_footer', self::$public_instance, 'show_login_message');
    }

    /**
     * Register all AJAX handlers.
     */
    private function define_ajax_hooks() {
        static $ajax_hooks_registered = false;
        if ($ajax_hooks_registered) {
            lus_debug_log('AJAX hooks already registered');
            return;
        }
        $ajax_hooks_registered = true;

        // Admin AJAX handlers
        $admin_ajax_actions = [
            'get_passage',
            'get_passages',
            'delete_passage',
            'get_questions',
            'delete_question',
            'get_results',
            'delete_assignment',
            'save_assessment',
            'delete_recording',
            'save_interactions'
        ];

        // Direct instantiation for admin
        $plugin_admin = new LUS_Admin($this->db);

        foreach ($admin_ajax_actions as $action) {
            $this->loader->add_action(
                'wp_ajax_lus_admin_' . $action,
                $plugin_admin,
                'ajax_' . $action
            );
        }

        // Public AJAX handlers
        $public_ajax_actions = [
            'get_questions',
            'save_recording',
            'submit_answers',
            'get_assessment'
        ];

        $plugin_public = new LUS_Public($this->db);

        foreach ($public_ajax_actions as $action) {
            // Logged in users
            $this->loader->add_action(
                'wp_ajax_lus_' . $action,
                $plugin_public,
                'ajax_' . $action
            );

            // Non-logged in users (if needed)
            if (in_array($action, ['get_questions'])) {
                $this->loader->add_action(
                    'wp_ajax_nopriv_lus_' . $action,
                    $plugin_public,
                    'ajax_' . $action
                );
            }
        }
    }

    /**
     * Run the loader to execute all the hooks with WordPress.
     */
    public function run() {
        if (self::$hook_registered) {
            lus_debug_log('Preventing duplicate hook registration');
            return;
        }
        self::$hook_registered = true;

        lus_debug_log('Registering hooks');
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_ajax_hooks();
        $this->loader->run();
    }

    /**
     * Get the loader that's responsible for maintaining and registering all hooks.
     */
    public function get_loader() {
        return $this->loader;
    }
}