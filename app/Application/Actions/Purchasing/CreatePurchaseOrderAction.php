<?php

namespace App\Application\Actions\Purchasing;

use App\Application\DTOs\PurchaseOrderData;
use App\Domain\Entities\Money;
use App\Domain\Entities\PurchaseOrderStatus;
use App\Domain\Entities\PurchasingDocumentType;
use App\Domain\Entities\Quantity;
use App\Domain\Exceptions\InvalidPurchasingDataException;
use App\Domain\Services\PurchasingNumberService;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\ProductVariant;
use App\Infrastructure\Models\PurchaseOrder;
use App\Infrastructure\Models\Supplier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OverflowException;

final readonly class CreatePurchaseOrderAction
{
    public function __construct(private PurchasingNumberService $numbers) {}

    public function handle(PurchaseOrderData $data, int $actingUserId): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $actingUserId): PurchaseOrder {
            Branch::query()->findOrFail($data->branchId);
            Supplier::query()->findOrFail($data->supplierId);

            if ($data->items === []) {
                throw new InvalidPurchasingDataException('A purchase order must contain at least one item.');
            }

            $variantIds = array_map(static fn ($item): int => $item->variantId, $data->items);

            if (count(array_unique($variantIds)) !== count($variantIds)) {
                throw new InvalidPurchasingDataException('A purchase order cannot contain duplicate product variants.');
            }

            if (ProductVariant::query()->whereKey($variantIds)->count() !== count($variantIds)) {
                throw new InvalidPurchasingDataException('Every product variant must belong to the current tenant.');
            }

            $items = [];

            try {
                foreach ($data->items as $item) {
                    $quantity = Quantity::from($item->quantityOrdered);
                    $cost = Quantity::from($item->unitCost);
                    Money::quantityTimesPrice($quantity->toDecimal(), $cost->toDecimal());

                    if (! $quantity->isPositive() || ! $cost->isPositive()) {
                        throw new InvalidArgumentException('Purchase order quantities and unit costs must be greater than zero.');
                    }

                    $items[] = [
                        'product_variant_id' => $item->variantId,
                        'quantity_ordered' => $quantity->toDecimal(),
                        'quantity_received' => '0.0000',
                        'unit_cost' => $cost->toDecimal(),
                    ];
                }
            } catch (InvalidArgumentException|OverflowException $exception) {
                throw new InvalidPurchasingDataException($exception->getMessage(), previous: $exception);
            }

            $number = $this->numbers->next(PurchasingDocumentType::PurchaseOrder, (int) $data->orderDate->format('Y'));
            $purchaseOrder = PurchaseOrder::query()->create([
                'branch_id' => $data->branchId,
                'supplier_id' => $data->supplierId,
                'po_number' => $number,
                'status' => PurchaseOrderStatus::Draft,
                'order_date' => $data->orderDate->format('Y-m-d'),
                'expected_date' => $data->expectedDate?->format('Y-m-d'),
                'notes' => $data->notes,
            ]);
            $purchaseOrder->items()->createMany($items);

            AuditLog::query()->create([
                'user_id' => $actingUserId,
                'action' => 'PURCHASE_ORDER_CREATED',
                'entity_type' => 'purchase_order',
                'entity_id' => $purchaseOrder->getKey(),
                'new_values' => [
                    'po_number' => $number,
                    'branch_id' => $data->branchId,
                    'supplier_id' => $data->supplierId,
                    'status' => PurchaseOrderStatus::Draft->value,
                    'items' => $items,
                ],
            ]);

            return $purchaseOrder->load('items');
        });
    }
}
