<?php

namespace App\Infrastructure\Providers;

use App\Domain\Repositories\InvoiceSequenceRepository;
use App\Domain\Repositories\SalesRepository;
use App\Infrastructure\Persistence\EloquentInvoiceSequenceRepository;
use App\Infrastructure\Persistence\EloquentSalesRepository;
use Illuminate\Support\ServiceProvider;

final class SalesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InvoiceSequenceRepository::class, EloquentInvoiceSequenceRepository::class);
        $this->app->bind(SalesRepository::class, EloquentSalesRepository::class);
    }
}
