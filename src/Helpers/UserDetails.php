<?php

declare(strict_types=1);

namespace SmileIdentity\Helpers;

use SmileIdentity\Errors\ValidationError;

/**
 * Client-side validation for the shared `user_details` block.
 *
 * The block is passed as an associative array with verbatim snake_case wire
 * keys (given_names, last_name, email, phone_number) and serialized as a JSON
 * multipart part named `user_details`.
 */
final class UserDetails
{
    /**
     * @param array<string, mixed> $userDetails
     *
     * @throws ValidationError when required fields are missing or neither
     *     email nor phone_number is present
     */
    public static function validate(array $userDetails): void
    {
        if (self::isBlank($userDetails['given_names'] ?? null)) {
            throw new ValidationError('user_details.given_names is required.');
        }
        if (self::isBlank($userDetails['last_name'] ?? null)) {
            throw new ValidationError('user_details.last_name is required.');
        }

        $hasEmail = !self::isBlank($userDetails['email'] ?? null);
        $hasPhone = !self::isBlank($userDetails['phone_number'] ?? null);
        if (!$hasEmail && !$hasPhone) {
            throw new ValidationError('user_details requires at least one of email or phone_number.');
        }
    }

    private static function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
