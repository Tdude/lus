<?php
/** File: class-lus-database.php
 * Database Handler Class
 *
 * Handles all database operations for the plugin with error handling,
 * caching, and prepared statements.
 *
 * @package    LUS
 * @subpackage LUS/includes
 */

class LUS_Database {
    /** @var wpdb WordPress database instance */
    private $db;

    /** @var array Cache storage */
    private $cache = [];

    /** @var array SQL query templates */
    private const SQL = [
        // Passages
        'get_passage' => "
            SELECT *
            FROM {prefix}passages
            WHERE id = %d
            AND deleted_at IS NULL",

        'get_all_passages' => "
            SELECT *
            FROM {prefix}passages
            WHERE deleted_at IS NULL
            ORDER BY %s %s
            LIMIT %d OFFSET %d",

        'create_passage' => "
            INSERT INTO {prefix}passages
            (title, content, time_limit, difficulty_level, created_by, created_at)
            VALUES (%s, %s, %d, %d, %d, %s)",

        'update_passage' => "
            UPDATE {prefix}passages
            SET title = %s,
                content = %s,
                time_limit = %d,
                difficulty_level = %d,
                updated_at = %s
            WHERE id = %d",

        'soft_delete_passage' => "
            UPDATE {prefix}passages
            SET deleted_at = %s
            WHERE id = %d",

        // Recordings
        'get_recording' => "
            SELECT r.*, u.display_name, u.user_email,
                   p.title as passage_title, p.difficulty_level
            FROM {prefix}recordings r
            JOIN {wp_prefix}users u ON r.user_id = u.ID
            LEFT JOIN {prefix}passages p ON r.passage_id = p.id
            WHERE r.id = %d",

        'get_recordings' => "
            SELECT r.*, u.display_name, p.title as passage_title
            FROM {prefix}recordings r
            INNER JOIN {wp_prefix}users u ON r.user_id = u.ID
            LEFT JOIN {prefix}passages p ON r.passage_id = p.id
            WHERE 1=1
            AND r.status = 'active' -- Example filter
            ORDER BY r.created_at DESC
            LIMIT %d OFFSET %d",

        'create_recording' => "
            INSERT INTO {prefix}recordings
            (user_id, passage_id, audio_file_path, duration, status, created_at)
            VALUES (%d, %d, %s, %d, %s, %s)",

        'update_recording' => "
            UPDATE {prefix}recordings
            SET passage_id = %d,
                status = %s,
                updated_at = %s
            WHERE id = %d",

        // Questions
        'create_question' => "
            INSERT INTO {prefix}questions
            (passage_id, question_text, correct_answer, weight, active, created_at)
            VALUES (%d, %s, %s, %f, 1, %s)",

        'update_question' => "
            UPDATE {prefix}questions
            SET question_text = %s,
                correct_answer = %s,
                weight = %f,
                active = 1,
                updated_at = %s
            WHERE id = %d",

        'get_question' => "
            SELECT q.*, p.title as passage_title
            FROM {prefix}questions q
            JOIN {prefix}passages p ON q.passage_id = p.id
            WHERE q.id = %d
            AND q.active = 1",

        'get_passage_questions' => "
            SELECT q.*
            FROM {prefix}questions q
            WHERE q.passage_id = %d
            AND q.active = 1
            ORDER BY q.id ASC",

        // Responses
        'get_recording_responses' => "
            SELECT r.*, q.question_text, q.correct_answer, q.weight
            FROM {prefix}responses r
            JOIN {prefix}questions q ON r.question_id = q.id
            WHERE r.recording_id = %d
            ORDER BY q.id ASC",

        // Assessments
        'get_assessment' => "
            SELECT a.*, r.passage_id, u.display_name as assessor_name,
                   p.title as passage_title
            FROM {prefix}assessments a
            JOIN {prefix}recordings r ON a.recording_id = r.id
            JOIN {wp_prefix}users u ON a.assessed_by = u.ID
            JOIN {prefix}passages p ON r.passage_id = p.id
            WHERE a.id = %d",

        // Statistics
        'get_passage_stats' => "
            SELECT COUNT(r.id) as recording_count,
                   AVG(a.normalized_score) as avg_score,
                   AVG(r.duration) as avg_duration,
                   COUNT(DISTINCT r.user_id) as unique_users
            FROM {prefix}passages p
            LEFT JOIN {prefix}recordings r ON p.id = r.passage_id
            LEFT JOIN {prefix}assessments a ON r.id = a.recording_id
            WHERE p.id = %d
            {date_filter}"
    ];

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }

    /**
     * Begin a database transaction
     */
    public function begin_transaction(): void {
        $this->db->query('START TRANSACTION');
    }

    /**
     * Commit a database transaction
     */
    public function commit(): void {
        $this->db->query('COMMIT');
    }

    /**
     * Rollback a database transaction
     */
    public function rollback(): void {
        $this->db->query('ROLLBACK');
    }

    /**
     * Execute a transaction with callback
     *
     * @param callable $callback Transaction callback
     * @return mixed Result of callback
     * @throws Exception
     */
    public function transaction(callable $callback) {
        $this->begin_transaction();

        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Prepare SQL query with proper table prefixes
     *
     * @param string $query SQL query template
     * @param array  $args  Query arguments
     * @return string Prepared query
     */
    private function prepare_query(string $query, array $args = []): string {
        // Replace table prefixes
        $query = str_replace(
            ['{prefix}', '{wp_prefix}'],
            [$this->db->prefix . 'lus_', $this->db->prefix],
            $query
        );

        // Prepare with arguments if provided
        if (!empty($args)) {
            return $this->db->prepare($query, $args);
        }

        return $query;
    }

    /**
     * Get item from cache or execute callback
     *
     * @param string   $key      Cache key
     * @param callable $callback Callback to execute if not cached
     * @param int      $expires  Cache expiration in seconds
     * @return mixed Cached or fresh data
     */
    private function cache_get(string $key, callable $callback, int $expires = 300) {
        // Check runtime cache
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        // Check WordPress transient
        $cached = get_transient("lus_$key");
        if ($cached !== false) {
            $this->cache[$key] = $cached;
            return $cached;
        }

        // Execute callback and cache result
        $data = $callback();
        $this->cache[$key] = $data;
        set_transient("lus_$key", $data, $expires);

        return $data;
    }

    /**
     * Clear cache for specific key or all cache
     *
     * @param string|null $key Specific cache key to clear
     */
    public function clear_cache(?string $key = null): void {
        if ($key === null) {
            // Clear all cache
            $this->cache = [];
            $this->delete_transients();
        } else {
            // Clear specific key
            unset($this->cache[$key]);
            delete_transient("lus_$key");
        }
    }

    /**
     * Delete all plugin transients
     */
    private function delete_transients(): void {
        $this->db->query("
            DELETE FROM {$this->db->options}
            WHERE option_name LIKE '%_transient_lus_%'
            OR option_name LIKE '%_transient_timeout_lus_%'
        ");
    }

    /**
     * Handle database errors
     *
     * @param string $operation Operation being performed
     * @throws Exception
     */
    private function handle_error(string $operation): void {
        if ($this->db->last_error) {
            throw new Exception(sprintf(
                __('Database error during %1$s: %2$s', 'lus'),
                $operation,
                $this->db->last_error
            ));
        }
    }

    /**
     * Get passage by ID
     *
     * @param int $passage_id Passage ID
     * @return object|null Passage object or null if not found
     * @throws Exception
     */
    public function get_passage(int $passage_id): ?object {
        return $this->cache_get("passage_$passage_id", function() use ($passage_id) {
            $query = $this->prepare_query(
                self::SQL['get_passage'],
                [$passage_id]
            );

            $result = $this->db->get_row($query);
            $this->handle_error('get_passage');

            return $result;
        });
    }

    /**
     * Get all passages with optional filtering
     *
     * @param array $args Query arguments
     * @return array Array of passage objects
     * @throws Exception
     */
    public function get_all_passages(array $args = []): array {
        $defaults = [
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => LUS_Constants::DEFAULT_PER_PAGE,
            'offset' => 0,
            'search' => '',
            'difficulty_level' => 0
        ];

        $args = wp_parse_args($args, $defaults);
        $cache_key = 'passages_' . md5(serialize($args));

        return $this->cache_get($cache_key, function() use ($args) {
            // Build WHERE clause
            $where = [];
            $where_args = [];

            if ($args['difficulty_level']) {
                $where[] = 'difficulty_level = %d';
                $where_args[] = $args['difficulty_level'];
            }

            if ($args['search']) {
                $where[] = '(title LIKE %s OR content LIKE %s)';
                $search = '%' . $this->db->esc_like($args['search']) . '%';
                $where_args[] = $search;
                $where_args[] = $search;
            }

            // Prepare final arguments
            $query_args = array_merge(
                $where_args,
                [
                    $args['orderby'],
                    $args['order'],
                    $args['limit'],
                    $args['offset']
                ]
            );

            // Build and execute query
            $query = $this->prepare_query(
                self::SQL['get_all_passages'],
                $query_args
            );

            $results = $this->db->get_results($query);
            $this->handle_error('get_all_passages');

            return $results;
        });
    }

/**
     * Create a new passage
     *
     * @param array $data Passage data
     * @return int|WP_Error New passage ID or error
     */
    public function create_passage(array $data) {
        try {
            if (empty($data['title']) || empty($data['content'])) {
                return new WP_Error('missing_fields', __('Title and content are required.', 'lus'));
            }

            $result = $this->db->insert(
                $this->db->prefix . LUS_Constants::TABLE_PASSAGES,
                [
                    'title' => $data['title'],
                    'content' => $data['content'],
                    'time_limit' => isset($data['time_limit']) ? absint($data['time_limit']) : LUS_Constants::DEFAULT_TIME_LIMIT,
                    'difficulty_level' => isset($data['difficulty_level']) ? absint($data['difficulty_level']) : LUS_Constants::DEFAULT_DIFFICULTY_LEVEL,
                    'created_by' => get_current_user_id(),
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%s', '%d', '%d', '%d', '%s']
            );

            if ($result === false) {
                throw new Exception($this->db->last_error);
            }

            $passage_id = $this->db->insert_id;
            $this->clear_cache('passages');

            return $passage_id;

        } catch (Exception $e) {
            return new WP_Error('db_error', $e->getMessage());
        }
    }

    /**
     * Update existing passage
     *
     * @param int   $passage_id Passage ID
     * @param array $data       Updated passage data
     * @return bool|WP_Error True on success, error object on failure
     */
    public function update_passage(int $passage_id, array $data) {
        try {
            if (empty($data['title']) || empty($data['content'])) {
                return new WP_Error('missing_fields', __('Title and content are required.', 'lus'));
            }

            $result = $this->db->update(
                $this->db->prefix . LUS_Constants::TABLE_PASSAGES,
                [
                    'title' => $data['title'],
                    'content' => $data['content'],
                    'time_limit' => absint($data['time_limit']),
                    'difficulty_level' => absint($data['difficulty_level']),
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $passage_id],
                ['%s', '%s', '%d', '%d', '%s'],
                ['%d']
            );

            if ($result === false) {
                throw new Exception($this->db->last_error);
            }

            $this->clear_cache("passage_$passage_id");
            $this->clear_cache('passages');

            return true;

        } catch (Exception $e) {
            return new WP_Error('db_error', $e->getMessage());
        }
    }

    /**
     * Delete passage (soft delete)
     *
     * @param int $passage_id Passage ID
     * @return bool|WP_Error True on success, error object on failure
     */
    public function delete_passage(int $passage_id) {
        return $this->transaction(function() use ($passage_id) {
            try {
                // Check for dependencies
                $has_recordings = $this->db->get_var($this->db->prepare(
                    "SELECT COUNT(*) FROM {$this->db->prefix}" . LUS_Constants::TABLE_RECORDINGS .
                    " WHERE passage_id = %d",
                    $passage_id
                ));

                if ($has_recordings > 0) {
                    // Soft delete
                    $result = $this->db->update(
                        $this->db->prefix . LUS_Constants::TABLE_PASSAGES,
                        ['deleted_at' => current_time('mysql')],
                        ['id' => $passage_id],
                        ['%s'],
                        ['%d']
                    );
                } else {
                    // Hard delete
                    $result = $this->db->delete(
                        $this->db->prefix . LUS_Constants::TABLE_PASSAGES,
                        ['id' => $passage_id],
                        ['%d']
                    );
                }

                if ($result === false) {
                    throw new Exception($this->db->last_error);
                }

                $this->clear_cache("passage_$passage_id");
                $this->clear_cache('passages');

                return true;

            } catch (Exception $e) {
                return new WP_Error('delete_error', $e->getMessage());
            }
        });
    }

    /**
     * Save new recording
     *
     * @param array $data Recording data
     * @return int|WP_Error New recording ID or error
     */
    public function save_recording(array $data) {
        try {
            $insert_data = [
                'user_id' => get_current_user_id(),
                'passage_id' => isset($data['passage_id']) ? absint($data['passage_id']) : 0,
                'audio_file_path' => str_replace(LUS_Constants::UPLOAD_DIR, '', $data['file_path']),
                'duration' => isset($data['duration']) ? absint($data['duration']) : 0,
                'status' => LUS_Constants::STATUS_PENDING,
                'created_at' => current_time('mysql')
            ];

            $result = $this->db->insert(
                $this->db->prefix . LUS_Constants::TABLE_RECORDINGS,
                $insert_data,
                ['%d', '%d', '%s', '%d', '%s', '%s']
            );

            if ($result === false) {
                throw new Exception($this->db->last_error);
            }

            $recording_id = $this->db->insert_id;
            $this->clear_cache('recordings');

            return $recording_id;

        } catch (Exception $e) {
            return new WP_Error('db_error', $e->getMessage());
        }
    }

    /**
     * Update recording status and metadata
     *
     * @param int   $recording_id Recording ID
     * @param array $data         Updated recording data
     * @return bool|WP_Error True on success, error object on failure
     */
    public function update_recording(int $recording_id, array $data) {
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

            if (empty($update_data)) {
                return new WP_Error('no_data', __('No data provided for update.', 'lus'));
            }

            $update_data['updated_at'] = current_time('mysql');
            $format[] = '%s';

            $result = $this->db->update(
                $this->db->prefix . LUS_Constants::TABLE_RECORDINGS,
                $update_data,
                ['id' => $recording_id],
                $format,
                ['%d']
            );

            if ($result === false) {
                throw new Exception($this->db->last_error);
            }

            $this->clear_cache("recording_$recording_id");
            $this->clear_cache('recordings');

            return true;

        } catch (Exception $e) {
            return new WP_Error('update_error', $e->getMessage());
        }
    }

    /**
     * Delete recording and associated file
     *
     * @param int $recording_id Recording ID
     * @return bool True on success, false on failure
     */
    public function delete_recording(int $recording_id): bool {
        return $this->transaction(function() use ($recording_id) {
            try {
                // Get recording info first
                $recording = $this->get_recording($recording_id);
                if (!$recording) {
                    throw new Exception('Recording not found');
                }

                // Delete file if it exists
                if ($recording->audio_file_path) {
                    $file_path = LUS_Constants::UPLOAD_DIR . $recording->audio_file_path;
                    if (file_exists($file_path)) {
                        if (!@unlink($file_path)) {
                            throw new Exception('Failed to delete audio file');
                        }
                    }
                }

                // Delete database record
                $result = $this->db->delete(
                    $this->db->prefix . LUS_Constants::TABLE_RECORDINGS,
                    ['id' => $recording_id],
                    ['%d']
                );

                if ($result === false) {
                    throw new Exception($this->db->last_error);
                }

                $this->clear_cache("recording_$recording_id");
                $this->clear_cache('recordings');

                return true;

            } catch (Exception $e) {
                error_log('LUS: Error deleting recording: ' . $e->getMessage());
                return false;
            }
        });
    }

    /**
     * Get total count of orphaned recordings
     *
     * @return int Number of recordings needing repair
     */
    public function get_total_orphaned_recordings(): int {
        return (int)$this->cache_get('orphaned_recordings_count', function() {
            return $this->db->get_var("
                SELECT COUNT(*)
                FROM {$this->db->prefix}" . LUS_Constants::TABLE_RECORDINGS . "
                WHERE passage_id = 0 OR passage_id IS NULL"
            );
        });
    }

    /**
    * Get orphaned/unassigned recordings with user details and optional pagination
    *
    * Returns recordings that have no passage_id (0 or NULL) along with user display names.
    * Results are ordered by creation date, newest first.
    *
    * @param int $limit Maximum number of records to return
    * @param int $offset Number of records to skip (for pagination)
    * @return array Array of recording objects with user details
    */
    public function get_orphaned_recordings($limit = 20, $offset = 0) {
        return $this->db->get_results($this->db->prepare(
            "SELECT r.*, u.display_name
             FROM {$this->db->prefix}" . LUS_Constants::TABLE_RECORDINGS . " r
             JOIN {$this->db->users} u ON r.user_id = u.ID
             WHERE r.passage_id = 0 OR r.passage_id IS NULL
             ORDER BY r.created_at DESC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }


    /**
     * Get assessment details
     *
     * @param int $assessment_id Assessment ID
     * @return object|null Assessment object or null if not found
     */
    public function get_assessment(int $assessment_id): ?object {
        return $this->cache_get("assessment_$assessment_id", function() use ($assessment_id) {
            $query = $this->prepare_query(
                self::SQL['get_assessment'],
                [$assessment_id]
            );

            $result = $this->db->get_row($query);
            $this->handle_error('get_assessment');

            return $result;
        });
    }

    /**
     * Save assessment for a recording
     *
     * @param array $data Assessment data
     * @return int|WP_Error Assessment ID or error
     */
    public function save_assessment(array $data) {
        return $this->transaction(function() use ($data) {
            try {
                if (empty($data['recording_id'])) {
                    throw new Exception(__('Recording ID is required.', 'lus'));
                }

                // Verify recording exists
                $recording = $this->get_recording($data['recording_id']);
                if (!$recording) {
                    throw new Exception(__('Invalid recording ID.', 'lus'));
                }

                $insert_data = [
                    'recording_id' => absint($data['recording_id']),
                    'total_score' => floatval($data['total_score']),
                    'normalized_score' => floatval($data['normalized_score']),
                    'assessed_by' => get_current_user_id(),
                    'completed_at' => current_time('mysql')
                ];

                $result = $this->db->insert(
                    $this->db->prefix . LUS_Constants::TABLE_ASSESSMENTS,
                    $insert_data,
                    ['%d', '%f', '%f', '%d', '%s']
                );

                if ($result === false) {
                    throw new Exception($this->db->last_error);
                }

                $assessment_id = $this->db->insert_id;

                // Update recording status
                $this->update_recording($data['recording_id'], [
                    'status' => LUS_Constants::STATUS_ASSESSED
                ]);

                $this->clear_cache("assessment_$assessment_id");
                $this->clear_cache("recording_{$data['recording_id']}");

                return $assessment_id;

            } catch (Exception $e) {
                return new WP_Error('assessment_error', $e->getMessage());
            }
        });
    }


    /**
     * Create a new question
     *
     * @param array $question_data Associative array of question data
     * @return int|WP_Error Inserted question ID on success, or WP_Error on failure
     */
    public function create_question(array $question_data) {
        $query = $this->prepare_query(
            self::SQL['create_question'],
            [
                $question_data['passage_id'],
                $question_data['question_text'],
                $question_data['correct_answer'],
                $question_data['weight'],
                current_time('mysql')
            ]
        );

        $result = $this->db->query($query);

        if ($result === false) {
            return new WP_Error(
                'db_insert_error',
                __('Failed to insert question into the database.', 'lus'),
                $this->db->last_error
            );
        }

        return $this->db->insert_id;
    }

    /**
     * Update an existing question
     *
     * @param int $question_id Question ID
     * @param array $question_data Associative array of question data
     * @return int|WP_Error Number of rows updated, or WP_Error on failure
     */
    public function update_question(int $question_id, array $question_data) {
        $query = $this->prepare_query(
            self::SQL['update_question'],
            [
                $question_data['question_text'],
                $question_data['correct_answer'],
                $question_data['weight'],
                current_time('mysql'),
                $question_id
            ]
        );

        $result = $this->db->query($query);

        if ($result === false) {
            return new WP_Error(
                'db_update_error',
                __('Failed to update question in the database.', 'lus'),
                $this->db->last_error
            );
        }

        return $result;
    }

    /**
     * Get questions for a passage
     *
     * @param int   $passage_id  Passage ID
     * @param array $args        Optional arguments
     * @return array Array of question objects
     */
    public function get_questions_for_passage(int $passage_id, array $args = []): array {
        $cache_key = "questions_passage_{$passage_id}_" . md5(serialize($args));

        return $this->cache_get($cache_key, function() use ($passage_id, $args) {
            $query = $this->prepare_query(
                self::SQL['get_passage_questions'],
                [$passage_id]
            );

            $results = $this->db->get_results($query);
            $this->handle_error('get_questions_for_passage');

            return $results;
        });
    }

    /**
     * Get responses for a recording
     *
     * @param int $recording_id Recording ID
     * @return array Array of response objects
     */
    public function get_recording_responses(int $recording_id): array {
        return $this->cache_get("responses_recording_$recording_id", function() use ($recording_id) {
            $query = $this->prepare_query(
                self::SQL['get_recording_responses'],
                [$recording_id]
            );

            $results = $this->db->get_results($query);
            $this->handle_error('get_recording_responses');

            return $results;
        });
    }

    /**
     * Save a response to a question
     *
     * @param array $data Response data
     * @return int|WP_Error Response ID or error
     */
    public function save_response(array $data) {
        try {
            if (empty($data['recording_id']) || empty($data['question_id']) || !isset($data['user_answer'])) {
                return new WP_Error('missing_fields', __('All fields are required.', 'lus'));
            }

            // Get question for comparison
            $question = $this->get_question($data['question_id']);
            if (!$question) {
                return new WP_Error('invalid_question', __('Invalid question ID.', 'lus'));
            }

            $insert_data = [
                'recording_id' => absint($data['recording_id']),
                'question_id' => absint($data['question_id']),
                'user_answer' => sanitize_text_field($data['user_answer']),
                'is_correct' => isset($data['is_correct']) ? absint($data['is_correct']) : 0,
                'score' => isset($data['score']) ? floatval($data['score']) : 0,
                'similarity' => isset($data['similarity']) ? floatval($data['similarity']) : 0,
                'created_at' => current_time('mysql')
            ];

            $result = $this->db->insert(
                $this->db->prefix . LUS_Constants::TABLE_RESPONSES,
                $insert_data,
                ['%d', '%d', '%s', '%d', '%f', '%f', '%s']
            );

            if ($result === false) {
                throw new Exception($this->db->last_error);
            }

            $response_id = $this->db->insert_id;
            $this->clear_cache('responses_recording_' . $data['recording_id']);

            return $response_id;

        } catch (Exception $e) {
            return new WP_Error('db_error', $e->getMessage());
        }
    }

    /**
     * Get passage assessment statistics
     *
     * @param int         $passage_id  Passage ID
     * @param string|null $date_limit  Optional date limit
     * @return object|null Statistics object
     */
    public function get_passage_assessment_stats(int $passage_id, ?string $date_limit = null): ?object {
        $cache_key = "passage_stats_{$passage_id}" . ($date_limit ? "_" . md5($date_limit) : "");

        return $this->cache_get($cache_key, function() use ($passage_id, $date_limit) {
            $date_filter = '';
            if ($date_limit) {
                $date_filter = $this->db->prepare(
                    "AND r.created_at >= %s",
                    $date_limit
                );
            }

            $query = $this->prepare_query(
                str_replace('{date_filter}', $date_filter, self::SQL['get_passage_stats']),
                [$passage_id]
            );

            $result = $this->db->get_row($query);
            $this->handle_error('get_passage_assessment_stats');

            return $result;
        });
    }

    /**
     * Assign a passage to a user
     *
     * @param int         $passage_id  The ID of the passage to assign
     * @param int         $user_id     The ID of the user receiving the assignment
     * @param int         $assigned_by ID of the user making the assignment
     * @param string|null $due_date    Optional due date for the assignment
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function assign_passage_to_user(
        int $passage_id,
        int $user_id,
        int $assigned_by,
        ?string $due_date = null
    ) {
        try {
            if (empty($passage_id) || empty($user_id) || empty($assigned_by)) {
                return new WP_Error('missing_data', __('Required fields missing', 'lus'));
            }

            // Verify the passage exists
            $passage = $this->get_passage($passage_id);
            if (!$passage) {
                return new WP_Error('invalid_passage', __('Invalid passage ID', 'lus'));
            }

            // Verify the user exists
            $user = get_user_by('id', $user_id);
            if (!$user) {
                return new WP_Error('invalid_user', __('Invalid user ID', 'lus'));
            }

            $insert_data = [
                'passage_id' => $passage_id,
                'user_id' => $user_id,
                'assigned_by' => $assigned_by,
                'assigned_at' => current_time('mysql'),
                'status' => LUS_Constants::STATUS_PENDING
            ];

            $formats = ['%d', '%d', '%d', '%s', '%s'];

            if ($due_date) {
                $insert_data['due_date'] = $due_date;
                $formats[] = '%s';
            }

            $result = $this->db->insert(
                $this->db->prefix . LUS_Constants::TABLE_ASSIGNMENTS,
                $insert_data,
                $formats
            );

            if ($result === false) {
                throw new Exception($this->db->last_error);
            }

            $this->clear_cache('assignments_user_' . $user_id);
            $this->clear_cache('assignments_passage_' . $passage_id);

            return true;

        } catch (Exception $e) {
            return new WP_Error('db_error', $e->getMessage());
        }
    }

    /**
     * Get all assignments with optional filtering
     *
     * @param array $args Query arguments
     * @return array Array of assignment objects
     */
    public function get_all_assignments(array $args = []): array {
        $defaults = [
            'orderby' => 'assigned_at',
            'order' => 'DESC',
            'limit' => LUS_Constants::DEFAULT_PER_PAGE,
            'offset' => 0,
            'status' => '',
            'search' => ''
        ];

        $args = wp_parse_args($args, $defaults);
        $cache_key = 'assignments_' . md5(serialize($args));

        return $this->cache_get($cache_key, function() use ($args) {
            // Build the query
            $where = ['1=1'];
            $where_args = [];

            if ($args['status']) {
                $where[] = 'a.status = %s';
                $where_args[] = $args['status'];
            }

            if ($args['search']) {
                $where[] = '(p.title LIKE %s OR u.display_name LIKE %s)';
                $search_term = '%' . $this->db->esc_like($args['search']) . '%';
                $where_args[] = $search_term;
                $where_args[] = $search_term;
            }

            // Add paging parameters
            $where_args[] = $args['limit'];
            $where_args[] = $args['offset'];

            $query = $this->db->prepare(
                "SELECT a.*, u.display_name AS user_name, p.title AS passage_title
                FROM {$this->db->prefix}" . LUS_Constants::TABLE_ASSIGNMENTS . " a
                LEFT JOIN {$this->db->users} u ON a.user_id = u.ID
                LEFT JOIN {$this->db->prefix}" . LUS_Constants::TABLE_PASSAGES . " p
                    ON a.passage_id = p.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.{$args['orderby']} {$args['order']}
                LIMIT %d OFFSET %d",
                $where_args
            );

            $results = $this->db->get_results($query);
            $this->handle_error('get_all_assignments');

            return $results;
        });
    }







    /**
     * Get total count of recordings
     *
     * @return int Total number of recordings
     */
    public function get_recordings_count(): int {
        return (int)$this->cache_get('recordings_count', function() {
            return $this->db->get_var("
                SELECT COUNT(*)
                FROM {$this->db->prefix}lus_recordings
            ");
        });
    }

    /**
     * Get recordings with optional filtering
     *
     * @param array $args Query arguments
     * @return array Array of recording objects
     * @throws Exception
     */
    public function get_recordings(array $args = []): array {
        $defaults = [
            'user_id' => 0,
            'passage_id' => 0,
            'status' => '',
            'limit' => LUS_Constants::DEFAULT_PER_PAGE,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'with_user' => true,
            'with_assessments' => false
        ];

        $args = wp_parse_args($args, $defaults);
        $cache_key = 'recordings_' . md5(serialize($args));

        return $this->cache_get($cache_key, function() use ($args) {
            $where = [];
            $where_args = [];

            if ($args['user_id']) {
                $where[] = 'r.user_id = %d';
                $where_args[] = $args['user_id'];
            }

            if ($args['passage_id']) {
                $where[] = 'r.passage_id = %d';
                $where_args[] = $args['passage_id'];
            }

            if ($args['status']) {
                $where[] = 'r.status = %s';
                $where_args[] = $args['status'];
            }

            $where_clause = !empty($where) ?
                'AND ' . implode(' AND ', $where) : '';

            // Replace placeholder in query template
            $query = str_replace(
                '{where}',
                $where_clause,
                self::SQL['get_recordings']
            );

            // Add limit/offset to where args
            $where_args[] = $args['limit'];
            $where_args[] = $args['offset'];

            // Prepare and execute query
            $query = $this->prepare_query($query, $where_args);
            $results = $this->db->get_results($query);

            $this->handle_error('get_recordings');

            // Add assessment counts if requested
            if ($args['with_assessments']) {
                foreach ($results as $recording) {
                    $recording->assessment_count = $this->get_assessment_count($recording->id);
                }
            }

            return $results;
        });
    }

}