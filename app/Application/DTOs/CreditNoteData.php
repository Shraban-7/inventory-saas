<?php

namespace App\Application\DTOs;

final readonly class CreditNoteData
{
    /** @param list<CreditNoteItemData> $items */
    public function __construct(
        public int $branchId,
        public int $customerId,
        public ?int $invoiceId,
        public string $reason,
        public array $items,
    ) {}
}
