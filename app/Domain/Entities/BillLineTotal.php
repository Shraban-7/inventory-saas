<?php

namespace App\Domain\Entities;

final readonly class BillLineTotal
{
    public function __construct(
        public int $variantId,
        public string $quantity,
        public string $unitCost,
        public ?int $taxId,
        public ?string $taxRate,
        public ?int $taxAccountId,
        public Money $gross,
        public Money $tax,
        public Money $total,
    ) {}
}
