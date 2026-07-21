<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Repositories\JournalHistoryRepository;
use App\Infrastructure\Models\JournalEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

final class EloquentJournalHistoryRepository implements JournalHistoryRepository
{
    public const REPORTING_CONTEXT_BOUND = 'reporting_tenant_context_bound';

    public function paginate(
        ?array $branchIds,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $referenceType,
        int $perPage,
    ): CursorPaginator {
        $query = $this->query()->with('branch');

        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds);
        }
        if ($dateFrom !== null) {
            $query->whereDate('posted_at', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->whereDate('posted_at', '<=', $dateTo);
        }
        if ($referenceType !== null) {
            $query->where('reference_type', $referenceType);
        }

        return $query->orderByDesc('id')->cursorPaginate($perPage);
    }

    public function find(int $journalEntryId): ?JournalEntry
    {
        return $this->query()
            ->with(['branch', 'reference', 'lines.account'])
            ->find($journalEntryId);
    }

    /** @return Builder<JournalEntry> */
    private function query(): Builder
    {
        $defaultDriver = DB::connection()->getDriverName();

        if (! in_array($defaultDriver, ['mysql', 'mariadb'], true)
            || (app()->runningUnitTests() && DB::connection()->transactionLevel() > 0)) {
            return JournalEntry::query();
        }

        $this->setReportingTenantContext();

        return JournalEntry::on('reporting');
    }

    private function setReportingTenantContext(): void
    {
        DB::connection('reporting')->statement(
            'SET @current_tenant_id = ?',
            [current_tenant_id()],
        );
        app()->instance(self::REPORTING_CONTEXT_BOUND, true);
    }
}
