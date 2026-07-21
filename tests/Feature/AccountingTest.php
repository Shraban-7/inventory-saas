<?php

use App\Application\Actions\Accounting\CreateJournalEntryAction;
use App\Application\Actions\Reporting\GenerateProfitAndLossAction;
use App\Application\DTOs\JournalEntryData;
use App\Application\DTOs\JournalEntryLineData;
use App\Application\Jobs\AggregateJournalRollupsJob;
use App\Application\Jobs\GenerateProfitAndLossReportJob;
use App\Domain\Entities\Money;
use App\Domain\Entities\ReportJobStatus;
use App\Domain\Exceptions\ImmutableRecordException;
use App\Domain\Exceptions\InvalidJournalEntryException;
use App\Domain\Exceptions\UnbalancedJournalEntryException;
use App\Infrastructure\Models\AccountingPeriod;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\ChartOfAccount;
use App\Infrastructure\Models\DailyAccountBalance;
use App\Infrastructure\Models\Invoice;
use App\Infrastructure\Models\JournalEntry;
use App\Infrastructure\Models\JournalEntryLine;
use App\Infrastructure\Models\ReportJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\AccountingContext;

afterEach(fn () => app()->forgetInstance('current_tenant'));

function accountingFeatureKey(): string
{
    return (string) Str::uuid();
}

/** @param list<array<string, mixed>> $tree */
function accountingFeatureFlattenTree(array $tree): array
{
    $accounts = [];

    foreach ($tree as $account) {
        $children = is_array($account['children'] ?? null) ? $account['children'] : [];
        unset($account['children']);
        $accounts[] = $account;
        $accounts = [...$accounts, ...accountingFeatureFlattenTree($children)];
    }

    return $accounts;
}

/** @return array<string, array{debit: string, credit: string}> */
function accountingFeatureJournalLines(JournalEntry $entry): array
{
    return $entry->load('lines.account')->lines->mapWithKeys(fn (JournalEntryLine $line): array => [
        $line->account->code => ['debit' => $line->debit, 'credit' => $line->credit],
    ])->all();
}

it('seeds complete independent chart of account trees and caches only arrays', function () {
    $tenantA = AccountingContext::create('Manager');
    $tenantB = AccountingContext::create('Manager');
    $tenantBAccountId = $tenantB->account('1100')->getKey();
    $this->actingAs($tenantA->purchasing->sales->user);

    $response = $this->getJson('/api/v1/chart-of-accounts')->assertSuccessful();
    $accounts = accountingFeatureFlattenTree($response->json('data'));
    $codes = array_column($accounts, 'code');
    sort($codes);

    expect($codes)->toBe(['1100', '1200', '1300', '2000', '2050', '2100', '4000', '5000', '6000'])
        ->and(ChartOfAccount::query()
            ->where('tenant_id', $tenantA->purchasing->sales->tenant->getKey())
            ->count())->toBe(9)
        ->and(Cache::get("tenant:{$tenantA->purchasing->sales->tenant->getKey()}:coa:v1"))->toBeArray()
        ->and(Cache::get("tenant:{$tenantA->purchasing->sales->tenant->getKey()}:coa:v1")[0] ?? null)
        ->toBeArray();

    app()->instance('current_tenant', $tenantB->purchasing->sales->tenant);
    expect(ChartOfAccount::query()
        ->where('tenant_id', $tenantB->purchasing->sales->tenant->getKey())
        ->count())->toBe(9);

    app()->instance('current_tenant', $tenantA->purchasing->sales->tenant);
    $this->actingAs($tenantA->purchasing->sales->user)
        ->getJson('/api/v1/chart-of-accounts')
        ->assertJsonMissing(['id' => $tenantBAccountId]);
});

it('invalidates a cached account tree after direct account creation', function () {
    $context = AccountingContext::create('Manager');
    $this->actingAs($context->purchasing->sales->user)
        ->getJson('/api/v1/chart-of-accounts')
        ->assertSuccessful()
        ->assertJsonMissing(['code' => '6100']);

    ChartOfAccount::query()->create([
        'tenant_id' => $context->purchasing->sales->tenant->getKey(),
        'code' => '6100',
        'name' => 'Utilities',
        'type' => 'expense',
    ]);

    expect(Cache::has("tenant:{$context->purchasing->sales->tenant->getKey()}:coa:v1"))->toBeFalse();
    $this->getJson('/api/v1/chart-of-accounts')
        ->assertSuccessful()
        ->assertJsonFragment(['code' => '6100', 'name' => 'Utilities']);
});

it('rejects updates and deletes for journal headers and lines', function () {
    $context = AccountingContext::create();
    $entry = $context->createJournal('JRN-IMMUTABLE-1');
    $line = $entry->lines()->firstOrFail();

    expect(fn () => $entry->update(['description' => 'Changed']))
        ->toThrow(ImmutableRecordException::class)
        ->and(fn () => $entry->delete())
        ->toThrow(ImmutableRecordException::class)
        ->and(fn () => $line->update(['description' => 'Changed']))
        ->toThrow(ImmutableRecordException::class)
        ->and(fn () => $line->delete())
        ->toThrow(ImmutableRecordException::class);
});

it('validates journals before writes and persists balanced entries atomically', function () {
    $context = AccountingContext::create();
    $cash = $context->account('1100');
    $revenue = $context->account('4000');
    $unbalanced = new JournalEntryData(
        $context->purchasing->sales->branch->getKey(),
        'JRN-INVALID-1',
        null,
        null,
        new DateTimeImmutable('2025-03-15'),
        'Unbalanced',
        [
            new JournalEntryLineData($cash->getKey(), '1.00', '0.00', null),
            new JournalEntryLineData($revenue->getKey(), '0.00', '0.99', null),
        ],
    );

    expect(fn () => DB::transaction(fn () => app(CreateJournalEntryAction::class)->handle($unbalanced)))
        ->toThrow(UnbalancedJournalEntryException::class)
        ->and(JournalEntry::query()->count())->toBe(0)
        ->and(JournalEntryLine::query()->count())->toBe(0)
        ->and(AccountingPeriod::query()->count())->toBe(0);

    $entry = $context->createJournal('JRN-BALANCED-1', '2025-03-15');
    expect($entry->lines()->count())->toBe(2)
        ->and(AccountingPeriod::query()->where('year', 2025)->where('month', 3)->count())->toBe(1);
});

it('requires journal reference fields to both be null or both be present', function (?string $type, ?int $id) {
    $context = AccountingContext::create();

    expect(fn () => $context->createJournal(
        'JRN-REFERENCE-'.accountingFeatureKey(),
        referenceType: $type,
        referenceId: $id,
    ))->toThrow(InvalidJournalEntryException::class)
        ->and(JournalEntry::query()->count())->toBe(0);
})->with([
    'type only' => ['invoice', null],
    'id only' => [null, 1],
]);

it('creates an exact balanced immutable auditable idempotent manual journal', function () {
    $context = AccountingContext::create('Accountant');
    $key = accountingFeatureKey();
    $payload = $context->manualJournalPayload();
    $this->actingAs($context->purchasing->sales->user);

    $first = $this->postJson('/api/v1/journal-entries', $payload, ['Idempotency-Key' => $key])
        ->assertCreated()
        ->assertJsonPath('data.journal_entry_number', 'JRN-MAN-2026-00001')
        ->assertJsonPath('data.reference_type', null)
        ->assertJsonPath('data.reference_id', null)
        ->assertJsonPath('data.posted_at', '2026-07-22')
        ->assertJsonPath('data.lines.0.debit', '25.10')
        ->assertJsonPath('data.lines.1.credit', '25.10');
    $entryId = (int) $first->json('data.id');
    $responseBody = $first->json();

    expect(JournalEntry::query()->count())->toBe(1)
        ->and(JournalEntryLine::query()->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'MANUAL_JOURNAL_CREATED')->where('entity_id', $entryId)->count())->toBe(1)
        ->and(fn () => JournalEntry::query()->findOrFail($entryId)->delete())
        ->toThrow(ImmutableRecordException::class);

    $replay = $this->postJson('/api/v1/journal-entries', $payload, ['Idempotency-Key' => $key])
        ->assertCreated();
    expect($replay->json())->toBe($responseBody)
        ->and(JournalEntry::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'MANUAL_JOURNAL_CREATED')->count())->toBe(1);
});

it('returns accounting problem details for invalid and unbalanced manual journals', function (array $mutations) {
    $context = AccountingContext::create('Accountant');
    $payload = array_replace_recursive($context->manualJournalPayload(), $mutations);

    $this->actingAs($context->purchasing->sales->user)
        ->postJson('/api/v1/journal-entries', $payload, ['Idempotency-Key' => accountingFeatureKey()])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json');

    expect(JournalEntry::query()->count())->toBe(0);
})->with([
    'both debit and credit' => [['lines' => [0 => ['credit' => '1.00']]]],
    'unbalanced' => [['lines' => [1 => ['credit' => '24.99']]]],
]);

it('cursor paginates and filters journal history by reference date and branch scope', function () {
    $context = AccountingContext::create('Manager');
    $invoice = Invoice::query()->create([
        'branch_id' => $context->purchasing->sales->branch->getKey(),
        'customer_id' => $context->purchasing->sales->customer->getKey(),
        'invoice_number' => 'INV-2026-95001',
        'invoice_date' => '2026-07-20',
        'total_amount' => '10.00',
        'tax_amount' => '0.00',
        'balance_due' => '10.00',
    ]);
    $first = $context->createJournal(
        'JRN-LIST-1',
        '2026-07-20',
        referenceType: 'invoice',
        referenceId: $invoice->getKey(),
    );
    $second = $context->createJournal('JRN-LIST-2', '2026-07-21');
    $otherBranch = $context->createJournal(
        'JRN-LIST-OTHER',
        '2026-07-20',
        $context->purchasing->sales->otherBranch->getKey(),
    );
    $context->useScopedRole('Manager', $context->purchasing->sales->branch->getKey());
    $this->actingAs($context->purchasing->sales->user);

    $page = $this->getJson('/api/v1/journal-entries?per_page=1')
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $second->getKey())
        ->assertJsonMissing(['id' => $otherBranch->getKey()]);
    $cursor = $page->json('meta.next_cursor');
    expect($cursor)->toBeString();
    $this->getJson('/api/v1/journal-entries?per_page=1&cursor='.urlencode($cursor))
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $first->getKey());

    $this->getJson('/api/v1/journal-entries?date_from=2026-07-20&date_to=2026-07-20&reference_type=invoice')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $first->getKey());
    $this->getJson("/api/v1/journal-entries/{$first->getKey()}")
        ->assertSuccessful()
        ->assertJsonPath('data.lines.0.account.code', '1100');
});

it('forbids journal detail outside an authorized branch', function () {
    $context = AccountingContext::create('Manager');
    $entry = $context->createJournal(
        'JRN-BRANCH-DENIED',
        branchId: $context->purchasing->sales->otherBranch->getKey(),
    );
    $context->useScopedRole('Manager', $context->purchasing->sales->branch->getKey());

    $this->actingAs($context->purchasing->sales->user)
        ->getJson("/api/v1/journal-entries/{$entry->getKey()}")
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('locks an accounting period idempotently and rejects posting into it', function () {
    $context = AccountingContext::create('Admin');
    $period = AccountingPeriod::query()->create([
        'tenant_id' => $context->purchasing->sales->tenant->getKey(),
        'year' => 2026,
        'month' => 7,
    ]);
    $key = accountingFeatureKey();
    $this->actingAs($context->purchasing->sales->user);

    $first = $this->putJson(
        "/api/v1/accounting-periods/{$period->getKey()}/lock",
        [],
        ['Idempotency-Key' => $key],
    )->assertSuccessful()
        ->assertJsonPath('data.is_locked', true)
        ->assertJsonPath('data.locked_by_user_id', $context->purchasing->sales->user->getKey());
    $this->putJson(
        "/api/v1/accounting-periods/{$period->getKey()}/lock",
        [],
        ['Idempotency-Key' => $key],
    )->assertSuccessful()->assertExactJson($first->json());

    expect(AuditLog::query()->where('action', 'ACCOUNTING_PERIOD_LOCKED')->count())->toBe(1);
    $context->addRole('Accountant');
    $this->postJson(
        '/api/v1/journal-entries',
        $context->manualJournalPayload(),
        ['Idempotency-Key' => accountingFeatureKey()],
    )->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'urn:problem:accounting-period-locked');
    expect(JournalEntry::query()->count())->toBe(0);
});

it('clears GRNI to exactly zero after a full linked receipt and bill approval', function () {
    $context = AccountingContext::create('Admin');
    $this->actingAs($context->purchasing->sales->user);
    $orderId = (int) $this->postJson(
        '/api/v1/purchase-orders',
        $context->purchasing->purchaseOrderPayload(),
        ['Idempotency-Key' => accountingFeatureKey()],
    )->assertCreated()->json('data.id');
    $this->putJson(
        "/api/v1/purchase-orders/{$orderId}/confirm",
        [],
        ['Idempotency-Key' => accountingFeatureKey()],
    )->assertSuccessful();
    $grnId = (int) $this->postJson(
        '/api/v1/goods-receipt-notes',
        $context->purchasing->goodsReceiptPayload($orderId),
        ['Idempotency-Key' => accountingFeatureKey()],
    )->assertCreated()->json('data.id');
    $billId = (int) $this->postJson(
        '/api/v1/bills',
        $context->purchasing->billPayload($grnId),
        ['Idempotency-Key' => accountingFeatureKey()],
    )->assertCreated()->json('data.id');
    $this->putJson(
        "/api/v1/bills/{$billId}/approve",
        [],
        ['Idempotency-Key' => accountingFeatureKey()],
    )->assertSuccessful();

    $grniId = $context->account('2050')->getKey();
    $debit = Money::fromDecimal((string) JournalEntryLine::query()->where('coa_id', $grniId)->sum('debit'));
    $credit = Money::fromDecimal((string) JournalEntryLine::query()->where('coa_id', $grniId)->sum('credit'));
    expect($debit->toDecimal())->toBe('21.25')
        ->and($credit->toDecimal())->toBe('21.25')
        ->and($debit->subtract($credit)->toDecimal())->toBe('0.00');
});

it('posts the exact FIFO sale accounting pattern including tax', function () {
    $context = AccountingContext::create('Admin');
    $invoiceId = (int) $this->actingAs($context->purchasing->sales->user)
        ->postJson(
            '/api/v1/invoices',
            $context->purchasing->sales->invoicePayload(),
            ['Idempotency-Key' => accountingFeatureKey()],
        )->assertCreated()->json('data.id');
    $journal = JournalEntry::query()
        ->where('reference_type', 'invoice')
        ->where('reference_id', $invoiceId)
        ->firstOrFail();

    expect(accountingFeatureJournalLines($journal))->toBe([
        '1200' => ['debit' => '21.50', 'credit' => '0.00'],
        '4000' => ['debit' => '0.00', 'credit' => '20.00'],
        '2100' => ['debit' => '0.00', 'credit' => '1.50'],
        '5000' => ['debit' => '8.50', 'credit' => '0.00'],
        '1300' => ['debit' => '0.00', 'credit' => '8.50'],
    ]);
});

it('replaces rollups idempotently for a backdated day and separates branches', function () {
    $context = AccountingContext::create();
    $context->createJournal('JRN-ROLLUP-A', '2025-01-03');
    $context->createJournal(
        'JRN-ROLLUP-B',
        '2025-01-03',
        $context->purchasing->sales->otherBranch->getKey(),
        [
            new JournalEntryLineData($context->account('1100')->getKey(), '7.25', '0.00', null),
            new JournalEntryLineData($context->account('4000')->getKey(), '0.00', '7.25', null),
        ],
    );
    $job = new AggregateJournalRollupsJob(
        $context->purchasing->sales->tenant->getKey(),
        '2025-01-03',
    );

    $job->handle();
    $job->handle();

    expect(DailyAccountBalance::query()->count())->toBe(4)
        ->and(DailyAccountBalance::query()->distinct()->count('branch_id'))->toBe(2)
        ->and(DailyAccountBalance::query()
            ->where('branch_id', $context->purchasing->sales->otherBranch->getKey())
            ->where('coa_id', $context->account('4000')->getKey())
            ->value('credit_total'))->toBe('7.25')
        ->and(DailyAccountBalance::query()->whereDate('date', '2025-01-03')->count())->toBe(4);
});

it('queues completes and serves an exact profit and loss report from rollups', function () {
    $context = AccountingContext::create('Manager');
    $branchId = $context->purchasing->sales->branch->getKey();
    $context->createJournal('JRN-PL-REVENUE', '2026-06-30', $branchId, [
        new JournalEntryLineData($context->account('1100')->getKey(), '100.00', '0.00', null),
        new JournalEntryLineData($context->account('4000')->getKey(), '0.00', '100.00', null),
    ]);
    $context->createJournal('JRN-PL-COGS', '2026-06-30', $branchId, [
        new JournalEntryLineData($context->account('5000')->getKey(), '40.00', '0.00', null),
        new JournalEntryLineData($context->account('1300')->getKey(), '0.00', '40.00', null),
    ]);
    $context->createJournal('JRN-PL-EXPENSE', '2026-06-30', $branchId, [
        new JournalEntryLineData($context->account('6000')->getKey(), '10.00', '0.00', null),
        new JournalEntryLineData($context->account('1100')->getKey(), '0.00', '10.00', null),
    ]);
    (new AggregateJournalRollupsJob(
        $context->purchasing->sales->tenant->getKey(),
        '2026-06-30',
    ))->handle();

    Queue::fake();
    $response = $this->actingAs($context->purchasing->sales->user)
        ->postJson('/api/v1/reports/profit-and-loss', [
            'start' => '2026-06-01',
            'end' => '2026-06-30',
            'branch_id' => $branchId,
        ], ['Idempotency-Key' => accountingFeatureKey()])
        ->assertAccepted()
        ->assertJsonPath('data.status', 'queued');
    $reportJobId = (string) $response->json('data.id');
    Queue::assertPushed(
        GenerateProfitAndLossReportJob::class,
        fn (GenerateProfitAndLossReportJob $job): bool => $job->tenantId === $context->purchasing->sales->tenant->getKey()
            && $job->reportJobId === $reportJobId
            && $job->queue === 'reports',
    );
    expect(ReportJob::query()->findOrFail($reportJobId)->parameters)->toMatchArray([
        'start' => '2026-06-01',
        'end' => '2026-06-30',
        'branch_ids' => [$branchId],
    ]);

    $this->getJson("/api/v1/reports/jobs/{$reportJobId}/result")
        ->assertConflict()
        ->assertJsonPath('type', 'urn:problem:accounting-report-not-ready');

    (new GenerateProfitAndLossReportJob(
        $context->purchasing->sales->tenant->getKey(),
        $reportJobId,
    ))->handle(app(GenerateProfitAndLossAction::class));

    $job = ReportJob::query()->findOrFail($reportJobId);
    expect($job->getRawOriginal('status'))->toBe(ReportJobStatus::Completed->value)
        ->and($job->started_at)->not->toBeNull()
        ->and($job->completed_at)->not->toBeNull()
        ->and($job->result)->toMatchArray([
            'period' => ['start' => '2026-06-01', 'end' => '2026-06-30'],
            'scope' => ['branch_ids' => [$branchId]],
            'revenue' => '100.00',
            'cogs' => '40.00',
            'gross_profit' => '60.00',
            'operating_expenses' => '10.00',
            'net_profit' => '50.00',
        ]);

    $this->getJson("/api/v1/reports/jobs/{$reportJobId}")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath(
            'data.result_url',
            route('reports.jobs.result', ['reportJobId' => $reportJobId]),
        );
    $this->getJson("/api/v1/reports/jobs/{$reportJobId}/result")
        ->assertSuccessful()
        ->assertJsonPath('data.net_profit', '50.00')
        ->assertJsonPath('data.scope.branch_ids.0', $branchId);

    $job->forceFill(['status' => ReportJobStatus::Expired])->save();
    $this->getJson("/api/v1/reports/jobs/{$reportJobId}/result")
        ->assertGone()
        ->assertJsonPath('type', 'urn:problem:accounting-report-expired');
});

it('persists the exact authorized branch scope for an implicit scoped report', function () {
    $context = AccountingContext::create('Manager');
    $branchId = $context->purchasing->sales->branch->getKey();
    $context->useScopedRole('Manager', $branchId);
    Queue::fake();

    $reportJobId = (string) $this->actingAs($context->purchasing->sales->user)
        ->postJson('/api/v1/reports/profit-and-loss', [
            'start' => '2026-01-01',
            'end' => '2026-12-31',
        ], ['Idempotency-Key' => accountingFeatureKey()])
        ->assertAccepted()
        ->json('data.id');

    expect(ReportJob::query()->findOrFail($reportJobId)->parameters)->toMatchArray([
        'start' => '2026-01-01',
        'end' => '2026-12-31',
        'branch_ids' => [$branchId],
    ]);
    $this->postJson('/api/v1/reports/profit-and-loss', [
        'start' => '2026-01-01',
        'end' => '2026-12-31',
        'branch_id' => $context->purchasing->sales->otherBranch->getKey(),
    ], ['Idempotency-Key' => accountingFeatureKey()])
        ->assertForbidden();
});
