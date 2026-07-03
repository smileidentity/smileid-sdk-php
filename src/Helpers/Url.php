<?php

declare(strict_types=1);

namespace SmileIdentity\Helpers;

use SmileIdentity\Errors\ValidationError;

/**
 * https-only URL validation. Base URLs must be absolute https with no query
 * or fragment; callback URLs must be absolute https. Both rules are fleet
 * standards, deliberately stricter than the API spec's plain "uri".
 */
final class Url
{
    /**
     * @return string the validated URL
     */
    public static function requireHttpsBase(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false
            || ($parts['scheme'] ?? '') !== 'https'
            || ($parts['host'] ?? '') === ''
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new ValidationError(
                'base_url must be an absolute https URL with no query or fragment.',
            );
        }

        return $url;
    }

    public static function requireHttpsCallback(?string $url, string $field = 'callback_url'): void
    {
        if ($url === null) {
            return;
        }

        $parts = parse_url($url);
        if ($parts === false
            || ($parts['scheme'] ?? '') !== 'https'
            || ($parts['host'] ?? '') === ''
        ) {
            throw new ValidationError("{$field} must be an absolute https URL.");
        }
    }
}
