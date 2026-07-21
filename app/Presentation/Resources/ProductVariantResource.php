<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $variant = $this->resource;

        if (! $variant instanceof ProductVariant) {
            return [];
        }

        return [
            'id' => $variant->getKey(),
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'cost_price' => $variant->cost_price,
            'sale_price' => $variant->sale_price,
            'reorder_point' => $variant->reorder_point,
            'attribute_values' => $this->whenLoaded('attributeValues'),
            'stock_levels' => $this->whenLoaded('stockLevels'),
        ];
    }
}
