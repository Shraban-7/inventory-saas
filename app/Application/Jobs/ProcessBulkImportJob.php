<?php

namespace App\Application\Jobs;

use App\Application\Jobs\Concerns\RestoresTenantContext;
use App\Domain\Entities\BulkImportRowStatus;
use App\Domain\Entities\BulkImportStatus;
use App\Infrastructure\Models\BulkImport;
use App\Infrastructure\Models\BulkImportRow;
use App\Infrastructure\Models\User;
use App\Notifications\BulkImportFinishedNotification;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class ProcessBulkImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable, RestoresTenantContext;

    public const CHUNK_SIZE = 100;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    public int $timeout = 60;

    /**
     * Bound unique lock so a crashed worker cannot permanently block re-dispatch.
     */
    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $importId,
    ) {
        $this->onQueue('imports');
    }

    public function uniqueId(): string
    {
        return $this->importId;
    }

    public function handle(): void
    {
        $this->withinTenant(function (): void {
            DB::transaction(function (): void {
                $import = BulkImport::query()
                    ->whereKey($this->importId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (in_array($import->getRawOriginal('status'), [
                    BulkImportStatus::Completed->value,
                    BulkImportStatus::Failed->value,
                ], true) || $import->batch_id !== null) {
                    return;
                }

                $import->rows()->delete();
                $import->forceFill([
                    'status' => BulkImportStatus::Running,
                    'total_rows' => 0,
                    'processed_rows' => 0,
                    'succeeded_rows' => 0,
                    'failed_rows' => 0,
                    'skipped_rows' => 0,
                    'error_code' => null,
                    'started_at' => now(),
                    'completed_at' => null,
                ])->save();

                $stream = Storage::disk($import->disk)->readStream($import->path);

                if (! is_resource($stream)) {
                    throw new RuntimeException('The stored import file could not be opened.');
                }

                try {
                    $headers = $this->readHeaders($stream, (string) $import->getRawOriginal('type'));
                    $jobs = $this->readChunkJobs($stream, $headers, $import);
                } finally {
                    fclose($stream);
                }

                $totalRows = $import->rows()->count();

                if ($jobs === []) {
                    $import->forceFill([
                        'total_rows' => $totalRows,
                        'batch_id' => (string) Str::uuid(),
                    ])->save();

                    FinalizeBulkImportJob::dispatch($this->tenantId, $this->importId)->afterCommit();

                    return;
                }

                $tenantId = $this->tenantId;
                $importId = $this->importId;
                $batch = Bus::batch($jobs)
                    ->name("bulk-import:{$importId}")
                    ->allowFailures()
                    ->finally(function (Batch $batch) use ($tenantId, $importId): void {
                        FinalizeBulkImportJob::dispatch($tenantId, $importId, $batch->id);
                    })
                    ->onQueue('imports')
                    ->dispatch();

                $import->forceFill([
                    'total_rows' => $totalRows,
                    'batch_id' => $batch->id,
                ])->save();
            });
        });
    }

    public function failed(?Throwable $exception): void
    {
        $this->withinTenant(function (): void {
            $import = BulkImport::query()->find($this->importId);

            if (! $import instanceof BulkImport
                || in_array($import->getRawOriginal('status'), [
                    BulkImportStatus::Completed->value,
                    BulkImportStatus::Failed->value,
                ], true)) {
                return;
            }

            $pending = $import->rows()
                ->where('status', BulkImportRowStatus::Pending->value)
                ->get();

            foreach ($pending as $row) {
                $row->forceFill([
                    'status' => BulkImportRowStatus::Failed,
                    'error_code' => 'import_processing_failed',
                    'error_message' => 'The import could not be processed.',
                ])->save();
            }

            $failedRows = $import->rows()->where('status', BulkImportRowStatus::Failed->value)->count();
            $import->forceFill([
                'status' => BulkImportStatus::Failed,
                'processed_rows' => $failedRows,
                'failed_rows' => $failedRows,
                'error_code' => 'import_processing_failed',
                'completed_at' => now(),
            ])->save();

            Storage::disk($import->disk)->delete($import->path);
            $user = User::query()->find($import->requested_by_user_id);

            if ($user instanceof User) {
                $user->notify(BulkImportFinishedNotification::fromImport($import));
            }
        });
    }

    /**
     * @param  resource  $stream
     * @return list<string>
     */
    private function readHeaders($stream, string $type): array
    {
        $configured = config("imports.headers.{$type}");

        if (! is_array($configured) || ! array_is_list($configured)) {
            throw new UnexpectedValueException("CSV headers are not configured for import type [{$type}].");
        }

        $expected = array_map('strval', $configured);
        $headers = fgetcsv($stream, null, ',', '"', '');

        if ($headers === false) {
            throw new UnexpectedValueException('The CSV file is empty.');
        }

        $headers = array_map(
            static fn (?string $header): string => trim((string) $header),
            $headers,
        );

        $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");

        if ($headers !== $expected) {
            throw new UnexpectedValueException('The CSV header does not match the documented import format.');
        }

        return $expected;
    }

    /**
     * @param  resource  $stream
     * @param  list<string>  $headers
     * @return list<ProcessBulkImportChunkJob>
     */
    private function readChunkJobs($stream, array $headers, BulkImport $import): array
    {
        $jobs = [];
        $chunk = [];
        $rowNumber = 1;

        while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rowNumber++;
            $row = BulkImportRow::query()->create([
                'tenant_id' => $this->tenantId,
                'bulk_import_id' => $import->getKey(),
                'row_number' => $rowNumber,
                'status' => BulkImportRowStatus::Pending,
            ]);
            $chunk[] = [
                'row_id' => (int) $row->getKey(),
                'row_number' => $rowNumber,
                'values' => $values,
            ];

            if (count($chunk) === self::CHUNK_SIZE) {
                $jobs[] = $this->chunkJob($headers, $chunk, $import);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $jobs[] = $this->chunkJob($headers, $chunk, $import);
        }

        return $jobs;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array{row_id: int, row_number: int, values: list<string|null>}>  $rows
     */
    private function chunkJob(array $headers, array $rows, BulkImport $import): ProcessBulkImportChunkJob
    {
        return (new ProcessBulkImportChunkJob(
            $this->tenantId,
            $this->importId,
            (int) $import->requested_by_user_id,
            (string) $import->getRawOriginal('type'),
            $headers,
            $rows,
        ))->afterCommit();
    }
}
