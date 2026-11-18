<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/** HTTP 200 response from GET /v3/aml/monitoring/{job_id}/news/{reference}. */
final class AmlMonitoringNewsResponse
{
    /**
     * @param list<AmlMonitoringNewsItem> $news
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $product,
        public readonly string $userId,
        public readonly int $currentPage,
        public readonly int $limit,
        public readonly int $totalCount,
        public readonly array $news,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $news = [];
        foreach (is_array($data['news'] ?? null) ? $data['news'] : [] as $item) {
            if (is_array($item)) {
                $news[] = AmlMonitoringNewsItem::fromArray($item);
            }
        }

        return new self(
            jobId: (string) ($data['job_id'] ?? ''),
            product: (string) ($data['product'] ?? ''),
            userId: (string) ($data['user_id'] ?? ''),
            currentPage: (int) ($meta['current_page'] ?? 0),
            limit: (int) ($meta['limit'] ?? 0),
            totalCount: (int) ($meta['total_count'] ?? 0),
            news: $news,
        );
    }
}
