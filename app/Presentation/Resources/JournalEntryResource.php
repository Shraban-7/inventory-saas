<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\JournalEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $entry = $this->resource;

        if (! $entry instanceof JournalEntry) {
            return [];
        }

        $branch = $entry->relationLoaded('branch') ? $entry->branch : null;

        return [
            'id' => $entry->getKey(),
            'branch_id' => $entry->branch_id,
            'journal_entry_number' => $entry->journal_entry_number,
            'description' => $entry->description,
            'reference_type' => $entry->reference_type,
            'reference_id' => $entry->reference_id,
            'posted_at' => CarbonImmutable::parse($entry->posted_at)->toDateString(),
            'branch' => $branch instanceof Branch ? [
                'id' => $branch->getKey(),
                'name' => $branch->name,
            ] : null,
            'created_at' => $entry->created_at,
        ];
    }
}
