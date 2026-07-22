<?php

namespace App\Infrastructure\Providers;

use App\Application\Listeners\CreateWebhookDeliveries;
use App\Domain\Events\CreditNoteApproved;
use App\Domain\Events\GoodsReceived;
use App\Domain\Events\InvoiceCreated;
use App\Domain\Events\InvoiceVoided;
use App\Domain\Events\StockLow;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class WebhookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach ([
            InvoiceCreated::class,
            InvoiceVoided::class,
            GoodsReceived::class,
            CreditNoteApproved::class,
            StockLow::class,
        ] as $event) {
            Event::listen($event, [CreateWebhookDeliveries::class, 'handle']);
        }
    }
}
