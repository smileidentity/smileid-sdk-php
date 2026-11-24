<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

use SmileIdentity\Helpers\ResponseHeader;

/**
 * HTTP 200 response from POST /v3/aml/sync: the terminal result, identical to
 * the payload the async One-Time AML callback delivers.
 */
final class OneTimeAmlResult
{
    /**
     * @param array<string, string>|null $partnerParams
     * @param list<array<string, mixed>> $people matches, with the wire snake_case keys
     * @param array<string, string|null> $userProvidedInfo
     * @param array<string, mixed> $consent
     * @param array<string, mixed>|null $antifraud
     * @param array<string, mixed>|null $deviceSignals
     * @param string|null $jobId the Smile job id, from the SmileID-Job-ID response header
     * @param string|null $userId from the User-ID response header
     * @param string|null $responseSignature the Response-Signature header, for
     *     your own signature check
     * @param string|null $responseTimestamp the Response-Timestamp header the
     *     signature was computed against
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $reason,
        public readonly string $createdAt,
        public readonly ?string $completedAt,
        public readonly string $product,
        public readonly ?array $partnerParams,
        public readonly ?string $kycReceipt,
        public readonly bool $strictMatch,
        public readonly int $noOfPersonsFound,
        public readonly array $people,
        public readonly array $userProvidedInfo,
        public readonly array $consent,
        public readonly ?array $antifraud,
        public readonly ?array $deviceSignals,
        public readonly ?string $jobId = null,
        public readonly ?string $userId = null,
        public readonly ?string $responseSignature = null,
        public readonly ?string $responseTimestamp = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param string|null $jobId the SmileID-Job-ID response header; the body
     *     carries no job id
     * @param array<string, string> $headers the response headers; names are
     *     matched case-insensitively and blank values count as absent
     */
    public static function fromArray(array $data, ?string $jobId = null, array $headers = []): self
    {
        $people = [];
        foreach (is_array($data['people'] ?? null) ? $data['people'] : [] as $person) {
            if (is_array($person)) {
                $people[] = $person;
            }
        }

        return new self(
            status: (string) ($data['status'] ?? ''),
            message: (string) ($data['message'] ?? ''),
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            createdAt: (string) ($data['created_at'] ?? ''),
            completedAt: isset($data['completed_at']) ? (string) $data['completed_at'] : null,
            product: (string) ($data['product'] ?? ''),
            partnerParams: is_array($data['partner_params'] ?? null) ? $data['partner_params'] : null,
            kycReceipt: isset($data['kyc_receipt']) ? (string) $data['kyc_receipt'] : null,
            strictMatch: (bool) ($data['strict_match'] ?? false),
            noOfPersonsFound: (int) ($data['no_of_persons_found'] ?? 0),
            people: $people,
            userProvidedInfo: is_array($data['user_provided_info'] ?? null) ? $data['user_provided_info'] : [],
            consent: is_array($data['consent'] ?? null) ? $data['consent'] : [],
            antifraud: is_array($data['antifraud'] ?? null) ? $data['antifraud'] : null,
            deviceSignals: is_array($data['device_signals'] ?? null) ? $data['device_signals'] : null,
            jobId: $jobId,
            userId: ResponseHeader::get($headers, 'User-ID'),
            responseSignature: ResponseHeader::get($headers, 'Response-Signature'),
            responseTimestamp: ResponseHeader::get($headers, 'Response-Timestamp'),
        );
    }
}
