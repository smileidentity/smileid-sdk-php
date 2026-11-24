<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/**
 * A Smile Link resource, from GET and PUT /v3/smile_links/{refId} and the
 * listing. The whole body is kept in $data, so fields not typed here stay
 * readable.
 */
final class SmileLink
{
    /**
     * @param list<array<string, mixed>> $idTypes verification configurations
     *     (country, id_type, verification_method)
     * @param array<string, mixed>|null $partnerParams
     * @param list<string> $tokenProvidedFields
     * @param array<string, mixed> $tokenProvidedValues
     * @param SmileLinkJobStats|null $jobStats outcome counts; present only on listed links
     * @param array<string, mixed> $data the whole response body
     */
    public function __construct(
        public readonly string $refId,
        public readonly string $link,
        public readonly string $name,
        public readonly ?string $companyName,
        public readonly ?string $description,
        public readonly array $idTypes,
        public readonly ?bool $isSingleUse,
        public readonly ?string $callbackUrl,
        public readonly ?string $noticePrivacyPolicyUrl,
        public readonly ?string $logoUrl,
        public readonly ?string $redirectUrl,
        public readonly ?string $themeColor,
        public readonly ?string $expiresAt,
        public readonly ?string $disabledAt,
        public readonly ?array $partnerParams,
        public readonly ?string $userId,
        public readonly array $tokenProvidedFields,
        public readonly array $tokenProvidedValues,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        public readonly ?SmileLinkJobStats $jobStats,
        public readonly array $data,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $idTypes = [];
        foreach (is_array($data['id_types'] ?? null) ? $data['id_types'] : [] as $idType) {
            if (is_array($idType)) {
                $idTypes[] = $idType;
            }
        }
        $fields = [];
        foreach (is_array($data['token_provided_fields'] ?? null) ? $data['token_provided_fields'] : [] as $field) {
            $fields[] = (string) $field;
        }

        return new self(
            refId: (string) ($data['ref_id'] ?? ''),
            link: (string) ($data['link'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            companyName: isset($data['company_name']) ? (string) $data['company_name'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            idTypes: $idTypes,
            isSingleUse: isset($data['is_single_use']) ? (bool) $data['is_single_use'] : null,
            callbackUrl: isset($data['callback_url']) ? (string) $data['callback_url'] : null,
            noticePrivacyPolicyUrl: isset($data['notice_privacy_policy_url']) ? (string) $data['notice_privacy_policy_url'] : null,
            logoUrl: isset($data['logo_url']) ? (string) $data['logo_url'] : null,
            redirectUrl: isset($data['redirect_url']) ? (string) $data['redirect_url'] : null,
            themeColor: isset($data['theme_color']) ? (string) $data['theme_color'] : null,
            expiresAt: isset($data['expires_at']) ? (string) $data['expires_at'] : null,
            disabledAt: isset($data['disabled_at']) ? (string) $data['disabled_at'] : null,
            partnerParams: is_array($data['partner_params'] ?? null) ? $data['partner_params'] : null,
            userId: isset($data['user_id']) ? (string) $data['user_id'] : null,
            tokenProvidedFields: $fields,
            tokenProvidedValues: is_array($data['token_provided_values'] ?? null) ? $data['token_provided_values'] : [],
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            jobStats: isset($data['total_jobs']) ? SmileLinkJobStats::fromArray($data) : null,
            data: $data,
        );
    }
}
