<?php

use App\Application\Actions\Accounting\CreateJournalEntryAction;
use App\Application\DTOs\JournalEntryData;
use App\Application\DTOs\JournalEntryLineData;
use App\Domain\Exceptions\ImmutableRecordException;
use App\Domain\Exceptions\TransactionRequiredException;
use App\Domain\Exceptions\UnbalancedJournalEntryException;
use App\Infrastructure\Models\ChartOfAccount;
use App\Infrastructure\Models\Invoice;
use App\Infrastructure\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Tests\Support\SalesContext;

afterEach(fn () => app()->forgetInstance('current_tenant'));

function journalDataForSales(SalesContext $context, array $lines): JournalEntryData
{
    $invoice = Invoice::query()->create([
        'branch_id' => $context->branch->getKey(),
        'customer_id' => $context->customer->getKey(),
        'invoice_number' => 'INV-2026-90001',
        'invoice_date' => '2026-07-22',
        'total_amount' => '10.00',
        'tax_amount' => '0.00',
        'balance_due' => '10.00',
    ]);

    return new JournalEntryData(
        $context->branch->getKey(),
        'JRN-UNIT-1',
        'invoice',
        $invoice->getKey(),
        new DateTimeImmutable('2026-07-22'),
        'Unit journal',
        $lines,
    );
}

it('rejects unbalanced entries before writing a header or lines', function () {
    $context = SalesContext::create();
    $account = ChartOfAccount::query()->where('code', '1100')->firstOrFail();
    $data = journalDataForSales($context, [
        new JournalEntryLineData($account->getKey(), '10.00', '0.00', null),
        new JournalEntryLineData($account->getKey(), '0.00', '9.99', null),
    ]);

    expect(fn () => DB::transaction(fn () => app(CreateJournalEntryAction::class)->handle($data)))
        ->toThrow(UnbalancedJournalEntryException::class)
        ->and(JournalEntry::query()->count())->toBe(0);
});

it('creates a balanced immutable journal header and lines in an existing transaction', function () {
    $context = SalesContext::create();
    $cash = ChartOfAccount::query()->where('code', '1100')->firstOrFail();
    $receivable = ChartOfAccount::query()->where('code', '1200')->firstOrFail();
    $data = journalDataForSales($context, [
        new JournalEntryLineData($cash->getKey(), '10.00', '0.00', 'Cash'),
        new JournalEntryLineData($receivable->getKey(), '0.00', '10.00', 'Receivable'),
    ]);

    $entry = DB::transaction(fn () => app(CreateJournalEntryAction::class)->handle($data));

    expect($entry->journal_entry_number)->toBe('JRN-UNIT-1')
        ->and($entry->lines()->count())->toBe(2)
        ->and($entry->lines()->sum('debit'))->toEqual(10.0)
        ->and($entry->lines()->sum('credit'))->toEqual(10.0)
        ->and(fn () => $entry->update(['description' => 'Changed']))
        ->toThrow(ImmutableRecordException::class);
});

it('requires an existing database transaction', function () {
    $context = SalesContext::create();
    $account = ChartOfAccount::query()->where('code', '1100')->firstOrFail();
    $data = journalDataForSales($context, [
        new JournalEntryLineData($account->getKey(), '1.00', '0.00', null),
        new JournalEntryLineData($account->getKey(), '0.00', '1.00', null),
    ]);
    DB::shouldReceive('transactionLevel')->once()->andReturn(0);

    expect(fn () => app(CreateJournalEntryAction::class)->handle($data))
        ->toThrow(TransactionRequiredException::class);
});
