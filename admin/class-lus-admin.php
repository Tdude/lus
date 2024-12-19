<?php
/**
 * class-lus-admin.php
*/
class LUS_Admin {
    private $plugin_name;
    private $version;
    private $db;
    private $assessment_handler;  // Add this property

    public function __construct($plugin_name, $version, $db) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->db = $db;
        $this->assessment_handler = new LUS_Assessment_Handler($db);  // Initialize here
    }

    // ... other admin methods like enqueue_scripts, add_menu_pages, etc ...

    /**
     * AJAX Handlers Section
     */

    // Existing AJAX handlers
    public function ajax_get_passage() {
        // ... existing code ...
    }

    public function ajax_get_passages() {
        // ... existing code ...
    }

    // Add the new assessment handler here
    public function ajax_save_assessment() {
        if (!check_ajax_referer('lus_admin_action', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'lus')]);
        }

        $recording_id = intval($_POST['recording_id']);

        // Get configured evaluator types
        $evaluation_types = get_option('lus_enabled_evaluators', ['manual']);

        $result = $this->assessment_handler->process_assessment(
            $recording_id,
            $evaluation_types
        );

        if ($result['success']) {
            wp_send_json_success([
                'message' => __('Assessment saved', 'lus'),
                'assessment_id' => $result['assessment_id'],
                'results' => $result['results']
            ]);
        } else {
            wp_send_json_error(['message' => $result['error']]);
        }
    }

    public function ajax_delete_recording() {
        error_log('=== Start delete_recording AJAX handler ===');

        // Clear all previous output and buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            error_log('Not an AJAX request');
            exit('Not an AJAX request');
        }

        if (!check_ajax_referer('lus_admin_action', 'nonce', false)) {
            error_log('Nonce verification failed');
            wp_send_json_error(['message' => __('Security check failed', 'lus')]);
            exit;
        }

        if (!current_user_can('manage_options')) {
            error_log('Permission check failed');
            wp_send_json_error(['message' => __('Permission denied', 'lus')]);
            exit;
        }

        $recording_id = isset($_POST['recording_id']) ? intval($_POST['recording_id']) : 0;
        if (!$recording_id) {
            error_log('No recording ID provided');
            wp_send_json_error(['message' => __('Recording ID missing', 'lus')]);
            exit;
        }

        error_log('Processing recording ID: ' . $recording_id);

        try {
            $recording = $this->db->get_recording($recording_id);

            if (!$recording) {
                error_log('Recording not found');
                wp_send_json_error(['message' => __('Recording not found', 'lus')]);
                exit;
            }

            // Try to delete the file
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . $recording->audio_file_path;
            error_log('Attempting to delete file: ' . $file_path);

            if (file_exists($file_path)) {
                if (!@unlink($file_path)) {
                    error_log('Failed to delete file: ' . $file_path);
                } else {
                    error_log('File deleted successfully');
                }
            } else {
                error_log('File does not exist: ' . $file_path);
            }

            // Delete database record
            $result = $this->db->delete_recording($recording_id);

            if ($result) {
                error_log('Recording deleted successfully');
                wp_cache_delete('lus_recordings_count');
                wp_cache_delete('lus_recordings_page_1');
                wp_send_json_success(['message' => __('Inspelningen har raderats', 'lus')]);
            } else {
                error_log('Failed to delete recording from database');
                wp_send_json_error(['message' => __('Kunde inte radera inspelningen', 'lus')]);
            }
        } catch (Exception $e) {
            error_log('Exception in delete_recording: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }

        error_log('=== End delete_recording AJAX handler ===');
        exit;
    }
    // ... other admin methods ...
}