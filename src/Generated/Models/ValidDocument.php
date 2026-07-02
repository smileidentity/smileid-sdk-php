<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

final class ValidDocument
{
    /**
     * @param list<DocumentIdType> $idTypes
     */
    public function __construct(
        public readonly ?DocumentCountry $country,
        public readonly array $idTypes,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $country = null;
        if (isset($data['country']) && is_array($data['country'])) {
            $country = DocumentCountry::fromArray($data['country']);
        }

        $idTypes = [];
        if (isset($data['id_types']) && is_array($data['id_types'])) {
            foreach ($data['id_types'] as $row) {
                if (is_array($row)) {
                    $idTypes[] = DocumentIdType::fromArray($row);
                }
            }
        }

        return new self($country, $idTypes);
    }
}
