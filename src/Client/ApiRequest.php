<?php

declare(strict_types=1);

namespace SmileIdentity\Client;

/**
 * A language-neutral description of one wire request, produced by the
 * generated operation functions and consumed by {@see Transport}.
 *
 * The transport layer owns auth, telemetry, signing, and retries; this object
 * only carries what is specific to the operation.
 */
final class ApiRequest
{
    public const BODY_NONE = 'none';
    public const BODY_MULTIPART = 'multipart';
    public const BODY_JSON = 'json';

    /**
     * @param array<string, scalar> $query
     * @param list<array<string, mixed>> $multipart Guzzle-style multipart entries
     * @param array<string, mixed>|null $jsonBody
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly bool $authenticated,
        public readonly bool $idempotent,
        public readonly bool $needsPartnerIdHeader = false,
        public readonly ?string $userIdHeader = null,
        public readonly array $query = [],
        public readonly array $multipart = [],
        public readonly ?array $jsonBody = null,
        public readonly string $bodyKind = self::BODY_NONE,
    ) {
    }
}
