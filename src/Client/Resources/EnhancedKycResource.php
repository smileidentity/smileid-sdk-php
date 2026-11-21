<?php

declare(strict_types=1);

namespace SmileIdentity\Client\Resources;

use SmileIdentity\Client\Config;
use SmileIdentity\Client\Transport;
use SmileIdentity\Consent;
use SmileIdentity\Generated\Models\AcceptedResponse;
use SmileIdentity\Generated\Models\EnhancedKycResult;
use SmileIdentity\Generated\Operations\Operations;
use SmileIdentity\Helpers\JobId;
use SmileIdentity\Helpers\Url;
use SmileIdentity\Helpers\UserDetails;

/** enhanced_kyc.verify → POST /v3/enhanced_kyc, enhanced_kyc.verifySync → POST /v3/enhanced_kyc/sync. */
final class EnhancedKycResource
{
    public function __construct(
        private readonly Transport $transport,
        private readonly Config $config,
    ) {
    }

    /**
     * @param array<string, mixed> $userDetails
     * @param array<string, mixed>|null $partnerParams
     * @param array<int, mixed>|null $metadata
     */
    public function verify(
        string $country,
        string $idType,
        string $idNumber,
        array $userDetails,
        Consent $consent,
        ?string $callbackUrl = null,
        ?string $bankCode = null,
        ?string $operator = null,
        ?array $partnerParams = null,
        ?array $metadata = null,
        ?string $userId = null,
    ): AcceptedResponse {
        return AcceptedResponse::fromArray($this->submit(
            false,
            $country,
            $idType,
            $idNumber,
            $userDetails,
            $consent,
            $callbackUrl ?? $this->config->defaultCallbackUrl,
            $bankCode,
            $operator,
            $partnerParams,
            $metadata,
            $userId,
        )[0]);
    }

    /**
     * Like {@see verify()}, but returns the result in the response, with the
     * job id in `jobId`. The client's default callback URL is not used:
     * `callback_url` is sent only when you pass one, and the API ignores it.
     * A call that cannot finish raises a SmileIDError (408 or 424) or an
     * APIError (500). The job may still have run, and resubmitting runs and
     * bills a second lookup, so don't resubmit automatically: read the outcome
     * with `verifications->retrieve($e->jobId)`. A 500 with no `jobId` means
     * the job was not created. The call is never retried automatically.
     *
     * @param array<string, mixed> $userDetails
     * @param array<string, mixed>|null $partnerParams
     * @param array<int, mixed>|null $metadata
     */
    public function verifySync(
        string $country,
        string $idType,
        string $idNumber,
        array $userDetails,
        Consent $consent,
        ?string $callbackUrl = null,
        ?string $bankCode = null,
        ?string $operator = null,
        ?array $partnerParams = null,
        ?array $metadata = null,
        ?string $userId = null,
    ): EnhancedKycResult {
        [$data, $headers] = $this->submit(
            true,
            $country,
            $idType,
            $idNumber,
            $userDetails,
            $consent,
            $callbackUrl,
            $bankCode,
            $operator,
            $partnerParams,
            $metadata,
            $userId,
        );

        return EnhancedKycResult::fromArray($data, JobId::fromHeaders($headers));
    }

    /**
     * @param array<string, mixed> $userDetails
     * @param array<string, mixed>|null $partnerParams
     * @param array<int, mixed>|null $metadata
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>} the
     *     decoded body and the response headers
     */
    private function submit(
        bool $sync,
        string $country,
        string $idType,
        string $idNumber,
        array $userDetails,
        Consent $consent,
        ?string $callbackUrl,
        ?string $bankCode,
        ?string $operator,
        ?array $partnerParams,
        ?array $metadata,
        ?string $userId,
    ): array {
        UserDetails::validate($userDetails);
        Url::requireHttpsCallback($callbackUrl);

        $args = [
            'country' => $country,
            'id_type' => $idType,
            'id_number' => $idNumber,
            'bank_code' => $bankCode,
            'operator' => $operator,
            'callback_url' => $callbackUrl,
            'user_details' => $userDetails,
            'consent' => $consent,
            'partner_params' => $partnerParams,
            'metadata' => $metadata,
        ];

        return $sync
            ? Operations::enhancedKycSync($this->transport, $args, $userId)
            : [Operations::enhancedKyc($this->transport, $args, $userId), []];
    }
}
