<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/** Outcome counts for the jobs run against a link, from the Smile Links listing. */
final class SmileLinkJobStats
{
    public function __construct(
        public readonly int $clear,
        public readonly int $block,
        public readonly int $attention,
        public readonly int $error,
        public readonly int $successfulJobs,
        public readonly int $failedJobs,
        public readonly int $totalJobs,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            clear: (int) ($data['clear'] ?? 0),
            block: (int) ($data['block'] ?? 0),
            attention: (int) ($data['attention'] ?? 0),
            error: (int) ($data['error'] ?? 0),
            successfulJobs: (int) ($data['successful_jobs'] ?? 0),
            failedJobs: (int) ($data['failed_jobs'] ?? 0),
            totalJobs: (int) ($data['total_jobs'] ?? 0),
        );
    }
}
