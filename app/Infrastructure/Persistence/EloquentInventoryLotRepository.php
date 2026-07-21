<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Entities\LotBalance;
use App\Domain\Entities\Quantity;
use App\Domain\Entities\StockLevelKey;
use App\Domain\Repositories\InventoryLotRepository;
use App\Infrastructure\Models\InventoryLot;
use App\Infrastructure\Models\Product;
use App\Infrastructure\Models\ProductVariant;
use DateTimeImmutable;
use DateTimeInterface;
use UnexpectedValueException;

final class EloquentInventoryLotRepository implements InventoryLotRepository
{
    public function lockLots(array $keys): array
    {
        $keys = $this->canonicalKeys($keys);
        $result = array_fill_keys(array_map(
            static fn (StockLevelKey $key): string => $key->value(),
            $keys,
        ), []);

        foreach ($keys as $key) {
            $models = InventoryLot::query()
                ->where('product_variant_id', $key->variantId)
                ->where('branch_id', $key->branchId)
                ->where('quantity_remaining', '>', 0)
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $result[$key->value()] = array_values($models
                ->map(fn (InventoryLot $lot): LotBalance => $this->toBalance($lot))
                ->all());
        }

        return $result;
    }

    public function costingMethods(array $keys): array
    {
        $variantIds = array_values(array_unique(array_map(
            static fn (StockLevelKey $key): int => $key->variantId,
            $keys,
        )));

        return ProductVariant::query()
            ->whereKey($variantIds)
            ->with('product:id,costing_method')
            ->get(['id', 'product_id'])
            ->mapWithKeys(static function (ProductVariant $variant): array {
                $product = $variant->product;

                if (! $product instanceof Product) {
                    throw new UnexpectedValueException('The product costing method could not be loaded.');
                }

                $method = $product->getRawOriginal('costing_method');

                if (! is_string($method)) {
                    throw new UnexpectedValueException('The product costing method is invalid.');
                }

                return [$variant->getKey() => $method];
            })->all();
    }

    public function save(LotBalance $lot): void
    {
        InventoryLot::query()->whereKey($lot->id)->update([
            'quantity_remaining' => $lot->quantity->toDecimal(),
        ]);
    }

    public function create(int $variantId, int $branchId, string $quantity, string $unitCost, DateTimeImmutable $receivedAt): void
    {
        InventoryLot::query()->create([
            'product_variant_id' => $variantId,
            'branch_id' => $branchId,
            'quantity_remaining' => $quantity,
            'unit_cost' => $unitCost,
            'received_at' => $receivedAt,
        ]);
    }

    /** @param list<StockLevelKey> $keys
     * @return list<StockLevelKey>
     */
    private function canonicalKeys(array $keys): array
    {
        $unique = [];

        foreach ($keys as $key) {
            $unique[$key->value()] = $key;
        }

        $keys = array_values($unique);
        usort($keys, static fn (StockLevelKey $left, StockLevelKey $right): int => [$left->variantId, $left->branchId] <=> [$right->variantId, $right->branchId]);

        return $keys;
    }

    private function toBalance(InventoryLot $lot): LotBalance
    {
        $unitCost = $lot->getAttribute('unit_cost');
        $receivedAt = $lot->getAttribute('received_at');

        if (! is_string($unitCost) || ! $receivedAt instanceof DateTimeInterface) {
            throw new UnexpectedValueException('An inventory lot contains invalid decimal or date data.');
        }

        return new LotBalance(
            $lot->getKey(),
            $lot->product_variant_id,
            $lot->branch_id,
            Quantity::from($lot->quantity_remaining),
            $unitCost,
            DateTimeImmutable::createFromInterface($receivedAt),
        );
    }
}
