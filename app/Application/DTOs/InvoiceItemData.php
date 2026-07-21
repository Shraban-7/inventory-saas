<?php

namespace App\Application\DTOs;

final readonly class InvoiceItemData
{
    public function __construct(
        public int $variantId,
        public string $quantity,
        public string $unitPrice,
        public ?int $taxId,
    ) {}
}
