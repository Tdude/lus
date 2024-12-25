<?php
/**
 * DifficultyLevel Value Object
 *
 * @package    LUS
 * @subpackage LUS/includes/value-objects
 */

class LUS_Difficulty_Level {
    /** @var int */
    private $level;

    /** @var array Difficulty level descriptions */
    private const DESCRIPTIONS = [
        1  => ['label' => 'Nybörjare',     'description' => 'Mycket enkel text för nybörjare'],
        5  => ['label' => 'Grundläggande',  'description' => 'Enkel text för tidig läsning'],
        10 => ['label' => 'Mellan',         'description' => 'Text för mellannivå'],
        15 => ['label' => 'Avancerad',      'description' => 'Mer utmanande text'],
        20 => ['label' => 'Expert',         'description' => 'Komplex text för avancerade läsare']
    ];

    /**
     * Constructor
     *
     * @param int $level Difficulty level (1-20)
     * @throws InvalidArgumentException If level is invalid
     */
    private function __construct(int $level) {
        $this->validate($level);
        $this->level = $level;
    }

    /**
     * Create from level number
     *
     * @param int $level Difficulty level
     * @return self
     */
    public static function fromLevel(int $level): self {
        return new self($level);
    }

    /**
     * Create from category name
     *
     * @param string $category Category name (e.g., 'Nybörjare', 'Mellan', etc.)
     * @return self
     * @throws InvalidArgumentException If category is invalid
     */
    public static function fromCategory(string $category): self {
        foreach (self::DESCRIPTIONS as $level => $info) {
            if ($info['label'] === $category) {
                return new self($level);
            }
        }
        throw new InvalidArgumentException(__('Invalid difficulty category', 'lus'));
    }

    /**
     * Validate level
     *
     * @param int $level Level to validate
     * @throws InvalidArgumentException If level is invalid
     */
    private function validate(int $level): void {
        if ($level < 1 || $level > LUS_Constants::MAX_DIFFICULTY_LEVEL) {
            throw new InvalidArgumentException(
                sprintf(__('Difficulty level must be between 1 and %d', 'lus'), LUS_Constants::MAX_DIFFICULTY_LEVEL)
            );
        }
    }

    /**
     * Get raw level value
     *
     * @return int
     */
    public function getLevel(): int {
        return $this->level;
    }

    /**
     * Get level category label
     *
     * @return string
     */
    public function getCategory(): string {
        $lastMatch = null;
        foreach (self::DESCRIPTIONS as $threshold => $info) {
            if ($this->level <= $threshold) {
                return $info['label'];
            }
            $lastMatch = $info['label'];
        }
        return $lastMatch;
    }

    /**
     * Get level description
     *
     * @return string
     */
    public function getDescription(): string {
        $lastMatch = null;
        foreach (self::DESCRIPTIONS as $threshold => $info) {
            if ($this->level <= $threshold) {
                return $info['description'];
            }
            $lastMatch = $info['description'];
        }
        return $lastMatch;
    }

    /**
     * Get normalized difficulty (0-1)
     *
     * @return float
     */
    public function getNormalizedValue(): float {
        return ($this->level - 1) / (LUS_Constants::MAX_DIFFICULTY_LEVEL - 1);
    }

    /**
     * Check if level is beginner (1-5)
     *
     * @return bool
     */
    public function isBeginner(): bool {
        return $this->level <= 5;
    }

    /**
     * Check if level is intermediate (6-15)
     *
     * @return bool
     */
    public function isIntermediate(): bool {
        return $this->level > 5 && $this->level <= 15;
    }

    /**
     * Check if level is advanced (16-20)
     *
     * @return bool
     */
    public function isAdvanced(): bool {
        return $this->level > 15;
    }

    /**
     * Compare with another difficulty level
     *
     * @param LUS_Difficulty_Level $other Difficulty level to compare with
     * @return int -1 if less, 0 if equal, 1 if greater
     */
    public function compareTo(LUS_Difficulty_Level $other): int {
        return $this->level <=> $other->getLevel();
    }

    /**
     * Check if level equals another
     *
     * @param LUS_Difficulty_Level $other Difficulty level to compare with
     * @return bool
     */
    public function equals(LUS_Difficulty_Level $other): bool {
        return $this->level === $other->getLevel();
    }

    /**
     * Format as string
     *
     * @param bool $includeDescription Whether to include the description
     * @return string
     */
    public function format(bool $includeDescription = false): string {
        $output = sprintf('%d - %s', $this->level, $this->getCategory());
        if ($includeDescription) {
            $output .= sprintf(' (%s)', $this->getDescription());
        }
        return $output;
    }

    /**
     * Convert to string
     *
     * @return string
     */
    public function __toString(): string {
        return $this->format();
    }
}