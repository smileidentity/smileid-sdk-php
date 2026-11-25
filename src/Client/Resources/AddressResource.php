<?php

declare(strict_types=1);

namespace SmileIdentity\Client\Resources;

use SmileIdentity\Client\Config;
use SmileIdentity\Client\Transport;
use SmileIdentity\Consent;
use SmileIdentity\Errors\ValidationError;
use SmileIdentity\Generated\Models\AcceptedResponse;
use SmileIdentity\Generated\Operations\Operations;
use SmileIdentity\Helpers\Url;
use SmileIdentity\Helpers\UserDetails;

/** address.verify → POST /v3/address. */
final class AddressResource
{
    public function __construct(
        private readonly Transport $transport,
        private readonly Config $config,
    ) {
    }

    /**
     * Check an address against the address on record with a data provider; the
     * result arrives on the callback URL. Supported countries are NG and ZA.
     * Send `$idNumber` or `$utilityNumber`. Nigeria needs `$utilityNumber`,
     * `$utilityProvider` and `$utilityType` (PrePaid or PostPaid); South
     * Africa needs `$idNumber`.
     *
     * @param array<string, mixed> $userDetails
     * @param array<string, mixed>|null $partnerParams
     * @param array<int, mixed>|null $metadata
     */
    public function verify(
        string $address,
        string $country,
        array $userDetails,
        Consent $consent,
        ?string $callbackUrl = null,
        ?string $idNumber = null,
        ?string $utilityNumber = null,
        ?string $utilityProvider = null,
        ?string $utilityType = null,
        ?string $userId = null,
        ?array $partnerParams = null,
        ?array $metadata = null,
    ): AcceptedResponse {
        if (trim($address) === '' || strlen($address) > 512) {
            throw new ValidationError('address must be 1 to 512 characters.');
        }
        if (!in_array($country, ['NG', 'ZA'], true)) {
            throw new ValidationError('country must be NG or ZA.');
        }
        if (($idNumber === null || trim($idNumber) === '') && ($utilityNumber === null || trim($utilityNumber) === '')) {
            throw new ValidationError('idNumber or utilityNumber is required.');
        }
        if ($utilityType !== null && !in_array($utilityType, ['PrePaid', 'PostPaid'], true)) {
            throw new ValidationError('utilityType must be PrePaid or PostPaid.');
        }
        UserDetails::validate($userDetails);
        Url::requireHttpsCallback($callbackUrl);

        return AcceptedResponse::fromArray(Operations::addressVerification(
            $this->transport,
            $address,
            $country,
            $idNumber,
            $utilityNumber,
            $utilityProvider,
            $utilityType,
            $userId,
            $userDetails,
            $consent,
            $callbackUrl ?? $this->config->defaultCallbackUrl,
            $partnerParams,
            $metadata,
        ));
    }
}
