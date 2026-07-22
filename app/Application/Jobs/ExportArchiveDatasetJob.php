<?php

namespace App\Application\Jobs;

use App\Application\Jobs\Concerns\RestoresTenantContext;
use App\Application\Services\Archive\ArchiveExportService;
use App\Domain\Entities\ArchiveExportStatus;
use App\Infrastructure\Models\ArchiveExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExportArchiveDatasetJob implements ShouldQueue
{
    use Queueable;
    use RestoresTenantContext;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 300;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $archiveExportId,
    ) {
        $this->onQueue('reports');
    }

    public function handle(ArchiveExportService $archiveExportService): void
    {
        $this->withinTenant(function () use ($archiveExportService): void {
            $export = ArchiveExport::query()->find($this->archiveExportId);

            if (! $export instanceof ArchiveExport) {
                throw (new ModelNotFoundException)->setModel(ArchiveExport::class, [$this->archiveExportId]);
            }

            $archiveExportService->export($export);
        });
    }

    public function failed(?Throwable $exception): void
    {
        $this->withinTenant(function (): void {
            $export = ArchiveExport::query()->find($this->archiveExportId);

            if (! $export instanceof ArchiveExport
                || $export->status === ArchiveExportStatus::Completed
                || $export->status !== ArchiveExportStatus::Exporting) {
                return;
            }

            $export->forceFill([
                'status' => ArchiveExportStatus::Failed,
                'error_code' => 'archive_export_failed',
                'completed_at' => now(),
            ])->save();
        });
    }
}
