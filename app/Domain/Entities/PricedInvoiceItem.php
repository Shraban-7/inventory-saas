<?php

namespace App\Domain\Entities;

final readonly class PricedInvoiceItem
{
    public function __construct(
        public int $variantId,
        public string $quantity,
        public string $unitPrice,
        public string $currentCost,
        public ?int $taxId,
        public ?string $taxRate,
        public ?int $taxAccountId,
        public ?string $exactCostTotal = null,
    ) {}
}
