<?php
/**
 * Feature Flag Manager
 *
 * @package    LUS
 * @subpackage LUS/includes
 */

class LUS_Feature_Flags {
    /** @var array */
    private static $flags = [];

    /** @var array */
    private static $default_flags = [
        'use_new_architecture' => false,
        'use_new_recording_handler' => false,
        'use_new_assessment_handler' => false,
        'use_new_results_handler' => false,
        'enable_ai_evaluation' => false,
        'debug_mode' => false
    ];

    /**
     * Initialize feature flags
     */
    public static function init(): void {
        self::$flags = get_option('lus_feature_flags', self::$default_flags);

        // Add admin menu for flag management if user is admin
        if (is_admin()) {
            add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
            add_action('admin_init', [__CLASS__, 'register_settings']);
        }
    }

    /**
     * Check if a feature is enabled
     *
     * @param string $flag_name Flag identifier
     * @return bool Whether feature is enabled
     */
    public static function is_enabled(string $flag_name): bool {
        return isset(self::$flags[$flag_name]) && self::$flags[$flag_name];
    }

    /**
     * Add admin menu for feature flags
     */
    public static function add_admin_menu(): void {
        add_submenu_page(
            'lus',
            __('Feature Flags', 'lus'),
            __('Feature Flags', 'lus'),
            'manage_options',
            'lus-features',
            [__CLASS__, 'render_admin_page']
        );
    }

    /**
     * Register settings
     */
    public static function register_settings(): void {
        register_setting('lus_features', 'lus_feature_flags');

        add_settings_section(
            'lus_feature_flags_section',
            __('LUS Feature Flags', 'lus'),
            [__CLASS__, 'render_section_intro'],
            'lus_features'
        );

        foreach (self::$default_flags as $flag => $default) {
            add_settings_field(
                'lus_feature_' . $flag,
                self::get_flag_label($flag),
                [__CLASS__, 'render_flag_field'],
                'lus_features',
                'lus_feature_flags_section',
                ['flag' => $flag]
            );
        }
    }

    /**
     * Get human-readable label for flag
     *
     * @param string $flag Flag identifier
     * @return string Human-readable label
     */
    private static function get_flag_label(string $flag): string {
        $labels = [
            'use_new_architecture' => __('Use New Architecture', 'lus'),
            'use_new_recording_handler' => __('Use New Recording Handler', 'lus'),
            'use_new_assessment_handler' => __('Use New Assessment Handler', 'lus'),
            'use_new_results_handler' => __('Use New Results Handler', 'lus'),
            'enable_ai_evaluation' => __('Enable AI Evaluation', 'lus'),
            'debug_mode' => __('Debug Mode', 'lus')
        ];

        return $labels[$flag] ?? $flag;
    }

    /**
     * Get flag description
     *
     * @param string $flag Flag identifier
     * @return string Description
     */
    private static function get_flag_description(string $flag): string {
        $descriptions = [
            'use_new_architecture' => __('Enable the new plugin architecture (use with caution)', 'lus'),
            'use_new_recording_handler' => __('Use new recording management system', 'lus'),
            'use_new_assessment_handler' => __('Use new assessment processing system', 'lus'),
            'use_new_results_handler' => __('Use new results display system', 'lus'),
            'enable_ai_evaluation' => __('Enable AI-based evaluation features', 'lus'),
            'debug_mode' => __('Enable detailed debugging information', 'lus')
        ];

        return $descriptions[$flag] ?? '';
    }

    /**
     * Render section introduction
     */
    public static function render_section_intro(): void {
        echo '<p>' . __('Control which features are enabled in the plugin. Use with caution in production.', 'lus') . '</p>';
        echo '<p class="description">' . __('These settings affect how the plugin operates. Changes may require cache clearing.', 'lus') . '</p>';
    }

    /**
     * Render individual flag field
     *
     * @param array $args Field arguments
     */
    public static function render_flag_field(array $args): void {
        $flag = $args['flag'];
        $current = self::$flags[$flag] ?? self::$default_flags[$flag];

        echo '<label>';
        echo '<input type="checkbox" name="lus_feature_flags[' . esc_attr($flag) . ']" value="1" ' .
             checked(1, $current, false) . '>';
        echo ' ' . esc_html(self::get_flag_description($flag));
        echo '</label>';
    }

    /**
     * Render admin page
     */
    public static function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (isset($_GET['settings-updated'])) : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Feature flags updated.', 'lus'); ?></p>
    </div>
    <?php endif; ?>

    <form action="options.php" method="post">
        <?php
                settings_fields('lus_features');
                do_settings_sections('lus_features');
                submit_button(__('Save Features', 'lus'));
                ?>
    </form>
</div>
<?php
    }
}


// Initialize feature flags
require_once PLUGIN_DIR . 'includes/class-lus-feature-flags.php';
LUS_Feature_Flags::init();

// Helper function to check features
function lus_is_feature_enabled(string $flag): bool {
    return LUS_Feature_Flags::is_enabled($flag);
}

// Example usage in plugin code:
function run_lus() {
    try {
        // Use new or old architecture based on feature flag
        if (lus_is_feature_enabled('use_new_architecture')) {
            // Initialize new architecture
            $container = new LUS_Container();
            $plugin = $container->get('core');
        } else {
            // Use legacy initialization
            $plugin = new LUS();
        }

        $plugin->run();

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