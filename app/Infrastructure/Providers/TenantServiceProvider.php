<?php

namespace App\Infrastructure\Providers;

use App\Application\Listeners\SeedSystemRoles;
use App\Infrastructure\Models\Tenant;
use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    public function boot(SeedSystemRoles $seedSystemRoles): void
    {
        Tenant::created(
            static fn (Tenant $tenant) => $seedSystemRoles->handle($tenant),
        );
    }
}
