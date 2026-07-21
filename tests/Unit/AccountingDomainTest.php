<?php

use App\Application\Actions\Reporting\GenerateProfitAndLossAction;
use App\Domain\Entities\ChartOfAccountType;
use App\Domain\Entities\ProfitAndLossRollupRow;
use App\Domain\Repositories\ManualJournalSequenceRepository;
use App\Domain\Repositories\ProfitAndLossReadRepository;
use App\Domain\Services\ManualJournalNumberService;
use App\Domain\Services\ProfitAndLossCalculator;

it('calculates exact profit and loss decimals with contra balances and negative results', function () {
    $totals = (new ProfitAndLossCalculator)->calculate([
        new ProfitAndLossRollupRow(ChartOfAccountType::Revenue, '12.34', '100.00'),
        new ProfitAndLossRollupRow(ChartOfAccountType::Revenue, '20.00', '0.00'),
        new ProfitAndLossRollupRow(ChartOfAccountType::CostOfGoodsSold, '90.00', '5.55'),
        new ProfitAndLossRollupRow(ChartOfAccountType::Expense, '12.00', '2.25'),
        new ProfitAndLossRollupRow(ChartOfAccountType::Asset, '999.99', '0.00'),
    ]);

    expect($totals->toArray())->toBe([
        'revenue' => '67.66',
        'cogs' => '84.45',
        'gross_profit' => '-16.79',
        'operating_expenses' => '9.75',
        'net_profit' => '-26.54',
    ]);
});

it('preserves negative expense balances as contra expenses', function () {
    $totals = (new ProfitAndLossCalculator)->calculate([
        new ProfitAndLossRollupRow(ChartOfAccountType::Revenue, '0.00', '10.00'),
        new ProfitAndLossRollupRow(ChartOfAccountType::Expense, '1.00', '4.00'),
    ]);

    expect($totals->toArray())->toBe([
        'revenue' => '10.00',
        'cogs' => '0.00',
        'gross_profit' => '10.00',
        'operating_expenses' => '-3.00',
        'net_profit' => '13.00',
    ]);
});

it('formats manual journal numbers from exactly one repository sequence invocation', function () {
    $repository = new class implements ManualJournalSequenceRepository
    {
        /** @var list<int> */
        public array $years = [];

        public function next(int $year): int
        {
            $this->years[] = $year;

            return 42;
        }
    };

    expect((new ManualJournalNumberService($repository))->next(2026))
        ->toBe('JRN-MAN-2026-00042')
        ->and($repository->years)->toBe([2026]);
});

it('rejects inverted report dates before invoking the repository', function () {
    $repository = new class implements ProfitAndLossReadRepository
    {
        public int $calls = 0;

        public function totals(DateTimeImmutable $start, DateTimeImmutable $end, ?array $branchIds): array
        {
            $this->calls++;

            return [];
        }
    };
    $action = new GenerateProfitAndLossAction($repository, new ProfitAndLossCalculator);

    expect(fn () => $action->handle(
        new DateTimeImmutable('2026-07-31'),
        new DateTimeImmutable('2026-07-01'),
        [1],
    ))->toThrow(InvalidArgumentException::class)
        ->and($repository->calls)->toBe(0);
});

it('passes a deterministic exact branch scope to the report repository', function () {
    $repository = new class implements ProfitAndLossReadRepository
    {
        /** @var array{string, string, list<int>|null}|null */
        public ?array $arguments = null;

        public function totals(DateTimeImmutable $start, DateTimeImmutable $end, ?array $branchIds): array
        {
            $this->arguments = [$start->format('Y-m-d'), $end->format('Y-m-d'), $branchIds];

            return [];
        }
    };
    $action = new GenerateProfitAndLossAction($repository, new ProfitAndLossCalculator);

    $report = $action->handle(
        new DateTimeImmutable('2026-07-01'),
        new DateTimeImmutable('2026-07-31'),
        [7, 3, 7],
    );

    expect($repository->arguments)->toBe(['2026-07-01', '2026-07-31', [3, 7]])
        ->and($report->branchIds)->toBe([3, 7]);
});
