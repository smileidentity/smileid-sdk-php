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

/**
 * biometric.enroll → POST /v3/registration
 * biometric.authenticate → POST /v3/authentication (user_id in body)
 * biometric.compare → POST /v3/compare (user_id optional in body)
 */
final class BiometricResource
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
    public function enroll(
        mixed $selfieImage,
        array $livenessImages,
        Consent $consent,
        array $userDetails,
        ?bool $allowNewEnroll = null,
        ?string $callbackUrl = null,
        int|float|null $sandboxResult = null,
        ?array $partnerParams = null,
        ?array $metadata = null,
        ?string $userId = null,
    ): AcceptedResponse {
        UserDetails::validate($userDetails);
        Url::requireHttpsCallback($callbackUrl);

        $data = Operations::registration($this->transport, [
            'allow_new_enroll' => $allowNewEnroll,
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

    /**
     * @param array<string, mixed> $userDetails
     * @param BinaryInput|string|resource|null $selfieImage required unless useEnrolledImage
     * @param array<int, BinaryInput|string|resource>|null $livenessImages required unless useEnrolledImage
     * @param array<string, mixed>|null $partnerParams
     * @param array<int, mixed>|null $metadata
     */
    public function authenticate(
        string $userId,
        Consent $consent,
        array $userDetails,
        mixed $selfieImage = null,
        ?array $livenessImages = null,
        ?bool $useEnrolledImage = null,
        ?string $callbackUrl = null,
        int|float|null $sandboxResult = null,
        ?array $partnerParams = null,
        ?array $metadata = null,
    ): AcceptedResponse {
        UserDetails::validate($userDetails);
        Url::requireHttpsCallback($callbackUrl);

        if ($useEnrolledImage !== true) {
            if ($selfieImage === null || $livenessImages === null || $livenessImages === []) {
                throw new ValidationError('selfie_image and liveness_images are required unless use_enrolled_image is true.');
            }
        }

        $data = Operations::authentication($this->transport, [
            'user_id_body' => $userId,
            'use_enrolled_image' => $useEnrolledImage,
            'sandbox_result' => $sandboxResult,
            'callback_url' => $callbackUrl ?? $this->config->defaultCallbackUrl,
            'selfie_image' => $selfieImage,
            'liveness_images' => $livenessImages,
            'user_details' => $userDetails,
            'consent' => $consent,
            'partner_params' => $partnerParams,
            'metadata' => $metadata,
        ]);

        return AcceptedResponse::fromArray($data);
    }

    /**
     * @param BinaryInput|string|resource $selfieImage
     * @param BinaryInput|string|resource $comparisonImage
     * @param array<string, mixed> $userDetails
     * @param array<int, BinaryInput|string|resource>|null $livenessImages 6–8 images
     * @param array<string, mixed>|null $partnerParams
     * @param array<int, mixed>|null $metadata
     */
    public function compare(
        mixed $selfieImage,
        mixed $comparisonImage,
        string $comparisonImageType,
        Consent $consent,
        array $userDetails,
        ?array $livenessImages = null,
        ?bool $allowNewEnroll = null,
        ?string $userId = null,
        ?string $callbackUrl = null,
        int|float|null $sandboxResult = null,
        ?array $partnerParams = null,
        ?array $metadata = null,
    ): AcceptedResponse {
        UserDetails::validate($userDetails);
        Url::requireHttpsCallback($callbackUrl);

        // comparison_image_type is passed through verbatim: the server owns
        // that 400 (fleet decision).
        $data = Operations::compare($this->transport, [
            'comparison_image_type' => $comparisonImageType,
            'allow_new_enroll' => $allowNewEnroll,
            'user_id_body' => $userId,
            'sandbox_result' => $sandboxResult,
            'callback_url' => $callbackUrl ?? $this->config->defaultCallbackUrl,
            'selfie_image' => $selfieImage,
            'comparison_image' => $comparisonImage,
            'liveness_images' => $livenessImages,
            'user_details' => $userDetails,
            'consent' => $consent,
            'partner_params' => $partnerParams,
            'metadata' => $metadata,
        ]);

        return AcceptedResponse::fromArray($data);
    }
}
