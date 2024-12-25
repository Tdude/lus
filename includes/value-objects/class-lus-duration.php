<?php
/**
 * Duration Value Object
 *
 * @package    LUS
 * @subpackage LUS/includes/value-objects
 */

class LUS_Duration {
    /** @var int */
    private $seconds;

    /**
     * Constructor
     *
     * @param int $seconds Number of seconds
     * @throws InvalidArgumentException If seconds is negative
     */
    public function __construct(int $seconds) {
        if ($seconds < 0) {
            throw new InvalidArgumentException(__('Duration cannot be negative', 'lus'));
        }
        $this->seconds = $seconds;
    }

    /**
     * Create from seconds
     *
     * @param int $seconds Number of seconds
     * @return self
     */
    public static function fromSeconds(int $seconds): self {
        return new self($seconds);
    }

    /**
     * Create from minutes
     *
     * @param int $minutes Number of minutes
     * @return self
     */
    public static function fromMinutes(int $minutes): self {
        return new self($minutes * 60);
    }

    /**
     * Create from hours, minutes, seconds
     *
     * @param int $hours   Number of hours
     * @param int $minutes Number of minutes
     * @param int $seconds Number of seconds
     * @return self
     */
    public static function fromHMS(int $hours, int $minutes, int $seconds): self {
        return new self(($hours * 3600) + ($minutes * 60) + $seconds);
    }

    /**
     * Create from string format (HH:MM:SS or MM:SS)
     *
     * @param string $time Time string
     * @return self
     * @throws InvalidArgumentException If time string is invalid
     */
    public static function fromString(string $time): self {
        $parts = array_map('intval', explode(':', $time));
        $count = count($parts);

        if ($count === 3) {
            return self::fromHMS($parts[0], $parts[1], $parts[2]);
        } elseif ($count === 2) {
            return self::fromHMS(0, $parts[0], $parts[1]);
        } else {
            throw new InvalidArgumentException(__('Invalid time format. Use HH:MM:SS or MM:SS', 'lus'));
        }
    }

    /**
     * Get total seconds
     *
     * @return int
     */
    public function getSeconds(): int {
        return $this->seconds;
    }

    /**
     * Get hours component
     *
     * @return int
     */
    public function getHours(): int {
        return (int) floor($this->seconds / 3600);
    }

    /**
     * Get minutes component
     *
     * @return int
     */
    public function getMinutes(): int {
        return (int) floor(($this->seconds % 3600) / 60);
    }

    /**
     * Get remaining seconds component
     *
     * @return int
     */
    public function getRemainingSeconds(): int {
        return $this->seconds % 60;
    }

    /**
     * Add duration
     *
     * @param LUS_Duration $other Duration to add
     * @return self
     */
    public function add(LUS_Duration $other): self {
        return new self($this->seconds + $other->getSeconds());
    }

    /**
     * Subtract duration
     *
     * @param LUS_Duration $other Duration to subtract
     * @return self
     * @throws InvalidArgumentException If result would be negative
     */
    public function subtract(LUS_Duration $other): self {
        $result = $this->seconds - $other->getSeconds();
        if ($result < 0) {
            throw new InvalidArgumentException(__('Duration cannot be negative', 'lus'));
        }
        return new self($result);
    }

    /**
     * Compare with another duration
     *
     * @param LUS_Duration $other Duration to compare with
     * @return int -1 if less, 0 if equal, 1 if greater
     */
    public function compareTo(LUS_Duration $other): int {
        if ($this->seconds < $other->getSeconds()) {
            return -1;
        } elseif ($this->seconds > $other->getSeconds()) {
            return 1;
        }
        return 0;
    }

    /**
     * Check if duration equals another
     *
     * @param LUS_Duration $other Duration to compare with
     * @return bool
     */
    public function equals(LUS_Duration $other): bool {
        return $this->seconds === $other->getSeconds();
    }

    /**
     * Check if duration is greater than another
     *
     * @param LUS_Duration $other Duration to compare with
     * @return bool
     */
    public function greaterThan(LUS_Duration $other): bool {
        return $this->seconds > $other->getSeconds();
    }

    /**
     * Check if duration is less than another
     *
     * @param LUS_Duration $other Duration to compare with
     * @return bool
     */
    public function lessThan(LUS_Duration $other): bool {
        return $this->seconds < $other->getSeconds();
    }

    /**
     * Format as string (HH:MM:SS or MM:SS)
     *
     * @param bool $includeHours Whether to always include hours
     * @return string
     */
    public function format(bool $includeHours = false): string {
        $hours = $this->getHours();
        $minutes = $this->getMinutes();
        $seconds = $this->getRemainingSeconds();

        if ($hours > 0 || $includeHours) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Format for human reading (e.g., "2 hours 15 minutes")
     *
     * @param bool $short Whether to use short format (2h 15m)
     * @return string
     */
    public function formatHuman(bool $short = false): string {
        $hours = $this->getHours();
        $minutes = $this->getMinutes();
        $seconds = $this->getRemainingSeconds();

        $parts = [];

        if ($hours > 0) {
            $parts[] = $short ?
                sprintf('%dh', $hours) :
                sprintf(_n('%d hour', '%d hours', $hours, 'lus'), $hours);
        }

        if ($minutes > 0) {
            $parts[] = $short ?
                sprintf('%dm', $minutes) :
                sprintf(_n('%d minute', '%d minutes', $minutes, 'lus'), $minutes);
        }

        if ($seconds > 0 || empty($parts)) {
            $parts[] = $short ?
                sprintf('%ds', $seconds) :
                sprintf(_n('%d second', '%d seconds', $seconds, 'lus'), $seconds);
        }

        return implode($short ? ' ' : ', ', $parts);
    }

    /**
     * Convert to string (alias for format)
     *
     * @return string
     */
    public function __toString(): string {
        return $this->format();
    }

    /**
     * Create from various input types
     *
     * @param mixed $input Input value (seconds, string, or Duration)
     * @return self
     * @throws InvalidArgumentException If input is invalid
     */
    public static function from($input): self {
        if ($input instanceof self) {
            return $input;
        }

        if (is_numeric($input)) {
            return self::fromSeconds((int) $input);
        }

        if (is_string($input)) {
            return self::fromString($input);
        }

        throw new InvalidArgumentException(__('Invalid duration input', 'lus'));
    }
}