<?php

use App\Application\Jobs\DispatchJournalRollupsJob;
use App\Application\Jobs\PruneExpiredReportJobsJob;
use App\Application\Jobs\PruneIdempotencyRequestsJob;
use App\Application\Jobs\ReconcileStockLevelsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new PruneIdempotencyRequestsJob)
    ->daily()
    ->withoutOverlapping();

Schedule::job(new ReconcileStockLevelsJob)
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::job(new DispatchJournalRollupsJob, 'reports')
    ->dailyAt('02:00')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::job(new PruneExpiredReportJobsJob, 'reports')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping();
