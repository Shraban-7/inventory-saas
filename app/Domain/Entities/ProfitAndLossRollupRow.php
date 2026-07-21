<?php

namespace App\Domain\Entities;

final readonly class ProfitAndLossRollupRow
{
    public Money $debit;

    public Money $credit;

    public function __construct(
        public ChartOfAccountType $accountType,
        string $debit,
        string $credit,
    ) {
        $this->debit = Money::fromDecimal($debit);
        $this->credit = Money::fromDecimal($credit);
    }
}
