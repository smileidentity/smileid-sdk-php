<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

final class BankCode
{
    public function __construct(
        public readonly ?string $code,
        public readonly ?string $country,
        public readonly ?string $name,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: isset($data['code']) ? (string) $data['code'] : null,
            country: isset($data['country']) ? (string) $data['country'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
        );
    }
}
