<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Entities\Quantity;
use App\Domain\Entities\StockBalance;
use App\Domain\Entities\StockLevelKey;
use App\Domain\Entities\StockMovementType;
use App\Domain\Repositories\BulkStockRepository;
use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\ProductVariant;
use App\Infrastructure\Models\StockLevel;
use App\Infrastructure\Models\StockMovement;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;

class EloquentStockRepository implements BulkStockRepository
{
    /** @return array<int, StockBalance> */
    public function lockLevels(int $variantId, array $branchIds): array
    {
        $keys = array_map(
            static fn (int $branchId): StockLevelKey => new StockLevelKey($variantId, $branchId),
            array_values(array_unique($branchIds)),
        );

        $balances = $this->lockLevelPairs($keys);

        $result = [];

        foreach ($balances as $balance) {
            $result[$balance->branchId] = $balance;
        }

        return $result;
    }

    /** @return array<string, StockBalance> */
    public function lockLevelPairs(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $pairs = [];

        foreach ($keys as $key) {
            $pairs[$key->value()] = $key;
        }

        $pairs = array_values($pairs);
        usort($pairs, static fn (StockLevelKey $left, StockLevelKey $right): int => [$left->variantId, $left->branchId] <=> [$right->variantId, $right->branchId]);

        $variantIds = array_values(array_unique(array_map(static fn (StockLevelKey $key): int => $key->variantId, $pairs)));
        $branchIds = array_values(array_unique(array_map(static fn (StockLevelKey $key): int => $key->branchId, $pairs)));

        if (ProductVariant::query()->whereKey($variantIds)->count() !== count($variantIds)) {
            throw (new ModelNotFoundException)->setModel(ProductVariant::class, $variantIds);
        }

        if (Branch::query()->whereKey($branchIds)->count() !== count($branchIds)) {
            throw (new ModelNotFoundException)->setModel(Branch::class, $branchIds);
        }

        foreach ($pairs as $key) {
            $attributes = [
                'product_variant_id' => $key->variantId,
                'branch_id' => $key->branchId,
            ];

            if (StockLevel::query()->where($attributes)->doesntExist()) {
                try {
                    StockLevel::query()->create([...$attributes, 'quantity_on_hand' => '0.0000']);
                } catch (UniqueConstraintViolationException) {
                    StockLevel::query()->useWritePdo()->where($attributes)->firstOrFail();
                }
            }
        }

        $levelIds = [];

        foreach ($pairs as $key) {
            $levelIds[] = StockLevel::query()
                ->where('product_variant_id', $key->variantId)
                ->where('branch_id', $key->branchId)
                ->valueOrFail('id');
        }

        return StockLevel::query()
            ->whereKey($levelIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->mapWithKeys(fn (StockLevel $level): array => [
                $level->product_variant_id.':'.$level->branch_id => new StockBalance(
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
