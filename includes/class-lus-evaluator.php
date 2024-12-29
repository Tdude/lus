<?php
/**
 * File: includes/class-lus-evaluator.php
 * Base evaluator class and interface definition
 */

trait LUS_Evaluator_Base {
    protected $confidence_threshold = 0.9;
    protected $last_confidence_score = 0;

    protected function is_confident(): bool {
        return $this->last_confidence_score >= $this->confidence_threshold;
    }

    protected function format_evaluation_result(float $score, float $confidence, array $details = []): array {
        return [
            'score' => $score,
            'confidence' => $confidence,
            'details' => $details,
            'timestamp' => current_time('mysql'),
            'evaluator_type' => static::class
        ];
    }
}

class LUS_Manual_Evaluator implements LUS_Evaluation_Strategy {
    use LUS_Evaluator_Base;

    public function evaluate(string $response, string $correctAnswer): array {
        $similarity = $this->calculate_similarity($response, $correctAnswer);
        $this->last_confidence_score = 1.0;

        return $this->format_evaluation_result(
            $similarity,
            $this->last_confidence_score,
            ['method' => 'levenshtein']
        );
    }

    public function getConfidence(): float {
        return $this->last_confidence_score;
    }

    public function getName(): string {
        return __('Manual Evaluation', 'lus');
    }

    public function getDescription(): string {
        return __('Manual evaluation of responses using Levenshtein distance', 'lus');
    }

    public function isSuitableFor(string $response, string $correctAnswer): bool {
        return true;
    }

    private function calculate_similarity(string $str1, string $str2): float {
        $str1 = mb_strtolower(trim($str1));
        $str2 = mb_strtolower(trim($str2));

        if ($str1 === $str2) {
            return 100.0;
        }

        $leven = levenshtein($str1, $str2);
        $max_len = max(mb_strlen($str1), mb_strlen($str2));

        return $max_len > 0 ? (1 - ($leven / $max_len)) * 100 : 100.0;
    }
}

class LUS_AI_Evaluator implements LUS_Evaluation_Strategy {
    use LUS_Evaluator_Base;

    private $ai_service;

    public function __construct($ai_service) {
        $this->ai_service = $ai_service;
    }

    public function evaluate(string $response, string $correctAnswer): array {
        // AI evaluation implementation placeholder
        return $this->format_evaluation_result(0, 0, ['status' => 'not_implemented']);
    }

    public function getConfidence(): float {
        return $this->last_confidence_score;
    }

    public function getName(): string {
        return __('AI Evaluation', 'lus');
    }

    public function getDescription(): string {
        return __('AI-based evaluation of responses', 'lus');
    }

    public function isSuitableFor(string $response, string $correctAnswer): bool {
        return false; // Not implemented yet
    }
}