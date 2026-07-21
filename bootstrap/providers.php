<?php

use App\Infrastructure\Providers\InventoryServiceProvider;
use App\Infrastructure\Providers\MorphMapServiceProvider;
use App\Infrastructure\Providers\PurchasingServiceProvider;
use App\Infrastructure\Providers\SalesServiceProvider;
use App\Infrastructure\Providers\TenantServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    InventoryServiceProvider::class,
    MorphMapServiceProvider::class,
    PurchasingServiceProvider::class,
    SalesServiceProvider::class,
    TenantServiceProvider::class,
];
