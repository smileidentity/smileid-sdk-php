<?php

declare(strict_types=1);

namespace SmileIdentity\Generated\Models;

/** HTTP 200 response from GET /v3/services/bank_codes. */
final class BankCodesResponse
{
    /**
     * @param list<BankCode> $bankCodes
     */
    public function __construct(
        public readonly array $bankCodes,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $items = [];
        if (isset($data['bank_codes']) && is_array($data['bank_codes'])) {
            foreach ($data['bank_codes'] as $row) {
                if (is_array($row)) {
                    $items[] = BankCode::fromArray($row);
                }
            }
        }

        return new self($items);
    }
}
