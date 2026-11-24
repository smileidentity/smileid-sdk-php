<?php

declare(strict_types=1);

namespace SmileIdentity\Client\Resources;

use SmileIdentity\Client\Config;
use SmileIdentity\Client\Transport;
use SmileIdentity\Errors\ValidationError;
use SmileIdentity\Generated\Models\SmileLink;
use SmileIdentity\Generated\Models\SmileLinkCreated;
use SmileIdentity\Generated\Models\SmileLinkListResponse;
use SmileIdentity\Generated\Operations\Operations;
use SmileIdentity\Helpers\Url;

/**
 * smileLinks.create → POST /v3/smile_links, smileLinks.list → GET /v3/smile_links,
 * smileLinks.get → GET /v3/smile_links/{refId} and
 * smileLinks.update → PUT /v3/smile_links/{refId}.
 *
 * Smile Links tokens carry no product claim.
 */
final class SmileLinksResource
{
    public function __construct(
        private readonly Transport $transport,
        private readonly Config $config,
    ) {
    }

    /**
     * Create a Smile Link: a URL you hand to end users to run a verification
     * flow. Read the full link with {@see get()}.
     *
     * @param list<array<string, mixed>> $idTypes verification configurations, each
     *     with `country`, `id_type` and `verification_method` (biometric_kyc,
     *     document_verification, enhanced_document_verification or enhanced_kyc)
     * @param string|null $expiresAt ISO 8601 timestamp
     * @param array<string, mixed>|null $partnerParams
     */
    public function create(
        array $idTypes,
        string $name,
        string $noticePrivacyPolicyUrl,
        bool $isSingleUse,
        ?string $companyName = null,
        ?string $callbackUrl = null,
        ?string $description = null,
        ?string $expiresAt = null,
        ?string $logoUrl = null,
        ?string $redirectUrl = null,
        ?string $themeColor = null,
        ?array $partnerParams = null,
        ?string $userId = null,
    ): SmileLinkCreated {
        self::requireIdTypes($idTypes);
        if (trim($name) === '') {
            throw new ValidationError('name must not be empty.');
        }
        Url::requireHttpsCallback($noticePrivacyPolicyUrl, 'noticePrivacyPolicyUrl');
        Url::requireHttpsCallback($callbackUrl, 'callbackUrl');
        Url::requireHttpsCallback($logoUrl, 'logoUrl');
        Url::requireHttpsCallback($redirectUrl, 'redirectUrl');

        return SmileLinkCreated::fromArray(Operations::createSmileLink(
            $this->transport,
            $idTypes,
            $name,
            $noticePrivacyPolicyUrl,
            $isSingleUse,
            $companyName,
            $callbackUrl ?? $this->config->defaultCallbackUrl,
            $description,
            $expiresAt,
            $logoUrl,
            $redirectUrl,
            $themeColor,
            $partnerParams,
            $userId,
        ));
    }

    /**
     * One page of your v3 Smile Links, newest first, each with its job outcome
     * counts in `jobStats`. `$limit` is 1 to 100 (the API defaults to 20) and
     * `$page` starts at 1. `$singleUse` filters to single-use (true) or reusable
     * (false) links.
     */
    public function list(?int $limit = null, ?int $page = null, ?bool $singleUse = null): SmileLinkListResponse
    {
        if ($limit !== null && ($limit < 1 || $limit > 100)) {
            throw new ValidationError('limit must be between 1 and 100.');
        }
        if ($page !== null && $page < 1) {
            throw new ValidationError('page must be at least 1.');
        }

        return SmileLinkListResponse::fromArray(Operations::listSmileLinks($this->transport, $limit, $page, $singleUse));
    }

    /** Read a link by the `refId` returned when it was created. */
    public function get(string $refId): SmileLink
    {
        self::requireRefId($refId);

        return SmileLink::fromArray(Operations::getSmileLink($this->transport, $refId));
    }

    /**
     * Replace a link's `id_types` and/or disable (true) or re-enable (false)
     * it, and return the updated link. Send at least one of the two.
     *
     * @param list<array<string, mixed>>|null $idTypes
     */
    public function update(string $refId, ?array $idTypes = null, ?bool $isDisabled = null): SmileLink
    {
        self::requireRefId($refId);
        if ($idTypes === null && $isDisabled === null) {
            throw new ValidationError('idTypes or isDisabled is required.');
        }
        if ($idTypes !== null) {
            self::requireIdTypes($idTypes);
            if (count($idTypes) > 50) {
                throw new ValidationError('idTypes must contain at most 50 entries.');
            }
        }

        return SmileLink::fromArray(Operations::updateSmileLink($this->transport, $refId, $idTypes, $isDisabled));
    }

    /** @param list<array<string, mixed>> $idTypes */
    private static function requireIdTypes(array $idTypes): void
    {
        if ($idTypes === []) {
            throw new ValidationError('idTypes must contain at least one entry.');
        }
    }

    private static function requireRefId(string $refId): void
    {
        if (trim($refId) === '') {
            throw new ValidationError('refId must not be empty.');
        }
    }
}
