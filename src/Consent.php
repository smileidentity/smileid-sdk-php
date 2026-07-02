<?php

declare(strict_types=1);

namespace SmileIdentity;

use SmileIdentity\Errors\ValidationError;

/**
 * Consent block required on all seven entry endpoints.
 *
 * Build it with {@see Consent::granted()}, which always sets granted=true.
 * Serialized as a JSON multipart part named `consent`.
 */
final class Consent
{
    public function __construct(
        public readonly bool $granted,
        public readonly string $grantedAt,
        public readonly string $noticeLanguage,
        public readonly string $noticePrivacyPolicyUrl,
    ) {
    }

    /**
     * @param \DateTimeInterface|string $grantedAt a timestamp; DateTime values are
     *     rendered as ISO 8601 with milliseconds in UTC
     */
    public static function granted(
        \DateTimeInterface|string $grantedAt,
        string $noticeLanguage,
        string $noticePrivacyPolicyUrl,
    ): self {
        $timestamp = $grantedAt instanceof \DateTimeInterface
            ? self::formatTimestamp($grantedAt)
            : $grantedAt;

        if (preg_match('/^[A-Z]{2}$/', $noticeLanguage) !== 1) {
            throw new ValidationError('notice_language must be a two-letter uppercase ISO 639-1 code.');
        }

        return new self(
            granted: true,
            grantedAt: $timestamp,
            noticeLanguage: $noticeLanguage,
            noticePrivacyPolicyUrl: $noticePrivacyPolicyUrl,
        );
    }

    private static function formatTimestamp(\DateTimeInterface $value): string
    {
        $utc = \DateTimeImmutable::createFromInterface($value)
            ->setTimezone(new \DateTimeZone('UTC'));

        return $utc->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * @return array{granted: bool, granted_at: string, notice_language: string, notice_privacy_policy_url: string}
     */
    public function toArray(): array
    {
        return [
            'granted' => $this->granted,
            'granted_at' => $this->grantedAt,
            'notice_language' => $this->noticeLanguage,
            'notice_privacy_policy_url' => $this->noticePrivacyPolicyUrl,
        ];
    }
}
