<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

final class DocumentIdType
{
    /**
     * @param list<string> $example
     */
    public function __construct(
        public readonly ?string $code,
        public readonly ?string $name,
        public readonly array $example,
        public readonly ?bool $hasBack,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $example = [];
        if (isset($data['example']) && is_array($data['example'])) {
            foreach ($data['example'] as $item) {
                $example[] = (string) $item;
            }
        }

        return new self(
            code: isset($data['code']) ? (string) $data['code'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            example: $example,
            hasBack: isset($data['has_back']) ? (bool) $data['has_back'] : null,
        );
    }
}
