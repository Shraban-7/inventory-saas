<?php

namespace App\Domain\Entities;

final readonly class CreditNoteItemRecord
{
    public function __construct(
        public int $variantId,
        public ?int $taxId,
        public string $quantity,
        public string $unitPrice,
        public string $costPrice,
        public ?string $taxRate,
        public string $lineTotal,
    ) {}
}
