<?php

use App\Application\DTOs\JournalEntryLineData;
use App\Application\Jobs\AggregateJournalRollupsJob;
use App\Domain\Entities\Money;
use App\Domain\Exceptions\LockedAccountingPeriodException;
use App\Domain\Repositories\ProfitAndLossReadRepository;
use App\Infrastructure\Models\AccountingPeriod;
use App\Infrastructure\Models\DailyAccountBalance;
use App\Infrastructure\Models\JournalEntry;
use App\Infrastructure\Models\JournalEntryLine;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingContext;
use Tests\Support\SalesWorkers;

beforeEach(function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL or MariaDB is required for accounting concurrency coverage.');
    }
});

afterEach(function (): void {
    if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        DB::statement('SET @current_tenant_id = NULL');
        DB::connection('reporting')->statement('SET @current_tenant_id = NULL');
    }

    app()->forgetInstance('current_tenant');
});

/** @param list<array<string, mixed>> $results */
function assertNoAccountingDatabaseRaceFailure(array $results): void
{
    foreach ($results as $result) {
        $message = mb_strtolower((string) ($result['message'] ?? ''));

        expect($message)
            ->not->toContain('deadlock')
            ->not->toContain('duplicate entry')
            ->not->toContain('lock wait timeout');
    }
}

it('allocates unique sequential manual journal numbers across concurrent postings', function () {
    $context = AccountingContext::create('Accountant');
    $tenantId = (int) $context->purchasing->sales->tenant->getKey();
    $cashId = (int) $context->account('1100')->getKey();
    $revenueId = (int) $context->account('4000')->getKey();
    $payloads = [];

    foreach (range(1, 10) as $sequence) {
        $amount = number_format(10 + $sequence / 100, 2, '.', '');
        $payloads[] = [
            'tenant_id' => $tenantId,
            'user_id' => $context->purchasing->sales->user->getKey(),
            'branch_id' => $context->purchasing->sales->branch->getKey(),
            'posted_at' => '2026-07-22',
            'description' => "Concurrent manual journal {$sequence}",
            'lines' => [
                ['coa_id' => $cashId, 'debit' => $amount, 'credit' => '0.00'],
                ['coa_id' => $revenueId, 'debit' => '0.00', 'credit' => $amount],
            ],
        ];
    }

    $results = SalesWorkers::run('create-manual-journal', $payloads);
    $numbers = collect($results)->pluck('journal_entry_number')->sort()->values()->all();
    $expected = array_map(
        static fn (int $sequence): string => sprintf('JRN-MAN-2026-%05d', $sequence),
        range(1, 10),
    );
    $journals = JournalEntry::query()
        ->where('tenant_id', $tenantId)
        ->whereIn('journal_entry_number', $expected)
        ->with('lines')
        ->get();

    assertNoAccountingDatabaseRaceFailure($results);
    expect(collect($results)->where('ok', true))->toHaveCount(10)
        ->and($numbers)->toBe($expected)
        ->and(array_values(array_unique($numbers)))->toBe($expected)
        ->and($journals)->toHaveCount(10);

    foreach ($journals as $journal) {
        $debit = Money::zero();
        $credit = Money::zero();

        foreach ($journal->lines as $line) {
            $debit = $debit->add(Money::fromDecimal($line->debit));
            $credit = $credit->add(Money::fromDecimal($line->credit));
        }

        expect($debit->compare($credit))->toBe(0)
            ->and($journal->lines)->toHaveCount(2);
    }
});

it('linearizes a concurrent period lock and manual post without partial writes', function () {
    $context = AccountingContext::create('Admin');
    $tenantId = (int) $context->purchasing->sales->tenant->getKey();
    $period = AccountingPeriod::query()->create([
        'tenant_id' => $tenantId,
        'year' => 2026,
        'month' => 7,
    ]);
    $basePayload = [
        'tenant_id' => $tenantId,
        'user_id' => $context->purchasing->sales->user->getKey(),
    ];
    $results = SalesWorkers::runMixed([
        [
            'mode' => 'lock-accounting-period',
            'payload' => [...$basePayload, 'period_id' => $period->getKey()],
        ],
        [
            'mode' => 'create-manual-journal',
            'payload' => [
                ...$basePayload,
                'branch_id' => $context->purchasing->sales->branch->getKey(),
                'posted_at' => '2026-07-22',
                'description' => 'Period lock race',
                'lines' => [
                    [
                        'coa_id' => $context->account('1100')->getKey(),
                        'debit' => '12.34',
                        'credit' => '0.00',
                    ],
                    [
                        'coa_id' => $context->account('4000')->getKey(),
                        'debit' => '0.00',
                        'credit' => '12.34',
                    ],
                ],
            ],
        ],
    ]);
    $lockResult = $results[0];
    $postResult = $results[1];
    $journalCount = JournalEntry::query()
        ->where('tenant_id', $tenantId)
        ->where('description', 'Period lock race')
        ->count();

    assertNoAccountingDatabaseRaceFailure($results);
    expect($lockResult['ok'])->toBeTrue()
        ->and($lockResult['is_locked'])->toBeTrue()
        ->and($period->refresh()->is_locked)->toBeTrue();

    if ($postResult['ok'] === true) {
        expect($journalCount)->toBe(1)
            ->and(JournalEntryLine::query()
                ->where('tenant_id', $tenantId)
                ->where('journal_entry_id', $postResult['journal_entry_id'])
                ->count())->toBe(2);
    } else {
        expect($postResult['error'])->toBe(LockedAccountingPeriodException::class)
            ->and($journalCount)->toBe(0)
            ->and(JournalEntryLine::query()->where('tenant_id', $tenantId)->count())->toBe(0);
    }
})->repeat(3);

it('concurrently replaces journal rollups without duplicate aggregate rows', function () {
    $context = AccountingContext::create();
    $tenantId = (int) $context->purchasing->sales->tenant->getKey();
    $branchId = (int) $context->purchasing->sales->branch->getKey();
    $otherBranchId = (int) $context->purchasing->sales->otherBranch->getKey();
    $cashId = (int) $context->account('1100')->getKey();
    $revenueId = (int) $context->account('4000')->getKey();
    $expenseId = (int) $context->account('6000')->getKey();
    $context->createJournal('JRN-ROLLUP-RACE-A1', '2026-05-04', $branchId, [
        new JournalEntryLineData($cashId, '10.00', '0.00', null),
        new JournalEntryLineData($revenueId, '0.00', '10.00', null),
    ]);
    $context->createJournal('JRN-ROLLUP-RACE-A2', '2026-05-04', $branchId, [
        new JournalEntryLineData($cashId, '5.25', '0.00', null),
        new JournalEntryLineData($revenueId, '0.00', '5.25', null),
    ]);
    $context->createJournal('JRN-ROLLUP-RACE-B', '2026-05-04', $otherBranchId, [
        new JournalEntryLineData($expenseId, '4.50', '0.00', null),
        new JournalEntryLineData($cashId, '0.00', '4.50', null),
    ]);
    DailyAccountBalance::query()->create([
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'coa_id' => $cashId,
        'date' => '2026-05-04',
        'debit_total' => '999.00',
        'credit_total' => '999.00',
    ]);
    $payload = ['tenant_id' => $tenantId, 'date' => '2026-05-04'];

    $results = SalesWorkers::run('aggregate-journal-rollup', [$payload, $payload]);
    $rows = DailyAccountBalance::query()
        ->where('tenant_id', $tenantId)
        ->whereDate('date', '2026-05-04')
        ->get()
        ->keyBy(fn (DailyAccountBalance $balance): string => "{$balance->branch_id}:{$balance->coa_id}");
    $duplicates = DailyAccountBalance::query()
        ->where('tenant_id', $tenantId)
        ->whereDate('date', '2026-05-04')
        ->select(['branch_id', 'coa_id'])
        ->selectRaw('COUNT(*) AS aggregate_count')
        ->groupBy('branch_id', 'coa_id')
        ->havingRaw('COUNT(*) > 1')
        ->count();

    assertNoAccountingDatabaseRaceFailure($results);
    expect(collect($results)->where('ok', true))->toHaveCount(2)
        ->and($rows)->toHaveCount(4)
        ->and($duplicates)->toBe(0)
        ->and($rows->get("{$branchId}:{$cashId}")?->debit_total)->toBe('15.25')
        ->and($rows->get("{$branchId}:{$cashId}")?->credit_total)->toBe('0.00')
        ->and($rows->get("{$branchId}:{$revenueId}")?->credit_total)->toBe('15.25')
        ->and($rows->get("{$otherBranchId}:{$expenseId}")?->debit_total)->toBe('4.50')
        ->and($rows->get("{$otherBranchId}:{$cashId}")?->credit_total)->toBe('4.50');
});

it('reads exact profit and loss rollups on reporting with sequential tenant isolation', function () {
    $tenantA = AccountingContext::create();
    $tenantAId = (int) $tenantA->purchasing->sales->tenant->getKey();
    $branchAId = (int) $tenantA->purchasing->sales->branch->getKey();
    $tenantA->createJournal('JRN-REPORTING-A-REVENUE', '2026-06-30', $branchAId, [
        new JournalEntryLineData($tenantA->account('1100')->getKey(), '100.00', '0.00', null),
        new JournalEntryLineData($tenantA->account('4000')->getKey(), '0.00', '100.00', null),
    ]);
    $tenantA->createJournal('JRN-REPORTING-A-COGS', '2026-06-30', $branchAId, [
        new JournalEntryLineData($tenantA->account('5000')->getKey(), '40.00', '0.00', null),
        new JournalEntryLineData($tenantA->account('1300')->getKey(), '0.00', '40.00', null),
    ]);
    $tenantA->createJournal('JRN-REPORTING-A-EXPENSE', '2026-06-30', $branchAId, [
        new JournalEntryLineData($tenantA->account('6000')->getKey(), '10.00', '0.00', null),
        new JournalEntryLineData($tenantA->account('1100')->getKey(), '0.00', '10.00', null),
    ]);
    (new AggregateJournalRollupsJob($tenantAId, '2026-06-30'))->handle();

    $tenantB = AccountingContext::create();
    $tenantBId = (int) $tenantB->purchasing->sales->tenant->getKey();
    $branchBId = (int) $tenantB->purchasing->sales->branch->getKey();
    $tenantB->createJournal('JRN-REPORTING-B-REVENUE', '2026-06-30', $branchBId, [
        new JournalEntryLineData($tenantB->account('1100')->getKey(), '999.00', '0.00', null),
        new JournalEntryLineData($tenantB->account('4000')->getKey(), '0.00', '999.00', null),
    ]);
    (new AggregateJournalRollupsJob($tenantBId, '2026-06-30'))->handle();

    $reportingConnections = [];
    DB::listen(function (QueryExecuted $query) use (&$reportingConnections): void {
        if (str_contains($query->sql, 'daily_account_balances')) {
            $reportingConnections[] = $query->connectionName;
        }
    });
    $repository = app(ProfitAndLossReadRepository::class);

    app()->instance('current_tenant', $tenantA->purchasing->sales->tenant);
    $rowsA = collect($repository->totals(
        new DateTimeImmutable('2026-06-01'),
        new DateTimeImmutable('2026-06-30'),
        [$branchAId],
    ))->mapWithKeys(fn ($row): array => [
        $row->accountType->value => [
            'debit' => $row->debit->toDecimal(),
            'credit' => $row->credit->toDecimal(),
        ],
    ])->all();
    ksort($rowsA);
    $sessionTenantA = (int) DB::connection('reporting')->scalar('SELECT @current_tenant_id');

    app()->instance('current_tenant', $tenantB->purchasing->sales->tenant);
    $rowsB = collect($repository->totals(
        new DateTimeImmutable('2026-06-01'),
        new DateTimeImmutable('2026-06-30'),
        [$branchBId],
    ))->mapWithKeys(fn ($row): array => [
        $row->accountType->value => [
            'debit' => $row->debit->toDecimal(),
            'credit' => $row->credit->toDecimal(),
        ],
    ])->all();
    ksort($rowsB);
    $sessionTenantB = (int) DB::connection('reporting')->scalar('SELECT @current_tenant_id');

    expect($reportingConnections)->not->toBeEmpty()
        ->and(array_unique($reportingConnections))->toBe(['reporting'])
        ->and($sessionTenantA)->toBe($tenantAId)
        ->and($sessionTenantB)->toBe($tenantBId)
        ->and($rowsA)->toBe([
            'cogs' => ['debit' => '40.00', 'credit' => '0.00'],
            'expense' => ['debit' => '10.00', 'credit' => '0.00'],
            'revenue' => ['debit' => '0.00', 'credit' => '100.00'],
        ])
        ->and($rowsB)->toBe([
            'revenue' => ['debit' => '0.00', 'credit' => '999.00'],
        ]);
});
