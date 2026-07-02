<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/** HTTP 202 response from POST /v3/replay/{job_id}. */
final class ReplayCallbackResponse
{
    public readonly bool $isAccepted;

    public function __construct(
        public readonly string $status,
        public readonly ?string $jobId,
        public readonly ?string $userId,
        public readonly ?string $message,
    ) {
        $this->isAccepted = strtolower($status) === 'accepted';
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
