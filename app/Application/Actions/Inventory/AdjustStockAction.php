<?php

namespace App\Application\Actions\Inventory;

use App\Application\DTOs\StockAdjustmentData;
use App\Domain\Entities\Quantity;
use App\Domain\Events\StockAdjusted;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Services\StockMovementService;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;

final readonly class AdjustStockAction
{
    public function __construct(private StockMovementService $stock) {}

    public function handle(StockAdjustmentData $data): StockAdjustment
    {
        $existing = StockAdjustment::query()->where('idempotency_key', $data->idempotencyKey)->first();

        if ($existing !== null) {
            if (! hash_equals($existing->payload_hash, $data->payloadHash)) {
                throw new IdempotencyConflictException('The idempotency key was reused with a different adjustment.');
            }

            return $existing;
        }

        return DB::transaction(function () use ($data): StockAdjustment {
            $adjustment = StockAdjustment::query()->create([
                'product_variant_id' => $data->variantId,
                'branch_id' => $data->branchId,
                'idempotency_key' => $data->idempotencyKey,
                'payload_hash' => $data->payloadHash,
                'quantity_delta' => $data->quantityDelta,
                'type' => $data->type,
                'reason' => $data->reason,
            ]);

            $quantity = Quantity::from($data->quantityDelta);

            if ($quantity->isPositive()) {
                $this->stock->add($data->variantId, $data->branchId, $quantity->toDecimal(), null, $data->type, 'adjustment', $adjustment->getKey());
            } else {
                $this->stock->deduct($data->variantId, $data->branchId, $quantity->abs()->toDecimal(), null, $data->type, 'adjustment', $adjustment->getKey());
            }

            AuditLog::query()->create([
                'user_id' => $data->userId,
                'action' => 'STOCK_ADJUSTED',
                'entity_type' => 'adjustment',
                'entity_id' => $adjustment->getKey(),
                'new_values' => [
                    'quantity_delta' => $data->quantityDelta,
                    'reason' => $data->reason,
                ],
            ]);

            DB::afterCommit(fn () => event(new StockAdjusted($adjustment->getKey())));

            return $adjustment;
        });
    }
}
