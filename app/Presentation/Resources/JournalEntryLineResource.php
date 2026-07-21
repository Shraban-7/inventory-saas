<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\ChartOfAccount;
use App\Infrastructure\Models\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $line = $this->resource;

        if (! $line instanceof JournalEntryLine) {
            return [];
        }

        $account = $line->relationLoaded('account') ? $line->account : null;

        return [
            'id' => $line->getKey(),
            'coa_id' => $line->coa_id,
            'debit' => $line->debit,
            'credit' => $line->credit,
            'description' => $line->description,
            'account' => $account instanceof ChartOfAccount ? [
                'id' => $account->getKey(),
                'code' => $account->code,
                'name' => $account->name,
                'type' => (string) $account->getRawOriginal('type'),
            ] : null,
            'created_at' => $line->created_at,
        ];
    }
}
