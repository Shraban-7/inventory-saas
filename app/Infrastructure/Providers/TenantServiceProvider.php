<?php

namespace App\Infrastructure\Providers;

use App\Application\Listeners\SeedDefaultChartOfAccounts;
use App\Application\Listeners\SeedSystemRoles;
use App\Infrastructure\Models\Tenant;
use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    public function boot(
        SeedSystemRoles $seedSystemRoles,
        SeedDefaultChartOfAccounts $seedDefaultChartOfAccounts,
    ): void {
        Tenant::created(
            static fn (Tenant $tenant) => $seedSystemRoles->handle($tenant),
        );

        Tenant::created(
            static fn (Tenant $tenant) => $seedDefaultChartOfAccounts->handle($tenant),
        );
    }
}
