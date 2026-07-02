<?php

declare(strict_types=1);

namespace SmileIdentity\Client\Auth;

/**
 * Manages the internal JWT (§2.3). Partners never see it.
 *
 * The token is cached until its decoded `exp` claim minus a 60s skew. If the
 * claim cannot be decoded the token is treated as single-use and refreshed on
 * the next call. PHP's request model is single-threaded, so no OS-level mutex
 * is needed; the cache lives on this instance and is shared by all resources.
 */
final class TokenManager
{
    private const EXPIRY_SKEW_SECONDS = 60;

    private ?string $token = null;
    private ?int $expiresAt = null;

    /**
     * @param callable(): string $fetcher returns a fresh JWT (or throws)
     */
    public function __construct(
        private $fetcher,
    ) {
    }

    public function ensureToken(): string
    {
        if ($this->token !== null && $this->expiresAt !== null && time() < $this->expiresAt) {
            return $this->token;
        }

        return $this->refresh();
    }

    public function refresh(): string
    {
        $jwt = ($this->fetcher)();
        $exp = self::decodeExp($jwt);

        $this->token = $jwt;
        // A decodable exp caches until exp - skew; otherwise force a refresh next call.
        $this->expiresAt = $exp !== null ? $exp - self::EXPIRY_SKEW_SECONDS : 0;

        return $jwt;
    }

    public function invalidate(): void
    {
        $this->token = null;
        $this->expiresAt = null;
    }

    private static function decodeExp(string $jwt): ?int
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return null;
        }

        $payload = self::base64UrlDecode($parts[1]);
        if ($payload === null) {
            return null;
        }

        $claims = json_decode($payload, true);
        if (!is_array($claims) || !isset($claims['exp']) || !is_numeric($claims['exp'])) {
            return null;
        }

        return (int) $claims['exp'];
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
