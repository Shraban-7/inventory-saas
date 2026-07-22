<?php

namespace App\Providers;

use App\Application\Services\BranchAuthorizationService;
use App\Infrastructure\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Configure Horizon authorization without a local-environment bypass.
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(static function ($request): bool {
            return Gate::check('viewHorizon', [$request->user()]);
        });
    }

    /**
     * Register the Horizon gate.
     *
     * Tenant-wide Admin users may access Horizon in every environment.
     */
    protected function gate(): void
    {
        Gate::define(
            'viewHorizon',
            static fn (mixed $user = null): bool => $user instanceof User
                && app(BranchAuthorizationService::class)
                    ->hasTenantWideRole($user, 'Admin'),
        );
    }
}
