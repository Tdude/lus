<?php
/**
 * File: class-lus-admin.php
 * The admin-specific functionality of the plugin.
 * @package    LUS
 * @subpackage LUS/admin
 */

 class LUS_Admin {
    private static $instance = null;
    private static $hooks_registered = false;
    private $plugin_name;
    private $version;
    private $db;
    private $strings = [];
    private $page_hooks = [];
    private const SCRIPT_DEPS = [
        'toplevel_page_lus' => ['core' => ['jquery'], 'ui' => ['core'], 'passages' => ['core', 'ui']],
        'lus-installningar_page_lus-passages' => ['core' => ['jquery'], 'ui' => ['core'], 'passages' => ['core', 'ui']],
        'lus-installningar_page_lus-questions' => ['core' => ['jquery'], 'ui' => ['core'], 'questions' => ['core', 'ui']],
        'lus-installningar_page_lus-recordings' => ['core' => ['jquery'], 'ui' => ['core'], 'recordings' => ['core', 'ui']],
        'lus-installningar_page_lus-results' => ['core' => ['jquery'], 'ui' => ['core'], 'results' => ['core', 'ui'], 'chart' => ['core', 'ui', 'chartjs']],
    ];
    private const EXTERNAL_SCRIPTS = [
        'chartjs' => ['url' => 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', 'version' => '3.9.1', 'pages' => ['lus-installningar_page_lus-results']],
    ];

    public static function get_instance(LUS_Database $db = null) {
        if (null === self::$instance) {
            if (null === $db) {
                throw new Exception('Database instance required for admin');
            }
            self::$instance = new self($db);
        }
        return self::$instance;
    }

    public function __construct(LUS_Database $db) {
        if (self::$instance !== null) {
            // Prevent duplicate instantiation
            return self::$instance;
        }
        $this->plugin_name = LUS_Constants::PLUGIN_NAME;
        $this->version = LUS_Constants::PLUGIN_VERSION;
        $this->db = $db;
        $this->load_strings();
    }

    private function init_hooks(): void {
        if (self::$hooks_registered) {
            return;
        }
        self::$hooks_registered = true;

        remove_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        remove_action('admin_menu', [$this, 'add_menu_pages']);
        remove_action('admin_init', [$this, 'register_settings']);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('plugin_action_links_' . LUS_Constants::PLUGIN_NAME, [$this, 'add_settings_link']);

        $this->register_ajax_handlers();
    }

    private function load_strings(): void {
        $strings_path = LUS_Constants::PLUGIN_DIR . 'includes/config/strings-lus-admin.php';
        $this->strings = file_exists($strings_path) ? require $strings_path : [];
    }

    private function register_ajax_handlers(): void {
        foreach ([
            'get_passage',
            'get_passages',
            'delete_passage',
            'get_questions',
            'delete_question',
            'get_results',
            'delete_assignment',
            'save_assessment',
            'delete_recording',
            'bulk_assign_recordings'
            ] as $handler) {
            add_action('wp_ajax_lus_admin_' . $handler, [$this, 'ajax_' . $handler]);
        }
    }

    public function enqueue_assets(): void {
        $screen = get_current_screen();
        if (!$screen) return;
        // echo 'Current Screen ID: ' . $screen->id . '<br>';
        $this->enqueue_styles();
        $this->enqueue_scripts($screen->id);
    }

    public function enqueue_styles(): void {
        wp_enqueue_style($this->plugin_name, LUS_Constants::PLUGIN_URL . 'admin/css/lus-admin.css', [], $this->version, 'all');
    }


    public function enqueue_scripts(string $screen_id): void {
        if (!isset(self::SCRIPT_DEPS[$screen_id])) return;
        // Enqueue core, UI, and handler scripts IF NEEDED
        foreach (self::SCRIPT_DEPS[$screen_id] as $type => $deps) {
            $this->enqueue_script($type, $type === 'core' || $type === 'ui' ? "admin/js/lus-{$type}.js" : "admin/js/handlers/lus-{$type}-handler.js", $deps);
        }

        // Enqueue external scripts
        foreach (self::EXTERNAL_SCRIPTS as $handle => $script) {
            if (in_array($screen_id, $script['pages'])) {
                wp_enqueue_script($handle, $script['url'], [], $script['version'], true);
            }
        }
        // Localize strings to the core script and send to JS
        $localized_strings = $this->get_localized_strings();
        wp_localize_script("{$this->plugin_name}-core", 'LUSStrings', $localized_strings);

    }

    private function get_localized_strings(): array {
        return array_merge($this->strings, [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(LUS_Constants::NONCE_ADMIN),
        ]);
    }

    public function enqueue_script(string $handle, string $path, array $deps): void {
        $url = LUS_Constants::PLUGIN_URL . $path;
        $version = file_exists(LUS_Constants::PLUGIN_DIR . $path) ? filemtime(LUS_Constants::PLUGIN_DIR . $path) : $this->version;
        // Fix dependency mapping for native WP scripts like 'jquery'
        $mapped_deps = array_map(fn($dep) => in_array($dep, ['core', 'ui'])
            ? ($dep === 'core' ? 'jquery' : "{$this->plugin_name}-{$dep}")
            : $dep, $deps);
        wp_enqueue_script("{$this->plugin_name}-{$handle}", $url, $mapped_deps, $version, true);
        error_log('Enqueuing script: ' . "{$this->plugin_name}-{$handle} with deps: " . implode(',', $mapped_deps));
    }



    public function add_menu_pages(): void {
        static $menu_added = false;
        if ($menu_added) return;
        $menu_added = true;

        // Add main menu page
        $this->page_hooks['main'] = add_menu_page(
            __('Läsuppskattning', 'lus'),
            __('LUS Inställningar', 'lus'),
            'manage_options',
            'lus',
            [$this, 'render_dashboard_page'],
            'dashicons-welcome-learn-more',
            6
        );

        // Add submenu pages
        $subpages = [
            'passages' => __('Texter', 'lus'),
            'questions' => __('Frågor', 'lus'),
            'assignments' => __('Tilldelningar', 'lus'),
            'results' => __('Resultat', 'lus'),
            'recordings' => __('Inspelningar', 'lus'),
            'settings' => __('Inställningar', 'lus')
        ];

        foreach ($subpages as $slug => $title) {
            $this->page_hooks[$slug] = add_submenu_page(
                'lus',
                $title,
                $title,
                'manage_options',
                "lus-{$slug}",
                [$this, "render_{$slug}_page"]
            );
        }
        // Do not show if no bårken record(ing)s
        if ($this->db->get_total_orphaned_recordings() > 0) {
            $this->page_hooks['repair'] = add_submenu_page(
                'lus',
                __('Reparera inspelningar', 'lus'),
                __('Reparera', 'lus'),
                'manage_options',
                'lus-repair',
                [$this, 'render_repair_page']
            );
        }
    }

    public function register_settings(): void {
        $settings = [
            ['lus_enable_tracking', __('Aktivera aktivitetsspårning', 'lus'), 'lus_settings_group', true],
            ['lus_difficulty_level', __('Aktivera svårighetsgrad', 'lus'), 'lus_settings_group', false],
            ['lus_enable_ai_evaluation', __('Aktivera AI-utvärdering', 'lus'), 'lus_settings_group', false],
            ['lus_confidence_threshold', __('Vikta AI mot manuell LUSning', 'lus'), 'lus_settings_group', false],
        ];

        foreach ($settings as [$name, $title, $section, $default]) {
            register_setting('lus', $name, ['type' => 'boolean', 'default' => $default]);
            add_settings_field($name, $title, [$this, 'render_settings_page'], 'lus', $section);
        }
    }

    public function add_settings_link($links): array {
        $settings_link = '<a href="options-general.php?page=lus">' . __('Settings', 'lus') . '</a>';
        array_push($links, $settings_link);
        return $links;
    }

    public function render_settings_page(): void {
        include LUS_Constants::PLUGIN_DIR . 'admin/partials/lus-settings.php';
    }

    /**
     * Verify AJAX request
     *
     * @throws Exception If verification fails
     */
    private function verify_ajax_request(): void {
        if (!current_user_can('manage_options')) {
            throw new Exception(__('Permission denied', 'lus'));
        }

        if (!check_ajax_referer('lus_admin_action', 'nonce', false)) {
            throw new Exception(__('Security check failed', 'lus'));
        }
    }

    /**
     * Send JSON response for AJAX requests
     *
     * @param mixed  $data    Response data
     * @param bool   $success Whether request was successful
     * @param string $message Optional message
     */
    private function send_json_response($data, bool $success = true, string $message = ''): void {
        wp_send_json([
            'success' => $success,
            'data' => $data ?: ['message' => $message]
        ]);
    }


    /**
     * Page rendering methods:
     * Render dashboard page
     */
    public function render_dashboard_page(): void {
        // Get stats for dashboard
        $upload_dir = wp_upload_dir();
        $passage_filter = isset($_GET['passage_filter']) ? intval($_GET['passage_filter']) : 0;
        $passage_title = '';

        if ($passage_filter) {
            $passage = $this->db->get_passage($passage_filter);
            if ($passage) {
                $passage_title = $passage->title;
            }
        }

        // Verify template exists
        $template = LUS_Constants::PLUGIN_DIR . 'admin/partials/lus-dashboard.php';
        if (!file_exists($template)) {
            wp_die(__('Template file missing', 'lus'));
        }

        // Include template
        include $template;
    }

    /**
     * Render passages page
     */
    public function render_passages_page(): void {
        global $wpdb;
        $lus_db = $this->db;

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lus_passage_nonce'])) {
            if (!wp_verify_nonce($_POST['lus_passage_nonce'], 'lus_passage_action')) {
                wp_die(__('Security check failed', 'lus'));
            }

            $passage_data = [
                'title' => sanitize_text_field($_POST['title']),
                'content' => wp_kses_post($_POST['content']),
                'time_limit' => intval($_POST['time_limit']),
                'difficulty_level' => intval($_POST['difficulty_level'])
            ];

            // Handle file upload
            if (!empty($_FILES['audio_file']['name'])) {


                // Create directory if needed
                if (!file_exists( LUS_Constants::UPLOAD_DIR )) {
                    if (!wp_mkdir_p( LUS_Constants::UPLOAD_DIR )) {
                        $error_message = __('Failed to create upload directory', 'lus');
                    }
                }

                if (!isset($error_message)) {
                    $file_name = sanitize_file_name($_FILES['audio_file']['name']);
                    $file_path = LUS_Constants::UPLOAD_DIR . '/' . $file_name;

                    if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $file_path)) {
                        $passage_data['audio_file'] = $file_name;
                    } else {
                        $error_message = __('Failed to upload audio file', 'lus');
                    }
                }
            }

            if (!isset($error_message)) {
                if (isset($_POST['passage_id']) && !empty($_POST['passage_id'])) {
                    $result = $lus_db->update_passage(intval($_POST['passage_id']), $passage_data);
                } else {
                    $result = $lus_db->create_passage($passage_data);
                }

                if (is_wp_error($result)) {
                    $error_message = $result->get_error_message();
                } else {
                    $success_message = __('Text saved successfully.', 'lus');
                }
            }
        }

        include LUS_Constants::PLUGIN_DIR . 'admin/partials/lus-passages.php';
    }

    /**
     * Render questions page
     */
    public function render_questions_page(): void {
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lus_question_nonce'])) {
            if (!wp_verify_nonce($_POST['lus_question_nonce'], 'lus_question_action')) {
                wp_die(__('Security check failed', 'lus'));
            }

            $question_data = [
                'passage_id' => intval($_POST['passage_id']),
                'question_text' => sanitize_text_field($_POST['question_text']),
                'correct_answer' => sanitize_text_field($_POST['correct_answer']),
                'weight' => floatval($_POST['weight'])
            ];

            if (isset($_POST['question_id']) && !empty($_POST['question_id'])) {
                $result = $this->db->update_question(intval($_POST['question_id']), $question_data);
            } else {
                $result = $this->db->create_question($question_data);
            }

            if (is_wp_error($result)) {
                $error_message = $result->get_error_message();
            } else {
                $success_message = __('Question saved.', 'lus');
            }
        }

        // Get selected passage ID from query string or first passage
        $passages = $this->db->get_all_passages();
        $selected_passage_id = isset($_GET['passage_id']) ? intval($_GET['passage_id']) :
                             ($passages ? $passages[0]->id : 0);

        // Get questions for selected passage
        $questions = $selected_passage_id ? $this->db->get_questions_for_passage($selected_passage_id) : [];

        include LUS_Constants::PLUGIN_DIR . 'admin/partials/lus-questions.php';
    }

    /**
     * Render assignments page
     */
    public function render_assignments_page(): void {
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lus_assignment_nonce'])) {
            if (!wp_verify_nonce($_POST['lus_assignment_nonce'], 'lus_assignment_action')) {
                wp_die(__('Security check failed', 'lus'));
            }

            $user_id = intval($_POST['user_id']);
            $passage_id = intval($_POST['passage_id']);
            $due_date = !empty($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : null;

            $result = $this->db->assign_passage_to_user(
                $passage_id,
                $user_id,
                get_current_user_id(),
                $due_date
            );

            if ($result) {
                $success_message = __('Text assigned to student.', 'lus');
            } else {
                $error_message = __('Could not assign text.', 'lus');
            }
        }

        // Get users and passages for the form
        $users = get_users(['role__not_in' => ['administrator']]);
        $passages = $this->db->get_all_passages();

        include LUS_Constants::PLUGIN_DIR . 'admin/partials/lus-assignments.php';
    }

    /**
     * Render recordings page
     */
    public function render_recordings_page(): void {
        // Handle bulk assignment form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lus_bulk_assign_nonce'])) {
            if (!wp_verify_nonce($_POST['lus_bulk_assign_nonce'], 'lus_bulk_assign_action')) {
                wp_die(__('Security check failed', 'lus'));
            }

            if (isset($_POST['recording_passages']) && is_array($_POST['recording_passages'])) {
                $success_count = 0;
                $error_count = 0;

                foreach ($_POST['recording_passages'] as $recording_id => $passage_id) {
                    if ($passage_id > 0) {
                        $result = $this->db->update_recording_passage($recording_id, $passage_id);
                        if ($result) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }

                if ($success_count > 0) {
                    $success_message = sprintf(
                        _n(
                            '%d inspelning uppdaterad.',
                            '%d inspelningar uppdaterade.',
                            $success_count,
                            'lus'
                        ),
                        $success_count
                    );
                }

                if ($error_count > 0) {
                    $error_message = sprintf(
                        _n(
                            '%d inspelningen kunde inte uppdateras.',
                            '%d inspelningarna kunde inte uppdateras.',
                            $error_count,
                            'lus'
                        ),
                        $error_count
                    );
                }
            }
        }

        // Get pagination parameters
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Get recordings
        $recordings = $this->db->get_orphaned_recordings($per_page, $offset);
        $total_unassigned = $this->db->get_total_orphaned_recordings();
        $total_pages = ceil($total_unassigned / $per_page);

        // Get passages for assignment dropdown
        $passages = $this->db->get_all_passages(['orderby' => 'title', 'order' => 'ASC']);

        include LUS_Constants::PLUGIN_DIR . 'admin/partials/lus-recordings.php';
    }

    /**
     * Render results page
     */
    public function render_results_page(): void {
        // Get filter values
        $passage_id = isset($_GET['passage_id']) ? intval($_GET['passage_id']) : 0;
        $date_range = isset($_GET['date_range']) ? intval($_GET['date_range']) : 30;

        // Calculate date range
        $date_limit = '';
        if ($date_range > 0) {
            $date_limit = date('Y-m-d', strtotime("-{$date_range} days"));
        }

        // Get statistics
        $stats = new LUS_Statistics($this->db);
        $overall_stats = $stats->get_overall_statistics($passage_id, $date_limit);
        $passage_stats = $stats->get_passage_statistics($passage_id, $date_limit);
        $question_stats = $stats->get_question_statistics($passage_id, $date_limit);

        include LUS_Constants::PLUGIN_DIR . 'admin/partials/lus-results.php';
    }

    /**
     * Render repair page
     */
    public function render_repair_page(): void {
        include LUS_Constants::PLUGIN_DIR . 'admin/partials/lus-repair.php';
    }


    //AJAX handlers
    /**
     * AJAX handler for getting a passage
     */
    public function ajax_get_passage(): void {
        try {
            $this->verify_ajax_request();

            if (!isset($_POST['passage_id'])) {
                throw new Exception(__('Passagens ID nödvändigt.', 'lus'));
            }

            $passage_id = intval($_POST['passage_id']);
            $passage = $this->db->get_passage($passage_id);

            if (!$passage) {
                throw new Exception(__('Textpassagen hittades inte.', 'lus'));
            }

            $this->send_json_response($passage);

        } catch (Exception $e) {
            $this->send_json_response(null, false, $e->getMessage());
        }
    }

    /**
     * AJAX handler for getting all passages
     */
    public function ajax_get_passages(): void {
        try {
            $this->verify_ajax_request();

            $passages = $this->db->get_all_passages();

            if (empty($passages)) {
                throw new Exception(__('Ingen textpassage hittad.', 'lus'));
            }

            $this->send_json_response($passages);

        } catch (Exception $e) {
            $this->send_json_response(null, false, $e->getMessage());
        }
    }

    /**
     * AJAX handler for deleting a passage
     */
    public function ajax_delete_passage(): void {
        try {
            $this->verify_ajax_request();

            if (!isset($_POST['passage_id'])) {
                throw new Exception(__('Passagens ID nödvändigt.', 'lus'));
            }

            $passage_id = intval($_POST['passage_id']);
            $result = $this->db->delete_passage($passage_id);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            $this->send_json_response([
                'message' => __('Textpassagen är raderad.', 'lus')
            ]);

        } catch (Exception $e) {
            $this->send_json_response(null, false, $e->getMessage());
        }
    }

    /**
     * AJAX handler for saving an assessment
     */
    public function ajax_save_assessment(): void {
        try {
            $this->verify_ajax_request();

            if (!isset($_POST['recording_id'])) {
                throw new Exception(__('Inspelningens ID är nödvändigt', 'lus'));
            }

            $recording_id = intval($_POST['recording_id']);

            // Get configured evaluator types
            $evaluation_types = get_option('lus_enabled_evaluators', ['manual']);

            $result = $this->assessment_handler->process_assessment(
                $recording_id,
                $evaluation_types
            );

            if (!$result['success']) {
                throw new Exception($result['error']);
            }

            $this->send_json_response([
                'message' => __('Bedömningen är sparad.', 'lus'),
                'assessment_id' => $result['assessment_id'],
                'results' => $result['results']
            ]);

        } catch (Exception $e) {
            $this->send_json_response(null, false, $e->getMessage());
        }
    }

    /**
     * AJAX handler for deleting a recording
     */
    public function ajax_delete_recording(): void {
        try {
            $this->verify_ajax_request();

            if (!isset($_POST['recording_id'])) {
                throw new Exception(__('Inspelnings-ID nödvändigt', 'lus'));
            }

            $recording_id = intval($_POST['recording_id']);
            $result = $this->db->delete_recording($recording_id);

            if (!$result) {
                throw new Exception(__('Kunde inte radera inspelningen.', 'lus'));
            }

            // Clear caches
            wp_cache_delete('lus_recordings_count');
            wp_cache_delete('lus_recordings_page_1');

            $this->send_json_response([
                'message' => __('Inspelning raderad.', 'lus')
            ]);

        } catch (Exception $e) {
            $this->send_json_response(null, false, $e->getMessage());
        }
    }

    /**
     * AJAX handler for bulk assigning recordings
    */
    public function ajax_bulk_assign_recordings(): void {
        try {
            $this->verify_ajax_request();

            // Get and validate assignments data
            $assignments = isset($_POST['assignments']) ?
                json_decode(stripslashes($_POST['assignments']), true) : null;

            if (!is_array($assignments) || empty($assignments)) {
                throw new Exception(__('Det finns ingen tilldelning att spara.', 'lus'));
            }

            // Use database transaction
            $this->db->begin_transaction();

            try {
                $success_count = 0;
                $errors = [];

                // Process each assignment
                foreach ($assignments as $recording_id => $passage_id) {
                    $recording_id = absint($recording_id);
                    $passage_id = absint($passage_id);

                    // Verify recording exists
                    $recording = $this->db->get_recording($recording_id);
                    if (!$recording) {
                        $errors[] = sprintf(
                            __('Inspelningen %d ej hittad', 'lus'),
                            $recording_id
                        );
                        continue;
                    }

                    // Verify passage exists
                    $passage = $this->db->get_passage($passage_id);
                    if (!$passage) {
                        $errors[] = sprintf(
                            __('Textpassagen %d ej hittad', 'lus'),
                            $passage_id
                        );
                        continue;
                    }

                    // Update recording
                    $result = $this->db->update_recording($recording_id, [
                        'passage_id' => $passage_id,
                        'status' => LUS_Constants::STATUS_PENDING
                    ]);

                    if (is_wp_error($result)) {
                        $errors[] = sprintf(
                            __('Kunde inte uppdatera inspelningen %d: %s', 'lus'),
                            $recording_id,
                            $result->get_error_message()
                        );
                    } else {
                        $success_count++;
                    }
                }

                // If no successful updates or too many errors, rollback
                if ($success_count === 0 || count($errors) > ($success_count / 2)) {
                    throw new Exception(__('För många fel vid uppdatering.', 'lus'));
                }

                $this->db->commit();

                // Prepare response message
                $message = sprintf(
                    _n(
                        '%d inspelning tilldelad.',
                        '%d inspelningar tilldelade.',
                        $success_count,
                        'lus'
                    ),
                    $success_count
                );

                if (!empty($errors)) {
                    $message .= ' ' . sprintf(
                        _n(
                            '%d error occurred.',
                            '%d errors occurred.',
                            count($errors),
                            'lus'
                        ),
                        count($errors)
                    );
                }

                // Clear relevant caches
                $this->db->clear_cache('recordings');

                $this->send_json_response([
                    'message' => $message,
                    'updated' => $success_count,
                    'errors' => $errors
                ]);

            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            $this->send_json_response(null, false, $e->getMessage());
        }
    }

    /**
     * AJAX handler for getting questions
     */
    public function ajax_get_questions(): void {
        try {
            $this->verify_ajax_request();

            if (!isset($_POST['passage_id'])) {
                throw new Exception(__('Textpassagens ID nödvändigt.', 'lus'));
            }

            $passage_id = intval($_POST['passage_id']);
            $questions = $this->db->get_questions_for_passage($passage_id);

            if (empty($questions)) {
                throw new Exception(__('Ingen fråga hittad för denna text.', 'lus'));
            }

            $this->send_json_response($questions);

        } catch (Exception $e) {
            $this->send_json_response(null, false, $e->getMessage());
        }
    }

    /**
     * AJAX handler for saving a question
     */
    public function ajax_save_question(): void {
        try {
            $this->verify_ajax_request();

            $question_data = [
                'passage_id' => isset($_POST['passage_id']) ? intval($_POST['passage_id']) : 0,
                'question_text' => sanitize_text_field($_POST['question_text'] ?? ''),
                'correct_answer' => sanitize_text_field($_POST['correct_answer'] ?? ''),
                'weight' => isset($_POST['weight']) ? floatval($_POST['weight']) : 1.0
            ];

            if (empty($question_data['passage_id']) || empty($question_data['question_text'])) {
                throw new Exception(__('Nödvändigt fält är tomt.', 'lus'));
            }

            if (isset($_POST['question_id']) && !empty($_POST['question_id'])) {
                $result = $this->db->update_question(intval($_POST['question_id']), $question_data);
                $message = __('Frågan blev uppdaterad.', 'lus');
            } else {
                $result = $this->db->create_question($question_data);
                $message = __('Frågan blev skapad.', 'lus');
            }

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            $this->send_json_response([
                'message' => $message,
                'question_id' => is_numeric($result) ? $result : null
            ]);

        } catch (Exception $e) {
            $this->send_json_response(null, false, $e->getMessage());
        }
    }

    /**
     * AJAX handler for deleting a question
     */
    public function ajax_delete_question(): void {
        try {
            $this->verify_ajax_request();

            if (!isset($_POST['question_id'])) {
                throw new Exception(__('Question ID required', 'lus'));
            }

            $question_id = intval($_POST['question_id']);
            $result = $this->db->delete_question($question_id);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            $this->send_json_response([
                'message' => __('Frågan blev raderad.', 'lus')
            ]);

        } catch (Exception $e) {
            $this->send_json_response(null, false, $e->getMessage());
        }
    }

    /**
     * AJAX handler for getting results data
     */
    public function ajax_get_results(): void {
        try {
            $this->verify_ajax_request();

            $passage_id = isset($_POST['passage_id']) ? intval($_POST['passage_id']) : 0;
            $date_range = isset($_POST['date_range']) ? intval($_POST['date_range']) : 30;
            $date_limit = $date_range > 0 ? date('Y-m-d', strtotime("-{$date_range} days")) : '';

            $stats = new LUS_Statistics($this->db);

            $data = [
                'overall' => $stats->get_overall_statistics($passage_id, $date_limit),
                'passages' => $stats->get_passage_statistics($passage_id, $date_limit),
                'questions' => $stats->get_question_statistics($passage_id, $date_limit),
                'timeline' => $stats->get_time_stats('day', $date_range),
                'difficulty' => $stats->get_difficulty_stats()
            ];

            $this->send_json_response($data);

        } catch (Exception $e) {
            $this->send_json_response(null, false, $e->getMessage());
        }
    }

    /**
     * AJAX handler for exporting results
     */
    public function ajax_export_results(): void {
        try {
            $this->verify_ajax_request();

            $format = $_POST['format'] ?? 'csv';
            $passage_id = isset($_POST['passage_id']) ? intval($_POST['passage_id']) : 0;
            $date_range = isset($_POST['date_range']) ? intval($_POST['date_range']) : 30;

            $export_handler = new LUS_Export_Handler($this->db);
            $result = $export_handler->export_results($format, [
                'passage_id' => $passage_id,
                'date_range' => $date_range,
                'charts' => $_POST['charts'] ?? []
            ]);

            $this->send_json_response($result);

        } catch (Exception $e) {
            $this->send_json_response(null, false, $e->getMessage());
        }
    }

    /**
     * AJAX handler for repairing orphaned recordings
     */
    public function ajax_repair_recordings(): void {
        try {
            $this->verify_ajax_request();

            $recordings = isset($_POST['recordings']) ?
                array_map('intval', (array)$_POST['recordings']) : [];

            if (empty($recordings)) {
                throw new Exception(__('Ingen inspelning vald.', 'lus'));
            }

            $this->db->begin_transaction();

            try {
                $success_count = 0;
                $errors = [];

                foreach ($recordings as $recording_id) {
                    $result = $this->repair_recording($recording_id);

                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $success_count++;
                    }
                }

                if ($success_count === 0) {
                    throw new Exception(__('Kunde inte tilldela inspelade filer.', 'lus'));
                }

                $this->db->commit();

                $message = sprintf(
                    _n(
                        '%d recording repaired.',
                        '%d recordings repaired.',
                        $success_count,
                        'lus'
                    ),
                    $success_count
                );

                if (!empty($errors)) {
                    $message .= ' ' . sprintf(
                        _n(
                            '%d error occurred.',
                            '%d errors occurred.',
                            count($errors),
                            'lus'
                        ),
                        count($errors)
                    );
                }

                $this->send_json_response([
                    'message' => $message,
                    'repaired' => $success_count,
                    'errors' => $errors
                ]);

            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            $this->send_json_response(null, false, $e->getMessage());
        }
    }

    /**
     * AJAX handler for getting assignment data
     */
    public function ajax_get_assignments(): void {
        try {
            $this->verify_ajax_request();

            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

            $args = [
                'user_id' => $user_id,
                'status' => $status,
                'orderby' => sanitize_text_field($_POST['orderby'] ?? 'assigned_at'),
                'order' => sanitize_text_field($_POST['order'] ?? 'DESC')
            ];

            $assignments = $this->db->get_all_assignments($args);

            $this->send_json_response([
                'assignments' => $assignments,
                'total' => count($assignments)
            ]);

        } catch (Exception $e) {
            $this->send_json_response(null, false, $e->getMessage());
        }
    }

    /**
     * AJAX handler for deleting an assignment
     */
    public function ajax_delete_assignment(): void {
        try {
            $this->verify_ajax_request();

            if (!isset($_POST['assignment_id'])) {
                throw new Exception(__('Tilldeningens ID är nödvändigt', 'lus'));
            }

            $assignment_id = intval($_POST['assignment_id']);
            $result = $this->db->delete_assignment($assignment_id);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            $this->send_json_response([
                'message' => __('Tilldeningen är raderad', 'lus')
            ]);

        } catch (Exception $e) {
            $this->send_json_response(null, false, $e->getMessage());
        }
    }

    /**
     * AJAX handler for saving admin interactions
     */
    public function ajax_save_interactions(): void {
        try {
            $this->verify_ajax_request();

            if (!get_option('lus_enable_tracking', true)) {
                throw new Exception(__('Aktivitetsstatistik är inaktiverat', 'lus'));
            }

            $interactions = isset($_POST['interactions']) ?
                json_decode(stripslashes($_POST['interactions']), true) : null;

            if (!is_array($interactions)) {
                throw new Exception(__('Ogiltig interaktionsdata', 'lus'));
            }

            $saved = 0;
            foreach ($interactions as $interaction) {
                $result = $this->db->save_admin_interaction([
                    'user_id' => get_current_user_id(),
                    'page' => sanitize_text_field($interaction['page']),
                    'action' => sanitize_text_field($interaction['action']),
                    'clicks' => intval($interaction['clicks']),
                    'active_time' => intval($interaction['active_time']),
                    'idle_time' => intval($interaction['idle_time'])
                ]);

                if (!is_wp_error($result)) {
                    $saved++;
                }
            }

            $this->send_json_response([
                'message' => sprintf(__('%d interaktioner sparade', 'lus'), $saved)
            ]);

        } catch (Exception $e) {
            $this->send_json_response(null, false, $e->getMessage());
        }
    }
}