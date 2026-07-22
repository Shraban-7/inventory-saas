<?php

namespace App\Infrastructure\Events;

use App\Domain\Contracts\DeferredDomainEventPublisher;
use Illuminate\Support\Facades\DB;

final class AfterCommitDomainEventPublisher implements DeferredDomainEventPublisher
{
    public function publishAfterCommit(object $event): void
    {
        DB::afterCommit(static fn () => event($event));
    }
}
