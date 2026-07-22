<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\BulkImportRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BulkImportRowErrorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        if (! $row instanceof BulkImportRow) {
            return [];
        }

        return [
            'row' => $row->row_number,
            'code' => $row->error_code,
            'message' => $row->error_message,
        ];
    }
}
