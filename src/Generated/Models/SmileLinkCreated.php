<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/**
 * HTTP 201 response from POST /v3/smile_links: an acknowledgment with the
 * link's id and URL. Read the full link with `smileLinks->get($refId)`.
 */
final class SmileLinkCreated
{
    public function __construct(
        public readonly string $link,
        public readonly string $message,
        public readonly string $refId,
        public readonly ?string $userId,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            link: (string) ($data['link'] ?? ''),
            message: (string) ($data['message'] ?? ''),
            refId: (string) ($data['ref_id'] ?? ''),
            userId: isset($data['user_id']) ? (string) $data['user_id'] : null,
        );
    }
}
