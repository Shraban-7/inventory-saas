<?php

namespace App\Application\DTOs;

final readonly class SupplierReturnItemData
{
    public function __construct(
        public int $variantId,
        public string $quantity,
    ) {}
}
