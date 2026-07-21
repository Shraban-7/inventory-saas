<?php

namespace App\Application\Actions\Sales;

use App\Application\DTOs\CreditNoteData;
use App\Domain\Entities\CreditNoteItemRecord;
use App\Domain\Entities\CreditNoteRecord;
use App\Domain\Entities\CreditNoteStatus;
use App\Domain\Entities\PricedInvoiceItem;
use App\Domain\Entities\Quantity;
use App\Domain\Exceptions\CreditQuantityExceededException;
use App\Domain\Exceptions\InvalidSalesDataException;
use App\Domain\Repositories\SalesRepository;
use App\Domain\Services\InvoiceDomainService;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\CreditNote;
use App\Infrastructure\Models\CreditNoteItem;
use App\Infrastructure\Models\Customer;
use App\Infrastructure\Models\Invoice;
use App\Infrastructure\Models\InvoiceItem;
use App\Infrastructure\Models\ProductVariant;
use App\Infrastructure\Models\Tax;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OverflowException;

final readonly class CreateCreditNoteAction
{
    public function __construct(
        private InvoiceDomainService $domain,
        private SalesRepository $sales,
    ) {}

    public function handle(CreditNoteData $data, int $actingUserId): CreditNote
    {
        return DB::transaction(function () use ($data, $actingUserId): CreditNote {
            Branch::query()->findOrFail($data->branchId);
            Customer::query()->findOrFail($data->customerId);

            if (trim($data->reason) === '' || mb_strlen($data->reason) > 255) {
                throw new InvalidSalesDataException('Credit note reason must be non-empty and at most 255 characters.');
            }

            if ($data->items === []) {
                throw new InvalidSalesDataException('A credit note must contain at least one item.');
            }

            $variantIds = array_map(static fn ($item): int => $item->variantId, $data->items);

            if (count(array_unique($variantIds)) !== count($variantIds)) {
                throw new InvalidSalesDataException('A credit note cannot contain duplicate product variants.');
            }

            $pricedItems = $data->invoiceId === null
                ? $this->standaloneItems($data, $variantIds)
                : $this->linkedItems($data, $variantIds);

            try {
                $totals = $this->domain->calculate($pricedItems);
            } catch (InvalidArgumentException|OverflowException $exception) {
                throw new InvalidSalesDataException($exception->getMessage(), previous: $exception);
            }

            if (! $totals->total->isPositive()) {
                throw new InvalidSalesDataException('Credit note gross total must be positive.');
            }

            $creditNoteId = $this->sales->createCreditNoteDraft(
                new CreditNoteRecord(
                    $data->branchId,
                    $data->customerId,
                    $data->invoiceId,
                    $data->reason,
                    $totals->total->toDecimal(),
                ),
                array_map(
                    static fn ($line): CreditNoteItemRecord => new CreditNoteItemRecord(
                        $line->variantId,
                        $line->taxId,
                        $line->quantity,
                        $line->unitPrice,
                        $line->costPrice,
                        $line->taxRate,
                        $line->gross->toDecimal(),
                    ),
                    $totals->lines,
                ),
            );

            AuditLog::query()->create([
                'user_id' => $actingUserId,
                'action' => 'CREDIT_NOTE_CREATED',
                'entity_type' => 'credit_note',
                'entity_id' => $creditNoteId,
                'new_values' => [
                    'invoice_id' => $data->invoiceId,
                    'branch_id' => $data->branchId,
                    'customer_id' => $data->customerId,
                    'reason' => $data->reason,
                    'subtotal' => $totals->subtotal->toDecimal(),
                    'tax' => $totals->tax->toDecimal(),
                    'total' => $totals->total->toDecimal(),
                    'cost' => $totals->cost->toDecimal(),
                    'items' => array_map(static fn ($line): array => [
                        'variant_id' => $line->variantId,
                        'quantity' => $line->quantity,
                        'unit_price' => $line->unitPrice,
                        'cost_price' => $line->costPrice,
                        'tax_id' => $line->taxId,
                        'tax_rate' => $line->taxRate,
                        'line_total' => $line->gross->toDecimal(),
                    ], $totals->lines),
                ],
            ]);

            return CreditNote::query()->with('items')->findOrFail($creditNoteId);
        });
    }

    /**
     * @param  list<int>  $variantIds
     * @return list<PricedInvoiceItem>
     */
    private function standaloneItems(CreditNoteData $data, array $variantIds): array
    {
        $variants = ProductVariant::query()->whereKey($variantIds)->get(['id', 'cost_price'])->keyBy('id');

        if ($variants->count() !== count($variantIds)) {
            throw new InvalidSalesDataException('Every product variant must belong to the current tenant.');
        }

        $taxIds = array_values(array_unique(array_filter(
            array_map(static fn ($item): ?int => $item->taxId, $data->items),
            static fn (?int $id): bool => $id !== null,
        )));
        $taxes = Tax::query()->whereKey($taxIds)->get(['id', 'rate', 'coa_id'])->keyBy('id');

        if ($taxes->count() !== count($taxIds)) {
            throw new InvalidSalesDataException('Every tax must belong to the current tenant.');
        }

        $items = [];

        foreach ($data->items as $item) {
            if ($item->unitPrice === null) {
                throw new InvalidSalesDataException('Standalone credit note items require a unit price.');
            }

            $tax = $item->taxId === null ? null : $taxes->get($item->taxId);
            $variant = $variants->get($item->variantId);

            if (! $variant instanceof ProductVariant || ($item->taxId !== null && ! $tax instanceof Tax)) {
                throw new InvalidSalesDataException('A requested return snapshot could not be loaded.');
            }

            $items[] = new PricedInvoiceItem(
                $item->variantId,
                $item->quantity,
                $item->unitPrice,
                DecimalSnapshot::from($variant, 'cost_price'),
                $item->taxId,
                $tax === null ? null : DecimalSnapshot::from($tax, 'rate'),
                $tax?->coa_id,
            );
        }

        return $items;
    }

    /**
     * @param  list<int>  $variantIds
     * @return list<PricedInvoiceItem>
     */
    private function linkedItems(CreditNoteData $data, array $variantIds): array
    {
        $invoice = Invoice::query()
            ->with(['items.tax:id,coa_id'])
            ->lockForUpdate()
            ->findOrFail($data->invoiceId);

        if ($invoice->branch_id !== $data->branchId || $invoice->customer_id !== $data->customerId) {
            throw new InvalidSalesDataException('The linked invoice must have the same branch and customer as the credit note.');
        }

        $soldItems = $invoice->items->keyBy('product_variant_id');

        foreach ($variantIds as $variantId) {
            if (! $soldItems->has($variantId)) {
                throw new InvalidSalesDataException('Every returned variant must exist on the linked invoice.');
            }
        }

        $alreadyReturned = [];

        foreach (CreditNoteItem::query()
            ->whereIn('product_variant_id', $variantIds)
            ->whereHas('creditNote', static fn ($query) => $query
                ->where('invoice_id', $data->invoiceId)
                ->where('status', '!=', CreditNoteStatus::Cancelled))
            ->get(['product_variant_id', 'quantity']) as $item) {
            $alreadyReturned[$item->product_variant_id] = ($alreadyReturned[$item->product_variant_id] ?? Quantity::from(0))
                ->add(Quantity::from(DecimalSnapshot::from($item, 'quantity')));
        }

        $items = [];

        foreach ($data->items as $item) {
            $sold = $soldItems->get($item->variantId);

            if (! $sold instanceof InvoiceItem) {
                throw new InvalidSalesDataException('A sold item snapshot could not be loaded.');
            }

            try {
                $requested = Quantity::from($item->quantity);
                $remaining = Quantity::from(DecimalSnapshot::from($sold, 'quantity'))
                    ->subtract($alreadyReturned[$item->variantId] ?? Quantity::from(0))
                    ->subtract($requested);
            } catch (InvalidArgumentException $exception) {
                throw new InvalidSalesDataException($exception->getMessage(), previous: $exception);
            }

            if (! $requested->isPositive() || $remaining->isNegative()) {
                throw new CreditQuantityExceededException('Returned quantity cannot exceed the quantity sold.');
            }

            $items[] = new PricedInvoiceItem(
                $item->variantId,
                $requested->toDecimal(),
                DecimalSnapshot::from($sold, 'unit_price_at_sale'),
                DecimalSnapshot::from($sold, 'cost_price_at_sale'),
                $sold->tax_id,
                $sold->tax_id === null ? null : DecimalSnapshot::from($sold, 'tax_rate_at_sale'),
                $sold->tax?->coa_id,
            );
        }

        return $items;
    }
}
