<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\ProfitAndLossRollupRow;
use DateTimeImmutable;

interface ProfitAndLossReadRepository
{
    /**
     * @param  list<int>|null  $branchIds
     * @return list<ProfitAndLossRollupRow>
     */
    public function totals(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?array $branchIds,
    ): array;
}
