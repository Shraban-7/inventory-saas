<?php

namespace App\Infrastructure\Providers;

use App\Domain\Repositories\InventoryLotRepository;
use App\Domain\Repositories\StockRepository;
use App\Infrastructure\Persistence\EloquentInventoryLotRepository;
use App\Infrastructure\Persistence\EloquentStockRepository;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StockRepository::class, EloquentStockRepository::class);
        $this->app->bind(InventoryLotRepository::class, EloquentInventoryLotRepository::class);
    }
}
