<?php

namespace App\Domain\Events;

final readonly class StockLow
{
    public function __construct(
        public int $tenantId,
        public int $variantId,
        public int $branchId,
        public string $quantityOnHand,
        public int $reorderPoint,
        public int $stockMovementId,
    ) {}
}
