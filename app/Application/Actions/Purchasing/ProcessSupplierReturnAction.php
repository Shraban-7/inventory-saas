<?php

namespace App\Application\Actions\Purchasing;

use App\Application\Actions\Accounting\CreateJournalEntryAction;
use App\Application\Contracts\PurchasingOperationLock;
use App\Application\DTOs\JournalEntryData;
use App\Application\DTOs\SupplierReturnData;
use App\Application\Services\SystemChartOfAccountResolver;
use App\Domain\Entities\BillStatus;
use App\Domain\Entities\Money;
use App\Domain\Entities\Quantity;
use App\Domain\Entities\StockMovementData;
use App\Domain\Entities\StockMovementType;
use App\Domain\Entities\SupplierReturnStatus;
use App\Domain\Events\SupplierReturnProcessed;
use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\InvalidBillStateException;
use App\Domain\Exceptions\InvalidPurchasingDataException;
use App\Domain\Services\StockMovementService;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\Bill;
use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\ProductVariant;
use App\Infrastructure\Models\Supplier;
use App\Infrastructure\Models\SupplierReturn;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ProcessSupplierReturnAction
{
    public function __construct(
        private PurchasingOperationLock $lock,
        private StockMovementService $stock,
        private SystemChartOfAccountResolver $accounts,
        private CreateJournalEntryAction $journals,
    ) {}

    public function handle(SupplierReturnData $data, int $actingUserId): SupplierReturn
    {
        return $this->lock->run('supplier-return', $data->idempotencyKey, function () use ($data, $actingUserId): SupplierReturn {
            $existing = SupplierReturn::query()->where('idempotency_key', $data->idempotencyKey)->first();

            if ($existing !== null) {
                return $this->replay($existing, $data->payloadHash);
            }

            return DB::transaction(function () use ($data, $actingUserId): SupplierReturn {
                Branch::query()->findOrFail($data->branchId);
                Supplier::query()->findOrFail($data->supplierId);
                [$normalized, $variants] = $this->validateItems($data);
                $bill = $this->lockBill($data);
                $return = SupplierReturn::query()->create([
                    'branch_id' => $data->branchId,
                    'supplier_id' => $data->supplierId,
                    'bill_id' => $data->billId,
                    'idempotency_key' => $data->idempotencyKey,
                    'payload_hash' => $data->payloadHash,
                    'reason' => trim($data->reason),
                    'status' => SupplierReturnStatus::Draft,
                    'total_cost' => '0.00',
                ]);
                $movements = [];

                foreach ($normalized as $item) {
                    $variant = $variants->get($item['variant_id']);

                    if (! $variant instanceof ProductVariant) {
                        throw new InvalidPurchasingDataException('A requested product cost snapshot could not be loaded.');
                    }

                    $movements[] = new StockMovementData(
                        $item['variant_id'],
                        $data->branchId,
                        $item['quantity'],
                        (string) $variant->cost_price,
                        StockMovementType::PurchaseReturn,
                        'supplier_return',
                        (int) $return->getKey(),
                    );
                }

                $deductions = $this->stock->bulkDeduct($movements);

                $total = Money::zero();
                $returnItems = [];

                foreach ($normalized as $item) {
                    $result = $deductions[$item['variant_id'].':'.$data->branchId];
                    $total = $total->add($result->totalCost);
                    $returnItems[] = [
                        'product_variant_id' => $item['variant_id'],
                        'quantity' => $item['quantity'],
                        'unit_cost' => $result->weightedUnitCost,
                    ];
                }

                if (! $total->isPositive()) {
                    throw new InvalidPurchasingDataException('Supplier return inventory cost must be greater than zero.');
                }

                if ($bill !== null) {
                    $balance = Money::fromDecimal((string) $bill->balance_due);

                    if ($total->compare($balance) > 0) {
                        throw new InvalidPurchasingDataException('Supplier return total cannot exceed the linked bill balance.');
                    }

                    $remaining = $balance->subtract($total);
                    $bill->forceFill([
                        'balance_due' => $remaining->toDecimal(),
                        'status' => $remaining->isZero()
                            ? BillStatus::Paid
                            : BillStatus::from((string) $bill->getRawOriginal('status')),
                    ])->save();
                }

                $return->items()->createMany($returnItems);
                $return->forceFill([
                    'total_cost' => $total->toDecimal(),
                    'status' => SupplierReturnStatus::Approved,
                ])->save();
                $this->journals->handle(new JournalEntryData(
                    $data->branchId,
                    'JRN-SRET-'.$return->getKey(),
                    'supplier_return',
                    (int) $return->getKey(),
                    new DateTimeImmutable('today'),
                    'Process supplier return '.$return->getKey(),
                    PurchasingJournalLineFactory::supplierReturn(
                        $total,
                        $this->accounts->ids([$bill === null ? '2050' : '2000', '1300']),
                        $bill !== null,
                    ),
                ));
                AuditLog::query()->create([
                    'user_id' => $actingUserId,
                    'action' => 'SUPPLIER_RETURN_PROCESSED',
                    'entity_type' => 'supplier_return',
                    'entity_id' => $return->getKey(),
                    'new_values' => [
                        'branch_id' => $data->branchId,
                        'supplier_id' => $data->supplierId,
                        'bill_id' => $data->billId,
                        'reason' => trim($data->reason),
                        'status' => SupplierReturnStatus::Approved->value,
                        'total_cost' => $total->toDecimal(),
                        'items' => $returnItems,
                    ],
                ]);

                $id = (int) $return->getKey();
                DB::afterCommit(static fn () => event(new SupplierReturnProcessed($id)));

                return $return->load(['items', 'stockMovements', 'journalEntries.lines']);
            });
        });
    }

    /** @return array{list<array{variant_id: int, quantity: string}>, Collection<int, ProductVariant>} */
    private function validateItems(SupplierReturnData $data): array
    {
        if (trim($data->reason) === '') {
            throw new InvalidPurchasingDataException('Supplier return reason must not be blank.');
        }

        if ($data->items === []) {
            throw new InvalidPurchasingDataException('A supplier return must contain at least one item.');
        }

        $variantIds = array_map(static fn ($item): int => $item->variantId, $data->items);

        if (count(array_unique($variantIds)) !== count($variantIds)) {
            throw new InvalidPurchasingDataException('A supplier return cannot contain duplicate product variants.');
        }

        $variants = ProductVariant::query()->whereKey($variantIds)->get(['id', 'cost_price'])->keyBy('id');

        if ($variants->count() !== count($variantIds)) {
            throw new InvalidPurchasingDataException('Every product variant must belong to the current tenant.');
        }

        $normalized = [];

        try {
            foreach ($data->items as $item) {
                $quantity = Quantity::from($item->quantity);

                if (! $quantity->isPositive()) {
                    throw new InvalidArgumentException('Supplier return quantities must be greater than zero.');
                }

                $normalized[] = ['variant_id' => $item->variantId, 'quantity' => $quantity->toDecimal()];
            }
        } catch (InvalidArgumentException $exception) {
            throw new InvalidPurchasingDataException($exception->getMessage(), previous: $exception);
        }

        return [$normalized, $variants];
    }

    private function lockBill(SupplierReturnData $data): ?Bill
    {
        if ($data->billId === null) {
            return null;
        }

        $bill = Bill::query()->lockForUpdate()->findOrFail($data->billId);
        $status = BillStatus::from((string) $bill->getRawOriginal('status'));

        if ($bill->branch_id !== $data->branchId || $bill->supplier_id !== $data->supplierId) {
            throw new InvalidPurchasingDataException('The linked bill branch and supplier must match the supplier return.');
        }

        if (! in_array($status, [BillStatus::Approved, BillStatus::PartiallyPaid], true)) {
            throw new InvalidBillStateException('Only an approved or partially paid bill can be linked to a supplier return.');
        }

        return $bill;
    }

    private function replay(SupplierReturn $return, string $payloadHash): SupplierReturn
    {
        if (! hash_equals($return->payload_hash, $payloadHash)) {
            throw new IdempotencyConflictException('The idempotency key was reused with a different supplier return.');
        }

        return $return->load(['items', 'stockMovements', 'journalEntries.lines']);
    }
}
