<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        return $item instanceof PurchaseOrderItem ? [
            'id' => $item->getKey(),
            'variant_id' => $item->product_variant_id,
            'quantity_ordered' => $item->quantity_ordered,
            'quantity_received' => $item->quantity_received,
            'unit_cost' => $item->unit_cost,
        ] : [];
    }
}
