<?php

namespace App\Domain\Services;

use App\Domain\Entities\ChartOfAccountType;
use App\Domain\Entities\Money;
use App\Domain\Entities\ProfitAndLossRollupRow;
use App\Domain\Entities\ProfitAndLossTotals;

final class ProfitAndLossCalculator
{
    /** @param iterable<ProfitAndLossRollupRow> $rows */
    public function calculate(iterable $rows): ProfitAndLossTotals
    {
        $revenue = Money::zero();
        $costOfGoodsSold = Money::zero();
        $operatingExpenses = Money::zero();

        foreach ($rows as $row) {
            $balance = match ($row->accountType) {
                ChartOfAccountType::Revenue => $row->credit->subtract($row->debit),
                ChartOfAccountType::CostOfGoodsSold,
                ChartOfAccountType::Expense => $row->debit->subtract($row->credit),
                default => null,
            };

            if ($balance === null) {
                continue;
            }

            if ($row->accountType === ChartOfAccountType::Revenue) {
                $revenue = $revenue->add($balance);
            } elseif ($row->accountType === ChartOfAccountType::CostOfGoodsSold) {
                $costOfGoodsSold = $costOfGoodsSold->add($balance);
            } else {
                $operatingExpenses = $operatingExpenses->add($balance);
            }
        }

        $grossProfit = $revenue->subtract($costOfGoodsSold);

        return new ProfitAndLossTotals(
            $revenue,
            $costOfGoodsSold,
            $grossProfit,
            $operatingExpenses,
            $grossProfit->subtract($operatingExpenses),
        );
    }
}
