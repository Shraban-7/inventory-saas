<?php

namespace App\Providers;

use App\Application\Contracts\DnsResolver;
use App\Infrastructure\Network\NativeDnsResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DnsResolver::class, NativeDnsResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('bulk-imports', function (Request $request): Limit {
            $user = $request->user();
            $userId = $user !== null ? $user->getAuthIdentifier() : $request->ip();
            $tenantId = $user !== null ? $user->tenant_id : 'none';

            return Limit::perMinute(10)->by("{$tenantId}:{$userId}");
        });
    }
}
