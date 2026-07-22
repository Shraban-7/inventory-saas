<?php

namespace App\Domain\Contracts;

interface DeferredDomainEventPublisher
{
    public function publishAfterCommit(object $event): void;
}
