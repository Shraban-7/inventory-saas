<?php

namespace App\Application\Jobs;

use App\Application\Jobs\Concerns\RestoresTenantContext;
use App\Domain\Entities\BulkImportRowStatus;
use App\Domain\Entities\BulkImportStatus;
use App\Infrastructure\Models\BulkImport;
use App\Infrastructure\Models\User;
use App\Notifications\BulkImportFinishedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FinalizeBulkImportJob implements ShouldQueue
{
    use Queueable, RestoresTenantContext;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    public int $timeout = 60;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $importId,
        public readonly ?string $batchId = null,
    ) {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $this->withinTenant(function (): void {
            $import = BulkImport::query()->findOrFail($this->importId);
            $batch = $this->batchId !== null ? Bus::findBatch($this->batchId) : null;
            $status = $batch !== null && $batch->failedJobs > 0
                ? BulkImportStatus::Failed
                : BulkImportStatus::Completed;

            if (in_array($import->getRawOriginal('status'), [
                BulkImportStatus::Completed->value,
                BulkImportStatus::Failed->value,
            ], true)) {
                return;
            }

            $succeeded = $import->rows()->where('status', BulkImportRowStatus::Succeeded->value)->count();
            $failed = $import->rows()->where('status', BulkImportRowStatus::Failed->value)->count();
            $skipped = $import->rows()->where('status', BulkImportRowStatus::Skipped->value)->count();

            $import->forceFill([
                'status' => $status,
                'processed_rows' => $succeeded + $failed + $skipped,
                'succeeded_rows' => $succeeded,
                'failed_rows' => $failed,
                'skipped_rows' => $skipped,
                'error_code' => $status === BulkImportStatus::Failed ? 'import_processing_failed' : null,
                'completed_at' => now(),
            ])->save();

            Storage::disk($import->disk)->delete($import->path);
            $this->notify($import);
        });
    }

    public function failed(?Throwable $exception): void
    {
        $this->withinTenant(function (): void {
            $import = BulkImport::query()->find($this->importId);

            if (! $import instanceof BulkImport
                || $import->getRawOriginal('status') === BulkImportStatus::Completed->value) {
                return;
            }

            $import->forceFill([
                'status' => BulkImportStatus::Failed,
                'error_code' => 'import_finalization_failed',
                'completed_at' => now(),
            ])->save();
            $this->notify($import);
        });
    }

    private function notify(BulkImport $import): void
    {
        $user = User::query()->find($import->requested_by_user_id);

        if ($user instanceof User) {
            $user->notify(BulkImportFinishedNotification::fromImport($import));
        }
    }
}
