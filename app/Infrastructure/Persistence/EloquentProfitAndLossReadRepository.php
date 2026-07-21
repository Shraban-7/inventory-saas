<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Entities\ChartOfAccountType;
use App\Domain\Entities\ProfitAndLossRollupRow;
use App\Domain\Repositories\ProfitAndLossReadRepository;
use App\Infrastructure\Models\ChartOfAccount;
use App\Infrastructure\Models\DailyAccountBalance;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentProfitAndLossReadRepository implements ProfitAndLossReadRepository
{
    public function totals(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?array $branchIds,
    ): array {
        if ($branchIds === []) {
            return [];
        }

        $balanceTable = (new DailyAccountBalance)->getTable();
        $accountTable = (new ChartOfAccount)->getTable();
        $tenantId = current_tenant_id();
        $query = $this->query();

        $query
            ->join(
                $accountTable,
                "{$accountTable}.id",
                '=',
                "{$balanceTable}.coa_id",
            )
            ->where("{$balanceTable}.tenant_id", $tenantId)
            ->where("{$accountTable}.tenant_id", $tenantId)
            ->whereColumn(
                "{$accountTable}.tenant_id",
                "{$balanceTable}.tenant_id",
            )
            ->where("{$balanceTable}.date", '>=', $start->format('Y-m-d'))
            ->where(
                "{$balanceTable}.date",
                '<',
                $end->modify('+1 day')->format('Y-m-d'),
            )
            ->whereIn("{$accountTable}.type", [
                ChartOfAccountType::Revenue->value,
                ChartOfAccountType::CostOfGoodsSold->value,
                ChartOfAccountType::Expense->value,
            ])
            ->select("{$accountTable}.type")
            ->selectRaw('SUM(debit_total) AS debit_total')
            ->selectRaw('SUM(credit_total) AS credit_total')
            ->groupBy("{$accountTable}.type");

        if ($branchIds !== null) {
            $query->whereIn("{$balanceTable}.branch_id", $branchIds);
        }

        $rows = [];

        foreach ($query->get() as $total) {
            $rows[] = new ProfitAndLossRollupRow(
                ChartOfAccountType::from((string) $total->getAttribute('type')),
                (string) $total->getAttribute('debit_total'),
                (string) $total->getAttribute('credit_total'),
            );
        }

        return $rows;
    }

    /** @return Builder<DailyAccountBalance> */
    private function query(): Builder
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
            || (app()->runningUnitTests() && DB::connection()->transactionLevel() > 0)) {
            return DailyAccountBalance::query();
        }

        DB::connection('reporting')->statement(
            'SET @current_tenant_id = ?',
            [current_tenant_id()],
        );
        app()->instance(EloquentJournalHistoryRepository::REPORTING_CONTEXT_BOUND, true);

        return DailyAccountBalance::on('reporting');
    }
}
