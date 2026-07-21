<?php

namespace App\Application\Actions\Inventory;

use App\Application\DTOs\ProductData;
use App\Infrastructure\Models\Product;
use Illuminate\Support\Facades\DB;

class CreateProductAction
{
    public function handle(ProductData $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $product = Product::query()->create([
                'category_id' => $data->categoryId,
                'name' => $data->name,
                'description' => $data->description,
                'costing_method' => $data->costingMethod,
            ]);

            foreach ($data->variants as $variantData) {
                $attributeValueIds = $variantData['attribute_value_ids'] ?? [];
                unset($variantData['attribute_value_ids']);
                $variant = $product->variants()->create($variantData);

                if ($attributeValueIds !== []) {
                    $variant->attributeValues()->attach(
                        collect($attributeValueIds)->mapWithKeys(fn (int $id): array => [$id => ['tenant_id' => current_tenant_id()]])->all(),
                    );
                }
            }

            return $product->load('variants.attributeValues');
        });
    }
}
