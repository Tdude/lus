<?php
/**
 * Score Value Object
 *
 * @package    LUS
 * @subpackage LUS/includes/value-objects
 */

class LUS_Score {
    /** @var float */
    private $value;

    /** @var float */
    private const MIN_SCORE = 0.0;

    /** @var float */
    private const MAX_SCORE = 100.0;

    /**
     * Constructor
     *
     * @param float $value Score value (0-100)
     * @throws InvalidArgumentException If score is invalid
     */
    private function __construct(float $value) {
        $this->validate($value);
        $this->value = $value;
    }

    /**
     * Create from raw value
     *
     * @param float|int $value Raw score value
     * @return self
     */
    public static function fromValue($value): self {
        return new self((float) $value);
    }

    /**
     * Create from percentage
     *
     * @param float $percentage Percentage value (0-100)
     * @return self
     */
    public static function fromPercentage(float $percentage): self {
        return new self($percentage);
    }

    /**
     * Create from ratio
     *
     * @param float $correct Number of correct answers
     * @param float $total Total number of questions
     * @return self
     * @throws InvalidArgumentException If total is 0 or parameters are invalid
     */
    public static function fromRatio(float $correct, float $total): self {
        if ($total <= 0) {
            throw new InvalidArgumentException(__('Total must be greater than zero', 'lus'));
        }
        if ($correct < 0 || $correct > $total) {
            throw new InvalidArgumentException(__('Invalid number of correct answers', 'lus'));
        }
        return new self(($correct / $total) * 100);
    }

    /**
     * Validate score value
     *
     * @param float $value Value to validate
     * @throws InvalidArgumentException If value is invalid
     */
    private function validate(float $value): void {
        if ($value < self::MIN_SCORE || $value > self::MAX_SCORE) {
            throw new InvalidArgumentException(
                sprintf(__('Score must be between %1$s and %2$s', 'lus'), self::MIN_SCORE, self::MAX_SCORE)
            );
        }
    }

    /**
     * Get raw score value
     *
     * @return float
     */
    public function getValue(): float {
        return $this->value;
    }

    /**
     * Get score as percentage
     *
     * @return float
     */
    public function asPercentage(): float {
        return $this->value;
    }

    /**
     * Get score as ratio (0-1)
     *
     * @return float
     */
    public function asRatio(): float {
        return $this->value / 100;
    }

    /**
     * Get letter grade based on score
     *
     * @return string
     */
    public function getLetterGrade(): string {
        if ($this->value >= 90) return 'A';
        if ($this->value >= 80) return 'B';
        if ($this->value >= 70) return 'C';
        if ($this->value >= 60) return 'D';
        return 'F';
    }

    /**
     * Format score with specified precision
     *
     * @param int $precision Number of decimal places
     * @return string
     */
    public function format(int $precision = 1): string {
        return number_format($this->value, $precision);
    }

    /**
     * Format score with percentage symbol
     *
     * @param int $precision Number of decimal places
     * @return string
     */
    public function formatWithSymbol(int $precision = 1): string {
        return $this->format($precision) . '%';
    }

    /**
     * Check if perfect score
     *
     * @return bool
     */
    public function isPerfect(): bool {
        return $this->value === self::MAX_SCORE;
    }

    /**
     * Check if failing score (below 60%)
     *
     * @return bool
     */
    public function isFailing(): bool {
        return $this->value < 60;
    }

    /**
     * Add to score
     *
     * @param LUS_Score $other Score to add
     * @return self
     */
    public function add(LUS_Score $other): self {
        $newValue = min(self::MAX_SCORE, $this->value + $other->getValue());
        return new self($newValue);
    }

    /**
     * Compare with another score
     *
     * @param LUS_Score $other Score to compare with
     * @return int -1 if less, 0 if equal, 1 if greater
     */
    public function compareTo(LUS_Score $other): int {
        return $this->value <=> $other->getValue();
    }

    /**
     * Convert to string
     *
     * @return string
     */
    public function __toString(): string {
        return $this->formatWithSymbol();
    }
}