<?php

declare(strict_types=1);

namespace SmileIdentity\Client\Auth;

/**
 * Optional HMAC request signing (§2.5) — OFF unless a partner secret is set.
 *
 * ⚠️ Provisional construction. The exact message/encoding is not defined in the
 * spec, so this is the single place to correct once the backend contract is
 * confirmed. Current construction:
 *   hex( HMAC_SHA256(key = partner_secret, message = timestamp + raw_body_bytes) )
 */
final class HmacSigner
{
    public function __construct(
        private readonly string $partnerSecret,
    ) {
    }

    /**
     * @return array{SmileID-Timestamp: string, SmileID-Request-Signature: string}
     */
    public function headers(string $bodyBytes, ?\DateTimeImmutable $now = null): array
    {
        $timestamp = ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.v\Z');

        $signature = hash_hmac('sha256', $timestamp . $bodyBytes, $this->partnerSecret);

        return [
            'SmileID-Timestamp' => $timestamp,
            'SmileID-Request-Signature' => $signature,
        ];
    }
}
