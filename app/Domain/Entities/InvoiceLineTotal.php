<?php

namespace App\Domain\Entities;

final readonly class InvoiceLineTotal
{
    public function __construct(
        public int $variantId,
        public string $quantity,
        public string $unitPrice,
        public string $costPrice,
        public ?int $taxId,
        public ?string $taxRate,
        public ?int $taxAccountId,
        public Money $net,
        public Money $tax,
        public Money $gross,
        public Money $cost,
    ) {}
}
