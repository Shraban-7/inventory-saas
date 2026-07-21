<?php

namespace App\Domain\Entities;

final readonly class ProfitAndLossTotals
{
    public function __construct(
        public Money $revenue,
        public Money $costOfGoodsSold,
        public Money $grossProfit,
        public Money $operatingExpenses,
        public Money $netProfit,
    ) {}

    /** @return array{revenue: string, cogs: string, gross_profit: string, operating_expenses: string, net_profit: string} */
    public function toArray(): array
    {
        return [
            'revenue' => $this->revenue->toDecimal(),
            'cogs' => $this->costOfGoodsSold->toDecimal(),
            'gross_profit' => $this->grossProfit->toDecimal(),
            'operating_expenses' => $this->operatingExpenses->toDecimal(),
            'net_profit' => $this->netProfit->toDecimal(),
        ];
    }
}
