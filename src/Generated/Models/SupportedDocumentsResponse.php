<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/** HTTP 200 response from GET /v3/services/supported_documents. */
final class SupportedDocumentsResponse
{
    /**
     * @param list<ValidDocument> $validDocuments
     */
    public function __construct(
        public readonly array $validDocuments,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $items = [];
        if (isset($data['valid_documents']) && is_array($data['valid_documents'])) {
            foreach ($data['valid_documents'] as $row) {
                if (is_array($row)) {
                    $items[] = ValidDocument::fromArray($row);
                }
            }
        }

        return new self($items);
    }
}
