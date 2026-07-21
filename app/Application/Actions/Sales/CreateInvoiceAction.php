<?php

namespace App\Application\Actions\Sales;

use App\Application\Actions\Accounting\CreateJournalEntryAction;
use App\Application\DTOs\InvoiceData;
use App\Application\DTOs\JournalEntryData;
use App\Application\Services\SystemChartOfAccountResolver;
use App\Domain\Entities\InvoiceItemRecord;
use App\Domain\Entities\InvoiceRecord;
use App\Domain\Entities\PricedInvoiceItem;
use App\Domain\Entities\StockMovementData;
use App\Domain\Entities\StockMovementType;
use App\Domain\Events\InvoiceCreated;
use App\Domain\Exceptions\InvalidSalesDataException;
use App\Domain\Repositories\SalesRepository;
use App\Domain\Services\InvoiceDomainService;
use App\Domain\Services\InvoiceNumberService;
use App\Domain\Services\StockMovementService;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\Customer;
use App\Infrastructure\Models\Invoice;
use App\Infrastructure\Models\ProductVariant;
use App\Infrastructure\Models\Tax;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OverflowException;

final readonly class CreateInvoiceAction
{
    public function __construct(
        private InvoiceNumberService $numbers,
        private InvoiceDomainService $domain,
        private SalesRepository $sales,
        private StockMovementService $stock,
        private SystemChartOfAccountResolver $accounts,
        private CreateJournalEntryAction $journals,
    ) {}

    public function handle(InvoiceData $data, int $actingUserId): Invoice
    {
        $number = $this->numbers->next((int) $data->invoiceDate->format('Y'));

        return DB::transaction(function () use ($data, $actingUserId, $number): Invoice {
            Branch::query()->findOrFail($data->branchId);
            Customer::query()->findOrFail($data->customerId);

            if ($data->items === []) {
                throw new InvalidSalesDataException('An invoice must contain at least one item.');
            }

            $variantIds = array_map(static fn ($item): int => $item->variantId, $data->items);

            if (count(array_unique($variantIds)) !== count($variantIds)) {
                throw new InvalidSalesDataException('An invoice cannot contain duplicate product variants.');
            }

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

            $pricedItems = [];

            foreach ($data->items as $item) {
                $variant = $variants->get($item->variantId);
                $tax = $item->taxId === null ? null : $taxes->get($item->taxId);

                if (! $variant instanceof ProductVariant || ($item->taxId !== null && ! $tax instanceof Tax)) {
                    throw new InvalidSalesDataException('A requested sales snapshot could not be loaded.');
                }

                $pricedItems[] = new PricedInvoiceItem(
                    $item->variantId,
                    $item->quantity,
                    $item->unitPrice,
                    DecimalSnapshot::from($variant, 'cost_price'),
                    $item->taxId,
                    $tax === null ? null : DecimalSnapshot::from($tax, 'rate'),
                    $tax?->coa_id,
                );
            }

            try {
                $totals = $this->domain->calculate($pricedItems);
            } catch (InvalidArgumentException|OverflowException $exception) {
                throw new InvalidSalesDataException($exception->getMessage(), previous: $exception);
            }

            if (! $totals->total->isPositive()) {
                throw new InvalidSalesDataException('Invoice gross total must be positive.');
            }

            $invoiceId = $this->sales->createInvoice(
                new InvoiceRecord(
                    $data->branchId,
                    $data->customerId,
                    $number,
                    $data->invoiceDate->format('Y-m-d'),
                    $data->dueDate?->format('Y-m-d'),
                    $totals->total->toDecimal(),
                    $totals->tax->toDecimal(),
                    $totals->total->toDecimal(),
                    $data->notes,
                ),
                array_map(
                    static fn ($line): InvoiceItemRecord => new InvoiceItemRecord(
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

            $this->stock->bulkDeduct(array_map(
                static fn ($line): StockMovementData => new StockMovementData(
                    $line->variantId,
                    $data->branchId,
                    $line->quantity,
                    $line->costPrice,
                    StockMovementType::SalesDeduction,
                    'invoice',
                    $invoiceId,
                ),
                $totals->lines,
            ));

            $accountIds = $this->accounts->ids(['1200', '1300', '4000', '5000']);
            $this->journals->handle(new JournalEntryData(
                $data->branchId,
                "JRN-SALE-{$invoiceId}",
                'invoice',
                $invoiceId,
                $data->invoiceDate,
                "Sale {$number}",
                SalesJournalLineFactory::invoice($totals, $accountIds),
            ));

            AuditLog::query()->create([
                'user_id' => $actingUserId,
                'action' => 'INVOICE_CREATED',
                'entity_type' => 'invoice',
                'entity_id' => $invoiceId,
                'new_values' => [
                    'invoice_number' => $number,
                    'branch_id' => $data->branchId,
                    'customer_id' => $data->customerId,
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

            DB::afterCommit(static fn () => event(new InvoiceCreated($invoiceId)));

            return Invoice::query()->with(['items', 'journalEntries.lines'])->findOrFail($invoiceId);
        });
    }
}
