<?php

namespace App\Application\Jobs;

use App\Infrastructure\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use UnexpectedValueException;

class DispatchJournalRollupsJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        $lookbackDays = filter_var(
            config('accounting.rollup_lookback_days', 7),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 31]],
        );

        if ($lookbackDays === false) {
            throw new UnexpectedValueException(
                'accounting.rollup_lookback_days must be an integer between 1 and 31.',
            );
        }

        $yesterday = CarbonImmutable::yesterday();
        $dates = [];

        for ($offset = 0; $offset < $lookbackDays; $offset++) {
            $dates[] = $yesterday->subDays($offset)->toDateString();
        }

        Tenant::query()
            ->select('id')
            ->chunkById(100, function (Collection $tenants) use ($dates): void {
                foreach ($tenants as $tenant) {
                    foreach ($dates as $date) {
                        AggregateJournalRollupsJob::dispatch(
                            (int) $tenant->getKey(),
                            $date,
                        )->afterCommit();
                    }
                }
            });
    }
}
