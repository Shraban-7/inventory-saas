<?php

use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\Category;
use App\Infrastructure\Models\Product;
use App\Infrastructure\Models\ProductVariant;
use App\Infrastructure\Models\Role;
use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

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

it('cannot create a transfer with another tenants branches', function () {
    $tenantA = Tenant::factory()->create();
    app()->instance('current_tenant', $tenantA);
    $user = User::factory()->create(['tenant_id' => $tenantA->getKey()]);
    $user->assignRole(Role::query()->where('name', 'Admin')->firstOrFail());

    $tenantB = Tenant::factory()->create();
    app()->instance('current_tenant', $tenantB);
    $from = Branch::factory()->create(['tenant_id' => $tenantB->getKey()]);
    $to = Branch::factory()->create(['tenant_id' => $tenantB->getKey()]);
    $category = Category::query()->create(['name' => 'Other']);
    $product = Product::query()->create(['category_id' => $category->getKey(), 'name' => 'Other', 'costing_method' => 'fifo']);
    $variant = ProductVariant::query()->create(['product_id' => $product->getKey(), 'sku' => 'OTHER', 'cost_price' => '1', 'sale_price' => '2']);

    $this->actingAs($user)->postJson('/api/v1/stock-transfers', [
        'from_branch_id' => $from->getKey(),
        'to_branch_id' => $to->getKey(),
        'items' => [['variant_id' => $variant->getKey(), 'quantity' => '1.0000']],
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
});
