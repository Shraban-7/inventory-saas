<?php

namespace App\Application\Actions\Purchasing;

use App\Application\Actions\Accounting\CreateJournalEntryAction;
use App\Application\Contracts\PurchasingOperationLock;
use App\Application\DTOs\GoodsReceiptData;
use App\Application\DTOs\JournalEntryData;
use App\Application\Services\SystemChartOfAccountResolver;
use App\Domain\Entities\Money;
use App\Domain\Entities\PurchaseOrderStatus;
use App\Domain\Entities\PurchasingDocumentType;
use App\Domain\Entities\Quantity;
use App\Domain\Entities\StockMovementData;
use App\Domain\Entities\StockMovementType;
use App\Domain\Events\GoodsReceived;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\InvalidPurchaseOrderStateException;
use App\Domain\Exceptions\InvalidPurchasingDataException;
use App\Domain\Services\PurchasingNumberService;
use App\Domain\Services\StockMovementService;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\GoodsReceiptNote;
use App\Infrastructure\Models\ProductVariant;
use App\Infrastructure\Models\PurchaseOrder;
use App\Infrastructure\Models\PurchaseOrderItem;
use App\Infrastructure\Models\Supplier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OverflowException;

final readonly class ProcessGoodsReceiptAction
{
    public function __construct(
        private PurchasingOperationLock $lock,
        private PurchasingNumberService $numbers,
        private StockMovementService $stock,
        private SystemChartOfAccountResolver $accounts,
        private CreateJournalEntryAction $journals,
    ) {}

    public function handle(GoodsReceiptData $data, int $actingUserId): GoodsReceiptNote
    {
        return $this->lock->run('grn', $data->idempotencyKey, function () use ($data, $actingUserId): GoodsReceiptNote {
            $existing = GoodsReceiptNote::query()->where('idempotency_key', $data->idempotencyKey)->first();

            if ($existing !== null) {
                return $this->replay($existing, $data->payloadHash);
            }

            return DB::transaction(function () use ($data, $actingUserId): GoodsReceiptNote {
                Branch::query()->findOrFail($data->branchId);
                Supplier::query()->findOrFail($data->supplierId);
                [$normalizedItems, $variantIds] = $this->validateItems($data);
                $purchaseOrder = $this->lockPurchaseOrder($data, $normalizedItems);
                $number = $this->numbers->next(PurchasingDocumentType::GoodsReceiptNote, (int) $data->receivedAt->format('Y'));
                $grn = GoodsReceiptNote::query()->create([
                    'branch_id' => $data->branchId,
                    'supplier_id' => $data->supplierId,
                    'purchase_order_id' => $data->purchaseOrderId,
                    'grn_number' => $number,
                    'idempotency_key' => $data->idempotencyKey,
                    'payload_hash' => $data->payloadHash,
                    'received_at' => $data->receivedAt,
                    'notes' => $data->notes,
                ]);
                $grn->items()->createMany($normalizedItems);

                $this->stock->bulkAdd(array_map(
                    static fn (array $item): StockMovementData => new StockMovementData(
                        $item['product_variant_id'],
                        $data->branchId,
                        $item['quantity_received'],
                        $item['unit_cost'],
                        StockMovementType::PurchaseReceipt,
                        'grn',
                        (int) $grn->getKey(),
                        $data->receivedAt,
                    ),
                    $normalizedItems,
                ));

                if ($purchaseOrder !== null) {
                    $this->applyReceiptToPurchaseOrder($purchaseOrder, $normalizedItems);
                }

                $total = Money::zero();

                foreach ($normalizedItems as $item) {
                    $total = $total->add(Money::quantityTimesPrice($item['quantity_received'], $item['unit_cost']));
                }

                $accountIds = $this->accounts->ids(['1300', '2050']);
                $this->journals->handle(new JournalEntryData(
                    $data->branchId,
                    'JRN-GRN-'.$grn->getKey(),
                    'grn',
                    (int) $grn->getKey(),
                    $data->receivedAt,
                    'Receive goods '.$number,
                    PurchasingJournalLineFactory::receipt($total, $accountIds),
                ));
                AuditLog::query()->create([
                    'user_id' => $actingUserId,
                    'action' => 'GOODS_RECEIVED',
                    'entity_type' => 'grn',
                    'entity_id' => $grn->getKey(),
                    'new_values' => [
                        'grn_number' => $number,
                        'branch_id' => $data->branchId,
                        'supplier_id' => $data->supplierId,
                        'purchase_order_id' => $data->purchaseOrderId,
                        'total_cost' => $total->toDecimal(),
                        'variant_ids' => $variantIds,
                    ],
                ]);

                $id = (int) $grn->getKey();
                DB::afterCommit(static fn () => event(new GoodsReceived($id)));

                return $grn->load(['items', 'stockMovements', 'journalEntries.lines']);
            });
        });
    }

    /** @return array{list<array{product_variant_id: int, quantity_received: string, unit_cost: string}>, list<int>} */
    private function validateItems(GoodsReceiptData $data): array
    {
        if ($data->items === []) {
            throw new InvalidPurchasingDataException('A goods receipt must contain at least one item.');
        }

        $variantIds = array_map(static fn ($item): int => $item->variantId, $data->items);

        if (count(array_unique($variantIds)) !== count($variantIds)) {
            throw new InvalidPurchasingDataException('A goods receipt cannot contain duplicate product variants.');
        }

        if (ProductVariant::query()->whereKey($variantIds)->count() !== count($variantIds)) {
            throw new InvalidPurchasingDataException('Every product variant must belong to the current tenant.');
        }

        $normalized = [];

        try {
            foreach ($data->items as $item) {
                $quantity = Quantity::from($item->quantityReceived);
                $cost = Quantity::from($item->unitCost);
                Money::quantityTimesPrice($quantity->toDecimal(), $cost->toDecimal());

                if (! $quantity->isPositive() || ! $cost->isPositive()) {
                    throw new InvalidArgumentException('Receipt quantities and unit costs must be greater than zero.');
                }

                $normalized[] = [
                    'product_variant_id' => $item->variantId,
                    'quantity_received' => $quantity->toDecimal(),
                    'unit_cost' => $cost->toDecimal(),
                ];
            }
        } catch (InvalidArgumentException|OverflowException $exception) {
            throw new InvalidPurchasingDataException($exception->getMessage(), previous: $exception);
        }

        return [$normalized, $variantIds];
    }

    /** @param list<array{product_variant_id: int, quantity_received: string, unit_cost: string}> $items */
    private function lockPurchaseOrder(GoodsReceiptData $data, array $items): ?PurchaseOrder
    {
        if ($data->purchaseOrderId === null) {
            return null;
        }

        $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($data->purchaseOrderId);
        $status = PurchaseOrderStatus::from((string) $purchaseOrder->getRawOriginal('status'));

        if ($purchaseOrder->branch_id !== $data->branchId || $purchaseOrder->supplier_id !== $data->supplierId) {
            throw new InvalidPurchasingDataException('The purchase order branch and supplier must match the goods receipt.');
        }

        if (! in_array($status, [PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::PartiallyReceived], true)) {
            throw new InvalidPurchaseOrderStateException('Only a confirmed or partially received purchase order can receive goods.');
        }

        $orderedItems = PurchaseOrderItem::query()
            ->where('purchase_order_id', $purchaseOrder->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_variant_id');

        foreach ($items as $item) {
            $ordered = $orderedItems->get($item['product_variant_id']);

            if (! $ordered instanceof PurchaseOrderItem) {
                throw new InvalidPurchasingDataException('Every received variant must exist on the purchase order.');
            }

            if (! Quantity::from((string) $ordered->unit_cost)->equals(Quantity::from($item['unit_cost']))) {
                throw new InvalidPurchasingDataException('Goods receipt unit cost must match the purchase order snapshot.');
            }

            $newReceived = Quantity::from((string) $ordered->quantity_received)->add(Quantity::from($item['quantity_received']));

            if ($newReceived->compare(Quantity::from((string) $ordered->quantity_ordered)) > 0) {
                throw new InvalidPurchasingDataException('Goods receipt quantity exceeds the outstanding purchase order quantity.');
            }
        }

        $purchaseOrder->setRelation('items', $orderedItems->values());

        return $purchaseOrder;
    }

    /** @param list<array{product_variant_id: int, quantity_received: string, unit_cost: string}> $items */
    private function applyReceiptToPurchaseOrder(PurchaseOrder $purchaseOrder, array $items): void
    {
        $receivedByVariant = [];

        foreach ($items as $item) {
            $receivedByVariant[$item['product_variant_id']] = Quantity::from($item['quantity_received']);
        }

        $complete = true;

        foreach ($purchaseOrder->items as $ordered) {
            $received = Quantity::from((string) $ordered->quantity_received);

            if (isset($receivedByVariant[$ordered->product_variant_id])) {
                $received = $received->add($receivedByVariant[$ordered->product_variant_id]);
                $ordered->forceFill(['quantity_received' => $received->toDecimal()])->save();
            }

            if ($received->compare(Quantity::from((string) $ordered->quantity_ordered)) < 0) {
                $complete = false;
            }
        }

        $purchaseOrder->forceFill([
            'status' => $complete ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived,
        ])->save();
    }

    private function replay(GoodsReceiptNote $receipt, string $payloadHash): GoodsReceiptNote
    {
        if (! hash_equals($receipt->payload_hash, $payloadHash)) {
            throw new IdempotencyConflictException('The idempotency key was reused with a different goods receipt.');
        }

        return $receipt->load(['items', 'stockMovements', 'journalEntries.lines']);
    }
}
