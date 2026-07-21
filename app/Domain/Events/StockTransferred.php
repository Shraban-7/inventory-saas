<?php

namespace App\Domain\Events;

final readonly class StockTransferred
{
    public function __construct(public int $transferId) {}
}
