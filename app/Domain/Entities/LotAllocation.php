<?php

namespace App\Domain\Entities;

use DateTimeImmutable;

final readonly class LotAllocation
{
    public function __construct(
        public int $lotId,
        public Quantity $quantity,
        public string $unitCost,
        public DateTimeImmutable $receivedAt,
    ) {}

    public function totalCost(): Money
    {
        return Money::quantityTimesPrice($this->quantity->toDecimal(), $this->unitCost);
    }
}
