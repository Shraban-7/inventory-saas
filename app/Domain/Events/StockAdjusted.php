<?php

namespace App\Domain\Events;

final readonly class StockAdjusted
{
    public function __construct(public int $adjustmentId) {}
}
