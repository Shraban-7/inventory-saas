<?php

use App\Application\Listeners\SeedSystemRoles;
use App\Infrastructure\Models\Role;
use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Models\User;

afterEach(function (): void {
    app()->forgetInstance('current_tenant');
});

it('seeds system roles and least privilege permissions for each tenant', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $admin = Role::query()->where('name', 'Admin')->firstOrFail();
    $cashier = Role::query()->where('name', 'Cashier')->firstOrFail();

    expect(Role::query()->where('is_system', true)->count())->toBe(4)
        ->and($admin->permissions)->toHaveCount(count(SeedSystemRoles::PERMISSIONS))
        ->and($admin->hasPermissionTo('invoice.void'))->toBeTrue()
        ->and($cashier->hasPermissionTo('invoice.create'))->toBeTrue()
        ->and($cashier->hasPermissionTo('invoice.void'))->toBeFalse();
});

it('assigns roles through the approved role user pivot', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $user = User::factory()->create(['tenant_id' => $tenant->getKey()]);
    $cashier = Role::query()->where('name', 'Cashier')->firstOrFail();

    $user->assignRole($cashier);

    expect($user->fresh()->hasRole('Cashier'))->toBeTrue();

    $this->assertDatabaseHas('role_user', [
        'user_id' => $user->getKey(),
        'role_id' => $cashier->getKey(),
        'branch_id' => null,
    ]);
});
