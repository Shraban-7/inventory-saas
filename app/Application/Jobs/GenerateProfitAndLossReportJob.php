<?php

namespace App\Application\Jobs;

use App\Application\Actions\Reporting\GenerateProfitAndLossAction;
use App\Domain\Entities\ReportJobStatus;
use App\Infrastructure\Models\ReportJob;
use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Persistence\EloquentJournalHistoryRepository;
use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use UnexpectedValueException;

class GenerateProfitAndLossReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    public int $timeout = 60;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $reportJobId,
    ) {
        $this->onQueue('reports');
    }

    public function handle(GenerateProfitAndLossAction $action): void
    {
        $previousTenant = $this->currentTenant();
        $tenant = $this->bindTenant();

        try {
            $reportJob = ReportJob::query()->find($this->reportJobId);

            if (! $reportJob instanceof ReportJob) {
                throw (new ModelNotFoundException)->setModel(ReportJob::class, [$this->reportJobId]);
            }

            if (in_array($reportJob->getRawOriginal('status'), [
                ReportJobStatus::Completed->value,
                ReportJobStatus::Expired->value,
            ], true)) {
                return;
            }

            $reportJob->forceFill([
                'status' => ReportJobStatus::Running,
                'started_at' => $reportJob->started_at ?? now(),
                'error_code' => null,
                'error_message' => null,
            ])->save();

            $parameters = $reportJob->getAttribute('parameters');

            if (! is_array($parameters)
                || ! is_string($parameters['start'] ?? null)
                || ! is_string($parameters['end'] ?? null)
                || ! array_key_exists('branch_ids', $parameters)
                || ($parameters['branch_ids'] !== null && ! is_array($parameters['branch_ids']))) {
                throw new UnexpectedValueException('The report job parameters are invalid.');
            }

            $branchIds = $parameters['branch_ids'] === null
                ? null
                : array_values(array_map(
                    static fn (mixed $branchId): int => (int) $branchId,
                    $parameters['branch_ids'],
                ));
            $result = $action->handle(
                new DateTimeImmutable($parameters['start']),
                new DateTimeImmutable($parameters['end']),
                $branchIds,
            );

            $reportJob->forceFill([
                'status' => ReportJobStatus::Completed,
                'result' => $result->toArray(),
                'completed_at' => now(),
            ])->save();
        } finally {
            $this->clearTenant($tenant, $previousTenant);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $previousTenant = $this->currentTenant();
        $tenant = $this->bindTenant();

        try {
            $reportJob = ReportJob::query()->find($this->reportJobId);

            if (! $reportJob instanceof ReportJob
                || $reportJob->getRawOriginal('status') === ReportJobStatus::Expired->value) {
                return;
            }

            $reportJob->forceFill([
                'status' => ReportJobStatus::Failed,
                'error_code' => app()->isProduction()
                    ? 'report_generation_failed'
                    : Str::snake(class_basename($exception ?? Throwable::class)),
                'error_message' => app()->isProduction()
                    ? 'The report could not be generated.'
                    : Str::limit($exception?->getMessage() ?? 'The report could not be generated.', 1000),
                'completed_at' => now(),
            ])->save();
        } finally {
            $this->clearTenant($tenant, $previousTenant);
        }
    }

    private function bindTenant(): Tenant
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if (! $tenant instanceof Tenant) {
            throw (new ModelNotFoundException)->setModel(Tenant::class, [$this->tenantId]);
        }

        app()->instance('current_tenant', $tenant);

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('SET @current_tenant_id = ?', [$tenant->getKey()]);
        }

        return $tenant;
    }

    private function currentTenant(): ?Tenant
    {
        if (! app()->bound('current_tenant')) {
            return null;
        }

        $tenant = app()->make('current_tenant');

        return $tenant instanceof Tenant ? $tenant : null;
    }

    private function clearTenant(Tenant $tenant, ?Tenant $previousTenant): void
    {
        try {
            $this->clearReportingTenant();
        } finally {
            $this->restorePrimaryTenant($tenant, $previousTenant);
        }
    }

    private function clearReportingTenant(): void
    {
        if (app()->bound(EloquentJournalHistoryRepository::REPORTING_CONTEXT_BOUND)) {
            DB::connection('reporting')->statement('SET @current_tenant_id = NULL');
            app()->forgetInstance(EloquentJournalHistoryRepository::REPORTING_CONTEXT_BOUND);
        }
    }

    private function restorePrimaryTenant(Tenant $tenant, ?Tenant $previousTenant): void
    {
        try {
            if (in_array($tenant->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $tenant->getConnection()->statement(
                    'SET @current_tenant_id = ?',
                    [$previousTenant?->getKey()],
                );
            }
        } finally {
            app()->forgetInstance('current_tenant');

            if ($previousTenant !== null) {
                app()->instance('current_tenant', $previousTenant);
            }
        }
    }
}
