<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/** HTTP 202 response from POST /v3/users/{user_id}/report_fraud. */
final class ReportUserFraudResponse
{
    public readonly bool $isAccepted;

    public function __construct(
        public readonly string $status,
        public readonly ?string $message,
        public readonly ?string $userId,
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
            userId: isset($data['user_id']) ? (string) $data['user_id'] : null,
        );
    }
}
