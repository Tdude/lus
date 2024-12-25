<?php
/**
 * Levenshtein Evaluation Strategy
 *
 * @package    LUS
 * @subpackage LUS/includes/strategy
 */

class LUS_Levenshtein_Strategy implements LUS_Evaluation_Strategy {
    /** @var float */
    private $lastConfidence = 0.0;

    /** @var array */
    private $config = [
        'min_length' => 3,           // Minimum text length for reliable comparison
        'max_length' => 1000,        // Maximum text length for performance
        'min_confidence' => 0.5,     // Minimum confidence score
        'length_weight' => 0.3,      // Weight for length similarity
        'case_weight' => 0.1,        // Weight for case matching
        'exact_weight' => 0.2,       // Weight for exact matches
        'leven_weight' => 0.4,       // Weight for Levenshtein similarity
    ];

    /**
     * Evaluate response against correct answer
     *
     * @param string $response Student's response
     * @param string $correctAnswer Correct answer to compare against
     * @return array Evaluation result
     */
    public function evaluate(string $response, string $correctAnswer): array {
        // Initialize scores
        $scores = [
            'length' => 0,
            'case' => 0,
            'exact' => 0,
            'levenshtein' => 0
        ];

        // Clean and normalize texts
        $cleanResponse = $this->normalizeText($response);
        $cleanAnswer = $this->normalizeText($correctAnswer);

        // Calculate individual scores
        $scores['length'] = $this->calculateLengthScore($cleanResponse, $cleanAnswer);
        $scores['case'] = $this->calculateCaseScore($response, $correctAnswer);
        $scores['exact'] = $this->calculateExactScore($cleanResponse, $cleanAnswer);
        $scores['levenshtein'] = $this->calculateLevenshteinScore($cleanResponse, $cleanAnswer);

        // Calculate weighted final score
        $finalScore =
            ($scores['length'] * $this->config['length_weight']) +
            ($scores['case'] * $this->config['case_weight']) +
            ($scores['exact'] * $this->config['exact_weight']) +
            ($scores['levenshtein'] * $this->config['leven_weight']);

        // Calculate confidence
        $this->lastConfidence = $this->calculateConfidence($cleanResponse, $cleanAnswer);

        // Prepare response
        return [
            'score' => round($finalScore, 2),
            'similarity' => round($scores['levenshtein'], 2),
            'confidence' => $this->lastConfidence,
            'details' => [
                'scores' => $scores,
                'weights' => array_intersect_key($this->config, array_flip([
                    'length_weight',
                    'case_weight',
                    'exact_weight',
                    'leven_weight'
                ])),
                'normalized' => [
                    'response' => $cleanResponse,
                    'answer' => $cleanAnswer
                ]
            ]
        ];
    }

    /**
     * Calculate length-based similarity score
     *
     * @param string $response Normalized response
     * @param string $answer Normalized answer
     * @return float Score (0-100)
     */
    private function calculateLengthScore(string $response, string $answer): float {
        $lenResponse = mb_strlen($response);
        $lenAnswer = mb_strlen($answer);

        if ($lenAnswer === 0) {
            return $lenResponse === 0 ? 100 : 0;
        }

        return 100 * (1 - abs($lenResponse - $lenAnswer) / max($lenResponse, $lenAnswer));
    }

    /**
     * Calculate case-matching score
     *
     * @param string $response Original response
     * @param string $answer Original answer
     * @return float Score (0-100)
     */
    private function calculateCaseScore(string $response, string $answer): float {
        $matches = similar_text($response, $answer);
        $maxLen = max(mb_strlen($response), mb_strlen($answer));

        return $maxLen > 0 ? (100 * $matches / $maxLen) : 100;
    }

    /**
     * Calculate exact matching score
     *
     * @param string $response Normalized response
     * @param string $answer Normalized answer
     * @return float Score (0-100)
     */
    private function calculateExactScore(string $response, string $answer): float {
        return $response === $answer ? 100 : 0;
    }

    /**
     * Calculate Levenshtein-based similarity score
     *
     * @param string $response Normalized response
     * @param string $answer Normalized answer
     * @return float Score (0-100)
     */
    private function calculateLevenshteinScore(string $response, string $answer): float {
        if (empty($response) && empty($answer)) {
            return 100;
        }

        if (empty($response) || empty($answer)) {
            return 0;
        }

        $maxLen = max(mb_strlen($response), mb_strlen($answer));
        $levenDist = levenshtein($response, $answer);

        return 100 * (1 - ($levenDist / $maxLen));
    }

    /**
     * Calculate confidence score
     *
     * @param string $response Normalized response
     * @param string $answer Normalized answer
     * @return float Confidence (0-1)
     */
    private function calculateConfidence(string $response, string $answer): float {
        // Base confidence on text lengths
        $lenResponse = mb_strlen($response);
        $lenAnswer = mb_strlen($answer);

        if ($lenResponse < $this->config['min_length'] ||
            $lenAnswer < $this->config['min_length']) {
            return $this->config['min_confidence'];
        }

        if ($lenResponse > $this->config['max_length'] ||
            $lenAnswer > $this->config['max_length']) {
            return $this->config['min_confidence'];
        }

        // Higher confidence for more similar lengths
        $lengthRatio = min($lenResponse, $lenAnswer) / max($lenResponse, $lenAnswer);

        // Scale confidence between min_confidence and 1.0
        return $this->config['min_confidence'] +
               ((1.0 - $this->config['min_confidence']) * $lengthRatio);
    }

    /**
     * Normalize text for comparison
     *
     * @param string $text Text to normalize
     * @return string Normalized text
     */
    private function normalizeText(string $text): string {
        // Convert to lowercase
        $text = mb_strtolower($text);

        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));

        // Remove punctuation
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);

        return $text;
    }

    /**
     * Get last confidence score
     *
     * @return float
     */
    public function getConfidence(): float {
        return $this->lastConfidence;
    }

    /**
     * Get strategy name
     *
     * @return string
     */
    public function getName(): string {
        return __('Levenshtein Distance', 'lus');
    }

    /**
     * Get strategy description
     *
     * @return string
     */
    public function getDescription(): string {
        return __('Jämför texter baserat på Levenshtein-avstånd med viktad scoring.', 'lus');
    }

    /**
     * Check if suitable for texts
     *
     * @param string $response Response to check
     * @param string $correctAnswer Answer to check
     * @return bool
     */
    public function isSuitableFor(string $response, string $correctAnswer): bool {
        $lenResponse = mb_strlen($response);
        $lenAnswer = mb_strlen($correctAnswer);

        return
            $lenResponse >= $this->config['min_length'] &&
            $lenAnswer >= $this->config['min_length'] &&
            $lenResponse <= $this->config['max_length'] &&
            $lenAnswer <= $this->config['max_length'];
    }
}