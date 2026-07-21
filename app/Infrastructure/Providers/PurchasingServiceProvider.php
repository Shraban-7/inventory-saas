<?php

namespace App\Infrastructure\Providers;

use App\Application\Contracts\PurchasingOperationLock;
use App\Domain\Repositories\PurchasingSequenceRepository;
use App\Infrastructure\Locking\DatabaseSafePurchasingOperationLock;
use App\Infrastructure\Persistence\EloquentPurchasingSequenceRepository;
use Illuminate\Support\ServiceProvider;

final class PurchasingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PurchasingSequenceRepository::class,
            EloquentPurchasingSequenceRepository::class,
        );
        $this->app->bind(
            PurchasingOperationLock::class,
            DatabaseSafePurchasingOperationLock::class,
        );
    }
}
