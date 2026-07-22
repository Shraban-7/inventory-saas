<?php

namespace App\Application\Jobs;

use App\Application\Services\Archive\ArchiveExportService;
use App\Domain\Entities\ArchiveDataset;
use App\Domain\Entities\ArchiveExportStatus;
use App\Infrastructure\Models\ArchiveExport;
use App\Infrastructure\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Context;

class DispatchArchiveExportsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('reports');
    }

    public function handle(ArchiveExportService $archiveExportService): void
    {
        $schemaVersion = (string) config('archive.schema_version', '1');
        $cutoff = $archiveExportService->retentionCutoff();

        Tenant::query()->select('id')->orderBy('id')->chunkById(100, function ($tenants) use (
            $archiveExportService,
            $schemaVersion,
            $cutoff,
        ): void {
            foreach ($tenants as $tenant) {
                $this->withTenant((int) $tenant->getKey(), function () use (
                    $archiveExportService,
                    $schemaVersion,
                    $cutoff,
                ): void {
                    foreach (ArchiveDataset::cases() as $dataset) {
                        foreach ($archiveExportService->discoverYearsWithData($dataset, $cutoff) as $year) {
                            $export = ArchiveExport::query()->firstOrCreate(
                                [
                                    'dataset' => $dataset,
                                    'period_year' => $year,
                                    'schema_version' => $schemaVersion,
                                ],
                                [
                                    'status' => ArchiveExportStatus::Pending,
                                ],
                            );

                            if ($export->status === ArchiveExportStatus::Completed) {
                                continue;
                            }

                            ExportArchiveDatasetJob::dispatch(
                                (int) current_tenant_id(),
                                (int) $export->getKey(),
                            );
                        }
                    }
                });
            }
        });
    }

    private function withTenant(int $tenantId, callable $callback): void
    {
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant instanceof Tenant) {
            return;
        }

        $previousTenant = app()->bound('current_tenant') ? app()->make('current_tenant') : null;
        $previousTenant = $previousTenant instanceof Tenant ? $previousTenant : null;
        $previousContextTenant = Context::get('tenant_id');
        $connection = $tenant->getConnection();
        $usesMySqlSession = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true);
        $previousSessionTenantId = $usesMySqlSession
            ? $connection->scalar('SELECT @current_tenant_id')
            : null;

        app()->instance('current_tenant', $tenant);
        Context::add('tenant_id', $tenant->getKey());

        try {
            if ($usesMySqlSession) {
                $connection->statement('SET @current_tenant_id = ?', [$tenant->getKey()]);
            }

            $callback();
        } finally {
            try {
                if ($usesMySqlSession) {
                    $connection->statement('SET @current_tenant_id = ?', [$previousSessionTenantId]);
                }
            } finally {
                if ($previousTenant !== null) {
                    app()->instance('current_tenant', $previousTenant);
                } else {
                    app()->forgetInstance('current_tenant');
                }

                if ($previousContextTenant === null) {
                    Context::forget('tenant_id');
                } else {
                    Context::add('tenant_id', $previousContextTenant);
                }
            }
        }
    }
}
