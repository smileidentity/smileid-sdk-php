<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/** HTTP 200 response from GET /v3/services/supported_id_types. */
final class SupportedIdTypesResponse
{
    /**
     * @param list<IdType> $idTypes
     */
    public function __construct(
        public readonly array $idTypes,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $items = [];
        if (isset($data['id_types']) && is_array($data['id_types'])) {
            foreach ($data['id_types'] as $row) {
                if (is_array($row)) {
                    $items[] = IdType::fromArray($row);
                }
            }
        }

        return new self($items);
    }
}
