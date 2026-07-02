<?php

declare(strict_types=1);

namespace SmileIdentity\Client\Resources;

use SmileIdentity\Client\Transport;
use SmileIdentity\Generated\Models\BankCodesResponse;
use SmileIdentity\Generated\Models\IdStatusResponse;
use SmileIdentity\Generated\Models\SupportedDocumentsResponse;
use SmileIdentity\Generated\Models\SupportedIdTypesResponse;
use SmileIdentity\Generated\Operations\Operations;

/**
 * services.bankCodes / supportedIdTypes / supportedDocuments (no auth) and
 * services.idStatus (token required).
 */
final class ServicesResource
{
    public function __construct(
        private readonly Transport $transport,
    ) {
    }

    public function bankCodes(?string $country = null): BankCodesResponse
    {
        return BankCodesResponse::fromArray(Operations::bankCodes($this->transport, $country));
    }

    public function supportedIdTypes(?string $country = null): SupportedIdTypesResponse
    {
        return SupportedIdTypesResponse::fromArray(Operations::supportedIdTypes($this->transport, $country));
    }

    public function supportedDocuments(
        ?string $continent = null,
        ?string $countryCode = null,
        ?string $locale = null,
    ): SupportedDocumentsResponse {
        return SupportedDocumentsResponse::fromArray(
            Operations::supportedDocuments($this->transport, $continent, $countryCode, $locale),
        );
    }

    public function idStatus(string $country, string $idType): IdStatusResponse
    {
        return IdStatusResponse::fromArray(Operations::idStatus($this->transport, $country, $idType));
    }
}
