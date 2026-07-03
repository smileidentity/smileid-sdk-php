<?php

declare(strict_types=1);

namespace SmileIdentity\Client\Resources;

use SmileIdentity\Client\Config;
use SmileIdentity\Client\Transport;
use SmileIdentity\Consent;
use SmileIdentity\Generated\Models\AcceptedResponse;
use SmileIdentity\Generated\Operations\Operations;
use SmileIdentity\Helpers\Url;
use SmileIdentity\Helpers\UserDetails;

/** enhanced_kyc.verify → POST /v3/enhanced_kyc. */
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
        UserDetails::validate($userDetails);
        Url::requireHttpsCallback($callbackUrl);

        $data = Operations::enhancedKyc($this->transport, [
            'country' => $country,
            'id_type' => $idType,
            'id_number' => $idNumber,
            'bank_code' => $bankCode,
            'operator' => $operator,
            'callback_url' => $callbackUrl ?? $this->config->defaultCallbackUrl,
            'user_details' => $userDetails,
            'consent' => $consent,
            'partner_params' => $partnerParams,
            'metadata' => $metadata,
        ], $userId);

        return AcceptedResponse::fromArray($data);
    }
}
