<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/** HTTP 200 response from GET /v3/smile_links: one page of links, newest first. */
final class SmileLinkListResponse
{
    /**
     * @param list<SmileLink> $links each with its `jobStats`
     */
    public function __construct(
        public readonly array $links,
        public readonly int $currentPage,
        public readonly int $limit,
        public readonly int $totalCount,
        public readonly int $totalPages,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $links = [];
        foreach (is_array($data['links'] ?? null) ? $data['links'] : [] as $link) {
            if (is_array($link)) {
                $links[] = SmileLink::fromArray($link);
            }
        }
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        return new self(
            links: $links,
            currentPage: (int) ($meta['current_page'] ?? 0),
            limit: (int) ($meta['limit'] ?? 0),
            totalCount: (int) ($meta['total_count'] ?? 0),
            totalPages: (int) ($meta['total_pages'] ?? 0),
        );
    }
}
