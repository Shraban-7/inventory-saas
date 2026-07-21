<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Quantity;
use App\Domain\Entities\StockBalance;
use App\Domain\Entities\StockMovementType;

interface StockRepository
{
    /**
     * @param  list<int>  $branchIds
     * @return array<int, StockBalance>
     */
    public function lockLevels(int $variantId, array $branchIds): array;

    public function saveBalance(StockBalance $balance): void;

    public function appendMovement(
        int $variantId,
        int $branchId,
        Quantity $delta,
        ?string $unitCost,
        StockMovementType $type,
        ?string $sourceType,
        ?int $sourceId,
    ): void;
}
