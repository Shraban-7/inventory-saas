<?php

namespace App\Domain\Entities;

final readonly class StockMovementData
{
    public function __construct(
        public int $variantId,
        public int $branchId,
        public string $quantity,
        public ?string $unitCost,
        public StockMovementType $type,
        public ?string $sourceType,
        public ?int $sourceId,
    ) {}

    public function key(): StockLevelKey
    {
        return new StockLevelKey($this->variantId, $this->branchId);
    }
}
