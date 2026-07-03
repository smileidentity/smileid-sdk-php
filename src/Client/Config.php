<?php

declare(strict_types=1);

namespace SmileIdentity\Client;

use SmileIdentity\Errors\ValidationError;

/**
 * Immutable client configuration (§2.1). Constructed once and shared with the
 * transport and auth layers.
 */
final class Config
{
    public const SANDBOX_BASE_URL = 'https://testapi.smileidentity.com';
    public const PRODUCTION_BASE_URL = 'https://api.smileidentity.com';

    public readonly string $baseUrl;

    public function __construct(
        public readonly string $partnerId,
        public readonly string $apiKey,
        public readonly string $environment = 'sandbox',
        public readonly ?string $partnerSecret = null,
        public readonly ?string $defaultCallbackUrl = null,
        ?string $baseUrl = null,
        public readonly bool $allowInsecureBaseUrl = false,
        public readonly float $timeout = 30.0,
        public readonly int $maxRetries = 2,
    ) {
        if (preg_match('/^[1-9]\d*$/', $partnerId) !== 1) {
            throw new ValidationError('partner_id must be a numeric string with no leading zeros.');
        }
        if ($apiKey === '') {
            throw new ValidationError('api_key is required.');
        }
        if (!in_array($environment, ['sandbox', 'production'], true)) {
            throw new ValidationError("environment must be 'sandbox' or 'production'.");
        }

        $this->baseUrl = self::normalizeBaseUrl($baseUrl ?? self::defaultBaseUrl($environment), $allowInsecureBaseUrl);
        if ($defaultCallbackUrl !== null && $defaultCallbackUrl !== '') {
            self::validateCallbackUrl($defaultCallbackUrl);
        }
    }

    private static function defaultBaseUrl(string $environment): string
    {
        return $environment === 'production' ? self::PRODUCTION_BASE_URL : self::SANDBOX_BASE_URL;
    }

    public function hmacEnabled(): bool
    {
        return $this->partnerSecret !== null && $this->partnerSecret !== '';
    }

    public static function validateCallbackUrl(string $url): void
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new ValidationError('callback_url must be an absolute URL.');
        }
        if ($parts['scheme'] !== 'https') {
            throw new ValidationError('callback_url must use https.');
        }
    }

    private static function normalizeBaseUrl(string $url, bool $allowInsecure): string
    {
        $url = rtrim(trim($url), '/');
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new ValidationError('base_url must be an absolute URL.');
        }
        if (isset($parts['query']) || isset($parts['fragment'])) {
            throw new ValidationError('base_url must not include query or fragment.');
        }
        if ($parts['scheme'] === 'https') {
            return $url;
        }
        if ($allowInsecure && $parts['scheme'] === 'http' && self::isLoopbackHost($parts['host'])) {
            return $url;
        }

        throw new ValidationError('base_url must use https.');
    }

    private static function isLoopbackHost(string $host): bool
    {
        return $host === 'localhost' || $host === '::1' || str_starts_with($host, '127.');
    }
}
