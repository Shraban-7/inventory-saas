<?php

namespace App\Application\DTOs;

final readonly class BillItemData
{
    public function __construct(
        public int $variantId,
        public ?int $taxId,
        public string $quantity,
        public string $unitCost,
    ) {}
}
