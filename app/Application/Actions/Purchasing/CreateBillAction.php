<?php

namespace App\Application\Actions\Purchasing;

use App\Application\DTOs\BillData;
use App\Domain\Entities\BillLineTotal;
use App\Domain\Entities\BillStatus;
use App\Domain\Entities\PricedBillItem;
use App\Domain\Entities\Quantity;
use App\Domain\Exceptions\InvalidPurchasingDataException;
use App\Domain\Services\BillDomainService;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\Bill;
use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\GoodsReceiptNote;
use App\Infrastructure\Models\GrnItem;
use App\Infrastructure\Models\ProductVariant;
use App\Infrastructure\Models\Supplier;
use App\Infrastructure\Models\Tax;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OverflowException;

final readonly class CreateBillAction
{
    public function __construct(private BillDomainService $domain) {}

    public function handle(BillData $data, int $actingUserId): Bill
    {
        try {
            return DB::transaction(function () use ($data, $actingUserId): Bill {
                Branch::query()->findOrFail($data->branchId);
                Supplier::query()->findOrFail($data->supplierId);

                if (trim($data->billNumber) === '') {
                    throw new InvalidPurchasingDataException('Bill number must not be blank.');
                }

                if ($data->items === []) {
                    throw new InvalidPurchasingDataException('A bill must contain at least one item.');
                }

                $variantIds = array_map(static fn ($item): int => $item->variantId, $data->items);

                if (count(array_unique($variantIds)) !== count($variantIds)) {
                    throw new InvalidPurchasingDataException('A bill cannot contain duplicate product variants.');
                }

                if (ProductVariant::query()->whereKey($variantIds)->count() !== count($variantIds)) {
                    throw new InvalidPurchasingDataException('Every product variant must belong to the current tenant.');
                }

                $taxIds = array_values(array_unique(array_filter(
                    array_map(static fn ($item): ?int => $item->taxId, $data->items),
                    static fn (?int $id): bool => $id !== null,
                )));
                $taxes = Tax::query()->whereKey($taxIds)->get(['id', 'rate', 'coa_id'])->keyBy('id');

                if ($taxes->count() !== count($taxIds)) {
                    throw new InvalidPurchasingDataException('Every tax must belong to the current tenant.');
                }

                $pricedItems = [];

                foreach ($data->items as $item) {
                    $tax = $item->taxId === null ? null : $taxes->get($item->taxId);
                    $pricedItems[] = new PricedBillItem(
                        $item->variantId,
                        $item->quantity,
                        $item->unitCost,
                        $item->taxId,
                        $tax === null ? null : (string) $tax->rate,
                        $tax?->coa_id,
                    );
                }

                try {
                    $totals = $this->domain->calculate($pricedItems);
                } catch (InvalidArgumentException|OverflowException $exception) {
                    throw new InvalidPurchasingDataException($exception->getMessage(), previous: $exception);
                }

                if ($data->grnId !== null) {
                    $this->validateGrnMatch($data, $totals->lines);
                }

                $bill = Bill::query()->create([
                    'branch_id' => $data->branchId,
                    'supplier_id' => $data->supplierId,
                    'grn_id' => $data->grnId,
                    'bill_number' => trim($data->billNumber),
                    'bill_date' => $data->billDate->format('Y-m-d'),
                    'due_date' => $data->dueDate?->format('Y-m-d'),
                    'status' => BillStatus::Draft,
                    'total_amount' => $totals->total->toDecimal(),
                    'tax_amount' => $totals->tax->toDecimal(),
                    'balance_due' => $totals->total->toDecimal(),
                ]);
                $bill->items()->createMany(array_map(static fn ($line): array => [
                    'product_variant_id' => $line->variantId,
                    'tax_id' => $line->taxId,
                    'quantity' => $line->quantity,
                    'unit_cost' => $line->unitCost,
                    'tax_rate_snapshot' => $line->taxRate,
                    'line_total' => $line->total->toDecimal(),
                ], $totals->lines));

                AuditLog::query()->create([
                    'user_id' => $actingUserId,
                    'action' => 'BILL_CREATED',
                    'entity_type' => 'bill',
                    'entity_id' => $bill->getKey(),
                    'new_values' => [
                        'bill_number' => $bill->bill_number,
                        'branch_id' => $data->branchId,
                        'supplier_id' => $data->supplierId,
                        'grn_id' => $data->grnId,
                        'gross' => $totals->gross->toDecimal(),
                        'tax' => $totals->tax->toDecimal(),
                        'total' => $totals->total->toDecimal(),
                    ],
                ]);

                return $bill->load('items');
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new InvalidPurchasingDataException('Bill number already exists for the current tenant.', previous: $exception);
        }
    }

    /** @param list<BillLineTotal> $lines */
    private function validateGrnMatch(BillData $data, array $lines): void
    {
        $grn = GoodsReceiptNote::query()->lockForUpdate()->findOrFail($data->grnId);

        if ($grn->branch_id !== $data->branchId || $grn->supplier_id !== $data->supplierId) {
            throw new InvalidPurchasingDataException('The bill branch and supplier must match the goods receipt.');
        }

        $received = GrnItem::query()
            ->where('grn_id', $grn->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_variant_id');
        $reserved = [];
        $existingBills = Bill::query()
            ->where('grn_id', $grn->getKey())
            ->where('status', '!=', BillStatus::Cancelled)
            ->with('items')
            ->lockForUpdate()
            ->get();

        foreach ($existingBills as $existingBill) {
            foreach ($existingBill->items as $item) {
                $reserved[$item->product_variant_id] = ($reserved[$item->product_variant_id] ?? Quantity::from(0))
                    ->add(Quantity::from((string) $item->quantity));
            }
        }

        foreach ($lines as $line) {
            $grnItem = $received->get($line->variantId);

            if (! $grnItem instanceof GrnItem) {
                throw new InvalidPurchasingDataException('Every billed variant must exist on the goods receipt.');
            }

            if (! Quantity::from((string) $grnItem->unit_cost)->equals(Quantity::from($line->unitCost))) {
                throw new InvalidPurchasingDataException('Bill unit cost must match the goods receipt snapshot.');
            }

            $newReserved = ($reserved[$line->variantId] ?? Quantity::from(0))->add(Quantity::from($line->quantity));

            if ($newReserved->compare(Quantity::from((string) $grnItem->quantity_received)) > 0) {
                throw new InvalidPurchasingDataException('Billed quantity exceeds the unbilled goods receipt quantity.');
            }
        }
    }
}
