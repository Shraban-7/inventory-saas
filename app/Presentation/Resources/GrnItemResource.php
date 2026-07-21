<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\GrnItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrnItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        return $item instanceof GrnItem ? [
            'id' => $item->getKey(),
            'variant_id' => $item->product_variant_id,
            'quantity_received' => $item->quantity_received,
            'unit_cost' => $item->unit_cost,
        ] : [];
    }
}
