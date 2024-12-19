<?php
class LUS_Assessment_Handler {
    private $db;
    private $evaluators = [];
    private $primary_evaluator = 'manual'; // Default to manual evaluation

    public function __construct($db) {
        $this->db = $db;
        // Register default manual evaluator
        $this->register_evaluator('manual', new LUS_Manual_Evaluator());
    }

    /**
     * Register a new evaluator
     */
    public function register_evaluator($type, LUS_Evaluator_Interface $evaluator) {
        $this->evaluators[$type] = $evaluator;
    }

    /**
     * Set primary evaluator type
     */
    public function set_primary_evaluator($type) {
        if (isset($this->evaluators[$type])) {
            $this->primary_evaluator = $type;
            return true;
        }
        return false;
    }


    /**
     * Evaluation system init
    */
    private function init_assessment_system() {
        $assessment_handler = new LUS_Assessment_Handler($this->db);

        // Register default evaluator
        $assessment_handler->register_evaluator('manual', new LUS_Manual_Evaluator());

        // Optionally register AI evaluator if configured
        if (get_option('lus_ai_evaluation_enabled')) {
            $ai_service = $this->get_ai_service(); // Your AI service configuration
            $assessment_handler->register_evaluator('ai', new LUS_AI_Evaluator($ai_service));
        }

        return $assessment_handler;
    }

    /**
     * Process a complete assessment
     */
    public function process_assessment($recording_id, $evaluation_types = null) {
        try {
            // Start transaction
            $this->db->begin_transaction();

            // Get recording and responses
            $recording = $this->db->get_recording($recording_id);
            $responses = $this->db->get_recording_responses($recording_id);

            if (!$recording || empty($responses)) {
                throw new Exception(__('Invalid recording or no responses found', 'lus'));
            }

            // Determine which evaluators to use
            $types_to_use = $evaluation_types ?? [$this->primary_evaluator];

            $assessment_results = [];

            // Process each response with each evaluator
            foreach ($responses as $response) {
                foreach ($types_to_use as $evaluator_type) {
                    if (!isset($this->evaluators[$evaluator_type])) {
                        continue;
                    }

                    $evaluator = $this->evaluators[$evaluator_type];

                    // Evaluate text response
                    $response_evaluation = $evaluator->evaluate_response(
                        $response->user_answer,
                        $response->correct_answer
                    );

                    // Save evaluation result
                    $this->db->save_evaluation([
                        'recording_id' => $recording_id,
                        'response_id' => $response->id,
                        'evaluator_type' => $evaluator_type,
                        'score' => $response_evaluation['score'],
                        'confidence' => $response_evaluation['confidence'],
                        'details' => $response_evaluation['details']
                    ]);

                    // Aggregate results
                    if (!isset($assessment_results[$evaluator_type])) {
                        $assessment_results[$evaluator_type] = [
                            'total_score' => 0,
                            'total_weight' => 0,
                            'confidence_sum' => 0,
                            'response_count' => 0
                        ];
                    }

                    $assessment_results[$evaluator_type]['total_score'] += $response_evaluation['score'] * $response->weight;
                    $assessment_results[$evaluator_type]['total_weight'] += $response->weight;
                    $assessment_results[$evaluator_type]['confidence_sum'] += $response_evaluation['confidence'];
                    $assessment_results[$evaluator_type]['response_count']++;
                }
            }

            // Process audio recording if available
            foreach ($types_to_use as $evaluator_type) {
                if (isset($this->evaluators[$evaluator_type])) {
                    $evaluator = $this->evaluators[$evaluator_type];

                    // Only evaluate audio if the evaluator supports it
                    if (method_exists($evaluator, 'evaluate_recording')) {
                        $audio_evaluation = $evaluator->evaluate_recording(
                            $recording->audio_file_path,
                            $this->db->get_passage($recording->passage_id)->content
                        );

                        if ($audio_evaluation) {
                            $this->db->save_evaluation([
                                'recording_id' => $recording_id,
                                'evaluator_type' => $evaluator_type,
                                'score' => $audio_evaluation['score'],
                                'confidence' => $audio_evaluation['confidence'],
                                'details' => $audio_evaluation['details'],
                                'evaluation_type' => 'audio'
                            ]);
                        }
                    }
                }
            }

            // Save final assessment using primary evaluator's results
            if (isset($assessment_results[$this->primary_evaluator])) {
                $primary_results = $assessment_results[$this->primary_evaluator];
                $normalized_score = $primary_results['total_weight'] > 0
                    ? ($primary_results['total_score'] / $primary_results['total_weight']) * 100
                    : 0;

                $assessment_data = [
                    'recording_id' => $recording_id,
                    'total_score' => $primary_results['total_score'],
                    'normalized_score' => $normalized_score,
                    'confidence_score' => $primary_results['confidence_sum'] / $primary_results['response_count'],
                    'evaluator_type' => $this->primary_evaluator
                ];

                $assessment_id = $this->db->save_assessment($assessment_data);
            }

            $this->db->commit();
            return [
                'success' => true,
                'assessment_id' => $assessment_id ?? null,
                'results' => $assessment_results
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get assessment details including all evaluations
     */
    public function get_assessment_details($assessment_id) {
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
}