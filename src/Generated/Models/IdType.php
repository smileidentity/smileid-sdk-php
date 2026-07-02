<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

final class IdType
{
    /**
     * @param list<string> $requiredFields
     */
    public function __construct(
        public readonly ?string $type,
        public readonly ?string $country,
        public readonly ?string $label,
        public readonly ?string $regex,
        public readonly array $requiredFields,
        public readonly ?string $bankCode = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $requiredFields = [];
        if (isset($data['required_fields']) && is_array($data['required_fields'])) {
            foreach ($data['required_fields'] as $field) {
                $requiredFields[] = (string) $field;
            }
        }

        return new self(
            type: isset($data['type']) ? (string) $data['type'] : null,
            country: isset($data['country']) ? (string) $data['country'] : null,
            label: isset($data['label']) ? (string) $data['label'] : null,
            regex: isset($data['regex']) ? (string) $data['regex'] : null,
            requiredFields: $requiredFields,
            bankCode: isset($data['bank_code']) ? (string) $data['bank_code'] : null,
        );
    }
}
