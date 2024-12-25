<?php
/**
 * Passage/texts Data Transfer Object
 *
 * @package    LUS
 * @subpackage LUS/includes/dto
 */

class LUS_Passage_DTO {
    /** @var int|null */
    private $id;

    /** @var string */
    private $title;

    /** @var string */
    private $content;

    /** @var int */
    private $time_limit;

    /** @var int */
    private $difficulty_level;

    /** @var int */
    private $created_by;

    /** @var string|null */
    private $created_at;

    /** @var string|null */
    private $updated_at;

    /** @var string|null */
    private $deleted_at;

    /**
     * Create from array
     *
     * @param array $data Raw data
     * @return self
     */
    public static function fromArray(array $data): self {
        $dto = new self();

        $dto->id = isset($data['id']) ? (int) $data['id'] : null;
        $dto->title = sanitize_text_field($data['title'] ?? '');
        $dto->content = wp_kses_post($data['content'] ?? '');
        $dto->time_limit = (int) ($data['time_limit'] ?? LUS_Constants::DEFAULT_TIME_LIMIT);
        $dto->difficulty_level = (int) ($data['difficulty_level'] ?? LUS_Constants::DEFAULT_DIFFICULTY_LEVEL);
        $dto->created_by = (int) ($data['created_by'] ?? get_current_user_id());
        $dto->created_at = $data['created_at'] ?? null;
        $dto->updated_at = $data['updated_at'] ?? null;
        $dto->deleted_at = $data['deleted_at'] ?? null;

        return $dto;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'time_limit' => $this->time_limit,
            'difficulty_level' => $this->difficulty_level,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }

    /**
     * Get database insert/update data
     *
     * @return array
     */
    public function toDbArray(): array {
        $data = $this->toArray();

        // Remove null values and ID for inserts
        return array_filter($data, function($value) {
            return $value !== null;
        });
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getContent(): string { return $this->content; }
    public function getTimeLimit(): int { return $this->time_limit; }
    public function getDifficultyLevel(): int { return $this->difficulty_level; }
    public function getCreatedBy(): int { return $this->created_by; }
    public function getCreatedAt(): ?string { return $this->created_at; }
    public function getUpdatedAt(): ?string { return $this->updated_at; }
    public function getDeletedAt(): ?string { return $this->deleted_at; }

    // Setters
    public function setTitle(string $title): self {
        $this->title = sanitize_text_field($title);
        return $this;
    }

    public function setContent(string $content): self {
        $this->content = wp_kses_post($content);
        return $this;
    }

    public function setTimeLimit(int $time_limit): self {
        $this->time_limit = max(0, $time_limit);
        return $this;
    }

    public function setDifficultyLevel(int $level): self {
        $this->difficulty_level = max(1, min(LUS_Constants::MAX_DIFFICULTY_LEVEL, $level));
        return $this;
    }

    /**
     * Validate the DTO
     *
     * @return bool|WP_Error True if valid, WP_Error if not
     */
    public function validate() {
        if (empty($this->title)) {
            return new WP_Error('invalid_title', __('Title is required', 'lus'));
        }

        if (empty($this->content)) {
            return new WP_Error('invalid_content', __('Content is required', 'lus'));
        }

        if ($this->time_limit < 0) {
            return new WP_Error('invalid_time_limit', __('Time limit cannot be negative', 'lus'));
        }

        if ($this->difficulty_level < 1 || $this->difficulty_level > LUS_Constants::MAX_DIFFICULTY_LEVEL) {
            return new WP_Error(
                'invalid_difficulty',
                sprintf(__('Difficulty level must be between 1 and %d', 'lus'), LUS_Constants::MAX_DIFFICULTY_LEVEL)
            );
        }

        return true;
    }
}