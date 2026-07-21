<?php

namespace App\Application\Actions\Inventory;

use App\Infrastructure\Models\Product;
use App\Infrastructure\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class AddProductVariantAction
{
    /** @param array{sku: string, barcode?: string|null, cost_price: string, sale_price: string, reorder_point?: int, attribute_value_ids?: list<int>} $data */
    public function handle(Product $product, array $data): ProductVariant
    {
        return DB::transaction(function () use ($product, $data): ProductVariant {
            $attributeValueIds = $data['attribute_value_ids'] ?? [];
            unset($data['attribute_value_ids']);
            $variant = $product->variants()->create($data);

            if ($attributeValueIds !== []) {
                $variant->attributeValues()->attach(
                    collect($attributeValueIds)->mapWithKeys(fn (int $id): array => [$id => ['tenant_id' => current_tenant_id()]])->all(),
                );
            }

            return $variant->load('attributeValues');
        });
    }
}
