<?php

use App\Infrastructure\Providers\MorphMapServiceProvider;
use App\Infrastructure\Providers\TenantServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    MorphMapServiceProvider::class,
    TenantServiceProvider::class,
];
