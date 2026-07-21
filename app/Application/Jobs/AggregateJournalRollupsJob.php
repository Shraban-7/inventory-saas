<?php

namespace App\Application\Jobs;

use App\Infrastructure\Models\DailyAccountBalance;
use App\Infrastructure\Models\JournalEntry;
use App\Infrastructure\Models\JournalEntryLine;
use App\Infrastructure\Models\Tenant;
use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use InvalidArgumentException;

class AggregateJournalRollupsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    public int $timeout = 60;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $date,
    ) {
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('The rollup date must use the Y-m-d format.');
        }

        $this->onQueue('reports');
    }

    public function uniqueId(): string
    {
        return "{$this->tenantId}:{$this->date}";
    }

    public function handle(): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if (! $tenant instanceof Tenant) {
            throw (new ModelNotFoundException)->setModel(Tenant::class, [$this->tenantId]);
        }

        $resolvedPreviousTenant = app()->bound('current_tenant')
            ? app()->make('current_tenant')
            : null;
        $previousTenant = $resolvedPreviousTenant instanceof Tenant
            ? $resolvedPreviousTenant
            : null;
        $connection = $tenant->getConnection();
        $usesMySqlSession = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true);
        $previousSessionTenantId = $usesMySqlSession
            ? $connection->scalar('SELECT @current_tenant_id')
            : null;

        app()->instance('current_tenant', $tenant);

        try {
            if ($usesMySqlSession) {
                $connection->statement('SET @current_tenant_id = ?', [$tenant->getKey()]);
            }

            $this->aggregate();
        } finally {
            try {
                if ($usesMySqlSession) {
                    $connection->statement(
                        'SET @current_tenant_id = ?',
                        [$previousSessionTenantId],
                    );
                }
            } finally {
                if ($previousTenant !== null) {
                    app()->instance('current_tenant', $previousTenant);
                } else {
                    app()->forgetInstance('current_tenant');
                }
            }
        }
    }

    private function aggregate(): void
    {
        $lineTable = (new JournalEntryLine)->getTable();
        $entryTable = (new JournalEntry)->getTable();
        $nextDate = (new DateTimeImmutable($this->date))
            ->modify('+1 day')
            ->format('Y-m-d');

        $totals = JournalEntryLine::query()
            ->join(
                $entryTable,
                "{$entryTable}.id",
                '=',
                "{$lineTable}.journal_entry_id",
            )
            ->where("{$lineTable}.tenant_id", $this->tenantId)
            ->where("{$entryTable}.tenant_id", $this->tenantId)
            ->whereColumn(
                "{$entryTable}.tenant_id",
                "{$lineTable}.tenant_id",
            )
            ->where("{$entryTable}.posted_at", '>=', $this->date)
            ->where("{$entryTable}.posted_at", '<', $nextDate)
            ->select([
                "{$entryTable}.branch_id",
                "{$lineTable}.coa_id",
            ])
            ->selectRaw('SUM(journal_entry_lines.debit) AS debit_total')
            ->selectRaw('SUM(journal_entry_lines.credit) AS credit_total')
            ->groupBy(
                "{$entryTable}.branch_id",
                "{$lineTable}.coa_id",
            )
            ->get();

        if ($totals->isEmpty()) {
            return;
        }

        $timestamp = now();
        $rows = [];

        foreach ($totals as $total) {
            $rows[] = [
                'tenant_id' => $this->tenantId,
                'branch_id' => (int) $total->getAttribute('branch_id'),
                'coa_id' => (int) $total->getAttribute('coa_id'),
                'date' => $this->date,
                'debit_total' => (string) $total->getAttribute('debit_total'),
                'credit_total' => (string) $total->getAttribute('credit_total'),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        DailyAccountBalance::query()->upsert(
            $rows,
            ['tenant_id', 'branch_id', 'coa_id', 'date'],
            ['debit_total', 'credit_total', 'updated_at'],
        );
    }
}
