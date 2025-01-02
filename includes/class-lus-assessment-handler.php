<?php
/**
 * Assessment Handler Class
 *
 * Handles assessment processing and management with support for
 * multiple evaluation strategies.
 *
 * @package    LUS
 * @subpackage LUS/includes
 */

class LUS_Assessment_Handler {
    private static $instance = null;

    /** @var LUS_Database */
    private $db;

    /** @var array */
    private $evaluators = [];

    /** @var string */
    private $primary_evaluator = 'manual';

    /** @var array */
    private $weights = [
        'response' => LUS_Constants::TEXT_RESPONSE_WEIGHT,
        'audio' => LUS_Constants::AUDIO_EVALUATION_WEIGHT
    ];

    /** @var float */
    private const MIN_CONFIDENCE_THRESHOLD = LUS_Constants::MIN_CONFIDENCE_THRESHOLD; // Between 0 and 1

    /**
     * Constructor
     *
     * @param LUS_Database $db Database instance
     */
    public function __construct(LUS_Database $db) {
        if (self::$instance !== null) {
            return self::$instance;
        }
        self::$instance = $this;

        $this->db = $db;

        // Register default evaluators
        $this->register_evaluator('manual', new LUS_Manual_Evaluator());
        $this->register_evaluator('levenshtein', new LUS_Levenshtein_Strategy());

        // Register AI evaluator if enabled
        if (get_option('lus_enable_ai_evaluation')) {
            $ai_service = $this->get_ai_service();
            $this->register_evaluator('ai', new LUS_AI_Evaluator($ai_service));
        }
    }

    /**
     * Register an evaluator
     *
     * @param string                 $type      Evaluator type
     * @param LUS_Evaluation_Strategy $evaluator Evaluator instance
     */
    public function register_evaluator(string $type, LUS_Evaluation_Strategy $evaluator): void {
        $this->evaluators[$type] = $evaluator;
    }

    /**
     * Set primary evaluator
     *
     * @param string $type Evaluator type
     * @return bool Whether the evaluator was set
     */
    public function set_primary_evaluator(string $type): bool {
        if (isset($this->evaluators[$type])) {
            $this->primary_evaluator = $type;
            return true;
        }
        return false;
    }

    /**
     * Process a complete assessment
     *
     * @param int   $recording_id    Recording ID
     * @param array $evaluation_types Optional evaluator types to use
     * @return array Assessment results
     */
    public function process_assessment(int $recording_id, array $evaluation_types = null): array {
        try {
            return $this->db->transaction(function() use ($recording_id, $evaluation_types) {
                // Get recording and responses
                $recording = $this->db->get_recording($recording_id);
                $responses = $this->db->get_recording_responses($recording_id);

                if (!$recording || empty($responses)) {
                    throw new Exception(__('Invalid recording or no responses found', 'lus'));
                }

                // Determine which evaluators to use
                $types_to_use = $evaluation_types ?? [$this->primary_evaluator];
                $assessment_results = [];

                // Process text responses
                foreach ($responses as $response) {
                    foreach ($types_to_use as $evaluator_type) {
                        if (!isset($this->evaluators[$evaluator_type])) {
                            continue;
                        }

                        $evaluator = $this->evaluators[$evaluator_type];

                        // Check if evaluator is suitable
                        if (!$evaluator->isSuitableFor($response->user_answer, $response->correct_answer)) {
                            continue;
                        }

                        // Evaluate response
                        $evaluation = $evaluator->evaluate(
                            $response->user_answer,
                            $response->correct_answer
                        );

                        // Store evaluation result
                        $this->save_evaluation([
                            'recording_id' => $recording_id,
                            'response_id' => $response->id,
                            'evaluator_type' => $evaluator_type,
                            'score' => $evaluation['score'],
                            'confidence' => $evaluation['confidence'],
                            'details' => $evaluation['details']
                        ]);

                        // Aggregate results
                        $this->aggregate_results(
                            $assessment_results,
                            $evaluator_type,
                            $response,
                            $evaluation
                        );
                    }
                }

                // Process audio recording if available
                foreach ($types_to_use as $evaluator_type) {
                    $evaluator = $this->evaluators[$evaluator_type] ?? null;

                    if ($evaluator && method_exists($evaluator, 'evaluate_recording')) {
                        $passage = $this->db->get_passage($recording->passage_id);

                        if ($passage) {
                            $audio_evaluation = $evaluator->evaluate_recording(
                                $recording->audio_file_path,
                                $passage->content
                            );

                            if ($audio_evaluation) {
                                $this->save_evaluation([
                                    'recording_id' => $recording_id,
                                    'evaluator_type' => $evaluator_type,
                                    'score' => $audio_evaluation['score'],
                                    'confidence' => $audio_evaluation['confidence'],
                                    'details' => $audio_evaluation['details'],
                                    'evaluation_type' => 'audio'
                                ]);

                                $this->aggregate_audio_results(
                                    $assessment_results,
                                    $evaluator_type,
                                    $audio_evaluation
                                );
                            }
                        }
                    }
                }

                // Calculate final scores using primary evaluator's results
                if (isset($assessment_results[$this->primary_evaluator])) {
                    $primary_results = $assessment_results[$this->primary_evaluator];

                    $text_score = $primary_results['total_weight'] > 0
                        ? ($primary_results['total_score'] / $primary_results['total_weight']) * 100
                        : 0;

                    $audio_score = $primary_results['audio_score'] ?? 0;

                    // Weight the scores
                    $final_score = ($text_score * $this->weights['response']) +
                                 ($audio_score * $this->weights['audio']);

                    // Calculate confidence
                    $confidence = ($primary_results['confidence_sum'] / $primary_results['response_count'])
                              * ($primary_results['audio_confidence'] ?? 1.0);

                    // Save final assessment
                    $assessment_data = [
                        'recording_id' => $recording_id,
                        'total_score' => $final_score,
                        'normalized_score' => $final_score,
                        'confidence_score' => $confidence,
                        'evaluator_type' => $this->primary_evaluator
                    ];

                    $assessment_id = $this->db->save_assessment($assessment_data);

                    if (is_wp_error($assessment_id)) {
                        throw new Exception($assessment_id->get_error_message());
                    }

                    // Update recording status if confidence meets threshold
                    if ($confidence >= self::MIN_CONFIDENCE_THRESHOLD) {
                        $this->db->update_recording($recording_id, [
                            'status' => LUS_Constants::STATUS_ASSESSED // 'assessed'
                        ]);
                    }

                    return [
                        'success' => true,
                        'assessment_id' => $assessment_id,
                        'results' => $assessment_results,
                        'final_score' => $final_score,
                        'confidence' => $confidence
                    ];
                }

                throw new Exception(__('No valid assessment results', 'lus'));
            });

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Save evaluation result
     *
     * @param array $data Evaluation data
     * @return int|WP_Error Evaluation ID or error
     */
    private function save_evaluation(array $data) {
        try {
            $insert_data = [
                'recording_id' => $data['recording_id'],
                'response_id' => $data['response_id'] ?? null,
                'evaluator_type' => $data['evaluator_type'],
                'score' => $data['score'],
                'confidence' => $data['confidence'],
                'details' => maybe_serialize($data['details']),
                'evaluation_type' => $data['evaluation_type'] ?? 'text',
                'created_at' => current_time('mysql')
            ];

            $result = $this->db->insert(
                $this->db->prefix . LUS_Constants::TABLE_EVALUATIONS,
                $insert_data,
                ['%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s']
            );

            if ($result === false) {
                throw new Exception($this->db->last_error);
            }

            return $this->db->insert_id;

        } catch (Exception $e) {
            return new WP_Error('evaluation_error', $e->getMessage());
        }
    }

    /**
     * Aggregate text response results
     *
     * @param array  $results    Results array
     * @param string $type       Evaluator type
     * @param object $response   Response object
     * @param array  $evaluation Evaluation result
     */
    private function aggregate_results(
        array &$results,
        string $type,
        object $response,
        array $evaluation
    ): void {
        if (!isset($results[$type])) {
            $results[$type] = [
                'total_score' => 0,
                'total_weight' => 0,
                'confidence_sum' => 0,
                'response_count' => 0
            ];
        }

        $results[$type]['total_score'] += $evaluation['score'] * $response->weight;
        $results[$type]['total_weight'] += $response->weight;
        $results[$type]['confidence_sum'] += $evaluation['confidence'];
        $results[$type]['response_count']++;
    }

    /**
     * Aggregate audio evaluation results
     *
     * @param array  $results    Results array
     * @param string $type       Evaluator type
     * @param array  $evaluation Audio evaluation result
     */
    private function aggregate_audio_results(
        array &$results,
        string $type,
        array $evaluation
    ): void {
        if (!isset($results[$type])) {
            $results[$type] = [
                'total_score' => 0,
                'total_weight' => 0,
                'confidence_sum' => 0,
                'response_count' => 0
            ];
        }

        $results[$type]['audio_score'] = $evaluation['score'];
        $results[$type]['audio_confidence'] = $evaluation['confidence'];
    }

    /**
     * Get assessment details
     *
     * @param int $assessment_id Assessment ID
     * @return array|null Assessment details or null if not found
     */
    public function get_assessment_details(int $assessment_id): ?array {
        $assessment = $this->db->get_assessment($assessment_id);
        if (!$assessment) {
            return null;
        }

        // Get all evaluations for this recording
        $evaluations = $this->db->get_evaluations_for_recording($assessment->recording_id);

        $details = [
            'assessment' => $assessment,
            'evaluations' => [
                'responses' => [],
                'audio' => []
            ]
        ];

        foreach ($evaluations as $eval) {
            if ($eval->response_id) {
                $details['evaluations']['responses'][$eval->response_id][$eval->evaluator_type] = $eval;
            } else {
                $details['evaluations']['audio'][$eval->evaluator_type] = $eval;
            }
        }

        return $details;
    }

    /**
     * Get AI service instance
     *
     * @return object AI service instance
     */
    private function get_ai_service() {
        // Implementation for AI service initialization
        // This would be replaced with actual AI service configuration
        return new stdClass();
    }
}