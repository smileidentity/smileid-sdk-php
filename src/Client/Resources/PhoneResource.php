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

/** phone.verify → POST /v3/phone. */
final class PhoneResource
{
    public function __construct(
        private readonly Transport $transport,
        private readonly Config $config,
    ) {
    }

    /**
     * Check who a mobile number is registered to and how well that matches the
     * names, and the ID number, you supply; the result arrives on the callback
     * URL. `$userDetails` holds the consumer's own details, not the number
     * checked. Kenya requires `$idNumber` and Ghana requires `$operator`
     * (MTN, VODAFONE or AIRTELTIGO).
     *
     * @param string $phoneNumber the number to check, in E.164 format
     * @param array<string, mixed> $userDetails
     * @param array<string, mixed>|null $partnerParams
     * @param array<int, mixed>|null $metadata
     */
    public function verify(
        string $country,
        string $phoneNumber,
        array $userDetails,
        Consent $consent,
        ?string $callbackUrl = null,
        ?string $operator = null,
        ?string $idNumber = null,
        ?string $userId = null,
        ?array $partnerParams = null,
        ?array $metadata = null,
    ): AcceptedResponse {
        if (preg_match('/^\+[1-9]\d{6,14}$/', $phoneNumber) !== 1) {
            throw new ValidationError('phoneNumber must be in E.164 format, such as +2348012345678.');
        }
        if ($country === 'KE' && ($idNumber === null || trim($idNumber) === '')) {
            throw new ValidationError('idNumber is required for KE.');
        }
        if ($country === 'GH' && ($operator === null || trim($operator) === '')) {
            throw new ValidationError('operator is required for GH.');
        }
        UserDetails::validate($userDetails);
        Url::requireHttpsCallback($callbackUrl);

        return AcceptedResponse::fromArray(Operations::phoneVerification(
            $this->transport,
            $country,
            $phoneNumber,
            $operator,
            $idNumber,
            $userId,
            $userDetails,
            $consent,
            $callbackUrl ?? $this->config->defaultCallbackUrl,
            $partnerParams,
            $metadata,
        ));
    }
}
