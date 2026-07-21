<?php

namespace Database\Factories;

use App\Infrastructure\Models\Supplier;
use App\Infrastructure\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'contact_name' => fake()->optional()->name(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'address' => fake()->optional()->passthrough([
                'line_1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'country' => fake()->countryCode(),
            ]),
        ];
    }
}
