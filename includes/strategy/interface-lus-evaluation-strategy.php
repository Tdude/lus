<?php
/**
 * Evaluation Strategy Interface
 *
 * @package    LUS
 * @subpackage LUS/includes/strategy
 */

interface LUS_Evaluation_Strategy {
    /**
     * Evaluate a response against a correct answer
     * See also class-lus-evaluator.php and class-lus-assessment-handler.php
     * @param string $response Student's response
     * @param string $correctAnswer Correct answer to compare against
     * @return array Evaluation result with keys:
     *               - score: float (0-100)
     *               - similarity: float (0-100)
     *               - confidence: float (0-1)
     *               - details: array (additional evaluation details)
     */
     public function evaluate(string $response, string $correctAnswer): array;

    /**
     * Get the confidence score for the last evaluation
     *
     * @return float Confidence score (0-1)
     */
    public function getConfidence(): float;

    /**
     * Get the name of the strategy
     *
     * @return string Strategy name
     */
    public function getName(): string;

    /**
     * Get the description of the strategy
     *
     * @return string Strategy description
     */
    public function getDescription(): string;

    /**
     * Check if this strategy is suitable for a given type of answer
     *
     * @param string $response Response to check
     * @param string $correctAnswer Correct answer to check
     * @return bool Whether this strategy is suitable
     */
    public function isSuitableFor(string $response, string $correctAnswer): bool;
}