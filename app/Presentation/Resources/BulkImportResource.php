<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\BulkImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BulkImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $import = $this->resource;

        if (! $import instanceof BulkImport) {
            return [];
        }

        return [
            'id' => $import->getKey(),
            'job_id' => $import->getKey(),
            'type' => (string) $import->getRawOriginal('type'),
            'status' => (string) $import->getRawOriginal('status'),
            'counts' => [
                'total' => $import->total_rows,
                'processed' => $import->processed_rows,
                'succeeded' => $import->succeeded_rows,
                'failed' => $import->failed_rows,
                'skipped' => $import->skipped_rows,
            ],
            'error_code' => $import->error_code,
            'errors_url' => route('bulk.imports.errors', ['bulkImportId' => $import->getKey()]),
            'timestamps' => [
                'started_at' => $import->started_at,
                'completed_at' => $import->completed_at,
                'created_at' => $import->created_at,
                'updated_at' => $import->updated_at,
            ],
        ];
    }
}
