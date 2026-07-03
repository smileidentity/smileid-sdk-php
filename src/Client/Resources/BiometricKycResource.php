<?php

declare(strict_types=1);

namespace SmileIdentity\Client\Resources;

use SmileIdentity\Client\Config;
use SmileIdentity\Client\Transport;
use SmileIdentity\Consent;
use SmileIdentity\Generated\Models\AcceptedResponse;
use SmileIdentity\Generated\Operations\Operations;
use SmileIdentity\Helpers\BinaryInput;
use SmileIdentity\Helpers\Url;
use SmileIdentity\Helpers\UserDetails;

/** biometric_kyc.verify → POST /v3/biometric_kyc (requires SmileID-Partner-ID header). */
final class BiometricKycResource
{
    public function __construct(
        private readonly Transport $transport,
        private readonly Config $config,
    ) {
    }

    /**
     * @param BinaryInput|string|resource $selfieImage
     * @param array<int, BinaryInput|string|resource> $livenessImages 6–8 images
     * @param array<string, mixed> $userDetails
     * @param array<string, mixed>|null $partnerParams
     * @param array<int, mixed>|null $metadata
     */
    public function verify(
        mixed $selfieImage,
        array $livenessImages,
        Consent $consent,
        string $country,
        string $idType,
        string $idNumber,
        array $userDetails,
        ?string $callbackUrl = null,
        int|float|null $sandboxResult = null,
        ?array $partnerParams = null,
        ?array $metadata = null,
        ?string $userId = null,
    ): AcceptedResponse {
        UserDetails::validate($userDetails);
        Url::requireHttpsCallback($callbackUrl);

        $data = Operations::biometricKyc($this->transport, [
            'country' => $country,
            'id_type' => $idType,
            'id_number' => $idNumber,
            'sandbox_result' => $sandboxResult,
            'callback_url' => $callbackUrl ?? $this->config->defaultCallbackUrl,
            'selfie_image' => $selfieImage,
            'liveness_images' => $livenessImages,
            'user_details' => $userDetails,
            'consent' => $consent,
            'partner_params' => $partnerParams,
            'metadata' => $metadata,
        ], $userId);

        return AcceptedResponse::fromArray($data);
    }
}
