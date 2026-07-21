<?php

namespace App\Infrastructure\Providers;

use App\Domain\Repositories\ChartOfAccountTreeRepository;
use App\Domain\Repositories\JournalHistoryRepository;
use App\Domain\Repositories\ManualJournalSequenceRepository;
use App\Domain\Repositories\ProfitAndLossReadRepository;
use App\Infrastructure\Models\ChartOfAccount;
use App\Infrastructure\Observers\ChartOfAccountObserver;
use App\Infrastructure\Persistence\CachedChartOfAccountTreeRepository;
use App\Infrastructure\Persistence\EloquentJournalHistoryRepository;
use App\Infrastructure\Persistence\EloquentManualJournalSequenceRepository;
use App\Infrastructure\Persistence\EloquentProfitAndLossReadRepository;
use Illuminate\Support\ServiceProvider;

final class AccountingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ChartOfAccountTreeRepository::class,
            CachedChartOfAccountTreeRepository::class,
        );
        $this->app->bind(
            JournalHistoryRepository::class,
            EloquentJournalHistoryRepository::class,
        );
        $this->app->bind(
            ManualJournalSequenceRepository::class,
            EloquentManualJournalSequenceRepository::class,
        );
        $this->app->bind(
            ProfitAndLossReadRepository::class,
            EloquentProfitAndLossReadRepository::class,
        );
    }

    public function boot(): void
    {
        ChartOfAccount::observe(ChartOfAccountObserver::class);
    }
}
