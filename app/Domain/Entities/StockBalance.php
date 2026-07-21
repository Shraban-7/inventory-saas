<?php

namespace App\Domain\Entities;

final class StockBalance
{
    public function __construct(
        public readonly int $id,
        public readonly int $variantId,
        public readonly int $branchId,
        public Quantity $quantity,
    ) {}
}
