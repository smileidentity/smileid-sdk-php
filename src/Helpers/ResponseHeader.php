<?php

declare(strict_types=1);

namespace SmileIdentity\Helpers;

/** Reads a single response header value. */
final class ResponseHeader
{
    /**
     * The trimmed value of the named header, or null when it is absent, empty
     * or only whitespace. Header names are matched case-insensitively.
     *
     * @param array<string, string> $headers response headers
     */
    public static function get(array $headers, string $name): ?string
    {
        $value = trim(array_change_key_case($headers, CASE_LOWER)[strtolower($name)] ?? '');

        return $value !== '' ? $value : null;
    }
}
