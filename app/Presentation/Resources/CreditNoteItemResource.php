<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\CreditNoteItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditNoteItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        if (! $item instanceof CreditNoteItem) {
            return [];
        }

        return [
            'id' => $item->getKey(),
            'variant_id' => $item->product_variant_id,
            'tax_id' => $item->tax_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'cost_price_at_return' => $item->cost_price_at_return,
            'tax_rate_at_return' => $item->tax_rate_at_return,
            'line_total' => $item->line_total,
            'cost_total_at_return' => $item->cost_total_at_return,
        ];
    }
}
