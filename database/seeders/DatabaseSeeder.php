<?php

namespace Database\Seeders;

use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Demo Company',
            'slug' => 'demo-company',
        ]);

        User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
