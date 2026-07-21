<?php

use App\Application\Actions\Reporting\GenerateProfitAndLossAction;
use App\Application\DTOs\JournalEntryLineData;
use App\Application\Jobs\AggregateJournalRollupsJob;
use App\Application\Jobs\GenerateProfitAndLossReportJob;
use App\Domain\Entities\ReportJobStatus;
use App\Domain\Entities\ReportJobType;
use App\Infrastructure\Models\AccountingPeriod;
use App\Infrastructure\Models\ReportJob;
use App\Infrastructure\Models\Role;
use App\Infrastructure\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\AccountingContext;

afterEach(fn () => app()->forgetInstance('current_tenant'));

function accountingSecurityKey(): string
{
    return (string) Str::uuid();
}

it('requires authentication on every accounting endpoint', function (string $method, string $uri) {
    $this->json($method, $uri, [], ['Idempotency-Key' => accountingSecurityKey()])
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json');
})->with([
    'chart of accounts' => ['GET', '/api/v1/chart-of-accounts'],
    'journal list' => ['GET', '/api/v1/journal-entries'],
    'journal detail' => ['GET', '/api/v1/journal-entries/1'],
    'manual journal' => ['POST', '/api/v1/journal-entries'],
    'queue report' => ['POST', '/api/v1/reports/profit-and-loss'],
    'report status' => ['GET', '/api/v1/reports/jobs/missing'],
    'report result' => ['GET', '/api/v1/reports/jobs/missing/result'],
    'period lock' => ['PUT', '/api/v1/accounting-periods/1/lock'],
]);

it('denies cashiers all accounting and report reads', function (string $uri) {
    $context = AccountingContext::create('Cashier');

    $this->actingAs($context->purchasing->sales->user)
        ->getJson($uri)
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json');
})->with([
    '/api/v1/chart-of-accounts',
    '/api/v1/journal-entries',
    '/api/v1/reports/jobs/missing',
]);

it('allows managers to read and queue reports but not post manual journals', function () {
    $context = AccountingContext::create('Manager');
    Queue::fake();
    $this->actingAs($context->purchasing->sales->user)
        ->getJson('/api/v1/chart-of-accounts')
        ->assertSuccessful();
    $this->getJson('/api/v1/journal-entries')->assertSuccessful();
    $this->postJson('/api/v1/reports/profit-and-loss', [
        'start' => '2026-01-01',
        'end' => '2026-01-31',
    ], ['Idempotency-Key' => accountingSecurityKey()])
        ->assertAccepted();
    $this->postJson(
        '/api/v1/journal-entries',
        $context->manualJournalPayload(),
        ['Idempotency-Key' => accountingSecurityKey()],
    )->assertForbidden();
});

it('requires an accountant role for manual posting even when admin is present', function () {
    $context = AccountingContext::create('Admin');
    $payload = $context->manualJournalPayload();
    $this->actingAs($context->purchasing->sales->user)
        ->postJson('/api/v1/journal-entries', $payload, ['Idempotency-Key' => accountingSecurityKey()])
        ->assertForbidden();

    $context->addRole('Accountant');
    $this->postJson('/api/v1/journal-entries', $payload, ['Idempotency-Key' => accountingSecurityKey()])
        ->assertCreated();
});

it('allows accountants to post only within their assigned branch', function () {
    $context = AccountingContext::create('Accountant');
    $branchId = $context->purchasing->sales->branch->getKey();
    $context->useScopedRole('Accountant', $branchId);
    $this->actingAs($context->purchasing->sales->user)
        ->postJson(
            '/api/v1/journal-entries',
            $context->manualJournalPayload(branchId: $branchId),
            ['Idempotency-Key' => accountingSecurityKey()],
        )->assertCreated();
    $this->postJson(
        '/api/v1/journal-entries',
        $context->manualJournalPayload(branchId: $context->purchasing->sales->otherBranch->getKey()),
        ['Idempotency-Key' => accountingSecurityKey()],
    )->assertForbidden();
});

it('does not widen a scoped accountant through a tenant wide manager role', function () {
    $context = AccountingContext::create('Accountant');
    $branchId = $context->purchasing->sales->branch->getKey();
    $context->useScopedRole('Accountant', $branchId);
    $context->addRole('Manager');

    $this->actingAs($context->purchasing->sales->user)
        ->postJson(
            '/api/v1/journal-entries',
            $context->manualJournalPayload(branchId: $context->purchasing->sales->otherBranch->getKey()),
            ['Idempotency-Key' => accountingSecurityKey()],
        )->assertForbidden();
});

it('allows only tenant wide admins to lock accounting periods', function () {
    $context = AccountingContext::create('Admin');
    $period = AccountingPeriod::query()->create([
        'tenant_id' => $context->purchasing->sales->tenant->getKey(),
        'year' => 2026,
        'month' => 8,
    ]);
    $context->useScopedRole('Admin', $context->purchasing->sales->branch->getKey());
    $this->actingAs($context->purchasing->sales->user)
        ->putJson(
            "/api/v1/accounting-periods/{$period->getKey()}/lock",
            [],
            ['Idempotency-Key' => accountingSecurityKey()],
        )->assertForbidden();

    $context->purchasing->sales->user->roles()->detach();
    $context->addRole('Admin');
    $this->putJson(
        "/api/v1/accounting-periods/{$period->getKey()}/lock",
        [],
        ['Idempotency-Key' => accountingSecurityKey()],
    )->assertSuccessful();
});

it('hides or rejects every foreign tenant accounting identifier', function () {
    $tenantA = AccountingContext::create('Admin');
    $tenantA->addRole('Accountant');
    $tenantB = AccountingContext::create('Admin');
    $foreignCoaId = $tenantB->account('1100')->getKey();
    $foreignBranchId = $tenantB->purchasing->sales->branch->getKey();
    $foreignJournal = $tenantB->createJournal('JRN-FOREIGN-1');
    $foreignPeriod = AccountingPeriod::query()->create([
        'tenant_id' => $tenantB->purchasing->sales->tenant->getKey(),
        'year' => 2026,
        'month' => 9,
    ]);
    $foreignReport = ReportJob::query()->create([
        'tenant_id' => $tenantB->purchasing->sales->tenant->getKey(),
        'requested_by_user_id' => $tenantB->purchasing->sales->user->getKey(),
        'type' => ReportJobType::ProfitAndLoss,
        'status' => ReportJobStatus::Queued,
        'parameters' => ['start' => '2026-01-01', 'end' => '2026-01-31', 'branch_ids' => null],
        'parameter_hash' => hash('sha256', 'foreign'),
    ]);
    app()->instance('current_tenant', $tenantA->purchasing->sales->tenant);
    $this->actingAs($tenantA->purchasing->sales->user);

    $payload = $tenantA->manualJournalPayload();
    $payload['lines'][0]['coa_id'] = $foreignCoaId;
    $this->postJson('/api/v1/journal-entries', $payload, ['Idempotency-Key' => accountingSecurityKey()])
        ->assertUnprocessable();
    $payload = $tenantA->manualJournalPayload(branchId: $foreignBranchId);
    $this->postJson('/api/v1/journal-entries', $payload, ['Idempotency-Key' => accountingSecurityKey()])
        ->assertUnprocessable();
    $this->getJson("/api/v1/journal-entries/{$foreignJournal->getKey()}")->assertNotFound();
    $this->putJson(
        "/api/v1/accounting-periods/{$foreignPeriod->getKey()}/lock",
        [],
        ['Idempotency-Key' => accountingSecurityKey()],
    )->assertNotFound();
    $this->getJson("/api/v1/reports/jobs/{$foreignReport->getKey()}")->assertNotFound();
    $this->getJson("/api/v1/reports/jobs/{$foreignReport->getKey()}/result")->assertNotFound();
    $this->postJson('/api/v1/reports/profit-and-loss', [
        'start' => '2026-01-01',
        'end' => '2026-01-31',
        'branch_id' => $foreignBranchId,
    ], ['Idempotency-Key' => accountingSecurityKey()])
        ->assertUnprocessable();
});

it('restricts report job status and results to the requester or tenant wide admin', function () {
    $context = AccountingContext::create('Manager');
    $requester = $context->purchasing->sales->user;
    $report = ReportJob::query()->create([
        'tenant_id' => $context->purchasing->sales->tenant->getKey(),
        'requested_by_user_id' => $requester->getKey(),
        'type' => ReportJobType::ProfitAndLoss,
        'status' => ReportJobStatus::Completed,
        'parameters' => ['start' => '2026-01-01', 'end' => '2026-01-31', 'branch_ids' => null],
        'parameter_hash' => hash('sha256', 'authorization'),
        'result' => [
            'period' => ['start' => '2026-01-01', 'end' => '2026-01-31'],
            'scope' => ['branch_ids' => null],
            'revenue' => '0.00',
            'cogs' => '0.00',
            'gross_profit' => '0.00',
            'operating_expenses' => '0.00',
            'net_profit' => '0.00',
        ],
    ]);
    $manager = User::factory()->create(['tenant_id' => $context->purchasing->sales->tenant->getKey()]);
    $manager->assignRole(Role::query()->where('name', 'Manager')->firstOrFail());
    $admin = User::factory()->create(['tenant_id' => $context->purchasing->sales->tenant->getKey()]);
    $admin->assignRole(Role::query()->where('name', 'Admin')->firstOrFail());

    $this->actingAs($requester)
        ->getJson("/api/v1/reports/jobs/{$report->getKey()}")
        ->assertSuccessful();
    $this->getJson("/api/v1/reports/jobs/{$report->getKey()}/result")
        ->assertSuccessful();
    $this->actingAs($manager)
        ->getJson("/api/v1/reports/jobs/{$report->getKey()}")
        ->assertForbidden();
    $this->getJson("/api/v1/reports/jobs/{$report->getKey()}/result")
        ->assertForbidden();
    $this->actingAs($admin)
        ->getJson("/api/v1/reports/jobs/{$report->getKey()}")
        ->assertSuccessful();
    $this->getJson("/api/v1/reports/jobs/{$report->getKey()}/result")
        ->assertSuccessful();
});

it('excludes other branch balances from a branch scoped report result', function () {
    $context = AccountingContext::create('Manager');
    $branchId = $context->purchasing->sales->branch->getKey();
    $otherBranchId = $context->purchasing->sales->otherBranch->getKey();
    $context->createJournal('JRN-SECURITY-OWN', '2026-04-10', $branchId, [
        new JournalEntryLineData($context->account('1100')->getKey(), '50.00', '0.00', null),
        new JournalEntryLineData($context->account('4000')->getKey(), '0.00', '50.00', null),
    ]);
    $context->createJournal('JRN-SECURITY-OTHER', '2026-04-10', $otherBranchId, [
        new JournalEntryLineData($context->account('1100')->getKey(), '999.00', '0.00', null),
        new JournalEntryLineData($context->account('4000')->getKey(), '0.00', '999.00', null),
    ]);
    (new AggregateJournalRollupsJob(
        $context->purchasing->sales->tenant->getKey(),
        '2026-04-10',
    ))->handle();
    $context->useScopedRole('Manager', $branchId);
    Queue::fake();

    $reportJobId = (string) $this->actingAs($context->purchasing->sales->user)
        ->postJson('/api/v1/reports/profit-and-loss', [
            'start' => '2026-04-01',
            'end' => '2026-04-30',
        ], ['Idempotency-Key' => accountingSecurityKey()])
        ->assertAccepted()
        ->json('data.id');
    (new GenerateProfitAndLossReportJob(
        $context->purchasing->sales->tenant->getKey(),
        $reportJobId,
    ))->handle(app(GenerateProfitAndLossAction::class));

    $this->getJson("/api/v1/reports/jobs/{$reportJobId}/result")
        ->assertSuccessful()
        ->assertJsonPath('data.scope.branch_ids', [$branchId])
        ->assertJsonPath('data.revenue', '50.00')
        ->assertJsonPath('data.net_profit', '50.00')
        ->assertJsonMissing(['revenue' => '1049.00']);
});
