<?php

namespace App\Application\Actions\Inventory;

use App\Application\DTOs\StockTransferData;
use App\Domain\Entities\StockTransferStatus;
use App\Domain\Events\StockTransferred;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Services\StockMovementService;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

final readonly class ProcessStockTransferAction
{
    public function __construct(private StockMovementService $stock) {}

    public function handle(StockTransferData $data): StockTransfer
    {
        $existing = StockTransfer::query()->where('idempotency_key', $data->idempotencyKey)->first();

        if ($existing !== null) {
            if (! hash_equals($existing->payload_hash, $data->payloadHash)) {
                throw new IdempotencyConflictException('The idempotency key was reused with a different transfer.');
            }

            return $existing->load('items');
        }

        return DB::transaction(function () use ($data): StockTransfer {
            $transfer = StockTransfer::query()->create([
                'from_branch_id' => $data->fromBranchId,
                'to_branch_id' => $data->toBranchId,
                'idempotency_key' => $data->idempotencyKey,
                'payload_hash' => $data->payloadHash,
                'status' => StockTransferStatus::Pending,
            ]);

            foreach ($data->items as $item) {
                $transfer->items()->create([
                    'product_variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                ]);
                $this->stock->transfer($item['variant_id'], $data->fromBranchId, $data->toBranchId, $item['quantity'], $transfer->getKey());
            }

            $transfer->update([
                'status' => StockTransferStatus::Completed,
                'transferred_at' => now(),
            ]);

            AuditLog::query()->create([
                'user_id' => $data->userId,
                'action' => 'STOCK_TRANSFERRED',
                'entity_type' => 'stock_transfer',
                'entity_id' => $transfer->getKey(),
                'new_values' => ['from_branch_id' => $data->fromBranchId, 'to_branch_id' => $data->toBranchId],
            ]);

            DB::afterCommit(fn () => event(new StockTransferred($transfer->getKey())));

            return $transfer->load('items');
        });
    }
}
