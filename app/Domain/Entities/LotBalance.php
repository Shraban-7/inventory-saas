<?php

namespace App\Domain\Entities;

use DateTimeImmutable;

final class LotBalance
{
    public function __construct(
        public readonly int $id,
        public readonly int $variantId,
        public readonly int $branchId,
        public Quantity $quantity,
        public readonly string $unitCost,
        public readonly DateTimeImmutable $receivedAt,
    ) {}
}
