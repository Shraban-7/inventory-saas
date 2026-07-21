<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\JournalEntry;
use Illuminate\Http\Request;

class JournalEntryDetailResource extends JournalEntryResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $entry = $this->resource;

        if (! $entry instanceof JournalEntry) {
            return [];
        }

        return [
            ...parent::toArray($request),
            'lines' => JournalEntryLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}
