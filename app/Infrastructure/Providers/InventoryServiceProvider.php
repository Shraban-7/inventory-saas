<?php

namespace App\Infrastructure\Providers;

use App\Application\Listeners\SendStockLowNotifications;
use App\Domain\Contracts\DeferredDomainEventPublisher;
use App\Domain\Events\StockLow;
use App\Domain\Repositories\InventoryLotRepository;
use App\Domain\Repositories\StockRepository;
use App\Infrastructure\Events\AfterCommitDomainEventPublisher;
use App\Infrastructure\Persistence\EloquentInventoryLotRepository;
use App\Infrastructure\Persistence\EloquentStockRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StockRepository::class, EloquentStockRepository::class);
        $this->app->bind(InventoryLotRepository::class, EloquentInventoryLotRepository::class);
        $this->app->bind(DeferredDomainEventPublisher::class, AfterCommitDomainEventPublisher::class);
    }

    public function boot(): void
    {
        Event::listen(StockLow::class, [SendStockLowNotifications::class, 'handle']);
    }
}
