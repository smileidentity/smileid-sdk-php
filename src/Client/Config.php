<?php

declare(strict_types=1);

namespace SmileIdentity\Client;

use SmileIdentity\Errors\ValidationError;
use SmileIdentity\Helpers\Url;

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
        public readonly ?string $defaultCallbackUrl = null,
        ?string $baseUrl = null,
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

        Url::requireHttpsCallback($defaultCallbackUrl, 'default_callback_url');

        $this->baseUrl = rtrim(Url::requireHttpsBase($baseUrl ?? self::defaultBaseUrl($environment)), '/');
    }

    private static function defaultBaseUrl(string $environment): string
    {
        return $environment === 'production' ? self::PRODUCTION_BASE_URL : self::SANDBOX_BASE_URL;
    }
}
