<?php

declare(strict_types=1);

namespace SmileIdentity\Client\Resources;

use SmileIdentity\Client\Config;
use SmileIdentity\Client\Transport;
use SmileIdentity\Consent;
use SmileIdentity\Generated\Models\AcceptedResponse;
use SmileIdentity\Generated\Operations\Operations;
use SmileIdentity\Helpers\BinaryInput;
use SmileIdentity\Helpers\UserDetails;
use SmileIdentity\Helpers\Validation;

/**
 * documents.verify → POST /v3/document_verification
 * documents.verifyEnhanced → POST /v3/enhanced_document_verification
 *
 * Both require the SmileID-Partner-ID header.
 */
final class DocumentsResource
{
    public function __construct(
        private readonly Transport $transport,
        private readonly Config $config,
    ) {
    }

    /**
     * @param BinaryInput|string|resource $selfieImage
     * @param array<int, BinaryInput|string|resource> $livenessImages 6–8 images
     * @param BinaryInput|string|resource $document
     * @param array<string, mixed> $userDetails
     * @param BinaryInput|string|resource|null $documentBack
     * @param array<string, mixed>|null $partnerParams
     * @param array<int, mixed>|null $metadata
     */
    public function verify(
        mixed $selfieImage,
        array $livenessImages,
        mixed $document,
        Consent $consent,
        string $country,
        array $userDetails,
        ?string $idType = null,
        mixed $documentBack = null,
        ?string $callbackUrl = null,
        ?array $partnerParams = null,
        ?array $metadata = null,
        ?string $userId = null,
    ): AcceptedResponse {
        return $this->submit(
            enhanced: false,
            selfieImage: $selfieImage,
            livenessImages: $livenessImages,
            document: $document,
            consent: $consent,
            country: $country,
            userDetails: $userDetails,
            idType: $idType,
            documentBack: $documentBack,
            callbackUrl: $callbackUrl,
            partnerParams: $partnerParams,
            metadata: $metadata,
            userId: $userId,
        );
    }

    /**
     * @param BinaryInput|string|resource $selfieImage
     * @param array<int, BinaryInput|string|resource> $livenessImages 6–8 images
     * @param BinaryInput|string|resource $document
     * @param array<string, mixed> $userDetails
     * @param BinaryInput|string|resource|null $documentBack
     * @param array<string, mixed>|null $partnerParams
     * @param array<int, mixed>|null $metadata
     */
    public function verifyEnhanced(
        mixed $selfieImage,
        array $livenessImages,
        mixed $document,
        Consent $consent,
        string $country,
        string $idType,
        array $userDetails,
        mixed $documentBack = null,
        ?string $callbackUrl = null,
        ?array $partnerParams = null,
        ?array $metadata = null,
        ?string $userId = null,
    ): AcceptedResponse {
        return $this->submit(
            enhanced: true,
            selfieImage: $selfieImage,
            livenessImages: $livenessImages,
            document: $document,
            consent: $consent,
            country: $country,
            userDetails: $userDetails,
            idType: $idType,
            documentBack: $documentBack,
            callbackUrl: $callbackUrl,
            partnerParams: $partnerParams,
            metadata: $metadata,
            userId: $userId,
        );
    }

    /**
     * @param BinaryInput|string|resource $selfieImage
     * @param array<int, BinaryInput|string|resource> $livenessImages
     * @param BinaryInput|string|resource $document
     * @param array<string, mixed> $userDetails
     * @param BinaryInput|string|resource|null $documentBack
     * @param array<string, mixed>|null $partnerParams
     * @param array<int, mixed>|null $metadata
     */
    private function submit(
        bool $enhanced,
        mixed $selfieImage,
        array $livenessImages,
        mixed $document,
        Consent $consent,
        string $country,
        array $userDetails,
        ?string $idType,
        mixed $documentBack,
        ?string $callbackUrl,
        ?array $partnerParams,
        ?array $metadata,
        ?string $userId,
    ): AcceptedResponse {
        UserDetails::validate($userDetails);
        Validation::livenessImages($livenessImages);
        $resolvedCallbackUrl = $callbackUrl ?? $this->config->defaultCallbackUrl;
        Validation::callbackUrl($resolvedCallbackUrl);

        $data = Operations::documentVerification($this->transport, [
            'country' => $country,
            'id_type' => $idType,
            'callback_url' => $resolvedCallbackUrl,
            'selfie_image' => $selfieImage,
            'document' => $document,
            'document_back' => $documentBack,
            'liveness_images' => $livenessImages,
            'user_details' => $userDetails,
            'consent' => $consent,
            'partner_params' => $partnerParams,
            'metadata' => $metadata,
        ], $userId, $enhanced);

        return AcceptedResponse::fromArray($data);
    }
}
