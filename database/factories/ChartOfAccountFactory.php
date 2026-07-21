<?php

namespace Database\Factories;

use App\Domain\Entities\ChartOfAccountType;
use App\Infrastructure\Models\ChartOfAccount;
use App\Infrastructure\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChartOfAccount> */
class ChartOfAccountFactory extends Factory
{
    protected $model = ChartOfAccount::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'parent_id' => null,
            'code' => fake()->unique()->numerify('####'),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(ChartOfAccountType::cases()),
            'is_system' => false,
        ];
    }
}
