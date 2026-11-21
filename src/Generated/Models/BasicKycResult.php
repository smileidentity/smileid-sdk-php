<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/**
 * HTTP 200 response from POST /v3/basic_kyc/sync: the verification result,
 * identical to the payload the async Basic KYC callback delivers. It reports
 * how each submitted field compared with the ID authority's record and never
 * returns the record itself.
 */
final class BasicKycResult
{
    /**
     * @param array<string, string|null>|null $partnerParams
     * @param array<string, string|null>|null $matchResults verdict per field (MatchVerdict:
     *     exact_match, partial_match, transposed, no_match or not_provided); null when
     *     the check produced none
     * @param array<string, string|null> $userProvidedInfo
     * @param array<string, mixed> $consent
     * @param array<string, mixed>|null $antifraud
     * @param array<string, mixed>|null $deviceSignals
     * @param string|null $jobId the Smile job id, from the SmileID-Job-ID response header
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $reason,
        public readonly string $createdAt,
        public readonly ?string $completedAt,
        public readonly string $product,
        public readonly ?array $partnerParams,
        public readonly ?array $matchResults,
        public readonly array $userProvidedInfo,
        public readonly array $consent,
        public readonly ?array $antifraud,
        public readonly ?array $deviceSignals,
        public readonly ?string $kycReceipt,
        public readonly ?string $jobId = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param string|null $jobId the SmileID-Job-ID response header; the body
     *     carries no job id
     */
    public static function fromArray(array $data, ?string $jobId = null): self
    {
        return new self(
            status: (string) ($data['status'] ?? ''),
            message: (string) ($data['message'] ?? ''),
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            createdAt: (string) ($data['created_at'] ?? ''),
            completedAt: isset($data['completed_at']) ? (string) $data['completed_at'] : null,
            product: (string) ($data['product'] ?? ''),
            partnerParams: is_array($data['partner_params'] ?? null) ? $data['partner_params'] : null,
            matchResults: is_array($data['match_results'] ?? null) ? $data['match_results'] : null,
            userProvidedInfo: is_array($data['user_provided_info'] ?? null) ? $data['user_provided_info'] : [],
            consent: is_array($data['consent'] ?? null) ? $data['consent'] : [],
            antifraud: is_array($data['antifraud'] ?? null) ? $data['antifraud'] : null,
            deviceSignals: is_array($data['device_signals'] ?? null) ? $data['device_signals'] : null,
            kycReceipt: isset($data['kyc_receipt']) ? (string) $data['kyc_receipt'] : null,
            jobId: $jobId,
        );
    }
}
