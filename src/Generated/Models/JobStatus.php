<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/**
 * GET /v3/status/{jobId} response.
 *
 * status is one of complete / processing / not_found. The terminal sub-state
 * (clear/block/attention/error) currently appears only in $message.
 */
final class JobStatus
{
    public readonly bool $isComplete;
    public readonly bool $isProcessing;
    public readonly bool $isNotFound;

    public function __construct(
        public readonly string $status,
        public readonly ?string $jobId,
        public readonly ?string $userId,
        public readonly ?string $message,
    ) {
        $this->isComplete = $status === 'complete';
        $this->isProcessing = $status === 'processing';
        $this->isNotFound = $status === 'not_found';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: isset($data['status']) ? (string) $data['status'] : '',
            jobId: isset($data['job_id']) ? (string) $data['job_id'] : null,
            userId: isset($data['user_id']) ? (string) $data['user_id'] : null,
            message: isset($data['message']) ? (string) $data['message'] : null,
        );
    }
}
