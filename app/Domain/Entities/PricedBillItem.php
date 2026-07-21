<?php

namespace App\Domain\Entities;

final readonly class PricedBillItem
{
    public function __construct(
        public int $variantId,
        public string $quantity,
        public string $unitCost,
        public ?int $taxId = null,
        public ?string $taxRate = null,
        public ?int $taxAccountId = null,
    ) {}
}
