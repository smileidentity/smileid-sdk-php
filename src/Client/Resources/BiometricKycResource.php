<?php

declare(strict_types=1);

namespace SmileIdentity\Client\Resources;

use SmileIdentity\Client\Config;
use SmileIdentity\Client\Transport;
use SmileIdentity\Consent;
use SmileIdentity\Errors\ValidationError;
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
     * With `useEnrolledImage: true` the job verifies against `userId`'s enrolled
     * selfie, so the selfie and liveness images may be omitted. The grant is
     * bound into this job's token, which names `userId`.
     *
     * @param BinaryInput|string|resource|null $selfieImage required unless useEnrolledImage
     * @param array<int, BinaryInput|string|resource>|null $livenessImages 6–8 images, required unless useEnrolledImage
     * @param bool $useEnrolledImage only a literal true grants
     * @param array<string, mixed> $userDetails
     * @param array<string, mixed>|null $partnerParams
     * @param array<int, mixed>|null $metadata
     */
    public function verify(
        Consent $consent,
        string $country,
        string $idType,
        string $idNumber,
        array $userDetails,
        mixed $selfieImage = null,
        ?array $livenessImages = null,
        mixed $useEnrolledImage = false,
        ?string $callbackUrl = null,
        int|float|null $sandboxResult = null,
        ?array $partnerParams = null,
        ?array $metadata = null,
        ?string $userId = null,
    ): AcceptedResponse {
        if (!is_bool($useEnrolledImage)) {
            throw new ValidationError('use_enrolled_image must be a bool.');
        }
        UserDetails::validate($userDetails);
        Url::requireHttpsCallback($callbackUrl);

        if ($useEnrolledImage) {
            if ($userId === null || $userId === '') {
                throw new ValidationError('user_id is required when use_enrolled_image is true.');
            }
        } elseif ($selfieImage === null || $livenessImages === null || $livenessImages === []) {
            throw new ValidationError('selfie_image and liveness_images are required unless use_enrolled_image is true.');
        }

        $data = Operations::biometricKyc($useEnrolledImage ? $this->transport->withEnrolledImageGrant() : $this->transport, [
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
