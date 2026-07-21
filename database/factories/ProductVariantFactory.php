<?php

namespace Database\Factories;

use App\Infrastructure\Models\Product;
use App\Infrastructure\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $product = Product::factory()->create();

        return [
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->getKey(),
            'sku' => fake()->unique()->bothify('SKU-####-????'),
            'cost_price' => '10.0000',
            'sale_price' => '15.0000',
            'reorder_point' => 0,
        ];
    }
}
