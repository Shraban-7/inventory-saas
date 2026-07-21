<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\StockAdjustment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $adjustment = $this->resource;

        if (! $adjustment instanceof StockAdjustment) {
            return [];
        }

        return [
            'id' => $adjustment->getKey(),
            'variant_id' => $adjustment->product_variant_id,
            'branch_id' => $adjustment->branch_id,
            'quantity_delta' => $adjustment->quantity_delta,
            'type' => $adjustment->type,
            'reason' => $adjustment->reason,
        ];
    }
}
