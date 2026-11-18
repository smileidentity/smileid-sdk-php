<?php

declare(strict_types=1);

namespace SmileIdentity\Client\Resources;

use SmileIdentity\Client\Config;
use SmileIdentity\Client\Transport;
use SmileIdentity\Consent;
use SmileIdentity\Errors\ValidationError;
use SmileIdentity\Generated\Models\AcceptedResponse;
use SmileIdentity\Generated\Models\AmlMonitoringNewsResponse;
use SmileIdentity\Generated\Models\OneTimeAmlResult;
use SmileIdentity\Generated\Operations\Operations;
use SmileIdentity\Helpers\Url;
use SmileIdentity\Helpers\UserDetails;

/**
 * aml.oneTime → POST /v3/aml, aml.oneTimeSync → POST /v3/aml/sync,
 * aml.monitoring → POST /v3/aml/monitoring and
 * aml.monitoringNews → GET /v3/aml/monitoring/{job_id}/news/{reference}.
 */
final class AmlResource
{
    public const NEWS_CATEGORIES = [
        'arms_trafficking',
        'credit_update',
        'environmental_crime',
        'financial_crime',
        'human_rights_violation',
        'leadership_change',
        'legal_risk',
        'location_change',
        'narcotics',
        'other',
        'other_crime',
        'ownership_change',
        'product_change',
        'regulatory',
        'sexual_crime',
        'technology_event',
        'terrorism',
        'violent_crime',
    ];

    public function __construct(
        private readonly Transport $transport,
        private readonly Config $config,
    ) {
    }

    /**
     * Screen a name against sanctions, PEP and adverse-media lists; the result
     * arrives on the callback URL.
     *
     * @param list<string> $countries ISO 3166-1 alpha-2 codes
     * @param array<string, mixed> $userDetails
     * @param list<string>|null $aliases
     * @param array<string, mixed>|null $partnerParams
     * @param array<int, mixed>|null $metadata
     */
    public function oneTime(
        array $countries,
        string $fullName,
        array $userDetails,
        Consent $consent,
        ?string $callbackUrl = null,
        ?string $birthYear = null,
        ?bool $strictMatch = null,
        ?array $aliases = null,
        ?bool $searchExistingUser = null,
        ?string $userId = null,
        ?array $partnerParams = null,
        ?array $metadata = null,
    ): AcceptedResponse {
        $this->validate($countries, $fullName, $birthYear, $aliases, $userDetails);
        Url::requireHttpsCallback($callbackUrl);

        return AcceptedResponse::fromArray(Operations::oneTimeAml(
            $this->transport,
            false,
            $countries,
            $fullName,
            $birthYear,
            $strictMatch,
            $aliases,
            $searchExistingUser,
            $userId,
            $userDetails,
            $consent,
            $callbackUrl ?? $this->config->defaultCallbackUrl,
            $partnerParams,
            $metadata,
            null,
        ));
    }

    /**
     * Like {@see oneTime()}, but returns the terminal result in the response.
     * The callback URL is optional; no callback is sent. Pass an idempotency
     * key (16 to 128 characters of A-Z a-z 0-9 - _) to make a retry resolve to
     * the same screening; the call is only retried automatically with a key.
     *
     * @param list<string> $countries ISO 3166-1 alpha-2 codes
     * @param array<string, mixed> $userDetails
     * @param list<string>|null $aliases
     * @param array<string, mixed>|null $partnerParams
     * @param array<int, mixed>|null $metadata
     */
    public function oneTimeSync(
        array $countries,
        string $fullName,
        array $userDetails,
        Consent $consent,
        ?string $callbackUrl = null,
        ?string $birthYear = null,
        ?bool $strictMatch = null,
        ?array $aliases = null,
        ?bool $searchExistingUser = null,
        ?string $userId = null,
        ?array $partnerParams = null,
        ?array $metadata = null,
        ?string $idempotencyKey = null,
    ): OneTimeAmlResult {
        $this->validate($countries, $fullName, $birthYear, $aliases, $userDetails);
        Url::requireHttpsCallback($callbackUrl);
        if ($idempotencyKey !== null && preg_match('/^[A-Za-z0-9_-]{16,128}$/', $idempotencyKey) !== 1) {
            throw new ValidationError('idempotencyKey must be 16 to 128 characters of A-Z a-z 0-9 - _.');
        }

        return OneTimeAmlResult::fromArray(Operations::oneTimeAml(
            $this->transport,
            true,
            $countries,
            $fullName,
            $birthYear,
            $strictMatch,
            $aliases,
            $searchExistingUser,
            $userId,
            $userDetails,
            $consent,
            $callbackUrl ?? $this->config->defaultCallbackUrl,
            $partnerParams,
            $metadata,
            $idempotencyKey,
        ));
    }

    /**
     * Screen a name and enrol it for ongoing AML monitoring. `fullName` is
     * required unless `searchExistingUser` is true.
     *
     * @param list<string> $countries ISO 3166-1 alpha-2 codes
     * @param array<string, mixed> $userDetails
     * @param list<string>|null $aliases
     * @param array<string, mixed>|null $partnerParams
     * @param array<int, mixed>|null $metadata
     */
    public function monitoring(
        array $countries,
        array $userDetails,
        Consent $consent,
        ?string $fullName = null,
        ?string $callbackUrl = null,
        ?string $birthYear = null,
        ?bool $strictMatch = null,
        ?array $aliases = null,
        ?bool $searchExistingUser = null,
        ?string $userId = null,
        ?array $partnerParams = null,
        ?array $metadata = null,
    ): AcceptedResponse {
        if ($fullName === null && $searchExistingUser !== true) {
            throw new ValidationError('fullName is required unless searchExistingUser is true.');
        }
        if ($searchExistingUser === true && ($userId === null || trim($userId) === '')) {
            throw new ValidationError('userId is required when searchExistingUser is true.');
        }
        $this->validate($countries, $fullName ?? 'n/a', $birthYear, $aliases, $userDetails);
        Url::requireHttpsCallback($callbackUrl);

        return AcceptedResponse::fromArray(Operations::amlMonitoring(
            $this->transport,
            $countries,
            $fullName,
            $birthYear,
            $strictMatch,
            $aliases,
            $searchExistingUser,
            $userId,
            $userDetails,
            $consent,
            $callbackUrl ?? $this->config->defaultCallbackUrl,
            $partnerParams,
            $metadata,
        ));
    }

    /**
     * One page of adverse-media articles for a person in a monitoring job.
     *
     * @param string $newsCategory one of {@see NEWS_CATEGORIES}
     */
    public function monitoringNews(
        string $jobId,
        string $reference,
        string $newsCategory,
        ?int $page = null,
        ?int $limit = null,
    ): AmlMonitoringNewsResponse {
        if (!in_array($newsCategory, self::NEWS_CATEGORIES, true)) {
            throw new ValidationError('newsCategory must be one of: ' . implode(', ', self::NEWS_CATEGORIES) . '.');
        }
        if ($page !== null && ($page < 1 || $page > 100)) {
            throw new ValidationError('page must be between 1 and 100.');
        }
        if ($limit !== null && ($limit < 1 || $limit > 100)) {
            throw new ValidationError('limit must be between 1 and 100.');
        }

        return AmlMonitoringNewsResponse::fromArray(
            Operations::amlMonitoringNews($this->transport, $jobId, $reference, $newsCategory, $page, $limit),
        );
    }

    /**
     * @param list<string> $countries
     * @param list<string>|null $aliases
     * @param array<string, mixed> $userDetails
     */
    private function validate(array $countries, string $fullName, ?string $birthYear, ?array $aliases, array $userDetails): void
    {
        if ($countries === []) {
            throw new ValidationError('countries must contain at least one country code.');
        }
        foreach ($countries as $country) {
            if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
                throw new ValidationError('countries must be uppercase ISO 3166-1 alpha-2 codes.');
            }
        }
        if (trim($fullName) === '') {
            throw new ValidationError('fullName must not be empty.');
        }
        if ($birthYear !== null && preg_match('/^\d{4}$/', $birthYear) !== 1) {
            throw new ValidationError('birthYear must be a four digit year.');
        }
        if ($aliases !== null && count($aliases) > 20) {
            throw new ValidationError('aliases must contain at most 20 entries.');
        }
        UserDetails::validate($userDetails);
    }
}
