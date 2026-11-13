<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/** One adverse-media article in an AML Monitoring news response. */
final class AmlMonitoringNewsItem
{
    public function __construct(
        public readonly string $body,
        public readonly string $publishedDate,
        public readonly string $publisher,
        public readonly string $publisherCountryCode,
        public readonly string $title,
        public readonly string $url,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            body: (string) ($data['body'] ?? ''),
            publishedDate: (string) ($data['published_date'] ?? ''),
            publisher: (string) ($data['publisher'] ?? ''),
            publisherCountryCode: (string) ($data['publisher_country_code'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            url: (string) ($data['url'] ?? ''),
        );
    }
}
