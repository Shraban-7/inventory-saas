<?php

use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\Tenant;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(['auth', 'tenant'])
        ->get('/api/test/branches', fn () => response()->json(
            Branch::query()->orderBy('id')->get(['id', 'tenant_id', 'name']),
        ));

    Route::middleware(['auth', 'tenant'])
        ->get('/api/test/branches/{branchId}', fn (int $branchId) => response()->json(
            Branch::query()->findOrFail($branchId, ['id', 'tenant_id', 'name']),
        ));
});

it('returns not found for a resource owned by another tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $branchB = Branch::factory()->create(['tenant_id' => $tenantB->getKey()]);

    actingAsTenant($tenantA->getKey());

    $this->getJson("/api/test/branches/{$branchB->getKey()}")
        ->assertNotFound();
});

it('never lists resources owned by another tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $branchA = Branch::factory()->create(['tenant_id' => $tenantA->getKey()]);
    Branch::factory()->create(['tenant_id' => $tenantB->getKey()]);

    actingAsTenant($tenantA->getKey());

    $this->getJson('/api/test/branches')
        ->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $branchA->getKey());
});
