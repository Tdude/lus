<?php
/**
 * Status Value Object
 *
 * @package    LUS
 * @subpackage LUS/includes/value-objects
 */

class LUS_Status {
    /** @var string */
    private $status;

    /** @var array Valid status transitions */
    private const TRANSITIONS = [
        LUS_Constants::STATUS_PENDING => [
            LUS_Constants::STATUS_ASSESSED,
            LUS_Constants::STATUS_COMPLETED
        ],
        LUS_Constants::STATUS_ASSESSED => [
            LUS_Constants::STATUS_COMPLETED
        ],
        LUS_Constants::STATUS_COMPLETED => [],
        LUS_Constants::STATUS_OVERDUE => [
            LUS_Constants::STATUS_COMPLETED
        ]
    ];

    /** @var array Status labels */
    private const LABELS = [
        LUS_Constants::STATUS_PENDING => [
            'label' => 'Väntar',
            'description' => 'Väntar på bedömning'
        ],
        LUS_Constants::STATUS_ASSESSED => [
            'label' => 'Bedömd',
            'description' => 'Bedömning genomförd'
        ],
        LUS_Constants::STATUS_COMPLETED => [
            'label' => 'Slutförd',
            'description' => 'Slutförd och godkänd'
        ],
        LUS_Constants::STATUS_OVERDUE => [
            'label' => 'Försenad',
            'description' => 'Försenad inlämning'
        ]
    ];

    /**
     * Constructor
     *
     * @param string $status Status string
     * @throws InvalidArgumentException If status is invalid
     */
    private function __construct(string $status) {
        $this->validate($status);
        $this->status = $status;
    }

    /**
     * Create from status string
     *
     * @param string $status Status string
     * @return self
     */
    public static function fromString(string $status): self {
        return new self($status);
    }

    /**
     * Create initial pending status
     *
     * @return self
     */
    public static function pending(): self {
        return new self(LUS_Constants::STATUS_PENDING);
    }

    /**
     * Create assessed status
     *
     * @return self
     */
    public static function assessed(): self {
        return new self(LUS_Constants::STATUS_ASSESSED);
    }

    /**
     * Create completed status
     *
     * @return self
     */
    public static function completed(): self {
        return new self(LUS_Constants::STATUS_COMPLETED);
    }

    /**
     * Create overdue status
     *
     * @return self
     */
    public static function overdue(): self {
        return new self(LUS_Constants::STATUS_OVERDUE);
    }

/**
     * Validate status
     *
     * @param string $status Status to validate
     * @throws InvalidArgumentException If status is invalid
     */
    private function validate(string $status): void {
        if (!isset(self::TRANSITIONS[$status])) {
            throw new InvalidArgumentException(
                sprintf(__('Invalid status: %s', 'lus'), $status)
            );
        }
    }

    /**
     * Get raw status string
     *
     * @return string
     */
    public function getStatus(): string {
        return $this->status;
    }

    /**
     * Get status label
     *
     * @return string
     */
    public function getLabel(): string {
        return self::LABELS[$this->status]['label'];
    }

    /**
     * Get status description
     *
     * @return string
     */
    public function getDescription(): string {
        return self::LABELS[$this->status]['description'];
    }

    /**
     * Check if status can transition to another status
     *
     * @param string|LUS_Status $newStatus Status to check transition to
     * @return bool
     */
    public function canTransitionTo($newStatus): bool {
        if ($newStatus instanceof self) {
            $newStatus = $newStatus->getStatus();
        }

        return in_array($newStatus, self::TRANSITIONS[$this->status], true);
    }

    /**
     * Transition to new status
     *
     * @param string|LUS_Status $newStatus Status to transition to
     * @return self
     * @throws InvalidArgumentException If transition is not allowed
     */
    public function transitionTo($newStatus): self {
        if ($newStatus instanceof self) {
            $newStatus = $newStatus->getStatus();
        }

        if (!$this->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException(
                sprintf(
                    __('Invalid status transition from %1$s to %2$s', 'lus'),
                    $this->status,
                    $newStatus
                )
            );
        }

        return new self($newStatus);
    }

    /**
     * Check if status is pending
     *
     * @return bool
     */
    public function isPending(): bool {
        return $this->status === LUS_Constants::STATUS_PENDING;
    }

    /**
     * Check if status is assessed
     *
     * @return bool
     */
    public function isAssessed(): bool {
        return $this->status === LUS_Constants::STATUS_ASSESSED;
    }

    /**
     * Check if status is completed
     *
     * @return bool
     */
    public function isCompleted(): bool {
        return $this->status === LUS_Constants::STATUS_COMPLETED;
    }

    /**
     * Check if status is overdue
     *
     * @return bool
     */
    public function isOverdue(): bool {
        return $this->status === LUS_Constants::STATUS_OVERDUE;
    }

    /**
     * Check if status is final (cannot transition further)
     *
     * @return bool
     */
    public function isFinal(): bool {
        return empty(self::TRANSITIONS[$this->status]);
    }

    /**
     * Check if status equals another status
     *
     * @param string|LUS_Status $other Status to compare with
     * @return bool
     */
    public function equals($other): bool {
        if ($other instanceof self) {
            $other = $other->getStatus();
        }
        return $this->status === $other;
    }

    /**
     * Get available transitions from current status
     *
     * @return array
     */
    public function getAvailableTransitions(): array {
        return self::TRANSITIONS[$this->status];
    }

    /**
     * Format status for display
     *
     * @param bool $includeDescription Whether to include the description
     * @return string
     */
    public function format(bool $includeDescription = false): string {
        $output = $this->getLabel();
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