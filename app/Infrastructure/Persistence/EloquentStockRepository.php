<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Entities\Quantity;
use App\Domain\Entities\StockBalance;
use App\Domain\Entities\StockMovementType;
use App\Domain\Repositories\StockRepository;
use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\ProductVariant;
use App\Infrastructure\Models\StockLevel;
use App\Infrastructure\Models\StockMovement;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EloquentStockRepository implements StockRepository
{
    /** @return array<int, StockBalance> */
    public function lockLevels(int $variantId, array $branchIds): array
    {
        ProductVariant::query()->findOrFail($variantId);
        $branchIds = array_values(array_unique($branchIds));

        if (Branch::query()->whereKey($branchIds)->count() !== count($branchIds)) {
            throw (new ModelNotFoundException)->setModel(Branch::class, $branchIds);
        }

        foreach ($branchIds as $branchId) {
            StockLevel::query()->firstOrCreate([
                'product_variant_id' => $variantId,
                'branch_id' => $branchId,
            ], ['quantity_on_hand' => '0.0000']);
        }

        return StockLevel::query()
            ->where('product_variant_id', $variantId)
            ->whereIn('branch_id', $branchIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->mapWithKeys(fn (StockLevel $level): array => [
                $level->branch_id => new StockBalance(
                    $level->getKey(),
                    $level->product_variant_id,
                    $level->branch_id,
                    Quantity::from($level->quantity_on_hand),
                ),
            ])->all();
    }

    public function saveBalance(StockBalance $balance): void
    {
        StockLevel::query()->whereKey($balance->id)->update([
            'quantity_on_hand' => $balance->quantity->toDecimal(),
        ]);
    }

    public function appendMovement(int $variantId, int $branchId, Quantity $delta, ?string $unitCost, StockMovementType $type, ?string $sourceType, ?int $sourceId): void
    {
        StockMovement::query()->create([
            'product_variant_id' => $variantId,
            'branch_id' => $branchId,
            'type' => $type,
            'quantity_delta' => $delta->toDecimal(),
            'unit_cost' => $unitCost,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
    }
}
