<?php

namespace App\Presentation\Resources;

use App\Infrastructure\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $product = $this->resource;

        if (! $product instanceof Product) {
            return [];
        }

        return [
            'id' => $product->getKey(),
            'category_id' => $product->category_id,
            'name' => $product->name,
            'description' => $product->description,
            'costing_method' => $product->costing_method,
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}
