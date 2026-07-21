<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        if (! $item instanceof InvoiceItem) {
            return [];
        }

        return [
            'id' => $item->getKey(),
            'variant_id' => $item->product_variant_id,
            'tax_id' => $item->tax_id,
            'quantity' => $item->quantity,
            'unit_price_at_sale' => $item->unit_price_at_sale,
            'cost_price_at_sale' => $item->cost_price_at_sale,
            'tax_rate_at_sale' => $item->tax_rate_at_sale,
            'line_total' => $item->line_total,
        ];
    }
}
