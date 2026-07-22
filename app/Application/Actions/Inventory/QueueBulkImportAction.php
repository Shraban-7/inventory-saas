<?php

namespace App\Application\Actions\Inventory;

use App\Application\Jobs\ProcessBulkImportJob;
use App\Domain\Entities\BulkImportStatus;
use App\Domain\Entities\BulkImportType;
use App\Infrastructure\Models\BulkImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class QueueBulkImportAction
{
    public function handle(UploadedFile $file, BulkImportType $type, int $requestedByUserId): BulkImport
    {
        $disk = (string) config('imports.disk', 'local');
        $path = $file->store('bulk-imports/'.current_tenant_id(), $disk);

        if (! is_string($path)) {
            throw new RuntimeException('The import file could not be stored.');
        }

        try {
            return DB::transaction(function () use ($disk, $path, $requestedByUserId, $type): BulkImport {
                $import = BulkImport::query()->create([
                    'tenant_id' => current_tenant_id(),
                    'requested_by_user_id' => $requestedByUserId,
                    'type' => $type,
                    'status' => BulkImportStatus::Queued,
                    'disk' => $disk,
                    'path' => $path,
                ]);

                ProcessBulkImportJob::dispatch(
                    current_tenant_id(),
                    (string) $import->getKey(),
                )->afterCommit();

                return $import;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }
}
