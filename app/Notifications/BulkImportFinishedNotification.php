<?php

namespace App\Notifications;

use App\Infrastructure\Models\BulkImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BulkImportFinishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $importId,
        public readonly string $status,
        public readonly int $totalRows,
        public readonly int $succeededRows,
        public readonly int $failedRows,
        public readonly int $skippedRows,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, int|string> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'import_id' => $this->importId,
            'status' => $this->status,
            'total_rows' => $this->totalRows,
            'succeeded_rows' => $this->succeededRows,
            'failed_rows' => $this->failedRows,
            'skipped_rows' => $this->skippedRows,
        ];
    }

    public static function fromImport(BulkImport $import): self
    {
        return new self(
            (string) $import->getKey(),
            (string) $import->getRawOriginal('status'),
            (int) $import->total_rows,
            (int) $import->succeeded_rows,
            (int) $import->failed_rows,
            (int) $import->skipped_rows,
        );
    }
}
