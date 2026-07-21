<?php

namespace App\Application\Actions\Sales;

use App\Application\Actions\Accounting\CreateJournalEntryAction;
use App\Application\DTOs\JournalEntryData;
use App\Application\Services\SystemChartOfAccountResolver;
use App\Domain\Entities\InvoiceStatus;
use App\Domain\Entities\PricedInvoiceItem;
use App\Domain\Entities\StockMovementData;
use App\Domain\Entities\StockMovementType;
use App\Domain\Events\InvoiceVoided;
use App\Domain\Exceptions\InvalidInvoiceStateException;
use App\Domain\Services\InvoiceDomainService;
use App\Domain\Services\StockMovementService;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\Invoice;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class VoidInvoiceAction
{
    public function __construct(
        private InvoiceDomainService $domain,
        private StockMovementService $stock,
        private SystemChartOfAccountResolver $accounts,
        private CreateJournalEntryAction $journals,
    ) {}

    public function handle(int $invoiceId, int $actingUserId): Invoice
    {
        return DB::transaction(function () use ($invoiceId, $actingUserId): Invoice {
            $invoice = Invoice::query()
                ->with(['items.tax:id,coa_id'])
                ->withCount('receipts')
                ->lockForUpdate()
                ->findOrFail($invoiceId);
            $rawStatus = $invoice->getRawOriginal('status');

            if (! is_string($rawStatus)
                || InvoiceStatus::from($rawStatus) !== InvoiceStatus::Issued
                || $invoice->receipts_count !== 0) {
                throw new InvalidInvoiceStateException('Only an issued invoice without receipts can be voided.');
            }

            $pricedItems = array_values($invoice->items->map(
                static fn ($item): PricedInvoiceItem => new PricedInvoiceItem(
                    $item->product_variant_id,
                    DecimalSnapshot::from($item, 'quantity'),
                    DecimalSnapshot::from($item, 'unit_price_at_sale'),
                    DecimalSnapshot::from($item, 'cost_price_at_sale'),
                    $item->tax_id,
                    $item->tax_id === null ? null : DecimalSnapshot::from($item, 'tax_rate_at_sale'),
                    $item->tax?->coa_id,
                ),
            )->all());
            $totals = $this->domain->calculate($pricedItems);

            $this->stock->bulkAdd(array_values($invoice->items->map(
                static fn ($item): StockMovementData => new StockMovementData(
                    $item->product_variant_id,
                    $invoice->branch_id,
                    DecimalSnapshot::from($item, 'quantity'),
                    DecimalSnapshot::from($item, 'cost_price_at_sale'),
                    StockMovementType::SalesReturn,
                    'invoice',
                    $invoice->getKey(),
                ),
            )->all()));

            $accountIds = $this->accounts->ids(['1200', '1300', '4000', '5000']);
            $this->journals->handle(new JournalEntryData(
                $invoice->branch_id,
                'JRN-VOID-'.$invoice->getKey(),
                'invoice',
                $invoice->getKey(),
                new DateTimeImmutable('today'),
                'Void '.$invoice->invoice_number,
                SalesJournalLineFactory::invoice($totals, $accountIds, true),
            ));

            $oldBalance = DecimalSnapshot::from($invoice, 'balance_due');
            $invoice->forceFill([
                'status' => InvoiceStatus::Voided,
                'balance_due' => '0.00',
            ])->save();

            AuditLog::query()->create([
                'user_id' => $actingUserId,
                'action' => 'INVOICE_VOIDED',
                'entity_type' => 'invoice',
                'entity_id' => $invoice->getKey(),
                'old_values' => ['status' => InvoiceStatus::Issued->value, 'balance_due' => $oldBalance],
                'new_values' => ['status' => InvoiceStatus::Voided->value, 'balance_due' => '0.00'],
            ]);

            $voidedInvoiceId = $invoice->getKey();
            DB::afterCommit(static fn () => event(new InvoiceVoided($voidedInvoiceId)));

            return $invoice->load(['items', 'journalEntries.lines']);
        });
    }
}
