<?php

namespace App\Domain\Entities;

final readonly class StockLevelKey
{
    public function __construct(
        public int $variantId,
        public int $branchId,
    ) {}

    public function value(): string
    {
        return $this->variantId.':'.$this->branchId;
    }
}
