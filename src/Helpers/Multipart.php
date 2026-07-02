<?php

declare(strict_types=1);

namespace SmileIdentity\Helpers;

use SmileIdentity\Errors\ValidationError;

/**
 * Builders for the Guzzle `multipart` entries used by every entry endpoint.
 *
 * Applies the §5.3 rules: scalars become plain text parts, objects/arrays
 * become JSON parts with an application/json content type, and binary arrays
 * (liveness_images) become repeated parts sharing one field name — never CSV
 * or indexed names.
 */
final class Multipart
{
    /**
     * A plain text part. Booleans render as "true"/"false", numbers as their
     * decimal string.
     *
     * @return array{name: string, contents: string}
     */
    public static function scalar(string $name, bool|int|float|string $value): array
    {
        if (is_bool($value)) {
            $contents = $value ? 'true' : 'false';
        } else {
            $contents = (string) $value;
        }

        return ['name' => $name, 'contents' => $contents];
    }

    /**
     * A JSON object/array part with Content-Type: application/json.
     *
     * @param array<mixed>|object $value
     *
     * @return array{name: string, contents: string, headers: array<string, string>}
     */
    public static function json(string $name, array|object $value): array
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new ValidationError("Unable to JSON-encode multipart part '{$name}'.");
        }

        return [
            'name' => $name,
            'contents' => $encoded,
            'headers' => ['Content-Type' => 'application/json'],
        ];
    }
}
