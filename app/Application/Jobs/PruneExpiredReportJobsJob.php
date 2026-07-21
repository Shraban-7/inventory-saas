<?php

namespace App\Application\Jobs;

use App\Domain\Entities\ReportJobStatus;
use App\Infrastructure\Models\ReportJob;
use App\Infrastructure\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class PruneExpiredReportJobsJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        Tenant::query()
            ->select('id')
            ->chunkById(100, function (Collection $tenants): void {
                foreach ($tenants as $tenant) {
                    app()->instance('current_tenant', $tenant);
                    $usesMySqlSession = in_array(
                        $tenant->getConnection()->getDriverName(),
                        ['mysql', 'mariadb'],
                        true,
                    );

                    try {
                        if ($usesMySqlSession) {
                            $tenant->getConnection()->statement(
                                'SET @current_tenant_id = ?',
                                [$tenant->getKey()],
                            );
                        }

                        ReportJob::query()
                            ->whereIn('status', [
                                ReportJobStatus::Completed,
                                ReportJobStatus::Failed,
                            ])
                            ->where('expires_at', '<=', now())
                            ->eachById(function (ReportJob $reportJob): void {
                                $reportJob->forceFill([
                                    'status' => ReportJobStatus::Expired,
                                    'result' => null,
                                ])->save();
                            }, 100);
                    } finally {
                        if ($usesMySqlSession) {
                            DB::statement('SET @current_tenant_id = NULL');
                        }

                        app()->forgetInstance('current_tenant');
                    }
                }
            });
    }
}
