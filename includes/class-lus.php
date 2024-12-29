<?php
/** class-lus.php
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks,
 * and public-facing site hooks.
 *
 * @package    LUS
 * @subpackage LUS/includes
 */

class LUS {
    /**
     * Maintains and registers all hooks for the plugin.
     * @var LUS_Loader
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     * @var string
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     * @var string
     */
    protected $version;

    /**
     * Database handler instance.
     * @var LUS_Database
     */
    protected $db;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct(LUS_Database $db) {
        // Ensure dependencies are loaded first
        $this->load_dependencies();

        // Initialize properties
        $this->db = $db;
        $this->plugin_name = LUS_Constants::PLUGIN_NAME;
        $this->version = LUS_Constants::PLUGIN_VERSION;

        // Initialize plugin hooks and other components
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_ajax_hooks();
    }


    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        // Constants and config
        require_once LUS_Constants::PLUGIN_DIR . 'includes/config/class-lus-constants.php';
        require_once LUS_Constants::PLUGIN_DIR . 'includes/config/admin-strings.php';

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
     * Define the locale for internationalization.
     */
    private function set_locale() {
        $plugin_i18n = new LUS_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register admin hooks
     */
    private function define_admin_hooks() {
        // Ensure $this->db is an instance of LUS_Database before passing it.
        if (!($this->db instanceof LUS_Database)) {
            throw new InvalidArgumentException('Expected $db to be an instance of LUS_Database.');
        }
        $plugin_admin = new LUS_Admin($this->db);

        // Admin scripts and styles
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

        // Admin menu and settings
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_menu_pages');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');


        // AJAX handlers
        $ajax_actions = [
            'get_passage',
            'get_passages',
            'delete_passage',
            'get_questions',
            'delete_question',
            'get_results',
            'delete_assignment',
            'save_assessment',
            'delete_recording',
            'save_interactions',
            'bulk_assign_recordings'
        ];

        foreach ($ajax_actions as $action) {
            $this->loader->add_action(
                'wp_ajax_lus_admin_' . $action,
                $plugin_admin,
                'ajax_' . $action
            );
        }
    }

    /**
     * Register public-facing hooks
     */
    private function define_public_hooks() {
        $plugin_public = new LUS_Public($this->db);

        // Public scripts and styles
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');

        // Shortcodes and content handling
        $this->loader->add_action('init', $plugin_public, 'register_shortcodes');

        // Public AJAX handlers
        $public_ajax_actions = [
            'get_questions',
            'save_recording',
            'submit_answers',
            'get_assessment'
        ];

        foreach ($public_ajax_actions as $action) {
            // For logged-in users
            $this->loader->add_action(
                'wp_ajax_lus_' . $action,
                $plugin_public,
                'ajax_' . $action
            );

            // For non-logged-in users (if needed)
            if (in_array($action, ['get_questions'])) {
                $this->loader->add_action(
                    'wp_ajax_nopriv_lus_' . $action,
                    $plugin_public,
                    'ajax_' . $action
                );
            }
        }

        // User handling
        $this->loader->add_filter('login_redirect', $plugin_public, 'subscriber_login_redirect', 10, 3);
        $this->loader->add_action('wp_footer', $plugin_public, 'show_login_message');
    }

    /**
     * Register all AJAX handlers.
     */
    private function define_ajax_hooks() {
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
        $this->loader->run();
    }
}