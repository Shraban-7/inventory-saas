<?php

namespace App\Application\Actions\Reporting;

use App\Domain\Entities\ProfitAndLossReport;
use App\Domain\Repositories\ProfitAndLossReadRepository;
use App\Domain\Services\ProfitAndLossCalculator;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class GenerateProfitAndLossAction
{
    public function __construct(
        private ProfitAndLossReadRepository $repository,
        private ProfitAndLossCalculator $calculator,
    ) {}

    /**
     * @param  list<int>|null  $branchIds
     */
    public function handle(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?array $branchIds,
    ): ProfitAndLossReport {
        if ($start > $end) {
            throw new InvalidArgumentException('The report start date must not be after the end date.');
        }

        if ($branchIds !== null) {
            $branchIds = array_values(array_unique($branchIds));
            sort($branchIds, SORT_NUMERIC);
        }

        $rows = $this->repository->totals($start, $end, $branchIds);

        return new ProfitAndLossReport(
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $branchIds,
            $this->calculator->calculate($rows),
        );
    }
}
