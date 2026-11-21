<?php

declare(strict_types=1);

namespace SmileIdentity\Client\Resources;

use SmileIdentity\Client\Config;
use SmileIdentity\Client\Transport;
use SmileIdentity\Consent;
use SmileIdentity\Generated\Models\AcceptedResponse;
use SmileIdentity\Generated\Models\BasicKycResult;
use SmileIdentity\Generated\Operations\Operations;
use SmileIdentity\Helpers\Url;
use SmileIdentity\Helpers\UserDetails;

/** basicKyc.verify → POST /v3/basic_kyc, basicKyc.verifySync → POST /v3/basic_kyc/sync. */
final class BasicKycResource
{
    public function __construct(
        private readonly Transport $transport,
        private readonly Config $config,
    ) {
    }

    /**
     * Check submitted identity details against the ID authority's record; the
     * result arrives on the callback URL.
     *
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
        ));
    }

    /**
     * Like {@see verify()}, but returns the result in the response. The
     * client's default callback URL is not used: `callback_url` is sent only
     * when you pass one. A call that cannot finish raises a SmileIDError (408 or
     * 424) or an APIError (500). The job may still have run, and resubmitting
     * runs and bills a second lookup, so don't resubmit automatically. The call
     * is never retried automatically.
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
    ): BasicKycResult {
        return BasicKycResult::fromArray($this->submit(
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
        ));
    }

    /**
     * @param array<string, mixed> $userDetails
     * @param array<string, mixed>|null $partnerParams
     * @param array<int, mixed>|null $metadata
     *
     * @return array<string, mixed>
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
    ): array {
        UserDetails::validate($userDetails);
        Url::requireHttpsCallback($callbackUrl);

        return Operations::basicKyc($this->transport, [
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
        ], $sync);
    }
}
