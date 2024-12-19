<?php
/** class-lus-database.php
 * Handles all database operations for the plugin.
 *
 * @package    LUS
 * @subpackage LUS/includes
 */
class LUS_Database {
    /** @var wpdb WordPress database instance */
    private $db;

    /** @var string Upload directory path */
    private $upload_dir;

    /** @var array Cache for frequent queries */
    private $cache = [];

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->upload_dir = wp_upload_dir()['basedir'] . '/lus';
    }

    /**
     * Get upload path for the plugin
     * @return string Full path to upload directory
     */
    private function get_upload_path() {
        return $this->upload_dir;
    }

    /**
     * Get recording path for a specific user
     * @param int $user_id User ID
     * @return string Path to user's recording directory
     */
    private function get_recording_path($user_id) {
        return $this->get_upload_path() . '/' . date('Y/m') . '/' . $user_id;
    }

    /**
     * Begin a database transaction
     */
    public function begin_transaction() {
        $this->db->query('START TRANSACTION');
    }

    /**
     * Commit a database transaction
     */
    public function commit() {
        $this->db->query('COMMIT');
    }

    /**
     * Rollback a database transaction
     */
    public function rollback() {
        $this->db->query('ROLLBACK');
    }

    /**
     * Clear cache for specific key or all cache
     * @param string|null $key Specific cache key to clear
     */
    public function clear_cache($key = null) {
        if ($key === null) {
            $this->cache = [];
        } else {
            unset($this->cache[$key]);
        }
    }

    /**
     * Create a new passage
     *
     * @param array $data Passage data
     * @return int|WP_Error New passage ID or error
     */
    public function create_passage($data) {
        try {
            if (empty($data['title']) || empty($data['content'])) {
                return new WP_Error('missing_fields', __('Title and content are required.', 'lus'));
            }

            $insert_data = [
                'title' => $data['title'],
                'content' => $data['content'],
                'time_limit' => isset($data['time_limit']) ? absint($data['time_limit']) : 180,
                'difficulty_level' => isset($data['difficulty_level']) ? absint($data['difficulty_level']) : 1,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ];

            $result = $this->db->insert(
                $this->db->prefix . 'lus_passages',
                $insert_data,
                ['%s', '%s', '%d', '%d', '%d', '%s']
            );

            if ($result === false) {
                return new WP_Error('db_error', $this->db->last_error);
            }

            $this->clear_cache('passages');
            return $this->db->insert_id;
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }

    /**
     * Get passage by ID
     *
     * @param int $passage_id Passage ID
     * @return object|null Passage object or null if not found
     */
    public function get_passage($passage_id) {
        $cache_key = "passage_{$passage_id}";

        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        $passage = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}lus_passages
             WHERE id = %d AND deleted_at IS NULL",
            $passage_id
        ));

        if ($passage) {
            $this->cache[$cache_key] = $passage;
        }

        return $passage;
    }

    /**
     * Get all passages with optional filtering
     *
     * @param array $args Query arguments
     * @return array Array of passage objects
     */
    public function get_all_passages($args = []) {
        $defaults = [
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0,
            'difficulty_level' => 0,
            'search' => '',
            'include_deleted' => false
        ];

        $args = wp_parse_args($args, $defaults);
        $where = ['1=1'];
        $where_args = [];

        if (!$args['include_deleted']) {
            $where[] = 'deleted_at IS NULL';
        }

        if ($args['difficulty_level']) {
            $where[] = 'difficulty_level = %d';
            $where_args[] = $args['difficulty_level'];
        }

        if ($args['search']) {
            $where[] = '(title LIKE %s OR content LIKE %s)';
            $search_term = '%' . $this->db->esc_like($args['search']) . '%';
            $where_args[] = $search_term;
            $where_args[] = $search_term;
        }

        $limit_clause = $args['limit'] ? ' LIMIT ' . absint($args['limit']) : '';
        $offset_clause = $args['offset'] ? ' OFFSET ' . absint($args['offset']) : '';

        $query = $this->db->prepare(
            "SELECT * FROM {$this->db->prefix}lus_passages
             WHERE " . implode(' AND ', $where) . "
             ORDER BY {$args['orderby']} {$args['order']}
             {$limit_clause}{$offset_clause}",
            $where_args
        );

        return $this->db->get_results($query);
    }

    /**
     * Update existing passage
     *
     * @param int $passage_id Passage ID
     * @param array $data Updated passage data
     * @return bool|WP_Error True on success, error object on failure
     */
    public function update_passage($passage_id, $data) {
        try {
            if (empty($data['title']) || empty($data['content'])) {
                return new WP_Error('missing_fields', __('Title and content are required.', 'lus'));
            }

            $update_data = [
                'title' => $data['title'],
                'content' => $data['content'],
                'time_limit' => absint($data['time_limit']),
                'difficulty_level' => isset($data['difficulty_level']) ? absint($data['difficulty_level']) : 1,
                'updated_at' => current_time('mysql')
            ];

            $result = $this->db->update(
                $this->db->prefix . 'lus_passages',
                $update_data,
                ['id' => $passage_id],
                ['%s', '%s', '%d', '%d', '%s'],
                ['%d']
            );

            if ($result === false) {
                return new WP_Error('db_error', $this->db->last_error);
            }

            $this->clear_cache("passage_{$passage_id}");
            $this->clear_cache('passages');
            return true;
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }

    /**
     * Soft delete a passage
     *
     * @param int $passage_id Passage ID
     * @return bool|WP_Error True on success, error object on failure
     */
    public function delete_passage($passage_id) {
        $this->begin_transaction();

        try {
            // Check for dependencies
            $has_recordings = $this->db->get_var($this->db->prepare(
                "SELECT COUNT(*) FROM {$this->db->prefix}lus_recordings
                 WHERE passage_id = %d",
                $passage_id
            ));

            if ($has_recordings > 0) {
                // Soft delete if there are dependencies
                $result = $this->db->update(
                    $this->db->prefix . 'lus_passages',
                    ['deleted_at' => current_time('mysql')],
                    ['id' => $passage_id],
                    ['%s'],
                    ['%d']
                );
            } else {
                // Hard delete if no dependencies
                $result = $this->db->delete(
                    $this->db->prefix . 'lus_passages',
                    ['id' => $passage_id],
                    ['%d']
                );
            }

            if ($result === false) {
                throw new Exception($this->db->last_error);
            }

            $this->commit();
            $this->clear_cache("passage_{$passage_id}");
            $this->clear_cache('passages');
            return true;

        } catch (Exception $e) {
            $this->rollback();
            return new WP_Error('delete_error', $e->getMessage());
        }
    }

    /**
     * Save new recording
     *
     * @param array $data Recording data including file info and duration
     * @return int|WP_Error New recording ID or error
     */
    public function save_recording($data) {
        try {
            $user_id = get_current_user_id();
            $recording_path = $this->get_recording_path($user_id);

            if (!file_exists($recording_path)) {
                wp_mkdir_p($recording_path);
            }

            $insert_data = [
                'user_id' => $user_id,
                'passage_id' => isset($data['passage_id']) ? absint($data['passage_id']) : 0,
                'audio_file_path' => str_replace($this->get_upload_path(), '', $data['file_path']),
                'duration' => isset($data['duration']) ? absint($data['duration']) : 0,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ];

            $result = $this->db->insert(
                $this->db->prefix . 'lus_recordings',
                $insert_data,
                ['%d', '%d', '%s', '%d', '%s', '%s']
            );

            if ($result === false) {
                return new WP_Error('db_error', __('Failed to save recording data.', 'lus'));
            }

            $this->clear_cache('recordings');
            return $this->db->insert_id;

        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }

    /**
     * Get recording by ID
     *
     * @param int $recording_id Recording ID
     * @return object|null Recording object with user details or null if not found
     */
    public function get_recording($recording_id) {
        $cache_key = "recording_{$recording_id}";

        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        $recording = $this->db->get_row($this->db->prepare(
            "SELECT r.*, u.display_name, u.user_email,
                    p.title as passage_title, p.difficulty_level
             FROM {$this->db->prefix}lus_recordings r
             JOIN {$this->db->users} u ON r.user_id = u.ID
             LEFT JOIN {$this->db->prefix}lus_passages p ON r.passage_id = p.id
             WHERE r.id = %d",
            $recording_id
        ));

        if ($recording) {
            $this->cache[$cache_key] = $recording;
        }

        return $recording;
    }

    /**
     * Get recordings for a user with optional filtering
     *
     * @param int $user_id User ID
     * @param array $args Query arguments
     * @return array Array of recording objects
     */
    public function get_user_recordings($user_id, $args = []) {
        $defaults = [
            'passage_id' => 0,
            'status' => '',
            'limit' => 10,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);
        $where = ['r.user_id = %d'];
        $where_args = [$user_id];

        if ($args['passage_id']) {
            $where[] = 'r.passage_id = %d';
            $where_args[] = $args['passage_id'];
        }

        if ($args['status']) {
            $where[] = 'r.status = %s';
            $where_args[] = $args['status'];
        }

        $query = $this->db->prepare(
            "SELECT r.*, p.title as passage_title
             FROM {$this->db->prefix}lus_recordings r
             LEFT JOIN {$this->db->prefix}lus_passages p ON r.passage_id = p.id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY r.{$args['orderby']} {$args['order']}
             LIMIT %d OFFSET %d",
            array_merge($where_args, [$args['limit'], $args['offset']])
        );

        return $this->db->get_results($query);
    }

    /**
     * Update recording status and metadata
     *
     * @param int $recording_id Recording ID
     * @param array $data Updated recording data
     * @return bool|WP_Error True on success, error object on failure
     */
    public function update_recording($recording_id, $data) {
        try {
            $update_data = [];
            $format = [];

            // Only update provided fields
            if (isset($data['passage_id'])) {
                $update_data['passage_id'] = absint($data['passage_id']);
                $format[] = '%d';
            }
            if (isset($data['duration'])) {
                $update_data['duration'] = absint($data['duration']);
                $format[] = '%d';
            }
            if (isset($data['status'])) {
                $update_data['status'] = sanitize_text_field($data['status']);
                $format[] = '%s';
            }
            if (isset($data['audio_file_path'])) {
                $update_data['audio_file_path'] = sanitize_text_field($data['audio_file_path']);
                $format[] = '%s';
            }

            if (empty($update_data)) {
                return new WP_Error('no_data', __('No data provided for update.', 'lus'));
            }

            $update_data['updated_at'] = current_time('mysql');
            $format[] = '%s';

            $result = $this->db->update(
                $this->db->prefix . 'lus_recordings',
                $update_data,
                ['id' => $recording_id],
                $format,
                ['%d']
            );

            if ($result === false) {
                return new WP_Error('db_error', $this->db->last_error);
            }

            $this->clear_cache("recording_{$recording_id}");
            $this->clear_cache('recordings');
            return true;

        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }

    /**
     * Delete recording and associated file
     *
     * @param int $recording_id Recording ID
     * @return bool True on success, false on failure
     */
    public function delete_recording($recording_id) {
        $this->begin_transaction();

        try {
            // Get recording info first
            $recording = $this->get_recording($recording_id);
            if (!$recording) {
                throw new Exception('Recording not found');
            }

            // Delete file if it exists
            if ($recording->audio_file_path) {
                $file_path = $this->get_upload_path() . $recording->audio_file_path;
                if (file_exists($file_path)) {
                    if (!@unlink($file_path)) {
                        throw new Exception('Failed to delete audio file');
                    }
                }
            }

            // Delete database record
            $result = $this->db->delete(
                $this->db->prefix . 'lus_recordings',
                ['id' => $recording_id],
                ['%d']
            );

            if ($result === false) {
                throw new Exception($this->db->last_error);
            }

            $this->commit();
            $this->clear_cache("recording_{$recording_id}");
            $this->clear_cache('recordings');
            return true;

        } catch (Exception $e) {
            $this->rollback();
            error_log('LUS: Error deleting recording: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Create new question
     *
     * @param array $data Question data
     * @return int|WP_Error New question ID or error
     */
    public function create_question($data) {
        try {
            if (empty($data['passage_id']) || empty($data['question_text']) || empty($data['correct_answer'])) {
                return new WP_Error('missing_fields', __('All fields are required.', 'lus'));
            }

            // Validate passage exists
            if (!$this->get_passage($data['passage_id'])) {
                return new WP_Error('invalid_passage', __('Invalid passage ID.', 'lus'));
            }

            $insert_data = [
                'passage_id' => absint($data['passage_id']),
                'question_text' => wp_kses_post($data['question_text']),
                'correct_answer' => sanitize_text_field($data['correct_answer']),
                'weight' => isset($data['weight']) ? floatval($data['weight']) : 1.0,
                'active' => isset($data['active']) ? absint($data['active']) : 1,
                'created_at' => current_time('mysql')
            ];

            $result = $this->db->insert(
                $this->db->prefix . 'lus_questions',
                $insert_data,
                ['%d', '%s', '%s', '%f', '%d', '%s']
            );

            if ($result === false) {
                return new WP_Error('db_error', $this->db->last_error);
            }

            $this->clear_cache('questions_passage_' . $data['passage_id']);
            return $this->db->insert_id;

        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }

    /**
     * Get question by ID
     *
     * @param int $question_id Question ID
     * @return object|null Question object with passage info or null if not found
     */
    public function get_question($question_id) {
        $cache_key = "question_{$question_id}";

        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        $question = $this->db->get_row($this->db->prepare(
            "SELECT q.*, p.title as passage_title
             FROM {$this->db->prefix}lus_questions q
             JOIN {$this->db->prefix}lus_passages p ON q.passage_id = p.id
             WHERE q.id = %d",
            $question_id
        ));

        if ($question) {
            $this->cache[$cache_key] = $question;
        }

        return $question;
    }

    /**
     * Get questions for a passage
     *
     * @param int $passage_id Passage ID
     * @param array $args Optional arguments
     * @return array Array of question objects
     */
    public function get_questions_for_passage($passage_id, $args = []) {
        $defaults = [
            'active_only' => true,
            'orderby' => 'id',
            'order' => 'ASC',
            'include_stats' => false
        ];

        $args = wp_parse_args($args, $defaults);
        $cache_key = "questions_passage_{$passage_id}";

        if (isset($this->cache[$cache_key]) && !$args['include_stats']) {
            return $this->cache[$cache_key];
        }

        $where = ['q.passage_id = %d'];
        $where_args = [$passage_id];

        if ($args['active_only']) {
            $where[] = 'q.active = 1';
        }

        if ($args['include_stats']) {
            $query = $this->db->prepare(
                "SELECT q.*,
                        COUNT(r.id) as total_responses,
                        SUM(r.is_correct) as correct_responses,
                        AVG(r.similarity) as avg_similarity
                 FROM {$this->db->prefix}lus_questions q
                 LEFT JOIN {$this->db->prefix}lus_responses r ON q.id = r.question_id
                 WHERE " . implode(' AND ', $where) . "
                 GROUP BY q.id
                 ORDER BY q.{$args['orderby']} {$args['order']}",
                $where_args
            );
        } else {
            $query = $this->db->prepare(
                "SELECT q.*
                 FROM {$this->db->prefix}lus_questions q
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY q.{$args['orderby']} {$args['order']}",
                $where_args
            );
        }

        $questions = $this->db->get_results($query);

        if (!$args['include_stats']) {
            $this->cache[$cache_key] = $questions;
        }

        return $questions;
    }

    /**
     * Update existing question
     *
     * @param int $question_id Question ID
     * @param array $data Updated question data
     * @return bool|WP_Error True on success, error object on failure
     */
    public function update_question($question_id, $data) {
        try {
            if (empty($data['question_text']) || empty($data['correct_answer'])) {
                return new WP_Error('missing_fields', __('Question text and correct answer are required.', 'lus'));
            }

            $update_data = [
                'question_text' => wp_kses_post($data['question_text']),
                'correct_answer' => sanitize_text_field($data['correct_answer']),
                'weight' => isset($data['weight']) ? floatval($data['weight']) : 1.0,
                'active' => isset($data['active']) ? absint($data['active']) : 1,
                'updated_at' => current_time('mysql')
            ];

            $result = $this->db->update(
                $this->db->prefix . 'lus_questions',
                $update_data,
                ['id' => $question_id],
                ['%s', '%s', '%f', '%d', '%s'],
                ['%d']
            );

            if ($result === false) {
                return new WP_Error('db_error', $this->db->last_error);
            }

            // Clear relevant caches
            $question = $this->get_question($question_id);
            if ($question) {
                $this->clear_cache('questions_passage_' . $question->passage_id);
            }
            $this->clear_cache("question_{$question_id}");

            return true;

        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }

    /**
     * Delete or deactivate question based on usage
     *
     * @param int $question_id Question ID
     * @return bool|WP_Error True on success, error object on failure
     */
    public function delete_question($question_id) {
        $this->begin_transaction();

        try {
            // Check if question has responses
            $has_responses = $this->db->get_var($this->db->prepare(
                "SELECT COUNT(*)
                 FROM {$this->db->prefix}lus_responses
                 WHERE question_id = %d",
                $question_id
            ));

            // Get question for cache clearing
            $question = $this->get_question($question_id);

            if ($has_responses > 0) {
                // Soft delete by deactivating
                $result = $this->db->update(
                    $this->db->prefix . 'lus_questions',
                    ['active' => 0, 'updated_at' => current_time('mysql')],
                    ['id' => $question_id],
                    ['%d', '%s'],
                    ['%d']
                );
            } else {
                // Hard delete if no responses
                $result = $this->db->delete(
                    $this->db->prefix . 'lus_questions',
                    ['id' => $question_id],
                    ['%d']
                );
            }

            if ($result === false) {
                throw new Exception($this->db->last_error);
            }

            $this->commit();

            // Clear caches
            if ($question) {
                $this->clear_cache('questions_passage_' . $question->passage_id);
            }
            $this->clear_cache("question_{$question_id}");

            return true;

        } catch (Exception $e) {
            $this->rollback();
            return new WP_Error('delete_error', $e->getMessage());
        }
    }

    /**
     * Get question statistics
     *
     * @param int $question_id Question ID
     * @return object|null Statistics object or null if not found
     */
    public function get_question_statistics($question_id) {
        return $this->db->get_row($this->db->prepare(
            "SELECT
                COUNT(r.id) as total_responses,
                SUM(r.is_correct) as correct_responses,
                AVG(r.score) as average_score,
                AVG(r.similarity) as average_similarity,
                MIN(r.created_at) as first_response,
                MAX(r.created_at) as last_response
            FROM {$this->db->prefix}lus_questions q
            LEFT JOIN {$this->db->prefix}lus_responses r ON q.id = r.question_id
            WHERE q.id = %d
            GROUP BY q.id",
            $question_id
        ));
    }


    /**
     * Save a response to a question
     *
     * @param array $data Response data
     * @return int|WP_Error Response ID or error
     */
    public function save_response($data) {
        try {
            if (empty($data['recording_id']) || empty($data['question_id']) || !isset($data['user_answer'])) {
                return new WP_Error('missing_fields', __('All fields are required.', 'lus'));
            }

            // Get question for comparison
            $question = $this->get_question($data['question_id']);
            if (!$question) {
                return new WP_Error('invalid_question', __('Invalid question ID.', 'lus'));
            }

            // Calculate similarity and correctness
            $similarity = $this->calculate_similarity(
                $question->correct_answer,
                $data['user_answer']
            );

            $insert_data = [
                'recording_id' => absint($data['recording_id']),
                'question_id' => absint($data['question_id']),
                'user_answer' => sanitize_text_field($data['user_answer']),
                'is_correct' => ($similarity >= 90) ? 1 : 0,
                'score' => $similarity >= 90 ? $question->weight : 0,
                'similarity' => $similarity,
                'created_at' => current_time('mysql')
            ];

            $result = $this->db->insert(
                $this->db->prefix . 'lus_responses',
                $insert_data,
                ['%d', '%d', '%s', '%d', '%f', '%f', '%s']
            );

            if ($result === false) {
                return new WP_Error('db_error', $this->db->last_error);
            }

            $this->clear_cache('responses_recording_' . $data['recording_id']);
            return $this->db->insert_id;

        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }

    /**
     * Calculate similarity between two strings
     *
     * @param string $str1 First string
     * @param string $str2 Second string
     * @return float Similarity percentage
     */
    private function calculate_similarity($str1, $str2) {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));

        if ($str1 === $str2) {
            return 100;
        }

        $leven = levenshtein($str1, $str2);
        $max_len = max(strlen($str1), strlen($str2));

        if ($max_len === 0) {
            return 100;
        }

        return (1 - ($leven / $max_len)) * 100;
    }

    /**
     * Save assessment for a recording
     *
     * @param array $data Assessment data
     * @return int|WP_Error Assessment ID or error
     */
    public function save_assessment($data) {
        $this->begin_transaction();

        try {
            if (empty($data['recording_id'])) {
                throw new Exception(__('Recording ID is required.', 'lus'));
            }

            // Verify recording exists
            $recording = $this->get_recording($data['recording_id']);
            if (!$recording) {
                throw new Exception(__('Invalid recording ID.', 'lus'));
            }

            // Calculate scores if not provided
            if (!isset($data['total_score']) || !isset($data['normalized_score'])) {
                $scores = $this->calculate_recording_scores($data['recording_id']);
                $data['total_score'] = $scores['total_score'];
                $data['normalized_score'] = $scores['normalized_score'];
            }

            $insert_data = [
                'recording_id' => absint($data['recording_id']),
                'total_score' => floatval($data['total_score']),
                'normalized_score' => floatval($data['normalized_score']),
                'assessed_by' => get_current_user_id(),
                'completed_at' => current_time('mysql')
            ];

            $result = $this->db->insert(
                $this->db->prefix . 'lus_assessments',
                $insert_data,
                ['%d', '%f', '%f', '%d', '%s']
            );

            if ($result === false) {
                throw new Exception($this->db->last_error);
            }

            // Update recording status
            $this->update_recording($data['recording_id'], ['status' => 'assessed']);

            $this->commit();
            $this->clear_cache('assessments_recording_' . $data['recording_id']);
            return $this->db->insert_id;

        } catch (Exception $e) {
            $this->rollback();
            return new WP_Error('assessment_error', $e->getMessage());
        }
    }

    /**
     * Calculate scores for a recording based on responses
     *
     * @param int $recording_id Recording ID
     * @return array Calculated scores
     */
    private function calculate_recording_scores($recording_id) {
        $responses = $this->db->get_results($this->db->prepare(
            "SELECT r.*, q.weight
             FROM {$this->db->prefix}lus_responses r
             JOIN {$this->db->prefix}lus_questions q ON r.question_id = q.id
             WHERE r.recording_id = %d",
            $recording_id
        ));

        $total_score = 0;
        $total_weight = 0;

        foreach ($responses as $response) {
            $total_score += $response->score;
            $total_weight += $response->weight;
        }

        return [
            'total_score' => $total_score,
            'normalized_score' => $total_weight > 0 ? ($total_score / $total_weight) * 100 : 0
        ];
    }

    /**
     * Get responses for a recording
     *
     * @param int $recording_id Recording ID
     * @return array Array of response objects
     */
    public function get_recording_responses($recording_id) {
        $cache_key = "responses_recording_{$recording_id}";

        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        $responses = $this->db->get_results($this->db->prepare(
            "SELECT r.*, q.question_text, q.correct_answer, q.weight
             FROM {$this->db->prefix}lus_responses r
             JOIN {$this->db->prefix}lus_questions q ON r.question_id = q.id
             WHERE r.recording_id = %d
             ORDER BY q.id ASC",
            $recording_id
        ));

        if ($responses) {
            $this->cache[$cache_key] = $responses;
        }

        return $responses;
    }

    /**
     * Get assessment details
     *
     * @param int $assessment_id Assessment ID
     * @return object|null Assessment object or null if not found
     */
    public function get_assessment($assessment_id) {
        return $this->db->get_row($this->db->prepare(
            "SELECT a.*,
                    r.passage_id,
                    u.display_name as assessor_name,
                    p.title as passage_title
             FROM {$this->db->prefix}lus_assessments a
             JOIN {$this->db->prefix}lus_recordings r ON a.recording_id = r.id
             JOIN {$this->db->users} u ON a.assessed_by = u.ID
             JOIN {$this->db->prefix}lus_passages p ON r.passage_id = p.id
             WHERE a.id = %d",
            $assessment_id
        ));
    }

    /**
     * Get assessment statistics for a passage
     *
     * @param int $passage_id Passage ID
     * @return object Statistics object
     */
    public function get_passage_assessment_stats($passage_id) {
        return $this->db->get_row($this->db->prepare(
            "SELECT
                COUNT(DISTINCT r.id) as total_recordings,
                COUNT(DISTINCT a.id) as total_assessments,
                AVG(a.normalized_score) as average_score,
                MIN(a.normalized_score) as min_score,
                MAX(a.normalized_score) as max_score,
                AVG(r.duration) as average_duration,
                COUNT(DISTINCT r.user_id) as unique_users
             FROM {$this->db->prefix}lus_recordings r
             LEFT JOIN {$this->db->prefix}lus_assessments a ON r.id = a.recording_id
             WHERE r.passage_id = %d",
            $passage_id
        ));
    }
}