<?php

use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(['auth', 'tenant'])
        ->get('/api/test/tenant-context', fn () => response()->json([
            'tenant_id' => current_tenant_id(),
            'db_session_tenant_id' => in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
                ? (int) DB::scalar('SELECT @current_tenant_id')
                : null,
        ]));
});

afterEach(function (): void {
    app()->forgetInstance('current_tenant');
});

it('scopes users to the current tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenantA->getKey()]);
    User::factory()->create(['tenant_id' => $tenantB->getKey()]);

    app()->instance('current_tenant', $tenantA);

    expect(User::query()->get())
        ->toHaveCount(1)
        ->first()->id->toBe($userA->getKey());
});

it('enforces email uniqueness within a tenant only', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    User::factory()->create([
        'tenant_id' => $tenantA->getKey(),
        'email' => 'shared@example.com',
    ]);

    User::factory()->create([
        'tenant_id' => $tenantB->getKey(),
        'email' => 'shared@example.com',
    ]);

    expect(fn () => User::factory()->create([
        'tenant_id' => $tenantA->getKey(),
        'email' => 'shared@example.com',
    ]))->toThrow(QueryException::class);
});

it('rejects unauthenticated protected api requests', function () {
    $this->getJson('/api/test/tenant-context')
        ->assertUnauthorized();
});

it('rejects authenticated users whose tenant cannot be resolved', function () {
    $user = User::factory()->make(['tenant_id' => PHP_INT_MAX]);

    $this->actingAs($user)
        ->getJson('/api/test/tenant-context')
        ->assertUnauthorized();
});

it('binds a valid tenant for a protected api request', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->getKey()]);

    $response = $this->actingAs($user)
        ->getJson('/api/test/tenant-context')
        ->assertSuccessful()
        ->assertJsonPath('tenant_id', $tenant->getKey());

    if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $response->assertJsonPath('db_session_tenant_id', $tenant->getKey());
    }

    expect(app()->bound('current_tenant'))->toBeFalse();
});
