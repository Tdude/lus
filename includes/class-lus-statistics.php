<?php
/**
 * File: includes/class-lus-statistics.php
 * Make stats
 *
 * @package    LUS
 * @subpackage LUS/includes
 */

class LUS_Statistics {
    private static $instance = null;
    private $db;

    public function __construct($db) {
        if (self::$instance !== null) {
            return self::$instance;
        }
        self::$instance = $this;
        $this->db = $db;
    }

    /**
     * Get aggregate statistics across all recordings including student participation and performance metrics
     *
     * @param string $date_limit  Optional. Date string to filter results after a specific date. Default empty string.
     * @param int    $passage_id  Optional. Filter statistics for a specific passage. Default 0.
     *
     * @return array Statistics with the following keys:
     *               'total_recordings'      - (int)   Total number of unique recordings
     *               'unique_students'       - (int)   Number of distinct students who made recordings
     *               'avg_normalized_score'  - (float) Average normalized assessment score
     *               'total_questions_answered' - (int) Total number of questions answered
     *               'correct_answer_rate'   - (float) Percentage of correct answers (0-100)
     */
    public function get_overall_statistics($passage_id = 0, $date_limit = '') {
        global $wpdb;

        $where = [];
        $args = [];

        if (!empty($date_limit) && strtotime($date_limit)) {
            $where[] = 'r.created_at >= %s';
            $args[] = date('Y-m-d H:i:s', strtotime($date_limit));
        }

        if ($passage_id) {
            $where[] = 'r.passage_id = %d';
            $args[] = $passage_id;
        }

        $query = "SELECT
            COUNT(DISTINCT r.id) as total_recordings,
            COUNT(DISTINCT r.user_id) as unique_students,
            ROUND(AVG(NULLIF(a.normalized_score, 0)), 1) as avg_normalized_score,
            COUNT(resp.id) as total_questions_answered,
            ROUND(
                (SUM(CASE WHEN resp.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 /
                NULLIF(COUNT(resp.id), 0)),
                1
            ) as correct_answer_rate
            FROM {$wpdb->prefix}lus_recordings r
            LEFT JOIN {$wpdb->prefix}lus_assessments a ON r.id = a.recording_id
            LEFT JOIN {$wpdb->prefix}lus_responses resp ON r.id = resp.recording_id";

        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }

        if (!empty($args)) {
            $query = $wpdb->prepare($query, $args);
        }

        return $wpdb->get_row($query, ARRAY_A);
    }


    /**
     * Get statistics for a specific passage
     *
     * @param int    $passage_id ID of the passage
     * @param string $date_limit Optional. Date string to filter results. Default empty string.
     * @return array Statistics including recording counts and scores
     */
    public function get_passage_statistics($passage_id, $date_limit = '') {
        global $wpdb;

        $query = "SELECT
            p.title,
            COUNT(DISTINCT r.id) as total_attempts,
            ROUND(AVG(NULLIF(a.normalized_score, 0)), 1) as average_score,
            ROUND(AVG(NULLIF(r.duration, 0)), 1) as avg_duration,
            ROUND(
                (SUM(CASE WHEN resp.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 /
                NULLIF(COUNT(resp.id), 0)),
                1
            ) as correct_answer_rate
            FROM {$wpdb->prefix}lus_passages p
            LEFT JOIN {$wpdb->prefix}lus_recordings r ON p.id = r.passage_id
            LEFT JOIN {$wpdb->prefix}lus_assessments a ON r.id = a.recording_id
            LEFT JOIN {$wpdb->prefix}lus_responses resp ON r.id = resp.recording_id
            WHERE p.id = %d";

        $args = [$passage_id];

        if ($date_limit) {
            $query .= " AND r.created_at >= %s";
            $args[] = $date_limit;
        }

        $query .= " GROUP BY p.id, p.title";

        $result = $wpdb->get_row(
            $wpdb->prepare($query, $args),
            OBJECT
        );

        return $result ?: (object)[
            'title' => '',
            'total_attempts' => 0,
            'average_score' => 0,
            'avg_duration' => 0,
            'correct_answer_rate' => 0
        ];
    }




    /**
     * Get the total number of unique recordings for a specific passage
     *
     * @param int    $passage_id ID of the passage to count recordings for
     * @param string $date_limit Optional. Date string to filter recordings after a specific date. Default empty string.
     * @return int   Number of unique recordings for the passage
     */
    public function get_passage_recording_count($passage_id, $date_limit = '') {
        global $wpdb;

        $query = "SELECT COUNT(DISTINCT r.id)
            FROM {$wpdb->prefix}lus_recordings r
            WHERE r.passage_id = %d";

        $args = [$passage_id];

        if ($date_limit) {
            $query .= " AND r.created_at >= %s";
            $args[] = $date_limit;
        }

        return (int)$wpdb->get_var(
            $wpdb->prepare($query, $args)
        );
    }

    /**
     * Retrieves detailed statistics about questions, including answer rates and performance metrics.
     *
     * @param string $date_limit Optional. Date string to filter results after a specific date. Default empty string.
     * @param int    $passage_id Optional. Filter statistics for a specific passage. Default 0.
     *
     * @return array[] Array of statistics with the following keys:
     *                 'question_text'  - (string) The text of the question
     *                 'passage_title'  - (string) Title of the passage the question belongs to
     *                 'times_answered' - (int) Number of times the question was answered
     *                 'correct_rate'   - (float) Percentage of correct answers (0-100)
     *                 'avg_similarity' - (float) Average similarity score for responses
     *                 'first_answer'   - (string) Timestamp of the first answer
     *                 'last_answer'    - (string) Timestamp of the most recent answer
     *
     * @global wpdb $wpdb WordPress database abstraction object
     */
    public function get_question_statistics($passage_id = 0, $date_limit = '') {
        global $wpdb;

        $where = [];
        $args = [];

        if (!empty($date_limit) && strtotime($date_limit)) {
            $where[] = 'r.created_at >= %s';
            $args[] = date('Y-m-d H:i:s', strtotime($date_limit));
        }

        if ($passage_id) {
            $where[] = 'r.passage_id = %d';
            $args[] = $passage_id;
        }

        $query = "
            SELECT
                q.question_text,
                p.title AS passage_title,
                COUNT(resp.id) AS times_answered,
                ROUND(
                    (SUM(CASE WHEN resp.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 /
                    NULLIF(COUNT(resp.id), 0)),
                    1
                ) AS correct_rate,
                ROUND(AVG(NULLIF(resp.score, 0)), 1) AS avg_similarity,
                MIN(resp.created_at) AS first_answer,
                MAX(resp.created_at) AS last_answer
            FROM {$wpdb->prefix}lus_questions q
            JOIN {$wpdb->prefix}lus_passages p ON q.passage_id = p.id
            JOIN {$wpdb->prefix}lus_responses resp ON q.id = resp.question_id
            JOIN {$wpdb->prefix}lus_recordings r ON resp.recording_id = r.id
        ";

        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }

        $query .= " GROUP BY q.id, q.question_text, p.title ORDER BY correct_rate DESC";

        // Prepare query with arguments
        if (!empty($args)) {
            $query = $wpdb->prepare($query, ...$args);
        }

        return $wpdb->get_results($query, ARRAY_A);
    }


    /**
     * Retrieves student progress data including recording details and assessment scores
     *
     * @param int|null $user_id Optional. Filter progress for a specific user. Default null.
     * @param int      $limit   Optional. Maximum number of records to return. Default 10.
     *
     * @return array[] Array of progress records with the following keys:
     *                 'user_id'          - (int)    ID of the user
     *                 'display_name'     - (string) User's display name
     *                 'passage_title'    - (string) Title of the recorded passage
     *                 'normalized_score' - (float)  Normalized assessment score
     *                 'created_at'       - (string) Recording timestamp
     */
    public function get_student_progress($user_id = null, $limit = 10) {
        global $wpdb;

        $query = "SELECT
            r.user_id,
            u.display_name,
            p.title as passage_title,
            ROUND(a.normalized_score, 1) as normalized_score,
            r.created_at
            FROM {$wpdb->prefix}lus_recordings r
            JOIN {$wpdb->users} u ON r.user_id = u.ID
            JOIN {$wpdb->prefix}lus_passages p ON r.passage_id = p.id
            LEFT JOIN {$wpdb->prefix}lus_assessments a ON r.id = a.recording_id";

        $args = [$limit];

        if ($user_id) {
            $query .= " WHERE r.user_id = %d";
            array_unshift($args, $user_id);
        }

        $query .= " ORDER BY r.created_at DESC LIMIT %d";

        if (!empty($args)) {
            return $wpdb->get_results(
                $wpdb->prepare($query, $args),
                OBJECT
            );
        }
    }

    /**
     * Retrieves all active passages for filtering purposes
     *
     * @return object[] Array of passage objects with 'id' and 'title' properties
     */
    public function get_all_passages() {
        global $wpdb;

        // Don't use prepare() when there are no variables to escape
        return $wpdb->get_results(
            "SELECT id, title
            FROM {$wpdb->prefix}lus_passages
            WHERE deleted_at IS NULL
            ORDER BY title ASC",
            OBJECT
        );
    }

    /**
     * Retrieves aggregated statistics grouped by time periods
     *
     * @param string $interval Optional. Time grouping interval ('day', 'week', or 'month'). Default 'day'.
     * @param int    $limit    Optional. Maximum number of periods to return. Default 30.
     *
     * @return array[] Array of statistics with the following keys:
     *                 'period'         - (string) Time period identifier
     *                 'recording_count' - (int)   Number of recordings in the period
     *                 'unique_users'    - (int)   Number of distinct users
     *                 'avg_score'       - (float) Average normalized score
     *                 'avg_duration'    - (float) Average recording duration
     */
    public function get_time_stats($interval = 'day', $limit = 30) {
        global $wpdb;

        // Validate and sanitize interval
        $group_by = match($interval) {
            'week'  => "YEARWEEK(r.created_at)",
            'month' => "DATE_FORMAT(r.created_at, '%Y-%m')",
            default => "DATE(r.created_at)"
        };

        $query = "SELECT
            {$group_by} as period,
            COUNT(DISTINCT r.id) as recording_count,
            COUNT(DISTINCT r.user_id) as unique_users,
            ROUND(AVG(NULLIF(a.normalized_score, 0)), 1) as avg_score,
            ROUND(AVG(NULLIF(r.duration, 0)), 1) as avg_duration
            FROM {$wpdb->prefix}lus_recordings r
            LEFT JOIN {$wpdb->prefix}lus_assessments a ON r.id = a.recording_id
            GROUP BY period
            ORDER BY period DESC
            LIMIT " . absint($limit);

        return $wpdb->get_results($query, OBJECT);
    }

    /**
     * Get difficulty level statistics
     */
    public function get_difficulty_stats() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT
                p.difficulty_level,
                COUNT(DISTINCT r.id) as recording_count,
                AVG(a.normalized_score) as avg_score,
                MIN(a.normalized_score) as min_score,
                MAX(a.normalized_score) as max_score
            FROM {$wpdb->prefix}lus_passages p
            JOIN {$wpdb->prefix}lus_recordings r ON p.id = r.passage_id
            LEFT JOIN {$wpdb->prefix}lus_assessments a ON r.id = a.recording_id
            GROUP BY p.difficulty_level
            ORDER BY p.difficulty_level ASC",
            OBJECT
        );
    }
}