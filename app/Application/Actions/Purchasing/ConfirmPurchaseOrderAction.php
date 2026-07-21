<?php

namespace App\Application\Actions\Purchasing;

use App\Domain\Entities\PurchaseOrderStatus;
use App\Domain\Events\PurchaseOrderConfirmed;
use App\Domain\Exceptions\InvalidPurchaseOrderStateException;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

final class ConfirmPurchaseOrderAction
{
    public function handle(int $purchaseOrderId, int $actingUserId): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrderId, $actingUserId): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrderId);
            $status = PurchaseOrderStatus::from((string) $purchaseOrder->getRawOriginal('status'));

            if ($status !== PurchaseOrderStatus::Draft) {
                throw new InvalidPurchaseOrderStateException('Only a draft purchase order can be confirmed.');
            }

            $purchaseOrder->forceFill(['status' => PurchaseOrderStatus::Confirmed])->save();
            AuditLog::query()->create([
                'user_id' => $actingUserId,
                'action' => 'PURCHASE_ORDER_CONFIRMED',
                'entity_type' => 'purchase_order',
                'entity_id' => $purchaseOrder->getKey(),
                'old_values' => ['status' => PurchaseOrderStatus::Draft->value],
                'new_values' => ['status' => PurchaseOrderStatus::Confirmed->value],
            ]);

            $id = (int) $purchaseOrder->getKey();
            DB::afterCommit(static fn () => event(new PurchaseOrderConfirmed($id)));

            return $purchaseOrder;
        });
    }
}
