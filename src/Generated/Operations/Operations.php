<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Operations;

use SmileIdentity\Client\ApiRequest;
use SmileIdentity\Client\Transport;
use SmileIdentity\Consent;
use SmileIdentity\Helpers\BinaryInput;
use SmileIdentity\Helpers\Multipart;

/**
 * Thin per-operation functions (§3). Each maps typed params to their wire
 * destinations, builds an {@see ApiRequest}, and sends it through the transport.
 *
 * This is the layer a generator would own later; it holds no auth or retry
 * logic (that lives in the hand-written client/ tree) and never renames a
 * wire field.
 *
 * @phpstan-type ScalarMap array<string, bool|int|float|string>
 * @phpstan-type EntryArgs array{
 *     country?: ?string, id_type?: ?string, id_number?: ?string,
 *     bank_code?: ?string, operator?: ?string, comparison_image_type?: ?string,
 *     allow_new_enroll?: ?bool, use_enrolled_image?: ?bool, user_id_body?: ?string,
 *     sandbox_result?: int|float|null, callback_url?: ?string,
 *     user_details: array<string, mixed>, consent: Consent,
 *     partner_params?: ?array<string, mixed>, metadata?: ?array<int, mixed>,
 *     selfie_image?: mixed, document?: mixed, document_back?: mixed,
 *     comparison_image?: mixed, liveness_images?: ?array<int, mixed>
 * }
 */
final class Operations
{
    /**
     * @param EntryArgs $args
     *
     * @return array<string, mixed>
     */
    public static function enhancedKyc(Transport $transport, array $args, ?string $userIdHeader): array
    {
        $parts = self::scalarParts($args, ['country', 'id_type', 'id_number', 'bank_code', 'operator', 'callback_url']);
        $parts = array_merge($parts, self::jsonParts($args));

        return $transport->send(new ApiRequest(
            method: 'POST',
            path: '/v3/enhanced_kyc',
            authenticated: true,
            idempotent: false,
            userIdHeader: $userIdHeader,
            multipart: $parts,
            bodyKind: ApiRequest::BODY_MULTIPART,
        ));
    }

    /**
     * @param EntryArgs $args
     *
     * @return array<string, mixed>
     */
    public static function documentVerification(Transport $transport, array $args, ?string $userIdHeader, bool $enhanced): array
    {
        $parts = self::scalarParts($args, ['country', 'id_type', 'callback_url']);
        $parts = array_merge($parts, self::binaryParts($args, ['selfie_image', 'document', 'document_back']));
        $parts = array_merge($parts, self::livenessParts($args));
        $parts = array_merge($parts, self::jsonParts($args));

        return $transport->send(new ApiRequest(
            method: 'POST',
            path: $enhanced ? '/v3/enhanced_document_verification' : '/v3/document_verification',
            authenticated: true,
            idempotent: false,
            needsPartnerIdHeader: true,
            userIdHeader: $userIdHeader,
            multipart: $parts,
            bodyKind: ApiRequest::BODY_MULTIPART,
        ));
    }

    /**
     * @param EntryArgs $args
     *
     * @return array<string, mixed>
     */
    public static function biometricKyc(Transport $transport, array $args, ?string $userIdHeader): array
    {
        $parts = self::scalarParts($args, ['country', 'id_type', 'id_number', 'sandbox_result', 'callback_url']);
        $parts = array_merge($parts, self::binaryParts($args, ['selfie_image']));
        $parts = array_merge($parts, self::livenessParts($args));
        $parts = array_merge($parts, self::jsonParts($args));

        return $transport->send(new ApiRequest(
            method: 'POST',
            path: '/v3/biometric_kyc',
            authenticated: true,
            idempotent: false,
            needsPartnerIdHeader: true,
            userIdHeader: $userIdHeader,
            multipart: $parts,
            bodyKind: ApiRequest::BODY_MULTIPART,
        ));
    }

    /**
     * @param EntryArgs $args
     *
     * @return array<string, mixed>
     */
    public static function registration(Transport $transport, array $args, ?string $userIdHeader): array
    {
        $parts = self::scalarParts($args, ['allow_new_enroll', 'sandbox_result', 'callback_url']);
        $parts = array_merge($parts, self::binaryParts($args, ['selfie_image']));
        $parts = array_merge($parts, self::livenessParts($args));
        $parts = array_merge($parts, self::jsonParts($args));

        return $transport->send(new ApiRequest(
            method: 'POST',
            path: '/v3/registration',
            authenticated: true,
            idempotent: false,
            userIdHeader: $userIdHeader,
            multipart: $parts,
            bodyKind: ApiRequest::BODY_MULTIPART,
        ));
    }

    /**
     * @param EntryArgs $args
     *
     * @return array<string, mixed>
     */
    public static function authentication(Transport $transport, array $args): array
    {
        // user_id is a required BODY field here, not the User-ID header.
        $parts = self::scalarParts($args, ['user_id_body', 'use_enrolled_image', 'sandbox_result', 'callback_url']);
        $parts = array_merge($parts, self::binaryParts($args, ['selfie_image']));
        $parts = array_merge($parts, self::livenessParts($args));
        $parts = array_merge($parts, self::jsonParts($args));

        return $transport->send(new ApiRequest(
            method: 'POST',
            path: '/v3/authentication',
            authenticated: true,
            idempotent: false,
            multipart: $parts,
            bodyKind: ApiRequest::BODY_MULTIPART,
        ));
    }

    /**
     * @param EntryArgs $args
     *
     * @return array<string, mixed>
     */
    public static function compare(Transport $transport, array $args): array
    {
        // user_id is an optional BODY field here.
        $parts = self::scalarParts($args, ['comparison_image_type', 'allow_new_enroll', 'user_id_body', 'sandbox_result', 'callback_url']);
        $parts = array_merge($parts, self::binaryParts($args, ['selfie_image', 'comparison_image']));
        $parts = array_merge($parts, self::livenessParts($args));
        $parts = array_merge($parts, self::jsonParts($args));

        return $transport->send(new ApiRequest(
            method: 'POST',
            path: '/v3/compare',
            authenticated: true,
            idempotent: false,
            multipart: $parts,
            bodyKind: ApiRequest::BODY_MULTIPART,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public static function statusRetrieve(Transport $transport, string $jobId): array
    {
        return $transport->send(
            new ApiRequest(
                method: 'GET',
                path: '/v3/status/' . rawurlencode($jobId),
                authenticated: true,
                idempotent: true,
            ),
            nonErrorStatuses: [404],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function replay(Transport $transport, string $jobId, ?string $callbackUrl): array
    {
        // Multipart with one callback_url part when overriding; otherwise no
        // body at all (any content type other than multipart gets a 415).
        $hasOverride = $callbackUrl !== null && $callbackUrl !== '';

        return $transport->send(new ApiRequest(
            method: 'POST',
            path: '/v3/replay/' . rawurlencode($jobId),
            authenticated: true,
            idempotent: false,
            multipart: $hasOverride ? [Multipart::scalar('callback_url', $callbackUrl)] : [],
            bodyKind: $hasOverride ? ApiRequest::BODY_MULTIPART : ApiRequest::BODY_NONE,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public static function reportFraud(
        Transport $transport,
        string $userId,
        bool $isFraud,
        string $reportedBy,
        ?string $reason,
        ?string $notes,
    ): array {
        $parts = [Multipart::scalar('is_fraud', $isFraud), Multipart::scalar('reported_by', $reportedBy)];
        if ($reason !== null) {
            $parts[] = Multipart::scalar('reason', $reason);
        }
        if ($notes !== null) {
            $parts[] = Multipart::scalar('notes', $notes);
        }

        return $transport->send(new ApiRequest(
            method: 'POST',
            path: '/v3/users/' . rawurlencode($userId) . '/report_fraud',
            authenticated: true,
            idempotent: false,
            multipart: $parts,
            bodyKind: ApiRequest::BODY_MULTIPART,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public static function bankCodes(Transport $transport, ?string $country): array
    {
        return $transport->send(new ApiRequest(
            method: 'GET',
            path: '/v3/services/bank_codes',
            authenticated: false,
            idempotent: true,
            query: $country !== null ? ['country' => $country] : [],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public static function supportedIdTypes(Transport $transport, ?string $country): array
    {
        return $transport->send(new ApiRequest(
            method: 'GET',
            path: '/v3/services/supported_id_types',
            authenticated: false,
            idempotent: true,
            query: $country !== null ? ['country' => $country] : [],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public static function supportedDocuments(
        Transport $transport,
        ?string $continent,
        ?string $countryCode,
        ?string $locale,
    ): array {
        $query = [];
        if ($continent !== null) {
            $query['continent'] = $continent;
        }
        if ($countryCode !== null) {
            $query['country_code'] = $countryCode;
        }
        if ($locale !== null) {
            $query['locale'] = $locale;
        }

        return $transport->send(new ApiRequest(
            method: 'GET',
            path: '/v3/services/supported_documents',
            authenticated: false,
            idempotent: true,
            query: $query,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public static function idStatus(Transport $transport, string $country, string $idType): array
    {
        return $transport->send(new ApiRequest(
            method: 'GET',
            path: '/v3/services/id_status',
            authenticated: true,
            idempotent: true,
            query: ['country' => $country, 'id_type' => $idType],
        ));
    }

    /**
     * Build plain text parts for the scalar fields present in $args.
     *
     * @param EntryArgs $args
     * @param list<string> $fields wire field names, mapped from the args keys
     *
     * @return list<array<string, mixed>>
     */
    private static function scalarParts(array $args, array $fields): array
    {
        $parts = [];
        foreach ($fields as $field) {
            // user_id lives under user_id_body in $args but is named user_id on the wire.
            $argKey = $field === 'user_id' ? 'user_id_body' : $field;
            $wireName = $field === 'user_id_body' ? 'user_id' : $field;
            $value = $args[$argKey] ?? null;
            if ($value === null) {
                continue;
            }
            /** @var bool|int|float|string $value */
            $parts[] = Multipart::scalar($wireName, $value);
        }

        return $parts;
    }

    /**
     * @param EntryArgs $args
     * @param list<string> $fields binary field names
     *
     * @return list<array<string, mixed>>
     */
    private static function binaryParts(array $args, array $fields): array
    {
        $parts = [];
        foreach ($fields as $field) {
            $value = $args[$field] ?? null;
            if ($value === null) {
                continue;
            }
            // document/document_back may be image/jpeg or image/png; the rest are jpeg only.
            $allowPng = $field === 'document' || $field === 'document_back';
            $parts[] = BinaryInput::coerce($value)->toMultipartPart($field, $field . '.jpg', 'image/jpeg', $allowPng);
        }

        return $parts;
    }

    /**
     * Repeated liveness_images parts — one per image, same field name.
     *
     * @param EntryArgs $args
     *
     * @return list<array<string, mixed>>
     */
    private static function livenessParts(array $args): array
    {
        $images = $args['liveness_images'] ?? null;
        if (!is_array($images)) {
            return [];
        }

        $parts = [];
        $index = 0;
        foreach ($images as $image) {
            ++$index;
            $parts[] = BinaryInput::coerce($image)->toMultipartPart(
                'liveness_images',
                "liveness_{$index}.jpg",
                'image/jpeg',
            );
        }

        return $parts;
    }

    /**
     * JSON object/array parts: user_details, consent, partner_params, metadata.
     *
     * @param EntryArgs $args
     *
     * @return list<array<string, mixed>>
     */
    private static function jsonParts(array $args): array
    {
        $parts = [
            Multipart::json('user_details', $args['user_details']),
            Multipart::json('consent', $args['consent']->toArray()),
        ];

        if (isset($args['partner_params'])) {
            $parts[] = Multipart::json('partner_params', $args['partner_params']);
        }
        if (isset($args['metadata'])) {
            $parts[] = Multipart::json('metadata', $args['metadata']);
        }

        return $parts;
    }
}
