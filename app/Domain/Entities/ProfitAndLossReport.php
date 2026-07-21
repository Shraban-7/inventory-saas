<?php

namespace App\Domain\Entities;

final readonly class ProfitAndLossReport
{
    /**
     * @param  list<int>|null  $branchIds
     */
    public function __construct(
        public string $start,
        public string $end,
        public ?array $branchIds,
        public ProfitAndLossTotals $totals,
    ) {}

    /**
     * @return array{
     *   period: array{start: string, end: string},
     *   scope: array{branch_ids: list<int>|null},
     *   revenue: string,
     *   cogs: string,
     *   gross_profit: string,
     *   operating_expenses: string,
     *   net_profit: string
     * }
     */
    public function toArray(): array
    {
        return [
            'period' => ['start' => $this->start, 'end' => $this->end],
            'scope' => ['branch_ids' => $this->branchIds],
            ...$this->totals->toArray(),
        ];
    }
}
