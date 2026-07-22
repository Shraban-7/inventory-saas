<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Quantity;
use App\Domain\Entities\StockBalance;
use App\Domain\Entities\StockMovementType;
use App\Domain\Entities\VariantReorderProfile;

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
    ): int;

    /**
     * @param  list<int>  $variantIds
     * @return array<int, VariantReorderProfile>
     */
    public function reorderProfiles(array $variantIds): array;
}
