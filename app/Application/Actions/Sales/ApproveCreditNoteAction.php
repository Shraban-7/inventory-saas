<?php

namespace App\Application\Actions\Sales;

use App\Application\Actions\Accounting\CreateJournalEntryAction;
use App\Application\DTOs\JournalEntryData;
use App\Application\Services\SystemChartOfAccountResolver;
use App\Domain\Entities\CreditNoteStatus;
use App\Domain\Entities\PricedInvoiceItem;
use App\Domain\Entities\StockMovementData;
use App\Domain\Entities\StockMovementType;
use App\Domain\Events\CreditNoteApproved;
use App\Domain\Exceptions\InvalidCreditNoteStateException;
use App\Domain\Services\InvoiceDomainService;
use App\Domain\Services\StockMovementService;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\CreditNote;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class ApproveCreditNoteAction
{
    public function __construct(
        private InvoiceDomainService $domain,
        private StockMovementService $stock,
        private SystemChartOfAccountResolver $accounts,
        private CreateJournalEntryAction $journals,
    ) {}

    public function handle(int $creditNoteId, int $actingUserId): CreditNote
    {
        return DB::transaction(function () use ($creditNoteId, $actingUserId): CreditNote {
            $creditNote = CreditNote::query()
                ->with(['items.tax:id,coa_id'])
                ->lockForUpdate()
                ->findOrFail($creditNoteId);
            $rawStatus = $creditNote->getRawOriginal('status');

            if (! is_string($rawStatus) || CreditNoteStatus::from($rawStatus) !== CreditNoteStatus::Draft) {
                throw new InvalidCreditNoteStateException('Only a draft credit note can be approved.');
            }

            $totals = $this->domain->calculate(array_values($creditNote->items->map(
                static fn ($item): PricedInvoiceItem => new PricedInvoiceItem(
                    $item->product_variant_id,
                    DecimalSnapshot::from($item, 'quantity'),
                    DecimalSnapshot::from($item, 'unit_price'),
                    DecimalSnapshot::from($item, 'cost_price_at_return'),
                    $item->tax_id,
                    $item->tax_id === null ? null : DecimalSnapshot::from($item, 'tax_rate_at_return'),
                    $item->tax?->coa_id,
                ),
            )->all()));

            $this->stock->bulkAdd(array_values($creditNote->items->map(
                static fn ($item): StockMovementData => new StockMovementData(
                    $item->product_variant_id,
                    $creditNote->branch_id,
                    DecimalSnapshot::from($item, 'quantity'),
                    DecimalSnapshot::from($item, 'cost_price_at_return'),
                    StockMovementType::SalesReturn,
                    'credit_note',
                    $creditNote->getKey(),
                ),
            )->all()));

            $accountIds = $this->accounts->ids(['1200', '1300', '4000', '5000']);
            $this->journals->handle(new JournalEntryData(
                $creditNote->branch_id,
                'JRN-CN-'.$creditNote->getKey(),
                'credit_note',
                $creditNote->getKey(),
                new DateTimeImmutable('today'),
                'Approve credit note '.$creditNote->getKey(),
                SalesJournalLineFactory::invoice($totals, $accountIds, true),
            ));

            $creditNote->forceFill(['status' => CreditNoteStatus::Approved])->save();

            AuditLog::query()->create([
                'user_id' => $actingUserId,
                'action' => 'CREDIT_NOTE_APPROVED',
                'entity_type' => 'credit_note',
                'entity_id' => $creditNote->getKey(),
                'old_values' => ['status' => CreditNoteStatus::Draft->value],
                'new_values' => [
                    'status' => CreditNoteStatus::Approved->value,
                    'total' => $totals->total->toDecimal(),
                    'cost' => $totals->cost->toDecimal(),
                ],
            ]);

            $approvedCreditNoteId = $creditNote->getKey();
            DB::afterCommit(static fn () => event(new CreditNoteApproved($approvedCreditNoteId)));

            return $creditNote->load(['items', 'journalEntries.lines']);
        });
    }
}
