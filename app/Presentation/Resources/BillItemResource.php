<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\BillItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        return $item instanceof BillItem ? [
            'id' => $item->getKey(),
            'variant_id' => $item->product_variant_id,
            'tax_id' => $item->tax_id,
            'quantity' => $item->quantity,
            'unit_cost' => $item->unit_cost,
            'tax_rate_snapshot' => $item->tax_rate_snapshot,
            'line_total' => $item->line_total,
        ] : [];
    }
}
