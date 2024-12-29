<?php
/**
 * Recording Data Transfer Object
 *
 * @package    LUS
 * @subpackage LUS/includes/dto
 */

class LUS_Recording_DTO {
    /** @var int|null */
    private $id;

    /** @var int */
    private $user_id;

    /** @var int|null */
    private $passage_id;

    /** @var string */
    private $audio_file_path;

    /** @var int */
    private $duration;

    /** @var string */
    private $status;

    /** @var string|null */
    private $created_at;

    /** @var string|null */
    private $updated_at;

    /** @var array Additional metadata */
    private $metadata = [];

    /**
     * Create from array
     *
     * @param array $data Raw data
     * @return self
     */
    public static function fromArray(array $data): self {
        $dto = new self();

        $dto->id = isset($data['id']) ? (int) $data['id'] : null;
        $dto->user_id = (int) ($data['user_id'] ?? get_current_user_id());
        $dto->passage_id = isset($data['passage_id']) ? (int) $data['passage_id'] : null;
        $dto->audio_file_path = sanitize_text_field(LUS_Constants::UPLOAD_URL ?? '');
        $dto->duration = (int) ($data['duration'] ?? 0);
        $dto->status = sanitize_text_field($data['status'] ?? LUS_Constants::STATUS_PENDING);
        $dto->created_at = $data['created_at'] ?? null;
        $dto->updated_at = $data['updated_at'] ?? null;

        // Extract any additional metadata
        $dto->metadata = array_diff_key($data, array_flip([
            'id', 'user_id', 'passage_id', 'audio_file_path', 'duration',
            'status', 'created_at', 'updated_at'
        ]));

        return $dto;
    }

    /**
     * Convert to array
     *
     * @param bool $include_metadata Whether to include metadata
     * @return array
     */
    public function toArray(bool $include_metadata = true): array {
        $data = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'passage_id' => $this->passage_id,
            'audio_file_path' => $this->audio_file_path,
            'duration' => $this->duration,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($include_metadata) {
            $data = array_merge($data, $this->metadata);
        }

        return $data;
    }

    /**
     * Get database insert/update data
     *
     * @return array
     */
    public function toDbArray(): array {
        $data = $this->toArray(false);

        // Remove null values and ID for inserts
        return array_filter($data, function($value) {
            return $value !== null;
        });
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getUserId(): int { return $this->user_id; }
    public function getPassageId(): ?int { return $this->passage_id; }
    // Se all audio stuff below
    // public function getAudioFilePath(): string { return $this->audio_file_path; }
    public function getDuration(): int { return $this->duration; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedAt(): ?string { return $this->created_at; }
    public function getUpdatedAt(): ?string { return $this->updated_at; }
    public function getMetadata(string $key = null) {
        if ($key === null) {
            return $this->metadata;
        }
        return $this->metadata[$key] ?? null;
    }

    // Setters
    public function setUserId(int $user_id): self {
        $this->user_id = $user_id;
        return $this;
    }

    public function setPassageId(?int $passage_id): self {
        $this->passage_id = $passage_id;
        return $this;
    }

    public function setAudioFilePath(string $path): self {
        $this->audio_file_path = sanitize_text_field($path);
        return $this;
    }

    public function setDuration(int $duration): self {
        $this->duration = max(0, $duration);
        return $this;
    }

    public function setStatus(string $status): self {
        $this->status = sanitize_text_field($status);
        return $this;
    }

    public function setMetadata(string $key, $value): self {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Get the full audio file URL
     *
     * @return string
     */
    public function getAudioFileUrl(): string {
        return LUS_Constants::UPLOAD_URL . $this->audio_file_path;
    }

    /**
     * Get the full audio file system path
     *
     * @return string
     */
    public function getAudioFilePath(): string {
        return LUS_Constants::UPLOAD_DIR . $this->audio_file_path;
    }

    /**
     * Check if audio file exists
     *
     * @return bool
     */
    public function audioFileExists(): bool {
        return !empty($this->audio_file_path) && file_exists($this->getAudioFilePath());
    }

    /**
     * Validate the DTO
     *
     * @return bool|WP_Error True if valid, WP_Error if not
     */
    public function validate() {
        if (empty($this->audio_file_path)) {
            return new WP_Error('invalid_file_path', __('Audio file path is required', 'lus'));
        }

        if ($this->duration < 0) {
            return new WP_Error('invalid_duration', __('Duration cannot be negative', 'lus'));
        }

        if (!in_array($this->status, [
            LUS_Constants::STATUS_PENDING,
            LUS_Constants::STATUS_ASSESSED,
            LUS_Constants::STATUS_COMPLETED
        ])) {
            return new WP_Error('invalid_status', __('Invalid status', 'lus'));
        }

        if ($this->passage_id && !get_post($this->passage_id)) {
            return new WP_Error('invalid_passage', __('Invalid passage ID', 'lus'));
        }

        return true;
    }
}