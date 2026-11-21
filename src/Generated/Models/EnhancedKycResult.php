<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/**
 * HTTP 200 response from POST /v3/enhanced_kyc/sync: the verification result,
 * identical to the payload the async Enhanced KYC callback delivers. The spec
 * documents its fields with that callback rather than here, so the fields
 * common to every verification result are typed and the whole body is kept
 * in $data.
 */
final class EnhancedKycResult
{
    /**
     * @param array<string, mixed> $data the whole response body, including
     *     fields not typed here
     * @param string|null $jobId the Smile job id, from the SmileID-Job-ID
     *     response header, or the body's job_id when the header is absent
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $reason,
        public readonly ?string $jobId,
        public readonly ?string $userId,
        public readonly ?string $kycReceipt,
        public readonly array $data,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param string|null $jobId the SmileID-Job-ID response header; the body's
     *     job_id is used when it is null
     */
    public static function fromArray(array $data, ?string $jobId = null): self
    {
        return new self(
            status: (string) ($data['status'] ?? ''),
            message: (string) ($data['message'] ?? ''),
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            jobId: $jobId ?? (isset($data['job_id']) ? (string) $data['job_id'] : null),
            userId: isset($data['user_id']) ? (string) $data['user_id'] : null,
            kycReceipt: isset($data['kyc_receipt']) ? (string) $data['kyc_receipt'] : null,
            data: $data,
        );
    }
}
