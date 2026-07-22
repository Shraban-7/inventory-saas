<?php

namespace App\Infrastructure\Providers;

use App\Application\Listeners\RecordQueueHeartbeat;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ObservabilityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach ([
            Looping::class,
            JobProcessing::class,
            JobProcessed::class,
            JobExceptionOccurred::class,
        ] as $event) {
            Event::listen($event, [RecordQueueHeartbeat::class, 'handle']);
        }
    }
}
