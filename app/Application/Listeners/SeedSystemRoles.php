<?php

namespace App\Application\Listeners;

use App\Infrastructure\Models\Permission;
use App\Infrastructure\Models\Role;
use App\Infrastructure\Models\Tenant;
use Spatie\Permission\PermissionRegistrar;

class SeedSystemRoles
{
    /** @var list<string> */
    public const PERMISSIONS = [
        'invoice.create',
        'invoice.void',
        'invoice.view',
        'report.view',
        'stock.adjust',
        'stock.transfer',
        'product.manage',
        'purchase.create',
        'purchase.receive',
    ];

    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'Admin' => self::PERMISSIONS,
        'Manager' => [
            'invoice.create',
            'invoice.view',
            'report.view',
            'stock.adjust',
            'stock.transfer',
            'product.manage',
            'purchase.create',
            'purchase.receive',
        ],
        'Cashier' => [
            'invoice.create',
            'invoice.view',
        ],
        'Accountant' => [
            'invoice.view',
            'report.view',
        ],
    ];

    public function handle(Tenant $tenant): void
    {
        $previousTenant = app()->bound('current_tenant')
            ? app()->make('current_tenant')
            : null;

        app()->instance('current_tenant', $tenant);

        try {
            $permissions = collect(self::PERMISSIONS)
                ->mapWithKeys(function (string $name): array {
                    $permission = Permission::findOrCreate($name, 'web');

                    return [$name => $permission];
                });

            foreach (self::ROLE_PERMISSIONS as $name => $permissionNames) {
                $role = Role::query()->firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['tenant_id' => $tenant->getKey(), 'is_system' => true],
                );

                $role->syncPermissions($permissions->only($permissionNames)->values());
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } finally {
            if ($previousTenant instanceof Tenant) {
                app()->instance('current_tenant', $previousTenant);
            } else {
                app()->forgetInstance('current_tenant');
            }
        }
    }
}
