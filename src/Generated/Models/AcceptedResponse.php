<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/**
 * HTTP 202 response from every entry endpoint.
 *
 * The wire `status` value differs by endpoint (`Accepted` vs `accepted`); the
 * SDK normalizes it into $isAccepted so callers never branch on raw casing.
 */
final class AcceptedResponse
{
    public readonly bool $isAccepted;

    public function __construct(
        public readonly string $status,
        public readonly ?string $message,
        public readonly ?string $jobId,
        public readonly ?string $userId,
        public readonly ?string $createdAt = null,
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
            message: isset($data['message']) ? (string) $data['message'] : null,
            jobId: isset($data['job_id']) ? (string) $data['job_id'] : null,
            userId: isset($data['user_id']) ? (string) $data['user_id'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
        );
    }
}
