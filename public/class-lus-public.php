<?php
/**
 * File: public/class-lus-public.php
 * Handles public-facing functionality
 *
 * @package    LUS
 * @subpackage LUS/public
 */

 class LUS_Public {
    private $plugin_name;
    private $version;
    private $db;
    private $assessment_handler;

    /**
     * Initialize the class
     */
    public function __construct(LUS_Database $db) {
        $this->plugin_name = LUS_Constants::PLUGIN_NAME;
        $this->version = LUS_Constants::PLUGIN_VERSION;
        $this->db = $db;
        $this->assessment_handler = new LUS_Assessment_Handler($db);
    }

    /**
     * Register public styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            LUS_Constants::PLUGIN_NAME,
            LUS_Constants::PLUGIN_URL . 'public/css/lus-public.css',
            [],
            LUS_Constants::PLUGIN_VERSION,
            'all'
        );
    }

    /**
     * Register public scripts
     */
    public function enqueue_scripts() {
        // Main public script
        wp_enqueue_script(
            LUS_Constants::PLUGIN_NAME,
            LUS_Constants::PLUGIN_URL . 'public/js/lus-public.js',
            ['jquery'],
            LUS_Constants::PLUGIN_VERSION,
            true
        );

        // Recorder script - only load when needed
        if (has_shortcode(get_post()->post_content, 'lus_recorder')) {
            wp_enqueue_script(
                LUS_Constants::PLUGIN_NAME . '-recorder',
                LUS_Constants::PLUGIN_URL . 'public/js/lus-recorder.js',
                [LUS_Constants::PLUGIN_NAME],
                LUS_Constants::PLUGIN_VERSION,
                true
            );
        }

        // Localize script
        wp_localize_script(
            LUS_Constants::PLUGIN_NAME,
            'lusPublic',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => LUS_Constants::NONCE_PUBLIC,
                'strings' => $this->get_strings(),
                'user_id' => get_current_user_id(),
                'is_logged_in' => is_user_logged_in()
            ]
        );
    }

    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('lus_recorder', [$this, 'render_recorder_shortcode']);
        add_shortcode('lus_results', [$this, 'render_results_shortcode']);
    }

    /**
     * Render recorder shortcode
     */
    public function render_recorder_shortcode($atts) {
        if (!is_user_logged_in()) {
            return $this->show_login_message();
        }

        $atts = shortcode_atts([
            'passage_id' => 0,
            'show_questions' => true
        ], $atts);

        ob_start();
        include LUS_Constants::PLUGIN_DIR . 'partials/recorder.php';
        error_log('RECORDER PATH in class-lus-public.php: ' . LUS_Constants::PLUGIN_DIR . 'partials/recorder.php');
        return ob_get_clean();
    }

    /**
     * Render results shortcode
     */
    public function render_results_shortcode($atts) {
        if (!is_user_logged_in()) {
            return $this->show_login_message();
        }

        $atts = shortcode_atts([
            'user_id' => get_current_user_id(),
            'show_charts' => true
        ], $atts);

        ob_start();
        include LUS_Constants::PLUGIN_DIR . 'partials/results.php';
        return ob_get_clean();
    }

    /**
     * AJAX handler for saving recordings
     */
    public function ajax_save_recording() {
        try {
            $this->verify_ajax_request();

            if (!isset($_FILES['audio_file'])) {
                throw new Exception(__('Ingen ljudfil hittades', 'lus'));
            }

            $file_data = $_FILES['audio_file'];
            $passage_id = isset($_POST['passage_id']) ? intval($_POST['passage_id']) : 0;
            $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;

            $recorder = new LUS_Recorder();
            $result = $recorder->save_recording($file_data, get_current_user_id(), $passage_id, $duration);

            wp_send_json_success($result);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX handler for submitting answers
     */
    public function ajax_submit_answers() {
        try {
            $this->verify_ajax_request();

            $recording_id = isset($_POST['recording_id']) ? intval($_POST['recording_id']) : 0;
            $answers = isset($_POST['answers']) ? json_decode(stripslashes($_POST['answers']), true) : [];

            if (!$recording_id || empty($answers)) {
                throw new Exception(__('Ogiltig data', 'lus'));
            }

            $result = $this->assessment_handler->process_assessment($recording_id);
            wp_send_json_success($result);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Handle login redirection for subscribers
     */
    public function subscriber_login_redirect($redirect_to, $requested_redirect_to, $user) {
        if (!isset($user->roles) || !is_array($user->roles)) {
            return $redirect_to;
        }

        // Redirect subscribers to recorder page
        if (in_array('subscriber', $user->roles)) {
            $recorder_page = get_option('lus_recorder_page');
            if ($recorder_page) {
                return get_permalink($recorder_page);
            }
        }

        return $redirect_to;
    }

    /**
     * Show "You are logged in" message for previously non-logged-in users
     */
    public function show_login_message() {
        ob_start();
        include LUS_Constants::PLUGIN_DIR . 'includes/lus-login-message.php';
        return ob_get_clean();
    }

    /**
     * Verify AJAX request
     */
    private function verify_ajax_request() {
        if (!check_ajax_referer('lus_public_action', 'nonce', false)) {
            throw new Exception(__('Säkerhetskontroll misslyckades', 'lus'));
        }

        if (!is_user_logged_in()) {
            throw new Exception(__('Du måste vara inloggad', 'lus'));
        }
    }

    /**
     * Get localized strings
     */
    private function get_strings() {
        return [
            'recording' => __('Spelar in...', 'lus'),
            'recorded' => __('Inspelning klar', 'lus'),
            'playback' => __('Spela upp', 'lus'),
            'stop' => __('Stoppa', 'lus'),
            'save' => __('Spara', 'lus'),
            'cancel' => __('Avbryt', 'lus'),
            'error' => __('Ett fel uppstod', 'lus'),
            'confirm_cancel' => __('Är du säker på att du vill avbryta? Inspelningen kommer att försvinna.', 'lus')
        ];
    }
}