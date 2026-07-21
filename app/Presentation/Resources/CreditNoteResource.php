<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\CreditNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditNoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $creditNote = $this->resource;

        if (! $creditNote instanceof CreditNote) {
            return [];
        }

        return [
            'id' => $creditNote->getKey(),
            'branch_id' => $creditNote->branch_id,
            'customer_id' => $creditNote->customer_id,
            'invoice_id' => $creditNote->invoice_id,
            'reason' => $creditNote->reason,
            'status' => (string) $creditNote->getRawOriginal('status'),
            'total_amount' => $creditNote->total_amount,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'items' => CreditNoteItemResource::collection($this->whenLoaded('items')),
            'created_at' => $creditNote->created_at,
            'updated_at' => $creditNote->updated_at,
        ];
    }
}
