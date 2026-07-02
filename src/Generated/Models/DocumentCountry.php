<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

final class DocumentCountry
{
    public function __construct(
        public readonly ?string $code,
        public readonly ?string $name,
        public readonly ?string $continent,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: isset($data['code']) ? (string) $data['code'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            continent: isset($data['continent']) ? (string) $data['continent'] : null,
        );
    }
}
