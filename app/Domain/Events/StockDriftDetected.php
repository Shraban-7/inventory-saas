<?php

namespace App\Domain\Events;

final readonly class StockDriftDetected
{
    public function __construct(public int $stockLevelId, public string $difference) {}
}
