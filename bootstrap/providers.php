<?php

use App\Infrastructure\Providers\AccountingServiceProvider;
use App\Infrastructure\Providers\InventoryServiceProvider;
use App\Infrastructure\Providers\MorphMapServiceProvider;
use App\Infrastructure\Providers\ObservabilityServiceProvider;
use App\Infrastructure\Providers\PurchasingServiceProvider;
use App\Infrastructure\Providers\SalesServiceProvider;
use App\Infrastructure\Providers\TenantServiceProvider;
use App\Infrastructure\Providers\WebhookServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AccountingServiceProvider::class,
    InventoryServiceProvider::class,
    MorphMapServiceProvider::class,
    ObservabilityServiceProvider::class,
    PurchasingServiceProvider::class,
    SalesServiceProvider::class,
    TenantServiceProvider::class,
    WebhookServiceProvider::class,
    AppServiceProvider::class,
    HorizonServiceProvider::class,
];
