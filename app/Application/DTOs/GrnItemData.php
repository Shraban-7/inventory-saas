<?php

namespace App\Application\DTOs;

final readonly class GrnItemData
{
    public function __construct(
        public int $variantId,
        public string $quantityReceived,
        public string $unitCost,
    ) {}
}
