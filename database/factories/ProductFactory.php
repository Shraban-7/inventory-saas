<?php

namespace Database\Factories;

use App\Infrastructure\Models\Category;
use App\Infrastructure\Models\Product;
use App\Infrastructure\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $category = Category::query()->create(['tenant_id' => $tenant->getKey(), 'name' => fake()->word()]);

        return [
            'tenant_id' => $tenant->getKey(),
            'category_id' => $category->getKey(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'costing_method' => 'fifo',
        ];
    }
}
