<?php

use App\Infrastructure\Providers\InventoryServiceProvider;
use App\Infrastructure\Providers\MorphMapServiceProvider;
use App\Infrastructure\Providers\SalesServiceProvider;
use App\Infrastructure\Providers\TenantServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    InventoryServiceProvider::class,
    MorphMapServiceProvider::class,
    SalesServiceProvider::class,
    TenantServiceProvider::class,
];
