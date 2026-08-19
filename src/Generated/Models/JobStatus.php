<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/**
 * GET /v3/status/{jobId} response.
 *
 * status is `processing` while the job runs, `not_found` for an unknown job,
 * and otherwise the terminal decision itself: clear / block / attention /
 * error. $message is a human-readable note ("Job completed" on a finished
 * job), not the decision.
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
        $this->isProcessing = $status === 'processing';
        $this->isNotFound = $status === 'not_found';
        // Terminal means "any decision the API reports": anything that is not
        // still running and not missing.
        $this->isComplete = $status !== '' && !$this->isProcessing && !$this->isNotFound;
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
