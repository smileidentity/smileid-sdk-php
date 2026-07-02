<?php

declare(strict_types=1);

namespace SmileIdentity\Errors;

/**
 * Turns an HTTP error response into a typed {@see SmileIDError}.
 *
 * Handles both wire shapes: {status, message} (everywhere; id_status reorders
 * to {message, status}) and {error, code} (the three unauthenticated services
 * endpoints). The class is selected by HTTP status, never by body contents.
 */
final class ErrorFactory
{
    /**
     * @param array<string, string> $headers response headers, lowercased keys
     */
    public static function fromResponse(
        int $statusCode,
        ?string $rawBody,
        string $reasonPhrase = '',
        array $headers = [],
    ): SmileIDError {
        $body = null;
        if ($rawBody !== null && $rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        $message = null;
        $status = null;
        $code = null;

        if ($body !== null) {
            // Probe `message` then `error` for the human-readable message.
            if (isset($body['message']) && is_scalar($body['message'])) {
                $message = (string) $body['message'];
            } elseif (isset($body['error']) && is_scalar($body['error'])) {
                $message = (string) $body['error'];
            }
            if (isset($body['status']) && is_scalar($body['status'])) {
                $status = (string) $body['status'];
            }
            if (isset($body['code']) && is_scalar($body['code'])) {
                $code = (string) $body['code'];
            }
        }

        if ($message === null || $message === '') {
            $message = $reasonPhrase !== '' ? $reasonPhrase : 'HTTP ' . $statusCode;
        }

        $requestId = $headers['x-request-id'] ?? $headers['request-id'] ?? null;

        $class = self::classFor($statusCode);

        return new $class(
            message: $message,
            statusCode: $statusCode,
            status: $status,
            code: $code,
            requestId: $requestId,
            rawBody: $rawBody,
        );
    }

    /**
     * @return class-string<SmileIDError>
     */
    public static function classFor(int $statusCode): string
    {
        return match ($statusCode) {
            400, 415 => InvalidRequestError::class,
            401 => AuthenticationError::class,
            402 => PaymentRequiredError::class,
            403 => PermissionError::class,
            404 => NotFoundError::class,
            409 => ConflictError::class,
            413 => PayloadTooLargeError::class,
            429 => RateLimitError::class,
            default => $statusCode >= 500 ? APIError::class : SmileIDError::class,
        };
    }
}
