<?php
/**
 * File: includes/class-lus-evaluator.php
 * Base evaluator class and interface definition
 */

 interface LUS_Evaluator_Interface {
    public function evaluate_response($response_text, $correct_answer);
    public function evaluate_recording($audio_path, $passage_text);
    public function get_confidence_score();
}

trait LUS_Evaluator_Base {
    protected $confidence_threshold = 0.9;
    protected $last_confidence_score = 0;

    protected function is_confident() {
        return $this->last_confidence_score >= $this->confidence_threshold;
    }

    protected function format_evaluation_result($score, $confidence, $details = []) {
        return [
            'score' => $score,
            'confidence' => $confidence,
            'details' => $details,
            'timestamp' => current_time('mysql'),
            'evaluator_type' => static::class
        ];
    }
}

class LUS_Manual_Evaluator implements LUS_Evaluator_Interface {
    use LUS_Evaluator_Base;

    public function evaluate_response($response_text, $correct_answer) {
        // Calculate similarity using Levenshtein distance
        $similarity = $this->calculate_similarity($response_text, $correct_answer);
        $this->last_confidence_score = 1.0; // Manual evaluation is always confident

        return $this->format_evaluation_result(
            $similarity,
            $this->last_confidence_score,
            ['method' => 'levenshtein']
        );
    }

    public function evaluate_recording($audio_path, $passage_text) {
        // Manual evaluator doesn't evaluate recordings
        return null;
    }

    public function get_confidence_score() {
        return $this->last_confidence_score;
    }

    private function calculate_similarity($str1, $str2) {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));

        if ($str1 === $str2) {
            return 100;
        }

        $leven = levenshtein($str1, $str2);
        $max_len = max(strlen($str1), strlen($str2));

        return $max_len > 0 ? (1 - ($leven / $max_len)) * 100 : 100;
    }
}

class LUS_AI_Evaluator implements LUS_Evaluator_Interface {
    use LUS_Evaluator_Base;

    private $ai_service;

    public function __construct($ai_service) {
        $this->ai_service = $ai_service;
    }

    public function evaluate_response($response_text, $correct_answer) {
        // Implement AI-based evaluation when ready
        return null;
    }

    public function evaluate_recording($audio_path, $passage_text) {
        // Implement AI-based audio evaluation when ready
        return null;
    }

    public function get_confidence_score() {
        return $this->last_confidence_score;
    }
}