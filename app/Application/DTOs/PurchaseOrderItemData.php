<?php

namespace App\Application\DTOs;

final readonly class PurchaseOrderItemData
{
    public function __construct(
        public int $variantId,
        public string $quantityOrdered,
        public string $unitCost,
    ) {}
}
