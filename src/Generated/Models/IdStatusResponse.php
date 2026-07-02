<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/** HTTP 200 response from GET /v3/services/id_status. */
final class IdStatusResponse
{
    public function __construct(
        public readonly ?string $lastChecked,
        public readonly ?string $lastCheckStatus,
        public readonly ?string $lastHourSuccessRate,
        public readonly ?string $lastKnownStatus,
        public readonly ?string $lastCheckSuccessRate,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            lastChecked: isset($data['last_checked']) ? (string) $data['last_checked'] : null,
            lastCheckStatus: isset($data['last_check_status']) ? (string) $data['last_check_status'] : null,
            lastHourSuccessRate: isset($data['last_hour_success_rate']) ? (string) $data['last_hour_success_rate'] : null,
            lastKnownStatus: isset($data['last_known_status']) ? (string) $data['last_known_status'] : null,
            lastCheckSuccessRate: isset($data['last_check_success_rate']) ? (string) $data['last_check_success_rate'] : null,
        );
    }
}
